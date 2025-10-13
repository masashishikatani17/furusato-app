<?php

namespace App\Domain\Tax\Contracts;

use Illuminate\Support\Collection;

interface MasterProviderContract
{
    public function getShotokuRates(int $year, ?int $companyId = null): Collection;

    public function getJuminRates(int $year, ?int $companyId = null): Collection;

    public function getTokureiRates(int $year, ?int $companyId = null): Collection;

    public function getShinkokutokureiRates(int $year, ?int $companyId = null): Collection;
}