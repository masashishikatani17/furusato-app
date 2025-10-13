<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class FurusatoResultCalculator implements ProvidesKeys
{
    public const ID = 'results.furusato';
    public const ORDER = 9000;
    public const ANCHOR = 'results';
    public const BEFORE = [];
    public const AFTER = [];

    public static function provides(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        return $payload;
    }
}