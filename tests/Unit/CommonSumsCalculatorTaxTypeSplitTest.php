<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\CommonSumsCalculator;
use PHPUnit\Framework\TestCase;

final class CommonSumsCalculatorTaxTypeSplitTest extends TestCase
{
    public function test_sum_for_sogoshotoku_etc_is_split_by_tax_type_for_retirement_income(): void
    {
        $sut = new CommonSumsCalculator();

        $payload = [
            // A（総合）
            'shotoku_keijo_prev' => 1_000_000,
            // B（山林）
            'shotoku_sanrin_prev' => 50_000,
            // B（退職：税目別）
            'bunri_shotoku_taishoku_shotoku_prev' => 200_000,
            'bunri_shotoku_taishoku_jumin_prev' => 300_000,
            // Cafter（分離）
            'tsusango_tanki_ippan_prev' => 10_000,
        ];

        $out = $sut->compute($payload, []);

        // 所得税側: 1,000,000 + (200,000+50,000) + 10,000 = 1,260,000
        $this->assertSame(1_260_000, $out['sum_for_sogoshotoku_etc_prev'] ?? null);
        $this->assertSame(1_260_000, $out['sum_for_sogoshotoku_etc_shotoku_prev'] ?? null);

        // 住民税側: 1,000,000 + (300,000+50,000) + 10,000 = 1,360,000
        $this->assertSame(1_360_000, $out['sum_for_sogoshotoku_etc_jumin_prev'] ?? null);

        // 既存の合計所得金額SoTは所得税側を継続利用
        $this->assertSame(1_260_000, $out['sum_for_gokeishotoku_prev'] ?? null);
    }
}
