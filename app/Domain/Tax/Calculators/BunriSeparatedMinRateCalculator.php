<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class BunriSeparatedMinRateCalculator implements ProvidesKeys
{
    public const ID = 'bunri.minrate';
    public const ORDER = 7100;
    public const ANCHOR = 'credits';
    public const BEFORE = [];
    public const AFTER = [TokureiRateCalculator::ID];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    /** @var string[] */
    private const OTHER_BASE_KEYS = [
        'bunri_kazeishotoku_choki',
        'bunri_kazeishotoku_haito',
        'bunri_kazeishotoku_sakimono',
        'bunri_kazeishotoku_joto',
    ];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        return [
            'tokurei_rate_bunri_min_prev',
            'tokurei_rate_bunri_min_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        unset($ctx);

        $updates = array_fill_keys(self::provides(), null);

        foreach (self::PERIODS as $period) {
            $rate = null;

            $shortAmount = $this->floorToThousands(
                $this->separatedIncomeAmount($payload, 'bunri_kazeishotoku_tanki', $period)
            );

            if ($shortAmount > 0) {
                $rate = $this->roundPercent(59.37);
            } else {
                foreach (self::OTHER_BASE_KEYS as $base) {
                    $amount = $this->floorToThousands(
                        $this->separatedIncomeAmount($payload, $base, $period)
                    );

                    if ($amount > 0) {
                        $rate = $this->roundPercent(74.685);
                        break;
                    }
                }
            }

            $updates[sprintf('tokurei_rate_bunri_min_%s', $period)] = $rate;
        }

        return array_replace($payload, $updates);
    }

    private function separatedIncomeAmount(array $payload, string $baseKey, string $period): int
    {
        $juminKey = sprintf('%s_jumin_%s', $baseKey, $period);
        $shotokuKey = sprintf('%s_shotoku_%s', $baseKey, $period);

        $jumin = $this->n($payload[$juminKey] ?? null);
        if ($jumin > 0) {
            return $jumin;
        }

        return max(0, $this->n($payload[$shotokuKey] ?? null));
    }

    private function floorToThousands(int $value): int
    {
        if ($value <= 0) {
            return 0;
        }

        return $value - ($value % 1000);
    }

    private function n(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        return is_numeric($value) ? (int) floor((float) $value) : 0;
    }

    private function roundPercent(float $value): float
    {
        return round($value, 3);
    }
}