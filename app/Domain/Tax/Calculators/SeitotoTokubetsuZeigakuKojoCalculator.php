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
    //  - AFTER:  所得税額確定(ShotokuTax) → 寄附金「所得控除」(Kifukin)が I を供給 → 本Calculator
    //  - BEFORE: 住民税計算(JuminTax)より前に適用（所得税の税額控除）
    public const AFTER  = [ShotokuTaxCalculator::ID, KifukinCalculator::ID];
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
            // T：25%上限の基礎となる「所得税額」。
            // 現状は ShotokuTax 直後の tax_zeigaku_shotoku_* を用いる。
            // ※将来、他の“先順位の税額控除”（例：住宅ローン控除 等）を組み込む場合は、
            //   それら適用後の税額を T に反映する運用とすること。
            $T = $this->n($payload["tax_zeigaku_shotoku_{$p}"] ?? null);

            // 所得控除で使用済みの寄附「元本」I（KifukinCalculator が出力）
            $I = $this->n($payload["used_by_income_deduction_{$p}"] ?? null);

            // 40% 枠（元本ベース）の残余：税額控除に回せる母集団の上限（I との食い合い）
            $cap40        = intdiv(max($S, 0) * 4, 10);
            $capForCredit = max($cap40 - $I, 0);

            // 2,000 円の相殺（安全版）：まず所得控除 I で相殺した残りだけを税額控除側から引く。
            //  → I = 0 かつ 寄附合計 < 2,000 のときは、税額控除は必ず 0 になる。
            $floorRem = max(2000 - min($I, 2000), 0);
            $donEff   = max($donSum - $floorRem, 0);

            // 税額控除に回せる実効元本（最終）：2,000相殺後 × 40%枠残
            $baseTotal  = min($donEff, $capForCredit);

            // 25% 上限（税額ベース）
            //  - 公益＋NPO：共通25%枠（同一枠内で「公益→NPO」優先）
            //  - 政党等：別枠25%（同じTから算定するが、公益/NPOとは別に判定する）
            $cap25Common = $this->floor100((int) floor(max($T, 0) * 0.25));
            $cap25Seito  = $this->floor100((int) floor(max($T, 0) * 0.25));

            // ――― 25% 上限の厳密適用：「公益 → NPO」優先（No.1263 注4の整理に整合）―――
            $cap25Rem = $cap25Common;
            $baseLeft = $baseTotal;

            // 1) 公益：元本上限 / baseLeft / 25%上限（金額）を順に当て、100円切捨て
            $kouekiBaseMax    = min($donKoueki, $baseLeft);
            $kouekiBaseBy25   = $this->floorTo((int) floor($cap25Rem / 0.4), 250); // 0.4×250=100
            $allocKoueki      = min($kouekiBaseMax, $kouekiBaseBy25);
            $credKoueki       = $this->floor100((int) floor($allocKoueki * 0.40));
            $cap25Rem        -= $credKoueki;
            $baseLeft        -= $allocKoueki;

            // 2) NPO：公益控除後の 25% 残余で同様に制限
            $npoBaseMax       = min($donNpo, max($baseLeft, 0));
            $npoBaseBy25      = $this->floorTo((int) floor(max($cap25Rem, 0) / 0.4), 250);
            $allocNpo         = min($npoBaseMax, $npoBaseBy25);
            $credNpo          = $this->floor100((int) floor($allocNpo * 0.40));
            $baseLeft        -= $allocNpo;

            $allocSeitoMax    = min($donSeito, max($baseLeft, 0));

            // 政党等は「別枠」で 所得税額×25% 上限（税額ベース）を持つ
            // 30%控除なので、100円単位の整合を取りやすいよう元本は334円単位で刻む（0.3×334=100.2→floorで100）
            $seitoBaseBy25    = $this->floorTo((int) floor(max($cap25Seito, 0) / 0.3), 334);
            $allocSeito       = min($allocSeitoMax, $seitoBaseBy25);
            $credSeitoRaw     = $this->floor100((int) floor($allocSeito * 0.30));
            // 念のため二重ガード（端数・浮動小数の揺れでも cap を超えない）
            $credSeito        = min($credSeitoRaw, max($cap25Seito, 0));

            // 万一、寄附元本の偏在で baseTotal を使い切れない場合は（まれ）、
            // 40%側寄附が残っていれば政党枠へリバランスできないため、その分は自然に未使用となる。

            // ※この順序なら「公益を先に25%へ当て、残りでNPO」を満たし、
            //   かつ 2,000円の相殺を I + 40%側の元本で一体管理できる。

            $credTotal = $credNpo + $credKoueki + $credSeito;
            $taxAfter  = max($T - $credTotal, 0);

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