<?php

namespace App\Domain\Tax\Calculators\Support;

final class JotoIchijiNetting
{
    private const TOKUBETSU_KOJO_LIMIT = 500_000;

    /**
     * @return array{
     *     sashihiki_joto_tanki_sogo: int,
     *     sashihiki_joto_choki_sogo: int,
     *     tsusanmae_joto_tanki_sogo: int,
     *     tsusanmae_joto_choki_sogo: int,
     *     tsusanmae_ichiji: int,
     *     tsusango_joto_tanki_sogo: int,
     *     tsusango_joto_choki_sogo: int,
     *     tsusango_ichiji: int,
     *     tokubetsukojo_joto_tanki_sogo: int,
     *     tokubetsukojo_joto_choki_sogo: int,
     *     tokubetsukojo_ichiji: int,
     *     after_joto_ichiji_tousan_joto_tanki_sogo: int,
     *     after_joto_ichiji_tousan_joto_choki_sogo: int,
     *     after_joto_ichiji_tousan_ichiji: int,
     * }
     */
    public static function compute(int $short, int $long, int $ichiji): array
    {
        // ----------------------------
        // 1) 入力（差引金額）
        // ----------------------------
        $tsusanmaeShort = $short; // 負可
        $tsusanmaeLong  = $long;  // 負可
        $tsusanmaeIchiji = max(0, $ichiji); // 仕様：一時は min0

        // ----------------------------
        // 2) 内部通算（短期⇔長期）
        //    例：短期-200,000 長期350,000 → 短期0 長期150,000
        // ----------------------------
        [$naibuShort, $naibuLong] = self::netBetweenShortAndLong($tsusanmaeShort, $tsusanmaeLong);
        $naibuIchiji = $tsusanmaeIchiji;

        // 互換キーとして “内部通算後” を tsusango_*_sogo に保持（表示側が参照していても事故らないように）
        $tsusangoShort = $naibuShort;
        $tsusangoLong  = $naibuLong;
        $tsusangoIchiji = $naibuIchiji;

        // ----------------------------
        // 3) 特別控除 50万円（別枠）
        //    - 譲渡所得の特別控除：最大50万円（短期→長期の順）
        //    - 一時所得の特別控除：最大50万円（譲渡とは別枠）
        // ----------------------------
        $poolJoto = self::TOKUBETSU_KOJO_LIMIT;
        $tokubetsuShort = min($poolJoto, max(0, $tsusangoShort));
        $poolJoto -= $tokubetsuShort;
        $tokubetsuLong  = min($poolJoto, max(0, $tsusangoLong));

        // 一時は別枠で上限50万
        $tokubetsuIchiji = min(self::TOKUBETSU_KOJO_LIMIT, max(0, $tsusangoIchiji));

        $short0 = (int) ($tsusangoShort - $tokubetsuShort);
        $long0  = (int) ($tsusangoLong  - $tokubetsuLong);
        $one0   = (int) max(0, $tsusangoIchiji - $tokubetsuIchiji);

        // ----------------------------
        // 4) 譲渡⇔一時の通算
        //    - 両方マイナス残り：一時で短期→長期の順に補填
        //    - 片方がプラス：マイナス側は「もう片方→一時」の順に補填
        // ----------------------------
        $shortAfter = $short0;
        $longAfter  = $long0;
        $oneAfter   = $one0;

        if ($short0 < 0 && $long0 < 0) {
            // 一時で短期→長期
            $use = min($oneAfter, -$shortAfter);
            $shortAfter += $use;
            $oneAfter   -= $use;

            $use = min($oneAfter, -$longAfter);
            $longAfter += $use;
            $oneAfter  -= $use;
        } elseif ($short0 < 0 && $long0 >= 0) {
            // 長期→短期、残りは一時
            $m = min($longAfter, -$shortAfter);
            $shortAfter += $m;
            $longAfter  -= $m;

            if ($shortAfter < 0) {
                $use = min($oneAfter, -$shortAfter);
                $shortAfter += $use;
                $oneAfter   -= $use;
            }
        } elseif ($long0 < 0 && $short0 >= 0) {
            // 短期→長期、残りは一時
            $m = min($shortAfter, -$longAfter);
            $longAfter  += $m;
            $shortAfter -= $m;

            if ($longAfter < 0) {
                $use = min($oneAfter, -$longAfter);
                $longAfter += $use;
                $oneAfter  -= $use;
            }
        }

        return [
            'sashihiki_joto_tanki_sogo' => $tsusanmaeShort,
            'sashihiki_joto_choki_sogo' => $tsusanmaeLong,
            'tsusanmae_joto_tanki_sogo' => $tsusanmaeShort,
            'tsusanmae_joto_choki_sogo' => $tsusanmaeLong,
            'tsusanmae_ichiji' => $tsusanmaeIchiji,
            'tsusango_joto_tanki_sogo' => $tsusangoShort,
            'tsusango_joto_choki_sogo' => $tsusangoLong,
            'tsusango_ichiji' => $tsusangoIchiji,
            'tokubetsukojo_joto_tanki_sogo' => $tokubetsuShort,
            'tokubetsukojo_joto_choki_sogo' => $tokubetsuLong,
            'tokubetsukojo_ichiji' => $tokubetsuIchiji,
            'after_joto_ichiji_tousan_joto_tanki_sogo' => $shortAfter,
            'after_joto_ichiji_tousan_joto_choki_sogo' => $longAfter,
            'after_joto_ichiji_tousan_ichiji' => max(0, (int) $oneAfter),
        ];
    }

    /**
     * 短期⇔長期の内部通算（符号が反対なら相殺して片方が0になるまで）
     * @return array{0:int,1:int} [short, long]
     */
    private static function netBetweenShortAndLong(int $short, int $long): array
    {
        $s = $short;
        $l = $long;

        if ($s * $l < 0) {
            $m = min(abs($s), abs($l));
            if ($s < 0) {
                // 短期が負：長期の正で埋める
                $s += $m;
                $l -= $m;
            } else {
                // 長期が負：短期の正で埋める
                $s -= $m;
                $l += $m;
            }
        }

        return [(int) $s, (int) $l];
    }
}