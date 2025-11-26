<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

/**
 * 住宅借入金等特別控除（所得税）
 * - 内訳画面の入力（限度額・年末残高・控除率%）から理論控除額を算定
 * - 上限は「当年の所得税額（税額控除適用前）」＝ tax_zeigaku_shotoku_*
 * - 適用後の税額（後続の政党等特別控除の母数）を tax_after_jutaku_shotoku_* に出力
 * - 住民税側へ渡す「未控除額」 itax_unapplied_* もここで確定
 */
class JutakuLoanCreditCalculator implements ProvidesKeys
{
    public const ID = 'tax.shotoku.jutaku';
    // 【制度順】所得税の算出税額（ShotokuTax）→ 本Calculator →（後段）政党等 等
    public const ORDER = 5110;
    public const BEFORE = [SeitotoTokubetsuZeigakuKojoCalculator::ID];
    public const AFTER  = [ShotokuTaxCalculator::ID, CommonTaxableBaseCalculator::ID];

    /** @var string[] */
    private const PERIODS = ['prev','curr'];

    public static function provides(): array
    {
        return [
            'itax_theoretical_credit_prev',
            'itax_theoretical_credit_curr',
            'itax_unapplied_prev',
            'itax_unapplied_curr',
            'tax_jutaku_shotoku_prev',
            'tax_jutaku_shotoku_curr',
            'tax_after_jutaku_shotoku_prev',
            'tax_after_jutaku_shotoku_curr',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $ctx
     * @return array<string,mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        unset($ctx);
        $out = array_fill_keys(self::provides(), 0);

        foreach (self::PERIODS as $p) {
            // 入力（限度・残高・率%）
            $cap   = $this->n($payload["itax_borrow_cap_{$p}"] ?? null);
            $bal   = $this->n($payload["itax_year_end_balance_{$p}"] ?? null);
            $rateP = $this->f1($payload["itax_credit_rate_percent_{$p}"] ?? 0.7); // %単位（小数1位・未設定時0.7）
            $rate  = max(0.0, $rateP) / 100.0;

            // 理論控除額
            $theoretical = (int) floor(min($cap, $bal) * $rate);
            $out["itax_theoretical_credit_{$p}"] = $theoretical;

            // 上限：税額控除適用前の所得税額（＝算出税額）
            $baseTax   = max(0, (int) ($payload["tax_zeigaku_shotoku_{$p}"] ?? 0));
            $applied   = min($theoretical, $baseTax);
            $afterTax  = $baseTax - $applied;
            $unapplied = $theoretical - $applied;

            $out["tax_jutaku_shotoku_{$p}"]       = $applied;
            $out["tax_after_jutaku_shotoku_{$p}"] = $afterTax;
            $out["itax_unapplied_{$p}"]           = max(0, $unapplied);
            
\Log::info('[ITAX JUTAKU]', [
    'p'          => $p,
    'cap'        => $cap,
    'bal'        => $bal,
    'rateP(%)'   => $rateP,
    'theoretical'=> $theoretical,
    'baseTax'    => $baseTax,
    'applied'    => $applied,
    'unapplied'  => $out["itax_unapplied_{$p}"],
]);
        }

        return array_replace($payload, $out);
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int) floor((float) $v) : 0;
    }
    private function f1(mixed $v): float
    {
        if ($v === null || $v === '') return 0.0;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (float) number_format(round((float)$v, 1), 1, '.', '') : 0.0;
    }
}