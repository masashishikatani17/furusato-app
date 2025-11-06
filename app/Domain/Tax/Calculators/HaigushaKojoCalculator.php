<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;
use App\Domain\Tax\Calculators\CommonSumsCalculator;
use App\Domain\Tax\Calculators\KojoAggregationCalculator;

class HaigushaKojoCalculator implements ProvidesKeys
{
    public const ID = 'kojo.haigusha';
    public const ORDER = 2300;
    public const ANCHOR = 'deductions';
    // 集計(KojoAggregation)より先に実行
    public const BEFORE = [KojoAggregationCalculator::ID];
    // 合計所得金額のSoT(CommonSums)確定後に実行
    public const AFTER = [CommonSumsCalculator::ID];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

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

    private const SPECIAL_BAND_VALUES_SHOTOKU = [
        1 => [380_000, 360_000, 310_000, 260_000, 210_000, 160_000, 110_000, 60_000, 30_000],
        2 => [260_000, 240_000, 210_000, 180_000, 140_000, 110_000, 80_000, 40_000, 20_000],
        3 => [130_000, 120_000, 110_000, 90_000, 70_000, 60_000, 40_000, 20_000, 10_000],
    ];

    private const SPECIAL_BAND_VALUES_JUMIN = [
        1 => [330_000, 330_000, 310_000, 260_000, 210_000, 160_000, 110_000, 60_000, 30_000],
        2 => [220_000, 220_000, 210_000, 180_000, 140_000, 110_000, 80_000, 40_000, 20_000],
        3 => [110_000, 110_000, 110_000, 90_000, 70_000, 60_000, 40_000, 20_000, 10_000],
    ];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        return [
            'kojo_haigusha_shotoku_prev', 'kojo_haigusha_shotoku_curr',
            'kojo_haigusha_jumin_prev', 'kojo_haigusha_jumin_curr',
            'kojo_haigusha_tokubetsu_shotoku_prev', 'kojo_haigusha_tokubetsu_shotoku_curr',
            'kojo_haigusha_tokubetsu_jumin_prev', 'kojo_haigusha_tokubetsu_jumin_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $updates = array_fill_keys(self::provides(), 0);

        // 令和7年(=2025年)分以降は配偶者特別控除の起算を 58万円超 に引き上げ
        $year = (int) ($ctx['master_kihu_year'] ?? $ctx['kihu_year'] ?? 0);
        $isR7OrLater = $year >= 2025;
        $spouseStartThreshold = $isR7OrLater ? 580_000 : 480_000;
        foreach (self::PERIODS as $period) {
            // 合計所得金額は CommonSums の SoT を参照
            $total = $this->n($payload[sprintf('sum_for_gokeishotoku_%s', $period)] ?? null);
            $category = $this->normalizeCategory($payload, $period);

            [$shotoku, $jumin] = $this->calculateSpousalDeduction($total, $category);
            $updates[sprintf('kojo_haigusha_shotoku_%s', $period)] = $shotoku;
            $updates[sprintf('kojo_haigusha_jumin_%s', $period)] = $jumin;

            // ▼ 配偶者の合計所得金額（入力フィールドを使用）
            $spouseIncome = $this->n($payload[sprintf('kojo_haigusha_tokubetsu_gokeishotoku_%s', $period)] ?? null);
            [$specialShotoku, $specialJumin] = $this->calculateSpecialDeduction($total, $spouseIncome, $spouseStartThreshold);
            $updates[sprintf('kojo_haigusha_tokubetsu_shotoku_%s', $period)] = $specialShotoku;
            $updates[sprintf('kojo_haigusha_tokubetsu_jumin_%s', $period)] = $specialJumin;
        }

        return array_replace($payload, $updates);
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

    private function calculateSpecialDeduction(int $total, int $spouseIncome, int $startThreshold): array
    {
        // 納税者1,000万円超は不可／配偶者の起算は年分で可変（48万→58万）／133万円超は不可
        if ($total > self::SPOUSAL_THRESHOLD || $spouseIncome <= $startThreshold || $spouseIncome > 1_330_000) {
            return [0, 0];
        }

        $band = $this->matchValue($total, self::SPECIAL_TOTAL_THRESHOLDS, self::SPECIAL_TOTAL_BANDS);
        $thresholds = self::SPECIAL_SPOUSE_THRESHOLDS;
        $thresholds[0] = $startThreshold + 1; // 「超」なので +1
        $index = $this->matchValue($spouseIncome, $thresholds, self::SPECIAL_SPOUSE_INDICES);

        if ($band === 0 || $index === 0) {
            return [0, 0];
        }

        $shotokuTable = self::SPECIAL_BAND_VALUES_SHOTOKU[$band] ?? null;
        $juminTable = self::SPECIAL_BAND_VALUES_JUMIN[$band] ?? null;

        if ($shotokuTable === null || $juminTable === null) {
            return [0, 0];
        }

        return [
            $shotokuTable[$index - 1] ?? 0,
            $juminTable[$index - 1] ?? 0,
        ];
    }

    private function normalizeCategory(array $payload, string $period): string
    {
        $key = sprintf('kojo_haigusha_category_%s', $period);
        $raw = (string) ($payload[$key] ?? '');

        $trimmed = trim($raw);
        $converted = mb_convert_kana($trimmed, 'asKV', 'UTF-8');
        $normalized = trim(mb_strtolower($converted, 'UTF-8'));
        $roman = str_replace(['ō', 'ô'], 'o', $normalized);
        $roman = preg_replace('/\s+/u', '', $roman) ?? '';

        $latinMatches = [
            'roujin' => '老',
            'rojin' => '老',
            'ippan' => '一',
            'none' => 'なし',
            'x' => 'なし',
        ];

        if (array_key_exists($roman, $latinMatches)) {
            return $latinMatches[$roman];
        }

        $headSource = $trimmed === ''
            ? ''
            : preg_replace('/^[\h\v　]+/u', '', $trimmed) ?? $trimmed;
        $head = mb_substr($headSource, 0, 1, 'UTF-8') ?: '';

        return match ($head) {
            '老' => '老',
            '一' => '一',
            'な', '×' => 'なし',
            default => 'なし',
        };
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