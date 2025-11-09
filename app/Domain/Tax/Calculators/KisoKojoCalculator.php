<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;
use App\Domain\Tax\Calculators\CommonSumsCalculator;
use App\Domain\Tax\Calculators\KojoAggregationCalculator;

class KisoKojoCalculator implements ProvidesKeys
{
    public const ID = 'kojo.kiso';
    // 【制度順】フェーズC：基礎控除（CommonSums後→Aggregation前）
    public const ORDER = 3230;
    public const ANCHOR = 'deductions';
    public const AFTER  = [CommonSumsCalculator::ID];
    public const BEFORE = [KojoAggregationCalculator::ID];

    // ★ 所得税：2025年改訂前（prev 用）／改訂後（curr 用）
    //   - 2025データ: prev=改訂前、curr=改訂後
    //   - 2026年以降: prev/curr とも改訂後
    //   - 2024年以前: prev/curr とも改訂前（後方互換）

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

        // SoT：合計所得金額（CommonSums）
        $totalPrev = $this->n($payload['sum_for_gokeishotoku_prev'] ?? null);
        $totalCurr = $this->n($payload['sum_for_gokeishotoku_curr'] ?? null);

        // 所得税（prev/curr）に使うテーブルを年次で決定
        //  2025: prev=改訂前 / curr=改訂後
        //  2026+: prev&curr=改訂後
        //  2024-: prev&curr=改訂前（後方互換）
        $useCurrTableForPrev = ($kihuYear >= 2026);
        $useCurrTableForCurr = ($kihuYear >= 2025);

        // prev
        $shotokuPrevThresholds = $useCurrTableForPrev
            ? self::SHOTOKU_2025_CURR_THRESHOLDS
            : self::SHOTOKU_2025_PREV_THRESHOLDS;
        $shotokuPrevValues = $useCurrTableForPrev
            ? self::SHOTOKU_2025_CURR_VALUES
            : self::SHOTOKU_2025_PREV_VALUES;

        // curr
        $shotokuCurrThresholds = $useCurrTableForCurr
            ? self::SHOTOKU_2025_CURR_THRESHOLDS
            : self::SHOTOKU_2025_PREV_THRESHOLDS;
        $shotokuCurrValues = $useCurrTableForCurr
            ? self::SHOTOKU_2025_CURR_VALUES
            : self::SHOTOKU_2025_PREV_VALUES;

        $updates = [
            'shotokuzei_kojo_kiso_prev' => $this->matchIndex($totalPrev, $shotokuPrevThresholds, $shotokuPrevValues),
            'shotokuzei_kojo_kiso_curr' => $this->matchIndex($totalCurr, $shotokuCurrThresholds, $shotokuCurrValues),
            'juminzei_kojo_kiso_prev'   => $this->matchIndex($totalPrev, self::JUMIN_THRESHOLDS, self::JUMIN_VALUES),
            'juminzei_kojo_kiso_curr'   => $this->matchIndex($totalCurr, self::JUMIN_THRESHOLDS, self::JUMIN_VALUES),
        ];

        return array_replace($payload, $updates);
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