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

    #[Test]
    public function it_calculates_shogaisyo_deductions_from_counts(): void
    {
        $service = new JintekiKojoService();

        $payload = [
            'kojo_shogaisha_count_prev' => '1',
            'kojo_tokubetsu_shogaisha_count_prev' => '1',
            'kojo_doukyo_tokubetsu_shogaisha_count_prev' => '1',
            'kojo_shogaisha_count_curr' => '0',
            'kojo_tokubetsu_shogaisha_count_curr' => null,
            'kojo_doukyo_tokubetsu_shogaisha_count_curr' => '',
        ];

        $result = $service->compute($payload);

        $this->assertSame(1_420_000, $result['kojo_shogaisyo_shotoku_prev']);
        $this->assertSame(1_090_000, $result['kojo_shogaisyo_jumin_prev']);
        $this->assertSame(0, $result['kojo_shogaisyo_shotoku_curr']);
        $this->assertSame(0, $result['kojo_shogaisyo_jumin_curr']);
    }

    #[Test]
    public function it_calculates_fuyo_deductions_from_counts(): void
    {
        $service = new JintekiKojoService();

        $payload = [
            'kojo_fuyo_ippan_count_prev' => '1',
            'kojo_fuyo_tokutei_count_prev' => '1',
            'kojo_fuyo_roujin_doukyo_count_prev' => '1',
            'kojo_fuyo_roujin_sonota_count_prev' => '1',
            'kojo_fuyo_ippan_count_curr' => '1',
            'kojo_fuyo_tokutei_count_curr' => '1',
            'kojo_fuyo_roujin_doukyo_count_curr' => '1',
            'kojo_fuyo_roujin_sonota_count_curr' => '1',
        ];

        $result = $service->compute($payload);

        $this->assertSame(2_070_000, $result['kojo_fuyo_shotoku_prev']);
        $this->assertSame(2_070_000, $result['kojo_fuyo_shotoku_curr']);
        $this->assertSame(1_610_000, $result['kojo_fuyo_jumin_prev']);
        $this->assertSame(1_610_000, $result['kojo_fuyo_jumin_curr']);
    }

    #[Test]
    public function it_calculates_tokutei_shinzoku_deduction_from_incomes(): void
    {
        $service = new JintekiKojoService();

        $payload = [
            'kojo_tokutei_shinzoku_1_shotoku_prev' => '580,001',
            'kojo_tokutei_shinzoku_2_shotoku_prev' => '1,000,001',
            'kojo_tokutei_shinzoku_3_shotoku_prev' => '1,230,001',
            'kojo_tokutei_shinzoku_1_shotoku_curr' => '900,001',
            'kojo_tokutei_shinzoku_2_shotoku_curr' => '1,050,001',
            'kojo_tokutei_shinzoku_3_shotoku_curr' => '1,150,001',
        ];

        $result = $service->compute($payload, 2024);

        $this->assertSame(940_000, $result['kojo_tokutei_shinzoku_shotoku_prev']);
        $this->assertSame(780_000, $result['kojo_tokutei_shinzoku_shotoku_curr']);
        $this->assertSame(0, $result['kojo_tokutei_shinzoku_jumin_prev']);
        $this->assertSame(0, $result['kojo_tokutei_shinzoku_jumin_curr']);
    }

    #[Test]
    public function it_sets_prev_tokutei_deduction_to_zero_for_2025(): void
    {
        $service = new JintekiKojoService();

        $payload = [
            'kojo_tokutei_shinzoku_1_shotoku_prev' => '580,001',
            'kojo_tokutei_shinzoku_2_shotoku_prev' => '1,000,001',
            'kojo_tokutei_shinzoku_3_shotoku_prev' => '1,230,001',
            'kojo_tokutei_shinzoku_1_shotoku_curr' => '580,001',
            'kojo_tokutei_shinzoku_2_shotoku_curr' => '1,000,001',
            'kojo_tokutei_shinzoku_3_shotoku_curr' => '1,230,001',
        ];

        $result = $service->compute($payload, 2025);

        $this->assertSame(0, $result['kojo_tokutei_shinzoku_shotoku_prev']);
        $this->assertSame(940_000, $result['kojo_tokutei_shinzoku_shotoku_curr']);
        $this->assertSame(0, $result['kojo_tokutei_shinzoku_jumin_prev']);
        $this->assertSame(0, $result['kojo_tokutei_shinzoku_jumin_curr']);
    }
}