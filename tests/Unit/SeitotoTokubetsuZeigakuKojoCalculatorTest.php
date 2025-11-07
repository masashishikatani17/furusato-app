<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\SeitotoTokubetsuZeigakuKojoCalculator;
use PHPUnit\Framework\TestCase;

final class SeitotoTokubetsuZeigakuKojoCalculatorTest extends TestCase
{
    private function calc(array $payload): array
    {
        $sut = new SeitotoTokubetsuZeigakuKojoCalculator();
        // ctx は今回未使用
        return $sut->compute($payload, []);
    }

    public function test_under_2000_yen_results_in_zero_credit(): void
    {
        $p = [
            'sum_for_sogoshotoku_etc_prev' => 10_000_000,
            'tax_zeigaku_shotoku_prev'     => 100_000,
            'used_by_income_deduction_prev'=> 0,
            'shotokuzei_zeigakukojo_seito_prev'  => 0,
            'shotokuzei_zeigakukojo_npo_prev'    => 1_000,
            'shotokuzei_zeigakukojo_koueki_prev' => 500,
        ];
        $out = $this->calc($p);
        $this->assertSame(0, $out['tax_credit_shotoku_total_prev'] ?? null);
        $this->assertSame(100_000, $out['tax_sashihiki_shotoku_prev'] ?? null);
    }

    public function test_40_percent_cap_limits_credit_base(): void
    {
        // 総所得金額等 S=1,000,000 → 40%枠=400,000
        // 税額 T=1,000,000 → 25%枠=250,000（＝40%カテゴリの控除額上限）
        // 入力：NPO=500,000（> cap40）、I=0（所得控除側未使用）
        $p = [
            'sum_for_sogoshotoku_etc_prev' => 1_000_000,
            'tax_zeigaku_shotoku_prev'     => 1_000_000,
            'used_by_income_deduction_prev'=> 0,
            'shotokuzei_zeigakukojo_seito_prev'  => 0,
            'shotokuzei_zeigakukojo_npo_prev'    => 500_000,
            'shotokuzei_zeigakukojo_koueki_prev' => 0,
        ];
        $out = $this->calc($p);
        // 40%枠で元本は 400,000 まで → 40%控除で 160,000 → 100円未満切捨て不要
        $this->assertSame(160_000, $out['tax_credit_shotoku_total_prev'] ?? null);
        $this->assertSame(1_000_000 - 160_000, $out['tax_sashihiki_shotoku_prev'] ?? null);
    }

    public function test_25_percent_cap_triggers_reallocation_to_seito(): void
    {
        // S は十分、T が小さいケース：T=200,000 → 25%枠=50,000（100円単位化で 50,000）
        // NPO=300,000, 公益=300,000, 政党等=300,000, I=0
        // まず 40%側で 25%枠を使い切る（50,000）→ 残りは 30%側へ
        $p = [
            'sum_for_sogoshotoku_etc_prev' => 20_000_000,
            'tax_zeigaku_shotoku_prev'     => 200_000,
            'used_by_income_deduction_prev'=> 0,
            'shotokuzei_zeigakukojo_seito_prev'  => 300_000,
            'shotokuzei_zeigakukojo_npo_prev'    => 300_000,
            'shotokuzei_zeigakukojo_koueki_prev' => 300_000,
        ];
        $out = $this->calc($p);
        // 40%側は 50,000 で頭打ち。30%側は足切り2,000共有後に残り元本が入る想定だが
        // 本SUTは最大化で 40%→30%へ自動迂回済み。合計が 50,000 を超えていることを確認。
        $this->assertGreaterThan(50_000, $out['tax_credit_shotoku_total_prev'] ?? 0, '政党等への迂回で総額が25%上限を超えている（別枠）');
        $this->assertSame(
            ($out['tax_credit_shotoku_npo_prev'] ?? 0) + ($out['tax_credit_shotoku_koueki_prev'] ?? 0) <= 50_000,
            true,
            'NPO+公益の合計は 25%枠以下であるべき'
        );
    }

    public function test_flooring_to_100yen_each_category(): void
    {
        // 100円未満切捨て確認用：
        // 前提：足切り2,000円はすでに所得控除側で消費（I=2,000）しているため、税額控除側の足切り残=0。
        // NPO元本=251 → 40%控除=100.4 → 100へ（カテゴリごとに100円未満切捨て）
        $p = [
            'sum_for_sogoshotoku_etc_prev' => 1_000_000,
            'tax_zeigaku_shotoku_prev'     => 1_000_000,
            'used_by_income_deduction_prev'=> 2_000,
            'shotokuzei_zeigakukojo_seito_prev'  => 0,
            'shotokuzei_zeigakukojo_npo_prev'    => 251,
            'shotokuzei_zeigakukojo_koueki_prev' => 0,
        ];
        $out = $this->calc($p);
        $this->assertSame(100, $out['tax_credit_shotoku_total_prev'] ?? null);
    }
}
