<?php

namespace App\Services\Tax;

final class FurusatoMasterDefaults
{
    public const DEFAULT_YEAR = 2025;

    public static function shotoku(): array
    {
        return [
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 10, 'lower' => 0, 'upper' => 1_949_000, 'rate' => 5.000, 'deduction_amount' => 0, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 20, 'lower' => 1_950_000, 'upper' => 3_299_000, 'rate' => 10.000, 'deduction_amount' => 97_500, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 30, 'lower' => 3_300_000, 'upper' => 6_949_000, 'rate' => 20.000, 'deduction_amount' => 427_500, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 40, 'lower' => 6_950_000, 'upper' => 8_999_000, 'rate' => 23.000, 'deduction_amount' => 636_000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 50, 'lower' => 9_000_000, 'upper' => 17_999_000, 'rate' => 33.000, 'deduction_amount' => 1_536_000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 60, 'lower' => 18_000_000, 'upper' => 39_999_000, 'rate' => 40.000, 'deduction_amount' => 2_796_000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 70, 'lower' => 40_000_000, 'upper' => null, 'rate' => 45.000, 'deduction_amount' => 4_796_000, 'remark' => null],
        ];
    }

    public static function jumin(): array
    {
        return [
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 10, 'category' => '総合', 'sub_category' => null, 'city_specified' => 8.000, 'pref_specified' => 2.000, 'city_non_specified' => 6.000, 'pref_non_specified' => 4.000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 20, 'category' => '短期譲渡', 'sub_category' => '一般', 'city_specified' => 7.200, 'pref_specified' => 1.800, 'city_non_specified' => 5.400, 'pref_non_specified' => 3.600, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 30, 'category' => '短期譲渡', 'sub_category' => '軽減', 'city_specified' => 4.000, 'pref_specified' => 1.000, 'city_non_specified' => 3.000, 'pref_non_specified' => 2.000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 40, 'category' => '長期譲渡', 'sub_category' => '一般', 'city_specified' => 4.000, 'pref_specified' => 1.000, 'city_non_specified' => 3.000, 'pref_non_specified' => 2.000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 50, 'category' => '長期譲渡', 'sub_category' => '特定', 'city_specified' => 3.200, 'pref_specified' => 0.800, 'city_non_specified' => 2.400, 'pref_non_specified' => 1.600, 'remark' => '2,000万円以下の部分'],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 60, 'category' => '長期譲渡', 'sub_category' => '特定', 'city_specified' => 4.000, 'pref_specified' => 1.000, 'city_non_specified' => 3.000, 'pref_non_specified' => 2.000, 'remark' => '2,000万円超の部分'],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 70, 'category' => '長期譲渡', 'sub_category' => '軽課', 'city_specified' => 3.200, 'pref_specified' => 0.800, 'city_non_specified' => 2.400, 'pref_non_specified' => 1.600, 'remark' => '6,000万円以下の部分'],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 80, 'category' => '長期譲渡', 'sub_category' => '軽課', 'city_specified' => 4.000, 'pref_specified' => 1.000, 'city_non_specified' => 3.000, 'pref_non_specified' => 2.000, 'remark' => '6,000万円超の部分'],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 90, 'category' => '一般株式等の譲渡', 'sub_category' => null, 'city_specified' => 4.000, 'pref_specified' => 1.000, 'city_non_specified' => 3.000, 'pref_non_specified' => 2.000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 100, 'category' => '上場株式等の譲渡', 'sub_category' => null, 'city_specified' => 4.000, 'pref_specified' => 1.000, 'city_non_specified' => 3.000, 'pref_non_specified' => 2.000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 110, 'category' => '上場株式等の配当等', 'sub_category' => null, 'city_specified' => 4.000, 'pref_specified' => 1.000, 'city_non_specified' => 3.000, 'pref_non_specified' => 2.000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 120, 'category' => '先物取引', 'sub_category' => null, 'city_specified' => 4.000, 'pref_specified' => 1.000, 'city_non_specified' => 3.000, 'pref_non_specified' => 2.000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 130, 'category' => '山林', 'sub_category' => null, 'city_specified' => 8.000, 'pref_specified' => 2.000, 'city_non_specified' => 6.000, 'pref_non_specified' => 4.000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 140, 'category' => '退職', 'sub_category' => null, 'city_specified' => 8.000, 'pref_specified' => 2.000, 'city_non_specified' => 6.000, 'pref_non_specified' => 4.000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 150, 'category' => '調整控除', 'sub_category' => null, 'city_specified' => 4.000, 'pref_specified' => 1.000, 'city_non_specified' => 3.000, 'pref_non_specified' => 2.000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 160, 'category' => '基本控除', 'sub_category' => null, 'city_specified' => 8.000, 'pref_specified' => 2.000, 'city_non_specified' => 6.000, 'pref_non_specified' => 4.000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 170, 'category' => '特例控除', 'sub_category' => null, 'city_specified' => 0.800, 'pref_specified' => 0.200, 'city_non_specified' => 0.600, 'pref_non_specified' => 0.400, 'remark' => null],
        ];
    }

    public static function tokurei(): array
    {
        return [
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 10, 'lower' => 0, 'upper' => 1_950_000, 'income_rate' => 5.000, 'ninety_minus_rate' => 85.000, 'income_rate_with_recon' => 5.105, 'tokurei_deduction_rate' => 84.895, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 20, 'lower' => 1_951_000, 'upper' => 3_300_000, 'income_rate' => 10.000, 'ninety_minus_rate' => 80.000, 'income_rate_with_recon' => 10.210, 'tokurei_deduction_rate' => 79.790, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 30, 'lower' => 3_301_000, 'upper' => 6_950_000, 'income_rate' => 20.000, 'ninety_minus_rate' => 70.000, 'income_rate_with_recon' => 20.420, 'tokurei_deduction_rate' => 69.580, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 40, 'lower' => 6_951_000, 'upper' => 9_000_000, 'income_rate' => 23.000, 'ninety_minus_rate' => 67.000, 'income_rate_with_recon' => 23.483, 'tokurei_deduction_rate' => 66.517, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 50, 'lower' => 9_001_000, 'upper' => 18_000_000, 'income_rate' => 33.000, 'ninety_minus_rate' => 57.000, 'income_rate_with_recon' => 33.693, 'tokurei_deduction_rate' => 56.307, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 60, 'lower' => 18_001_000, 'upper' => 40_000_000, 'income_rate' => 40.000, 'ninety_minus_rate' => 50.000, 'income_rate_with_recon' => 40.840, 'tokurei_deduction_rate' => 49.160, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 70, 'lower' => 40_001_000, 'upper' => null, 'income_rate' => 45.000, 'ninety_minus_rate' => 45.000, 'income_rate_with_recon' => 45.945, 'tokurei_deduction_rate' => 44.055, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 80, 'lower' => null, 'upper' => null, 'income_rate' => 0.000, 'ninety_minus_rate' => 90.000, 'income_rate_with_recon' => 0.000, 'tokurei_deduction_rate' => 90.000, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 90, 'lower' => null, 'upper' => null, 'income_rate' => 30.000, 'ninety_minus_rate' => 60.000, 'income_rate_with_recon' => 30.630, 'tokurei_deduction_rate' => 59.370, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 100, 'lower' => null, 'upper' => null, 'income_rate' => 15.000, 'ninety_minus_rate' => 75.000, 'income_rate_with_recon' => 15.315, 'tokurei_deduction_rate' => 74.685, 'remark' => null],
        ];
    }

    public static function shinkokutokurei(): array
    {
        return [
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 10, 'lower' => 0, 'upper' => 1_950_000, 'ratio_a' => 5.105, 'ratio_b' => 84.895, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 20, 'lower' => 1_951_000, 'upper' => 3_300_000, 'ratio_a' => 10.210, 'ratio_b' => 79.790, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 30, 'lower' => 3_301_000, 'upper' => 6_950_000, 'ratio_a' => 20.420, 'ratio_b' => 69.580, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 40, 'lower' => 6_951_000, 'upper' => 9_000_000, 'ratio_a' => 23.483, 'ratio_b' => 66.517, 'remark' => null],
            ['year' => self::DEFAULT_YEAR, 'company_id' => null, 'sort' => 50, 'lower' => 9_001_000, 'upper' => null, 'ratio_a' => 33.693, 'ratio_b' => 56.307, 'remark' => null],
        ];
    }
}