<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;
use Illuminate\Support\Facades\Log;

/**
 * v1: 既存の合成と完全同値の「代理値」を出力する薄いレイヤ
 *
 * - sum_for_gokeishotoku_{prev|curr}
 * - sum_for_sogoshotoku_etc_{prev|curr}
 * - sum_for_pension_bucket_{prev|curr}
 *
 * 参照元は「通算系（第一表素材）」= after_3 系を採用。
 * 丸め・下限は“現行踏襲”（ここでは行わない／元データのまま）。
 */
class CommonSumsCalculator implements ProvidesKeys
{
    public const ID     = 'common.sums';
    public const ORDER  = 4100; // 通算(4010/4020)の直後、控除集計(4000)より後に来ないよう注意
    public const BEFORE = [];
    public const AFTER  = [];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public static function provides(): array
    {
        $keys = [];
        foreach (self::PERIODS as $p) {
            $keys[] = "sum_for_gokeishotoku_{$p}";
            $keys[] = "sum_for_sogoshotoku_etc_{$p}";
            $keys[] = "sum_for_pension_bucket_{$p}";
        }
        return $keys;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $ctx
     * @return array<string,mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        foreach (self::PERIODS as $period) {
            // --- 第一表素材（after_3）から必要合計を復元 ---
            $keijo = $this->n($payload["after_3jitsusan_keijo_{$period}"] ?? null);
            $st    = $this->n($payload["after_3jitsusan_joto_tanki_sogo_{$period}"] ?? $payload["after_3jitsusan_tanki_sogo_{$period}"] ?? null);
            $lt    = $this->n($payload["after_3jitsusan_joto_choki_sogo_{$period}"] ?? $payload["after_3jitsusan_choki_sogo_{$period}"] ?? null);
            $it    = $this->n($payload["after_3jitsusan_ichiji_{$period}"] ?? null);
            $san   = $this->n($payload["after_3jitsusan_sanrin_{$period}"] ?? null);
            $ret   = $this->n($payload["after_3jitsusan_taishoku_{$period}"] ?? null);

            // 「総所得金額等」の現行同値（= 経常＋短期＋長期＋ max(0,一時)）
            $sogoEtc = $keijo + $st + $lt + max(0, $it);

            // 「合計所得金額」相当の現行同値（= 経常ベース：after_3 の和を shotoku_* と同様に）
            // v1 は「現行の shotoku_* 合成（Kojo 前）」と同値の代理値を置く
            $gokei = $keijo + $st + (int)floor($lt / 2) + (int)floor($it / 2) + $san + $ret;

            // 公的年金等の“外側合計”（KyuyoNenkin が用いる otherSum と同値）
            // = shotoku_ プレフィクスの当該期キーの総和から「公的年金等」キーのみ除外
            $pensionBucket = $this->sumShotokuOthersExcludingNenkin($payload, $period);

            $payload["sum_for_sogoshotoku_etc_{$period}"] = $sogoEtc;
            $payload["sum_for_gokeishotoku_{$period}"]    = $gokei;
            $payload["sum_for_pension_bucket_{$period}"]  = $pensionBucket;
        }

        // v1: 監視（debug時のみ）。計算不能時は 0 フォールバック
        if (config('app.debug')) {
            foreach (self::PERIODS as $period) {
                $legacy = ($this->n($payload["shotoku_keijo_{$period}"] ?? null)
                    + $this->n($payload["shotoku_joto_tanki_{$period}"] ?? null)
                    + $this->n($payload["shotoku_joto_choki_sogo_{$period}"] ?? null)
                    + max(0, $this->n($payload["shotoku_ichiji_{$period}"] ?? null)));
                $delta = ($payload["sum_for_sogoshotoku_etc_{$period}"] ?? 0) - $legacy;
                if ($delta !== 0) {
                    Log::warning("[common.sums] Δ(sum_for_sogoshotoku_etc_{$period} - legacy)={$delta}");
                }
            }
        }
        return $payload;
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int)floor((float)$v) : 0;
    }

    /**
     * KyuyoNenkinCalculator の otherSum（shotoku_*）と同じ走査で
     * 「公的年金等」キー（shotoku_zatsu_nenkin_shotoku_{$period}）のみ除外して合計。
     */
    private function sumShotokuOthersExcludingNenkin(array $payload, string $period): int
    {
        $sum = 0;
        $suffix = "_{$period}";
        $exclude = "shotoku_zatsu_nenkin_shotoku_{$period}";
        foreach ($payload as $k => $v) {
            if (!is_string($k)) continue;
            if (!str_starts_with($k, 'shotoku_')) continue;
            if (!str_ends_with($k, $suffix)) continue;
            if ($k === $exclude) continue;
            $sum += $this->n($v);
        }
        return $sum;
    }
}