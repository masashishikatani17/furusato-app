<?php

namespace App\Console\Commands\Billing;

use App\Jobs\Billing\SyncSubscriptionInvoiceJob;
use App\Models\SubscriptionInvoice;
use Illuminate\Console\Command;

/**
 * 定期同期用（10分ごと想定）
 * - pending/issued/failed の invoice を対象に同期ジョブを投げる
 */
class SyncOutstandingInvoicesCommand extends Command
{
    protected $signature = 'billing:sync-outstanding-invoices {--limit=200}';
    protected $description = 'Dispatch sync jobs for outstanding subscription invoices.';

    public function handle(): int
    {
        $limit = (int)$this->option('limit');
        $limit = max(1, min(1000, $limit));

        $targets = SubscriptionInvoice::query()
            ->whereIn('status', ['pending', 'issued', 'failed'])
            ->orderBy('id')
            ->limit($limit)
            ->get(['id']);

        foreach ($targets as $inv) {
            SyncSubscriptionInvoiceJob::dispatch((int)$inv->id);
        }

        $this->info('dispatched=' . $targets->count());
        return 0;
    }
}
