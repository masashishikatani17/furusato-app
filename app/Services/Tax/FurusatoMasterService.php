<?php

namespace App\Services\Tax;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FurusatoMasterService
{
    private const CACHE_TTL = 300; // seconds

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
            ->values();
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
            });
    }

    public function getTokureiRates(int $year, ?int $companyId = null): Collection
    {
        return $this->rememberRates('tokurei', 'tokurei_rates', $year, $companyId, [FurusatoMasterDefaults::class, 'tokurei'])
            ->map(static function (array $rate): array {
                $lower = $rate['lower'];
                $upper = $rate['upper'];

                return [
                    'lower' => $lower !== null ? (int) $lower : null,
                    'upper' => $upper !== null ? (int) $upper : null,
                    'income_rate' => (float) $rate['income_rate'],
                    'ninety_minus_rate' => (float) $rate['ninety_minus_rate'],
                    'income_rate_with_recon' => (float) $rate['income_rate_with_recon'],
                    'tokurei_deduction_rate' => (float) $rate['tokurei_deduction_rate'],
                ];
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
            });
    }

    private function rememberRates(string $key, string $table, int $year, ?int $companyId, callable $fallback): Collection
    {
        $cacheKey = sprintf('furusato_master:%s:%d:%s', $key, $year, $companyId ?? 'default');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($table, $year, $companyId, $fallback): Collection {
            $rates = $this->fetchRates($table, $year, $companyId);

            if ($rates->isNotEmpty()) {
                return $rates;
            }

            return collect($fallback());
        });
    }

    private function fetchRates(string $table, int $year, ?int $companyId): Collection
    {
        $query = DB::table($table)
            ->where('year', '<=', $year);

        if ($companyId === null) {
            $query->whereNull('company_id');
        } else {
            $query->where(function ($inner) use ($companyId): void {
                $inner->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            });
        }

        $rows = $query
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('year')
            ->orderBy('sort')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $targetYear = (int) $rows->first()->year;

        $grouped = $rows
            ->filter(static fn ($row): bool => (int) $row->year === $targetYear)
            ->groupBy('sort')
            ->map(function (Collection $group) use ($companyId) {
                return $group->sortByDesc(function ($row) use ($companyId) {
                    if ($row->company_id === null) {
                        return 0;
                    }

                    return $companyId !== null && (int) $row->company_id === $companyId ? 2 : 1;
                })->first();
            })
            ->sortKeys()
            ->values();

        return $grouped->map(static fn ($row): array => (array) $row);
    }
}