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

    private function buildPeriodDetails(array $payload, array $tokureiRows, string $period): array
    {
        $taxableKey = sprintf('tax_kazeishotoku_jumin_%s', $period);
        $taxableRaw = PayloadAccessor::intOrNull($payload, $taxableKey);
        $taxableAmount = $taxableRaw !== null
            ? PayloadAccessor::floorToThousands(PayloadAccessor::nonNegativeFloat($taxableRaw))
            : null;

        $aa50 = $taxableAmount !== null
            ? $this->tokureiRateService->lowerBoundRate($taxableAmount, $tokureiRows)
            : null;

        $aa51 = self::FIXED_90_RATE;

        $sanrinKey = sprintf('bunri_kazeishotoku_sanrin_jumin_%s', $period);
        $sanrinRaw = PayloadAccessor::intOrNull($payload, $sanrinKey);
        $aa52 = null;
        if ($sanrinRaw !== null) {
            $sanrinAmount = PayloadAccessor::floorToThousands(PayloadAccessor::nonNegativeFloat($sanrinRaw));

            if ($sanrinAmount > 0.0) {
                $divided = PayloadAccessor::floorToThousands($sanrinAmount / 5);

                if ($divided !== null && $divided > 0.0) {
                    $aa52 = $this->tokureiRateService->lowerBoundRate($divided, $tokureiRows);
                }
            }
        }

        $taishokuKey = sprintf('bunri_kazeishotoku_taishoku_jumin_%s', $period);
        $taishokuRaw = PayloadAccessor::intOrNull($payload, $taishokuKey);
        $aa53 = null;
        if ($taishokuRaw !== null) {
            $taishokuAmount = PayloadAccessor::floorToThousands(PayloadAccessor::nonNegativeFloat($taishokuRaw));

            if ($taishokuAmount > 0.0) {
                $aa53 = $this->tokureiRateService->lowerBoundRate($taishokuAmount, $tokureiRows);
            }
        }

        $adoptedCandidates = array_filter([
            $aa52,
            $aa53,
        ], static fn (?float $value) => $value !== null);
        $aa54 = $adoptedCandidates ? min($adoptedCandidates) : null;

        $aa55 = $this->bunriRateService->minRateForSeparatedTaxes($payload, $period);

        $finalCandidates = array_filter([
            $aa50,
            $aa51,
            $aa54,
            $aa55,
        ], static fn (?float $value) => $value !== null);
        $aa56 = $finalCandidates ? min($finalCandidates) : null;

        return [
            'AA50' => $aa50,
            'AA51' => $aa51,
            'AA52' => $aa52,
            'AA53' => $aa53,
            'AA54' => $aa54,
            'AA55' => $aa55,
            'AA56' => $aa56,
        ];
    }
}