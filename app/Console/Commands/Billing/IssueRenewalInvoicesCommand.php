<?php

namespace App\Console\Commands\Billing;

use App\Models\Subscription;
use App\Services\Billing\IssueInvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class IssueRenewalInvoicesCommand extends Command
{
    protected $signature = 'billing:issue-renewal-invoices {--date=} {--limit=500}';
    protected $description = 'Issue renewal invoices based on payment method timing rules.';

    public function handle(IssueInvoiceService $issuer): int
    {
        $tz = 'Asia/Tokyo';
        $base = $this->option('date')
            ? Carbon::parse((string)$this->option('date'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $limit = max(1, min(5000, (int)$this->option('limit')));

        $targets = Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('term_end')
            ->whereNotNull('payment_method')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $issued = 0;
        $skipped = 0;

        foreach ($targets as $sub) {
            $termEnd = Carbon::parse((string)$sub->term_end, $tz)->startOfDay();
            $method = (string)$sub->payment_method;

            $trigger = match ($method) {
                'credit', 'debit' => $termEnd->copy()->subDays(3),
                'bank_transfer' => $termEnd->copy()->subDays(14),
                default => null,
            };

            if (!$trigger || !$trigger->equalTo($base)) {
                $skipped++;
                continue;
            }

            $inv = $issuer->issueRenewal($sub, $base);
            if ($inv) {
                $issued++;
            } else {
                $skipped++;
            }
        }

        $this->info('issued=' . $issued . ' skipped=' . $skipped);
        return 0;
    }
}
