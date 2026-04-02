<?php

namespace App\Console\Commands\Billing;

use App\Jobs\Billing\SyncSubscriptionInvoiceJob;
use App\Models\SubscriptionInvoice;
use Illuminate\Console\Command;

/**
 * 定期同期用（10分ごと想定）
 * - webhook で取りこぼした invoice を再同期する
 * - 初回クレカで paid 済みだが recurring master 未作成のものも救済対象に含める
 */
class SyncOutstandingInvoicesCommand extends Command
{
    protected $signature = 'billing:sync-outstanding-invoices {--limit=200}';
    protected $description = 'Dispatch sync jobs for outstanding subscription invoices.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $limit = max(1, min(1000, $limit));

        $targets = SubscriptionInvoice::query()
            ->where(function ($query) {
                $query->whereIn('status', ['pending', 'issued', 'failed'])
                    ->orWhere(function ($paidInitialCreditQuery) {
                        $paidInitialCreditQuery
                            ->where('kind', 'initial')
                            ->where('payment_method', 'credit')
                            ->where('status', 'paid')
                            ->whereHas('subscription', function ($subscriptionQuery) {
                                $subscriptionQuery->where(function ($subQuery) {
                                    $subQuery
                                        ->whereNull('billing_robo_managed_recurring')
                                        ->orWhere('billing_robo_managed_recurring', false)
                                        ->orWhereNull('billing_robo_master_demand_code')
                                        ->orWhere('billing_robo_master_demand_code', '');
                                });
                            });
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get(['id']);

        foreach ($targets as $inv) {
            SyncSubscriptionInvoiceJob::dispatch((int) $inv->id);
        }

        $this->info('dispatched=' . $targets->count());

        return self::SUCCESS;
    }
}
