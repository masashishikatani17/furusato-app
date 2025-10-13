<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class ShotokuTaxCalculator implements ProvidesKeys
{
    public const ID = 'tax.shotoku';
    public const ORDER = 5000;
    public const ANCHOR = 'tax';
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