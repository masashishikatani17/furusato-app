<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\BunriNettingCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingStagesCalculator;
use PHPUnit\Framework\TestCase;

class SogoShotokuNettingStagesCalculatorTest extends TestCase
{
    private function assertNotBothPositive(int $a, int $b, string $label): void
    {
        $this->assertFalse(($a > 0 && $b > 0), $label);
    }
    public function test_compute_prev_period_values_follow_formulas(): void
    {
        $calculator = new SogoShotokuNettingStagesCalculator();

        $payload = [
            'jigyo_eigyo_shotoku_prev' => 100,
            'shotoku_jigyo_nogyo_shotoku_prev' => -50,
            'fudosan_shotoku_prev' => 200,
            'shotoku_rishi_shotoku_prev' => -10,
            'shotoku_haito_shotoku_prev' => -30,
            'shotoku_kyuyo_shotoku_prev' => 70,
            'shotoku_zatsu_nenkin_shotoku_prev' => 40,
            'shotoku_zatsu_gyomu_shotoku_prev' => -10,
            'shotoku_zatsu_sonota_shotoku_prev' => 20,
            'sashihiki_joto_tanki_sogo_prev' => -80,
            'sashihiki_joto_choki_sogo_prev' => -60,
            'sashihiki_ichiji_prev' => 100,
            'bunri_shotoku_sanrin_shotoku_prev' => -30,
            'bunri_shotoku_taishoku_shotoku_prev' => 50,
            'after_joto_ichiji_tousan_joto_tanki_sogo_prev' => -70,
            'after_joto_ichiji_tousan_joto_choki_sogo_prev' => -55,
            'after_joto_ichiji_tousan_ichiji_prev' => 90,
        ];

        $result = $calculator->compute($payload, 'prev');

        // 具体値には依存せず、表4（通算前）について：
        $short = (int)($result['tsusanmae_joto_tanki_sogo_prev'] ?? 0);
        $long  = (int)($result['tsusanmae_joto_choki_sogo_prev'] ?? 0);
        $ichiji = (int)($result['tsusanmae_ichiji_prev'] ?? 0);
        $this->assertNotBothPositive($short, $long, 'Short and Long cannot both be positive at tsusanmae(prev)');
        $this->assertGreaterThanOrEqual(0, $ichiji, 'Ichiji must be non-negative at tsusanmae(prev)');
    }

    public function test_compute_curr_period_with_positive_forest_and_alias(): void
    {
        $calculator = new SogoShotokuNettingStagesCalculator();

        $payload = [
            'jigyo_eigyo_shotoku_curr' => -200,
            'shotoku_jigyo_nogyo_shotoku_curr' => 0,
            'fudosan_shotoku_curr' => 0,
            'shotoku_rishi_shotoku_curr' => -10,
            'shotoku_haito_shotoku_curr' => 50,
            'shotoku_kyuyo_shotoku_curr' => -40,
            'shotoku_zatsu_nenkin_shotoku_curr' => -10,
            'shotoku_zatsu_gyomu_shotoku_curr' => 30,
            'shotoku_zatsu_sonota_shotoku_curr' => -20,
            'sashihiki_joto_tanki_sogo_curr' => 40,
            'sashihiki_joto_choki_sogo_curr' => -100,
            'sashihiki_ichiji_curr' => -60,
            'bunri_shotoku_sanrin_shotoku_curr' => 80,
            'bunri_shotoku_taishoku_shotoku_curr' => -150,
            'after_joto_ichiji_tousan_joto_tanki_sogo_curr' => 25,
            'after_joto_ichiji_tousan_joto_choki_sogo_curr' => -90,
            'after_joto_ichiji_tousan_ichiji_curr' => 15,
        ];

        $result = $calculator->compute($payload, 'curr');

        // 具体値には依存せず、表4（通算前）について：
        $short = (int)($result['tsusanmae_joto_tanki_sogo_curr'] ?? 0);
        $long  = (int)($result['tsusanmae_joto_choki_sogo_curr'] ?? 0);
        $ichiji = (int)($result['tsusanmae_ichiji_curr'] ?? 0);
        $this->assertNotBothPositive($short, $long, 'Short and Long cannot both be positive at tsusanmae(curr)');
        $this->assertGreaterThanOrEqual(0, $ichiji, 'Ichiji must be non-negative at tsusanmae(curr)');
    }

    public function test_bunri_netting_block_matches_let_formulas_for_both_periods(): void
    {
        $stageCalculator = new SogoShotokuNettingStagesCalculator();
        $bunriCalculator = new BunriNettingCalculator();

        $shortCases = [
            [100, -60],
            [-80, 50],
            [0, -10],
            [200, 0],
            [-150, -200],
        ];

        $longCases = [
            [100, -70, 0],
            [-50, 120, -40],
            [0, 0, 0],
            [-1, -2, 300],
            [500, 0, 0],
        ];

        foreach ($shortCases as $short) {
            foreach ($longCases as $long) {
                [$shortGeneral, $shortReduced] = $short;
                [$longGeneral, $longReduced, $longLight] = $long;

                $this->assertSeparatedMatchesLetFormula(
                    $stageCalculator,
                    $bunriCalculator,
                    'prev',
                    $shortGeneral,
                    $shortReduced,
                    $longGeneral,
                    $longReduced,
                    $longLight,
                );

                $this->assertSeparatedMatchesLetFormula(
                    $stageCalculator,
                    $bunriCalculator,
                    'curr',
                    $shortGeneral,
                    $shortReduced,
                    $longGeneral,
                    $longReduced,
                    $longLight,
                );
            }
        }
    }

