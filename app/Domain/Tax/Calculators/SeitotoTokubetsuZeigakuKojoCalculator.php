<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class SeitotoTokubetsuZeigakuKojoCalculator implements ProvidesKeys
{
    public const ID = 'credit.seitoto';
    public const ORDER = 6000;
    public const ANCHOR = 'credits';
    // 所得税額（ShotokuTax）確定後に適用し、住民税計算（JuminTax）より前に置く
    public const AFTER = [ShotokuTaxCalculator::ID];
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

            // SoT：総所得金額等（40% 上限の基礎）と 所得税額（25% 上限の基礎）
            $S = $this->n($payload["sum_for_sogoshotoku_etc_{$p}"] ?? null);   // 総所得金額等
            $T = $this->n($payload["tax_zeigaku_shotoku_{$p}"]     ?? null);   // 所得税額（控除適用前）

            // 所得控除で使用済みの寄附「元本」I（KifukinCalculator が出力）
            $I = $this->n($payload["used_by_income_deduction_{$p}"] ?? null);

            // 2,000円足切りの一体管理（所得控除側で使った分だけ残りを減らす）
            $floorRem = max(2000 - min($I, 2000), 0);
            $donEff   = max($donSum - $floorRem, 0);

            // 「総所得金額等の40%」共通枠の残り（所得控除 I を控除）
            $cap40          = intdiv(max($S, 0) * 4, 10);
            $capForCredit   = max($cap40 - $I, 0);
            $baseTotal      = min($donEff, $capForCredit);  // 税額控除に回せる実効「元本」合計

            // 25% 上限（NPO+公益のみ対象、政党等は別枠）
            $cap25Credit = $this->floor100( (int) floor(max($T, 0) * 0.25) );

            // ――― 最適配分ロジック ―――
            // 40%カテゴリの元本を cap25 の範囲で最大化（100円単位に対応する 250円刻み）
            $cap25BaseMax = $this->floorTo( (int) floor($cap25Credit / 0.4), 250); // 0.4×250=100
            $wantBase40   = min($cap25BaseMax, $baseTotal, $donNpo + $donKoueki);

            // 40%側の配分（高率優先：NPO → 公益）
            $allocNpo    = min($donNpo,    $wantBase40);
            $rem40       = $wantBase40 - $allocNpo;
            $allocKoueki = min($donKoueki, $rem40);
            $alloc40     = $allocNpo + $allocKoueki;

            // 残りは 30%（政党等）へ（寄附元本と baseTotal の制約内で）
            $allocSeito = min($donSeito, max($baseTotal - $alloc40, 0));

            // 万一、寄附元本の偏在で baseTotal を使い切れない場合は（まれ）、
            // 40%側寄附が残っていれば政党枠へリバランスできないため、その分は自然に未使用となる。

            // カテゴリ別税額控除（100円未満切捨て）
            $credNpoRaw    = (int) floor($allocNpo    * 0.40);
            $credKouRaw    = (int) floor($allocKoueki * 0.40);
            $credSeitoRaw  = (int) floor($allocSeito  * 0.30);

            $credNpo    = $this->floor100($credNpoRaw);
            $credKoueki = $this->floor100($credKouRaw);

            // 25% 上限に対する最終調整（NPO+公益のみ、比例按分→各カテゴリで100円切捨て）
            $sum40 = $credNpo + $credKoueki;
            if ($sum40 > $cap25Credit) {
                $ratio = $cap25Credit > 0 ? ($cap25Credit / $sum40) : 0.0;
                $credNpo    = $this->floor100((int) floor($credNpo * $ratio));
                $credKoueki = $this->floor100((int) floor($credKoueki * $ratio));
                $sum40      = $credNpo + $credKoueki; // 再集計（≦ cap25）
            }

            // 政党等は別枠（上限なし：このレイヤでは T だけが最終的な全体下限に効く）
            $credSeito = $this->floor100($credSeitoRaw);

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