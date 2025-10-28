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
            $keys[] = sprintf('shotoku_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('shotoku_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('shotoku_ichiji_%s', $period);
            $keys[] = sprintf('shotoku_sanrin_%s', $period);
            $keys[] = sprintf('shotoku_taishoku_%s', $period);
            $keys[] = sprintf('shotoku_gokei_%s', $period);

            $keys[] = sprintf('shotoku_joto_ichiji_shotoku_%s', $period);
            $keys[] = sprintf('shotoku_joto_ichiji_jumin_%s', $period);

            $keys[] = sprintf('tax_kazeishotoku_shotoku_%s', $period);
            $keys[] = sprintf('tax_kazeishotoku_jumin_%s', $period);

            $keys[] = sprintf('bunri_sogo_gokeigaku_shotoku_%s', $period);
            $keys[] = sprintf('bunri_sogo_gokeigaku_jumin_%s', $period);
            $keys[] = sprintf('bunri_sashihiki_gokei_shotoku_%s', $period);
            $keys[] = sprintf('bunri_sashihiki_gokei_jumin_%s', $period);
            $keys[] = sprintf('bunri_kazeishotoku_sogo_shotoku_%s', $period);
            $keys[] = sprintf('bunri_kazeishotoku_sogo_jumin_%s', $period);

            $keys[] = sprintf('tokurei_kojo_sanrin_%s', $period);

            $keys[] = sprintf('after_2jitsusan_taishoku_%s', $period);
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

            $tsusanmaeShortValue = $this->value(
                $payload,
                sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $period),
                sprintf('after_joto_ichiji_tousan_joto_tanki_sogo_%s', $period),
                sprintf('tsusanmae_joto_tanki_sogo_%s', $period)
            );
            $tsusanmaeLongValue = $this->value(
                $payload,
                sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period),
                sprintf('after_joto_ichiji_tousan_joto_choki_%s', $period),
                sprintf('tsusanmae_joto_choki_sogo_%s', $period)
            );
            $tsusanmaeIchijiValue = $this->value(
                $payload,
                sprintf('after_joto_ichiji_tousan_ichiji_%s', $period),
                sprintf('tsusanmae_ichiji_%s', $period)
            );

            $updates[sprintf('tsusanmae_joto_tanki_sogo_%s', $period)] = $tsusanmaeShortValue;
            $updates[sprintf('tsusanmae_joto_choki_sogo_%s', $period)] = $tsusanmaeLongValue;
            $updates[sprintf('tsusanmae_ichiji_%s', $period)] = $tsusanmaeIchijiValue;

            $shotokuKeijoValue = $this->value(
                $payload,
                sprintf('shotoku_keijo_%s', $period),
                sprintf('after_3jitsusan_keijo_%s', $period)
            );
            $shotokuShortValue = $this->value(
                $payload,
                sprintf('shotoku_joto_tanki_sogo_%s', $period),
                sprintf('shotoku_joto_tanki_%s', $period),
                sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period),
                sprintf('after_3jitsusan_tanki_sogo_%s', $period),
                sprintf('after_3jitsusan_joto_tanki_%s', $period),
                sprintf('after_3jitsusan_tanki_%s', $period)
            );
            $shotokuLongValue = $this->value(
                $payload,
                sprintf('shotoku_joto_choki_sogo_%s', $period),
                sprintf('shotoku_joto_choki_%s', $period),
                sprintf('after_3jitsusan_joto_choki_sogo_%s', $period),
                sprintf('after_3jitsusan_choki_sogo_%s', $period),
                sprintf('after_3jitsusan_joto_choki_%s', $period),
                sprintf('after_3jitsusan_choki_%s', $period)
            );
            $shotokuIchijiValue = $this->value(
                $payload,
                sprintf('shotoku_ichiji_%s', $period),
                sprintf('after_3jitsusan_ichiji_%s', $period)
            );
            $shotokuSanrinValue = $this->value(
                $payload,
                sprintf('shotoku_sanrin_%s', $period),
                sprintf('after_3jitsusan_sanrin_%s', $period)
            );
            $shotokuTaishokuValue = $this->value(
                $payload,
                sprintf('shotoku_taishoku_%s', $period),
                sprintf('after_3jitsusan_taishoku_%s', $period)
            );

            $updates[sprintf('shotoku_keijo_%s', $period)] = $shotokuKeijoValue;
            $updates[sprintf('shotoku_joto_tanki_sogo_%s', $period)] = $shotokuShortValue;
            $updates[sprintf('shotoku_joto_tanki_%s', $period)] = $shotokuShortValue;
            $updates[sprintf('shotoku_joto_choki_sogo_%s', $period)] = $shotokuLongValue;
            $updates[sprintf('shotoku_ichiji_%s', $period)] = $shotokuIchijiValue;
            $updates[sprintf('shotoku_sanrin_%s', $period)] = $shotokuSanrinValue;
            $updates[sprintf('shotoku_taishoku_%s', $period)] = $shotokuTaishokuValue;

            $shotokuKeijo = $this->n($shotokuKeijoValue);
            $shotokuShort = $this->n($shotokuShortValue);
            $shotokuLong = $this->n($shotokuLongValue);
            $shotokuIchiji = $this->n($shotokuIchijiValue);
            $shotokuSanrin = $this->n($shotokuSanrinValue);
            $shotokuTaishoku = $this->n($shotokuTaishokuValue);

            $sumJotoIchiji = $shotokuShort + $shotokuLong + $shotokuIchiji;
            $updates[sprintf('shotoku_joto_ichiji_shotoku_%s', $period)] = $sumJotoIchiji;
            $updates[sprintf('shotoku_joto_ichiji_jumin_%s', $period)] = $sumJotoIchiji;

            $shotokuGokei = $shotokuKeijo + $shotokuShort + $shotokuLong + $shotokuIchiji + $shotokuSanrin + $shotokuTaishoku;
            $updates[sprintf('shotoku_gokei_%s', $period)] = $shotokuGokei;

            $kojoShotoku = $this->n($payload[sprintf('kojo_gokei_shotoku_%s', $period)] ?? null);
            $kojoJumin = $this->n($payload[sprintf('kojo_gokei_jumin_%s', $period)] ?? null);

            $taxShotoku = 0;
            $taxJumin = 0;
            $bunriSogoShotoku = 0;
            $bunriSogoJumin = 0;
            $bunriSashihikiShotoku = 0;
            $bunriSashihikiJumin = 0;
            $bunriKazeishotokuShotoku = 0;
            $bunriKazeishotokuJumin = 0;

            $sumComprehensive = $shotokuKeijo + $shotokuShort + $shotokuLong + max(0, $shotokuIchiji);

            if (! $isSeparated) {
                $taxShotoku = $this->floorToThousands(max(0, $sumComprehensive - $kojoShotoku));
                $taxJumin = $this->floorToThousands(max(0, $sumComprehensive - $kojoJumin));
            } else {
                $after3Short = $this->n($this->value(
                    $payload,
                    sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period),
                    sprintf('after_3jitsusan_tanki_sogo_%s', $period),
                    sprintf('after_3jitsusan_joto_tanki_%s', $period),
                    sprintf('after_3jitsusan_tanki_%s', $period)
                ));
                $after3Long = $this->n($this->value(
                    $payload,
                    sprintf('after_3jitsusan_joto_choki_sogo_%s', $period),
                    sprintf('after_3jitsusan_choki_sogo_%s', $period),
                    sprintf('after_3jitsusan_joto_choki_%s', $period),
                    sprintf('after_3jitsusan_choki_%s', $period)
                ));
                $after3Ichiji = $this->n($this->value(
                    $payload,
                    sprintf('after_3jitsusan_ichiji_%s', $period)
                ));
                $after3Sanrin = $this->n($this->value(
                    $payload,
                    sprintf('after_3jitsusan_sanrin_%s', $period)
                ));
                $after3Taishoku = $this->n($this->value(
                    $payload,
                    sprintf('after_3jitsusan_taishoku_%s', $period)
                ));

                $bunriSogoShotoku = $after3Short + $after3Long + $after3Ichiji + $after3Sanrin + $after3Taishoku;
                $bunriSogoJumin = $bunriSogoShotoku;
                $bunriSashihikiShotoku = min($kojoShotoku, $bunriSogoShotoku);
                $bunriSashihikiJumin = min($kojoJumin, $bunriSogoJumin);
                $bunriKazeishotokuShotoku = $this->floorToThousands(max(0, $bunriSogoShotoku - $bunriSashihikiShotoku));
                $bunriKazeishotokuJumin = $this->floorToThousands(max(0, $bunriSogoJumin - $bunriSashihikiJumin));

                $taxJumin = $bunriKazeishotokuJumin;

                $taxShotoku = $this->floorToThousands(max(0, $sumComprehensive - $kojoShotoku));

                $updates[sprintf('bunri_sogo_gokeigaku_shotoku_%s', $period)] = $bunriSogoShotoku;
                $updates[sprintf('bunri_sogo_gokeigaku_jumin_%s', $period)] = $bunriSogoJumin;
                $updates[sprintf('bunri_sashihiki_gokei_shotoku_%s', $period)] = $bunriSashihikiShotoku;
                $updates[sprintf('bunri_sashihiki_gokei_jumin_%s', $period)] = $bunriSashihikiJumin;
                $updates[sprintf('bunri_kazeishotoku_sogo_shotoku_%s', $period)] = $bunriKazeishotokuShotoku;
                $updates[sprintf('bunri_kazeishotoku_sogo_jumin_%s', $period)] = $bunriKazeishotokuJumin;
            }

            $updates[sprintf('tax_kazeishotoku_shotoku_%s', $period)] = $taxShotoku;
            $updates[sprintf('tax_kazeishotoku_jumin_%s', $period)] = $taxJumin;

            $after3Sanrin = $this->n($this->value(
                $payload,
                sprintf('after_3jitsusan_sanrin_%s', $period)
            ));
            $tokureiKojoSanrin = max(0, $after3Sanrin - $shotokuSanrin);
            $updates[sprintf('tokurei_kojo_sanrin_%s', $period)] = $tokureiKojoSanrin;

            $after2TaishokuValue = $this->value(
                $payload,
                sprintf('after_2jitsusan_taishoku_%s', $period)
            );
            $updates[sprintf('after_2jitsusan_taishoku_%s', $period)] = $after2TaishokuValue;
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

    private function floorToThousands(int $value): int
    {
        if ($value <= 0) {
            return 0;
        }

        return (int) (floor($value / 1000) * 1000);
    }

    private function value(array $payload, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if ($value === null || $value === '') {
                continue;
            }

            return $value;
        }

        return 0;
    }
}