<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class HaigushaKojoCalculator implements ProvidesKeys
{
    public const ID = 'kojo.haigusha';
    public const ORDER = 2300;
    public const ANCHOR = 'deductions';
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