<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

final class JuminzeiKifukinCalculator implements ProvidesKeys
{
    public const ID = 'kojo.kifukin.jumin';
    public const ORDER = 2055;
    public const BEFORE = [];
    public const AFTER = [];

    public static function provides(): array
    {
        return [
            'juminzei_kojo_kifukin_prev',
            'juminzei_kojo_kifukin_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        return array_replace($payload, [
            'juminzei_kojo_kifukin_prev' => (int) ($payload['juminzei_kojo_kifukin_prev'] ?? 0),
            'juminzei_kojo_kifukin_curr' => (int) ($payload['juminzei_kojo_kifukin_curr'] ?? 0),
        ]);
    }
}