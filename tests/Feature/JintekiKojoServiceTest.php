<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Tax\Kojo\JintekiKojoService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class JintekiKojoServiceTest extends TestCase
{
    #[Test]
    public function it_applies_widow_deduction_when_total_within_threshold(): void
    {
        $service = new JintekiKojoService();

        $payload = [
            'kojo_kafu_applicable_prev' => '〇',
            'kojo_kafu_applicable_curr' => '〇',
            'shotoku_gokei_shotoku_prev' => 4_000_000,
            'shotoku_gokei_shotoku_curr' => 5_000_000,
        ];

        $result = $service->compute($payload);

        $this->assertSame(270000, $result['kojo_kafu_shotoku_prev']);
        $this->assertSame(260000, $result['kojo_kafu_jumin_prev']);
        $this->assertSame(270000, $result['kojo_kafu_shotoku_curr']);
        $this->assertSame(260000, $result['kojo_kafu_jumin_curr']);
    }

    #[Test]
    public function it_disallows_single_parent_deduction_when_total_exceeds_threshold_or_not_selected(): void
    {
        $service = new JintekiKojoService();

        $payload = [
            'kojo_hitorioya_applicable_prev' => '〇',
            'kojo_hitorioya_applicable_curr' => '×',
            'shotoku_gokei_shotoku_prev' => 6_000_000,
            'shotoku_gokei_shotoku_curr' => 4_000_000,
        ];

        $result = $service->compute($payload);

        $this->assertSame(0, $result['kojo_hitorioya_shotoku_prev']);
        $this->assertSame(0, $result['kojo_hitorioya_jumin_prev']);
        $this->assertSame(0, $result['kojo_hitorioya_shotoku_curr']);
        $this->assertSame(0, $result['kojo_hitorioya_jumin_curr']);
    }

    #[Test]
    public function it_applies_kinrogakusei_deduction_only_when_selected(): void
    {
        $service = new JintekiKojoService();

        $payload = [
            'kojo_kinrogakusei_applicable_prev' => '〇',
            'kojo_kinrogakusei_applicable_curr' => '×',
            'shotoku_gokei_shotoku_prev' => 10_000_000,
            'shotoku_gokei_shotoku_curr' => 0,
        ];

        $result = $service->compute($payload);

        $this->assertSame(270000, $result['kojo_kinrogakusei_shotoku_prev']);
        $this->assertSame(260000, $result['kojo_kinrogakusei_jumin_prev']);
        $this->assertSame(0, $result['kojo_kinrogakusei_shotoku_curr']);
        $this->assertSame(0, $result['kojo_kinrogakusei_jumin_curr']);
    }
}