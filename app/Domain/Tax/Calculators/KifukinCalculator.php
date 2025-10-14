<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class KifukinCalculator implements ProvidesKeys
{
    public const ID = 'kojo.kifukin';
    public const ORDER = 2000;
    public const ANCHOR = 'deductions';
    public const BEFORE = [];
    public const AFTER = [];

    /** @var string[] */
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

    /** @var string[] */
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

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $settings = is_array($ctx['syori_settings'] ?? null) ? $ctx['syori_settings'] : [];

        $updates = array_fill_keys(self::provides(), 0);

        foreach (['prev', 'curr'] as $period) {
            $key = sprintf('one_stop_flag_%s', $period);
            $oneStop = $this->n($settings[$key] ?? 0) === 1;

            if ($oneStop) {
                $updates[sprintf('shotokuzei_kojo_kifukin_%s', $period)] = 0;
                continue;
            }

            $totalIncome = $this->sumTotalIncome($payload, $period);
            $baseDonations = $this->sumByKeys($payload, self::DONATION_BASE_KEYS, $period);
            $furusato = $this->n($payload[sprintf('shotokuzei_shotokukojo_furusato_%s', $period)] ?? 0);
            $donationSum = $baseDonations + $furusato;

            if ($donationSum < 2000) {
                $updates[sprintf('shotokuzei_kojo_kifukin_%s', $period)] = 0;
                continue;
            }

            $incomeCap = intdiv(max($totalIncome, 0) * 4, 10);
            $allowedBase = min($baseDonations, $incomeCap);
            $deduction = $allowedBase + $furusato - 2000;

            $updates[sprintf('shotokuzei_kojo_kifukin_%s', $period)] = max(0, $deduction);
        }

        return array_replace($payload, $updates);
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

    /**
     * @param  string[]  $keys
     */
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