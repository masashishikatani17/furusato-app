<?php

declare(strict_types=1);

namespace App\Services\Tax\Kojo;

use App\Services\Tax\Contracts\ProvidesKeys;

final class JintekiKojoService implements ProvidesKeys
{
    private const THRESHOLD = 5_000_000;

    private const PERIODS = ['prev', 'curr'];

    /**
     * @var string[]
     */
    private const TOTAL_BASE_KEYS = [
        'shotoku_gokei_shotoku',
        'bunri_tanki_ippan_shotoku',
        'bunri_tanki_keigen_shotoku',
        'bunri_choki_ippan_shotoku',
        'bunri_choki_tokutei_under_shotoku',
        'bunri_choki_tokutei_over_shotoku',
        'bunri_choki_keika_under_shotoku',
        'bunri_choki_keika_over_shotoku',
        'bunri_ippan_kabuteki_joto_shotoku',
        'bunri_jojo_kabuteki_joto_shotoku',
        'bunri_jojo_kabuteki_haito_shotoku',
        'bunri_sakimono_shotoku',
        'bunri_sanrin_shotoku',
        'bunri_taishoku_shotoku',
    ];

    private const KAFU_SHOTOKU = 270_000;
    private const KAFU_JUMIN = 260_000;

    private const HITORIOYA_SHOTOKU = 350_000;
    private const HITORIOYA_JUMIN = 300_000;

    private const KINROGAKUSEI_SHOTOKU = 270_000;
    private const KINROGAKUSEI_JUMIN = 260_000;

    /**
     * @return array<string, int>
     */
    public function compute(array $payload): array
    {
        $result = array_fill_keys(self::provides(), 0);

        foreach (self::PERIODS as $period) {
            $total = $this->calculateTotal($payload, $period);

            if ($this->isApplicable($payload, sprintf('kojo_kafu_applicable_%s', $period)) && $total <= self::THRESHOLD) {
                $result[sprintf('kojo_kafu_shotoku_%s', $period)] = self::KAFU_SHOTOKU;
                $result[sprintf('kojo_kafu_jumin_%s', $period)] = self::KAFU_JUMIN;
            }

            if ($this->isApplicable($payload, sprintf('kojo_hitorioya_applicable_%s', $period)) && $total <= self::THRESHOLD) {
                $result[sprintf('kojo_hitorioya_shotoku_%s', $period)] = self::HITORIOYA_SHOTOKU;
                $result[sprintf('kojo_hitorioya_jumin_%s', $period)] = self::HITORIOYA_JUMIN;
            }

            if ($this->isApplicable($payload, sprintf('kojo_kinrogakusei_applicable_%s', $period))) {
                $result[sprintf('kojo_kinrogakusei_shotoku_%s', $period)] = self::KINROGAKUSEI_SHOTOKU;
                $result[sprintf('kojo_kinrogakusei_jumin_%s', $period)] = self::KINROGAKUSEI_JUMIN;
            }
        }

        return $result;
    }

    public static function provides(): array
    {
        return [
            'kojo_kafu_shotoku_prev', 'kojo_kafu_shotoku_curr',
            'kojo_kafu_jumin_prev', 'kojo_kafu_jumin_curr',
            'kojo_hitorioya_shotoku_prev', 'kojo_hitorioya_shotoku_curr',
            'kojo_hitorioya_jumin_prev', 'kojo_hitorioya_jumin_curr',
            'kojo_kinrogakusei_shotoku_prev', 'kojo_kinrogakusei_shotoku_curr',
            'kojo_kinrogakusei_jumin_prev', 'kojo_kinrogakusei_jumin_curr',
        ];
    }

    private function calculateTotal(array $payload, string $period): int
    {
        $total = 0;

        foreach (self::TOTAL_BASE_KEYS as $base) {
            $total += $this->n($payload[sprintf('%s_%s', $base, $period)] ?? null);
        }

        return $total;
    }

    private function isApplicable(array $payload, string $key): bool
    {
        return ($payload[$key] ?? null) === '〇';
    }

    private function n(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        return is_numeric($value) ? (int) floor((float) $value) : 0;
    }
}