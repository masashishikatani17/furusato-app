<?php

namespace App\Services\Tax\Result;

use App\Services\Tax\FurusatoMasterService;
use App\Services\Tax\Result\Rate\BunriRateService;
use App\Services\Tax\Result\Rate\TokureiRateService;
use App\Services\Tax\Result\Support\PayloadAccessor;

final class FurusatoResultService
{
    public function __construct(
        private readonly FurusatoMasterService $masterService,
        private readonly TokureiRateService $tokureiRateService,
        private readonly BunriRateService $bunriRateService,
    ) {
    }

    public function buildFromPayload(int $kihuYear, ?int $companyId, array $payload): array
    {
        $accessor = new PayloadAccessor($payload);
        $tokureiRates = $this->masterService->getTokureiRates($kihuYear, $companyId);

        $taxableIncome = max(0.0, $accessor->getFloat('tax_kazeishotoku_shotoku_curr') ?? 0.0);
        $tokureiStandard = $this->tokureiRateService->lookup($taxableIncome, $tokureiRates);

        $tokurei90 = 0.90;

        $sanrinBase = null;
        $sanrinIncome = $accessor->getFloat('bunri_kazeishotoku_sanrin_shotoku_curr');
        if ($sanrinIncome !== null && $sanrinIncome > 0.0) {
            $sanrinBase = $this->tokureiRateService->lookup($sanrinIncome / 5, $tokureiRates);
        }

        $taishokuBase = null;
        $taishokuIncome = $accessor->getFloat('bunri_kazeishotoku_taishoku_shotoku_curr');
        if ($taishokuIncome !== null && $taishokuIncome > 0.0) {
            $taishokuBase = $this->tokureiRateService->lookup($taishokuIncome, $tokureiRates);
        }

        $adoptedCandidates = array_filter([
            $sanrinBase,
            $taishokuBase,
        ], static fn (?float $value) => $value !== null);
        $adoptedMin = $adoptedCandidates ? min($adoptedCandidates) : null;

        $bunriMin = $this->bunriRateService->determineMinimumRate($accessor);

        $finalCandidates = array_filter([
            $tokureiStandard,
            $tokurei90,
            $adoptedMin,
            $bunriMin,
        ], static fn (?float $value) => $value !== null);
        $finalRate = $finalCandidates ? min($finalCandidates) : null;

        return [
            'details' => [
                'tokurei_standard' => $tokureiStandard,
                'tokurei_90' => $tokurei90,
                'sanrin_base' => $sanrinBase,
                'taishoku_base' => $taishokuBase,
                'adopted_min' => $adoptedMin,
                'bunri_min' => $bunriMin,
                'final_rate' => $finalRate,
            ],
            'upper' => $payload,
        ];
    }
}