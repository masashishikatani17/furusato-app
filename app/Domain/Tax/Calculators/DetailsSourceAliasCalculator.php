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
     * @var array<string, string>
     */
    private const MAPPINGS = [
        'shotoku_jigyo_eigyo_shotoku_%s' => 'jigyo_eigyo_shotoku_%s',
        'shotoku_fudosan_shotoku_%s' => 'fudosan_shotoku_%s',
        'bunri_shotoku_sanrin_shotoku_%s' => 'sashihiki_sanrin_%s',
        'bunri_shotoku_tanki_ippan_shotoku_%s' => 'sashihiki_tanki_ippan_%s',
        'bunri_shotoku_tanki_keigen_shotoku_%s' => 'sashihiki_tanki_keigen_%s',
        'bunri_shotoku_choki_ippan_shotoku_%s' => 'sashihiki_choki_ippan_%s',
        'bunri_shotoku_choki_tokutei_shotoku_%s' => 'sashihiki_choki_tokutei_%s',
        'bunri_shotoku_choki_keika_shotoku_%s' => 'sashihiki_choki_keika_%s',
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

        foreach (self::MAPPINGS as $targetPattern => $sourcePattern) {
            $sourceKey = sprintf($sourcePattern, $period);
            if (! array_key_exists($sourceKey, $payload)) {
                continue;
            }

            $normalized = $this->normalize($payload[$sourceKey]);
            if ($normalized === null) {
                continue;
            }

            $targetKey = sprintf($targetPattern, $period);
            $updates[$targetKey] = $normalized;
        }

        return $updates;
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

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (int) ((float) $value);
        }

        return null;
    }
}