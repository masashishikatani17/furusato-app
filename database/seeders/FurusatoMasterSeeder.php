<?php

namespace Database\Seeders;

use App\Models\JuminRate;
use App\Models\ShinkokutokureiRate;
use App\Models\ShotokuRate;
use App\Models\TokureiRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class FurusatoMasterSeeder extends Seeder
{
    public function run(): void
    {
        $year = 2025;
        $version = 1;
        $now = Carbon::now();

        ShotokuRate::truncate();
        ShotokuRate::insert($this->shotokuRates($year, $version, $now));

        JuminRate::truncate();
        JuminRate::insert($this->juminRates($year, $version, $now));

        TokureiRate::truncate();
        TokureiRate::insert($this->tokureiRates($year, $version, $now));

        ShinkokutokureiRate::truncate();
        ShinkokutokureiRate::insert($this->shinkokutokureiRates($year, $version, $now));
    }

    private function shotokuRates(int $year, int $version, Carbon $now): array
    {
        $rows = [
            ['lower' => 0, 'upper' => 1_949_000, 'rate' => 5.000, 'deduction' => 0],
            ['lower' => 1_950_000, 'upper' => 3_299_000, 'rate' => 10.000, 'deduction' => 97_500],
            ['lower' => 3_300_000, 'upper' => 6_949_000, 'rate' => 20.000, 'deduction' => 427_500],
            ['lower' => 6_950_000, 'upper' => 8_999_000, 'rate' => 23.000, 'deduction' => 636_000],
            ['lower' => 9_000_000, 'upper' => 17_999_000, 'rate' => 33.000, 'deduction' => 1_536_000],
            ['lower' => 18_000_000, 'upper' => 39_999_000, 'rate' => 40.000, 'deduction' => 2_796_000],
            ['lower' => 40_000_000, 'upper' => null, 'rate' => 45.000, 'deduction' => 4_796_000],
        ];

        return $this->mapShotoku($rows, $year, $version, $now);
    }

    private function mapShotoku(array $rows, int $year, int $version, Carbon $now): array
    {
        return array_map(function (array $row, int $index) use ($year, $version, $now): array {
            return [
                'company_id' => null,
                'kihu_year' => $year,
                'version' => $version,
                'seq' => $index + 1,
                'lower' => $row['lower'],
                'upper' => $row['upper'],
                'rate' => $row['rate'],
                'deduction_amount' => $row['deduction'],
                'note' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $rows, array_keys($rows));
    }

    private function juminRates(int $year, int $version, Carbon $now): array
    {
        $rows = [
            ['category' => '総合', 'sub' => null, 'city' => 8.000, 'pref' => 2.000, 'city_other' => 6.000, 'pref_other' => 4.000, 'remark' => null],
            ['category' => '短期譲渡', 'sub' => '一般', 'city' => 7.200, 'pref' => 1.800, 'city_other' => 5.400, 'pref_other' => 3.600, 'remark' => null],
            ['category' => '短期譲渡', 'sub' => '軽減', 'city' => 4.000, 'pref' => 1.000, 'city_other' => 3.000, 'pref_other' => 2.000, 'remark' => null],
            ['category' => '長期譲渡', 'sub' => '一般', 'city' => 4.000, 'pref' => 1.000, 'city_other' => 3.000, 'pref_other' => 2.000, 'remark' => null],
            ['category' => '長期譲渡', 'sub' => '特定', 'city' => 3.200, 'pref' => 0.800, 'city_other' => 2.400, 'pref_other' => 1.600, 'remark' => '2,000万円以下の部分'],
            ['category' => '長期譲渡', 'sub' => '特定', 'city' => 4.000, 'pref' => 1.000, 'city_other' => 3.000, 'pref_other' => 2.000, 'remark' => '2,000万円超の部分'],
            ['category' => '長期譲渡', 'sub' => '軽課', 'city' => 3.200, 'pref' => 0.800, 'city_other' => 2.400, 'pref_other' => 1.600, 'remark' => '6,000万円以下の部分'],
            ['category' => '長期譲渡', 'sub' => '軽課', 'city' => 4.000, 'pref' => 1.000, 'city_other' => 3.000, 'pref_other' => 2.000, 'remark' => '6,000万円超の部分'],
            ['category' => '一般株式等の譲渡', 'sub' => null, 'city' => 4.000, 'pref' => 1.000, 'city_other' => 3.000, 'pref_other' => 2.000, 'remark' => null],
            ['category' => '上場株式等の譲渡', 'sub' => null, 'city' => 4.000, 'pref' => 1.000, 'city_other' => 3.000, 'pref_other' => 2.000, 'remark' => null],
            ['category' => '上場株式等の配当等', 'sub' => null, 'city' => 4.000, 'pref' => 1.000, 'city_other' => 3.000, 'pref_other' => 2.000, 'remark' => null],
            ['category' => '先物取引', 'sub' => null, 'city' => 4.000, 'pref' => 1.000, 'city_other' => 3.000, 'pref_other' => 2.000, 'remark' => null],
            ['category' => '山林', 'sub' => null, 'city' => 8.000, 'pref' => 2.000, 'city_other' => 6.000, 'pref_other' => 4.000, 'remark' => null],
            ['category' => '退職', 'sub' => null, 'city' => 8.000, 'pref' => 2.000, 'city_other' => 6.000, 'pref_other' => 4.000, 'remark' => null],
            ['category' => '調整控除', 'sub' => null, 'city' => 4.000, 'pref' => 1.000, 'city_other' => 3.000, 'pref_other' => 2.000, 'remark' => null],
            ['category' => '基本控除', 'sub' => null, 'city' => 8.000, 'pref' => 2.000, 'city_other' => 6.000, 'pref_other' => 4.000, 'remark' => null],
            ['category' => '特例控除', 'sub' => null, 'city' => 0.800, 'pref' => 0.200, 'city_other' => 0.600, 'pref_other' => 0.400, 'remark' => null],
        ];

        return array_map(function (array $row, int $index) use ($year, $version, $now): array {
            return [
                'company_id' => null,
                'kihu_year' => $year,
                'version' => $version,
                'seq' => $index + 1,
                'category' => $row['category'],
                'sub_category' => $row['sub'],
                'city_specified' => $row['city'],
                'pref_specified' => $row['pref'],
                'city_non_specified' => $row['city_other'],
                'pref_non_specified' => $row['pref_other'],
                'remark' => $row['remark'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $rows, array_keys($rows));
    }

    private function tokureiRates(int $year, int $version, Carbon $now): array
    {
        $rows = [
            ['lower' => 0, 'upper' => 1_950_000, 'income' => 5.000, 'ninety' => 85.000, 'recon' => 5.105, 'deduction' => 84.895, 'note' => null],
            ['lower' => 1_951_000, 'upper' => 3_300_000, 'income' => 10.000, 'ninety' => 80.000, 'recon' => 10.210, 'deduction' => 79.790, 'note' => null],
            ['lower' => 3_301_000, 'upper' => 6_950_000, 'income' => 20.000, 'ninety' => 70.000, 'recon' => 20.420, 'deduction' => 69.580, 'note' => null],
            ['lower' => 6_951_000, 'upper' => 9_000_000, 'income' => 23.000, 'ninety' => 67.000, 'recon' => 23.483, 'deduction' => 66.517, 'note' => null],
            ['lower' => 9_001_000, 'upper' => 18_000_000, 'income' => 33.000, 'ninety' => 57.000, 'recon' => 33.693, 'deduction' => 56.307, 'note' => null],
            ['lower' => 18_001_000, 'upper' => 40_000_000, 'income' => 40.000, 'ninety' => 50.000, 'recon' => 40.840, 'deduction' => 49.160, 'note' => null],
            ['lower' => 40_001_000, 'upper' => null, 'income' => 45.000, 'ninety' => 45.000, 'recon' => 45.945, 'deduction' => 44.055, 'note' => null],
            ['lower' => null, 'upper' => null, 'income' => 0.000, 'ninety' => 90.000, 'recon' => 0.000, 'deduction' => 90.000, 'note' => '課税総所得金額-人的控除差調整額が0未満かつ山林所得及び退職所得が0||所得税額0'],
            ['lower' => null, 'upper' => null, 'income' => 30.000, 'ninety' => 60.000, 'recon' => 30.630, 'deduction' => 59.370, 'note' => '短期譲渡所得を有する||'],
            ['lower' => null, 'upper' => null, 'income' => 15.000, 'ninety' => 75.000, 'recon' => 15.315, 'deduction' => 74.685, 'note' => '長期譲渡所得、株式配当等、株式譲渡等、先物取引を有する||'],
        ];

        return array_map(function (array $row, int $index) use ($year, $version, $now): array {
            return [
                'company_id' => null,
                'kihu_year' => $year,
                'version' => $version,
                'seq' => $index + 1,
                'lower' => $row['lower'],
                'upper' => $row['upper'],
                'income_rate' => $row['income'],
                'ninety_minus_rate' => $row['ninety'],
                'income_rate_with_recon' => $row['recon'],
                'tokurei_deduction_rate' => $row['deduction'],
                'note' => $row['note'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $rows, array_keys($rows));
    }

    private function shinkokutokureiRates(int $year, int $version, Carbon $now): array
    {
        $rows = [
            ['lower' => 0, 'upper' => 1_950_000, 'ratio_a' => 5.105, 'ratio_b' => 84.895],
            ['lower' => 1_951_000, 'upper' => 3_300_000, 'ratio_a' => 10.210, 'ratio_b' => 79.790],
            ['lower' => 3_301_000, 'upper' => 6_950_000, 'ratio_a' => 20.420, 'ratio_b' => 69.580],
            ['lower' => 6_951_000, 'upper' => 9_000_000, 'ratio_a' => 23.483, 'ratio_b' => 66.517],
            ['lower' => 9_001_000, 'upper' => null, 'ratio_a' => 33.693, 'ratio_b' => 56.307],
        ];

        return array_map(function (array $row, int $index) use ($year, $version, $now): array {
            return [
                'company_id' => null,
                'kihu_year' => $year,
                'version' => $version,
                'seq' => $index + 1,
                'lower' => $row['lower'],
                'upper' => $row['upper'],
                'ratio_a' => $row['ratio_a'],
                'ratio_b' => $row['ratio_b'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $rows, array_keys($rows));
    }
}