    private function assertSeparatedMatchesLetFormula(
        SogoShotokuNettingStagesCalculator $stageCalculator,
        BunriNettingCalculator $bunriCalculator,
        string $period,
        int $shortGeneral,
        int $shortReduced,
        int $longGeneral,
        int $longReduced,
        int $longLight,
    ): void {
        // Stage 側には実装が参照するキー名で渡す:
        //  - 短期: bunri_shotoku_tanki_{ippan|keigen}_**shotoku**_<period>
        //  - 長期: bunri_shotoku_choki_{ippan|tokutei|keika}_**shotoku**_<period>
        $stagePayload = [
            sprintf('bunri_shotoku_tanki_ippan_shotoku_%s',   $period)    => $shortGeneral,
            sprintf('bunri_shotoku_tanki_keigen_shotoku_%s',  $period)    => $shortReduced,
            sprintf('bunri_shotoku_choki_ippan_shotoku_%s',   $period)    => $longGeneral,
            sprintf('bunri_shotoku_choki_tokutei_shotoku_%s', $period)    => $longReduced,
            sprintf('bunri_shotoku_choki_keika_shotoku_%s',   $period)    => $longLight,
        ];

        // Bunri 側の期待値は「before = syunyu - keihi」で同じ値になるように組成
        $toPair = static function (int $v): array {
            return $v >= 0 ? [$v, 0] : [0, -$v];
        };
        [$sgY, $sgC] = $toPair($shortGeneral);
        [$srY, $srC] = $toPair($shortReduced);
        [$lgY, $lgC] = $toPair($longGeneral);
        [$ltY, $ltC] = $toPair($longReduced);
        [$lkY, $lkC] = $toPair($longLight);
        $bunriPayload = [
            sprintf('syunyu_tanki_ippan_%s',   $period) => $sgY, sprintf('keihi_tanki_ippan_%s',   $period) => $sgC,
            sprintf('syunyu_tanki_keigen_%s',  $period) => $srY, sprintf('keihi_tanki_keigen_%s',  $period) => $srC,
            sprintf('syunyu_choki_ippan_%s',   $period) => $lgY, sprintf('keihi_choki_ippan_%s',   $period) => $lgC,
            sprintf('syunyu_choki_tokutei_%s', $period) => $ltY, sprintf('keihi_choki_tokutei_%s', $period) => $ltC,
            sprintf('syunyu_choki_keika_%s',   $period) => $lkY, sprintf('keihi_choki_keika_%s',   $period) => $lkC,
        ];

        $expected = $bunriCalculator->compute($bunriPayload, $period);
        $actual   = $stageCalculator->compute($stagePayload, $period);
 

        $mapping = [
            // before
            'before_tsusan_tanki_ippan_%s'      => 'before_tsusan_tanki_ippan_%s',
            'before_tsusan_tanki_keigen_%s'     => 'before_tsusan_tanki_keigen_%s',
            'before_tsusan_choki_ippan_%s'      => 'before_tsusan_choki_ippan_%s',
            'before_tsusan_choki_tokutei_%s'    => 'before_tsusan_choki_tokutei_%s',
            'before_tsusan_choki_keika_%s'      => 'before_tsusan_choki_keika_%s',
            // after_1
            'after_1jitsusan_tanki_ippan_%s'    => 'after_1jitsusan_tanki_ippan_%s',
            'after_1jitsusan_tanki_keigen_%s'   => 'after_1jitsusan_tanki_keigen_%s',
            'after_1jitsusan_choki_ippan_%s'    => 'after_1jitsusan_choki_ippan_%s',
            'after_1jitsusan_choki_tokutei_%s'  => 'after_1jitsusan_choki_tokutei_%s',
            'after_1jitsusan_choki_keika_%s'    => 'after_1jitsusan_choki_keika_%s',
            // after_2
            'after_2jitsusan_tanki_ippan_%s'    => 'after_2jitsusan_tanki_ippan_%s',
            'after_2jitsusan_tanki_keigen_%s'   => 'after_2jitsusan_tanki_keigen_%s',
            'after_2jitsusan_choki_ippan_%s'    => 'after_2jitsusan_choki_ippan_%s',
            'after_2jitsusan_choki_tokutei_%s'  => 'after_2jitsusan_choki_tokutei_%s',
            'after_2jitsusan_choki_keika_%s'    => 'after_2jitsusan_choki_keika_%s',
        ];

        foreach ($mapping as $stagePattern => $bunriPattern) {
            $stageKey = sprintf($stagePattern, $period);
            $bunriKey = sprintf($bunriPattern, $period);

            $this->assertArrayHasKey($bunriKey, $expected);
            $this->assertArrayHasKey($stageKey, $actual);
            $this->assertSame($expected[$bunriKey], $actual[$stageKey]);
        }
    }
}