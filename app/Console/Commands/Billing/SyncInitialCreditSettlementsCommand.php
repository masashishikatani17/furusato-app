<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\IssueInvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

            Log::error('BillingRobo initial credit settlement diff sync failed.', [
                'updated_from' => $updatedFrom->format('Y-m-d H:i:s'),
                'updated_to' => $updatedTo->format('Y-m-d H:i:s'),
                'lookback_minutes' => $lookbackMinutes,
                'overlap_seconds' => $overlapSeconds,
                'limit_count' => $limitCount,
                'exception_message' => $e->getMessage(),
            ]);

            $this->error('failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        // 次回カーソルは「今回の実行終了時刻」を基準に進める。
        // max_update_at 基準にすると、更新が入っていない間ずっと同じ時刻を舐め続けることがある。
        $nextCursor = $updatedTo->copy()->subSeconds($overlapSeconds)->format('Y-m-d H:i:s');
        Cache::put(self::CURSOR_CACHE_KEY, $nextCursor, now('Asia/Tokyo')->addDays(7));
 
        Log::info('BillingRobo initial credit settlement diff sync finished.', [
            'updated_from' => $updatedFrom->format('Y-m-d H:i:s'),
            'updated_to' => $updatedTo->format('Y-m-d H:i:s'),
            'matched' => (int) ($result['matched'] ?? 0),
            'synced' => (int) ($result['synced'] ?? 0),
            'paid' => (int) ($result['paid'] ?? 0),
            'pending' => (int) ($result['pending'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'missing' => (int) ($result['missing'] ?? 0),
            'max_update_at' => (string) ($result['max_update_at'] ?? ''),
            'next_cursor' => $nextCursor,
        ]);

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