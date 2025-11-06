<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\JuminzeiKifukinCalculator;
use App\Domain\Tax\Contracts\MasterProviderContract;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class JuminzeiKifukinCalculatorTest extends TestCase
{
    public function test_deductions_use_specified_city_rates(): void
    {
        $calculator = new JuminzeiKifukinCalculator(new class implements MasterProviderContract {
            public function getShotokuRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }

            public function getJuminRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make([
                    (object) [
                        'category' => '基本控除',
                        'sub_category' => null,
                        'remark' => null,
                        'pref_specified' => 0.04,
                        'pref_non_specified' => 0.035,
                        'city_specified' => 0.06,
                        'city_non_specified' => 0.055,
                    ],
                    (object) [
                        'category' => '特例控除',
                        'sub_category' => null,
                        'remark' => null,
                        'pref_specified' => 0.5,
                        'pref_non_specified' => 0.4,
                        'city_specified' => 0.6,
                        'city_non_specified' => 0.45,
                    ],
                ]);
            }

            public function getTokureiRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }

            public function getShinkokutokureiRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }
        });

        $payload = [
            'tb_sogo_jumin_prev' => 300_000,
            'bunri_shotoku_sanrin_shotoku_prev' => 0,
            'bunri_shotoku_taishoku_shotoku_prev' => 0,
            'kojo_gokei_jumin_prev' => 0,
            'juminzei_zeigakukojo_pref_furusato_prev' => 40_000,
            'juminzei_zeigakukojo_muni_furusato_prev' => 60_000,
            'tokurei_rate_final_prev' => 69.58,
        ];

        $ctx = [
            'syori_settings' => [
                'shitei_toshi_flag_prev' => 1,
            ],
            'master_kihu_year' => 2025,
        ];

        $result = $calculator->compute($payload, $ctx);

        $this->assertSame(100_000, $result['kifu_gaku_prev']);
        $this->assertSame(3_520, $result['kihon_kojo_pref_prev']);
        $this->assertSame(5_280, $result['kihon_kojo_muni_prev']);
        $this->assertSame(34_094, $result['tokurei_kojo_pref_prev']);
        $this->assertSame(40_912, $result['tokurei_kojo_muni_prev']);
    }

    public function test_deductions_are_zero_when_below_threshold(): void
    {
        $calculator = new JuminzeiKifukinCalculator(new class implements MasterProviderContract {
            public function getShotokuRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }

            public function getJuminRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make([
                    (object) [
                        'category' => '基本控除',
                        'sub_category' => null,
                        'remark' => null,
                        'pref_specified' => 0.04,
                        'pref_non_specified' => 0.035,
                        'city_specified' => 0.06,
                        'city_non_specified' => 0.055,
                    ],
                    (object) [
                        'category' => '特例控除',
                        'sub_category' => null,
                        'remark' => null,
                        'pref_specified' => 0.5,
                        'pref_non_specified' => 0.4,
                        'city_specified' => 0.6,
                        'city_non_specified' => 0.45,
                    ],
                ]);
            }

            public function getTokureiRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }

            public function getShinkokutokureiRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }
        });

        $payload = [
            'tb_sogo_jumin_curr' => 400_000,
            'bunri_shotoku_sanrin_shotoku_curr' => 0,
            'bunri_shotoku_taishoku_shotoku_curr' => 0,
            'kojo_gokei_jumin_curr' => 0,
            'juminzei_zeigakukojo_pref_furusato_curr' => 1_200,
            'juminzei_zeigakukojo_muni_furusato_curr' => 800,
            'tokurei_rate_final_curr' => 50.0,
        ];

        $ctx = [
            'syori_settings' => [
                'shitei_toshi_flag_curr' => 0,
            ],
            'master_kihu_year' => 2025,
        ];

        $result = $calculator->compute($payload, $ctx);

        $this->assertSame(2_000, $result['kifu_gaku_curr']);
        $this->assertSame(0, $result['kihon_kojo_pref_curr']);
        $this->assertSame(0, $result['kihon_kojo_muni_curr']);
        $this->assertSame(0, $result['tokurei_kojo_pref_curr']);
        $this->assertSame(0, $result['tokurei_kojo_muni_curr']);
    }


    public function test_shinkokutokurei_is_zero_when_one_stop_disabled(): void
    {
        $calculator = new JuminzeiKifukinCalculator(new class implements MasterProviderContract {
            public function getShotokuRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }

            public function getJuminRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make([
                    (object) [
                        'category' => '基本控除',
                        'sub_category' => null,
                        'remark' => null,
                        'pref_specified' => 0.04,
                        'pref_non_specified' => 0.035,
                        'city_specified' => 0.06,
                        'city_non_specified' => 0.055,
                    ],
                    (object) [
                        'category' => '特例控除',
                        'sub_category' => null,
                        'remark' => null,
                        'pref_specified' => 0.5,
                        'pref_non_specified' => 0.4,
                        'city_specified' => 0.6,
                        'city_non_specified' => 0.45,
                    ],
                ]);
            }

            public function getTokureiRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }

            public function getShinkokutokureiRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make([
                    (object) [
                        'lower' => 0,
                        'upper' => null,
                        'ratio_a' => 0.8,
                        'ratio_b' => 0.2,
                    ],
                ]);
            }
        });

        $payload = [
            'tb_sogo_jumin_prev' => 30_000_000,
            'kojo_gokei_jumin_prev' => 0,
            'juminzei_zeigakukojo_furusato_prev' => 100_000,
            'tokurei_rate_final_prev' => 80.0,
        ];

        $ctx = [
            'syori_settings' => [
                'pref_applied_rate_prev' => 0.01,
                'muni_applied_rate_prev' => 0.015,
                'shitei_toshi_flag_prev' => 0,
                'one_stop_flag_prev' => 0,
            ],
            'master_kihu_year' => 2025,
        ];

        $result = $calculator->compute($payload, $ctx);

        $this->assertSame(0, $result['shinkokutokurei_kojo_pref_prev']);
        $this->assertSame(0, $result['shinkokutokurei_kojo_muni_prev']);
        $this->assertSame(31_360, $result['tokurei_kojo_jogen_pref_prev']);
        $this->assertSame(35_280, $result['tokurei_kojo_jogen_muni_prev']);
        $this->assertSame(34_790, $result['kifukin_zeigaku_kojo_pref_prev']);
        $this->assertSame(40_670, $result['kifukin_zeigaku_kojo_muni_prev']);
        $this->assertSame(75_460, $result['kifukin_zeigaku_kojo_gokei_prev']);
    }
}