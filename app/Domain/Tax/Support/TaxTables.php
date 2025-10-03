<?php
namespace App\Domain\Tax\Support;

/**
 * 税率テーブル（復興特別所得税込みの合成率→ x * 1.021）
 * 帯は「課税総所得金額から人的控除差調整額を控除した金額」を目安（初版簡易）
 */
final class TaxTables
{
    /**
     * 所得税（復興特別込み）の合成率（分子/分母で返す：例 33.693% => [33693, 100000]）
     */
    public static function incomeTaxRateInclSurtax(int $taxableForBand): array
    {
        // 帯境界は国税の速算表に準拠（初版簡易）
        if ($taxableForBand <= 1_950_000) return [5105, 100000];      // 5.105%
        if ($taxableForBand <= 3_300_000) return [10210, 100000];     // 10.21%
        if ($taxableForBand <= 6_950_000) return [20420, 100000];     // 20.42%
        if ($taxableForBand <= 9_000_000) return [23483, 100000];     // 23.483%
        if ($taxableForBand <= 18_000_000) return [33693, 100000];    // 33.693%
        if ($taxableForBand <= 40_000_000) return [40840, 100000];    // 40.84%
        return [45945, 100000];                                       // 45.945%
    }
}