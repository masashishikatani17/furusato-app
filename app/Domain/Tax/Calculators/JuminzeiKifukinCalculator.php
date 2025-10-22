<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

final class JuminzeiKifukinCalculator implements ProvidesKeys
{
    public const ID    = 'kojo.kifukin.jumin';
    public const ORDER = 4045;
    public const BEFORE = [];
    public const AFTER  = [
        \App\Domain\Tax\Calculators\KojoAggregationCalculator::ID,
        \App\Domain\Tax\Calculators\SogoShotokuNettingStagesCalculator::ID,
    ];

    public static function provides(): array
    {
        return [
            'kazeisoushotoku_prev',
            'kazeisoushotoku_curr',
            'kifu_gaku_prev',
            'kifu_gaku_curr',
            'furusato_kifu_gaku_prev',
            'furusato_kifu_gaku_curr',
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
        $out = [
            'kazeisoushotoku_prev'       => 0,
            'kazeisoushotoku_curr'       => 0,
            'kifu_gaku_prev'             => 0,
            'kifu_gaku_curr'             => 0,
            'furusato_kifu_gaku_prev'    => 0,
            'furusato_kifu_gaku_curr'    => 0,
            'juminzei_kojo_kifukin_prev' => (int) ($payload['juminzei_kojo_kifukin_prev'] ?? 0),
            'juminzei_kojo_kifukin_curr' => (int) ($payload['juminzei_kojo_kifukin_curr'] ?? 0),
        ];

        foreach (['prev', 'curr'] as $period) {
            $shotokuGokei = $this->n($payload["shotoku_gokei_shotoku_{$period}"] ?? null);
            $sanrin = $this->n($payload["bunri_shotoku_sanrin_shotoku_{$period}"] ?? null);
            $taishoku = $this->n($payload["bunri_shotoku_taishoku_shotoku_{$period}"] ?? null);
            $kojoJumin = $this->n($payload["kojo_gokei_jumin_{$period}"] ?? null);

            $tmp = $shotokuGokei + $sanrin + $taishoku - $kojoJumin;
            $tmpFloorThousand = $this->floorToThousands($tmp);
            $out["kazeisoushotoku_{$period}"] = max(0, $tmpFloorThousand);

            $categories = ['furusato', 'kyodobokin_nisseki', 'npo', 'koueki', 'sonota'];
            $sumKifu = 0;
            foreach ($categories as $category) {
                $sumKifu += $this->n($payload["juminzei_zeigakukojo_{$category}_{$period}"] ?? null);
            }
            $out["kifu_gaku_{$period}"] = $sumKifu;

            $out["furusato_kifu_gaku_{$period}"] = $this->n($payload["juminzei_zeigakukojo_furusato_{$period}"] ?? null);
        }

        return array_replace($payload, $out);
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
        if ($value >= 0) {
            return (int) (floor($value / 1000) * 1000);
        }

        $abs = abs($value);
        $thousand = (int) (ceil($abs / 1000) * 1000);

        return -$thousand;
    }
}