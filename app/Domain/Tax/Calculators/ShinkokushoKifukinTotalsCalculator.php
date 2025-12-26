<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

/**
 * 申告書転記用（第二表㉘等）の「寄付金額合計」SoT を生成する。
 *
 * - 所得税：第二表㉘の「寄付金」へ転記する想定の “その年に支出した寄付金の合計額”
 * - 併せて、政党等/NPO/公益の税額控除明細書で必要になりやすい
 *   「当該寄付金（税額控除選択分）」と「それ以外」も SoT として保持する。
 *
 * ※UIで排他入力（所得控除 vs 税額控除）を実装していても、
 *   旧データ/直POSTで両方に値が入る可能性があるため、合計は max() で安全化する。
 */
final class ShinkokushoKifukinTotalsCalculator implements ProvidesKeys
{
    public const ID    = 'shinkokusho.kifukin.totals';
    // 【制度順】寄付の入力を受けた後、所得控除(Kifukin)より前に SoT を確定
    public const ORDER = 3200;
    public const BEFORE = [KifukinCalculator::ID];
    public const AFTER  = [];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public static function provides(): array
    {
        $keys = [];
        foreach (self::PERIODS as $p) {
            $keys[] = "shotoku_shinkokusho_kifu_total_{$p}";

            $keys[] = "shotoku_shinkokusho_kifu_seito_credit_{$p}";
            $keys[] = "shotoku_shinkokusho_kifu_other_for_seito_{$p}";

            $keys[] = "shotoku_shinkokusho_kifu_npo_credit_{$p}";
            $keys[] = "shotoku_shinkokusho_kifu_other_for_npo_{$p}";

            $keys[] = "shotoku_shinkokusho_kifu_koueki_credit_{$p}";
            $keys[] = "shotoku_shinkokusho_kifu_other_for_koueki_{$p}";
        }
        return $keys;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $updates = array_fill_keys(self::provides(), 0);

        foreach (self::PERIODS as $p) {
            // ▼ 所得控除（入力）側
            $furusato = $this->n($payload["shotokuzei_shotokukojo_furusato_{$p}"] ?? null);
            $kyodo    = $this->n($payload["shotokuzei_shotokukojo_kyodobokin_nisseki_{$p}"] ?? null);
            $kuni     = $this->n($payload["shotokuzei_shotokukojo_kuni_{$p}"] ?? null);
            $sonota   = $this->n($payload["shotokuzei_shotokukojo_sonota_{$p}"] ?? null);

            // ▼ 政党/NPO/公益：所得控除側 or 税額控除側のどちらか一方を採用（両方入りでも二重計上しない）
            $seitoIncome  = $this->n($payload["shotokuzei_shotokukojo_seito_{$p}"] ?? null);
            $npoIncome    = $this->n($payload["shotokuzei_shotokukojo_npo_{$p}"] ?? null);
            $kouekiIncome = $this->n($payload["shotokuzei_shotokukojo_koueki_{$p}"] ?? null);

            $seitoCredit  = $this->n($payload["shotokuzei_zeigakukojo_seito_{$p}"] ?? null);
            $npoCredit    = $this->n($payload["shotokuzei_zeigakukojo_npo_{$p}"] ?? null);
            $kouekiCredit = $this->n($payload["shotokuzei_zeigakukojo_koueki_{$p}"] ?? null);

            $seito  = max($seitoIncome,  $seitoCredit);
            $npo    = max($npoIncome,    $npoCredit);
            $koueki = max($kouekiIncome, $kouekiCredit);

            // ✅ 第二表㉘相当（所得税）：その年中に支出した寄付金の合計
            $total = $furusato + $kyodo + $kuni + $sonota + $seito + $npo + $koueki;
            $updates["shotoku_shinkokusho_kifu_total_{$p}"] = $total;

            // ✅ 明細書用（税額控除を選んだ時に使う想定）
            $updates["shotoku_shinkokusho_kifu_seito_credit_{$p}"] = $seitoCredit;
            $updates["shotoku_shinkokusho_kifu_other_for_seito_{$p}"] = max(0, $total - $seitoCredit);

            $updates["shotoku_shinkokusho_kifu_npo_credit_{$p}"] = $npoCredit;
            $updates["shotoku_shinkokusho_kifu_other_for_npo_{$p}"] = max(0, $total - $npoCredit);

            $updates["shotoku_shinkokusho_kifu_koueki_credit_{$p}"] = $kouekiCredit;
            $updates["shotoku_shinkokusho_kifu_other_for_koueki_{$p}"] = max(0, $total - $kouekiCredit);
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