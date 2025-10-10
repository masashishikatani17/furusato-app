<?php

namespace App\Services\Tax\Kojo;

final class KihonService
{
    private const TOTAL_KEYS = [
        'shotoku_gokei_shotoku',
        'bunri_tanki_ippan_shotoku',
        'bunri_tanki_keigen_shotoku',
        'bunri_choki_ippan_shotoku',
        'bunri_choki_tokutei_under_shotoku',
        'bunri_choki_tokutei_over_shotoku',
        'bunri_choki_keika_under_shotoku',
        'bunri_choki_keika_over_shotoku',
        'bunri_ippan_kabuteki_joto_shotoku',
        'bunri_jojo_kabuteki_joto_shotoku',
        'bunri_jojo_kabuteki_haito_shotoku',
        'bunri_sakimono_shotoku',
        'bunri_sanrin_shotoku',
        'bunri_taishoku_shotoku',
    ];

    private const SHOTOKU_2025_PREV_THRESHOLDS = [0, 24000001, 24500001, 25000001];
    private const SHOTOKU_2025_PREV_VALUES = [480000, 320000, 160000, 0];

    private const SHOTOKU_2025_CURR_THRESHOLDS = [
        0,
        1320001,
        3360001,
        4890001,
        6550001,
        23500001,
        24000001,
        24500001,
        25000001,
    ];

    private const SHOTOKU_2025_CURR_VALUES = [
        950000,
        880000,
        680000,
        630000,
        580000,
        480000,
        320000,
        160000,
        0,
    ];

    private const JUMIN_THRESHOLDS = [0, 24000001, 24500001, 25000001];
    private const JUMIN_VALUES = [430000, 290000, 150000, 0];

    public function computeKisoKojo(array $payload, int $kihuYear): array
    {
        $totals = [];
        foreach (['prev', 'curr'] as $period) {
            $totals[$period] = $this->sumTotalIncome($payload, $period);
        }

        $shotokuPrevThresholds = self::SHOTOKU_2025_PREV_THRESHOLDS;
        $shotokuPrevValues = self::SHOTOKU_2025_PREV_VALUES;
        $shotokuCurrThresholds = self::SHOTOKU_2025_CURR_THRESHOLDS;
        $shotokuCurrValues = self::SHOTOKU_2025_CURR_VALUES;

        if ($kihuYear >= 2026) {
            $shotokuPrevThresholds = self::SHOTOKU_2025_CURR_THRESHOLDS;
            $shotokuPrevValues = self::SHOTOKU_2025_CURR_VALUES;
        }

        if ($kihuYear >= 2026) {
            $shotokuCurrThresholds = self::SHOTOKU_2025_CURR_THRESHOLDS;
            $shotokuCurrValues = self::SHOTOKU_2025_CURR_VALUES;
        }

        $result = [];
        $result['shotokuzei_kojo_kiso_prev'] = $this->matchIndex($totals['prev'], $shotokuPrevThresholds, $shotokuPrevValues);
        $result['shotokuzei_kojo_kiso_curr'] = $this->matchIndex($totals['curr'], $shotokuCurrThresholds, $shotokuCurrValues);
        $result['juminzei_kojo_kiso_prev'] = $this->matchIndex($totals['prev'], self::JUMIN_THRESHOLDS, self::JUMIN_VALUES);
        $result['juminzei_kojo_kiso_curr'] = $this->matchIndex($totals['curr'], self::JUMIN_THRESHOLDS, self::JUMIN_VALUES);

        return $result;
    }

    private function sumTotalIncome(array $payload, string $period): int
    {
        $sum = 0;
        foreach (self::TOTAL_KEYS as $key) {
            $field = sprintf('%s_%s', $key, $period);
            $sum += $this->n($payload[$field] ?? 0);
        }

        return $sum;
    }

    private function matchIndex(int $total, array $thresholds, array $values): int
    {
        $index = 0;
        foreach ($thresholds as $i => $threshold) {
            if ($total >= $threshold) {
                $index = $i;
            } else {
                break;
            }
        }

        return $values[$index] ?? 0;
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