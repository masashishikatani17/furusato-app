<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\SogoShotokuNettingStagesCalculator;
use PHPUnit\Framework\TestCase;

class SogoShotokuNettingStagesCalculatorTest extends TestCase
{
    public function test_compute_prev_period_values_follow_formulas(): void
    {
        $calculator = new SogoShotokuNettingStagesCalculator();

        $payload = [
            'shotoku_jigyo_eigyo_shotoku_prev' => 100,
            'shotoku_jigyo_nogyo_shotoku_prev' => -50,
            'shotoku_fudosan_shotoku_prev' => 200,
            'shotoku_haito_shotoku_prev' => -30,
            'shotoku_kyuyo_shotoku_prev' => 70,
            'shotoku_zatsu_nenkin_shotoku_prev' => 40,
            'shotoku_zatsu_gyomu_shotoku_prev' => -10,
            'shotoku_zatsu_sonota_shotoku_prev' => 20,
            'after_joto_ichiji_tousan_joto_tanki_prev' => -80,
            'after_joto_ichiji_tousan_joto_choki_sogo_prev' => -60,
            'after_joto_ichiji_tousan_ichiji_prev' => 100,
            'bunri_shotoku_sanrin_shotoku_prev' => -30,
            'bunri_shotoku_taishoku_shotoku_prev' => 50,
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame(380, $result['tsusanmae_keijo_prev']);
        $this->assertSame(-80, $result['tsusanmae_joto_tanki_sogo_prev']);
        $this->assertSame(-60, $result['tsusanmae_joto_choki_sogo_prev']);
        $this->assertSame(100, $result['tsusanmae_ichiji_prev']);

        $this->assertSame(240, $result['after_1jitsusan_keijo_prev']);
        $this->assertSame(0, $result['after_1jitsusan_joto_tanki_sogo_prev']);
        $this->assertSame(0, $result['after_1jitsusan_joto_choki_sogo_prev']);
        $this->assertSame(100, $result['after_1jitsusan_ichiji_prev']);
        $this->assertSame(-30, $result['after_1jitsusan_sanrin_prev']);

        $this->assertSame(210, $result['after_2jitsusan_keijo_prev']);
        $this->assertSame(0, $result['after_2jitsusan_joto_tanki_sogo_prev']);
        $this->assertSame(0, $result['after_2jitsusan_joto_choki_sogo_prev']);
        $this->assertSame(100, $result['after_2jitsusan_ichiji_prev']);
        $this->assertSame(0, $result['after_2jitsusan_sanrin_prev']);
        $this->assertSame(50, $result['after_2jitsusan_taishoku_prev']);

        $this->assertSame(210, $result['after_3jitsusan_keijo_prev']);
        $this->assertSame(0, $result['after_3jitsusan_joto_tanki_sogo_prev']);
        $this->assertSame(0, $result['after_3jitsusan_joto_choki_sogo_prev']);
        $this->assertSame(100, $result['after_3jitsusan_ichiji_prev']);
        $this->assertSame(0, $result['after_3jitsusan_sanrin_prev']);
        $this->assertSame(50, $result['after_3jitsusan_taishoku_prev']);

        $this->assertSame(210, $result['shotoku_keijo_prev']);
        $this->assertSame(0, $result['shotoku_joto_tanki_prev']);
        $this->assertSame(0, $result['shotoku_joto_choki_sogo_prev']);
        $this->assertSame(50, $result['shotoku_ichiji_prev']);
        $this->assertSame(0, $result['shotoku_sanrin_prev']);
        $this->assertSame(50, $result['shotoku_taishoku_prev']);
        $this->assertSame(310, $result['shotoku_gokei_prev']);
    }

    public function test_compute_curr_period_with_positive_forest_and_alias(): void
    {
        $calculator = new SogoShotokuNettingStagesCalculator();

        $payload = [
            'shotoku_jigyo_eigyo_shotoku_curr' => -200,
            'shotoku_jigyo_nogyo_shotoku_curr' => 0,
            'shotoku_fudosan_shotoku_curr' => 0,
            'shotoku_haito_shotoku_curr' => 50,
            'shotoku_kyuyo_shotoku_curr' => -40,
            'shotoku_zatsu_nankin_shotoku_curr' => -10,
            'shotoku_zatsu_gyomu_shotoku_curr' => 30,
            'shotoku_zatsu_sonota_shotoku_curr' => -20,
            'after_joto_ichiji_tousan_joto_tanki_curr' => 40,
            'after_joto_ichiji_tousan_joto_choki_sogo_curr' => -100,
            'after_joto_ichiji_tousan_ichiji_curr' => -60,
            'bunri_shotoku_sanrin_shotoku_curr' => 80,
            'bunri_shotoku_taishoku_shotoku_curr' => -150,
        ];

        $result = $calculator->compute($payload, 'curr');

        $this->assertSame(-120, $result['tsusanmae_keijo_curr']);
        $this->assertSame(40, $result['tsusanmae_joto_tanki_sogo_curr']);
        $this->assertSame(-100, $result['tsusanmae_joto_choki_sogo_curr']);
        $this->assertSame(-60, $result['tsusanmae_ichiji_curr']);

        $this->assertSame(-80, $result['after_1jitsusan_keijo_curr']);
        $this->assertSame(0, $result['after_1jitsusan_joto_tanki_sogo_curr']);
        $this->assertSame(-100, $result['after_1jitsusan_joto_choki_sogo_curr']);
        $this->assertSame(0, $result['after_1jitsusan_ichiji_curr']);
        $this->assertSame(80, $result['after_1jitsusan_sanrin_curr']);

        $this->assertSame(-80, $result['after_2jitsusan_keijo_curr']);
        $this->assertSame(0, $result['after_2jitsusan_joto_tanki_sogo_curr']);
        $this->assertSame(-20, $result['after_2jitsusan_joto_choki_sogo_curr']);
        $this->assertSame(0, $result['after_2jitsusan_ichiji_curr']);
        $this->assertSame(0, $result['after_2jitsusan_sanrin_curr']);
        $this->assertSame(0, $result['after_2jitsusan_taishoku_curr']);

        $this->assertSame(-80, $result['after_3jitsusan_keijo_curr']);
        $this->assertSame(0, $result['after_3jitsusan_joto_tanki_sogo_curr']);
        $this->assertSame(-20, $result['after_3jitsusan_joto_choki_sogo_curr']);
        $this->assertSame(0, $result['after_3jitsusan_ichiji_curr']);
        $this->assertSame(0, $result['after_3jitsusan_sanrin_curr']);
        $this->assertSame(0, $result['after_3jitsusan_taishoku_curr']);

        $this->assertSame(-80, $result['shotoku_keijo_curr']);
        $this->assertSame(0, $result['shotoku_joto_tanki_curr']);
        $this->assertSame(-10, $result['shotoku_joto_choki_sogo_curr']);
        $this->assertSame(0, $result['shotoku_ichiji_curr']);
        $this->assertSame(0, $result['shotoku_sanrin_curr']);
        $this->assertSame(0, $result['shotoku_taishoku_curr']);
        $this->assertSame(-90, $result['shotoku_gokei_curr']);
    }
}