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
     * @var array<string, array{group: string, source: string, preserveNull?: bool, legacy_sources?: array<int, string>}>
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
        'sashihiki_joto_tanki_sogo_%s' => ['group' => 'joto_ichiji_details', 'source' => 'sashihiki_joto_tanki_%s', 'preserveNull' => true],
        'sashihiki_joto_choki_sogo_%s' => ['group' => 'joto_ichiji_details', 'source' => 'sashihiki_joto_choki_%s', 'preserveNull' => true],
        // Mirror the raw first-lump sum amounts so that downstream calculators can
        // clamp tsusango_ichiji to max(0, sashihiki_ichiji).
        'sashihiki_ichiji_%s' => ['group' => 'joto_ichiji_details', 'source' => 'sashihiki_ichiji_%s', 'preserveNull' => true],
        'after_1jitsusan_sanrin_%s' => ['group' => 'bunri_sanrin_details', 'source' => 'sashihiki_sanrin_%s', 'preserveNull' => true],
        'after_2jitsusan_taishoku_%s' => [
            'group' => 'input',
            'source' => 'bunri_shotoku_taishoku_shotoku_%s',
            'legacy_sources' => ['bunri_shotoku_taisyoku_shotoku_%s'],
            'preserveNull' => true,
        ],
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
     * @return array<string, int|null>
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
            $preserveNull = $mapping['preserveNull'] ?? false;

            $source = $this->extractSourceValue($payload, $mapping['group'], $sourceKey);

            if (! $source['found'] && isset($mapping['legacy_sources'])) {
                foreach ($mapping['legacy_sources'] as $legacyPattern) {
                    $legacyKey = sprintf($legacyPattern, $period);
                    $legacy = $this->extractSourceValue($payload, $mapping['group'], $legacyKey);
                    if ($legacy['found']) {
                        $source = $legacy;
                        break;
                    }
                }
            }

            if ($source['found']) {
                $normalized = $this->normalize($source['value']);

                if ($normalized !== null) {
                    $updates[$targetKey] = $normalized;
                    continue;
                }

                if ($preserveNull) {
                    $updates[$targetKey] = null;
                    continue;
                }
            }

            $updates[$targetKey] = $this->resolveFallbackValue($payload, $targetKey, $preserveNull);
        }

        return $updates;
    }

    /**
     * @return array{found: bool, value: mixed}
     */
    private function extractSourceValue(array $payload, string $group, string $key): array
    {
        if ($group !== '') {
            $compoundKey = sprintf('%s.%s', $group, $key);
        } else {
            $compoundKey = $key;
        }

        if (array_key_exists($compoundKey, $payload)) {
            return ['found' => true, 'value' => $payload[$compoundKey]];
        }

        if ($group !== '' && array_key_exists($group, $payload) && is_array($payload[$group]) && array_key_exists($key, $payload[$group])) {
            return ['found' => true, 'value' => $payload[$group][$key]];
        }

        if (array_key_exists($key, $payload)) {
            return ['found' => true, 'value' => $payload[$key]];
        }

        return ['found' => false, 'value' => null];
    }

    private function resolveFallbackValue(array $payload, string $targetKey, bool $preserveNull): ?int
    {
        if (array_key_exists($targetKey, $payload)) {
            $normalized = $this->normalize($payload[$targetKey]);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $preserveNull ? null : 0;
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