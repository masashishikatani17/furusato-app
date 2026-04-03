<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\IssueInvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncInitialCreditSettlementsCommand extends Command
{
    private const CURSOR_CACHE_KEY = 'billing_robo:initial_credit_settlement_cursor';

    protected $signature = 'billing:sync-initial-credit-settlements';
    protected $description = 'Synchronously detect initial credit-card settlement success from BillingRobo bill update diffs.';

    public function handle(IssueInvoiceService $issuer): int
    {
        $now = now('Asia/Tokyo');
        $lookbackMinutes = max(1, (int) config('billing_robo.credit_initial_poll_lookback_minutes', 180));
        $overlapSeconds = max(0, (int) config('billing_robo.credit_initial_poll_overlap_seconds', 30));
        $limitCount = max(1, min(200, (int) config('billing_robo.credit_initial_poll_limit', 100)));

        $updatedFrom = $this->resolveUpdatedFrom($now, $lookbackMinutes, $overlapSeconds);
        $updatedTo = $now->copy();

        try {
            $result = $issuer->syncInitialCreditSettlementsByUpdatedWindow(
                updatedFrom: $updatedFrom,
                updatedTo: $updatedTo,
                limitCount: $limitCount,
            );
        } catch (Throwable $e) {
            report($e);
            $this->error('failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $cursorBase = $this->resolveCursorBase((string) ($result['max_update_at'] ?? ''), $updatedTo);
        $nextCursor = $cursorBase->copy()->subSeconds($overlapSeconds)->format('Y-m-d H:i:s');
        Cache::put(self::CURSOR_CACHE_KEY, $nextCursor, now('Asia/Tokyo')->addDays(7));

        $this->info(sprintf(
            'from=%s to=%s matched=%d synced=%d paid=%d pending=%d failed=%d missing=%d next_cursor=%s',
            $updatedFrom->format('Y-m-d H:i:s'),
            $updatedTo->format('Y-m-d H:i:s'),
            (int) ($result['matched'] ?? 0),
            (int) ($result['synced'] ?? 0),
            (int) ($result['paid'] ?? 0),
            (int) ($result['pending'] ?? 0),
            (int) ($result['failed'] ?? 0),
            (int) ($result['missing'] ?? 0),
            $nextCursor,
        ));

        return self::SUCCESS;
    }

    private function resolveUpdatedFrom(Carbon $now, int $lookbackMinutes, int $overlapSeconds): Carbon
    {
        $cachedCursor = trim((string) Cache::get(self::CURSOR_CACHE_KEY, ''));
        if ($cachedCursor === '') {
            return $now->copy()->subMinutes($lookbackMinutes);
        }

        try {
            return Carbon::parse($cachedCursor, 'Asia/Tokyo');
        } catch (Throwable) {
            return $now->copy()->subMinutes($lookbackMinutes)->subSeconds($overlapSeconds);
        }
    }

    private function resolveCursorBase(string $maxUpdateAt, Carbon $fallback): Carbon
    {
        $value = trim($maxUpdateAt);
        if ($value === '') {
            return $fallback;
        }

        try {
            return Carbon::parse($value, 'Asia/Tokyo');
        } catch (Throwable) {
            return $fallback;
        }
    }
}