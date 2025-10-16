<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

final class BunriKabutekiNettingCalculator implements ProvidesKeys
{
    public const ID = 'bunri.kabuteki.netting';
    public const ORDER = 4030;

    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [];

        foreach (self::PERIODS as $period) {
            $keys[] = sprintf('before_tsusan_jojo_joto_%s', $period);
            $keys[] = sprintf('before_tsusan_jojo_haito_%s', $period);
            $keys[] = sprintf('after_tsusan_jojo_joto_%s', $period);
            $keys[] = sprintf('after_tsusan_jojo_haito_%s', $period);
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string  $period
     * @return array<string, int>
     */
    public function compute(array $payload, string $period): array
    {
        if (! in_array($period, self::PERIODS, true)) {
            return [];
        }

        $transfer = $this->normalize($payload[sprintf('shotoku_jojo_joto_%s', $period)] ?? null);
        $dividendRaw = $this->normalize($payload[sprintf('shotoku_jojo_haito_%s', $period)] ?? null);

        $beforeTransfer = $transfer;
        $beforeDividend = max($dividendRaw, 0);

        $listedTransfer = $beforeTransfer;
        $listedDividendPos = max(0, $beforeDividend);
        $useDividend = min($listedDividendPos, max(0, -$listedTransfer));

        $afterTransfer = $listedTransfer + $useDividend;
        $afterDividend = max(0, $listedDividendPos - $useDividend);

        return [
            sprintf('before_tsusan_jojo_joto_%s', $period) => $beforeTransfer,
            sprintf('before_tsusan_jojo_haito_%s', $period) => $beforeDividend,
            sprintf('after_tsusan_jojo_joto_%s', $period) => $afterTransfer,
            sprintf('after_tsusan_jojo_haito_%s', $period) => $afterDividend,
        ];
    }

    private function normalize(mixed $value): int
    {
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (int) ((float) $value);
        }

        return 0;
    }
}