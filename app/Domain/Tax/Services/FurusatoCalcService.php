<?php
namespace App\Domain\Tax\Services;

use App\Domain\Tax\DTO\FurusatoInput;
use App\Domain\Tax\Support\Math;

final class FurusatoCalcService
{
    /**
     * vol23 「ふるさと上限」代表セルの移植
     * - B8  : ROUNDDOWN(((W17+W18)*0.2)/AB56 + 2000, 0)
     * - B9  : 上と同式（vol23）
     * - B12 : ROUND(AB6*0.4 + 2000, 0)
     * - B13 : ROUND(AB6*0.3 + 2000, 0)
     * - B16 : MIN(B8,B12,B13)
     * - B17 : MIN(B9,B13)
     */
    public function calcUpperLimit(FurusatoInput $in): array
    {
        $sumW = $in->w17 + $in->w18;
        $common = ($sumW * 0.2) / max(1, $in->ab56) + 2000;
        $b8 = Math::roundDown0($common);
        $b9 = Math::roundDown0($common); // vol23: B8と同式
        $b12 = Math::round0($in->ab6 * 0.4 + 2000);
        $b13 = Math::round0($in->ab6 * 0.3 + 2000);
        $b16 = min($b8, $b12, $b13);
        $b17 = min($b9, $b13);
        return [
            'b8' => $b8,
            'b9' => $b9,
            'b12' => $b12,
            'b13' => $b13,
            'b16' => $b16,
            'b17' => $b17,
            'flags' => [
                'v6' => $in->v6,
                'w6' => $in->w6,
                'x6' => $in->x6,
            ],
        ];
    }

    public function calcDonationOverview(FurusatoInput $in): array
    {
        $rows = [];

        foreach ([
            2 => $in->q2,
            3 => $in->q3,
            4 => $in->q4,
            5 => $in->q5,
        ] as $row => $q) {
            $s = $q * 1.021;
            $u = 1.0 - 0.10 - $s;

            $rows[] = [
                'row' => $row,
                'q' => (float) $q,
                's' => (float) $s,
                'u' => (float) $u,
            ];
        }

        return ['rows' => $rows];
    }
}