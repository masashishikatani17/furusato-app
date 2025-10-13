<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class ShotokuTaxCalculator implements ProvidesKeys
{
    public const ID = 'tax.shotoku';
    public const ORDER = 5000;
    public const ANCHOR = 'tax';
    public const BEFORE = [];
    public const AFTER = [KojoAggregationCalculator::ID];

    public static function provides(): array
    {
        return [
            'tax_zeigaku_shotoku_prev',
            'tax_zeigaku_shotoku_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $year = isset($ctx['master_kihu_year']) ? (int) $ctx['master_kihu_year'] : 0;
        $companyId = $ctx['company_id'] ?? null;
        if ($companyId !== null) {
            $companyId = (int) $companyId;
        }

        $rates = $this->masterProvider
            ->getShotokuRates($year, $companyId)
            ->all();

        $updates = array_fill_keys(self::provides(), 0);

        foreach (self::PERIODS as $period) {
            $key = sprintf('tax_kazeishotoku_shotoku_%s', $period);
            $amount = $this->n($payload[$key] ?? null);
            $updates[sprintf('tax_zeigaku_shotoku_%s', $period)] = $this->calculateTaxAmount($rates, $amount);
        }

        return array_replace($payload, $updates);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rates
     */
    private function calculateTaxAmount(array $rates, int $amount): int
    {
        $taxable = max(0, $amount);

        foreach ($rates as $rate) {
            $lower = (int) ($rate['lower'] ?? 0);
            $upper = array_key_exists('upper', $rate) ? $rate['upper'] : null;

            if ($taxable < $lower) {
                continue;
            }

            if ($upper !== null && $taxable > $upper) {
                continue;
            }

            $rateDecimal = (float) ($rate['rate'] ?? 0) / 100;
            $deduction = (int) ($rate['deduction_amount'] ?? 0);
            $value = $taxable * $rateDecimal - $deduction;

            return (int) $value;
        }

        return 0;
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
}