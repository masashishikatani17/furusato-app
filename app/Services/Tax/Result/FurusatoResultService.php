<?php

namespace App\Services\Tax\Result;

use App\Services\Tax\Result\Rate\BunriRateService;
use App\Services\Tax\Result\Rate\TokureiRateService;
use App\Services\Tax\Result\Support\PayloadAccessor;

final class FurusatoResultService
{
    private const PERIODS = ['prev', 'curr'];
    private const FIXED_90_RATE = 0.90;
    
    public function __construct(
        private readonly TokureiRateService $tokureiRateService,
        private readonly BunriRateService $bunriRateService,
    ) {
    }

    public function buildFromPayload(int $kihuYear, ?int $companyId, array $payload): array
    {
        $tokureiRows = $this->tokureiRateService->getRows($kihuYear, $companyId);

        $details = [];
        foreach (self::PERIODS as $period) {
            $details[$period] = $this->buildPeriodDetails($payload, $tokureiRows, $period);
        }

        return [
            'details' => $details,
            'upper' => $payload,
        ];
    }

    /**
     * @param array<int, array{lower:int, upper:int|null, rate:float}> $tokureiRows
     * @return array<string, float|null>
     */
    private function buildPeriodDetails(array $payload, array $tokureiRows, string $period): array
    {
        $taxableKey = sprintf('tax_kazeishotoku_jumin_%s', $period);
        $taxableRaw = PayloadAccessor::intOrNull($payload, $taxableKey);
        $taxableIncome = $taxableRaw !== null ? PayloadAccessor::nonNegativeFloat($taxableRaw) : null;

        $tokureiStandard = $taxableIncome !== null
            ? $this->tokureiRateService->lowerBoundRate($taxableIncome, $tokureiRows)
            : null;

        $sanrinKey = sprintf('bunri_kazeishotoku_sanrin_jumin_%s', $period);
        $sanrinRaw = PayloadAccessor::intOrNull($payload, $sanrinKey);
        $sanrinBase = null;
        if ($sanrinRaw !== null) {
            $sanrinIncome = PayloadAccessor::nonNegativeFloat($sanrinRaw);
            if ($sanrinIncome > 0.0) {
                $sanrinBase = $this->tokureiRateService->lowerBoundRate($sanrinIncome / 5, $tokureiRows);
            }
        }

        $taishokuKey = sprintf('bunri_kazeishotoku_taishoku_jumin_%s', $period);
        $taishokuRaw = PayloadAccessor::intOrNull($payload, $taishokuKey);
        $taishokuBase = null;
        if ($taishokuRaw !== null) {
            $taishokuIncome = PayloadAccessor::nonNegativeFloat($taishokuRaw);
            if ($taishokuIncome > 0.0) {
                $taishokuBase = $this->tokureiRateService->lowerBoundRate($taishokuIncome, $tokureiRows);
            }
        }

        $adoptedCandidates = array_filter([
            $sanrinBase,
            $taishokuBase,
        ], static fn (?float $value) => $value !== null);
        $adoptedMin = $adoptedCandidates ? min($adoptedCandidates) : null;

        $bunriMin = $this->bunriRateService->minRateForSeparatedTaxes($payload, $period);

        $finalCandidates = array_filter([
            $tokureiStandard,
            self::FIXED_90_RATE,
            $adoptedMin,
            $bunriMin,
        ], static fn (?float $value) => $value !== null);
        $finalRate = $finalCandidates ? min($finalCandidates) : null;

        return [
            'tokurei_standard' => $tokureiStandard,
            'tokurei_90' => self::FIXED_90_RATE,
            'sanrin_base' => $sanrinBase,
            'taishoku_base' => $taishokuBase,
            'adopted_min' => $adoptedMin,
            'bunri_min' => $bunriMin,
            'final_rate' => $finalRate,
        ];
    }
}