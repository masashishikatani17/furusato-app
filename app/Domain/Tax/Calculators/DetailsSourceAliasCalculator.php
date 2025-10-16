<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class DetailsSourceAliasCalculator implements ProvidesKeys
{
    public const ID = 'details.source.alias';
    public const ORDER = 3010;
    public const BEFORE = [];
    public const AFTER = [];

    private const PERIODS = ['prev', 'curr'];

    /**
     * @var array<string, array{group: string, source: string}>
     */
    private const MAPPINGS = [
        'shotoku_jigyo_eigyo_shotoku_%s' => ['group' => 'jigyo_eigyo_details', 'source' => 'jigyo_eigyo_shotoku_%s'],
        'shotoku_fudosan_shotoku_%s' => ['group' => 'fudosan_details', 'source' => 'fudosan_shotoku_%s'],
        'bunri_shotoku_sanrin_shotoku_%s' => ['group' => 'bunri_sanrin_details', 'source' => 'sashihiki_sanrin_%s'],
        'bunri_shotoku_tanki_ippan_shotoku_%s' => ['group' => 'bunri_joto_details', 'source' => 'sashihiki_tanki_ippan_%s'],
        'bunri_shotoku_tanki_keigen_shotoku_%s' => ['group' => 'bunri_joto_details', 'source' => 'sashihiki_tanki_keigen_%s'],
        'bunri_shotoku_choki_ippan_shotoku_%s' => ['group' => 'bunri_joto_details', 'source' => 'sashihiki_choki_ippan_%s'],
        'bunri_shotoku_choki_tokutei_shotoku_%s' => ['group' => 'bunri_joto_details', 'source' => 'sashihiki_choki_tokutei_%s'],
        'bunri_shotoku_choki_keika_shotoku_%s' => ['group' => 'bunri_joto_details', 'source' => 'sashihiki_choki_keika_%s'],
    ];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [];

        foreach (self::PERIODS as $period) {
            foreach (array_keys(self::MAPPINGS) as $pattern) {
                $keys[] = sprintf($pattern, $period);
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int>
     */
    public function compute(array $payload, string $period): array
    {
        if (! in_array($period, self::PERIODS, true)) {
            return [];
        }

        $updates = [];

        foreach (self::MAPPINGS as $targetPattern => $mapping) {
            $targetKey = sprintf($targetPattern, $period);
            $sourceKey = sprintf($mapping['source'], $period);

            $sourceValue = $this->extractSourceValue($payload, $mapping['group'], $sourceKey);
            $normalized = $this->normalize($sourceValue);

            if ($normalized !== null) {
                $updates[$targetKey] = $normalized;
                continue;
            }

            $current = array_key_exists($targetKey, $payload)
                ? $this->normalize($payload[$targetKey])
                : null;

            $updates[$targetKey] = $current ?? 0;
        }

        return $updates;
    }

    private function extractSourceValue(array $payload, string $group, string $key): mixed
    {
        $compoundKey = sprintf('%s.%s', $group, $key);
        if (array_key_exists($compoundKey, $payload)) {
            return $payload[$compoundKey];
        }

        if (array_key_exists($group, $payload) && is_array($payload[$group]) && array_key_exists($key, $payload[$group])) {
            return $payload[$group][$key];
        }

        if (array_key_exists($key, $payload)) {
            return $payload[$key];
        }

        return null;
    }

    private function normalize(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', trim($value));
            if ($value === '') {
                return null;
            }
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || is_numeric($value)) {
            return (int) floor((float) $value);
        }

        return null;
    }
}