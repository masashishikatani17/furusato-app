<?php

namespace App\Services\Tax;

use App\Models\JuminRate;
use App\Models\ShinkokutokureiRate;
use App\Models\ShotokuRate;
use App\Models\TokureiRate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FurusatoMasterService
{
    private const CACHE_TTL = 300; // seconds

    public function getShotokuRates(int $year, ?int $companyId = null): Collection
    {
        return $this->rememberRates('shotoku', ShotokuRate::class, $year, $companyId);
    }

    public function getJuminRates(int $year, ?int $companyId = null): Collection
    {
        return $this->rememberRates('jumin', JuminRate::class, $year, $companyId);
    }

    public function getTokureiRates(int $year, ?int $companyId = null): Collection
    {
        return $this->rememberRates('tokurei', TokureiRate::class, $year, $companyId);
    }

    public function getShinkokutokureiRates(int $year, ?int $companyId = null): Collection
    {
        return $this->rememberRates('shinkokutokurei', ShinkokutokureiRate::class, $year, $companyId);
    }

    private function rememberRates(string $key, string $modelClass, int $year, ?int $companyId): Collection
    {
        $cacheKey = sprintf('furusato_master:%s:%d:%s', $key, $year, $companyId ?? 'default');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($modelClass, $year, $companyId): Collection {
            $rates = $this->fetchRates($modelClass, $year, $companyId);

            if ($rates->isNotEmpty() || $companyId === null) {
                return $rates;
            }

            return $this->fetchRates($modelClass, $year, null);
        });
    }

    /**
     * @param class-string<Model> $modelClass
     */
    private function fetchRates(string $modelClass, int $year, ?int $companyId): Collection
    {
        $query = $modelClass::query()->where('kihu_year', $year);

        if ($companyId === null) {
            $query->whereNull('company_id');
        } else {
            $query->where('company_id', $companyId);
        }

        $version = (clone $query)->max('version');

        if ($version === null) {
            return collect();
        }

        return (clone $query)
            ->where('version', $version)
            ->orderBy('seq')
            ->get();
    }
}