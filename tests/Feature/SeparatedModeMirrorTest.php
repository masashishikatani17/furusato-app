<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Tax\Calculators\TaxBaseMirrorCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SeparatedModeMirrorTest extends TestCase
{
    #[Test]
    public function it_mirrors_common_sums_and_bunri_blocks_when_present(): void
    {
        $calculator = new TaxBaseMirrorCalculator();

        $payload = [
            // 入力：素の shotoku_* 群
            'shotoku_keijo_curr' => 100_000,
            'shotoku_joto_tanki_sogo_curr' => 200_000,
            'shotoku_joto_choki_sogo_curr' => 300_000,
            'shotoku_ichiji_curr' => 400_000,
            'shotoku_sanrin_curr' => 50_000,
            'shotoku_taishoku_curr' => 60_000,
            // 入力：共通合計（CommonSums）
            'sum_for_gokeishotoku_curr' => 1_110_000,
            'sum_for_sogoshotoku_curr' => 1_050_000,
            'sum_for_ab_total_curr'     => 1_110_000,
            // 入力：bunri_*（上流で確定済みとみなし、そのままミラーされるべき）
            'bunri_sogo_gokeigaku_shotoku_curr' => 650_000,
            'bunri_sogo_gokeigaku_jumin_curr'   => 650_000,
            'bunri_sashihiki_gokei_shotoku_curr'=> 16_000,
            'bunri_sashihiki_gokei_jumin_curr'  => 26_000,
            'bunri_kazeishotoku_sogo_shotoku_curr' => 634_000,
            'bunri_kazeishotoku_sogo_jumin_curr'   => 624_000,
        ];

        $result = $calculator->compute($payload, []);

        // 現仕様：第一表合計は sum_for_sogoshotoku_* を採用
        $this->assertSame(1_050_000, $result['shotoku_gokei_curr']);
        $this->assertSame(1_050_000, $result['shotoku_joto_ichiji_shotoku_curr']);
        $this->assertSame(1_050_000, $result['shotoku_joto_ichiji_jumin_curr']);
        // bunri_* はそのままミラー（再計算なし）
        $this->assertSame(650_000, $result['bunri_sogo_gokeigaku_shotoku_curr']);
        $this->assertSame(650_000, $result['bunri_sogo_gokeigaku_jumin_curr']);
        $this->assertSame(16_000, $result['bunri_sashihiki_gokei_shotoku_curr']);
        $this->assertSame(26_000, $result['bunri_sashihiki_gokei_jumin_curr']);
        $this->assertSame(634_000, $result['bunri_kazeishotoku_sogo_shotoku_curr']);
        $this->assertSame(624_000, $result['bunri_kazeishotoku_sogo_jumin_curr']);
    }

    #[Test]
    public function it_does_not_fallback_when_keys_are_absent(): void
    {
        $calculator = new TaxBaseMirrorCalculator();

        $payload = [
            // 新仕様: A+B を渡さないと shotoku_gokei_* はミラーされない
            // （sum_for_gokeishotoku_* だけでは shotoku_gokei_* を起こさない）
            'sum_for_sogoshotoku_prev' => 500_000,
        ];

        $result = $calculator->compute($payload, []);

        // 総合合計は joto_ichiji_* へミラー
        $this->assertSame(500_000, $result['shotoku_joto_ichiji_shotoku_prev']);
        $this->assertSame(500_000, $result['shotoku_joto_ichiji_jumin_prev']);
        // 現仕様：sum_for_sogoshotoku_* の値は joto_ichiji_* へミラーしつつ、
        // shotoku_gokei_* も sum_for_sogoshotoku_* を採用する
        $this->assertSame(500_000, $result['shotoku_gokei_prev']);
        // 未供給の bunri_* / tax_* は生成しない（フォールバック禁止）
        $this->assertArrayNotHasKey('bunri_sogo_gokeigaku_shotoku_prev', $result);
        $this->assertArrayNotHasKey('tax_kazeishotoku_shotoku_prev', $result);
        $this->assertArrayNotHasKey('tax_kazeishotoku_jumin_prev', $result);
    }
}