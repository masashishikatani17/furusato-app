<?php

namespace App\Domain\Tax\Calculators;

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

        $econ = $this->valueWithAliases($payload, [
            sprintf('jigyo_eigyo_shotoku_%s', $period),
            sprintf('shotoku_jigyo_eigyo_shotoku_%s', $period),
        ])
            + $this->valueWithAliases($payload, [
                sprintf('shotoku_jigyo_nogyo_shotoku_%s', $period),
                sprintf('jigyo_nogyo_shotoku_%s', $period),
            ])
            + $this->valueWithAliases($payload, [
                sprintf('fudosan_shotoku_%s', $period),
                sprintf('shotoku_fudosan_shotoku_%s', $period),
            ])
            + max(0, $this->valueWithAliases($payload, [
                sprintf('shotoku_rishi_shotoku_%s', $period),
                sprintf('shotoku_rishi_%s', $period),
            ]))
            + max(0, $this->value($payload, sprintf('shotoku_haito_shotoku_%s', $period)))
            + max(0, $this->value($payload, sprintf('shotoku_kyuyo_shotoku_%s', $period)))
            + max(0, $this->valueWithAliases($payload, [
                sprintf('shotoku_zatsu_nenkin_shotoku_%s', $period),
            ]))
            + max(0, $this->value($payload, sprintf('shotoku_zatsu_gyomu_shotoku_%s', $period)))
            + max(0, $this->value($payload, sprintf('shotoku_zatsu_sonota_shotoku_%s', $period)));

        $retireInput = $this->value($payload, sprintf('bunri_shotoku_taishoku_shotoku_%s', $period));

        $sashihikiForest = $this->value($payload, sprintf('sashihiki_sanrin_%s', $period));

        $tsusanmaeShort = $this->value($payload, sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $period));
        $tsusanmaeLong = $this->value($payload, sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period));
        $tsusanmaeIchiji = $this->value($payload, sprintf('after_joto_ichiji_tousan_ichiji_%s', $period));

        $econPos = (int) max(0, $econ);
        $ltNeg = (int) max(0, -$tsusanmaeLong);
        $stNeg = (int) max(0, -$tsusanmaeShort);
        $useEcon = (int) min($econPos, $ltNeg + $stNeg);

        $ltRaise = (int) min($useEcon, $ltNeg);
        $stRaise = (int) min($useEcon - $ltRaise, $stNeg);

        $econAfter = (int) ($econ - ($ltRaise + $stRaise));
        $stAfter = (int) ($tsusanmaeShort + $stRaise);
        $ltAfter = (int) ($tsusanmaeLong + $ltRaise);
        $itAfter = (int) $tsusanmaeIchiji;

        $econNeg = (int) max(0, -$econAfter);
        $useFromSt = (int) min(max(0, $stAfter), $econNeg);
        $useFromLt = (int) min(max(0, $ltAfter), $econNeg - $useFromSt);
        $useFromIt = (int) min(max(0, $itAfter), $econNeg - $useFromSt - $useFromLt);

        $after1Econ = (int) ($econAfter + $useFromSt + $useFromLt + $useFromIt);

        $econNeg = (int) max(0, -$econAfter);
        $useFromSt = (int) min(max(0, $stAfter), $econNeg);

        $after1Short = (int) ($stAfter - $useFromSt);

        $econNeg = (int) max(0, -$econAfter);
        $useFromSt = (int) min(max(0, $stAfter), $econNeg);
        $useFromLt = (int) min(max(0, $ltAfter), $econNeg - $useFromSt);

        $after1Long = (int) ($ltAfter - $useFromLt);

        $econNeg = (int) max(0, -$econAfter);
        $useFromSt = (int) min(max(0, $stAfter), $econNeg);
        $useFromLt = (int) min(max(0, $ltAfter), $econNeg - $useFromSt);
        $useFromIt = (int) min(max(0, $itAfter), $econNeg - $useFromSt - $useFromLt);

        $after1Ichiji = (int) max(0, $itAfter - $useFromIt);
        $after1Forest = $sashihikiForest;

        [$after2Econ, $after2Short, $after2Long, $after2Forest, $after2Ichiji] = $this->netWithForest(
            $after1Econ,
            $after1Short,
            $after1Long,
            $after1Forest,
            $after1Ichiji
        );
        $after2Retire = max(0, $retireInput);

        [$after3Econ, $after3Short, $after3Long, $after3Forest, $after3Ichiji, $after3Retire] = $this->netWithRetirement(
            $after2Econ,
            $after2Short,
            $after2Long,
            $after2Forest,
            $after2Ichiji,
            $after2Retire
        );

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

        $outputs = [
            sprintf('tsusanmae_keijo_%s', $period) => $econ,
            sprintf('tsusanmae_joto_tanki_sogo_%s', $period) => $tsusanmaeShort,
            sprintf('tsusanmae_joto_choki_sogo_%s', $period) => $tsusanmaeLong,
            sprintf('tsusanmae_ichiji_%s', $period) => $tsusanmaeIchiji,
            sprintf('after_1jitsusan_keijo_%s', $period) => $after1Econ,
            sprintf('after_1jitsusan_joto_tanki_sogo_%s', $period) => $after1Short,
            sprintf('after_1jitsusan_joto_choki_sogo_%s', $period) => $after1Long,
            sprintf('after_1jitsusan_ichiji_%s', $period) => $after1Ichiji,
            sprintf('after_1jitsusan_sanrin_%s', $period) => $after1Forest,
            sprintf('after_2jitsusan_keijo_%s', $period) => $after2Econ,
            sprintf('after_2jitsusan_joto_tanki_sogo_%s', $period) => $after2Short,
            sprintf('after_2jitsusan_joto_choki_sogo_%s', $period) => $after2Long,
            sprintf('after_2jitsusan_ichiji_%s', $period) => $after2Ichiji,
            sprintf('after_2jitsusan_sanrin_%s', $period) => $after2Forest,
            sprintf('after_2jitsusan_taishoku_%s', $period) => $after2Retire,
        ];

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
            sprintf('after_3jitsusan_ichiji_%s', $period) => $after3Ichiji,
            sprintf('after_3jitsusan_sanrin_%s', $period) => $after3Forest,
            sprintf('after_3jitsusan_taishoku_%s', $period) => $after3Retire,
            sprintf('shotoku_keijo_%s', $period) => $shotokuKeijo,
            sprintf('shotoku_joto_tanki_%s', $period) => $shotokuJotoTanki,
            sprintf('shotoku_joto_choki_sogo_%s', $period) => $shotokuJotoChoki,
            sprintf('shotoku_ichiji_%s', $period) => $shotokuIchiji,
            sprintf('shotoku_sanrin_%s', $period) => $shotokuSanrin,
            sprintf('shotoku_taishoku_%s', $period) => $shotokuTaishoku,
            sprintf('shotoku_gokei_%s', $period) => $shotokuGokei,
        ]);

        if ($bunriNettingOutputs !== []) {
            $outputs = array_replace($outputs, $bunriNettingOutputs);
        }

        return $outputs;
    }

    /**
     * @return array{0:int,1:int,2:int,3:int,4:int}
     */
    private function netWithForest(int $econ, int $short, int $long, int $forest, int $ichiji): array
    {
        $econAfter = $econ;
        $shortAfter = $short;
        $longAfter = $long;
        $forestAfter = $forest;
        $ichijiAfter = $ichiji;

        if ($forestAfter >= 0) {
            $use = min($forestAfter, max(0, -$longAfter));
            $forestAfter -= $use;
            $longAfter += $use;

            $use = min($forestAfter, max(0, -$shortAfter));
            $forestAfter -= $use;
            $shortAfter += $use;

            $use = min($forestAfter, max(0, -$econAfter));
            $forestAfter -= $use;
            $econAfter += $use;
        } else {
            $need = max(0, -$forestAfter);

            $use = min(max(0, $econAfter), $need);
            $econAfter -= $use;
            $forestAfter += $use;
            $need = max(0, -$forestAfter);

            $use = min(max(0, $shortAfter), $need);
            $shortAfter -= $use;
            $forestAfter += $use;
            $need = max(0, -$forestAfter);

            $use = min(max(0, $longAfter), $need);
            $longAfter -= $use;
            $forestAfter += $use;
            $need = max(0, -$forestAfter);

            $use = min($ichijiAfter, $need);
            $ichijiAfter -= $use;
            $forestAfter += $use;
        }

        return [$econAfter, $shortAfter, $longAfter, $forestAfter, $ichijiAfter];
    }

    /**
     * @return array{0:int,1:int,2:int,3:int,4:int,5:int}
     */
    private function netWithRetirement(int $econ, int $short, int $long, int $forest, int $ichiji, int $retire): array
    {
        $econAfter = $econ;
        $shortAfter = $short;
        $longAfter = $long;
        $forestAfter = $forest;
        $ichijiAfter = $ichiji;
        $retireAfter = $retire;

        $use = min($retireAfter, max(0, -$longAfter));
        $retireAfter -= $use;
        $longAfter += $use;

        $use = min($retireAfter, max(0, -$shortAfter));
        $retireAfter -= $use;
        $shortAfter += $use;

        $use = min($retireAfter, max(0, -$econAfter));
        $retireAfter -= $use;
        $econAfter += $use;

        $use = min($retireAfter, max(0, -$forestAfter));
        $retireAfter -= $use;
        $forestAfter += $use;

        return [$econAfter, $shortAfter, $longAfter, $forestAfter, $ichijiAfter, $retireAfter];
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