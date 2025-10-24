<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class TaxBaseMirrorCalculator implements ProvidesKeys
{
    public const ID = 'tax.base.mirror';
    public const ORDER = 5050;
    public const ANCHOR = 'tax';
    public const BEFORE = [
        ShotokuTaxCalculator::ID,
        JuminTaxCalculator::ID,
    ];
    public const AFTER = [
        JuminzeiKifukinCalculator::ID,
    ];

    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [];

        foreach (self::PERIODS as $period) {
            $keys[] = sprintf('tsusanmae_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('tsusanmae_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('tsusanmae_ichiji_%s', $period);

            $keys[] = sprintf('shotoku_keijo_%s', $period);
            $keys[] = sprintf('shotoku_joto_tanki_%s', $period);
            $keys[] = sprintf('shotoku_joto_choki_%s', $period);
            $keys[] = sprintf('shotoku_ichiji_%s', $period);
            $keys[] = sprintf('shotoku_sanrin_%s', $period);
            $keys[] = sprintf('shotoku_taishoku_%s', $period);

            $keys[] = sprintf('shotoku_joto_ichiji_shotoku_%s', $period);
            $keys[] = sprintf('shotoku_joto_ichiji_jumin_%s', $period);

            $keys[] = sprintf('tax_kazeishotoku_shotoku_%s', $period);
            $keys[] = sprintf('tax_kazeishotoku_jumin_%s', $period);
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $settings = is_array($ctx['syori_settings'] ?? null) ? $ctx['syori_settings'] : [];
        $updates = array_fill_keys(self::provides(), 0);

        foreach (self::PERIODS as $period) {
            $isSeparated = $this->isSeparated($settings, $period);

            $afterShort = $this->n($payload[sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $period)] ?? null);
            $afterLong = $this->n($payload[sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period)] ?? null);
            $afterOneTime = $this->n($payload[sprintf('after_joto_ichiji_tousan_ichiji_%s', $period)] ?? null);

            $updates[sprintf('tsusanmae_joto_tanki_sogo_%s', $period)] = $this->nonNegative($afterShort);
            $updates[sprintf('tsusanmae_joto_choki_sogo_%s', $period)] = $this->nonNegative($afterLong);
            $updates[sprintf('tsusanmae_ichiji_%s', $period)] = $this->nonNegative($afterOneTime);

            $shotokuKeijo = $this->nonNegative($this->n($payload[sprintf('after_3jitsusan_keijo_%s', $period)] ?? null));
            $shotokuShort = $this->nonNegative($this->n($payload[sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period)] ?? null));
            $shotokuLong = $this->nonNegative($this->n($payload[sprintf('after_3jitsusan_joto_choki_sogo_%s', $period)] ?? null));
            $shotokuIchiji = $this->nonNegative($this->n($payload[sprintf('after_3jitsusan_ichiji_%s', $period)] ?? null));
            $shotokuSanrin = $this->nonNegative($this->n($payload[sprintf('after_3jitsusan_sanrin_%s', $period)] ?? null));
            $shotokuTaishoku = $this->nonNegative($this->n($payload[sprintf('after_3jitsusan_taishoku_%s', $period)] ?? null));

            $updates[sprintf('shotoku_keijo_%s', $period)] = $shotokuKeijo;
            $updates[sprintf('shotoku_joto_tanki_%s', $period)] = $shotokuShort;
            $updates[sprintf('shotoku_joto_choki_%s', $period)] = $shotokuLong;
            $updates[sprintf('shotoku_ichiji_%s', $period)] = $shotokuIchiji;
            $updates[sprintf('shotoku_sanrin_%s', $period)] = $shotokuSanrin;
            $updates[sprintf('shotoku_taishoku_%s', $period)] = $shotokuTaishoku;

            $sumSource = array_replace($payload, $updates);

            $sumShotokuShort = $this->nonNegative($this->n($sumSource[sprintf('shotoku_joto_tanki_%s', $period)] ?? null));
            $sumShotokuLong = $this->nonNegative($this->n(
                $sumSource[sprintf('shotoku_joto_choki_sogo_%s', $period)]
                    ?? $sumSource[sprintf('shotoku_joto_choki_%s', $period)]
                    ?? null
            ));
            $sumShotokuIchiji = $this->nonNegative($this->n($sumSource[sprintf('shotoku_ichiji_%s', $period)] ?? null));

            $sumJotoIchiji = $this->nonNegative($sumShotokuShort + $sumShotokuLong + $sumShotokuIchiji);
            $updates[sprintf('shotoku_joto_ichiji_shotoku_%s', $period)] = $sumJotoIchiji;
            $updates[sprintf('shotoku_joto_ichiji_jumin_%s', $period)] = $sumJotoIchiji;

            $kojoShotoku = $this->nonNegative($this->n($payload[sprintf('kojo_gokei_shotoku_%s', $period)] ?? null));

            if ($isSeparated) {
                $bunriBase = $this->nonNegative($this->n($payload[sprintf('bunri_kazeishotoku_sogo_shotoku_%s', $period)] ?? null));
                $updates[sprintf('tax_kazeishotoku_shotoku_%s', $period)] = $this->floorToThousands($bunriBase);

                $kazeiSogo = $this->nonNegative($this->n($payload[sprintf('kazeisoushotoku_%s', $period)] ?? null));
                $updates[sprintf('tax_kazeishotoku_jumin_%s', $period)] = $this->floorToThousands($kazeiSogo);
            } else {
                $calcBase = $this->nonNegative($shotokuKeijo + $shotokuShort + $shotokuLong + $shotokuIchiji - $kojoShotoku);
                $rounded = $this->floorToThousands($calcBase);
                $updates[sprintf('tax_kazeishotoku_shotoku_%s', $period)] = $rounded;
                $updates[sprintf('tax_kazeishotoku_jumin_%s', $period)] = $rounded;
            }
        }

        return array_replace($payload, $updates);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function isSeparated(array $settings, string $period): bool
    {
        $flag = $settings[sprintf('bunri_flag_%s', $period)] ?? $settings['bunri_flag'] ?? 0;

        return (int) $flag === 1;
    }

    private function n(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        if (is_numeric($value)) {
            return (int) floor((float) $value);
        }

        return 0;
    }

    private function nonNegative(int $value): int
    {
        return $value > 0 ? $value : 0;
    }

    private function floorToThousands(int $value): int
    {
        if ($value <= 0) {
            return 0;
        }

        return (int) (floor($value / 1000) * 1000);
    }
}