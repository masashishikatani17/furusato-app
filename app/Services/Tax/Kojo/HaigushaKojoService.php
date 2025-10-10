<?php

declare(strict_types=1);

namespace App\Services\Tax\Kojo;

use App\Services\Tax\Contracts\ProvidesKeys;

final class HaigushaKojoService implements ProvidesKeys
{
    private const PERIODS = ['prev', 'curr'];

    /**
     * @var string[]
     */
    private const TOTAL_BASE_KEYS = [
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

    private const SPOUSAL_THRESHOLD = 10_000_000;

    private const SPOUSAL_DEDUCTION_THRESHOLDS = [0, 9_000_001, 9_500_001];

    private const ELDERLY_SHOTOKU_VALUES = [480_000, 320_000, 160_000];
    private const ELDERLY_JUMIN_VALUES = [380_000, 260_000, 130_000];

    private const GENERAL_SHOTOKU_VALUES = [380_000, 260_000, 130_000];
    private const GENERAL_JUMIN_VALUES = [330_000, 220_000, 110_000];

    private const SPECIAL_TOTAL_THRESHOLDS = [0, 9_000_001, 9_500_001];
    private const SPECIAL_TOTAL_BANDS = [1, 2, 3];

    private const SPECIAL_SPOUSE_THRESHOLDS = [
        480_001,
        950_001,
        1_000_001,
        1_050_001,
        1_100_001,
        1_150_001,
        1_200_001,
        1_250_001,
        1_300_001,
    ];

    private const SPECIAL_SPOUSE_INDICES = [1, 2, 3, 4, 5, 6, 7, 8, 9];

    private const SPECIAL_BAND_VALUES = [
        1 => [330_000, 330_000, 310_000, 260_000, 210_000, 160_000, 110_000, 60_000, 30_000],
        2 => [220_000, 220_000, 210_000, 180_000, 140_000, 110_000, 80_000, 40_000, 20_000],
        3 => [110_000, 110_000, 110_000, 90_000, 70_000, 60_000, 40_000, 20_000, 10_000],
    ];

    /**
     * @return array<string, int>
     */
    public function compute(array $payload): array
    {
        $result = array_fill_keys(self::provides(), 0);

        foreach (self::PERIODS as $period) {
            $total = $this->calculateTotal($payload, $period);
            $category = $this->normalizeCategory($payload, $period);

            [$shotoku, $jumin] = $this->calculateSpousalDeduction($total, $category);
            $result[sprintf('kojo_haigusha_shotoku_%s', $period)] = $shotoku;
            $result[sprintf('kojo_haigusha_jumin_%s', $period)] = $jumin;

            $spouseIncome = $this->n($payload[sprintf('kojo_haigusha_tokubetsu_gokeishotoku_%s', $period)] ?? null);
            $special = $this->calculateSpecialDeduction($total, $spouseIncome);
            $result[sprintf('kojo_haigusha_tokubetsu_shotoku_%s', $period)] = $special;
            $result[sprintf('kojo_haigusha_tokubetsu_jumin_%s', $period)] = $special;
        }

        return $result;
    }

    public static function provides(): array
    {
        return [
            'kojo_haigusha_shotoku_prev', 'kojo_haigusha_shotoku_curr',
            'kojo_haigusha_jumin_prev', 'kojo_haigusha_jumin_curr',
            'kojo_haigusha_tokubetsu_shotoku_prev', 'kojo_haigusha_tokubetsu_shotoku_curr',
            'kojo_haigusha_tokubetsu_jumin_prev', 'kojo_haigusha_tokubetsu_jumin_curr',
        ];
    }

    private function calculateSpousalDeduction(int $total, string $category): array
    {
        if ($total > self::SPOUSAL_THRESHOLD) {
            return [0, 0];
        }

        return match ($category) {
            '老' => [
                $this->matchValue($total, self::SPOUSAL_DEDUCTION_THRESHOLDS, self::ELDERLY_SHOTOKU_VALUES),
                $this->matchValue($total, self::SPOUSAL_DEDUCTION_THRESHOLDS, self::ELDERLY_JUMIN_VALUES),
            ],
            '一' => [
                $this->matchValue($total, self::SPOUSAL_DEDUCTION_THRESHOLDS, self::GENERAL_SHOTOKU_VALUES),
                $this->matchValue($total, self::SPOUSAL_DEDUCTION_THRESHOLDS, self::GENERAL_JUMIN_VALUES),
            ],
            default => [0, 0],
        };
    }

    private function calculateSpecialDeduction(int $total, int $spouseIncome): int
    {
        if (
            $total > self::SPOUSAL_THRESHOLD
            || $spouseIncome <= 480_000
            || $spouseIncome > 1_330_000
        ) {
            return 0;
        }

        $band = $this->matchValue($total, self::SPECIAL_TOTAL_THRESHOLDS, self::SPECIAL_TOTAL_BANDS);
        $index = $this->matchValue($spouseIncome, self::SPECIAL_SPOUSE_THRESHOLDS, self::SPECIAL_SPOUSE_INDICES);

        if ($band === 0 || $index === 0) {
            return 0;
        }

        $table = self::SPECIAL_BAND_VALUES[$band] ?? null;

        if ($table === null) {
            return 0;
        }

        return $table[$index - 1] ?? 0;
    }

    private function normalizeCategory(array $payload, string $period): string
    {
        $raw = (string) ($payload[sprintf('kojo_haigusha_category_%s', $period)] ?? '');
        $head = mb_substr(trim($raw), 0, 1) ?: '';

        return match ($head) {
            '老' => '老',
            '一' => '一',
            'な', '×' => 'なし',
            default => 'なし',
        };
    }

    private function calculateTotal(array $payload, string $period): int
    {
        $total = 0;

        foreach (self::TOTAL_BASE_KEYS as $base) {
            $total += $this->n($payload[sprintf('%s_%s', $base, $period)] ?? null);
        }

        return $total;
    }

    private function matchValue(int $value, array $thresholds, array $values): int
    {
        $result = 0;

        foreach ($thresholds as $index => $threshold) {
            if ($value >= $threshold) {
                $result = $values[$index] ?? $result;
            } else {
                break;
            }
        }

        return $result;
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