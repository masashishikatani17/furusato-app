<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\TokureiRateCalculator;
use App\Domain\Tax\Contracts\MasterProviderContract;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class TokureiRateCalculatorTest extends TestCase
{
    public function test_uses_standard_rate_when_draw_is_non_negative(): void
    {
        $calculator = new TokureiRateCalculator($this->provider());

        $result = $calculator->compute([
            'sum_for_sogoshotoku_prev' => 2_000_000,
            'kojo_gokei_jumin_prev' => 0,
            'human_diff_sum_prev' => 0,
            'tokurei_table_base_jumin_prev' => 1_000_000,
        ], [
            'master_kihu_year' => 2025,
        ]);

        $this->assertSame(0.8, $result['tokurei_rate_standard_prev']);
        $this->assertSame(0.8, $result['tokurei_rate_final_prev']);
    }

    public function test_uses_90_percent_when_draw_is_negative_and_no_separated_income(): void
    {
        $calculator = new TokureiRateCalculator($this->provider());

        $result = $calculator->compute([
            'sum_for_sogoshotoku_prev' => 500_000,
            'kojo_gokei_jumin_prev' => 0,
            'human_diff_sum_prev' => 1_000_000,
            'tb_taishoku_shotoku_prev' => 0,
            'tb_taishoku_jumin_prev' => 0,
        ], [
            'master_kihu_year' => 2025,
        ]);

        $this->assertSame(90.0, $result['tokurei_rate_90_prev']);
        $this->assertSame(0.9, $result['tokurei_rate_final_prev']);
    }

    public function test_taishoku_judgement_uses_shotoku_side_even_if_jumin_side_is_zero(): void
    {
        $calculator = new TokureiRateCalculator($this->provider());

        $result = $calculator->compute([
            'sum_for_sogoshotoku_prev' => 500_000,
            'kojo_gokei_jumin_prev' => 0,
            'human_diff_sum_prev' => 1_000_000,
            'tb_taishoku_shotoku_prev' => 2_000_000,
            'tb_taishoku_jumin_prev' => 0,
        ], [
            'master_kihu_year' => 2025,
        ]);

        $this->assertSame(0.8, $result['tokurei_rate_taishoku_prev']);
        $this->assertSame(0.8, $result['tokurei_rate_final_prev']);
    }

    private function provider(): MasterProviderContract
    {
        return new class implements MasterProviderContract {
            public function getShotokuRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }

            public function getJuminRates(int $year, ?int $companyId = null, ?int $dataId = null): Collection
            {
                return Collection::make();
            }

            public function getTokureiRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make([
                    (object) ['lower' => 0, 'upper' => 999999, 'tokurei_deduction_rate' => 0.9],
                    (object) ['lower' => 1000000, 'upper' => null, 'tokurei_deduction_rate' => 0.8],
                ]);
            }

            public function getShinkokutokureiRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make();
            }
        };
    }
}