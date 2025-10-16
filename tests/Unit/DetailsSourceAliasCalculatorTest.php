<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\DetailsSourceAliasCalculator;
use PHPUnit\Framework\TestCase;

class DetailsSourceAliasCalculatorTest extends TestCase
{
    public function test_it_copies_detail_values_with_truncation(): void
    {
        $calculator = new DetailsSourceAliasCalculator();

        $payload = [
            'jigyo_eigyo_shotoku_prev' => '123.9',
            'fudosan_shotoku_prev' => '456.7',
            'sashihiki_sanrin_prev' => -890,
            'sashihiki_tanki_ippan_prev' => '1,234',
            'sashihiki_tanki_keigen_prev' => '-567',
            'sashihiki_choki_ippan_prev' => '98',
            'sashihiki_choki_tokutei_prev' => '76',
            'sashihiki_choki_keika_prev' => '-54.3',
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame(123, $result['shotoku_jigyo_eigyo_shotoku_prev']);
        $this->assertSame(456, $result['shotoku_fudosan_shotoku_prev']);
        $this->assertSame(-890, $result['bunri_shotoku_sanrin_shotoku_prev']);
        $this->assertSame(1234, $result['bunri_shotoku_tanki_ippan_shotoku_prev']);
        $this->assertSame(-567, $result['bunri_shotoku_tanki_keigen_shotoku_prev']);
        $this->assertSame(98, $result['bunri_shotoku_choki_ippan_shotoku_prev']);
        $this->assertSame(76, $result['bunri_shotoku_choki_tokutei_shotoku_prev']);
        $this->assertSame(-54, $result['bunri_shotoku_choki_keika_shotoku_prev']);
    }

    public function test_it_does_not_override_when_source_is_missing_or_empty(): void
    {
        $calculator = new DetailsSourceAliasCalculator();

        $payload = [
            'jigyo_eigyo_shotoku_curr' => null,
            'fudosan_shotoku_curr' => '',
            'sashihiki_sanrin_curr' => ' ',
        ];

        $result = $calculator->compute($payload, 'curr');

        $this->assertArrayNotHasKey('shotoku_jigyo_eigyo_shotoku_curr', $result);
        $this->assertArrayNotHasKey('shotoku_fudosan_shotoku_curr', $result);
        $this->assertArrayNotHasKey('bunri_shotoku_sanrin_shotoku_curr', $result);
    }

    public function test_invalid_period_returns_empty_array(): void
    {
        $calculator = new DetailsSourceAliasCalculator();

        $result = $calculator->compute([], 'invalid');

        $this->assertSame([], $result);
    }
}