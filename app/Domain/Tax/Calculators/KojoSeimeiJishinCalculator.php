<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class KojoSeimeiJishinCalculator implements ProvidesKeys
{
    public const ID = 'kojo.seimei_jishin';
    public const ORDER = 2050;
    public const ANCHOR = 'deductions';
    public const BEFORE = [];
    public const AFTER = [];

    private const SHOTOKU_SEIMEI_TOTAL_CAP = 120_000;
    private const JUMIN_SEIMEI_TOTAL_CAP = 70_000;
    private const SHOTOKU_SEIMEI_CATEGORY_CAP = 40_000;
    private const JUMIN_SEIMEI_CATEGORY_CAP = 28_000;
    private const SHOTOKU_JISHIN_TOTAL_CAP = 50_000;
    private const JUMIN_JISHIN_TOTAL_CAP = 25_000;

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        return [
            // 所得税（生命保険料控除）
            'shotokuzei_kojo_seimei_ippan_prev',
            'shotokuzei_kojo_seimei_ippan_curr',
            'shotokuzei_kojo_seimei_nenkin_prev',
            'shotokuzei_kojo_seimei_nenkin_curr',
            'shotokuzei_kojo_seimei_kaigo_prev',
            'shotokuzei_kojo_seimei_kaigo_curr',
            'shotokuzei_kojo_seimei_gokei_prev',
            'shotokuzei_kojo_seimei_gokei_curr',
            'kojo_seimei_shotoku_prev',
            'kojo_seimei_shotoku_curr',
            // 所得税（地震保険料控除）
            'shotokuzei_kojo_jishin_eq_prev',
            'shotokuzei_kojo_jishin_eq_curr',
            'shotokuzei_kojo_jishin_old_prev',
            'shotokuzei_kojo_jishin_old_curr',
            'shotokuzei_kojo_jishin_gokei_prev',
            'shotokuzei_kojo_jishin_gokei_curr',
            'kojo_jishin_shotoku_prev',
            'kojo_jishin_shotoku_curr',
            // 住民税（生命保険料控除）
            'juminzei_kojo_seimei_ippan_prev',
            'juminzei_kojo_seimei_ippan_curr',
            'juminzei_kojo_seimei_nenkin_prev',
            'juminzei_kojo_seimei_nenkin_curr',
            'juminzei_kojo_seimei_kaigo_prev',
            'juminzei_kojo_seimei_kaigo_curr',
            'juminzei_kojo_seimei_gokei_prev',
            'juminzei_kojo_seimei_gokei_curr',
            'kojo_seimei_jumin_prev',
            'kojo_seimei_jumin_curr',
            // 住民税（地震保険料控除）
            'juminzei_kojo_jishin_eq_prev',
            'juminzei_kojo_jishin_eq_curr',
            'juminzei_kojo_jishin_old_prev',
            'juminzei_kojo_jishin_old_curr',
            'juminzei_kojo_jishin_gokei_prev',
            'juminzei_kojo_jishin_gokei_curr',
            'kojo_jishin_jumin_prev',
            'kojo_jishin_jumin_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string  $period
     * @return array<string, mixed>
     */
    public function compute(array $payload, string $period): array
    {
        if (! in_array($period, ['prev', 'curr'], true)) {
            return [];
        }

        $newGeneral = $this->n($payload[sprintf('kojo_seimei_shin_%s', $period)] ?? null);
        $oldGeneral = $this->n($payload[sprintf('kojo_seimei_kyu_%s', $period)] ?? null);
        $newNenkin = $this->n($payload[sprintf('kojo_seimei_nenkin_shin_%s', $period)] ?? null);
        $oldNenkin = $this->n($payload[sprintf('kojo_seimei_nenkin_kyu_%s', $period)] ?? null);
        $kaigo = $this->n($payload[sprintf('kojo_seimei_kaigo_iryo_%s', $period)] ?? null);
        $eq = $this->n($payload[sprintf('kojo_jishin_%s', $period)] ?? null);
        $oldEq = $this->n($payload[sprintf('kojo_kyuchoki_songai_%s', $period)] ?? null);

        $shotokuGeneral = $this->calculateShotokuSeimeiGeneral($newGeneral, $oldGeneral);
        $shotokuNenkin = $this->calculateShotokuSeimeiGeneral($newNenkin, $oldNenkin);
        $shotokuKaigo = $this->calculateShotokuSeimeiNew($kaigo);
        $shotokuTotal = min(
            self::SHOTOKU_SEIMEI_TOTAL_CAP,
            $shotokuGeneral + $shotokuNenkin + $shotokuKaigo
        );

        $juminGeneral = $this->calculateJuminSeimeiCombined($newGeneral, $oldGeneral);
        $juminNenkin = $this->calculateJuminSeimeiCombined($newNenkin, $oldNenkin);
        $juminKaigo = $this->calculateJuminSeimeiNew($kaigo);
        $juminTotal = min(
            self::JUMIN_SEIMEI_TOTAL_CAP,
            $juminGeneral + $juminNenkin + $juminKaigo
        );

        $shotokuEq = $this->calculateShotokuJishinEq($eq);
        $shotokuOld = $this->calculateShotokuJishinOld($oldEq);
        $shotokuJishinTotal = min(self::SHOTOKU_JISHIN_TOTAL_CAP, $shotokuEq + $shotokuOld);

        $juminEq = $this->calculateJuminJishinEq($eq);
        $juminOld = $this->calculateJuminJishinOld($oldEq);
        $juminJishinTotal = min(self::JUMIN_JISHIN_TOTAL_CAP, $juminEq + $juminOld);

        return [
            sprintf('shotokuzei_kojo_seimei_ippan_%s', $period) => $shotokuGeneral,
            sprintf('shotokuzei_kojo_seimei_nenkin_%s', $period) => $shotokuNenkin,
            sprintf('shotokuzei_kojo_seimei_kaigo_%s', $period) => $shotokuKaigo,
            sprintf('shotokuzei_kojo_seimei_gokei_%s', $period) => $shotokuTotal,
            sprintf('kojo_seimei_shotoku_%s', $period) => $shotokuTotal,
            sprintf('juminzei_kojo_seimei_ippan_%s', $period) => $juminGeneral,
            sprintf('juminzei_kojo_seimei_nenkin_%s', $period) => $juminNenkin,
            sprintf('juminzei_kojo_seimei_kaigo_%s', $period) => $juminKaigo,
            sprintf('juminzei_kojo_seimei_gokei_%s', $period) => $juminTotal,
            sprintf('kojo_seimei_jumin_%s', $period) => $juminTotal,
            sprintf('shotokuzei_kojo_jishin_eq_%s', $period) => $shotokuEq,
            sprintf('shotokuzei_kojo_jishin_old_%s', $period) => $shotokuOld,
            sprintf('shotokuzei_kojo_jishin_gokei_%s', $period) => $shotokuJishinTotal,
            sprintf('kojo_jishin_shotoku_%s', $period) => $shotokuJishinTotal,
            sprintf('juminzei_kojo_jishin_eq_%s', $period) => $juminEq,
            sprintf('juminzei_kojo_jishin_old_%s', $period) => $juminOld,
            sprintf('juminzei_kojo_jishin_gokei_%s', $period) => $juminJishinTotal,
            sprintf('kojo_jishin_jumin_%s', $period) => $juminJishinTotal,
        ];
    }

    private function calculateShotokuSeimeiGeneral(int $newAmount, int $oldAmount): int
    {
        $newOnly = $this->calculateShotokuSeimeiNew($newAmount);
        $oldOnly = $this->calculateShotokuSeimeiOld($oldAmount);
        $sumCapped = min(self::SHOTOKU_SEIMEI_CATEGORY_CAP, $newOnly + $oldOnly);

        return max($oldOnly, $sumCapped);
    }

    private function calculateShotokuSeimeiNew(int $amount): int
    {
        $value = max(0, $amount);

        if ($value <= 20_000) {
            return $value;
        }

        if ($value <= 40_000) {
            return intdiv($value, 2) + 10_000;
        }

        if ($value <= 80_000) {
            return intdiv($value, 4) + 20_000;
        }

        return self::SHOTOKU_SEIMEI_CATEGORY_CAP;
    }

    private function calculateShotokuSeimeiOld(int $amount): int
    {
        $value = max(0, $amount);

        if ($value <= 25_000) {
            return $value;
        }

        if ($value <= 50_000) {
            return intdiv($value, 2) + 12_500;
        }

        if ($value <= 100_000) {
            return intdiv($value, 4) + 25_000;
        }

        return 50_000;
    }

    private function calculateJuminSeimeiCombined(int $newAmount, int $oldAmount): int
    {
        $newOnly = $this->calculateJuminSeimeiNew($newAmount);
        $oldOnly = $this->calculateJuminSeimeiOld($oldAmount);
        $sumCapped = min(self::JUMIN_SEIMEI_CATEGORY_CAP, $newOnly + $oldOnly);

        return max($oldOnly, $sumCapped);
    }

    private function calculateJuminSeimeiNew(int $amount): int
    {
        $value = max(0, $amount);

        if ($value <= 12_000) {
            return $value;
        }

        if ($value <= 32_000) {
            return intdiv($value, 2) + 6_000;
        }

        if ($value <= 56_000) {
            return intdiv($value, 4) + 14_000;
        }

        return self::JUMIN_SEIMEI_CATEGORY_CAP;
    }

    private function calculateJuminSeimeiOld(int $amount): int
    {
        $value = max(0, $amount);

        if ($value <= 15_000) {
            return $value;
        }

        if ($value <= 40_000) {
            return intdiv($value, 2) + 7_500;
        }

        if ($value <= 70_000) {
            return intdiv($value, 4) + 17_500;
        }

        return 35_000;
    }

    private function calculateShotokuJishinEq(int $amount): int
    {
        $value = max(0, $amount);

        return min(self::SHOTOKU_JISHIN_TOTAL_CAP, $value);
    }

    private function calculateShotokuJishinOld(int $amount): int
    {
        $value = max(0, $amount);

        if ($value <= 10_000) {
            return $value;
        }

        if ($value <= 20_000) {
            return intdiv($value, 2) + 5_000;
        }

        return 15_000;
    }

    private function calculateJuminJishinEq(int $amount): int
    {
        $value = max(0, $amount);

        if ($value <= 50_000) {
            return intdiv($value, 2);
        }

        return self::JUMIN_JISHIN_TOTAL_CAP;
    }

    private function calculateJuminJishinOld(int $amount): int
    {
        $value = max(0, $amount);

        if ($value <= 5_000) {
            return $value;
        }

        if ($value <= 15_000) {
            return intdiv($value, 2) + 2_500;
        }

        return 10_000;
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