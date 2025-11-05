<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;
use Illuminate\Support\Facades\Log;

class CommonTaxableBaseCalculator implements ProvidesKeys
{
    public const ID = 'common.taxable.base';
    public const ORDER = 5050;
    public const BEFORE = [
        ShotokuTaxCalculator::ID,
        JuminTaxCalculator::ID,
        TokureiRateCalculator::ID,
    ];
    public const AFTER = [
        KojoAggregationCalculator::ID,
    ];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int, string>
     */
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
        foreach (self::PERIODS as $period) {
            // 現行 TaxBaseMirror が採っている「総所得金額等」相当の基礎合計
            $sum = 0;
            $sum += $this->n($payload[sprintf('shotoku_keijo_%s', $period)] ?? null);
            $sum += $this->n($payload[sprintf('shotoku_joto_tanki_%s', $period)] ?? null);
            $sum += $this->n($payload[sprintf('shotoku_joto_choki_sogo_%s', $period)] ?? null);
            $sum += max(0, $this->n($payload[sprintf('shotoku_ichiji_%s', $period)] ?? null));

            // 控除合計（所得税側）
            $kojo = $this->n($payload[sprintf('kojo_gokei_shotoku_%s', $period)] ?? null);

            // 0下限→千円未満切捨て
            $taxable = $this->floorToThousands(max(0, $sum - $kojo));
            $payload[sprintf('taxable_sogo_shotoku_%s', $period)] = $taxable;

            // Δログ：既存 tax_kazeishotoku_shotoku_*（あれば）と一致を確認
            if (config('app.debug')) {
                $legacyKey = sprintf('tax_kazeishotoku_shotoku_%s', $period);
                $legacy = $this->intOrNull($payload[$legacyKey] ?? null);
                if ($legacy !== null && $legacy !== $taxable) {
                    Log::warning(sprintf('[common.taxable.base] Δ(taxable_sogo_shotoku_%s)=%d (legacy=%d new=%d)',
                        $period, $taxable - $legacy, $legacy, $taxable));
                }
            }
        }

        return $payload;
    }

    private function floorToThousands(int $value): int
    {
        if ($value <= 0) return 0;
        return (int) (floor($value / 1000) * 1000);
    }

    private function n(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        if (is_string($value)) $value = str_replace([',',' '], '', $value);
        return is_numeric($value) ? (int) floor((float) $value) : 0;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (is_string($value)) {
            $value = str_replace([',',' '], '', $value);
            if ($value === '') return null;
        }
        return is_numeric($value) ? (int) floor((float) $value) : null;
    }
}