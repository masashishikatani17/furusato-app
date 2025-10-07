<?php

namespace App\Services\Tax\Result\Rate;

use App\Services\Tax\FurusatoMasterService;

final class TokureiRateService
{
    public function __construct(private readonly FurusatoMasterService $masterService)
    {
    }

    /**
     * @return array<int, array{lower:int, upper:int|null, rate:float}>
     */
    public function getRows(int $year, ?int $companyId): array
    {
        $collection = $this->masterService->getTokureiRates($year, $companyId);

        $rows = [];
        foreach ($collection as $row) {
            $lower = $row->lower;
            if ($lower === null) {
                continue;
            }

            $upper = $row->upper;
            $rate = $row->tokurei_deduction_rate;

            if (! is_numeric($rate)) {
                continue;
            }

            $rows[] = [
                'lower' => (int) $lower,
                'upper' => $upper !== null ? (int) $upper : null,
                'rate' => ((float) $rate) / 100.0,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $lowerCmp = $a['lower'] <=> $b['lower'];
            if ($lowerCmp !== 0) {
                return $lowerCmp;
            }

            $upperA = $a['upper'];
            $upperB = $b['upper'];

            if ($upperA === $upperB) {
                return 0;
            }

            if ($upperA === null) {
                return 1;
            }

            if ($upperB === null) {
                return -1;
            }

            return $upperA <=> $upperB;
        });

        return $rows;
    }

    public function lowerBoundRate(float $amount, array $rows): ?float
    {
        if ($rows === []) {
            return null;
        }

        $amount = max(0.0, $amount);
        $lowest = $rows[0]['lower'];

        if ($amount < $lowest) {
            return null;
        }

        foreach ($rows as $row) {
            $upper = $row['upper'];
            if ($upper === null) {
                if ($amount >= $row['lower']) {
                    return $row['rate'];
                }

                continue;
            }

            if ($row['lower'] <= $amount && $amount <= $upper) {
                return $row['rate'];
            }
        }

        return null;
    }
}