<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\JuminTaxCalculator;
use App\Domain\Tax\Contracts\MasterProviderContract;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class JuminTaxCalculatorTest extends TestCase
{
    public function test_capbase_excludes_retirement_tax_amounts_only(): void
    {
        $calculator = new JuminTaxCalculator(new class implements MasterProviderContract {
            public function getShotokuRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }

            public function getJuminRates(int $year, ?int $companyId = null, ?int $dataId = null): Collection
            {
                return Collection::make([
                    (object) [
                        'category' => '総合課税',
                        'sub_category' => null,
                        'remark' => null,
                        'pref_specified' => 4.0,
                        'pref_non_specified' => 4.0,
                        'city_specified' => 6.0,
                        'city_non_specified' => 6.0,
                    ],
                    (object) [
                        'category' => '退職',
                        'sub_category' => null,
                        'remark' => null,
                        'pref_specified' => 4.0,
                        'pref_non_specified' => 4.0,
                        'city_specified' => 6.0,
                        'city_non_specified' => 6.0,
                    ],
                ]);
            }

            public function getTokureiRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }

            public function getShinkokutokureiRates(int $year, ?int $companyId = null, ?int $dataId = null): Collection
            {
                return Collection::make();
            }
        });

        $payload = [
            'tb_sogo_jumin_prev' => 1_000_000,
            'tb_taishoku_jumin_prev' => 500_000,
            'sum_for_gokeishotoku_prev' => 30_000_000,
            'human_diff_sum_prev' => 0,
        ];

        $ctx = [
            'syori_settings' => ['shitei_toshi_flag_prev' => 0],
            'master_kihu_year' => 2025,
        ];

        $result = $calculator->compute($payload, $ctx);

        // 調整控除後（今回の修正対象外）は従来どおり
        $this->assertSame(60_000, $result['choseigo_shotokuwari_pref_prev']);
        $this->assertSame(90_000, $result['choseigo_shotokuwari_muni_prev']);

        // capbase のみ退職分（pref:20,000 / muni:30,000）を除外
        $this->assertSame(40_000, $result['choseigo_shotokuwari_capbase_pref_prev']);
        $this->assertSame(60_000, $result['choseigo_shotokuwari_capbase_muni_prev']);
    }

    public function test_capbase_equals_after_when_no_retirement_taxable_exists(): void
    {
        $calculator = new JuminTaxCalculator(new class implements MasterProviderContract {
            public function getShotokuRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }

            public function getJuminRates(int $year, ?int $companyId = null, ?int $dataId = null): Collection
            {
                return Collection::make([
                    (object) [
                        'category' => '総合課税',
                        'sub_category' => null,
                        'remark' => null,
                        'pref_specified' => 4.0,
                        'pref_non_specified' => 4.0,
                        'city_specified' => 6.0,
                        'city_non_specified' => 6.0,
                    ],
                    (object) [
                        'category' => '退職',
                        'sub_category' => null,
                        'remark' => null,
                        'pref_specified' => 4.0,
                        'pref_non_specified' => 4.0,
                        'city_specified' => 6.0,
                        'city_non_specified' => 6.0,
                    ],
                ]);
            }

            public function getTokureiRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }

            public function getShinkokutokureiRates(int $year, ?int $companyId = null, ?int $dataId = null): Collection
            {
                return Collection::make();
            }
        });

        $payload = [
            'tb_sogo_jumin_prev' => 1_000_000,
            'tb_taishoku_jumin_prev' => 0,
            'sum_for_gokeishotoku_prev' => 30_000_000,
            'human_diff_sum_prev' => 0,
        ];

        $ctx = [
            'syori_settings' => ['shitei_toshi_flag_prev' => 0],
            'master_kihu_year' => 2025,
        ];

        $result = $calculator->compute($payload, $ctx);

        $this->assertSame($result['choseigo_shotokuwari_pref_prev'], $result['choseigo_shotokuwari_capbase_pref_prev']);
        $this->assertSame($result['choseigo_shotokuwari_muni_prev'], $result['choseigo_shotokuwari_capbase_muni_prev']);
    }
}
