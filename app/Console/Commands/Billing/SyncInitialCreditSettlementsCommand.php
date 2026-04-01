<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\IssueInvoiceService;
use Illuminate\Console\Command;

class SyncInitialCreditSettlementsCommand extends Command
{
    protected $signature = 'billing:sync-initial-credit-settlements {--company_id=}';

    protected $description = '初回クレカ請求の決済結果を請求ロボから同期する';

    public function handle(IssueInvoiceService $issuer): int
    {
        $companyIdOption = $this->option('company_id');
        $companyId = $companyIdOption !== null && $companyIdOption !== ''
            ? (int) $companyIdOption
            : null;

        $results = $issuer->syncPendingInitialCreditSettlements($companyId);
        $summary = collect($results)->countBy()->all();

        $this->line('results=' . json_encode(
            $results,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
        $this->info('summary=' . json_encode(
            $summary,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        return self::SUCCESS;
    }
}