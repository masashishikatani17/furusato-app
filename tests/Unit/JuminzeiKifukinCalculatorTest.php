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
                        'pref_specified' => 4.0,
                        'pref_non_specified' => 3.5,
                        'city_specified' => 6.0,
                        'city_non_specified' => 5.5,
                    ],
                    (object) [
                        'category' => '特例控除',
                        'sub_category' => null,
                        'remark' => null,
                        'pref_specified' => 50.0,
                        'pref_non_specified' => 40.0,
                        'city_specified' => 60.0,
                        'city_non_specified' => 45.0,
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
            'shotoku_gokei_shotoku_prev' => 300_000,
            'bunri_shotoku_sanrin_shotoku_prev' => 0,
            'bunri_shotoku_taishoku_shotoku_prev' => 0,
            'kojo_gokei_jumin_prev' => 0,
            'juminzei_zeigakukojo_furusato_prev' => 100_000,
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
                        'pref_specified' => 4.0,
                        'pref_non_specified' => 3.5,
                        'city_specified' => 6.0,
                        'city_non_specified' => 5.5,
                    ],
                    (object) [
                        'category' => '特例控除',
                        'sub_category' => null,
                        'remark' => null,
                        'pref_specified' => 50.0,
                        'pref_non_specified' => 40.0,
                        'city_specified' => 60.0,
                        'city_non_specified' => 45.0,
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
            'shotoku_gokei_shotoku_curr' => 400_000,
            'bunri_shotoku_sanrin_shotoku_curr' => 0,
            'bunri_shotoku_taishoku_shotoku_curr' => 0,
            'kojo_gokei_jumin_curr' => 0,
            'juminzei_zeigakukojo_furusato_curr' => 2_000,
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
}