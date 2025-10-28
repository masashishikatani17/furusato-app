<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Tax\Calculators\TaxBaseMirrorCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SeparatedModeMirrorTest extends TestCase
{
    #[Test]
    public function it_computes_separated_mode_totals_for_current_period(): void
    {
        $calculator = new TaxBaseMirrorCalculator();

        $payload = [
            'shotoku_keijo_curr' => 100_000,
            'shotoku_joto_tanki_sogo_curr' => 200_000,
            'shotoku_joto_choki_sogo_curr' => 300_000,
            'shotoku_ichiji_curr' => 400_000,
            'shotoku_sanrin_curr' => 50_000,
            'shotoku_taishoku_curr' => 60_000,
            'after_3jitsusan_joto_tanki_sogo_curr' => 110_000,
            'after_3jitsusan_joto_choki_sogo_curr' => 120_000,
            'after_3jitsusan_ichiji_curr' => 130_000,
            'after_3jitsusan_sanrin_curr' => 140_000,
            'after_3jitsusan_taishoku_curr' => 150_000,
            'kojo_gokei_shotoku_curr' => 16_000,
            'kojo_gokei_jumin_curr' => 26_000,
        ];

        $context = [
            'syori_settings' => ['bunri_flag_curr' => 1],
        ];

        $result = $calculator->compute($payload, $context);

        $this->assertSame(650_000, $result['bunri_sogo_gokeigaku_shotoku_curr']);
        $this->assertSame(650_000, $result['bunri_sogo_gokeigaku_jumin_curr']);
        $this->assertSame(16_000, $result['bunri_sashihiki_gokei_shotoku_curr']);
        $this->assertSame(26_000, $result['bunri_sashihiki_gokei_jumin_curr']);
        $this->assertSame(634_000, $result['bunri_kazeishotoku_sogo_shotoku_curr']);
        $this->assertSame(624_000, $result['bunri_kazeishotoku_sogo_jumin_curr']);
        $this->assertSame(984_000, $result['tax_kazeishotoku_shotoku_curr']);
        $this->assertSame(624_000, $result['tax_kazeishotoku_jumin_curr']);
    }

    #[Test]
    public function it_computes_separated_mode_long_term_only_totals_for_previous_period(): void
    {
        $calculator = new TaxBaseMirrorCalculator();

        $payload = [
            'shotoku_keijo_prev' => 0,
            'shotoku_joto_tanki_sogo_prev' => 0,
            'shotoku_joto_choki_sogo_prev' => 500_123,
            'shotoku_ichiji_prev' => 0,
            'shotoku_sanrin_prev' => 0,
            'shotoku_taishoku_prev' => 0,
            'after_3jitsusan_joto_tanki_sogo_prev' => 0,
            'after_3jitsusan_joto_choki_sogo_prev' => 500_123,
            'after_3jitsusan_ichiji_prev' => 0,
            'after_3jitsusan_sanrin_prev' => 0,
            'after_3jitsusan_taishoku_prev' => 0,
            'kojo_gokei_shotoku_prev' => 100_000,
            'kojo_gokei_jumin_prev' => 200_000,
        ];

        $context = [
            'syori_settings' => ['bunri_flag_prev' => 1],
        ];

        $result = $calculator->compute($payload, $context);

        $this->assertSame(500_123, $result['bunri_sogo_gokeigaku_shotoku_prev']);
        $this->assertSame(500_123, $result['bunri_sogo_gokeigaku_jumin_prev']);
        $this->assertSame(100_000, $result['bunri_sashihiki_gokei_shotoku_prev']);
        $this->assertSame(200_000, $result['bunri_sashihiki_gokei_jumin_prev']);
        $this->assertSame(400_000, $result['bunri_kazeishotoku_sogo_shotoku_prev']);
        $this->assertSame(300_000, $result['bunri_kazeishotoku_sogo_jumin_prev']);
        $this->assertSame(400_000, $result['tax_kazeishotoku_shotoku_prev']);
        $this->assertSame(300_000, $result['tax_kazeishotoku_jumin_prev']);
    }

    #[Test]
    public function it_keeps_jumin_tax_in_sync_when_one_time_income_is_zero(): void
    {
        $calculator = new TaxBaseMirrorCalculator();

        $payload = [
            'shotoku_keijo_curr' => 0,
            'shotoku_joto_tanki_sogo_curr' => 0,
            'shotoku_joto_choki_sogo_curr' => 321_111,
            'shotoku_ichiji_curr' => 0,
            'shotoku_sanrin_curr' => 0,
            'shotoku_taishoku_curr' => 0,
            'after_3jitsusan_joto_tanki_sogo_curr' => 0,
            'after_3jitsusan_joto_choki_sogo_curr' => 321_111,
            'after_3jitsusan_ichiji_curr' => 0,
            'after_3jitsusan_sanrin_curr' => 0,
            'after_3jitsusan_taishoku_curr' => 0,
            'kojo_gokei_shotoku_curr' => 21_000,
            'kojo_gokei_jumin_curr' => 11_000,
        ];

        $context = [
            'syori_settings' => ['bunri_flag_curr' => 1],
        ];

        $result = $calculator->compute($payload, $context);

        $this->assertSame(321_111, $result['bunri_sogo_gokeigaku_shotoku_curr']);
        $this->assertSame(321_111, $result['bunri_sogo_gokeigaku_jumin_curr']);
        $this->assertSame(300_000, $result['bunri_kazeishotoku_sogo_shotoku_curr']);
        $this->assertSame(310_000, $result['bunri_kazeishotoku_sogo_jumin_curr']);
        $this->assertSame(300_000, $result['tax_kazeishotoku_shotoku_curr']);
        $this->assertSame(310_000, $result['tax_kazeishotoku_jumin_curr']);
    }

    #[Test]
    public function it_zeros_out_taxable_amounts_when_deductions_cover_separated_income(): void
    {
        $calculator = new TaxBaseMirrorCalculator();

        $payload = [
            'shotoku_keijo_curr' => 50_000,
            'shotoku_joto_tanki_sogo_curr' => 10_000,
            'shotoku_joto_choki_sogo_curr' => 20_000,
            'shotoku_ichiji_curr' => 10_000,
            'shotoku_sanrin_curr' => 0,
            'shotoku_taishoku_curr' => 0,
            'after_3jitsusan_joto_tanki_sogo_curr' => 10_000,
            'after_3jitsusan_joto_choki_sogo_curr' => 20_000,
            'after_3jitsusan_ichiji_curr' => 10_000,
            'after_3jitsusan_sanrin_curr' => 0,
            'after_3jitsusan_taishoku_curr' => 0,
            'kojo_gokei_shotoku_curr' => 100_000,
            'kojo_gokei_jumin_curr' => 120_000,
        ];

        $context = [
            'syori_settings' => ['bunri_flag_curr' => 1],
        ];

        $result = $calculator->compute($payload, $context);

        $this->assertSame(40_000, $result['bunri_sogo_gokeigaku_shotoku_curr']);
        $this->assertSame(40_000, $result['bunri_sogo_gokeigaku_jumin_curr']);
        $this->assertSame(0, $result['bunri_kazeishotoku_sogo_shotoku_curr']);
        $this->assertSame(0, $result['bunri_kazeishotoku_sogo_jumin_curr']);
        $this->assertSame(0, $result['tax_kazeishotoku_shotoku_curr']);
        $this->assertSame(0, $result['tax_kazeishotoku_jumin_curr']);
    }

    #[Test]
    public function it_applies_comprehensive_mode_flooring_and_zero_floor(): void
    {
        $calculator = new TaxBaseMirrorCalculator();

        $payload = [
            'shotoku_keijo_prev' => 120_000,
            'shotoku_joto_tanki_sogo_prev' => 50_000,
            'shotoku_joto_choki_sogo_prev' => 40_000,
            'shotoku_ichiji_prev' => -20_000,
            'kojo_gokei_shotoku_prev' => 33_333,
            'kojo_gokei_jumin_prev' => 10_000,
        ];

        $context = [
            'syori_settings' => ['bunri_flag_prev' => 0],
        ];

        $result = $calculator->compute($payload, $context);

        $this->assertSame(176_000, $result['tax_kazeishotoku_shotoku_prev']);
        $this->assertSame(200_000, $result['tax_kazeishotoku_jumin_prev']);
        $this->assertSame(70_000, $result['shotoku_joto_ichiji_shotoku_prev']);
        $this->assertSame(70_000, $result['shotoku_joto_ichiji_jumin_prev']);
    }
}