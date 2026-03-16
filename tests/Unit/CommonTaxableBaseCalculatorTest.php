<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\CommonTaxableBaseCalculator;
use App\Domain\Tax\Calculators\JuminTaxCalculator;
use App\Domain\Tax\Calculators\ShotokuTaxCalculator;
use App\Domain\Tax\Contracts\MasterProviderContract;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class CommonTaxableBaseCalculatorTest extends TestCase
{
    public function test_jumin_taishoku_is_fixed_to_zero_without_fallback_from_shotoku(): void
    {
        $calculator = new CommonTaxableBaseCalculator();

        $result = $calculator->compute([
            'sum_for_sogoshotoku_prev' => 1_000_000,
            'kojo_gokei_shotoku_prev' => 0,
            'kojo_gokei_jumin_prev' => 0,
            'shotoku_taishoku_prev' => 1_234_567,
            'bunri_shotoku_taishoku_jumin_prev' => 2_345_678,
            'shotoku_taishoku_jumin_prev' => 9_999_999,
        ], []);

        $this->assertSame(1_234_000, $result['tb_taishoku_shotoku_prev']);
        $this->assertSame(0, $result['tb_taishoku_jumin_prev']);
    }

    public function test_jumin_tax_and_capbase_are_not_affected_by_jumin_taishoku_input(): void
    {
        $provider = $this->provider();
        $baseCalculator = new CommonTaxableBaseCalculator();
        $juminCalculator = new JuminTaxCalculator($provider);

        $basePayload = [
            'sum_for_sogoshotoku_prev' => 1_000_000,
            'kojo_gokei_shotoku_prev' => 0,
            'kojo_gokei_jumin_prev' => 0,
            'shotoku_taishoku_prev' => 1_200_000,
            'sum_for_gokeishotoku_prev' => 10_000_000,
            'human_diff_sum_prev' => 0,
        ];

        $low = $baseCalculator->compute(array_replace($basePayload, [
            'bunri_shotoku_taishoku_jumin_prev' => 500_000,
        ]), []);
        $high = $baseCalculator->compute(array_replace($basePayload, [
            'bunri_shotoku_taishoku_jumin_prev' => 1_500_000,
        ]), []);

        $ctx = [
            'syori_settings' => [
                'shitei_toshi_flag_prev' => 0,
                'bunri_flag_prev' => 1,
            ],
            'master_kihu_year' => 2025,
        ];

        $lowResult = $juminCalculator->compute($low, $ctx);
        $highResult = $juminCalculator->compute($high, $ctx);

        $this->assertSame(0, $low['tb_taishoku_jumin_prev']);
        $this->assertSame(0, $high['tb_taishoku_jumin_prev']);

        $this->assertSame(
            $lowResult['choseigo_shotokuwari_capbase_pref_prev'],
            $highResult['choseigo_shotokuwari_capbase_pref_prev']
        );
        $this->assertSame(
            $lowResult['bunri_zeigaku_taishoku_jumin_prev'],
            $highResult['bunri_zeigaku_taishoku_jumin_prev']
        );
    }

    public function test_shotoku_side_remains_unchanged_when_only_jumin_taishoku_changes(): void
    {
        $provider = $this->provider();
        $baseCalculator = new CommonTaxableBaseCalculator();
        $shotokuCalculator = new ShotokuTaxCalculator($provider);

        $basePayload = [
            'sum_for_sogoshotoku_prev' => 1_000_000,
            'kojo_gokei_shotoku_prev' => 0,
            'kojo_gokei_jumin_prev' => 0,
            'shotoku_taishoku_prev' => 1_200_000,
            'tb_sogo_shotoku_prev' => 0,
        ];

        $low = $baseCalculator->compute(array_replace($basePayload, [
            'bunri_shotoku_taishoku_jumin_prev' => 500_000,
        ]), []);
        $high = $baseCalculator->compute(array_replace($basePayload, [
            'bunri_shotoku_taishoku_jumin_prev' => 1_500_000,
        ]), []);

        $ctx = [
            'master_kihu_year' => 2025,
            'syori_settings' => ['bunri_flag_prev' => 1],
        ];

        $lowResult = $shotokuCalculator->compute($low, $ctx);
        $highResult = $shotokuCalculator->compute($high, $ctx);

        $this->assertSame($lowResult['tb_taishoku_shotoku_prev'], $highResult['tb_taishoku_shotoku_prev']);
        $this->assertSame($lowResult['bunri_zeigaku_taishoku_shotoku_prev'], $highResult['bunri_zeigaku_taishoku_shotoku_prev']);
    }

    private function provider(): MasterProviderContract
    {
        return new class implements MasterProviderContract {
            public function getShotokuRates(int $year, ?int $companyId = null): Collection
            {
                return Collection::make([
                    (object) ['lower' => 0, 'upper' => null, 'rate' => 10.0, 'deduction_amount' => 0],
                ]);
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
        };
    }
}
