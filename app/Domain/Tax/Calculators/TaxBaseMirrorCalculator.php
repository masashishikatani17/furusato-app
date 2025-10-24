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
            $keys[] = sprintf('shotoku_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('shotoku_ichiji_%s', $period);
            $keys[] = sprintf('shotoku_sanrin_%s', $period);
            $keys[] = sprintf('shotoku_taishoku_%s', $period);

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

            $tsusanmaeShort = $this->n($payload[sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $period)] ?? null);
            $tsusanmaeLong = $this->n($payload[sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period)] ?? null);
            $tsusanmaeIchiji = $this->n($payload[sprintf('after_joto_ichiji_tousan_ichiji_%s', $period)] ?? null);

            $updates[sprintf('tsusanmae_joto_tanki_sogo_%s', $period)] = $tsusanmaeShort;
            $updates[sprintf('tsusanmae_joto_choki_sogo_%s', $period)] = $tsusanmaeLong;
            $updates[sprintf('tsusanmae_ichiji_%s', $period)] = $tsusanmaeIchiji;

            $shotokuKeijo = $this->n($payload[sprintf('shotoku_keijo_%s', $period)] ?? $payload[sprintf('after_3jitsusan_keijo_%s', $period)] ?? null);
            $shotokuShort = $this->n($payload[sprintf('shotoku_joto_tanki_%s', $period)] ?? $payload[sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period)] ?? null);
            $shotokuLong = $this->n(
                $payload[sprintf('shotoku_joto_choki_sogo_%s', $period)]
                ?? $payload[sprintf('shotoku_joto_choki_%s', $period)]
                ?? $payload[sprintf('after_3jitsusan_joto_choki_sogo_%s', $period)]
                ?? $payload[sprintf('after_3jitsusan_joto_choki_%s', $period)]
                ?? null
            );
            $shotokuIchiji = $this->n($payload[sprintf('shotoku_ichiji_%s', $period)] ?? $payload[sprintf('after_3jitsusan_ichiji_%s', $period)] ?? null);
            $shotokuSanrin = $this->n($payload[sprintf('shotoku_sanrin_%s', $period)] ?? $payload[sprintf('after_3jitsusan_sanrin_%s', $period)] ?? null);
            $shotokuTaishoku = $this->n($payload[sprintf('shotoku_taishoku_%s', $period)] ?? $payload[sprintf('after_3jitsusan_taishoku_%s', $period)] ?? null);

            $updates[sprintf('shotoku_keijo_%s', $period)] = $shotokuKeijo;
            $updates[sprintf('shotoku_joto_tanki_%s', $period)] = $shotokuShort;
            $updates[sprintf('shotoku_joto_choki_sogo_%s', $period)] = $shotokuLong;
            $updates[sprintf('shotoku_ichiji_%s', $period)] = $shotokuIchiji;
            $updates[sprintf('shotoku_sanrin_%s', $period)] = $shotokuSanrin;
            $updates[sprintf('shotoku_taishoku_%s', $period)] = $shotokuTaishoku;

            $sumJotoIchiji = $shotokuShort + $shotokuLong + $shotokuIchiji;
            $updates[sprintf('shotoku_joto_ichiji_shotoku_%s', $period)] = $sumJotoIchiji;
            $updates[sprintf('shotoku_joto_ichiji_jumin_%s', $period)] = $sumJotoIchiji;

            $sumS = $shotokuKeijo + $shotokuShort + $shotokuLong + $shotokuIchiji;
            $kojoShotoku = $this->n($payload[sprintf('kojo_gokei_shotoku_%s', $period)] ?? null);
            $kojoJumin = $this->n($payload[sprintf('kojo_gokei_jumin_%s', $period)] ?? null);

            $taxShotoku = $this->floorToThousands(max(0, $sumS - $kojoShotoku));
            $taxJumin = $this->floorToThousands(max(0, $sumS - $kojoJumin));

            $bunriSogoShotoku = $sumS;
            $bunriSogoJumin = $sumS;
            $bunriSashihikiShotoku = min($kojoShotoku, $bunriSogoShotoku);
            $bunriSashihikiJumin = min($kojoJumin, $bunriSogoJumin);
            $bunriKazeishotokuShotoku = $this->floorToThousands(max(0, $bunriSogoShotoku - $bunriSashihikiShotoku));
            $bunriKazeishotokuJumin = $this->floorToThousands(max(0, $bunriSogoJumin - $bunriSashihikiJumin));

            if ($isSeparated) {
                $taxShotoku = $bunriKazeishotokuShotoku;
                $taxJumin = $bunriKazeishotokuJumin;
            } else {
                $taxJumin = $bunriKazeishotokuJumin;
            }

            $updates[sprintf('tax_kazeishotoku_shotoku_%s', $period)] = $taxShotoku;
            $updates[sprintf('tax_kazeishotoku_jumin_%s', $period)] = $taxJumin;

            $updates[sprintf('bunri_sogo_gokeigaku_shotoku_%s', $period)] = $bunriSogoShotoku;
            $updates[sprintf('bunri_sogo_gokeigaku_jumin_%s', $period)] = $bunriSogoJumin;
            $updates[sprintf('bunri_sashihiki_gokei_shotoku_%s', $period)] = $bunriSashihikiShotoku;
            $updates[sprintf('bunri_sashihiki_gokei_jumin_%s', $period)] = $bunriSashihikiJumin;
            $updates[sprintf('bunri_kazeishotoku_sogo_shotoku_%s', $period)] = $bunriKazeishotokuShotoku;
            $updates[sprintf('bunri_kazeishotoku_sogo_jumin_%s', $period)] = $bunriKazeishotokuJumin;

            $after3Sanrin = $this->n($payload[sprintf('after_3jitsusan_sanrin_%s', $period)] ?? null);
            $tokureiKojoSanrin = max(0, $after3Sanrin - $shotokuSanrin);
            $updates[sprintf('tokurei_kojo_sanrin_%s', $period)] = $tokureiKojoSanrin;

            $after2Taishoku = $this->n($payload[sprintf('after_2jitsusan_taishoku_%s', $period)] ?? null);
            $updates[sprintf('after_2jitsusan_taishoku_%s', $period)] = $after2Taishoku;
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
}