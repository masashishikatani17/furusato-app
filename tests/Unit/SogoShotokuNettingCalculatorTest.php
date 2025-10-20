<?php

namespace Tests\Unit;

use App\Domain\Tax\Calculators\SogoShotokuNettingCalculator;
use PHPUnit\Framework\TestCase;

class SogoShotokuNettingCalculatorTest extends TestCase
{
    /**
     * 表4（通算前）についてのドメイン制約：
     * 同一テーブル内で「プラス」と「マイナス」が混在しない（符号混在禁止）。
     * ※「短期と長期が同時にプラス」自体は否定されないため、ここでは混在のみ検証する。
     */
    private function assertNoMixedSigns(array $vals, string $label = ''): void
    {
        $hasPos = false; $hasNeg = false;
        foreach ($vals as $v) {
            if ($v > 0) $hasPos = true;
            if ($v < 0) $hasNeg = true;
        }
        $this->assertFalse($hasPos && $hasNeg, $label !== '' ? $label : 'values contain both positive and negative');
    }

    private function assertNotBothPositive(int $a, int $b, string $label): void
    {
        $this->assertFalse(($a > 0 && $b > 0), $label);
    }

    /**
     * すべてプラスのケース：通算前（＝joto-ichiji 内部通算後）は負の値を含まない（符号混在なし）
     * ・ここでは「表4（通算前）」が同時にプラスとマイナスを持たないことのみ検証（ドメイン制約）
     */
    public function test_all_positive_inputs_satisfy_domain_sign_rule_at_tsusanmae(): void
    {
        $calculator = new SogoShotokuNettingCalculator();

        $payload = [
            'sashihiki_joto_tanki_sogo_prev' => 300_000,
            'sashihiki_joto_choki_sogo_prev' => 100_000,
            'sashihiki_ichiji_prev' => 20_000,
        ];

        $result = $calculator->compute($payload, 'prev');

        $short  = (int)($result['tsusango_joto_tanki_prev'] ?? 0);
        $long   = (int)($result['tsusango_joto_choki_sogo_prev'] ?? 0);
        $ichiji = (int)($result['tsusango_ichiji_prev'] ?? 0);
        // ドメイン制約：同一テーブル内で「プラスとマイナスが混在」しない
        $this->assertNoMixedSigns([$short, $long, $ichiji], 'tsusanmae(prev) must not contain both positive and negative');
        // 一時は負にしない運用（clamp 前提）
        $this->assertGreaterThanOrEqual(0, $ichiji, 'Ichiji must be non-negative at tsusanmae(prev)');
    }

    /**
     * 短期のみ赤字・一時に黒字があるケース：
     * ・表4（通算前）は符号混在なし
     * ・内部通算後の残高（after_…）も符号混在なし
     */
    public function test_short_negative_and_ichiji_positive_respects_domain_sign_rule(): void
    {
        $calculator = new SogoShotokuNettingCalculator();

        $payload = [
            'sashihiki_joto_tanki_sogo_curr' => -200_000, // 短期のみ赤字
            'sashihiki_joto_choki_sogo_curr' => 0,
            'sashihiki_ichiji_curr' => 600_000,           // 一時に黒字
        ];

        $result = $calculator->compute($payload, 'curr');

        $short = (int)($result['tsusango_joto_tanki_curr'] ?? 0);
        $long  = (int)($result['tsusango_joto_choki_sogo_curr'] ?? 0);
        $ichiji = (int)($result['tsusango_ichiji_curr'] ?? 0);
        $this->assertNotBothPositive($short, $long, 'Short and Long cannot both be positive at tsusanmae(curr)');
        $this->assertGreaterThanOrEqual(0, $ichiji, 'Ichiji must be non-negative at tsusanmae(curr)');

        // 内部通算後（after_…）も同様の性質を満たす
        $afterShort = (int)($result['after_joto_ichiji_tousan_joto_tanki_curr'] ?? 0);
        $afterLong  = (int)($result['after_joto_ichiji_tousan_joto_choki_sogo_curr'] ?? 0);
        $afterIchiji = (int)($result['after_joto_ichiji_tousan_ichiji_curr'] ?? 0);
        $this->assertNotBothPositive($afterShort, $afterLong, 'Short and Long cannot both be positive after netting(curr)');
        $this->assertGreaterThanOrEqual(0, $afterIchiji, 'Ichiji must be non-negative after netting(curr)');
    }

    public function test_inputs_are_normalized_before_calculation(): void
    {
        $calculator = new SogoShotokuNettingCalculator();

        $payload = [
            'sashihiki_joto_tanki_sogo_prev' => '123.9',
            'sashihiki_joto_choki_sogo_prev' => '-23.1',
            'sashihiki_ichiji_prev' => 'not-numeric',
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame(123, $result['sashihiki_joto_tanki_sogo_prev']);
        $this->assertSame(-23, $result['sashihiki_joto_choki_sogo_prev']);
        $short = (int)($result['tsusango_joto_tanki_prev'] ?? 0);
        $long  = (int)($result['tsusango_joto_choki_sogo_prev'] ?? 0);
        $ichiji = (int)($result['tsusango_ichiji_prev'] ?? 0);
        $this->assertNotBothPositive($short, $long, 'Short and Long cannot both be positive at tsusanmae(prev)');
        $this->assertGreaterThanOrEqual(0, $ichiji, 'Ichiji must be non-negative at tsusanmae(prev)');
    }

    public function test_tsusango_ichiji_is_clamped_to_zero_when_negative(): void
    {
        $calculator = new SogoShotokuNettingCalculator();

        $payload = [
            'sashihiki_joto_tanki_sogo_prev' => 0,
            'sashihiki_joto_choki_sogo_prev' => 0,
            'sashihiki_ichiji_prev' => -200_000,
        ];

        $result = $calculator->compute($payload, 'prev');

        $this->assertSame(0, $result['tsusango_ichiji_prev']);
        $this->assertSame(0, $result['tokubetsukojo_ichiji_prev']);
        $this->assertSame(0, $result['after_joto_ichiji_tousan_ichiji_prev']);
    }
}