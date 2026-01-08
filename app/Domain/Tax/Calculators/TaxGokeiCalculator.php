<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

/**
 * 税額（支払額）SoT を確定する（UIの tax_kijun / tax_fukkou / tax_gokei をサーバ側で再現）
 *
 * - 所得税（shotoku）：
 *   住宅ローン控除・政党/NPO/公益の税額控除（Seitoto）適用後の tax_sashihiki_shotoku_* を基礎とし、
 *   災害減免額・令和6年度分特別税額控除を差し引いた「基準所得税額」を tax_kijun_shotoku_* に出す。
 *   その2.1%を復興特別所得税額 tax_fukkou_shotoku_* とし、合計 tax_gokei_shotoku_* を確定する。
 *
 * - 住民税（jumin）：
 *   配当控除・住宅ローン控除適用後の tax_after_jutaku_jumin_* を基礎とし、
 *   住民税寄附金税額控除（kifukin_zeigaku_kojo_gokei_*：天井キャップ後）と災害減免額を差し引いた
 *   「基準（所得割残）」を tax_kijun_jumin_* とし、合計 tax_gokei_jumin_* を確定する。
 *   （住民税の復興税はUI上「－」なので tax_fukkou_jumin_* は0で保持）
 */
final class TaxGokeiCalculator implements ProvidesKeys
{
    public const ID = 'tax.gokei';
    // 結果表示寄せ（上限探索で参照するため、なるべく後段で確定）
    public const ORDER = 9700;
    public const ANCHOR = 'tax';

    public const AFTER = [
        SeitotoTokubetsuZeigakuKojoCalculator::ID,
        JuminJutakuLoanCreditCalculator::ID,
        JuminzeiKifukinCalculator::ID,
    ];
    public const BEFORE = [
        FurusatoResultCalculator::ID,
        TaxBaseMirrorCalculator::ID,
    ];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public static function provides(): array
    {
        $keys = [];
        foreach (self::PERIODS as $p) {
            $keys[] = "tax_kijun_shotoku_{$p}";
            $keys[] = "tax_fukkou_shotoku_{$p}";
            $keys[] = "tax_gokei_shotoku_{$p}";

            $keys[] = "tax_kijun_jumin_{$p}";
            $keys[] = "tax_fukkou_jumin_{$p}";
            $keys[] = "tax_gokei_jumin_{$p}";
        }
        return $keys;
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
            // --- 所得税（支払額） ---
            // まずは「寄附税額控除等を適用した後の残税額」を基礎にする
            $baseItax = $this->n($payload["tax_sashihiki_shotoku_{$p}"] ?? null);
            if ($baseItax <= 0) {
                // 保険：最低限のフォールバック（通常は tax_sashihiki が埋まる）
                $baseItax = $this->n($payload["tax_after_jutaku_shotoku_{$p}"] ?? null);
            }
            if ($baseItax <= 0) {
                $baseItax = $this->n($payload["tax_zeigaku_shotoku_{$p}"] ?? null);
            }
            $baseItax = max(0, $baseItax);

            // 災害減免額・令和6特別（入力）を差し引いた「基準所得税額」
            $saigaiItax = $this->n($payload["tax_saigai_genmen_shotoku_{$p}"] ?? null);
            $r6Tokubetsu = $this->n($payload["tax_tokubetsu_R6_shotoku_{$p}"] ?? null);
            $kijunItax = max(0, $baseItax - max(0, $saigaiItax) - max(0, $r6Tokubetsu));

            // 復興特別所得税（2.1%）…UI同様に trunc
            $fukkou = (int) floor($kijunItax * 0.021);
            $gokeiItax = $kijunItax + $fukkou;

            $out["tax_kijun_shotoku_{$p}"]  = $kijunItax;
            $out["tax_fukkou_shotoku_{$p}"] = $fukkou;
            $out["tax_gokei_shotoku_{$p}"]  = $gokeiItax;

            // --- 住民税（支払額：所得割） ---
            // 住民税は「配当→住宅」適用後の残税額を基礎にし、寄附金税額控除（天井後）を差し引く
            $baseJumin = $this->n($payload["tax_after_jutaku_jumin_{$p}"] ?? null);
            if ($baseJumin <= 0) {
                // 保険：最低限のフォールバック
                $baseJumin = $this->n($payload["tax_zeigaku_jumin_{$p}"] ?? null);
            }
            $baseJumin = max(0, $baseJumin);

            $kifukin = $this->n($payload["kifukin_zeigaku_kojo_gokei_{$p}"] ?? null); // 天井キャップ後の合計
            $saigaiJ  = $this->n($payload["tax_saigai_genmen_jumin_{$p}"] ?? null);

            $kijunJumin = max(0, $baseJumin - max(0, $kifukin) - max(0, $saigaiJ));

            $out["tax_kijun_jumin_{$p}"]  = $kijunJumin;
            $out["tax_fukkou_jumin_{$p}"] = 0; // UIは「－」扱い。SoTは0で保持
            $out["tax_gokei_jumin_{$p}"]  = $kijunJumin;
        }

        return array_replace($payload, $out);
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int) floor((float) $v) : 0;
    }
}
