<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

/**
 * 配当控除（所得税）
 * - input の tax_haito_shotoku_{prev,curr} を「税額控除額」として扱う（ユーザー任意入力）
 * - 算出税額 tax_zeigaku_shotoku_* から先に控除し、残税額を tax_after_haito_shotoku_* として出す
 * - 端数は入力が整数の前提のため考慮しない
 */
final class ItaxHaitoCreditCalculator implements ProvidesKeys
{
    public const ID    = 'tax.shotoku.haito';
    public const ORDER = 5105;
    public const ANCHOR = 'tax';

    public const AFTER  = [ShotokuTaxCalculator::ID];
    public const BEFORE = [JutakuLoanCreditCalculator::ID];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public static function provides(): array
    {
        return [
            'tax_haito_applied_shotoku_prev',
            'tax_haito_applied_shotoku_curr',
            'tax_after_haito_shotoku_prev',
            'tax_after_haito_shotoku_curr',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $ctx
     * @return array<string,mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        unset($ctx);

        $out = array_fill_keys(self::provides(), 0);

        foreach (self::PERIODS as $p) {
            $baseTax = max(0, (int)($payload["tax_zeigaku_shotoku_{$p}"] ?? 0));
            $haito   = max(0, (int)($payload["tax_haito_shotoku_{$p}"] ?? 0));

            $applied = min($haito, $baseTax);
            $after   = max($baseTax - $applied, 0);

            $out["tax_haito_applied_shotoku_{$p}"] = $applied;
            $out["tax_after_haito_shotoku_{$p}"]   = $after;
        }

        return array_replace($payload, $out);
    }
}
