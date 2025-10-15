<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class BunriNettingCalculator implements ProvidesKeys
{
    public const ID = 'bunri.netting';
    public const ORDER = 4020;
    public const BEFORE = [];
    public const AFTER = [];

    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [];

        foreach (self::PERIODS as $period) {
            $keys[] = sprintf('before_tsusan_tanki_ippan_%s', $period);
            $keys[] = sprintf('before_tsusan_tanki_keigen_%s', $period);
            $keys[] = sprintf('before_tsusan_choki_ippan_%s', $period);
            $keys[] = sprintf('before_tsusan_choki_tokutei_%s', $period);
            $keys[] = sprintf('before_tsusan_choki_keika_%s', $period);

            $keys[] = sprintf('after_1jitsusan_tanki_ippan_%s', $period);
            $keys[] = sprintf('after_1jitsusan_tanki_keigen_%s', $period);
            $keys[] = sprintf('after_1jitsusan_choki_ippan_%s', $period);
            $keys[] = sprintf('after_1jitsusan_tanki_tokutei_%s', $period);
            $keys[] = sprintf('after_1jitsusan_tanki_keika_%s', $period);

            $keys[] = sprintf('after_2jitsusan_tanki_ippan_%s', $period);
            $keys[] = sprintf('after_2jitsusan_tanki_keigen_%s', $period);
            $keys[] = sprintf('after_2jitsusan_choki_ippan_%s', $period);
            $keys[] = sprintf('after_2jitsusan_tanki_tokutei_%s', $period);
            $keys[] = sprintf('after_2jitsusan_tanki_keika_%s', $period);
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

        $shortGeneral = $this->n($payload[sprintf('bunri_shotoku_tanki_ippan_shotoku_%s', $period)] ?? null);
        $shortReduced = $this->n($payload[sprintf('bunri_shotoku_tanki_keigen_shotoku_%s', $period)] ?? null);
        $longGeneral = $this->n($payload[sprintf('bunri_shotoku_choki_ippan_shotoku_%s', $period)] ?? null);
        $longReduced = $this->n($payload[sprintf('bunri_shotoku_choki_tokutei_shotoku_%s', $period)] ?? null);
        $longLight = $this->n($payload[sprintf('bunri_shotoku_choki_keika_shotoku_%s', $period)] ?? null);

        $before = [
            sprintf('before_tsusan_tanki_ippan_%s', $period) => $shortGeneral,
            sprintf('before_tsusan_tanki_keigen_%s', $period) => $shortReduced,
            sprintf('before_tsusan_choki_ippan_%s', $period) => $longGeneral,
            sprintf('before_tsusan_choki_tokutei_%s', $period) => $longReduced,
            sprintf('before_tsusan_choki_keika_%s', $period) => $longLight,
        ];

        $shortGeneralAfter = $shortGeneral;
        if ($shortGeneral >= 0 && $shortReduced < 0) {
            $shortGeneralAfter = $shortGeneral - min($shortGeneral, -$shortReduced);
        } elseif ($shortGeneral < 0 && $shortReduced >= 0) {
            $shortGeneralAfter = $shortGeneral + min($shortReduced, -$shortGeneral);
        }

        $shortReducedAfter = $shortReduced;
        if ($shortGeneral >= 0 && $shortReduced < 0) {
            $shortReducedAfter = $shortReduced + min($shortGeneral, -$shortReduced);
        } elseif ($shortGeneral < 0 && $shortReduced >= 0) {
            $shortReducedAfter = $shortReduced - min($shortReduced, -$shortGeneral);
        }

        $needGeneral = max(0, -$longGeneral);
        $moveReducedToGeneral = min(max(0, $longReduced), $needGeneral);
        $lgA = $longGeneral + $moveReducedToGeneral;
        $lrA = $longReduced - $moveReducedToGeneral;
        $llA = $longLight;

        $needGeneral2 = max(0, -$lgA);
        $moveLightToGeneral = min(max(0, $llA), $needGeneral2);
        $lgB = $lgA + $moveLightToGeneral;
        $lrB = $lrA;
        $llB = $llA - $moveLightToGeneral;

        $needReduced = max(0, -$lrB);
        $moveGeneralToReduced = min(max(0, $lgB), $needReduced);
        $lgC = $lgB - $moveGeneralToReduced;
        $lrC = $lrB + $moveGeneralToReduced;

        $needReduced2 = max(0, -$lrC);
        $moveLightToReduced = min(max(0, $llB), $needReduced2);
        $lgD = $lgC;
        $lrD = $lrC + $moveLightToReduced;
        $llC = $llB - $moveLightToReduced;

        $needLight = max(0, -$llC);
        $moveGeneralToLight = min(max(0, $lgD), $needLight);
        $lgE = $lgD - $moveGeneralToLight;
        $llD = $llC + $moveGeneralToLight;

        $needLight2 = max(0, -$llD);
        $moveReducedToLight = min(max(0, $lrD), $needLight2);
        unset($moveReducedToLight);

        $afterFirstStage = [
            sprintf('after_1jitsusan_tanki_ippan_%s', $period) => $shortGeneralAfter,
            sprintf('after_1jitsusan_tanki_keigen_%s', $period) => $shortReducedAfter,
            sprintf('after_1jitsusan_choki_ippan_%s', $period) => $lgE,
            sprintf('after_1jitsusan_tanki_tokutei_%s', $period) => $lrD,
            sprintf('after_1jitsusan_tanki_keika_%s', $period) => $llC,
        ];

        $sGeneral = $shortGeneralAfter;
        $sReduced = $shortReducedAfter;
        $lGeneral = $lgE;
        $lReduced = $lrD;
        $lLight = $llC;

        $shortNeed = max(0, -(min(0, $sGeneral) + min(0, $sReduced)));
        $longNeed = max(0, -(min(0, $lGeneral) + min(0, $lReduced) + min(0, $lLight)));

        $giveLg = ($shortNeed > 0 && $longNeed === 0) ? min(max(0, $lGeneral), $shortNeed) : 0;
        $remS1 = ($shortNeed > 0 && $longNeed === 0) ? $shortNeed - $giveLg : 0;
        $giveLr = ($shortNeed > 0 && $longNeed === 0) ? min(max(0, $lReduced), $remS1) : 0;
        $remS2 = ($shortNeed > 0 && $longNeed === 0) ? $remS1 - $giveLr : 0;
        $giveLl = ($shortNeed > 0 && $longNeed === 0) ? min(max(0, $lLight), $remS2) : 0;
        $totalToShort = $giveLg + $giveLr + $giveLl;

        $incSGeneral = min(max(0, -$sGeneral), $totalToShort);
        $takeSg = ($longNeed > 0 && $shortNeed === 0) ? min(max(0, $sGeneral), $longNeed) : 0;
        $remL1 = ($longNeed > 0 && $shortNeed === 0) ? $longNeed - $takeSg : 0;
        $takeSr = ($longNeed > 0 && $shortNeed === 0) ? min(max(0, $sReduced), $remL1) : 0;

        $afterSecondShortGeneral = $sGeneral + $incSGeneral - $takeSg;
        $incSReduced = min(max(0, -$sReduced), max(0, $totalToShort - $incSGeneral));
        $afterSecondShortReduced = $sReduced + $incSReduced - $takeSr;

        $incLGeneral = min(max(0, -$lGeneral), $takeSg + $takeSr);
        $afterSecondLongGeneral = $lGeneral - $giveLg + $incLGeneral;

        $availableForReduced = max(0, $takeSg + $takeSr - $incLGeneral);
        $incLReduced = min(max(0, -$lReduced), $availableForReduced);
        $afterSecondLongReduced = $lReduced - $giveLr + $incLReduced;

        $availableForLight = max(0, $takeSg + $takeSr - $incLGeneral - $incLReduced);
        $incLLight = min(max(0, -$lLight), $availableForLight);
        $afterSecondLongLight = $lLight - $giveLl + $incLLight;

        $afterSecondStage = [
            sprintf('after_2jitsusan_tanki_ippan_%s', $period) => $afterSecondShortGeneral,
            sprintf('after_2jitsusan_tanki_keigen_%s', $period) => $afterSecondShortReduced,
            sprintf('after_2jitsusan_choki_ippan_%s', $period) => $afterSecondLongGeneral,
            sprintf('after_2jitsusan_tanki_tokutei_%s', $period) => $afterSecondLongReduced,
            sprintf('after_2jitsusan_tanki_keika_%s', $period) => $afterSecondLongLight,
        ];

        return array_replace($before, $afterFirstStage, $afterSecondStage);
    }

    private function n(mixed $value): int
    {
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (int) ((float) $value);
        }

        return 0;
    }
}