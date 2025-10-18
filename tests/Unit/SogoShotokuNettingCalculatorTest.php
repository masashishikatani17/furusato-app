<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\SogoShotokuNettingCalculator;
use PHPUnit\Framework\TestCase;

class SogoShotokuNettingCalculatorTest extends TestCase
{
    public function test_positive_values_remain_after_special_deduction(): void
    {
        $calculator = new SogoShotokuNettingCalculator();

        $payload = [
            'sashihiki_joto_tanki_sogo_prev' => 300_000,
            'sashihiki_joto_choki_sogo_prev' => 100_000,
            'sashihiki_ichiji_prev' => 20_000,
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame(300_000, $result['tsusango_joto_tanki_prev']);
        $this->assertSame(100_000, $result['tsusango_joto_choki_sogo_prev']);
        $this->assertSame(20_000, $result['tsusango_ichiji_prev']);
        $this->assertSame(300_000, $result['tokubetsukojo_joto_tanki_prev']);
        $this->assertSame(100_000, $result['tokubetsukojo_joto_choki_prev']);
        $this->assertSame(20_000, $result['tokubetsukojo_ichiji_prev']);
        $this->assertSame(0, $result['after_joto_ichiji_tousan_joto_tanki_prev']);
        $this->assertSame(0, $result['after_joto_ichiji_tousan_joto_choki_sogo_prev']);
        $this->assertSame(0, $result['after_joto_ichiji_tousan_ichiji_prev']);
    }

    public function test_shortfall_uses_ichiji_pool_after_special_deduction(): void
    {
        $calculator = new SogoShotokuNettingCalculator();

        $payload = [
            'sashihiki_joto_tanki_sogo_curr' => -200_000,
            'sashihiki_joto_choki_sogo_curr' => 50_000,
            'sashihiki_ichiji_curr' => 600_000,
        ];

        $result = $calculator->compute($payload, 'curr');

        $this->assertSame(-150_000, $result['tsusango_joto_tanki_curr']);
        $this->assertSame(0, $result['tsusango_joto_choki_sogo_curr']);
        $this->assertSame(600_000, $result['tsusango_ichiji_curr']);
        $this->assertSame(0, $result['tokubetsukojo_joto_tanki_curr']);
        $this->assertSame(0, $result['tokubetsukojo_joto_choki_curr']);
        $this->assertSame(500_000, $result['tokubetsukojo_ichiji_curr']);
        $this->assertSame(-50_000, $result['after_joto_ichiji_tousan_joto_tanki_curr']);
        $this->assertSame(0, $result['after_joto_ichiji_tousan_joto_choki_sogo_curr']);
        $this->assertSame(0, $result['after_joto_ichiji_tousan_ichiji_curr']);
    }

    public function test_long_shortfall_is_filled_by_ichiji_when_available(): void
    {
        $calculator = new SogoShotokuNettingCalculator();

        $payload = [
            'sashihiki_joto_tanki_sogo_curr' => 300_000,
            'sashihiki_joto_choki_sogo_curr' => -700_000,
            'sashihiki_ichiji_curr' => 900_000,
        ];

        $result = $calculator->compute($payload, 'curr');

        $this->assertSame(0, $result['tsusango_joto_tanki_curr']);
        $this->assertSame(-400_000, $result['tsusango_joto_choki_sogo_curr']);
        $this->assertSame(900_000, $result['tsusango_ichiji_curr']);
        $this->assertSame(0, $result['tokubetsukojo_joto_tanki_curr']);
        $this->assertSame(0, $result['tokubetsukojo_joto_choki_curr']);
        $this->assertSame(500_000, $result['tokubetsukojo_ichiji_curr']);
        $this->assertSame(0, $result['after_joto_ichiji_tousan_joto_tanki_curr']);
        $this->assertSame(0, $result['after_joto_ichiji_tousan_joto_choki_sogo_curr']);
        $this->assertSame(0, $result['after_joto_ichiji_tousan_ichiji_curr']);
    }

    public function test_inputs_are_normalized_before_calculation(): void
    {
        $calculator = new SogoShotokuNettingCalculator();

        $payload = [
            'sashihiki_joto_tanki_sogo_prev' => '123.9',
            'sashihiki_joto_choki_sogo_prev' => '-23.1',
            'sashihiki_ichiji_prev' => 'not-numeric',
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame(123, $result['sashihiki_joto_tanki_sogo_prev']);
        $this->assertSame(-23, $result['sashihiki_joto_choki_sogo_prev']);
        $this->assertSame(100, $result['tsusango_joto_tanki_prev']);
        $this->assertSame(0, $result['tsusango_joto_choki_sogo_prev']);
        $this->assertSame(0, $result['tsusango_ichiji_prev']);
    }

    public function test_tsusango_ichiji_is_clamped_to_zero_when_negative(): void
    {
        $calculator = new SogoShotokuNettingCalculator();

        $payload = [
            'sashihiki_joto_tanki_sogo_prev' => 0,
            'sashihiki_joto_choki_sogo_prev' => 0,
            'sashihiki_ichiji_prev' => -200_000,
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame(0, $result['tsusango_ichiji_prev']);
        $this->assertSame(0, $result['tokubetsukojo_ichiji_prev']);
        $this->assertSame(0, $result['after_joto_ichiji_tousan_ichiji_prev']);
    }
}