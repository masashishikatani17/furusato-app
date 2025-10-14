<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class KisoKojoCalculator implements ProvidesKeys
{
    public const ID = 'kojo.kiso';
    public const ORDER = 2100;
    public const ANCHOR = 'deductions';
    public const BEFORE = [];
    public const AFTER = [];

    /** @var string[] */
    private const TOTAL_KEYS = [
        'shotoku_gokei_shotoku',
        'bunri_shotoku_tanki_ippan_shotoku',
        'bunri_shotoku_tanki_keigen_shotoku',
        'bunri_shotoku_choki_ippan_shotoku',
        'bunri_shotoku_choki_tokutei_shotoku',
        'bunri_shotoku_choki_keika_shotoku',
        'bunri_shotoku_ippan_kabuteki_joto_shotoku',
        'bunri_shotoku_jojo_kabuteki_joto_shotoku',
        'bunri_shotoku_jojo_kabuteki_haito_shotoku',
        'bunri_shotoku_sakimono_shotoku',
        'bunri_shotoku_sanrin_shotoku',
        'bunri_shotoku_taishoku_shotoku',
    ];

    private const SHOTOKU_2025_PREV_THRESHOLDS = [0, 24_000_001, 24_500_001, 25_000_001];
    private const SHOTOKU_2025_PREV_VALUES = [480_000, 320_000, 160_000, 0];

    private const SHOTOKU_2025_CURR_THRESHOLDS = [
        0,
        1_320_001,
        3_360_001,
        4_890_001,
        6_550_001,
        23_500_001,
        24_000_001,
        24_500_001,
        25_000_001,
    ];

    private const SHOTOKU_2025_CURR_VALUES = [
        950_000,
        880_000,
        680_000,
        630_000,
        580_000,
        480_000,
        320_000,
        160_000,
        0,
    ];

    private const JUMIN_THRESHOLDS = [0, 24_000_001, 24_500_001, 25_000_001];
    private const JUMIN_VALUES = [430_000, 290_000, 150_000, 0];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        return [
            'shotokuzei_kojo_kiso_prev',
            'shotokuzei_kojo_kiso_curr',
            'juminzei_kojo_kiso_prev',
            'juminzei_kojo_kiso_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $kihuYear = isset($ctx['kihu_year']) ? (int) $ctx['kihu_year'] : 0;

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

        $updates = [
            'shotokuzei_kojo_kiso_prev' => $this->matchIndex($totals['prev'], $shotokuPrevThresholds, $shotokuPrevValues),
            'shotokuzei_kojo_kiso_curr' => $this->matchIndex($totals['curr'], $shotokuCurrThresholds, $shotokuCurrValues),
            'juminzei_kojo_kiso_prev' => $this->matchIndex($totals['prev'], self::JUMIN_THRESHOLDS, self::JUMIN_VALUES),
            'juminzei_kojo_kiso_curr' => $this->matchIndex($totals['curr'], self::JUMIN_THRESHOLDS, self::JUMIN_VALUES),
        ];

        return array_replace($payload, $updates);
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

    /**
     * @param  array<int, int>  $thresholds
     * @param  array<int, int>  $values
     */
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