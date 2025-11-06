<?php

namespace App\Domain\Tax\Calculators;

use App\Domain\Tax\Contracts\MasterProviderContract;
use App\Services\Tax\Contracts\ProvidesKeys;
use Illuminate\Support\Facades\Log;

class TokureiRateCalculator implements ProvidesKeys
{
    public const ID = 'tokurei.bundle';
    public const ORDER = 7000;
    public const ANCHOR = 'credits';
    public const BEFORE = [];
    public const AFTER = [JuminTaxCalculator::ID];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public function __construct(private readonly MasterProviderContract $masterProvider)
    {
    }

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        return [
            'tokurei_rate_standard_prev',
            'tokurei_rate_standard_curr',
            'tokurei_rate_90_prev',
            'tokurei_rate_90_curr',
            'tokurei_rate_sanrin_div5_prev',
            'tokurei_rate_sanrin_div5_curr',
            'tokurei_rate_taishoku_prev',
            'tokurei_rate_taishoku_curr',
            'tokurei_rate_adopted_prev',
            'tokurei_rate_adopted_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $updates = array_fill_keys(self::provides(), null);

        $year = $this->resolveMasterYear($ctx);
        $companyId = isset($ctx['company_id']) && $ctx['company_id'] !== ''
            ? (int) $ctx['company_id']
            : null;

        $rows = $year > 0 ? $this->buildTokureiRows($year, $companyId) : [];

        if ($rows === [] && $year > 0 && config('app.debug')) {
            Log::debug('TokureiRateCalculator: no tokurei rates found', [
                'year' => $year,
                'company_id' => $companyId,
            ]);
        }

        foreach (self::PERIODS as $period) {
            $standardAmount = $this->taxableBase($payload, $ctx, $period);
            $standardRate = $this->rateForAmount($rows, $standardAmount);
            $updates[sprintf('tokurei_rate_standard_%s', $period)] = $standardRate;

            $updates[sprintf('tokurei_rate_90_%s', $period)] = $this->roundPercent(90.0);

            $sanrinRate = $this->computeSanrinRate($rows, $payload, $period);
            $updates[sprintf('tokurei_rate_sanrin_div5_%s', $period)] = $sanrinRate;

            $taishokuRate = $this->computeTaishokuRate($rows, $payload, $period);
            $updates[sprintf('tokurei_rate_taishoku_%s', $period)] = $taishokuRate;

            $updates[sprintf('tokurei_rate_adopted_%s', $period)] = $this->adoptedRate($sanrinRate, $taishokuRate);
        }

        return array_replace($payload, $updates);
    }

    /**
     * @return array<int, array{lower:int, upper:int|null, rate:float}>
     */
    private function buildTokureiRows(int $year, ?int $companyId): array
    {
        $collection = $this->masterProvider->getTokureiRates($year, $companyId);

        $rows = [];
        foreach ($collection as $row) {
            $lower = $this->normalizeBound($row->lower ?? null);
            if ($lower === null) {
                continue;
            }

            $upper = $this->normalizeUpperBound($row->upper ?? null);
            $rate = $this->normalizeRate($row->tokurei_deduction_rate ?? null);

            if ($rate === null) {
                continue;
            }

            $rows[] = [
                'lower' => $lower,
                'upper' => $upper,
                'rate' => $rate,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $lowerCmp = $a['lower'] <=> $b['lower'];
            if ($lowerCmp !== 0) {
                return $lowerCmp;
            }

            $upperA = $a['upper'];
            $upperB = $b['upper'];

            if ($upperA === $upperB) {
                return 0;
            }

            if ($upperA === null) {
                return 1;
            }

            if ($upperB === null) {
                return -1;
            }

            return $upperA <=> $upperB;
        });

        return $rows;
    }

    private function taxableBase(array $payload, array $ctx, string $period): int
    {
        unset($ctx);
        // SoT統一：常に tb_sogo_shotoku_*
        $raw = $this->n($payload[sprintf('tb_sogo_shotoku_%s', $period)] ?? null);

        return $this->floorToThousands(max(0, $raw));
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function syoriSettings(array $ctx): array
    {
        $settings = $ctx['syori_settings'] ?? [];

        return is_array($settings) ? $settings : [];
    }

    private function computeSanrinRate(array $rows, array $payload, string $period): ?float
    {
        // tb_sanrin_shotoku_*
        $amount = $this->n($payload[sprintf('tb_sanrin_shotoku_%s', $period)] ?? null);
        if ($amount <= 0) {
            return null;
        }

        $divided = $this->floorToThousands(intdiv($amount, 5));
        if ($divided <= 0) {
            return null;
        }

        return $this->rateForAmount($rows, $divided);
    }

    private function computeTaishokuRate(array $rows, array $payload, string $period): ?float
    {
        // tb_taishoku_shotoku_*
        $amount = $this->n($payload[sprintf('tb_taishoku_shotoku_%s', $period)] ?? null);
        if ($amount <= 0) {
            return null;
        }

        $base = $this->floorToThousands($amount);
        if ($base <= 0) {
            return null;
        }

        return $this->rateForAmount($rows, $base);
    }

    private function adoptedRate(?float $sanrinRate, ?float $taishokuRate): ?float
    {
        if ($sanrinRate === null && $taishokuRate === null) {
            return null;
        }

        if ($sanrinRate === null) {
            return $taishokuRate;
        }

        if ($taishokuRate === null) {
            return $sanrinRate;
        }

        return min($sanrinRate, $taishokuRate);
    }

    private function rateForAmount(array $rows, int $amount): ?float
    {
        if ($rows === []) {
            return null;
        }

        $rate = $this->lowerBoundRate(max(0, $amount), $rows);

        if ($rate === null) {
            return null;
        }

        return $this->roundPercent($rate * 100);
    }

    private function lowerBoundRate(float $amount, array $rows): ?float
    {
        if ($rows === []) {
            return null;
        }

        $amount = $this->floorToThousands((int) max(0, floor($amount)));

        $fallbackRate = null;
        $fallbackLower = PHP_INT_MAX;

        foreach ($rows as $row) {
            $lower = (int) $row['lower'];
            if ($lower < $fallbackLower) {
                $fallbackLower = $lower;
                $fallbackRate = $row['rate'];
            }

            if ($lower > $amount) {
                continue;
            }

            $upper = $row['upper'];
            if ($upper !== null && $amount > $upper) {
                continue;
            }
            
            return $row['rate'];
        }

        return $fallbackRate;
    }

    private function resolveMasterYear(array $ctx): int
    {
        $year = isset($ctx['master_kihu_year']) ? (int) $ctx['master_kihu_year'] : 0;
        if ($year > 0) {
            return $year;
        }

        $fallback = isset($ctx['kihu_year']) ? (int) $ctx['kihu_year'] : 0;

        return max(0, $fallback);
    }

    private function normalizeBound(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeUpperBound(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeRate(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return ((float) $value) / 100.0;
    }

    private function floorToThousands(int $value): int
    {
        if ($value <= 0) {
            return 0;
        }

        return $value - ($value % 1000);
    }

    private function n(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        return is_numeric($value) ? (int) floor((float) $value) : 0;
    }

    private function roundPercent(float $value): float
    {
        return round($value, 3);
    }
}