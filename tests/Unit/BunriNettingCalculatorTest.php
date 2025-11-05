<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Tax\Calculators\BunriNettingCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BunriNettingCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_netting_for_prev_period_with_syuriyu_keihi_inputs(): void
    {
        $calculator = new BunriNettingCalculator();

        // 新仕様: before_tsusan = syunyu - keihi
        // 期待する差引: 120, -80, -150, 60, 40 になるように収入/経費を与える
        $payload = [
            'syunyu_tanki_ippan_prev'  => 120, 'keihi_tanki_ippan_prev'  => 0,
            'syunyu_tanki_keigen_prev' => 0,   'keihi_tanki_keigen_prev' => 80,
            'syunyu_choki_ippan_prev'  => 0,   'keihi_choki_ippan_prev'  => 150,
            'syunyu_choki_tokutei_prev'=> 60,  'keihi_choki_tokutei_prev'=> 0,
            'syunyu_choki_keika_prev'  => 40,  'keihi_choki_keika_prev'  => 0,
        ];

        $result = $calculator->compute($payload, 'prev');

        // 新仕様: BunriNettingCalculator は joto_shotoku_* と gokei も返す。
        // tokubetsu 未指定のため joto_shotoku_* = max(0, after_2)。
        $this->assertSame([
            // before (syunyu-keihi)
            'before_tsusan_tanki_ippan_prev'   => 120,
            'before_tsusan_tanki_keigen_prev'  => -80,
            'before_tsusan_choki_ippan_prev'   => -150,
            'before_tsusan_choki_tokutei_prev' => 60,
            'before_tsusan_choki_keika_prev'   => 40,
            // after_1 (短期内/長期内の相殺)
            'after_1jitsusan_tanki_ippan_prev'   => 40,
            'after_1jitsusan_tanki_keigen_prev'  => 0,
            'after_1jitsusan_choki_ippan_prev'   => -50,
            'after_1jitsusan_choki_tokutei_prev' => 0,
            'after_1jitsusan_choki_keika_prev'   => 0,
            // after_2（短期↔長期相殺）
            'after_2jitsusan_tanki_ippan_prev'   => 0,
            'after_2jitsusan_tanki_keigen_prev'  => 0,
            'after_2jitsusan_choki_ippan_prev'   => -10,
            'after_2jitsusan_choki_tokutei_prev' => 0,
            'after_2jitsusan_choki_keika_prev'   => 0,
            // 特別控除後（指定なし→0下限）
            'joto_shotoku_tanki_ippan_prev'   => 0,
            'joto_shotoku_tanki_keigen_prev'  => 0,
            'joto_shotoku_choki_ippan_prev'   => 0,
            'joto_shotoku_choki_tokutei_prev' => 0,
            'joto_shotoku_choki_keika_prev'   => 0,
            'joto_shotoku_tanki_gokei_prev' => 0,
            'joto_shotoku_choki_gokei_prev' => 0,
        ], $result);
    }

    #[Test]
    public function it_truncates_inputs_and_calculates_curr_period_with_syuriyu_keihi_inputs(): void
    {
        $calculator = new BunriNettingCalculator();

        // 小数は (float)→(int) の切捨てがかかる想定。
        // 例: 0.0 - 30.9 -> -30, 100.5 - 0.0 -> 100 など。
        $payload = [
            'syunyu_tanki_ippan_curr'   => '0.0',   'keihi_tanki_ippan_curr'   => '30.9', // -30
            'syunyu_tanki_keigen_curr'  => '0.0',   'keihi_tanki_keigen_curr'  => '40.1', // -40
            'syunyu_choki_ippan_curr'   => '100.5', 'keihi_choki_ippan_curr'   => '0.0',  // 100
            'syunyu_choki_tokutei_curr' => '50.9',  'keihi_choki_tokutei_curr' => '0.0',  // 50
            'syunyu_choki_keika_curr'   => '25.2',  'keihi_choki_keika_curr'   => '0.0',  // 25
        ];

        $result = $calculator->compute($payload, 'curr');

        $this->assertSame([
            // before
            'before_tsusan_tanki_ippan_curr'   => -30,
            'before_tsusan_tanki_keigen_curr'  => -40,
            'before_tsusan_choki_ippan_curr'   => 100,
            'before_tsusan_choki_tokutei_curr' => 50,
            'before_tsusan_choki_keika_curr'   => 25,
            // after_1
            'after_1jitsusan_tanki_ippan_curr'   => -30,
            'after_1jitsusan_tanki_keigen_curr'  => -40,
            'after_1jitsusan_choki_ippan_curr'   => 100,
            'after_1jitsusan_choki_tokutei_curr' => 50,
            'after_1jitsusan_choki_keika_curr'   => 25,
            // after_2
            'after_2jitsusan_tanki_ippan_curr'   => 0,
            'after_2jitsusan_tanki_keigen_curr'  => 0,
            'after_2jitsusan_choki_ippan_curr'   => 30,
            'after_2jitsusan_choki_tokutei_curr' => 50,
            'after_2jitsusan_choki_keika_curr'   => 25,
            // joto_shotoku_*（tokubetsu無→0/after_2を0下限で反映）
            'joto_shotoku_tanki_ippan_curr'   => 0,
            'joto_shotoku_tanki_keigen_curr'  => 0,
            'joto_shotoku_choki_ippan_curr'   => 30,
            'joto_shotoku_choki_tokutei_curr' => 50,
            'joto_shotoku_choki_keika_curr'   => 25,
            'joto_shotoku_tanki_gokei_curr'   => 0,
            'joto_shotoku_choki_gokei_curr'   => 105,
        ], $result);
    }
}