<?php

namespace App\Domain\Tax\Support;

class PayloadNormalizer
{
    /**
     * Normalize incoming payloads into their canonical representation.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        $payload = $this->migrateShotokuIchijiKeys($payload);
        $payload = $this->migrateKojoShogaishaKeys($payload);
        $payload = $this->normalizeBunriChokiShotokuKeys($payload);
        $payload = $this->normalizeBunriIncomeShotokuKeys($payload);

        foreach ($payload as $key => $value) {
            if ($this->isLabelField($key)) {
                continue;
            }

            $payload[$key] = $this->normalizeValue($value);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function migrateShotokuIchijiKeys(array $payload): array
    {
        $mapping = [];
        foreach (['shotoku', 'jumin'] as $tax) {
            foreach (['prev', 'curr'] as $period) {
                $legacy = sprintf('shotoku_ichiji_%s_%s', $tax, $period);
                $canonical = sprintf('shotoku_joto_ichiji_%s_%s', $tax, $period);
                $mapping[$legacy] = $canonical;
            }
        }

        foreach ($mapping as $legacy => $canonical) {
            if (! array_key_exists($legacy, $payload)) {
                continue;
            }

            if (! array_key_exists($canonical, $payload)) {
                $payload[$canonical] = $payload[$legacy];
            }

            unset($payload[$legacy]);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function migrateKojoShogaishaKeys(array $payload): array
    {
        $periods = ['prev', 'curr'];
        $taxTypes = ['shotoku', 'jumin'];

        foreach ($taxTypes as $tax) {
            foreach ($periods as $period) {
                $legacy = sprintf('kojo_shogaisyo_%s_%s', $tax, $period);
                $canonical = sprintf('kojo_shogaisha_%s_%s', $tax, $period);

                $hasCanonical = array_key_exists($canonical, $payload);
                $hasLegacy = array_key_exists($legacy, $payload);

                if (! $hasCanonical && $hasLegacy) {
                    $payload[$canonical] = $payload[$legacy];
                }

                if ($hasLegacy) {
                    unset($payload[$legacy]);
                }
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeBunriChokiShotokuKeys(array $payload): array
    {
        $types = ['tokutei', 'keika'];
        $periods = ['prev', 'curr'];

        foreach ($types as $type) {
            foreach ($periods as $period) {
                $legacyKey = sprintf('bunri_choki_%s_shotoku_%s', $type, $period);
                $underKey = sprintf('bunri_choki_%s_under_shotoku_%s', $type, $period);
                $overKey = sprintf('bunri_choki_%s_over_shotoku_%s', $type, $period);

                $legacyExists = array_key_exists($legacyKey, $payload);
                $underExists = array_key_exists($underKey, $payload);

                $legacyValue = $legacyExists ? $this->normalizeValue($payload[$legacyKey]) : null;
                $underValue = $underExists ? $this->normalizeValue($payload[$underKey]) : null;

                if ($legacyExists && $underValue === null) {
                    $payload[$underKey] = $legacyValue;
                }

                if ($legacyExists) {
                    unset($payload[$legacyKey]);
                }

                if (array_key_exists($overKey, $payload)) {
                    $payload[$overKey] = $this->normalizeValue($payload[$overKey]);
                }
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeBunriIncomeShotokuKeys(array $payload): array
    {
        foreach (['prev', 'curr'] as $period) {
            $tokuteiJuminKey = sprintf('bunri_choki_tokutei_jumin_%s', $period);
            if (array_key_exists($tokuteiJuminKey, $payload)) {
                $value = $this->normalizeValue($payload[$tokuteiJuminKey]);
                foreach (['over', 'under'] as $suffix) {
                    $canonical = sprintf('bunri_shotoku_choki_tokutei_%s_jumin_%s', $suffix, $period);
                    if (! array_key_exists($canonical, $payload)) {
                        $payload[$canonical] = $value;
                    }
                }
                unset($payload[$tokuteiJuminKey]);
            }

            $keikaJuminKey = sprintf('bunri_choki_keika_jumin_%s', $period);
            if (array_key_exists($keikaJuminKey, $payload)) {
                $value = $this->normalizeValue($payload[$keikaJuminKey]);
                foreach (['over', 'under'] as $suffix) {
                    $canonical = sprintf('bunri_shotoku_choki_keika_%s_jumin_%s', $suffix, $period);
                    if (! array_key_exists($canonical, $payload)) {
                        $payload[$canonical] = $value;
                    }
                }
                unset($payload[$keikaJuminKey]);
            }
        }

        $parts = [
            'tanki_ippan',
            'tanki_keigen',
            'choki_ippan',
            'choki_tokutei_over',
            'choki_tokutei_under',
            'choki_keika_over',
            'choki_keika_under',
            'ippan_kabuteki_joto',
            'jojo_kabuteki_joto',
            'jojo_kabuteki_haito',
            'sakimono',
            'sanrin',
            'taishoku',
        ];

        foreach ($parts as $part) {
            foreach (['shotoku', 'jumin'] as $tax) {
                foreach (['prev', 'curr'] as $period) {
                    $canonicalKey = sprintf('bunri_shotoku_%s_%s_%s', $part, $tax, $period);

                    if (array_key_exists($canonicalKey, $payload)) {
                        $payload[$canonicalKey] = $this->normalizeValue($payload[$canonicalKey]);
                        continue;
                    }

                    $legacyKey = sprintf('bunri_%s_%s_%s', $part, $tax, $period);
                    if (! array_key_exists($legacyKey, $payload)) {
                        continue;
                    }

                    $payload[$canonicalKey] = $this->normalizeValue($payload[$legacyKey]);
                    unset($payload[$legacyKey]);
                }
            }
        }

        return $payload;
    }

    private function isLabelField(string $key): bool
    {
        return str_contains($key, '_label_');
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            if (is_numeric($trimmed)) {
                return (int) $trimmed;
            }

            return $trimmed;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return $value;
    }
}