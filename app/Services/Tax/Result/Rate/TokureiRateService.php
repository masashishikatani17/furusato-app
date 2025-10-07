<?php

namespace App\Services\Tax\Result\Rate;

use Illuminate\Support\Collection;
use function data_get;

final class TokureiRateService
{
    /**
     * @param iterable<int, array{threshold: float, rate: float}|array-key, mixed> $rates
     */
    public function lookup(?float $amount, iterable $rates): ?float
    {
        if ($amount === null) {
            return null;
        }

        $normalized = $this->normalizeRates($rates);
        if ($normalized === []) {
            return null;
        }

        $amount = max(0.0, (float) $amount);

        $matched = null;
        foreach ($normalized as $candidate) {
            if ($candidate['threshold'] <= $amount) {
                $matched = $candidate['rate'];
            } else {
                break;
            }
        }

        return $matched ?? $normalized[0]['rate'];
    }

    /**
     * @param iterable<int, array{threshold: float, rate: float}|array-key, mixed> $rates
     * @return array<int, array{threshold: float, rate: float}>
     */
    private function normalizeRates(iterable $rates): array
    {
        if ($rates instanceof Collection) {
            $rates = $rates->all();
        }

        $normalized = [];

        foreach ($rates as $row) {
            $threshold = null;
            $rate = null;

            if (is_array($row)) {
                $threshold = $row['threshold'] ?? null;
                $rate = $row['rate'] ?? null;
            } elseif (is_object($row)) {
                $threshold = data_get($row, 'threshold');
                $rate = data_get($row, 'rate');
            }

            if (is_numeric($threshold) && is_numeric($rate)) {
                $normalized[] = [
                    'threshold' => (float) $threshold,
                    'rate' => (float) $rate,
                ];
            }
        }

        usort($normalized, static fn (array $a, array $b) => $a['threshold'] <=> $b['threshold']);

        return $normalized;
    }
}