<?php

namespace App\Domain\Tax\Calculators;
use Illuminate\Support\Facades\Log;

use App\Services\Tax\Contracts\ProvidesKeys;

class SeitotoTokubetsuZeigakuKojoCalculator implements ProvidesKeys
{
    public const ID = 'credit.seitoto';
    public const ORDER = 6000;
    public const ANCHOR = 'credits';

    // 実行順の明示:
    //  - AFTER:  所得税額確定(ShotokuTax)
    //            → 住宅ローン控除(JutakuLoan)で tax_after_jutaku_shotoku_* を確定
    //            → 寄附金「所得控除」(Kifukin)が I を供給
    //            → 本Calculator（税額控除）
    //  - BEFORE: 住民税計算(JuminTax)より前に適用（所得税の税額控除）
    public const AFTER  = [ShotokuTaxCalculator::ID, JutakuLoanCreditCalculator::ID, KifukinCalculator::ID];
    public const BEFORE = [JuminTaxCalculator::ID];

    public static function provides(): array
    {
        return [
            // カテゴリ別の所得税・寄附金税額控除
            'tax_credit_shotoku_seito_prev',
            'tax_credit_shotoku_seito_curr',
            'tax_credit_shotoku_npo_prev',
            'tax_credit_shotoku_npo_curr',
            'tax_credit_shotoku_koueki_prev',
            'tax_credit_shotoku_koueki_curr',
            // 合計と差引
            'tax_credit_shotoku_total_prev',
            'tax_credit_shotoku_total_curr',
            'tax_sashihiki_shotoku_prev',
            'tax_sashihiki_shotoku_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        foreach (['prev','curr'] as $p) {
            // 入力寄附（税額控除側の母集団）
            $donSeito  = $this->n($payload["shotokuzei_zeigakukojo_seito_{$p}"]  ?? null);
            $donNpo    = $this->n($payload["shotokuzei_zeigakukojo_npo_{$p}"]    ?? null);
            $donKoueki = $this->n($payload["shotokuzei_zeigakukojo_koueki_{$p}"] ?? null);
            $donSum    = $donSeito + $donNpo + $donKoueki;

            // SoT：総所得金額等（40%上限の基礎）
            $S = $this->n($payload["sum_for_sogoshotoku_etc_{$p}"] ?? null);
            /**
             * Tcap：25%上限の母数となる「所得税額」（控除前の所得税額を想定）
             *  - 既存SoT tax_zeigaku_shotoku_* を採用。
             */
            $Tcap = $this->n($payload["tax_zeigaku_shotoku_{$p}"] ?? null);

            /**
             * Tpay：実際に寄附税額控除を差し引く税額（控除の“引き場所”）
             *  - 住宅借入金等特別控除を適用した後の所得税額 tax_after_jutaku_shotoku_* を優先。
             *  - 欠損時は互換・安全弁として Tcap にフォールバック。
             */
            $Tpay = $this->n($payload["tax_after_jutaku_shotoku_{$p}"] ?? null);
            if ($Tpay <= 0) {
                $Tpay = $Tcap;
            }

            // 所得控除で使用済みの寄附「元本」I（KifukinCalculator が出力）
            $I = $this->n($payload["used_by_income_deduction_{$p}"] ?? null);

            // 40% 枠（元本ベース）の残余：税額控除に回せる母集団の上限（I との食い合い）
            $cap40        = intdiv(max($S, 0) * 4, 10);
            $capForCredit = max($cap40 - $I, 0);

            /**
             * 2,000円（残額）：
             *  - 所得控除側(I)が先に2,000円を消費し、残りだけが税額控除側へ回る。
             * 重要：税額控除側は「40%枠（元本）→2,000円残額」の順で適用する。
             */
            $floorRem = max(2000 - min(max($I, 0), 2000), 0);

            // ── 40%枠（残余）を元本に適用：公益→NPO→政党の順で枠を消費 ──
            $capLeft = max($capForCredit, 0);
            $allocKouekiGross = min(max($donKoueki, 0), $capLeft);
            $capLeft -= $allocKouekiGross;
            $allocNpoGross    = min(max($donNpo, 0), max($capLeft, 0));
            $capLeft -= $allocNpoGross;
            $allocSeitoGross  = min(max($donSeito, 0), max($capLeft, 0));

            // ── 2,000円（残額）を元本に相殺：公益→NPO→政党の順 ──
            $floorLeft = $floorRem;
            $kouekiBase = max($allocKouekiGross - $floorLeft, 0);
            $floorLeft  = max($floorLeft - $allocKouekiGross, 0);
            $npoBase    = max($allocNpoGross - $floorLeft, 0);
            $floorLeft  = max($floorLeft - $allocNpoGross, 0);
            $seitoBase  = max($allocSeitoGross - $floorLeft, 0);

            // 25% 上限（税額ベース）
            //  - 公益＋NPO：共通25%枠（同一枠内で「公益→NPO」優先）
            //  - 政党等：別枠25%（同じTから算定するが、公益/NPOとは別に判定する）
            $cap25Common = $this->floor100((int) floor(max($Tcap, 0) * 0.25));
            $cap25Seito  = $this->floor100((int) floor(max($Tcap, 0) * 0.25));

            // 1) 公益：min(元本×40%, 25%上限) → 100円未満切捨て
            $kouekiRaw  = $this->floor100((int) floor(max($kouekiBase, 0) * 0.40));
            $credKoueki = min($kouekiRaw, $cap25Common);

            // 2) NPO：公益控除後の共通25%残額で判定（公益→NPO優先）
            $cap25Rem = max($cap25Common - $credKoueki, 0);
            $npoRaw   = $this->floor100((int) floor(max($npoBase, 0) * 0.40));
            $credNpo  = min($npoRaw, $cap25Rem);

            // 3) 政党等：別枠25%
            $seitoRaw  = $this->floor100((int) floor(max($seitoBase, 0) * 0.30));
            $credSeito = min($seitoRaw, $cap25Seito);

            /**
             * 最終ガード：寄附税額控除は「住宅ローン控除後の所得税額（Tpay）」から控除するため、
             * 控除合計が Tpay を超えないように、税額残で刈り込む。
             * 優先順：公益 → NPO → 政党等
             */
            $taxLeft = max($Tpay, 0);
            $appliedKoueki = min($credKoueki, $taxLeft);
            $taxLeft -= $appliedKoueki;
            $appliedNpo    = min($credNpo, max($taxLeft, 0));
            $taxLeft -= $appliedNpo;
            $appliedSeito  = min($credSeito, max($taxLeft, 0));

            $credKoueki = $appliedKoueki;
            $credNpo    = $appliedNpo;
            $credSeito  = $appliedSeito;

            // 万一、寄附元本の偏在で baseTotal を使い切れない場合は（まれ）、
            // 40%側寄附が残っていれば政党枠へリバランスできないため、その分は自然に未使用となる。

            // ※この順序なら「公益を先に25%へ当て、残りでNPO」を満たし、
            //   かつ 2,000円の相殺を I + 40%側の元本で一体管理できる。

            $credTotal = $credNpo + $credKoueki + $credSeito;
            $taxAfter  = max($Tpay - $credTotal, 0);

            // 出力
            $payload["tax_credit_shotoku_npo_{$p}"]     = $credNpo;
            $payload["tax_credit_shotoku_koueki_{$p}"]  = $credKoueki;
            $payload["tax_credit_shotoku_seito_{$p}"]   = $credSeito;
            $payload["tax_credit_shotoku_total_{$p}"]   = $credTotal;
            $payload["tax_sashihiki_shotoku_{$p}"]      = $taxAfter;
        }

        return $payload;
    }

    /**
     * @param  string[]  $keys
     */
    private function sumByKeys(array $payload, array $keys, string $period): int
    {
        $sum = 0;
        foreach ($keys as $key) {
            $field = sprintf('%s_%s', $key, $period);
            $sum += $this->n($payload[$field] ?? 0);
        }
        return $sum;
    }

    // ===== helpers =====
    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int) floor((float) $v) : 0;
    }

    private function floor100(int $v): int
    {
        if ($v <= 0) return 0;
        return (int) (floor($v / 100) * 100);
    }

    private function floorTo(int $v, int $step): int
    {
        if ($v <= 0 || $step <= 0) return 0;
        return (int) (floor($v / $step) * $step);
    }
}