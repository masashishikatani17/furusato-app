<?php

namespace Tests\Feature\Signup;

use App\Models\Company;
use App\Models\User;
use App\Services\Billing\IssueInvoiceService;
use App\Services\BillingRobo\BillingRoboClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SignupInitialBillingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_rejects_invalid_email_format_on_server_side(): void
    {
        $response = $this->from(route('signup.show'))
            ->post(route('signup.submit'), [
                'company_name' => '株式会社テスト',
                'branch_name' => '東京支店',
                'owner_name' => '山田 太郎',
                'email' => 'teishi@teishi',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'payment_method' => 'クレジットカード',
                'quantity' => 1,
            ]);

        $response->assertRedirect(route('signup.show'));
        $response->assertSessionHasErrors('email');
    }

    public function test_signup_external_api_failure_returns_to_signup_without_500_and_rolls_back(): void
    {
        $mock = \Mockery::mock(IssueInvoiceService::class);
        $mock->shouldReceive('issueInitial')->once()->andThrow(new RuntimeException('demand作成失敗'));
        $this->app->instance(IssueInvoiceService::class, $mock);

        $response = $this->from(route('signup.show'))
            ->post(route('signup.submit'), [
                'company_name' => '株式会社テスト',
                'branch_name' => '東京支店',
                'owner_name' => '山田 太郎',
                'email' => 'owner@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'payment_method' => 'クレジットカード',
                'quantity' => 1,
            ]);

        $response->assertRedirect(route('signup.show'));
        $response->assertSessionHasErrors('signup');
        $response->assertSessionHasInput('company_name', '株式会社テスト');

        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('subscription_invoices', 0);
    }

    public function test_issue_initial_sets_billing_individual_code_on_demand(): void
    {
        $company = Company::query()->create([
            'name' => '株式会社テスト',
            'branch_name' => '東京支店',
        ]);

        $owner = User::query()->create([
            'company_id' => (int)$company->id,
            'name' => '山田 太郎',
            'email' => 'owner@example.com',
            'password' => bcrypt('password123'),
            'role' => 'owner',
            'is_active' => true,
        ]);
        $company->owner_user_id = (int)$owner->id;
        $company->save();

        $mock = \Mockery::mock(BillingRoboClient::class);
        $mock->shouldReceive('billingBulkUpsert')->once()->withArgs(function (array $billings): bool {
            $individual = $billings[0]['individual'][0] ?? [];

            return ($individual['code'] ?? null) === 'FURU-TEST-01'
                && ($individual['name'] ?? null) === '東京支店'
                && ($individual['address1'] ?? null) === '株式会社テスト 山田 太郎 御中'
                && ($individual['email'] ?? null) === 'owner@example.com';
        })->andReturn(['billing' => [['error_code' => 0]]]);

        $mock->shouldReceive('demandBulkUpsert')->once()->withArgs(function (array $demands): bool {
            return ($demands[0]['billing_individual_code'] ?? null) === 'FURU-TEST-01';
        })->andReturn(['demand' => [['error_code' => 0]]]);

        $mock->shouldReceive('demandBulkIssueBillSelect')->once()->andReturn([
            'bill' => [['number' => 'BILL-TEST-1']],
        ]);

        $service = new IssueInvoiceService($mock);
        $invoice = $service->issueInitial($company, 'FURU-TEST', 'credit', 1);

        $this->assertSame('issued', $invoice->status);
        $this->assertSame('BILL-TEST-1', $invoice->bill_number);
    }
}