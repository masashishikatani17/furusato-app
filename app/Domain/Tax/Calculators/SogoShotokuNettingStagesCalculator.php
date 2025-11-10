<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;
use App\Domain\Tax\Calculators\Support\NettingHelpers;

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
            $keys[] = sprintf('after_1jitsusan_joto_tanki_%s', $period);
            $keys[] = sprintf('after_1jitsusan_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('after_1jitsusan_ichiji_%s', $period);
            $keys[] = sprintf('after_1jitsusan_sanrin_%s', $period);

            $keys[] = sprintf('after_2jitsusan_keijo_%s', $period);
            $keys[] = sprintf('after_2jitsusan_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('after_2jitsusan_joto_tanki_%s', $period);
            $keys[] = sprintf('after_2jitsusan_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('after_2jitsusan_ichiji_%s', $period);
            $keys[] = sprintf('after_2jitsusan_sanrin_%s', $period);
            $keys[] = sprintf('after_2jitsusan_taishoku_%s', $period);

            $keys[] = sprintf('after_3jitsusan_keijo_%s', $period);
            $keys[] = sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('after_3jitsusan_joto_tanki_%s', $period);
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

        $econ = (int) (
            $this->valueWithAliases($payload, [
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
            + max(0, $this->value($payload, sprintf('shotoku_zatsu_sonota_shotoku_%s', $period)))
        );
        
        $retireInput = $this->value($payload, sprintf('bunri_shotoku_taishoku_shotoku_%s', $period));

        /**
         * 表示の「通算前」= 内部通算後（after_joto_ichiji_tousan_*）。
         * 第1次通算の計算元も “短期/長期/一時 すべて” 内部通算後を用いる。
         * ただし山林だけは sashihiki_sanrin を用いる（第1次結果 after_1jitsusan_sanrin_* のみ）。
         */
        $tsusanmaeShort  = (int) $this->value($payload, sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $period));
        $tsusanmaeLong   = (int) $this->value($payload, sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period));
        $tsusanmaeIchiji = (int) $this->value($payload, sprintf('after_joto_ichiji_tousan_ichiji_%s', $period));

        // 第1次通算の演算元
        $shortT = $tsusanmaeShort;
        $longT  = $tsusanmaeLong;
        $oneTime = $tsusanmaeIchiji;

        $econPos = (int) max(0, $econ);
        $ltNeg = (int) max(0, -$longT);
        $stNeg = (int) max(0, -$shortT);
        $useEcon = (int) min($econPos, $ltNeg + $stNeg);

        $ltRaise = (int) min($useEcon, $ltNeg);
        $stRaise = (int) min($useEcon - $ltRaise, $stNeg);

        $econAfter = (int) ($econ - ($ltRaise + $stRaise));
        $stAfter = (int) ($shortT + $stRaise);
        $ltAfter = (int) ($longT + $ltRaise);
        $itAfter = $oneTime;

        $econNeg = (int) max(0, -$econAfter);
        $useFromSt = (int) min(max(0, $stAfter), $econNeg);
        $useFromLt = (int) min(max(0, $ltAfter), $econNeg - $useFromSt);
        $useFromIt = (int) min(max(0, $itAfter), $econNeg - $useFromSt - $useFromLt);

        $after1Econ = (int) ($econAfter + $useFromSt + $useFromLt + $useFromIt);
        $after1Short = (int) ($stAfter - $useFromSt);
        $after1Long = (int) ($ltAfter - $useFromLt);
        $after1Ichiji = (int) max(0, $itAfter - $useFromIt);
        /**
         * 山林の第1次通算 after_1 は「差引金額 − 特別控除額」を起点にする
         *  - 小数点以下は切り捨て（floor）、負になれば 0
         *  - 千円未満切捨てはここでは行わない（課税所得金額の算出段階でのみ）
         */
        $sashihikiRaw = $this->fvalue($payload, sprintf('sashihiki_sanrin_%s', $period));
        $tokubetsuRaw = $this->fvalue($payload, sprintf('tokubetsukojo_sanrin_%s', $period));
        $after1Forest = (int) max(0, floor($sashihikiRaw) - floor($tokubetsuRaw));

        [$after2Econ, $after2Short, $after2Long, $after2Forest, $after2Ichiji] = NettingHelpers::netWithForest(
            $after1Econ,
            $after1Short,
            $after1Long,
            $after1Forest,
            $after1Ichiji
        );
        $after2Retire = max(0, $retireInput);

        $outputs = [
            sprintf('tsusanmae_keijo_%s', $period) => $econ,
            sprintf('tsusanmae_joto_tanki_sogo_%s', $period) => $tsusanmaeShort,
            sprintf('tsusanmae_joto_choki_sogo_%s', $period) => $tsusanmaeLong,
            sprintf('tsusanmae_ichiji_%s', $period) => $tsusanmaeIchiji,
            sprintf('after_1jitsusan_keijo_%s', $period) => $after1Econ,
            sprintf('after_1jitsusan_joto_tanki_sogo_%s', $period) => $after1Short,
            sprintf('after_1jitsusan_joto_tanki_%s', $period) => $after1Short,
            sprintf('after_1jitsusan_joto_choki_sogo_%s', $period) => $after1Long,
            sprintf('after_1jitsusan_ichiji_%s', $period) => $after1Ichiji,
            sprintf('after_1jitsusan_sanrin_%s', $period) => $after1Forest,
            sprintf('after_2jitsusan_keijo_%s', $period) => $after2Econ,
            sprintf('after_2jitsusan_joto_tanki_sogo_%s', $period) => $after2Short,
            sprintf('after_2jitsusan_joto_tanki_%s', $period) => $after2Short,
            sprintf('after_2jitsusan_joto_choki_sogo_%s', $period) => $after2Long,
            sprintf('after_2jitsusan_ichiji_%s', $period) => $after2Ichiji,
            sprintf('after_2jitsusan_sanrin_%s', $period) => $after2Forest,
            sprintf('after_2jitsusan_taishoku_%s', $period) => $after2Retire,
        ];

        if ($bunriNettingOutputs !== []) {
            $outputs = array_replace($outputs, $bunriNettingOutputs);
        }

        // 第3次通算（NettingHelpers へ委譲）
        [$after3Econ, $after3Short, $after3Long, $after3Forest, $after3Ichiji, $after3Retire] =
            NettingHelpers::netWithRetirement(
                (int)$after2Econ, (int)$after2Short, (int)$after2Long,
                (int)$after2Forest, (int)$after2Ichiji, (int)$after2Retire
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
            
        $outputs = array_replace($outputs, [
            sprintf('after_3jitsusan_keijo_%s', $period) => $after3Econ,
            sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period) => $after3Short,
            sprintf('after_3jitsusan_joto_tanki_%s', $period) => $after3Short,
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
    /**
     * 小数点以下切り捨て判定用に float で値を取得する（カンマ・空白除去）
     */
    private function fvalue(array $payload, string $key): float
    {
        if (!array_key_exists($key, $payload)) {
            return 0.0;
        }
        $value = $payload[$key];
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }
        if (is_int($value)) {
            return (float) $value;
        }
        if (is_float($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        return 0.0;
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
        // === 0) 通算前（BunriNetting と同じソース／キー ===
        $sG = $this->resolveSeparatedValue($payload, $period, [
            'bunri_shotoku_tanki_ippan_shotoku_%s',
            'before_tsusan_tanki_ippan_%s',
        ]);
        $sR = $this->resolveSeparatedValue($payload, $period, [
            'bunri_shotoku_tanki_keigen_shotoku_%s',
            'before_tsusan_tanki_keigen_%s',
        ]);
        $lG = $this->resolveSeparatedValue($payload, $period, [
            'bunri_shotoku_choki_ippan_shotoku_%s',
            'before_tsusan_choki_ippan_%s',
        ]);
        $lT = $this->resolveSeparatedValue($payload, $period, [
            'bunri_shotoku_choki_tokutei_shotoku_%s',
            'before_tsusan_choki_tokutei_%s',
        ]);
        $lK = $this->resolveSeparatedValue($payload, $period, [
            'bunri_shotoku_choki_keika_shotoku_%s',
            'before_tsusan_choki_keika_%s',
        ]);

        // 出力: before_* はそのまま
        $out = [
            sprintf('before_tsusan_tanki_ippan_%s',  $period) => $sG,
            sprintf('before_tsusan_tanki_keigen_%s', $period) => $sR,
            sprintf('before_tsusan_choki_ippan_%s',  $period) => $lG,
            sprintf('before_tsusan_choki_tokutei_%s',$period) => $lT,
            sprintf('before_tsusan_choki_keika_%s',  $period) => $lK,
        ];

        // === 1) 第1次通算（BunriNetting と同じ順序で補填）===
        // 短期内: まず一般と軽減を相殺
        if ($sG >= 0 && $sR < 0) {
            $m = min($sG, -$sR); $sG -= $m; $sR += $m;
        } elseif ($sG < 0 && $sR >= 0) {
            $m = min($sR, -$sG); $sG += $m; $sR -= $m;
        }
        // 長期内: 一般/特定/軽課の順序相殺
        // 1-1 一般が負なら特定→軽課の順で補填
        if ($lG < 0) { $m = min(max(0, $lT), -$lG); $lG += $m; $lT -= $m; }
        if ($lG < 0) { $m = min(max(0, $lK), -$lG); $lG += $m; $lK -= $m; }
        // 1-2 特定が負なら一般→軽課
        if ($lT < 0) { $m = min(max(0, $lG), -$lT); $lG -= $m; $lT += $m; }
        if ($lT < 0) { $m = min(max(0, $lK), -$lT); $lK -= $m; $lT += $m; }
        // 1-3 軽課が負なら一般→特定
        if ($lK < 0) { $m = min(max(0, $lG), -$lK); $lG -= $m; $lK += $m; }
        if ($lK < 0) { $m = min(max(0, $lT), -$lK); $lT -= $m; $lK += $m; }

        // after_1 を出力
        $out += [
            sprintf('after_1jitsusan_tanki_ippan_%s',   $period) => $sG,
            sprintf('after_1jitsusan_tanki_keigen_%s',  $period) => $sR,
            sprintf('after_1jitsusan_choki_ippan_%s',   $period) => $lG,
            sprintf('after_1jitsusan_choki_tokutei_%s', $period) => $lT,
            sprintf('after_1jitsusan_choki_keika_%s',   $period) => $lK,
        ];

        // === 2) 第2次通算（短期群⇔長期群の相互補填） ===
        $sNeg = max(0, -min(0, $sG) - min(0, $sR));               // 短期の不足総量
        $lNeg = max(0, -min(0, $lG) - min(0, $lT) - min(0, $lK)); // 長期の不足総量

        if ($sNeg > 0 && ($lG > 0 || $lT > 0 || $lK > 0)) {
            // 長期→短期へ: G→T→K の順で取り出し、短期は G→R の順で埋める
            $give = 0;
            $m = min(max(0, $lG), $sNeg - $give); $lG -= $m; $give += $m;
            $m = min(max(0, $lT), $sNeg - $give); $lT -= $m; $give += $m;
            $m = min(max(0, $lK), $sNeg - $give); $lK -= $m; $give += $m;
            if ($sG < 0) { $u = min($give, -$sG); $sG += $u; $give -= $u; }
            if ($give > 0 && $sR < 0) { $u = min($give, -$sR); $sR += $u; $give -= $u; }
        } elseif ($lNeg > 0 && ($sG > 0 || $sR > 0)) {
            // 短期→長期へ: sG→sR の順に取り、長期は G→T→K の順で埋める
            $give = 0;
            $m = min(max(0, $sG), $lNeg - $give); $sG -= $m; $give += $m;
            $m = min(max(0, $sR), $lNeg - $give); $sR -= $m; $give += $m;
            if ($lG < 0) { $u = min($give, -$lG); $lG += $u; $give -= $u; }
            if ($give > 0 && $lT < 0) { $u = min($give, -$lT); $lT += $u; $give -= $u; }
            if ($give > 0 && $lK < 0) { $u = min($give, -$lK); $lK += $u; $give -= $u; }
        }

        // after_2 を出力
        $out += [
            sprintf('after_2jitsusan_tanki_ippan_%s',   $period) => $sG,
            sprintf('after_2jitsusan_tanki_keigen_%s',  $period) => $sR,
            sprintf('after_2jitsusan_choki_ippan_%s',   $period) => $lG,
            sprintf('after_2jitsusan_choki_tokutei_%s', $period) => $lT,
            sprintf('after_2jitsusan_choki_keika_%s',   $period) => $lK,
        ];

        return $out;
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