<?php

namespace App\Services\Tax\Result\Support;

final class PayloadAccessor
{
    public static function intOrNull(array $payload, string $key): ?int
    {
        if (! array_key_exists($key, $payload)) {
            return null;
        }

        $value = $payload[$key];

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        return null;
    }

    public static function nonNegativeFloat(int|float|null $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        return max(0.0, (float) $value);
    }
}