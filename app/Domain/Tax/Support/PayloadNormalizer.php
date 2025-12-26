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
        $payload = $this->normalizeBunriChokiSyunyuKeys($payload);
        $payload = $this->normalizeBunriChokiShotokuKeys($payload);
        $payload = $this->normalizeBunriIncomeShotokuKeys($payload);
        $payload = $this->normalizeKifukinJuminzeiKeys($payload);
        $payload = $this->normalizeShotokuwariKeys($payload);

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
    private function normalizeShotokuwariKeys(array $payload): array
    {
        $keys = array_keys($payload);

        foreach ($keys as $key) {
            if (! str_contains($key, 'shotowari')) {
                continue;
            }

            $canonical = str_replace('shotowari', 'shotokuwari', $key);

            if (! array_key_exists($canonical, $payload)) {
                $payload[$canonical] = $payload[$key];
            }

            unset($payload[$key]);
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
        $taxes = ['shotoku', 'jumin'];
        $periods = ['prev', 'curr'];

        foreach ($types as $type) {
            foreach ($taxes as $tax) {
                foreach ($periods as $period) {
                    $canonicalKey = sprintf('bunri_shotoku_choki_%s_%s_%s', $type, $tax, $period);
                    $canonicalExists = array_key_exists($canonicalKey, $payload);
                    $canonicalValue = $canonicalExists ? $this->normalizeValue($payload[$canonicalKey]) : null;

                    if ($canonicalExists) {
                        $payload[$canonicalKey] = $canonicalValue;
                    }

                    $legacyKeys = [
                        sprintf('bunri_shotoku_choki_%s_over_%s_%s', $type, $tax, $period),
                        sprintf('bunri_shotoku_choki_%s_under_%s_%s', $type, $tax, $period),
                        sprintf('bunri_choki_%s_over_%s_%s', $type, $tax, $period),
                        sprintf('bunri_choki_%s_under_%s_%s', $type, $tax, $period),
                    ];

                    if ($tax === 'shotoku') {
                        $legacyKeys[] = sprintf('bunri_choki_%s_shotoku_%s', $type, $period);
                    }

                    $legacySum = null;
                    $hasLegacy = false;

                    foreach ($legacyKeys as $legacyKey) {
                        if (! array_key_exists($legacyKey, $payload)) {
                            continue;
                        }

                        $hasLegacy = true;
                        $value = $this->normalizeValue($payload[$legacyKey]) ?? 0;
                        $legacySum = ($legacySum ?? 0) + $value;
                        unset($payload[$legacyKey]);
                    }

                    if ($canonicalValue !== null) {
                        continue;
                    }

                    if ($hasLegacy) {
                        $payload[$canonicalKey] = $legacySum ?? 0;
                    }
                }
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeBunriChokiSyunyuKeys(array $payload): array
    {
        $types = ['tokutei', 'keika'];
        $taxes = ['shotoku', 'jumin'];
        $periods = ['prev', 'curr'];

        foreach ($types as $type) {
            foreach ($taxes as $tax) {
                foreach ($periods as $period) {
                    $canonicalKey = sprintf('bunri_syunyu_choki_%s_%s_%s', $type, $tax, $period);
                    $canonicalExists = array_key_exists($canonicalKey, $payload);
                    $canonicalValue = $canonicalExists ? $this->normalizeValue($payload[$canonicalKey]) : null;

                    if ($canonicalExists) {
                        $payload[$canonicalKey] = $canonicalValue;
                    }

                    $legacyKeys = [
                        sprintf('bunri_syunyu_choki_%s_over_%s_%s', $type, $tax, $period),
                        sprintf('bunri_syunyu_choki_%s_under_%s_%s', $type, $tax, $period),
                    ];

                    $legacySum = null;
                    $hasLegacy = false;

                    foreach ($legacyKeys as $legacyKey) {
                        if (! array_key_exists($legacyKey, $payload)) {
                            continue;
                        }

                        $hasLegacy = true;
                        $value = $this->normalizeValue($payload[$legacyKey]) ?? 0;
                        $legacySum = ($legacySum ?? 0) + $value;
                        unset($payload[$legacyKey]);
                    }

                    if ($canonicalValue !== null) {
                        continue;
                    }

                    if ($hasLegacy) {
                        $payload[$canonicalKey] = $legacySum ?? 0;
                    }
                }
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeKifukinJuminzeiKeys(array $payload): array
    {
        $categories = [
            'furusato',
            'kyodobokin_nisseki',
            'seito',
            'npo',
            'koueki',
            'kuni',
            'sonota',
        ];
        $periods = ['prev', 'curr'];

        foreach ($categories as $category) {
            foreach ($periods as $period) {
                $legacyKey = sprintf('juminzei_zeigakukojo_%s_%s', $category, $period);
                $prefKey = sprintf('juminzei_zeigakukojo_pref_%s_%s', $category, $period);
                $muniKey = sprintf('juminzei_zeigakukojo_muni_%s_%s', $category, $period);

                $prefValue = null;
                $muniValue = null;

                if (array_key_exists($prefKey, $payload)) {
                    $prefValue = (int) ($this->normalizeValue($payload[$prefKey]) ?? 0);
                }

                if (array_key_exists($muniKey, $payload)) {
                    $muniValue = (int) ($this->normalizeValue($payload[$muniKey]) ?? 0);
                }

                if (array_key_exists($legacyKey, $payload)) {
                    $legacyValue = (int) ($this->normalizeValue($payload[$legacyKey]) ?? 0);

                    if ($prefValue === null && $muniValue === null) {
                        $prefValue = 0;
                        $muniValue = $legacyValue;
                    }

                    unset($payload[$legacyKey]);
                }

                if ($prefValue !== null || $muniValue !== null) {
                    $payload[$prefKey] = $prefValue ?? 0;
                    $payload[$muniKey] = $muniValue ?? 0;
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
                $canonical = sprintf('bunri_shotoku_choki_tokutei_jumin_%s', $period);
                if (! array_key_exists($canonical, $payload)) {
                    $payload[$canonical] = $value;
                }
                unset($payload[$tokuteiJuminKey]);
            }

            $keikaJuminKey = sprintf('bunri_choki_keika_jumin_%s', $period);
            if (array_key_exists($keikaJuminKey, $payload)) {
                $value = $this->normalizeValue($payload[$keikaJuminKey]);
                $canonical = sprintf('bunri_shotoku_choki_keika_jumin_%s', $period);
                if (! array_key_exists($canonical, $payload)) {
                    $payload[$canonical] = $value;
                }
                unset($payload[$keikaJuminKey]);
            }
        }

        $parts = [
            'tanki_ippan',
            'tanki_keigen',
            'choki_ippan',
            'choki_tokutei',
            'choki_keika',
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

            // ▼ 数値は「整数→int」「小数→float」で保持する（小数を int に潰さない）
            //    例: "59.370" => 59.37(float), "0.7" => 0.7(float), "100" => 100(int)
            //    例: "1,234"  => 1234(int)
            $normalized = str_replace([',', ' '], '', $trimmed);
            if (is_numeric($normalized)) {
                // 整数判定（符号付き）
                if (preg_match('/^-?\d+$/', $normalized) === 1) {
                    return (int) $normalized;
                }
                return (float) $normalized;
            }

            // 非数値はそのまま（例：父/母/〇/× 等）
            return $trimmed;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            // ▼ float は float のまま保持（小数を保持する）
            return $value;
        }

        return $value;
    }
}