<?php

namespace App\Domain\Tax\Calculators;

use App\Domain\Tax\Contracts\MasterProviderContract;
use App\Services\Tax\Contracts\ProvidesKeys;

class FurusatoResultCalculator implements ProvidesKeys
{
    public const ID = 'results.furusato';
    public const ORDER = 9000;
    public const ANCHOR = 'results';
    public const BEFORE = [];
    public const AFTER = [
        TokureiRateCalculator::ID,
        BunriSeparatedMinRateCalculator::ID,
    ];

    private const PERIODS = ['prev', 'curr'];

    private const HUMAN_DIFF_BASES = [
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

    private const SEPARATED_OTHER_BASES = [
        'bunri_kazeishotoku_choki',
        'bunri_kazeishotoku_haito',
        'bunri_kazeishotoku_sakimono',
        'bunri_kazeishotoku_joto',
    ];

    private const FIXED_NINETY_RATE = 0.90;
    private const BUNRI_SHORT_TERM_RATE = 0.59370;
    private const BUNRI_OTHER_RATE = 0.74685;

    public function __construct(private readonly MasterProviderContract $masterProvider)
    {
    }

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        return [
            'furusato_result_details_prev',
            'furusato_result_details_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $details = $this->buildDetails($payload, $ctx);

        foreach (self::PERIODS as $period) {
            $payload[sprintf('furusato_result_details_%s', $period)] = $details[$period] ?? [];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array{prev: array<string, float|null>, curr: array<string, float|null>}
     */
    public function buildDetails(array $payload, array $ctx): array
    {
        $year = $this->resolveMasterYear($ctx);
        $companyId = isset($ctx['company_id']) && $ctx['company_id'] !== ''
            ? (int) $ctx['company_id']
            : null;

        $rows = $year > 0 ? $this->buildTokureiRows($year, $companyId) : [];

        $details = [];
        foreach (self::PERIODS as $period) {
            $details[$period] = $this->buildPeriodDetails($payload, $rows, $period);
        }

        return [
            'prev' => $details['prev'] ?? $this->emptyDetails(),
            'curr' => $details['curr'] ?? $this->emptyDetails(),
        ];
    }

    /**
     * @param  array<int, array{lower:int, upper:int|null, rate:float}>  $rows
     * @return array<string, float|null>
     */
    private function buildPeriodDetails(array $payload, array $rows, string $period): array
    {
        $adjustedTaxable = $this->adjustedTaxable($payload, $period);

        $aa50 = $adjustedTaxable !== null
            ? $this->lowerBoundRate($adjustedTaxable, $rows)
            : null;

        $aa51 = self::FIXED_NINETY_RATE;

        $aa52 = $this->sanrinRate($rows, $payload, $period);
        $aa53 = $this->taishokuRate($rows, $payload, $period);

        $aa54 = null;
        if ($aa52 !== null || $aa53 !== null) {
            $candidates = array_filter([$aa52, $aa53], static fn (?float $value): bool => $value !== null);
            $aa54 = $candidates === [] ? null : min($candidates);
        }

        $aa55 = $this->bunriMinRate($payload, $period);

        $finalCandidates = array_filter([
            $aa50,
            $aa51,
            $aa54,
            $aa55,
        ], static fn (?float $value): bool => $value !== null);
        $aa56 = $finalCandidates === [] ? null : min($finalCandidates);

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

    /**
     * @return array<string, float|null>
     */
    private function emptyDetails(): array
    {
        return [
            'AA50' => null,
            'AA51' => self::FIXED_NINETY_RATE,
            'AA52' => null,
            'AA53' => null,
            'AA54' => null,
            'AA55' => null,
            'AA56' => null,
        ];
    }

    /**
     * @return array<int, array{lower:int, upper:int|null, rate:float}>
     */
    private function buildTokureiRows(int $year, ?int $companyId): array
    {
        $collection = $this->masterProvider->getTokureiRates($year, $companyId);

        $rows = [];
        foreach ($collection as $row) {
            $lower = $this->normalizeBound($row->lower ?? null);
            if ($lower === null) {
                continue;
            }

            $upper = $this->normalizeUpperBound($row->upper ?? null);
            $rate = $this->normalizeRate($row->tokurei_deduction_rate ?? null);

            if ($rate === null) {
                continue;
            }

            $rows[] = [
                'lower' => $lower,
                'upper' => $upper,
                'rate' => $rate,
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

    private function sanrinRate(array $rows, array $payload, string $period): ?float
    {
        $amount = $this->separatedIncomeAmount($payload, 'bunri_kazeishotoku_sanrin', $period);
        if ($amount <= 0) {
            return null;
        }

        $divided = $this->floorToThousands($amount / 5);
        if ($divided <= 0) {
            return null;
        }

        return $this->lowerBoundRate($divided, $rows);
    }

    private function taishokuRate(array $rows, array $payload, string $period): ?float
    {
        $amount = $this->separatedIncomeAmount($payload, 'bunri_kazeishotoku_taishoku', $period);
        if ($amount <= 0) {
            return null;
        }

        $base = $this->floorToThousands($amount);
        if ($base <= 0) {
            return null;
        }

        return $this->lowerBoundRate($base, $rows);
    }

    private function bunriMinRate(array $payload, string $period): ?float
    {
        $shortAmount = $this->floorToThousands(
            $this->separatedIncomeAmount($payload, 'bunri_kazeishotoku_tanki', $period)
        );

        if ($shortAmount > 0) {
            return self::BUNRI_SHORT_TERM_RATE;
        }

        foreach (self::SEPARATED_OTHER_BASES as $base) {
            $amount = $this->floorToThousands(
                $this->separatedIncomeAmount($payload, $base, $period)
            );

            if ($amount > 0) {
                return self::BUNRI_OTHER_RATE;
            }
        }

        return null;
    }

    private function separatedIncomeAmount(array $payload, string $baseKey, string $period): int
    {
        $juminKey = sprintf('%s_jumin_%s', $baseKey, $period);
        $shotokuKey = sprintf('%s_shotoku_%s', $baseKey, $period);

        $jumin = $this->intOrNull($payload[$juminKey] ?? null);
        if ($jumin !== null && $jumin > 0) {
            return max(0, $jumin);
        }

        $shotoku = $this->intOrNull($payload[$shotokuKey] ?? null);

        return $shotoku !== null ? max(0, $shotoku) : 0;
    }

    private function adjustedTaxable(array $payload, string $period): ?int
    {
        $taxable = $this->taxableShotoku($payload, $period);
        if ($taxable === null) {
            return null;
        }

        $sum = 0;
        foreach (self::HUMAN_DIFF_BASES as $base) {
            $shotokuKey = sprintf('%s_shotoku_%s', $base, $period);
            $juminKey = sprintf('%s_jumin_%s', $base, $period);

            $shotoku = $this->intOrNull($payload[$shotokuKey] ?? null) ?? 0;
            $jumin = $this->intOrNull($payload[$juminKey] ?? null) ?? 0;

            $sum += ($shotoku - $jumin);
        }

        $adjusted = $taxable - $sum;

        return $this->floorToThousands($this->nonNegative($adjusted));
    }

    private function taxableShotoku(array $payload, string $period): ?int
    {
        $key = sprintf('tax_kazeishotoku_shotoku_%s', $period);
        $raw = $this->intOrNull($payload[$key] ?? null);

        if ($raw === null) {
            return null;
        }

        return $this->floorToThousands($this->nonNegative($raw));
    }

    private function lowerBoundRate(int $amount, array $rows): ?float
    {
        if ($rows === []) {
            return null;
        }

        $amount = $this->floorToThousands(max(0, $amount));

        $fallbackRate = null;
        $fallbackLower = PHP_INT_MAX;

        foreach ($rows as $row) {
            $lower = (int) $row['lower'];
            if ($lower < $fallbackLower) {
                $fallbackLower = $lower;
                $fallbackRate = $row['rate'];
            }

            if ($lower > $amount) {
                continue;
            }

            $upper = $row['upper'];
            if ($upper !== null && $amount > $upper) {
                continue;
            }

            return $row['rate'];
        }

        return $fallbackRate;
    }

    private function resolveMasterYear(array $ctx): int
    {
        $year = isset($ctx['master_kihu_year']) ? (int) $ctx['master_kihu_year'] : 0;
        if ($year > 0) {
            return $year;
        }

        $fallback = isset($ctx['kihu_year']) ? (int) $ctx['kihu_year'] : 0;

        return max(0, $fallback);
    }

    private function normalizeBound(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeUpperBound(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeRate(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return ((float) $value) / 100.0;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
            if ($value === '') {
                return null;
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) floor((float) $value);
    }

    private function nonNegative(int|float $value): float
    {
        return max(0.0, (float) $value);
    }

    private function floorToThousands(int|float $value): int
    {
        if ($value <= 0) {
            return 0;
        }

        return (int) (floor(((float) $value) / 1000) * 1000);
    }
}