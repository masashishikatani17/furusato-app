<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;
use Illuminate\Support\Facades\Log;

/**
 * v1: 現行 TaxBaseMirrorCalculator が内部で求めている
 * 「税目=所得税の課税所得（総合課税分）」と完全同値の値を
 * taxable_sogo_shotoku_{prev|curr} として出力する。
 *
 * 丸め規約: 0 下限 → 千円未満切捨て（ここで統一）
 */
class CommonTaxableBaseCalculator implements ProvidesKeys
{
    public const ID     = 'common.taxable.base';
    public const ORDER  = 5050; // KojoAggregation 後, Shotoku/Jumin 税額計算より前
    public const BEFORE = [
        ShotokuTaxCalculator::ID,
        JuminTaxCalculator::ID,
        TokureiRateCalculator::ID,
    ];
    public const AFTER  = [
        KojoAggregationCalculator::ID,
        TaxBaseMirrorCalculator::ID, // 監視比較用（同値を確認）
    ];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public static function provides(): array
    {
        return [
            'taxable_sogo_shotoku_prev',
            'taxable_sogo_shotoku_curr',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $ctx
     * @return array<string,mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $settings = is_array($ctx['syori_settings'] ?? null) ? $ctx['syori_settings'] : [];

        foreach (self::PERIODS as $period) {
            $isSeparated = $this->isSeparated($settings, $period);

            if ($isSeparated) {
                // 分離 ON: TaxBaseMirror と同じ規約
                $base = $this->n($payload["bunri_kazeishotoku_sogo_shotoku_{$period}"] ?? null);
                $taxable = $this->floorToThousands(max(0, $base));
            } else {
                // 分離 OFF: 総合 = 経常+短期+長期+max(0,一時) - 控除合計
                $sum = $this->n($payload["shotoku_keijo_{$period}"] ?? null)
                     + $this->n($payload["shotoku_joto_tanki_{$period}"] ?? null)
                     + $this->n($payload["shotoku_joto_choki_sogo_{$period}"] ?? null)
                     + max(0, $this->n($payload["shotoku_ichiji_{$period}"] ?? null));
                $kojo = $this->n($payload["kojo_gokei_shotoku_{$period}"] ?? null);
                $taxable = $this->floorToThousands(max(0, $sum - $kojo));
            }

            $payload["taxable_sogo_shotoku_{$period}"] = $taxable;

            // v1: 監視（debug時のみ）— 現行 tax_kazeishotoku_shotoku_* と一致するはず
            if (config('app.debug')) {
                $legacy = $this->n($payload["tax_kazeishotoku_shotoku_{$period}"] ?? null);
                $delta  = $taxable - $legacy;
                if ($delta !== 0) {
                    Log::warning("[common.taxable.base] Δ(taxable_sogo_shotoku_{$period} - legacy)={$delta}");
                }
            }
        }

        return $payload;
    }

    private function isSeparated(array $settings, string $period): bool
    {
        $flag = $settings["bunri_flag_{$period}"] ?? ($settings['bunri_flag'] ?? 0);
        return (int)$flag === 1;
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int)floor((float)$v) : 0;
    }

    private function floorToThousands(int $v): int
    {
        if ($v <= 0) return 0;
        return (int)(floor($v / 1000) * 1000);
    }
}