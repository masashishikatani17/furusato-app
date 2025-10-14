<?php

namespace App\Services\Tax\Kojo;

use App\Services\Tax\Contracts\ProvidesKeys;

final class KifukinShotokuKojoService implements ProvidesKeys
{
    private const TOTAL_KEYS = [
        'shotoku_gokei_shotoku',
        'bunri_shotoku_tanki_ippan_shotoku',
        'bunri_shotoku_tanki_keigen_shotoku',
        'bunri_shotoku_choki_ippan_shotoku',
        'bunri_shotoku_choki_tokutei_shotoku',
        'bunri_shotoku_choki_keika_shotoku',
        'bunri_shotoku_ippan_kabuteki_joto_shotoku',
        'bunri_shotoku_jojo_kabuteki_joto_shotoku',
        'bunri_shotoku_jojo_kabuteki_haito_shotoku',
        'bunri_shotoku_sakimono_shotoku',
        'bunri_shotoku_sanrin_shotoku',
        'bunri_shotoku_taishoku_shotoku',
    ];

    private const DONATION_BASE_KEYS = [
        'shotokuzei_shotokukojo_kyodobokin_nisseki',
        'shotokuzei_shotokukojo_seito',
        'shotokuzei_shotokukojo_npo',
        'shotokuzei_shotokukojo_koueki',
        'shotokuzei_shotokukojo_kuni',
        'shotokuzei_shotokukojo_sonota',
    ];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        return [
            'shotokuzei_kojo_kifukin_prev',
            'shotokuzei_kojo_kifukin_curr',
            'juminzei_kojo_kifukin_prev',
            'juminzei_kojo_kifukin_curr',
        ];
    }

    public function compute(array $payload, array $settings = []): array
    {
        $result = [
            'shotokuzei_kojo_kifukin_prev' => 0,
            'shotokuzei_kojo_kifukin_curr' => 0,
            'juminzei_kojo_kifukin_prev' => 0,
            'juminzei_kojo_kifukin_curr' => 0,
        ];

        foreach (['prev', 'curr'] as $period) {
            $key = sprintf('one_stop_flag_%s', $period);
            $oneStop = $this->n($settings[$key] ?? 0) === 1;

            if ($oneStop) {
                $result[sprintf('shotokuzei_kojo_kifukin_%s', $period)] = 0;
                continue;
            }

            $totalIncome = $this->sumTotalIncome($payload, $period);
            $baseDonations = $this->sumByKeys($payload, self::DONATION_BASE_KEYS, $period);
            $furusato = $this->n($payload[sprintf('shotokuzei_shotokukojo_furusato_%s', $period)] ?? 0);
            $donationSum = $baseDonations + $furusato;

            if ($donationSum < 2000) {
                $result[sprintf('shotokuzei_kojo_kifukin_%s', $period)] = 0;
                continue;
            }

            $incomeCap = intdiv(max($totalIncome, 0) * 4, 10);
            $allowedBase = min($baseDonations, $incomeCap);
            $deduction = $allowedBase + $furusato - 2000;

            $result[sprintf('shotokuzei_kojo_kifukin_%s', $period)] = max(0, $deduction);
        }

        return $result;
    }

    private function sumTotalIncome(array $payload, string $period): int
    {
        $sum = 0;
        foreach (self::TOTAL_KEYS as $key) {
            $field = sprintf('%s_%s', $key, $period);
            $sum += $this->n($payload[$field] ?? 0);
        }

        return $sum;
    }

    private function sumByKeys(array $payload, array $keys, string $period): int
    {
        $sum = 0;
        foreach ($keys as $key) {
            $field = sprintf('%s_%s', $key, $period);
            $sum += $this->n($payload[$field] ?? 0);
        }

        return $sum;
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
}