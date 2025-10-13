<?php

namespace App\Domain\Tax\Providers;

use App\Domain\Tax\Contracts\MasterProviderContract;
use App\Services\Tax\FurusatoMasterService;
use Illuminate\Support\Collection;

class MasterProvider implements MasterProviderContract
{
    public function __construct(private readonly FurusatoMasterService $masterService)
    {
    }

    public function getShotokuRates(int $year, ?int $companyId = null): Collection
    {
        return $this->delegate(__FUNCTION__, $year, $companyId);
    }

    public function getJuminRates(int $year, ?int $companyId = null): Collection
    {
        return $this->delegate(__FUNCTION__, $year, $companyId);
    }

    public function getTokureiRates(int $year, ?int $companyId = null): Collection
    {
        return $this->delegate(__FUNCTION__, $year, $companyId);
    }

    public function getShinkokutokureiRates(int $year, ?int $companyId = null): Collection
    {
        return $this->delegate(__FUNCTION__, $year, $companyId);
    }

    private function delegate(string $method, int $year, ?int $companyId): Collection
    {
        $rates = $this->masterService->{$method}($year, $companyId);

        if ($companyId !== null && $rates->isEmpty() && config('app.debug')) {
            logger()->debug(sprintf(
                'MasterProvider: no rates found after fallback. method=%s, year=%d, company_id=%d',
                $method,
                $year,
                $companyId
            ));
        }

        return $rates;
    }
}