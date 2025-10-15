<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\KojoSeimeiJishinCalculator;
use PHPUnit\Framework\TestCase;

class KojoSeimeiJishinCalculatorTest extends TestCase
{
    public function test_shotoku_life_insurance_prefers_old_only_when_larger(): void
    {
        $calculator = new KojoSeimeiJishinCalculator();

        $payload = [
            'kojo_seimei_shin_prev' => 0,
            'kojo_seimei_kyu_prev' => 100_000,
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame(50_000, $result['shotokuzei_kojo_seimei_ippan_prev']);
    }

    public function test_shotoku_life_insurance_total_is_capped(): void
    {
        $calculator = new KojoSeimeiJishinCalculator();

        $payload = [
            'kojo_seimei_shin_prev' => 102_000,
            'kojo_seimei_kyu_prev' => 102_000,
            'kojo_seimei_nenkin_shin_prev' => 80_000,
            'kojo_seimei_nenkin_kyu_prev' => 90_000,
            'kojo_seimei_kaigo_iryo_prev' => 46_000,
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame(120_000, $result['shotokuzei_kojo_seimei_gokei_prev']);
        $this->assertSame(120_000, $result['kojo_seimei_shotoku_prev']);
    }

    public function test_jumin_life_insurance_uses_combined_amount_when_larger(): void
    {
        $calculator = new KojoSeimeiJishinCalculator();

        $payload = [
            'kojo_seimei_shin_prev' => 32_000,
            'kojo_seimei_kyu_prev' => 1_000,
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame(23_000, $result['juminzei_kojo_seimei_ippan_prev']);
    }

    public function test_jumin_life_insurance_total_is_capped(): void
    {
        $calculator = new KojoSeimeiJishinCalculator();

        $payload = [
            'kojo_seimei_shin_prev' => 102_000,
            'kojo_seimei_nenkin_shin_prev' => 80_000,
            'kojo_seimei_nenkin_kyu_prev' => 80_000,
            'kojo_seimei_kaigo_iryo_prev' => 46_000,
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame(70_000, $result['juminzei_kojo_seimei_gokei_prev']);
        $this->assertSame(70_000, $result['kojo_seimei_jumin_prev']);
    }

    public function test_life_insurance_examples_match_specification(): void
    {
        $calculator = new KojoSeimeiJishinCalculator();

        $payload = [
            'kojo_seimei_shin_prev' => 80_000,
            'kojo_seimei_kyu_prev' => 50_000,
            'kojo_seimei_nenkin_shin_prev' => 30_000,
            'kojo_seimei_nenkin_kyu_prev' => 30_000,
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame(40_000, $result['shotokuzei_kojo_seimei_ippan_prev']);
        $this->assertSame(40_000, $result['shotokuzei_kojo_seimei_nenkin_prev']);
        $this->assertSame(80_000, $result['shotokuzei_kojo_seimei_gokei_prev']);
        $this->assertSame(80_000, $result['kojo_seimei_shotoku_prev']);
        $this->assertSame(30_000, $result['juminzei_kojo_seimei_ippan_prev']);
        $this->assertSame(28_000, $result['juminzei_kojo_seimei_nenkin_prev']);
        $this->assertSame(58_000, $result['juminzei_kojo_seimei_gokei_prev']);
        $this->assertSame(58_000, $result['kojo_seimei_jumin_prev']);
    }

    public function test_earthquake_deductions_follow_specification(): void
    {
        $calculator = new KojoSeimeiJishinCalculator();

        $payload = [
            'kojo_jishin_prev' => 60_000,
            'kojo_kyuchoki_songai_prev' => 30_000,
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame(50_000, $result['shotokuzei_kojo_jishin_eq_prev']);
        $this->assertSame(15_000, $result['shotokuzei_kojo_jishin_old_prev']);
        $this->assertSame(50_000, $result['shotokuzei_kojo_jishin_gokei_prev']);
        $this->assertSame(50_000, $result['kojo_jishin_shotoku_prev']);

        $this->assertSame(25_000, $result['juminzei_kojo_jishin_eq_prev']);
        $this->assertSame(10_000, $result['juminzei_kojo_jishin_old_prev']);
        $this->assertSame(25_000, $result['juminzei_kojo_jishin_gokei_prev']);
        $this->assertSame(25_000, $result['kojo_jishin_jumin_prev']);
    }
}