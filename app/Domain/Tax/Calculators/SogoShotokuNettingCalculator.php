<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class SogoShotokuNettingCalculator implements ProvidesKeys
{
    public const ID = 'sogo.shotoku.netting';
    public const ORDER = 4000;
    public const BEFORE = [];
    public const AFTER = [];

    private const PERIODS = ['prev', 'curr'];
    private const NETTING_POOL = 500_000;

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [];

        foreach (self::PERIODS as $period) {
            $keys[] = sprintf('sashihiki_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('sashihiki_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('tsusango_joto_tanki_%s', $period);
            $keys[] = sprintf('tsusango_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('tsusango_ichiji_%s', $period);
            $keys[] = sprintf('tokubetsukojo_joto_tanki_%s', $period);
            $keys[] = sprintf('tokubetsukojo_joto_choki_%s', $period);
            $keys[] = sprintf('tokubetsukojo_ichiji_%s', $period);
            $keys[] = sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $period);
            $keys[] = sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('after_joto_ichiji_tousan_ichiji_%s', $period);
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string  $period
     * @return array<string, int>
     */
    public function compute(array $payload, string $period): array
    {
        if (! in_array($period, self::PERIODS, true)) {
            return [];
        }

        $shortKey = sprintf('sashihiki_joto_tanki_sogo_%s', $period);
        $longKey = sprintf('sashihiki_joto_choki_sogo_%s', $period);
        $ichijiKey = sprintf('sashihiki_ichiji_%s', $period);

        $short = $this->n($payload[$shortKey] ?? null);
        $long = $this->n($payload[$longKey] ?? null);
        $ichiji = $this->n($payload[$ichijiKey] ?? null);

        $preShort = ($short * $long) < 0
            ? (abs($short) >= abs($long) ? $short + $long : 0)
            : $short;

        $preLong = ($short * $long) < 0
            ? (abs($long) > abs($short) ? $short + $long : 0)
            : $long;

        $tsusangoShort = $preShort;
        $tsusangoLong = $preLong;
        $tsusangoIchiji = $ichiji;

        $tokubetsuShort = min(self::NETTING_POOL, max(0, $tsusangoShort));
        $tokubetsuLongPool = self::NETTING_POOL - $tokubetsuShort;
        $tokubetsuLong = min($tokubetsuLongPool, max(0, $tsusangoLong));
        $tokubetsuIchiji = min(self::NETTING_POOL, max(0, $tsusangoIchiji));

        $shortInit = $tsusangoShort - $tokubetsuShort;
        $longInit = $tsusangoLong - $tokubetsuLong;
        $oneInit = max(0, $tsusangoIchiji - $tokubetsuIchiji);

        $needShort = max(0, -$shortInit);
        $useLongForShort = min(max(0, $longInit), $needShort);
        $remainingNeedShort = $needShort - $useLongForShort;
        $useIchijiForShort = min($oneInit, $remainingNeedShort);

        $needLong = max(0, -$longInit);
        $useShortForLong = min(max(0, $shortInit), $needLong);
        $remainingNeedLong = $needLong - $useShortForLong;
        $useIchijiForLong = min($oneInit, $remainingNeedLong);

        if ($shortInit < 0) {
            $shortAfter = $shortInit + $useLongForShort + $useIchijiForShort;
        } elseif ($longInit < 0) {
            $shortAfter = $shortInit - $useShortForLong;
        } else {
            $shortAfter = $shortInit;
        }

        if ($shortInit < 0) {
            $longAfter = $longInit - $useLongForShort;
        } elseif ($longInit < 0) {
            $longAfter = $longInit + $useShortForLong + $useIchijiForLong;
        } else {
            $longAfter = $longInit;
        }

        if ($shortInit < 0) {
            $ichijiAfter = $oneInit - $useIchijiForShort;
        } elseif ($longInit < 0) {
            $ichijiAfter = $oneInit - $useIchijiForLong;
        } else {
            $ichijiAfter = $oneInit;
        }

        $outputs = [
            $shortKey => $short,
            $longKey => $long,
            sprintf('tsusango_joto_tanki_%s', $period) => $tsusangoShort,
            sprintf('tsusango_joto_choki_sogo_%s', $period) => $tsusangoLong,
            sprintf('tsusango_ichiji_%s', $period) => $tsusangoIchiji,
            sprintf('tokubetsukojo_joto_tanki_%s', $period) => $tokubetsuShort,
            sprintf('tokubetsukojo_joto_choki_%s', $period) => $tokubetsuLong,
            sprintf('tokubetsukojo_ichiji_%s', $period) => $tokubetsuIchiji,
            sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $period) => $shortAfter,
            sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period) => $longAfter,
            sprintf('after_joto_ichiji_tousan_ichiji_%s', $period) => max(0, $ichijiAfter),
        ];

        return $outputs;
    }

    private function n(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) floor($value);
        }

        if (is_numeric($value)) {
            return (int) floor((float) $value);
        }

        return 0;
    }
}