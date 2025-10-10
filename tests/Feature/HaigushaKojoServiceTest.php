<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Tax\Kojo\HaigushaKojoService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HaigushaKojoServiceTest extends TestCase
{
    #[Test]
    public function it_calculates_spousal_deduction_based_on_category_and_total(): void
    {
        $service = new HaigushaKojoService();

        $payload = [
            'kojo_haigusha_category_prev' => '老人（70歳以上）',
            'kojo_haigusha_category_curr' => '一般（70歳未満）',
            'shotoku_gokei_shotoku_prev' => 8_500_000,
            'shotoku_gokei_shotoku_curr' => 9_300_000,
        ];

        $result = $service->compute($payload);

        $this->assertSame(480000, $result['kojo_haigusha_shotoku_prev']);
        $this->assertSame(380000, $result['kojo_haigusha_jumin_prev']);
        $this->assertSame(260000, $result['kojo_haigusha_shotoku_curr']);
        $this->assertSame(220000, $result['kojo_haigusha_jumin_curr']);
    }

    #[Test]
    public function it_sets_spousal_deduction_to_zero_when_not_applicable(): void
    {
        $service = new HaigushaKojoService();

        $payload = [
            'kojo_haigusha_category_prev' => '老人',
            'kojo_haigusha_category_curr' => 'なし',
            'shotoku_gokei_shotoku_prev' => 10_500_000,
            'shotoku_gokei_shotoku_curr' => 8_000_000,
            'kojo_haigusha_tokubetsu_gokeishotoku_prev' => 1_000_000,
        ];

        $result = $service->compute($payload);

        $this->assertSame(0, $result['kojo_haigusha_shotoku_prev']);
        $this->assertSame(0, $result['kojo_haigusha_jumin_prev']);
        $this->assertSame(0, $result['kojo_haigusha_shotoku_curr']);
        $this->assertSame(0, $result['kojo_haigusha_jumin_curr']);
        $this->assertSame(0, $result['kojo_haigusha_tokubetsu_shotoku_prev']);
        $this->assertSame(0, $result['kojo_haigusha_tokubetsu_jumin_prev']);
    }

    #[Test]
    public function it_calculates_spousal_special_deduction_based_on_total_and_spouse_income(): void
    {
        $service = new HaigushaKojoService();

        $payload = [
            'kojo_haigusha_category_prev' => '一般',
            'kojo_haigusha_category_curr' => '一般',
            'shotoku_gokei_shotoku_prev' => 8_000_000,
            'shotoku_gokei_shotoku_curr' => 9_200_000,
            'kojo_haigusha_tokubetsu_gokeishotoku_prev' => 600_000,
            'kojo_haigusha_tokubetsu_gokeishotoku_curr' => 960_000,
        ];

        $result = $service->compute($payload);

        $this->assertSame(380000, $result['kojo_haigusha_shotoku_prev']);
        $this->assertSame(330000, $result['kojo_haigusha_jumin_prev']);
        $this->assertSame(260000, $result['kojo_haigusha_shotoku_curr']);
        $this->assertSame(220000, $result['kojo_haigusha_jumin_curr']);
        $this->assertSame(380000, $result['kojo_haigusha_tokubetsu_shotoku_prev']);
        $this->assertSame(330000, $result['kojo_haigusha_tokubetsu_jumin_prev']);
        $this->assertSame(240000, $result['kojo_haigusha_tokubetsu_shotoku_curr']);
        $this->assertSame(220000, $result['kojo_haigusha_tokubetsu_jumin_curr']);
    }

    #[Test]
    public function it_returns_zero_special_deduction_when_spouse_income_outside_range(): void
    {
        $service = new HaigushaKojoService();

        $payload = [
            'kojo_haigusha_category_prev' => '一般',
            'kojo_haigusha_category_curr' => '一般',
            'shotoku_gokei_shotoku_prev' => 8_500_000,
            'shotoku_gokei_shotoku_curr' => 8_500_000,
            'kojo_haigusha_tokubetsu_gokeishotoku_prev' => 470_000,
            'kojo_haigusha_tokubetsu_gokeishotoku_curr' => 1_400_000,
        ];

        $result = $service->compute($payload);

        $this->assertSame(0, $result['kojo_haigusha_tokubetsu_shotoku_prev']);
        $this->assertSame(0, $result['kojo_haigusha_tokubetsu_jumin_prev']);
        $this->assertSame(0, $result['kojo_haigusha_tokubetsu_shotoku_curr']);
        $this->assertSame(0, $result['kojo_haigusha_tokubetsu_jumin_curr']);
    }


    #[Test]
    public function it_normalizes_spousal_category_inputs(): void
    {
        $service = new HaigushaKojoService();

        $cases = [
            'ippan' => [
                'input' => 'ippan',
                'total' => 8_000_000,
                'expected' => [380_000, 330_000],
            ],
            'japanese_label' => [
                'input' => '老人（70歳以上）',
                'total' => 8_000_000,
                'expected' => [480_000, 380_000],
            ],
            'romanized_elderly' => [
                'input' => ' rōjin ',
                'total' => 8_000_000,
                'expected' => [480_000, 380_000],
            ],
            'none_label' => [
                'input' => 'なし',
                'total' => 8_000_000,
                'expected' => [0, 0],
            ],
            'over_threshold' => [
                'input' => 'ippan',
                'total' => 10_000_001,
                'expected' => [0, 0],
            ],
        ];

        foreach ($cases as $case) {
            $payload = [
                'kojo_haigusha_category_prev' => $case['input'],
                'kojo_haigusha_category_curr' => 'なし',
                'shotoku_gokei_shotoku_prev' => $case['total'],
                'shotoku_gokei_shotoku_curr' => 0,
            ];

            $result = $service->compute($payload);

            $this->assertSame(
                $case['expected'][0],
                $result['kojo_haigusha_shotoku_prev'],
                sprintf('Failed asserting shotoku for case "%s"', $case['input'])
            );
            $this->assertSame(
                $case['expected'][1],
                $result['kojo_haigusha_jumin_prev'],
                sprintf('Failed asserting jumin for case "%s"', $case['input'])
            );
        }
    }
}
