<?php

namespace App\Services\Tax;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FurusatoMasterService
{
    private const CACHE_TTL = 300; // seconds

    /**
     * @var array<int, array{label: string, text: string}>
     */
    private const TOKUREI_NOTE_TEMPLATES = [
        80 => [
            'label' => '山林所得の特例',
            'text' => '山林所得がある場合は課税標準額を5で除した金額に対応する控除率を使用します。',
        ],
        90 => [
            'label' => '退職所得の特例',
            'text' => '退職所得がある場合は課税標準額に対応する控除率を使用します。',
        ],
        100 => [
            'label' => '採用控除率',
            'text' => '山林所得と退職所得の双方がある場合は低い控除率を採用します。',
        ],
    ];

    public function getShotokuRates(int $year, ?int $companyId = null): Collection
    {
        return $this->rememberRates('shotoku', 'shotoku_rates', $year, $companyId, [FurusatoMasterDefaults::class, 'shotoku'])
            ->map(static function (array $rate): array {
                $upper = $rate['upper'];

                return [
                    'lower' => (int) $rate['lower'],
                    'upper' => $upper !== null ? (int) $upper : null,
                    'rate' => (float) $rate['rate'],
                    'deduction_amount' => (int) $rate['deduction_amount'],
                ];
            })
            ->sort(function (array $a, array $b): int {
                $lowerCompare = $a['lower'] <=> $b['lower'];
                
                if ($lowerCompare !== 0) {
                    return $lowerCompare;
                }

                $aUpper = $a['upper'] ?? PHP_INT_MAX;
                $bUpper = $b['upper'] ?? PHP_INT_MAX;

                return $aUpper <=> $bUpper;
            })
            ->values()
            ->map(static fn (array $rate): object => (object) $rate);
    }

    public function getJuminRates(int $year, ?int $companyId = null): Collection
    {
        return $this->rememberRates('jumin', 'jumin_rates', $year, $companyId, [FurusatoMasterDefaults::class, 'jumin'])
            ->map(static function (array $rate): array {
                return [
                    'category' => $rate['category'],
                    'sub_category' => $rate['sub_category'],
                    'city_specified' => (float) $rate['city_specified'],
                    'pref_specified' => (float) $rate['pref_specified'],
                    'city_non_specified' => (float) $rate['city_non_specified'],
                    'pref_non_specified' => (float) $rate['pref_non_specified'],
                    'remark' => $rate['remark'],
                ];
            })
            ->map(static fn (array $rate): object => (object) $rate);
    }

    public function getTokureiRates(int $year, ?int $companyId = null): Collection
    {
        return $this->rememberRates('tokurei', 'tokurei_rates', $year, $companyId, [FurusatoMasterDefaults::class, 'tokurei'])
            ->map(function (array $rate): array {
                $lower = $rate['lower'];
                $upper = $rate['upper'];

                return [
                    'sort' => isset($rate['sort']) ? (int) $rate['sort'] : null,
                    'lower' => $lower !== null ? (int) $lower : null,
                    'upper' => $upper !== null ? (int) $upper : null,
                    'income_rate' => (float) $rate['income_rate'],
                    'ninety_minus_rate' => (float) $rate['ninety_minus_rate'],
                    'income_rate_with_recon' => (float) $rate['income_rate_with_recon'],
                    'tokurei_deduction_rate' => (float) $rate['tokurei_deduction_rate'],
                    'note' => array_key_exists('note', $rate) && $rate['note'] !== null ? (string) $rate['note'] : '',
                ];
            })
            ->map(static function (array $rate): object {
                if ($rate['sort'] === null) {
                    unset($rate['sort']);
                }

                return (object) $rate;
            });
    }

    public function getShinkokutokureiRates(int $year, ?int $companyId = null): Collection
    {
        return $this->rememberRates('shinkokutokurei', 'shinkokutokurei_rates', $year, $companyId, [FurusatoMasterDefaults::class, 'shinkokutokurei'])
            ->map(static function (array $rate): array {
                $upper = $rate['upper'];

                return [
                    'lower' => (int) $rate['lower'],
                    'upper' => $upper !== null ? (int) $upper : null,
                    'ratio_a' => (float) $rate['ratio_a'],
                    'ratio_b' => (float) $rate['ratio_b'],
                ];
            })
            ->map(static fn (array $rate): object => (object) $rate);
    }

    private function rememberRates(string $key, string $table, int $year, ?int $companyId, callable $fallback): Collection
    {
        $cacheKey = sprintf('furusato_master:%s:%d:%s', $key, $year, $companyId ?? 'default');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($table, $year, $companyId, $fallback): Collection {
            $rates = $this->fetchRates($table, $year, $companyId);

            if ($rates->isNotEmpty()) {
                return $rates;
            }

            return collect($fallback())->map(static fn ($row): array => (array) $row);
        });
    }

    private function fetchRates(string $table, int $year, ?int $companyId): Collection
    {
        $rows = DB::table($table)
            ->select('*')
            ->selectRaw('COALESCE(year, kifu_year) as effective_year')
            ->whereRaw('COALESCE(year, kifu_year) <= ?', [$year]);

        if ($companyId === null) {
            $rows->whereNull('company_id');
        } else {
            $rows->where(function ($inner) use ($companyId): void {
                $inner->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            });
        }

        $rows = $rows
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(year, kifu_year) DESC')
            ->orderBy('sort')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $effectiveYear = $rows->first()->effective_year;
        if ($effectiveYear === null) {
            return collect();
        }

        $filtered = $rows->filter(static function ($row) use ($effectiveYear): bool {
            return (int) ($row->effective_year ?? 0) === (int) $effectiveYear;
        });

        $grouped = $filtered
            ->groupBy('sort')
            ->map(function (Collection $group) use ($companyId) {
                return $group->sortBy(function ($row) use ($companyId) {
                    if ($companyId !== null) {
                        if ($row->company_id !== null && (int) $row->company_id === $companyId) {
                            return 0;
                        }

                        if ($row->company_id === null) {
                            return 1;
                        }

                        return 2;
                    }

                    return $row->company_id === null ? 0 : 1;
                })->first();
            })
            ->sortKeys()
            ->values();

        return $grouped->map(static function ($row): array {
            $data = (array) $row;
            unset($data['effective_year']);

            return $data;
        });
    }

    private function buildTokureiNote(array $rate): string
    {
        $sort = isset($rate['sort']) ? (int) $rate['sort'] : null;

        if ($sort === null || ! array_key_exists($sort, self::TOKUREI_NOTE_TEMPLATES)) {
            return '||';
        }

        $template = self::TOKUREI_NOTE_TEMPLATES[$sort];

        return sprintf('%s||%s', $template['label'], $template['text']);
    }
}