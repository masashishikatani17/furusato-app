<?php

namespace App\Domain\Tax\Calculators;

use App\Domain\Tax\Calculators\Support\JotoIchijiNetting;
use App\Services\Tax\Contracts\ProvidesKeys;

class SogoShotokuNettingStagesCalculator implements ProvidesKeys
{
    public const ID = 'sogo.netting.stages';
    public const ORDER = 4010;
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
            $keys[] = sprintf('tsusanmae_keijo_%s', $period);
            $keys[] = sprintf('tsusanmae_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('tsusanmae_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('tsusanmae_ichiji_%s', $period);

            $keys[] = sprintf('after_1jitsusan_keijo_%s', $period);
            $keys[] = sprintf('after_1jitsusan_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('after_1jitsusan_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('after_1jitsusan_ichiji_%s', $period);
            $keys[] = sprintf('after_1jitsusan_sanrin_%s', $period);

            $keys[] = sprintf('after_2jitsusan_keijo_%s', $period);
            $keys[] = sprintf('after_2jitsusan_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('after_2jitsusan_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('after_2jitsusan_ichiji_%s', $period);
            $keys[] = sprintf('after_2jitsusan_sanrin_%s', $period);
            $keys[] = sprintf('after_2jitsusan_taishoku_%s', $period);

            $keys[] = sprintf('after_3jitsusan_keijo_%s', $period);
            $keys[] = sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('after_3jitsusan_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('after_3jitsusan_ichiji_%s', $period);
            $keys[] = sprintf('after_3jitsusan_sanrin_%s', $period);
            $keys[] = sprintf('after_3jitsusan_taishoku_%s', $period);

            $keys[] = sprintf('shotoku_keijo_%s', $period);
            $keys[] = sprintf('shotoku_joto_tanki_%s', $period);
            $keys[] = sprintf('shotoku_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('shotoku_ichiji_%s', $period);
            $keys[] = sprintf('shotoku_sanrin_%s', $period);
            $keys[] = sprintf('shotoku_taishoku_%s', $period);
            $keys[] = sprintf('shotoku_gokei_%s', $period);
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

        $bunriNettingOutputs = $this->calculateSeparatedNettingStages($payload, $period);

        $shortSource = $this->valueWithFallback(
            $payload,
            sprintf('sashihiki_joto_tanki_sogo_%s', $period),
            sprintf('sashihiki_joto_tanki_%s', $period)
        );
        $longSource = $this->valueWithFallback(
            $payload,
            sprintf('sashihiki_joto_choki_sogo_%s', $period),
            sprintf('sashihiki_joto_choki_%s', $period)
        );
        $ichijiSource = $this->value($payload, sprintf('sashihiki_ichiji_%s', $period));

        $jotoIchiji = JotoIchijiNetting::compute($shortSource, $longSource, $ichijiSource);

        $econ = $this->value($payload, sprintf('shotoku_jigyo_eigyo_shotoku_%s', $period))
            + $this->value($payload, sprintf('shotoku_jigyo_nogyo_shotoku_%s', $period))
            + $this->value($payload, sprintf('shotoku_fudosan_shotoku_%s', $period))
            + max(0, $this->value($payload, sprintf('shotoku_haito_shotoku_%s', $period)))
            + max(0, $this->value($payload, sprintf('shotoku_kyuyo_shotoku_%s', $period)))
            + max(0, $this->valueWithAliases($payload, [
                sprintf('shotoku_zatsu_nankin_shotoku_%s', $period),
                sprintf('shotoku_zatsu_nenkin_shotoku_%s', $period),
            ]))
            + max(0, $this->value($payload, sprintf('shotoku_zatsu_gyomu_shotoku_%s', $period)))
            + max(0, $this->value($payload, sprintf('shotoku_zatsu_sonota_shotoku_%s', $period)));

        $short = $jotoIchiji['after_joto_ichiji_tousan_joto_tanki'];
        $long = $jotoIchiji['after_joto_ichiji_tousan_joto_choki_sogo'];
        $ichijiNetting = $jotoIchiji['after_joto_ichiji_tousan_ichiji'];
        $tsusanmaeShort = $jotoIchiji['tsusanmae_joto_tanki_sogo'];
        $tsusanmaeLong = $jotoIchiji['tsusanmae_joto_choki_sogo'];
        $tsusanmaeIchiji = $jotoIchiji['tsusanmae_ichiji'];
        $tsusangoIchiji = $jotoIchiji['tsusango_ichiji'];
        $forestInput = $this->value($payload, sprintf('bunri_shotoku_sanrin_shotoku_%s', $period));
        $retireInput = $this->value($payload, sprintf('bunri_shotoku_taishoku_shotoku_%s', $period));

        $outputs = [
            sprintf('tsusanmae_keijo_%s', $period) => $econ,
            sprintf('tsusanmae_joto_tanki_sogo_%s', $period) => $tsusanmaeShort,
            sprintf('tsusanmae_joto_choki_sogo_%s', $period) => $tsusanmaeLong,
            sprintf('tsusanmae_ichiji_%s', $period) => $tsusanmaeIchiji,
        ];

        // 第1次通算
        $econPos = max(0, $econ);
        $longNeg = max(0, -$long);
        $shortNeg = max(0, -$short);

        $useEcon = min($econPos, $longNeg + $shortNeg);
        $longRaise = min($useEcon, $longNeg);
        $shortRaise = min($useEcon - $longRaise, $shortNeg);

        $econAfter = $econ - ($longRaise + $shortRaise);
        $shortAfter = $short + $shortRaise;
        $longAfter = $long + $longRaise;
        $ichijiAfter = $ichijiNetting;

        $econNeg = max(0, -$econAfter);
        $useFromShort = min(max(0, $shortAfter), $econNeg);
        $useFromLong = min(max(0, $longAfter), $econNeg - $useFromShort);
        $useFromIchiji = min(max(0, $ichijiAfter), $econNeg - $useFromShort - $useFromLong);

        $after1Econ = $econAfter + $useFromShort + $useFromLong + $useFromIchiji;
        $after1Short = $shortAfter - $useFromShort;
        $after1Long = $longAfter - $useFromLong;
        $after1Ichiji = max(0, $ichijiAfter - $useFromIchiji);
        $after1Forest = $forestInput;

        $outputs = array_replace($outputs, [
            sprintf('after_1jitsusan_keijo_%s', $period) => $after1Econ,
            sprintf('after_1jitsusan_joto_tanki_sogo_%s', $period) => $after1Short,
            sprintf('after_1jitsusan_joto_choki_sogo_%s', $period) => $after1Long,
            sprintf('after_1jitsusan_ichiji_%s', $period) => $tsusangoIchiji,
            sprintf('after_1jitsusan_sanrin_%s', $period) => $after1Forest,
        ]);

        // 第2次通算
        $forest = $after1Forest;

        $forestPos = max(0, $forest);
        $forestNeg = max(0, -$forest);

        $longNeg2 = max(0, -$after1Long);
        $shortNeg2 = max(0, -$after1Short);
        $econNeg2 = max(0, -$after1Econ);
        $longPos2 = max(0, $after1Long);
        $shortPos2 = max(0, $after1Short);
        $econPos2 = max(0, $after1Econ);
        $ichijiPos2 = max(0, $after1Ichiji);

        $useLongPos = min($forestPos, $longNeg2);
        $useShortPos = min($forestPos - $useLongPos, $shortNeg2);
        $useEconPos = min($forestPos - $useLongPos - $useShortPos, $econNeg2);

        $econWhenPos = $after1Econ + $useEconPos;

        $useFromEcon = min($econPos2, $forestNeg);
        $useFromShort2 = min($shortPos2, $forestNeg - $useFromEcon);
        $useFromLong2 = min($longPos2, $forestNeg - $useFromEcon - $useFromShort2);
        $useFromIchiji2 = min($ichijiPos2, $forestNeg - $useFromEcon - $useFromShort2 - $useFromLong2);

        $econWhenNeg = $after1Econ - $useFromEcon;

        $after2Econ = $forest >= 0 ? $econWhenPos : $econWhenNeg;

        $useLongPosShort = min($forestPos, $longNeg2);
        $useShortPosShort = min($forestPos - $useLongPosShort, $shortNeg2);
        $shortWhenPos = $after1Short + $useShortPosShort;

        $useFromEconShort = min($econPos2, $forestNeg);
        $useFromShortShort = min($shortPos2, $forestNeg - $useFromEconShort);
        $shortWhenNeg = $after1Short - $useFromShortShort;

        $after2Short = $forest >= 0 ? $shortWhenPos : $shortWhenNeg;

        $useLongPosLong = min($forestPos, $longNeg2);
        $longWhenPos = $after1Long + $useLongPosLong;

        $useFromEconLong = min($econPos2, $forestNeg);
        $useFromShortLong = min($shortPos2, $forestNeg - $useFromEconLong);
        $useFromLongLong = min($longPos2, $forestNeg - $useFromEconLong - $useFromShortLong);
        $longWhenNeg = $after1Long - $useFromLongLong;

        $after2Long = $forest >= 0 ? $longWhenPos : $longWhenNeg;

        $useLongPosIchiji = min($forestPos, $longNeg2);
        $useShortPosIchiji = min($forestPos - $useLongPosIchiji, $shortNeg2);
        $useEconPosIchiji = min($forestPos - $useLongPosIchiji - $useShortPosIchiji, $econNeg2);
        $ichijiWhenPos = $after1Ichiji;

        $useFromEconIchiji = min($econPos2, $forestNeg);
        $useFromShortIchiji = min($shortPos2, $forestNeg - $useFromEconIchiji);
        $useFromLongIchiji = min($longPos2, $forestNeg - $useFromEconIchiji - $useFromShortIchiji);
        $useFromIchijiIchiji = min($ichijiPos2, $forestNeg - $useFromEconIchiji - $useFromShortIchiji - $useFromLongIchiji);
        $ichijiWhenNeg = max(0, $after1Ichiji - $useFromIchijiIchiji);

        $after2Ichiji = $forest >= 0 ? $ichijiWhenPos : $ichijiWhenNeg;

        $forestAfterPos = $forest - ($useLongPos + $useShortPos + $useEconPos);
        $forestAfterNeg = $forest + ($useFromEcon + $useFromShort2 + $useFromLong2 + $useFromIchiji2);
        $after2Forest = $forest >= 0 ? $forestAfterPos : $forestAfterNeg;

        $after2Retire = max($retireInput, 0);

        $outputs = array_replace($outputs, [
            sprintf('after_2jitsusan_keijo_%s', $period) => $after2Econ,
            sprintf('after_2jitsusan_joto_tanki_sogo_%s', $period) => $after2Short,
            sprintf('after_2jitsusan_joto_choki_sogo_%s', $period) => $after2Long,
            sprintf('after_2jitsusan_ichiji_%s', $period) => $tsusangoIchiji,
            sprintf('after_2jitsusan_sanrin_%s', $period) => $after2Forest,
            sprintf('after_2jitsusan_taishoku_%s', $period) => $after2Retire,
        ]);

        if ($bunriNettingOutputs !== []) {
            $outputs = array_replace($outputs, $bunriNettingOutputs);
        }

        // 第3次通算
        $retire = $after2Retire;

        $retirePos = max(0, $retire);
        $longNeg3 = max(0, -$after2Long);
        $shortNeg3 = max(0, -$after2Short);
        $econNeg3 = max(0, -$after2Econ);
        $forestNeg3 = max(0, -$after2Forest);

        $useLong3 = min($retirePos, $longNeg3);
        $useShort3 = min($retirePos - $useLong3, $shortNeg3);
        $useEcon3 = min($retirePos - $useLong3 - $useShort3, $econNeg3);
        $useForest3 = min($retirePos - $useLong3 - $useShort3 - $useEcon3, $forestNeg3);

        $after3Econ = $after2Econ + $useEcon3;
        $after3Short = $after2Short + $useShort3;
        $after3Long = $after2Long + $useLong3;
        $after3Ichiji = $after2Ichiji;
        $after3Forest = $after2Forest + $useForest3;
        $after3Retire = $retire - ($useLong3 + $useShort3 + $useEcon3 + $useForest3);

        $outputs = array_replace($outputs, [
            sprintf('after_3jitsusan_keijo_%s', $period) => $after3Econ,
            sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period) => $after3Short,
            sprintf('after_3jitsusan_joto_choki_sogo_%s', $period) => $after3Long,
            sprintf('after_3jitsusan_ichiji_%s', $period) => $tsusangoIchiji,
            sprintf('after_3jitsusan_sanrin_%s', $period) => $after3Forest,
            sprintf('after_3jitsusan_taishoku_%s', $period) => $after3Retire,
        ]);

        // 最終所得
        $shotokuKeijo = $after3Econ;
        $shotokuJotoTanki = $after3Short;
        $shotokuJotoChoki = $this->half($after3Long);
        $shotokuIchiji = $this->half($after3Ichiji);
        $shotokuSanrin = $after3Forest;
        $shotokuTaishoku = $after3Retire;

        $shotokuGokei = $shotokuKeijo
            + $shotokuJotoTanki
            + $shotokuJotoChoki
            + $shotokuIchiji
            + $shotokuSanrin
            + $shotokuTaishoku;

        $outputs = array_replace($outputs, [
            sprintf('shotoku_keijo_%s', $period) => $shotokuKeijo,
            sprintf('shotoku_joto_tanki_%s', $period) => $shotokuJotoTanki,
            sprintf('shotoku_joto_choki_sogo_%s', $period) => $shotokuJotoChoki,
            sprintf('shotoku_ichiji_%s', $period) => $shotokuIchiji,
            sprintf('shotoku_sanrin_%s', $period) => $shotokuSanrin,
            sprintf('shotoku_taishoku_%s', $period) => $shotokuTaishoku,
            sprintf('shotoku_gokei_%s', $period) => $shotokuGokei,
        ]);

        return $outputs;
    }

    private function value(array $payload, string $key): int
    {
        if (! array_key_exists($key, $payload)) {
            return 0;
        }

        $value = $payload[$key];

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

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    private function valueWithAliases(array $payload, array $keys): int
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return $this->value($payload, $key);
            }
        }

        return 0;
    }

    private function half(int $value): int
    {
        return (int) intdiv($value, 2);
    }

    private function valueWithFallback(array $payload, string $primary, ?string $fallback = null): int
    {
        if (array_key_exists($primary, $payload)) {
            return $this->value($payload, $primary);
        }

        if ($fallback !== null && array_key_exists($fallback, $payload)) {
            return $this->value($payload, $fallback);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int>
     */
    private function calculateSeparatedNettingStages(array $payload, string $period): array
    {
        $shortGeneral = $this->resolveSeparatedValue($payload, $period, [
            'bunri_shotoku_tanki_ippan_shotoku_%s',
            'before_tsusan_tanki_ippan_%s',
        ]);
        $shortReduced = $this->resolveSeparatedValue($payload, $period, [
            'bunri_shotoku_tanki_keigen_shotoku_%s',
            'before_tsusan_tanki_keigen_%s',
        ]);
        $longGeneral = $this->resolveSeparatedValue($payload, $period, [
            'bunri_shotoku_choki_ippan_shotoku_%s',
            'before_tsusan_choki_ippan_%s',
        ]);
        $longReduced = $this->resolveSeparatedValue($payload, $period, [
            'bunri_shotoku_choki_tokutei_shotoku_%s',
            'before_tsusan_choki_tokutei_%s',
        ]);
        $longLight = $this->resolveSeparatedValue($payload, $period, [
            'bunri_shotoku_choki_keika_shotoku_%s',
            'before_tsusan_choki_keika_%s',
        ]);

        $shortGeneralAfter = $shortGeneral;
        $shortReducedAfter = $shortReduced;

        if ($shortGeneral >= 0 && $shortReduced < 0) {
            $move = min($shortGeneral, -$shortReduced);
            $shortGeneralAfter -= $move;
            $shortReducedAfter += $move;
        } elseif ($shortGeneral < 0 && $shortReduced >= 0) {
            $move = min($shortReduced, -$shortGeneral);
            $shortGeneralAfter += $move;
            $shortReducedAfter -= $move;
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

        $shortNeed = max(0, -(min(0, $shortGeneralAfter) + min(0, $shortReducedAfter)));
        $longNeed = max(0, -(min(0, $lgE) + min(0, $lrD) + min(0, $llC)));

        $giveLongGeneral = ($shortNeed > 0 && $longNeed === 0)
            ? min(max(0, $lgE), $shortNeed)
            : 0;
        $remainingShortNeed = $shortNeed > 0 && $longNeed === 0
            ? $shortNeed - $giveLongGeneral
            : 0;
        $giveLongReduced = ($shortNeed > 0 && $longNeed === 0)
            ? min(max(0, $lrD), $remainingShortNeed)
            : 0;
        $remainingShortNeed = $shortNeed > 0 && $longNeed === 0
            ? $remainingShortNeed - $giveLongReduced
            : 0;
        $giveLongLight = ($shortNeed > 0 && $longNeed === 0)
            ? min(max(0, $llC), $remainingShortNeed)
            : 0;
        $totalGivenToShort = $giveLongGeneral + $giveLongReduced + $giveLongLight;

        $increaseShortGeneral = min(max(0, -$shortGeneralAfter), $totalGivenToShort);
        $takeShortGeneral = ($longNeed > 0 && $shortNeed === 0)
            ? min(max(0, $shortGeneralAfter), $longNeed)
            : 0;
        $remainingLongNeed = $longNeed > 0 && $shortNeed === 0
            ? $longNeed - $takeShortGeneral
            : 0;
        $takeShortReduced = ($longNeed > 0 && $shortNeed === 0)
            ? min(max(0, $shortReducedAfter), $remainingLongNeed)
            : 0;

        $afterSecondShortGeneral = $shortGeneralAfter + $increaseShortGeneral - $takeShortGeneral;
        $increaseShortReduced = min(max(0, -$shortReducedAfter), max(0, $totalGivenToShort - $increaseShortGeneral));
        $afterSecondShortReduced = $shortReducedAfter + $increaseShortReduced - $takeShortReduced;

        $increaseLongGeneral = min(max(0, -$lgE), $takeShortGeneral + $takeShortReduced);
        $afterSecondLongGeneral = $lgE - $giveLongGeneral + $increaseLongGeneral;

        $availableForLongReduced = max(0, $takeShortGeneral + $takeShortReduced - $increaseLongGeneral);
        $increaseLongReduced = min(max(0, -$lrD), $availableForLongReduced);
        $afterSecondLongReduced = $lrD - $giveLongReduced + $increaseLongReduced;

        $availableForLongLight = max(0, $takeShortGeneral + $takeShortReduced - $increaseLongGeneral - $increaseLongReduced);
        $increaseLongLight = min(max(0, -$llC), $availableForLongLight);
        $afterSecondLongLight = $llC - $giveLongLight + $increaseLongLight;

        return [
            sprintf('before_tsusan_tanki_ippan_%s', $period) => $shortGeneral,
            sprintf('before_tsusan_tanki_keigen_%s', $period) => $shortReduced,
            sprintf('before_tsusan_choki_ippan_%s', $period) => $longGeneral,
            sprintf('before_tsusan_choki_tokutei_%s', $period) => $longReduced,
            sprintf('before_tsusan_choki_keika_%s', $period) => $longLight,
            sprintf('after_1jitsusan_tanki_ippan_%s', $period) => $shortGeneralAfter,
            sprintf('after_1jitsusan_tanki_keigen_%s', $period) => $shortReducedAfter,
            sprintf('after_1jitsusan_choki_ippan_%s', $period) => $lgE,
            sprintf('after_1jitsusan_choki_tokutei_%s', $period) => $lrD,
            sprintf('after_1jitsusan_choki_keika_%s', $period) => $llC,
            sprintf('after_2jitsusan_tanki_ippan_%s', $period) => $afterSecondShortGeneral,
            sprintf('after_2jitsusan_tanki_keigen_%s', $period) => $afterSecondShortReduced,
            sprintf('after_2jitsusan_choki_ippan_%s', $period) => $afterSecondLongGeneral,
            sprintf('after_2jitsusan_choki_tokutei_%s', $period) => $afterSecondLongReduced,
            sprintf('after_2jitsusan_choki_keika_%s', $period) => $afterSecondLongLight,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $patterns
     */
    private function resolveSeparatedValue(array $payload, string $period, array $patterns): int
    {
        foreach ($patterns as $pattern) {
            $key = sprintf($pattern, $period);
            if (array_key_exists($key, $payload)) {
                return $this->value($payload, $key);
            }
        }

        return 0;
    }
}