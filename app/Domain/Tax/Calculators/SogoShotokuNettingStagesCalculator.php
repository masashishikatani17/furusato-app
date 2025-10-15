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

        $short = $this->value($payload, sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $period));
        $long = $this->value($payload, sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period));
        $ichiji = $this->value($payload, sprintf('after_joto_ichiji_tousan_ichiji_%s', $period));
        $forestInput = $this->value($payload, sprintf('bunri_shotoku_sanrin_shotoku_%s', $period));
        $retireInput = $this->value($payload, sprintf('bunri_shotoku_taishoku_shotoku_%s', $period));

        $outputs = [
            sprintf('tsusanmae_keijo_%s', $period) => $econ,
            sprintf('tsusanmae_joto_tanki_sogo_%s', $period) => $short,
            sprintf('tsusanmae_joto_choki_sogo_%s', $period) => $long,
            sprintf('tsusanmae_ichiji_%s', $period) => $ichiji,
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
        $ichijiAfter = $ichiji;

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
            sprintf('after_1jitsusan_ichiji_%s', $period) => $after1Ichiji,
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
            sprintf('after_2jitsusan_ichiji_%s', $period) => $after2Ichiji,
            sprintf('after_2jitsusan_sanrin_%s', $period) => $after2Forest,
            sprintf('after_2jitsusan_taishoku_%s', $period) => $after2Retire,
        ]);

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
}