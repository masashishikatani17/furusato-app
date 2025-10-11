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
        $adjustedTaxable = $this->adjustedTaxable($payload, $period);

        $aa50 = $adjustedTaxable !== null
            ? $this->tokureiRateService->lowerBoundRate($adjustedTaxable, $tokureiRows)
            : null;

        $aa51 = self::FIXED_90_RATE;

        $sanrinKey = sprintf('bunri_kazeishotoku_sanrin_jumin_%s', $period);
        $sanrinRaw = PayloadAccessor::intOrNull($payload, $sanrinKey);
        $aa52 = null;
        if ($sanrinRaw !== null) {
            $sanrinAmount = PayloadAccessor::nonNegativeFloat($sanrinRaw);

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

    private function taxableShotoku(array $payload, string $period): ?float
    {
        $key = sprintf('tax_kazeishotoku_shotoku_%s', $period);
        $raw = PayloadAccessor::intOrNull($payload, $key);

        if ($raw === null) {
            return null;
        }

        return PayloadAccessor::floorToThousands(PayloadAccessor::nonNegativeFloat($raw));
    }

    private function sumHumanDiff(array $payload, string $period): float
    {
        $bases = [
            'kojo_kafu',
            'kojo_hitorioya',
            'kojo_kinrogakusei',
            'kojo_shogaisyo',
            'kojo_haigusha',
            'kojo_haigusha_tokubetsu',
            'kojo_fuyo',
            'kojo_tokutei_shinzoku',
            'kojo_kiso',
        ];

        $sum = 0.0;

        foreach ($bases as $base) {
            $shotokuKey = sprintf('%s_shotoku_%s', $base, $period);
            $juminKey = sprintf('%s_jumin_%s', $base, $period);

            $shotoku = PayloadAccessor::intOrNull($payload, $shotokuKey) ?? 0;
            $jumin = PayloadAccessor::intOrNull($payload, $juminKey) ?? 0;

            $sum += ($shotoku - $jumin);
        }

        return (float) $sum;
    }

    private function adjustedTaxable(array $payload, string $period): ?float
    {
        $taxableShotoku = $this->taxableShotoku($payload, $period);

        if ($taxableShotoku === null) {
            return null;
        }

        $adjusted = $taxableShotoku - $this->sumHumanDiff($payload, $period);

        return PayloadAccessor::floorToThousands(
            PayloadAccessor::nonNegativeFloat($adjusted)
        );
    }
}