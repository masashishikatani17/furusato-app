<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Tax\Calculators\BunriNettingCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BunriNettingCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_netting_for_prev_period(): void
    {
        $calculator = new BunriNettingCalculator();

        $payload = [
            'bunri_shotoku_tanki_ippan_shotoku_prev' => 120,
            'bunri_shotoku_tanki_keigen_shotoku_prev' => -80,
            'bunri_shotoku_choki_ippan_shotoku_prev' => -150,
            'bunri_shotoku_choki_tokutei_shotoku_prev' => 60,
            'bunri_shotoku_choki_keika_shotoku_prev' => 40,
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame([
            'before_tsusan_tanki_ippan_prev' => 120,
            'before_tsusan_tanki_keigen_prev' => -80,
            'before_tsusan_choki_ippan_prev' => -150,
            'before_tsusan_choki_tokutei_prev' => 60,
            'before_tsusan_choki_keika_prev' => 40,
            'after_1jitsusan_tanki_ippan_prev' => 40,
            'after_1jitsusan_tanki_keigen_prev' => 0,
            'after_1jitsusan_choki_ippan_prev' => -50,
            'after_1jitsusan_tanki_tokutei_prev' => 0,
            'after_1jitsusan_tanki_keika_prev' => 0,
            'after_2jitsusan_tanki_ippan_prev' => 0,
            'after_2jitsusan_tanki_keigen_prev' => 0,
            'after_2jitsusan_choki_ippan_prev' => -10,
            'after_2jitsusan_tanki_tokutei_prev' => 0,
            'after_2jitsusan_tanki_keika_prev' => 0,
        ], $result);
    }

    #[Test]
    public function it_truncates_inputs_and_calculates_curr_period(): void
    {
        $calculator = new BunriNettingCalculator();

        $payload = [
            'bunri_shotoku_tanki_ippan_shotoku_curr' => '-30.9',
            'bunri_shotoku_tanki_keigen_shotoku_curr' => '-40.1',
            'bunri_shotoku_choki_ippan_shotoku_curr' => '100.5',
            'bunri_shotoku_choki_tokutei_shotoku_curr' => '50.9',
            'bunri_shotoku_choki_keika_shotoku_curr' => '25.2',
        ];

        $result = $calculator->compute($payload, 'curr');

        $this->assertSame([
            'before_tsusan_tanki_ippan_curr' => -30,
            'before_tsusan_tanki_keigen_curr' => -40,
            'before_tsusan_choki_ippan_curr' => 100,
            'before_tsusan_choki_tokutei_curr' => 50,
            'before_tsusan_choki_keika_curr' => 25,
            'after_1jitsusan_tanki_ippan_curr' => -30,
            'after_1jitsusan_tanki_keigen_curr' => -40,
            'after_1jitsusan_choki_ippan_curr' => 100,
            'after_1jitsusan_tanki_tokutei_curr' => 50,
            'after_1jitsusan_tanki_keika_curr' => 25,
            'after_2jitsusan_tanki_ippan_curr' => 0,
            'after_2jitsusan_tanki_keigen_curr' => 0,
            'after_2jitsusan_choki_ippan_curr' => 30,
            'after_2jitsusan_tanki_tokutei_curr' => 50,
            'after_2jitsusan_tanki_keika_curr' => 25,
        ], $result);
    }
}