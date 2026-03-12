<?php

namespace Tests\Feature\Billing;

use App\Http\Middleware\EnsureSubscriptionActive;
use App\Jobs\Billing\SyncSubscriptionInvoiceJob;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\BillingRobo\BillingRoboClient;
use App\Services\License\SeatService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RenewalBillingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_renewal_command_creates_invoice_and_prevents_duplicate(): void
    {
        Carbon::setTestNow('2026-02-25 10:00:00'); // term_end 2/28 の3日前

        $company = Company::query()->create(['name' => 'C1', 'branch_name' => 'B1']);
        $sub = Subscription::query()->create([
            'company_id' => (int)$company->id,
            'status' => 'active',
            'quantity' => 3,
            'term_start' => '2025-03-01',
            'term_end' => '2026-02-28',
            'paid_through' => '2026-02-28',
            'payment_method' => 'credit',
            'billing_code' => 'FURU-TEST1',
        ]);

        $mock = \Mockery::mock(BillingRoboClient::class);
        $mock->shouldReceive('demandBulkUpsert')->andReturn(['result' => 'ok']);
        $mock->shouldReceive('demandBulkIssueBillSelect')->andReturn(['bill' => [['number' => 'BILL-1']]]);
        $this->app->instance(BillingRoboClient::class, $mock);

        $this->artisan('billing:issue-renewal-invoices', ['--date' => '2026-02-25'])->assertExitCode(0);
        $this->assertDatabaseHas('subscription_invoices', [
            'subscription_id' => (int)$sub->id,
            'kind' => 'renewal',
            'period_start' => '2026-03-01 00:00:00',
            'period_end' => '2027-02-28 00:00:00',
            'status' => 'issued',
            'quantity' => 3,
        ]);

        // 2回目は重複作成しない
        $this->artisan('billing:issue-renewal-invoices', ['--date' => '2026-02-25'])->assertExitCode(0);
        $this->assertSame(1, SubscriptionInvoice::query()->where('subscription_id', (int)$sub->id)->where('kind', 'renewal')->count());
    }

    public function test_sync_job_updates_term_on_paid_renewal(): void
    {
        $company = Company::query()->create(['name' => 'C1', 'branch_name' => 'B1']);
        $sub = Subscription::query()->create([
            'company_id' => (int)$company->id,
            'status' => 'active',
            'quantity' => 3,
            'term_start' => '2025-03-01',
            'term_end' => '2026-02-28',
            'paid_through' => '2026-02-28',
            'payment_method' => 'credit',
            'billing_code' => 'FURU-TEST1',
        ]);

        $inv = SubscriptionInvoice::query()->create([
            'company_id' => (int)$company->id,
            'subscription_id' => (int)$sub->id,
            'kind' => 'renewal',
            'status' => 'issued',
            'bill_number' => 'BILL-2',
            'billing_code' => 'FURU-TEST1',
            'payment_method' => 'credit',
            'quantity' => 3,
            'unit_price_yen' => 30000,
            'months_charged' => 12,
            'amount_yen' => 90000,
            'period_start' => '2026-03-01',
            'period_end' => '2027-02-28',
            'issue_date' => '2026-02-25',
            'due_date' => '2026-02-28',
        ]);

        $mock = \Mockery::mock(BillingRoboClient::class);
        $mock->shouldReceive('billSearchByNumber')->andReturn([
            'bill' => [[
                'clearing_status' => 1,
                'unclearing_amount' => 0,
                'transfer_date' => '2026/02/28',
            ]],
        ]);
        $this->app->instance(BillingRoboClient::class, $mock);

        (new SyncSubscriptionInvoiceJob((int)$inv->id))->handle($mock);

        $sub->refresh();
        $this->assertSame('2026-03-01', $sub->term_start?->toDateString());
        $this->assertSame('2027-02-28', $sub->term_end?->toDateString());
        $this->assertSame('2027-02-28', $sub->paid_through?->toDateString());
    }

    public function test_seat_service_uses_quantity_times_five_and_middleware_enforces_paid_through_boundary_and_calc_route(): void
    {
        Carbon::setTestNow('2026-03-01 00:00:01');

        $company = Company::query()->create(['name' => 'C1', 'branch_name' => 'B1']);
        $user = User::query()->create([
            'company_id' => (int)$company->id,
            'name' => 'U1',
            'email' => 'u1@example.com',
            'password' => bcrypt('password123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        Subscription::query()->create([
            'company_id' => (int)$company->id,
            'status' => 'active',
            'quantity' => 3,
            'term_start' => '2025-03-01',
            'term_end' => '2026-02-28',
            'paid_through' => '2026-02-28',
            'payment_method' => 'credit',
            'billing_code' => 'FURU-TEST1',
        ]);

        $seat = app(SeatService::class);
        $this->assertSame(15, $seat->getActiveSeats((int)$company->id));

        $mw = new EnsureSubscriptionActive();

        // paid_through 当日 23:59:59 までは通す
        Carbon::setTestNow('2026-02-28 23:59:59');
        $allowedRequest = Request::create('/furusato', 'GET');
        $allowedRequest->setUserResolver(fn() => $user);
        $allowed = $mw->handle($allowedRequest, fn() => response('ok', 200));
        $this->assertSame(200, $allowed->getStatusCode());

        // 翌日 0:00:00 からは停止
        Carbon::setTestNow('2026-03-01 00:00:00');
        $request = Request::create('/furusato', 'GET');
        $request->setUserResolver(fn() => $user);

        $res = $mw->handle($request, fn() => response('ok', 200));
        $this->assertSame(302, $res->getStatusCode());
        $this->assertSame(route('subscription.suspended'), $res->headers->get('Location'));

        // /furusato/calc も保護対象
        $this->actingAs($user)
            ->post(route('furusato.calc'), ['id' => 1])
            ->assertRedirect(route('subscription.suspended'));
    }
}