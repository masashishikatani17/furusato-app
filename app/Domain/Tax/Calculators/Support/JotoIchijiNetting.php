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
     *     tsusango_joto_tanki: int,
     *     tsusango_joto_choki_sogo: int,
     *     tsusango_ichiji: int,
     *     tokubetsukojo_joto_tanki: int,
     *     tokubetsukojo_joto_choki: int,
     *     tokubetsukojo_ichiji: int,
     *     after_joto_ichiji_tousan_joto_tanki: int,
     *     after_joto_ichiji_tousan_joto_choki_sogo: int,
     *     after_joto_ichiji_tousan_ichiji: int,
     * }
     */
    public static function compute(int $short, int $long, int $ichiji): array
    {
        $tsusanmaeShort = $short;
        $tsusanmaeLong = $long;
        $tsusanmaeIchiji = $ichiji;

        $tsusangoShort = self::netShortAgainstLong($tsusanmaeShort, $tsusanmaeLong);
        $tsusangoLong = self::netLongAgainstShort($tsusanmaeShort, $tsusanmaeLong);
        $tsusangoIchiji = max(0, $tsusanmaeIchiji);

        $tokubetsuShort = min(self::TOKUBETSU_KOJO_LIMIT, max(0, $tsusangoShort));
        $tokubetsuPool = self::TOKUBETSU_KOJO_LIMIT - $tokubetsuShort;
        $tokubetsuLong = min($tokubetsuPool, max(0, $tsusangoLong));
        $tokubetsuIchiji = min(self::TOKUBETSU_KOJO_LIMIT, $tsusangoIchiji);

        $shortInit = $tsusangoShort - $tokubetsuShort;
        $longInit = $tsusangoLong - $tokubetsuLong;
        $oneInit = max(0, $tsusangoIchiji - $tokubetsuIchiji);

        $needShort = max(0, -$shortInit);
        $useLongForShort = min(max(0, $longInit), $needShort);
        $remainingNeedShort = $needShort - $useLongForShort;
        $useOneForShort = min($oneInit, $remainingNeedShort);

        $needLong = max(0, -$longInit);
        $useShortForLong = min(max(0, $shortInit), $needLong);
        $remainingNeedLong = $needLong - $useShortForLong;
        $useOneForLong = min($oneInit, $remainingNeedLong);

        if ($shortInit < 0) {
            $shortAfter = $shortInit + $useLongForShort + $useOneForShort;
        } elseif ($longInit < 0) {
            $shortAfter = $shortInit - $useShortForLong;
        } else {
            $shortAfter = $shortInit;
        }

        if ($shortInit < 0) {
            $longAfter = $longInit - $useLongForShort;
        } elseif ($longInit < 0) {
            $longAfter = $longInit + $useShortForLong + $useOneForLong;
        } else {
            $longAfter = $longInit;
        }

        if ($shortInit < 0) {
            $oneAfter = $oneInit - $useOneForShort;
        } elseif ($longInit < 0) {
            $oneAfter = $oneInit - $useOneForLong;
        } else {
            $oneAfter = $oneInit;
        }

        return [
            'sashihiki_joto_tanki_sogo' => $tsusanmaeShort,
            'sashihiki_joto_choki_sogo' => $tsusanmaeLong,
            'tsusanmae_joto_tanki_sogo' => $tsusanmaeShort,
            'tsusanmae_joto_choki_sogo' => $tsusanmaeLong,
            'tsusanmae_ichiji' => $tsusanmaeIchiji,
            'tsusango_joto_tanki' => $tsusangoShort,
            'tsusango_joto_choki_sogo' => $tsusangoLong,
            'tsusango_ichiji' => $tsusangoIchiji,
            'tokubetsukojo_joto_tanki' => $tokubetsuShort,
            'tokubetsukojo_joto_choki' => $tokubetsuLong,
            'tokubetsukojo_ichiji' => $tokubetsuIchiji,
            'after_joto_ichiji_tousan_joto_tanki' => $shortAfter,
            'after_joto_ichiji_tousan_joto_choki_sogo' => $longAfter,
            'after_joto_ichiji_tousan_ichiji' => max(0, $oneAfter),
        ];
    }

    private static function netShortAgainstLong(int $short, int $long): int
    {
        if ($short * $long < 0) {
            if (abs($short) >= abs($long)) {
                return $short + $long;
            }

            return 0;
        }

        return $short;
    }

    private static function netLongAgainstShort(int $short, int $long): int
    {
        if ($short * $long < 0) {
            if (abs($long) > abs($short)) {
                return $short + $long;
            }

            return 0;
        }

        return $long;
    }
}