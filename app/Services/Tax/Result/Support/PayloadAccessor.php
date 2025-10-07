<?php

namespace App\Services\Tax\Result\Support;

final class PayloadAccessor
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private readonly array $payload)
    {
    }

    public function getFloat(string $key): ?float
    {
        if (! array_key_exists($key, $this->payload)) {
            return null;
        }

        $value = $this->payload[$key];

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    public function isPositive(string $key): bool
    {
        $value = $this->getFloat($key);

        return $value !== null && $value > 0.0;
    }
}