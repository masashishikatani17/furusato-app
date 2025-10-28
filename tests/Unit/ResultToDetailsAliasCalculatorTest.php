<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\ResultToDetailsAliasCalculator;
use PHPUnit\Framework\TestCase;

final class ResultToDetailsAliasCalculatorTest extends TestCase
{
    public function test_it_backfills_and_clamps_values_for_each_period(): void
    {
        $calculator = new ResultToDetailsAliasCalculator();

        $payload = [
            'after_1jitsusan_sanrin_prev' => '800,000',
            'after_1jitsusan_sanrin_curr' => -200000,

            'after_naibutsusan_joto_tanki_sogo_prev' => 100,
            'after_naibutsusan_joto_choki_sogo_prev' => 200,
            'after_naibutsusan_ichiji_prev' => 300,
            'tokubetsukojo_joto_tanki_sogo_prev' => 50,
            'tokubetsukojo_joto_choki_sogo_prev' => 60,
            'tokubetsukojo_ichiji_prev' => 70,
            'after_joto_ichiji_tousan_joto_tanki_sogo_prev' => -10,
            'after_joto_ichiji_tousan_joto_choki_sogo_prev' => -20,
            'after_joto_ichiji_tousan_ichiji_prev' => 30,
            'after_3jitsusan_tanki_sogo_prev' => 111,
            'after_3jitsusan_choki_sogo_prev' => 222,
            'after_3jitsusan_ichiji_prev' => 333,
            'shotoku_joto_tanki_sogo_prev' => 444,
            'shotoku_joto_choki_sogo_prev' => 555,
            'shotoku_ichiji_prev' => 666,

            'after_naibutsusan_joto_tanki_sogo_curr' => -100,
            'after_naibutsusan_joto_choki_sogo_curr' => -200,
            'after_naibutsusan_ichiji_curr' => -300,
            'tokubetsukojo_joto_tanki_sogo_curr' => 500,
            'tokubetsukojo_joto_choki_sogo_curr' => 600,
            'tokubetsukojo_ichiji_curr' => 700,
            'after_joto_ichiji_tousan_joto_tanki_sogo_curr' => 10,
            'after_joto_ichiji_tousan_joto_choki_sogo_curr' => 20,
            'after_joto_ichiji_tousan_ichiji_curr' => 30,
            'after_3jitsusan_tanki_sogo_curr' => -111,
            'after_3jitsusan_choki_sogo_curr' => -222,
            'after_3jitsusan_ichiji_curr' => -333,
            'shotoku_joto_tanki_sogo_curr' => -444,
            'shotoku_joto_choki_sogo_curr' => -555,
            'shotoku_ichiji_curr' => -666,

            'after_2jitsusan_tanki_ippan_prev' => 600000,
            'after_2jitsusan_tanki_keigen_prev' => 700000,
            'after_2jitsusan_choki_ippan_prev' => -800000,
            'after_2jitsusan_choki_tokutei_prev' => 900000,
            'after_2jitsusan_choki_keika_prev' => -1000000,
            'tokubetsukojo_tanki_ippan_prev' => 900000,
            'tokubetsukojo_tanki_keigen_prev' => 100000,
            'tokubetsukojo_choki_ippan_prev' => 200000,
            'tokubetsukojo_choki_tokutei_prev' => 300000,
            'tokubetsukojo_choki_keika_prev' => 400000,

            'after_2jitsusan_tanki_ippan_curr' => -600000,
            'after_2jitsusan_tanki_keigen_curr' => 0,
            'after_2jitsusan_choki_ippan_curr' => 1000,
            'after_2jitsusan_choki_tokutei_curr' => -2000,
            'after_2jitsusan_choki_keika_curr' => 3000,
            'tokubetsukojo_tanki_ippan_curr' => 100,
            'tokubetsukojo_tanki_keigen_curr' => -200,
            'tokubetsukojo_choki_ippan_curr' => 4000,
            'tokubetsukojo_choki_tokutei_curr' => -5000,
            'tokubetsukojo_choki_keika_curr' => 6000,

            'after_tsusan_jojo_joto_prev' => 1_200_000,
            'after_tsusan_jojo_haito_prev' => 50_000,
            'shotoku_ippan_joto_prev' => 40_000,
            'kurikoshi_jojo_joto_prev' => 1_500_000,

            'after_tsusan_jojo_joto_curr' => -200_000,
            'after_tsusan_jojo_haito_curr' => 60_000,
            'shotoku_ippan_joto_curr' => 30_000,
            'kurikoshi_jojo_joto_curr' => 10_000,
        ];

        $result = $calculator->compute($payload, [
            'kihu_year' => 2025,
            'guest_birth_date' => null,
            'data' => null,
        ]);

        $this->assertSame(800000, $result['tsusango_sanrin_prev']);
        $this->assertSame(500000, $result['tokubetsukojo_sanrin_prev']);
        $this->assertSame(300000, $result['shotoku_sanrin_prev']);

        $this->assertSame(-200000, $result['tsusango_sanrin_curr']);
        $this->assertSame(0, $result['tokubetsukojo_sanrin_curr']);
        $this->assertSame(-200000, $result['shotoku_sanrin_curr']);

        $this->assertSame(111, $result['tsusango_joto_tanki_sogo_prev']);
        $this->assertSame(222, $result['tsusango_joto_choki_sogo_prev']);
        $this->assertSame(333, $result['tsusango_ichiji_prev']);
        $this->assertSame(-111, $result['tsusango_joto_tanki_sogo_curr']);
        $this->assertSame(-222, $result['tsusango_joto_choki_sogo_curr']);
        $this->assertSame(-333, $result['tsusango_ichiji_curr']);

        $this->assertSame(600000, $result['tsusango_tanki_ippan_prev']);
        $this->assertSame(600000, $result['tokubetsukojo_tanki_ippan_prev']);
        $this->assertSame(0, $result['joto_shotoku_tanki_ippan_prev']);
        $this->assertSame(-600000, $result['tsusango_tanki_ippan_curr']);
        $this->assertSame(0, $result['tokubetsukojo_tanki_ippan_curr']);
        $this->assertSame(-600000, $result['joto_shotoku_tanki_ippan_curr']);

        $this->assertSame(1_200_000, $result['tsusango_jojo_joto_prev']);
        $this->assertSame(0, $result['shotoku_after_kurikoshi_jojo_joto_prev']);
        $this->assertSame(40_000, $result['tsusango_ippan_joto_prev']);
        $this->assertSame(40_000, $result['shotoku_after_kurikoshi_ippan_joto_prev']);
        $this->assertSame(50_000, $result['shotoku_after_kurikoshi_jojo_haito_prev']);

        $this->assertSame(-200_000, $result['tsusango_jojo_joto_curr']);
        $this->assertSame(-200_000, $result['shotoku_after_kurikoshi_jojo_joto_curr']);
        $this->assertSame(30_000, $result['shotoku_after_kurikoshi_ippan_joto_curr']);
        $this->assertSame(60_000, $result['shotoku_after_kurikoshi_jojo_haito_curr']);
    }
}