<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

/**
 * 配当控除（住民税）
 * - input の tax_haito_jumin_{prev,curr} を「税額控除額」として扱う（ユーザー任意入力）
 * - JuminTaxCalculator が出す tax_zeigaku_jumin_*（調整控除後の所得割合計）から先に控除し、
 *   残税額を tax_after_haito_jumin_* として出す
 */
final class JuminHaitoCreditCalculator implements ProvidesKeys
{
    public const ID    = 'tax.jumin.haito';
    public const ORDER = 5205;
    public const ANCHOR = 'tax';

    public const AFTER  = [JuminTaxCalculator::ID];
    public const BEFORE = [JuminJutakuLoanCreditCalculator::ID];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public static function provides(): array
    {
        return [
            'tax_haito_applied_jumin_prev',
            'tax_haito_applied_jumin_curr',
            'tax_after_haito_jumin_prev',
            'tax_after_haito_jumin_curr',
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
            $baseTax = max(0, (int)($payload["tax_zeigaku_jumin_{$p}"] ?? 0));
            $haito   = max(0, (int)($payload["tax_haito_jumin_{$p}"] ?? 0));

            $applied = min($haito, $baseTax);
            $after   = max($baseTax - $applied, 0);

            $out["tax_haito_applied_jumin_{$p}"] = $applied;
            $out["tax_after_haito_jumin_{$p}"]   = $after;
        }

        return array_replace($payload, $out);
    }
}