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

    public function test_signup_rejects_debit_payment_method(): void
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
        $response->assertSessionHasErrors('payment_method');
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

    public function test_signup_saves_company_billing_settings_for_credit(): void
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
            'payment_method' => 'クレジットカード',
            'quantity' => 1,
        ]);

        $response->assertRedirect(route('login'));

        $company = Company::query()->firstOrFail();
        $setting = CompanyBillingSetting::query()->where('company_id', (int)$company->id)->first();

        $this->assertNotNull($setting);
        $this->assertSame('credit', $setting->payment_method);
        $this->assertNull($setting->bank_code);
        $this->assertNull($setting->branch_code);
        $this->assertNull($setting->bank_account_number);
        $this->assertNull($setting->bank_account_name);
    }

    public function test_signup_saves_company_billing_settings_for_bank_transfer(): void
    {
        $mock = \Mockery::mock(IssueInvoiceService::class);
        $mock->shouldReceive('issueInitial')->once()->andReturn(new SubscriptionInvoice());
        $this->app->instance(IssueInvoiceService::class, $mock);

        $response = $this->post(route('signup.submit'), [
            'company_name' => '株式会社テスト',
            'branch_name' => '東京支店',
            'owner_name' => '山田 太郎',
            'email' => 'owner-bank@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'payment_method' => '銀行振込',
            'quantity' => 1,
        ]);

        $response->assertRedirect(route('login'));

        $company = Company::query()->where('name', '株式会社テスト')->firstOrFail();
        $setting = CompanyBillingSetting::query()->where('company_id', (int)$company->id)->first();

        $this->assertNotNull($setting);
        $this->assertSame('bank_transfer', $setting->payment_method);
    }

    public function test_issue_initial_credit_creates_payment_then_assigns_individual_and_demand_payment_method_code(): void
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
        $mock->shouldReceive('billingBulkUpsert')->times(3)
            ->withArgs(function (array $billings): bool {
                $billing = $billings[0] ?? [];

                if (isset($billing['payment'])) {
                    return (int)($billing['payment'][0]['payment_method'] ?? -1) === 1
                        && (string)($billing['payment'][0]['code'] ?? '') === 'FURU-TEST-PM01'
                        && (string)($billing['payment'][0]['name'] ?? '') === 'クレジットカード'
                        && (int)($billing['payment'][0]['credit_card_regist_kind'] ?? -1) === 1;
                }

                if (isset($billing['individual'][0]['payment_method_code'])) {
                    return (string)$billing['individual'][0]['payment_method_code'] === 'FURU-TEST-PM01';
                }

                $individual = $billing['individual'][0] ?? [];
                return ($individual['code'] ?? null) === 'FURU-TEST-01'
                    && ($individual['name'] ?? null) === '東京支店'
                    && ($individual['address1'] ?? null) === '株式会社テスト 山田 太郎 御中'
                    && ($individual['email'] ?? null) === 'owner@example.com';
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
            return ($demands[0]['billing_individual_code'] ?? null) === 'FURU-TEST-01'
                && ($demands[0]['payment_method_code'] ?? null) === 'FURU-TEST-PM01';
        })->andReturn(['demand' => [['error_code' => 0]]]);

        $mock->shouldReceive('demandBulkIssueBillSelect')->once()->andReturn([
            'bill' => [['number' => 'BILL-TEST-1']],
        ]);

        $service = new IssueInvoiceService($mock);
        $invoice = $service->issueInitial($company, 'FURU-TEST', 'credit', 1);

        $this->assertSame('issued', $invoice->status);
        $this->assertSame('BILL-TEST-1', $invoice->bill_number);

        $setting = CompanyBillingSetting::query()->where('company_id', (int)$company->id)->firstOrFail();
        $this->assertSame('FURU-TEST-PM01', $setting->payment_method_code);
    }

    public function test_issue_initial_bank_transfer_creates_payment_then_assigns_individual_and_demand_payment_method_code(): void
    {
        config()->set('billing_robo.bank_transfer_pattern_code', '77');

        $company = Company::query()->create([
            'name' => '株式会社テスト',
            'branch_name' => '大阪支店',
        ]);

        CompanyBillingSetting::query()->create([
            'company_id' => (int)$company->id,
            'payment_method' => 'bank_transfer',
            'billing_code' => 'FURU-BANK',
        ]);

        $owner = User::query()->create([
            'company_id' => (int)$company->id,
            'name' => '佐藤 花子',
            'email' => 'owner-bank-transfer@example.com',
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
                    return (int)($billing['payment'][0]['payment_method'] ?? -1) === 0
                        && (string)($billing['payment'][0]['code'] ?? '') === 'FURU-BANK-PM01'
                        && (string)($billing['payment'][0]['bank_transfer_pattern_code'] ?? '') === '77'
                        && array_key_exists('source_bank_account_name', $billing['payment'][0])
                        && (string)($billing['payment'][0]['source_bank_account_name'] ?? '__not-empty__') === '';
                }

                if (isset($billing['individual'][0]['payment_method_code'])) {
                    return (string)$billing['individual'][0]['payment_method_code'] === 'FURU-BANK-PM01';
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
            return ($demands[0]['payment_method_code'] ?? null) === 'FURU-BANK-PM01'
                && ($demands[0]['billing_individual_code'] ?? null) === 'FURU-BANK-01';
        })->andReturn(['demand' => [['error_code' => 0]]]);

        $mock->shouldReceive('demandBulkIssueBillSelect')->once()->andReturn([
            'bill' => [['number' => 'BILL-BANK-1']],
        ]);

        $service = new IssueInvoiceService($mock);
        $invoice = $service->issueInitial($company, 'FURU-BANK', 'bank_transfer', 1);

        $this->assertSame('issued', $invoice->status);
        $this->assertSame('BILL-BANK-1', $invoice->bill_number);

        $setting = CompanyBillingSetting::query()->where('company_id', (int)$company->id)->firstOrFail();
        $this->assertSame('FURU-BANK-PM01', $setting->payment_method_code);
    }

    public function test_issue_initial_prefers_payment_method_code_from_payment_response_over_request_code(): void
    {
        $company = Company::query()->create([
            'name' => '株式会社テスト',
            'branch_name' => '名古屋支店',
        ]);

        CompanyBillingSetting::query()->create([
            'company_id' => (int)$company->id,
            'payment_method' => 'credit',
            'billing_code' => 'FURU-PMCODE',
        ]);

        $owner = User::query()->create([
            'company_id' => (int)$company->id,
            'name' => '田中 一郎',
            'email' => 'owner-pmcode@example.com',
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
                    return (string)($billing['payment'][0]['code'] ?? '') === 'FURU-PMCODE-PM01';
                }

                if (isset($billing['individual'][0]['payment_method_code'])) {
                    return (string)$billing['individual'][0]['payment_method_code'] === 'ROBO-PM-CODE-999';
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
                                'code' => 'FURU-PMCODE-PM01',
                                'payment_method_code' => 'ROBO-PM-CODE-999',
                            ]],
                        ]],
                    ];
                }

                return ['billing' => [['error_code' => 0]]];
            });

        $mock->shouldReceive('demandBulkUpsert')->once()->withArgs(function (array $demands): bool {
            return ($demands[0]['payment_method_code'] ?? null) === 'ROBO-PM-CODE-999';
        })->andReturn(['demand' => [['error_code' => 0]]]);

        $mock->shouldReceive('demandBulkIssueBillSelect')->once()->andReturn([
            'bill' => [['number' => 'BILL-PMCODE-1']],
        ]);

        $service = new IssueInvoiceService($mock);
        $invoice = $service->issueInitial($company, 'FURU-PMCODE', 'credit', 1);

        $this->assertSame('issued', $invoice->status);
        $this->assertSame('BILL-PMCODE-1', $invoice->bill_number);

        $setting = CompanyBillingSetting::query()->where('company_id', (int)$company->id)->firstOrFail();
        $this->assertSame('ROBO-PM-CODE-999', $setting->payment_method_code);
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

    public function test_issue_initial_throws_runtime_exception_and_does_not_continue_to_demand_when_payment_creation_fails(): void
    {
        $company = Company::query()->create([
            'name' => '株式会社テスト',
            'branch_name' => '東京支店',
        ]);

        CompanyBillingSetting::query()->create([
            'company_id' => (int)$company->id,
            'payment_method' => 'credit',
            'billing_code' => 'FURU-FAIL-PAYMENT',
        ]);

        $owner = User::query()->create([
            'company_id' => (int)$company->id,
            'name' => '山田 太郎',
            'email' => 'owner-fail-payment@example.com',
            'password' => bcrypt('password123'),
            'role' => 'owner',
            'is_active' => true,
        ]);
        $company->owner_user_id = (int)$owner->id;
        $company->save();

        $mock = \Mockery::mock(BillingRoboClient::class);
        $mock->shouldReceive('billingBulkUpsert')->twice()
            ->andReturnUsing(function (array $billings): array {
                $billing = $billings[0] ?? [];
                if (isset($billing['payment'])) {
                    throw new RuntimeException('payment endpoint failed');
                }

                return ['billing' => [['error_code' => 0]]];
            });
        $mock->shouldReceive('demandBulkUpsert')->never();
        $mock->shouldReceive('demandBulkIssueBillSelect')->never();

        $service = new IssueInvoiceService($mock);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('payment作成失敗');
        $service->issueInitial($company, 'FURU-FAIL-PAYMENT', 'credit', 1);
    }

    public function test_issue_initial_throws_runtime_exception_and_does_not_continue_to_demand_when_individual_link_fails(): void
    {
        $company = Company::query()->create([
            'name' => '株式会社テスト',
            'branch_name' => '東京支店',
        ]);

        CompanyBillingSetting::query()->create([
            'company_id' => (int)$company->id,
            'payment_method' => 'credit',
            'billing_code' => 'FURU-FAIL-IND',
        ]);

        $owner = User::query()->create([
            'company_id' => (int)$company->id,
            'name' => '山田 太郎',
            'email' => 'owner-fail-ind@example.com',
            'password' => bcrypt('password123'),
            'role' => 'owner',
            'is_active' => true,
        ]);
        $company->owner_user_id = (int)$owner->id;
        $company->save();

        $mock = \Mockery::mock(BillingRoboClient::class);
        $mock->shouldReceive('billingBulkUpsert')->times(3)
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
                if (isset($billing['individual'][0]['payment_method_code'])) {
                    throw new RuntimeException('individual link failed');
                }

                return ['billing' => [['error_code' => 0]]];
            });
        $mock->shouldReceive('demandBulkUpsert')->never();
        $mock->shouldReceive('demandBulkIssueBillSelect')->never();

        $service = new IssueInvoiceService($mock);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('individualへのpayment_method_code関連付け失敗');
        $service->issueInitial($company, 'FURU-FAIL-IND', 'credit', 1);
    }

    public function test_issue_initial_wraps_demand_failure_with_context(): void
    {
        $company = Company::query()->create([
            'name' => '株式会社テスト',
            'branch_name' => '東京支店',
        ]);

        CompanyBillingSetting::query()->create([
            'company_id' => (int)$company->id,
            'payment_method' => 'credit',
            'billing_code' => 'FURU-FAIL-DEMAND',
        ]);

        $owner = User::query()->create([
            'company_id' => (int)$company->id,
            'name' => '山田 太郎',
            'email' => 'owner-fail-demand@example.com',
            'password' => bcrypt('password123'),
            'role' => 'owner',
            'is_active' => true,
        ]);
        $company->owner_user_id = (int)$owner->id;
        $company->save();

        $mock = \Mockery::mock(BillingRoboClient::class);
        $mock->shouldReceive('billingBulkUpsert')->times(3)
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

        $mock->shouldReceive('demandBulkUpsert')->once()->andThrow(new RuntimeException('api 1340'));
        $mock->shouldReceive('demandBulkIssueBillSelect')->never();

        $service = new IssueInvoiceService($mock);

        try {
            $service->issueInitial($company, 'FURU-FAIL-DEMAND', 'credit', 1);
            $this->fail('RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('demand作成失敗', $e->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
            $this->assertSame('api 1340', $e->getPrevious()?->getMessage());
        }
    }

}