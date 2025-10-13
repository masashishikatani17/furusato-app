<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class SeitotoTokubetsuZeigakuKojoCalculator implements ProvidesKeys
{
    public const ID = 'credit.seitoto';
    public const ORDER = 6000;
    public const ANCHOR = 'credits';
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