<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

/**
 * 住宅借入金等特別控除（個人住民税）
 * - itax_unapplied_*（所得税で引き切れなかった額）をベースに
 *   min( 未控除額, 課税総所得金額等×率, 絶対上限, 住民税の算出税額 ) を適用
 * - 適用後の住民税額を tax_after_jutaku_jumin_* に出力（後続：寄附金税額控除 等）
 */
class JuminJutakuLoanCreditCalculator implements ProvidesKeys
{
    public const ID = 'tax.jumin.jutaku';
    // 【制度順】住民税の算出税額（JuminTax）→ 本Calculator →（後段）寄附金税額控除 等
    public const ORDER = 5210;
    public const BEFORE = [JuminzeiKifukinCalculator::ID];
    public const AFTER  = [JuminTaxCalculator::ID, CommonTaxableBaseCalculator::ID, JutakuLoanCreditCalculator::ID];

    /** @var string[] */
    private const PERIODS = ['prev','curr'];

    public static function provides(): array
    {
        return [
            'tax_jutaku_jumin_prev',
            'tax_jutaku_jumin_curr',
            'tax_after_jutaku_jumin_prev',
            'tax_after_jutaku_jumin_curr',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $ctx
     * @return array<string,mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $out = array_fill_keys(self::provides(), 0);

        foreach (self::PERIODS as $p) {
            // 住民税の算出税額（調整控除後）→ 配当控除後残税額をベースにする
            $baseTax = (int) ($payload["tax_after_haito_jumin_{$p}"] ?? $payload["tax_zeigaku_jumin_{$p}"] ?? 0);
            $baseTax = max(0, $baseTax);

            // 住宅控除の母数：未控除額（所得税側）
            $unapplied = max(0, (int) ($payload["itax_unapplied_{$p}"] ?? 0));

            // 課税総所得金額等（所得税の）＝ tb_sogo_shotoku + tb_sanrin_shotoku + tb_taishoku_shotoku
            $taxable = (int) ($payload["rtax_taxable_total_{$p}"] ?? 0);
            if ($taxable <= 0) {
                $sogo     = max(0, (int)($payload["tb_sogo_shotoku_{$p}"]     ?? 0));
                $sanrin   = max(0, (int)($payload["tb_sanrin_shotoku_{$p}"]   ?? 0));
                $taishoku = max(0, (int)($payload["tb_taishoku_shotoku_{$p}"] ?? 0));
                $taxable  = $sogo + $sanrin + $taishoku;
            }

            // 率は 5 or 7（プルダウンで確定済・バリデーション済）
            $ratePct = (int) ($payload["rtax_income_rate_percent_{$p}"] ?? 5);
            $ratePct = in_array($ratePct, [5,7], true) ? $ratePct : 5;
            $hardCap = $ratePct === 7 ? 136_500 : 97_500;
            $capByIncome = (int) floor($taxable * ($ratePct / 100.0));
            $carryCap = min($capByIncome, $hardCap);

            // 住民税に実際に適用：算出税額も上限
            $credit = min($unapplied, $carryCap, $baseTax);
            $after  = $baseTax - $credit;

            $out["tax_jutaku_jumin_{$p}"]       = $credit;
            $out["tax_after_jutaku_jumin_{$p}"] = $after;
        }

        return array_replace($payload, $out);

    }
}