<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class JuminTaxCalculator implements ProvidesKeys
{
    public const ID = 'tax.jumin';
    public const ORDER = 5100;
    public const ANCHOR = 'tax';
    public const BEFORE = [];
    public const AFTER = [ShotokuTaxCalculator::ID];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        return [
            'tax_zeigaku_jumin_prev',
            'tax_zeigaku_jumin_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $updates = array_fill_keys(self::provides(), 0);

        foreach (self::PERIODS as $period) {
            $key = sprintf('tax_kazeishotoku_jumin_%s', $period);
            $amount = max(0, $this->n($payload[$key] ?? null));
            $updates[sprintf('tax_zeigaku_jumin_%s', $period)] = (int) ($amount * 0.1);
        }

        return array_replace($payload, $updates);
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