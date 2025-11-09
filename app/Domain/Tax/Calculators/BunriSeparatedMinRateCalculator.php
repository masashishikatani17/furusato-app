<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class BunriSeparatedMinRateCalculator implements ProvidesKeys
{
    public const ID = 'bunri.minrate';
    // 【制度順】フェーズD：分離に基づく最小率（Tokurei率決定後）
    public const ORDER = 5410;
    public const ANCHOR = 'credits';
    public const BEFORE = [];
    public const AFTER = [TokureiRateCalculator::ID];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    /** @var string[] */
    // tb_* SoTに移行：評価対象の分離科目（短期以外）
    private const OTHER_KINDS = ['choki', 'haito', 'sakimono', 'joto'];

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

            // 短期：tb_joto_tanki_shotoku_*
            $shortAmount = $this->floorToThousands($this->fromTb($payload, 'tanki', $period));

            if ($shortAmount > 0) {
                $rate = $this->roundPercent(59.37);
            } else {
                foreach (self::OTHER_KINDS as $kind) {
                    $amount = $this->floorToThousands($this->fromTb($payload, $kind, $period));

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

    /**
     * tb_* SoT から分離科目金額（所得税側）を取得
     * kind: tanki|choki|haito|sakimono|joto
     */
    private function fromTb(array $payload, string $kind, string $period): int
    {
        $key = function (string $name): string {
            return $name . '_shotoku_%s';
        };
        switch ($kind) {
            case 'tanki':
                return $this->n($payload[sprintf($key('tb_joto_tanki'), $period)] ?? null);
            case 'choki':
                return $this->n($payload[sprintf($key('tb_joto_choki'), $period)] ?? null);
            case 'haito':
                return $this->n($payload[sprintf($key('tb_jojo_kabuteki_haito'), $period)] ?? null);
            case 'sakimono':
                return $this->n($payload[sprintf($key('tb_sakimono'), $period)] ?? null);
            case 'joto':
                return
                    $this->n($payload[sprintf($key('tb_ippan_kabuteki_joto'), $period)] ?? null) +
                    $this->n($payload[sprintf($key('tb_jojo_kabuteki_joto'),  $period)] ?? null);
            default:
                return 0;
        }
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