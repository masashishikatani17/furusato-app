<?php

namespace App\Services\Tax\Kojo;

use App\Services\Tax\Contracts\ProvidesKeys;

final class SeitotoKihukinTokubetsuService implements ProvidesKeys
{
    private const PERIODS = ['prev', 'curr'];

    private const TOTAL_INCOME_BASES = [
        'shotoku_gokei_shotoku',
        'bunri_tanki_ippan_shotoku',
        'bunri_tanki_keigen_shotoku',
        'bunri_choki_ippan_shotoku',
        'bunri_choki_tokutei_under_shotoku',
        'bunri_choki_tokutei_over_shotoku',
        'bunri_choki_keika_under_shotoku',
        'bunri_choki_keika_over_shotoku',
        'bunri_ippan_kabuteki_joto_shotoku',
        'bunri_jojo_kabuteki_joto_shotoku',
        'bunri_jojo_kabuteki_haito_shotoku',
        'bunri_sakimono_shotoku',
        'bunri_sanrin_shotoku',
        'bunri_taishoku_shotoku',
    ];

    private const KIFU_BASES = [
        'shotokuzei_shotokukojo_furusato',
        'shotokuzei_shotokukojo_kyodobokin_nisseki',
        'shotokuzei_shotokukojo_seito',
        'shotokuzei_shotokukojo_npo',
        'shotokuzei_shotokukojo_koueki',
        'shotokuzei_shotokukojo_kuni',
        'shotokuzei_shotokukojo_sonota',
    ];

    private const ZEIGAKUKOJO_BASES = [
        'shotokuzei_zeigakukojo_npo',
        'shotokuzei_zeigakukojo_koueki',
    ];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        return [
            'shotokuzei_zeigakukojo_seitoto_tokubetsu_prev',
            'shotokuzei_zeigakukojo_seitoto_tokubetsu_curr',
            'juminzei_zeigakukojo_seitoto_tokubetsu_prev',
            'juminzei_zeigakukojo_seitoto_tokubetsu_curr',
        ];
    }

    /**
     * @param array $payload
     * @return array{
     *     shotokuzei_zeigakukojo_seitoto_tokubetsu_prev:int,
     *     shotokuzei_zeigakukojo_seitoto_tokubetsu_curr:int,
     *     juminzei_zeigakukojo_seitoto_tokubetsu_prev:int,
     *     juminzei_zeigakukojo_seitoto_tokubetsu_curr:int,
     * }
     */
    public function compute(array $payload): array
    {
        $results = [];

        foreach (self::PERIODS as $period) {
            $results[sprintf('shotokuzei_zeigakukojo_seitoto_tokubetsu_%s', $period)] = $this->calculateShotokuzei($payload, $period);
            $results[sprintf('juminzei_zeigakukojo_seitoto_tokubetsu_%s', $period)] = 0;
        }

        return $results;
    }

    private function calculateShotokuzei(array $payload, string $period): int
    {
        $sumTotal = $this->sumPeriodValues($payload, self::TOTAL_INCOME_BASES, $period);
        $kifuSum = $this->sumPeriodValues($payload, self::KIFU_BASES, $period);
        $zeigakuKojoSum = $this->sumPeriodValues($payload, self::ZEIGAKUKOJO_BASES, $period);

        $taxAmount = $this->n($payload[sprintf('tax_zeigaku_shotoku_%s', $period)] ?? null);
        $taxLimit = $taxAmount * 0.25;
        $incomeLimit = $sumTotal * 0.4;

        $seito = $this->n($payload[sprintf('shotokuzei_shotokukojo_seito_%s', $period)] ?? null);
        $zeigakuNpo = $this->n($payload[sprintf('shotokuzei_zeigakukojo_npo_%s', $period)] ?? null);
        $zeigakuKoueki = $this->n($payload[sprintf('shotokuzei_zeigakukojo_koueki_%s', $period)] ?? null);

        $kyodo = $this->n($payload[sprintf('shotokuzei_shotokukojo_kyodobokin_nisseki_%s', $period)] ?? null);
        $npoShotokuKojo = $this->n($payload[sprintf('shotokuzei_shotokukojo_npo_%s', $period)] ?? null);
        $kouekiShotokuKojo = $this->n($payload[sprintf('shotokuzei_shotokukojo_koueki_%s', $period)] ?? null);
        $kuni = $this->n($payload[sprintf('shotokuzei_shotokukojo_kuni_%s', $period)] ?? null);
        $sonota = $this->n($payload[sprintf('shotokuzei_shotokukojo_sonota_%s', $period)] ?? null);
        $furusato = $this->n($payload[sprintf('shotokuzei_shotokukojo_furusato_%s', $period)] ?? null);

        $kifuAndZeigakuTotal = $kifuSum + $zeigakuKojoSum;

        $partABase = $this->max(
            0.0,
            $this->min(
                $seito,
                $this->max(0.0, $incomeLimit - $this->min($kifuAndZeigakuTotal, $incomeLimit))
            ) - $this->max(0.0, 2000 - $kifuAndZeigakuTotal)
        );
        $partA = $this->min($partABase * 0.3, $taxLimit);

        $shotokuGroupPlusKoueki = $kyodo + $seito + $npoShotokuKojo + $kouekiShotokuKojo + $kuni + $sonota + $furusato + $zeigakuKoueki;
        $partBBase = $this->max(
            0.0,
            $this->min(
                $zeigakuNpo,
                $this->max(0.0, $incomeLimit - $this->min($shotokuGroupPlusKoueki, $incomeLimit))
            ) - $this->max(0.0, 2000 - $shotokuGroupPlusKoueki)
        );
        $partB = $this->min($partBBase * 0.4, $taxLimit);

        $shotokuGroupPlusNpo = $kyodo + $seito + $npoShotokuKojo + $kouekiShotokuKojo + $kuni + $sonota + $furusato + $zeigakuNpo;
        $partCBase = $this->max(
            0.0,
            $this->min(
                $zeigakuKoueki,
                $this->max(0.0, $incomeLimit - $this->min($shotokuGroupPlusNpo, $incomeLimit))
            ) - $this->max(0.0, 2000 - $shotokuGroupPlusNpo)
        );
        $partC = $this->min($partCBase * 0.4, $this->max(0.0, $taxLimit - $partB));

        return $this->int($partA + $partB + $partC);
    }

    private function sumPeriodValues(array $payload, array $bases, string $period): float
    {
        $total = 0.0;
        foreach ($bases as $base) {
            $total += $this->n($payload[sprintf('%s_%s', $base, $period)] ?? null);
        }

        return $total;
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

    private function min(float $first, float $second): float
    {
        return $first < $second ? $first : $second;
    }

    private function max(float $first, float $second): float
    {
        return $first > $second ? $first : $second;
    }

    private function int(float $value): int
    {
        return (int) floor($this->max(0.0, $value));
    }
}