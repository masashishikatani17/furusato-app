<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class JintekiKojoCalculator implements ProvidesKeys
{
    public const ID = 'kojo.jinteki';
    public const ORDER = 2200;
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