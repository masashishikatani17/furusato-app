<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Tax\Calculators\BunriKabutekiNettingCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BunriKabutekiNettingCalculatorTest extends TestCase
{
    #[Test]
    public function it_netting_with_dividend_offsetting_transfer_loss(): void
    {
        $calculator = new BunriKabutekiNettingCalculator();

        $payload = [
            'shotoku_jojo_joto_prev' => -120,
            'shotoku_jojo_haito_prev' => 80,
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame([
            'before_tsusan_jojo_joto_prev' => -120,
            'before_tsusan_jojo_haito_prev' => 80,
            'after_tsusan_jojo_joto_prev' => -40,
            'after_tsusan_jojo_haito_prev' => 0,
        ], $result);
    }

    #[Test]
    public function it_handles_positive_transfer_without_offset(): void
    {
        $calculator = new BunriKabutekiNettingCalculator();

        $payload = [
            'shotoku_jojo_joto_curr' => 50,
            'shotoku_jojo_haito_curr' => 30,
        ];

        $result = $calculator->compute($payload, 'curr');

        $this->assertSame([
            'before_tsusan_jojo_joto_curr' => 50,
            'before_tsusan_jojo_haito_curr' => 30,
            'after_tsusan_jojo_joto_curr' => 50,
            'after_tsusan_jojo_haito_curr' => 30,
        ], $result);
    }

    #[Test]
    public function it_truncates_inputs_and_caps_negative_dividend(): void
    {
        $calculator = new BunriKabutekiNettingCalculator();

        $payload = [
            'shotoku_jojo_joto_prev' => '-30.9',
            'shotoku_jojo_haito_prev' => '-20.1',
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame([
            'before_tsusan_jojo_joto_prev' => -30,
            'before_tsusan_jojo_haito_prev' => 0,
            'after_tsusan_jojo_joto_prev' => -30,
            'after_tsusan_jojo_haito_prev' => 0,
        ], $result);
    }
}