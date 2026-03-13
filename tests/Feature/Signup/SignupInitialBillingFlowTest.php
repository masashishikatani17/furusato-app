<?php

namespace Tests\Feature\Signup;

use App\Models\Company;
use App\Models\CompanyBillingSetting;
use App\Models\SubscriptionInvoice;
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

    public function test_signup_requires_debit_bank_fields_when_payment_method_is_debit(): void
    {
        $response = $this->from(route('signup.show'))
            ->post(route('signup.submit'), [
                'company_name' => '株式会社テスト',
                'branch_name' => '東京支店',
                'owner_name' => '山田 太郎',
                'email' => 'owner@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'payment_method' => 'キャッシュカード',
                'quantity' => 1,
            ]);

        $response->assertRedirect(route('signup.show'));
        $response->assertSessionHasErrors([
            'bank_account_type',
            'bank_code',
            'branch_code',
            'bank_account_number',
            'bank_account_name',
        ]);
    }

    public function test_signup_validates_yucho_branch_and_account_length_for_debit(): void
    {
        $response = $this->from(route('signup.show'))
            ->post(route('signup.submit'), [
                'company_name' => '株式会社テスト',
                'branch_name' => '東京支店',
                'owner_name' => '山田 太郎',
                'email' => 'owner@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'payment_method' => 'キャッシュカード',
                'quantity' => 1,
                'bank_account_type' => '1',
                'bank_code' => '9900',
                'branch_code' => '123',
                'bank_account_number' => '1234567',
                'bank_account_name' => 'ﾔﾏﾀﾞｰ TARO',
            ]);

        $response->assertRedirect(route('signup.show'));
        $response->assertSessionHasErrors(['branch_code', 'bank_account_number']);
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
        $this->assertDatabaseCount('company_billing_settings', 0);
    }

    public function test_signup_saves_company_billing_settings_for_debit(): void
    {
        $mock = \Mockery::mock(IssueInvoiceService::class);
        $mock->shouldReceive('issueInitial')->once()->andReturn(new SubscriptionInvoice());
        $this->app->instance(IssueInvoiceService::class, $mock);

        $response = $this->post(route('signup.submit'), [
            'company_name' => '株式会社テスト',
            'branch_name' => '東京支店',
            'owner_name' => '山田 太郎',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'payment_method' => 'キャッシュカード',
            'quantity' => 1,
            'bank_account_type' => '1',
            'bank_code' => '9900',
            'branch_code' => '12345',
            'bank_account_number' => '12345678',
            'bank_account_name' => 'ﾔﾏﾀﾞｰ TARO',
        ]);

        $response->assertRedirect(route('login'));

        $company = Company::query()->firstOrFail();
        $setting = CompanyBillingSetting::query()->where('company_id', (int)$company->id)->first();

        $this->assertNotNull($setting);
        $this->assertSame('debit', $setting->payment_method);
        $this->assertSame('9900', $setting->bank_code);
        $this->assertSame('12345', $setting->branch_code);
        $this->assertSame('12345678', $setting->bank_account_number);
        $this->assertSame('ﾔﾏﾀﾞｰ TARO', $setting->bank_account_name);
    }

    public function test_issue_initial_sets_billing_individual_code_on_demand(): void
    {
        $company = Company::query()->create([
            'name' => '株式会社テスト',
            'branch_name' => '東京支店',
        ]);

        CompanyBillingSetting::query()->create([
            'company_id' => (int)$company->id,
            'payment_method' => 'credit',
            'billing_code' => 'FURU-TEST',
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

    public function test_issue_initial_debit_creates_payment_and_assigns_payment_method_code_to_demand(): void
    {
        $company = Company::query()->create([
            'name' => '株式会社テスト',
            'branch_name' => '東京支店',
        ]);

        CompanyBillingSetting::query()->create([
            'company_id' => (int)$company->id,
            'payment_method' => 'debit',
            'billing_code' => 'FURU-DEBIT',
            'bank_account_type' => 1,
            'bank_code' => '9900',
            'branch_code' => '12345',
            'bank_account_number' => '12345678',
            'bank_account_name' => 'ﾔﾏﾀﾞｰ TARO',
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
        $mock->shouldReceive('billingBulkUpsert')->times(3)
            ->withArgs(function (array $billings): bool {
                $billing = $billings[0] ?? [];

                if (isset($billing['payment'])) {
                    return (int)($billing['payment'][0]['payment_method'] ?? 0) === 3
                        && (string)($billing['payment'][0]['code'] ?? '') === 'FURU-DEBIT-PM01';
                }

                if (isset($billing['individual'][0]['payment_method_code'])) {
                    return (string)$billing['individual'][0]['payment_method_code'] === 'FURU-DEBIT-PM01';
                }

                return isset($billing['individual'][0]['code']);
            })
            ->andReturnUsing(function (array $billings): array {
                $billing = $billings[0] ?? [];
                if (isset($billing['payment'])) {
                    return [
                        'billing' => [[
                            'error_code' => 0,
                            'payment' => [[
                                'error_code' => 0,
                            ]],
                        ]],
                    ];
                }

                return ['billing' => [['error_code' => 0]]];
            });

        $mock->shouldReceive('demandBulkUpsert')->once()->withArgs(function (array $demands): bool {
            return ($demands[0]['payment_method_code'] ?? null) === 'FURU-DEBIT-PM01'
                && ($demands[0]['billing_individual_code'] ?? null) === 'FURU-DEBIT-01';
        })->andReturn(['demand' => [['error_code' => 0]]]);

        $mock->shouldReceive('demandBulkIssueBillSelect')->once()->andReturn([
            'bill' => [['number' => 'BILL-DEBIT-1']],
        ]);

        $service = new IssueInvoiceService($mock);
        $invoice = $service->issueInitial($company, 'FURU-DEBIT', 'debit', 1);

        $this->assertSame('issued', $invoice->status);

        $setting = CompanyBillingSetting::query()->where('company_id', (int)$company->id)->firstOrFail();
        $this->assertSame('FURU-DEBIT', $setting->billing_code);
        $this->assertSame('FURU-DEBIT-01', $setting->billing_individual_code);
        $this->assertSame('FURU-DEBIT-PM01', $setting->payment_method_code);
    }

    public function test_issue_add_quantity_for_debit_does_not_require_payment_method_code_when_not_initial(): void
    {
        $company = Company::query()->create([
            'name' => '株式会社テスト',
            'branch_name' => '東京支店',
        ]);

        $sub = \App\Models\Subscription::query()->create([
            'company_id' => (int)$company->id,
            'status' => 'active',
            'quantity' => 1,
            'term_start' => now()->startOfMonth()->toDateString(),
            'term_end' => now()->startOfMonth()->addYear()->subDay()->toDateString(),
            'payment_method' => 'debit',
            'billing_code' => 'FURU-DEBIT',
            'applied_at' => now(),
        ]);

        $mock = \Mockery::mock(BillingRoboClient::class);
        $mock->shouldReceive('billingBulkUpsert')->never();
        $mock->shouldReceive('demandBulkUpsert')->once()->withArgs(function (array $demands): bool {
            return !array_key_exists('payment_method_code', $demands[0]);
        })->andReturn(['demand' => [['error_code' => 0]]]);
        $mock->shouldReceive('demandBulkIssueBillSelect')->once()->andReturn([
            'bill' => [['number' => 'BILL-ADD-1']],
        ]);

        $service = new IssueInvoiceService($mock);
        $invoice = $service->issueAddQuantity($sub, 1, now());

        $this->assertNotNull($invoice);
        $this->assertSame('issued', $invoice->status);
    }

}