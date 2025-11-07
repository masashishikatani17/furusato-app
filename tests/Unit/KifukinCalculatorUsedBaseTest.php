<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\KifukinCalculator;
use PHPUnit\Framework\TestCase;

final class KifukinCalculatorUsedBaseTest extends TestCase
{
    private function calc(array $payload): array
    {
        $sut = new KifukinCalculator();
        return $sut->compute($payload, ['syori_settings' => []]);
    }

    public function test_used_base_is_min_of_donation_sum_and_40percent_cap(): void
    {
        // 総所得金額等 S=1,000,000 → 上限=400,000
        // baseDonations(=DONATION_BASE_KEYS合計)=300,000、ふるさと=150,000 → donationSum=450,000
        // I は min(450,000, 400,000) = 400,000
        $p = [
            'sum_for_sogoshotoku_etc_prev' => 1_000_000,
            // DONATION_BASE_KEYS の代表を2つだけ使う（合計=300,000）
            'shotokuzei_shotokukojo_kyodobokin_nisseki_prev' => 200_000,
            'shotokuzei_shotokukojo_npo_prev'                 => 100_000,
            'shotokuzei_shotokukojo_koueki_prev'              => 0,
            'shotokuzei_shotokukojo_furusato_prev' => 150_000,
        ];
        $out = $this->calc($p);
        $this->assertSame(400_000, $out['used_by_income_deduction_prev'] ?? null);
    }
}
