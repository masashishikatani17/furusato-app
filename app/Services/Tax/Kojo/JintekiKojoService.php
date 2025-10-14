<?php

declare(strict_types=1);

namespace App\Services\Tax\Kojo;

use App\Services\Tax\Contracts\ProvidesKeys;

final class JintekiKojoService implements ProvidesKeys
{
    private const THRESHOLD = 5_000_000;

    private const PERIODS = ['prev', 'curr'];

    private const FUYO_COUNT_KEYS = [
        'ippan' => 'kojo_fuyo_ippan_count_%s',
        'tokutei' => 'kojo_fuyo_tokutei_count_%s',
        'roujin_doukyo' => 'kojo_fuyo_roujin_doukyo_count_%s',
        'roujin_sonota' => 'kojo_fuyo_roujin_sonota_count_%s',
    ];

    private const FUYO_SHOTOKU_AMOUNTS = [
        'ippan' => 380_000,
        'tokutei' => 630_000,
        'roujin_doukyo' => 480_000,
        'roujin_sonota' => 580_000,
    ];

    private const FUYO_JUMIN_AMOUNTS = [
        'ippan' => 330_000,
        'tokutei' => 450_000,
        'roujin_doukyo' => 380_000,
        'roujin_sonota' => 450_000,
    ];

    private const TOKUTEI_SHINZOKU_KEYS = [
        'kojo_tokutei_shinzoku_1_shotoku_%s',
        'kojo_tokutei_shinzoku_2_shotoku_%s',
        'kojo_tokutei_shinzoku_3_shotoku_%s',
    ];

    private const TOKUTEI_THRESHOLDS = [
        0,
        580_001,
        850_001,
        900_001,
        950_001,
        1_000_001,
        1_050_001,
        1_100_001,
        1_150_001,
        1_200_001,
        1_230_001,
    ];

    private const TOKUTEI_VALUES = [
        0,
        630_000,
        610_000,
        510_000,
        410_000,
        310_000,
        210_000,
        110_000,
        60_000,
        30_000,
        0,
    ];

    /**
     * @var string[]
     */
    private const TOTAL_BASE_KEYS = [
        'shotoku_gokei_shotoku',
        'bunri_shotoku_tanki_ippan_shotoku',
        'bunri_shotoku_tanki_keigen_shotoku',
        'bunri_shotoku_choki_ippan_shotoku',
        'bunri_shotoku_choki_tokutei_shotoku',
        'bunri_shotoku_choki_keika_shotoku',
        'bunri_shotoku_ippan_kabuteki_joto_shotoku',
        'bunri_shotoku_jojo_kabuteki_joto_shotoku',
        'bunri_shotoku_jojo_kabuteki_haito_shotoku',
        'bunri_shotoku_sakimono_shotoku',
        'bunri_shotoku_sanrin_shotoku',
        'bunri_shotoku_taishoku_shotoku',
    ];

    private const KAFU_SHOTOKU = 270_000;
    private const KAFU_JUMIN = 260_000;

    private const HITORIOYA_SHOTOKU = 350_000;
    private const HITORIOYA_JUMIN = 300_000;

    private const KINROGAKUSEI_SHOTOKU = 270_000;
    private const KINROGAKUSEI_JUMIN = 260_000;

    private const SHOGAISHA_SHOTOKU = 270_000;
    private const SHOGAISHA_JUMIN = 260_000;

    private const TOKUBETSU_SHOGAISHA_SHOTOKU = 400_000;
    private const TOKUBETSU_SHOGAISHA_JUMIN = 300_000;

    private const DOUKYO_TOKUBETSU_SHOGAISHA_SHOTOKU = 750_000;
    private const DOUKYO_TOKUBETSU_SHOGAISHA_JUMIN = 530_000;

    /**
     * @return array<string, int>
     */
    public function compute(array $payload, ?int $kihuYear = null): array
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

            $shogaishaCount = $this->n($payload[sprintf('kojo_shogaisha_count_%s', $period)] ?? null);
            $tokubetsuShogaishaCount = $this->n($payload[sprintf('kojo_tokubetsu_shogaisha_count_%s', $period)] ?? null);
            $doukyoTokubetsuShogaishaCount = $this->n($payload[sprintf('kojo_doukyo_tokubetsu_shogaisha_count_%s', $period)] ?? null);

            $shotoku = $shogaishaCount * self::SHOGAISHA_SHOTOKU
                + $tokubetsuShogaishaCount * self::TOKUBETSU_SHOGAISHA_SHOTOKU
                + $doukyoTokubetsuShogaishaCount * self::DOUKYO_TOKUBETSU_SHOGAISHA_SHOTOKU;

            $jumin = $shogaishaCount * self::SHOGAISHA_JUMIN
                + $tokubetsuShogaishaCount * self::TOKUBETSU_SHOGAISHA_JUMIN
                + $doukyoTokubetsuShogaishaCount * self::DOUKYO_TOKUBETSU_SHOGAISHA_JUMIN;

            $result[sprintf('kojo_shogaisyo_shotoku_%s', $period)] = $shotoku;
            $result[sprintf('kojo_shogaisyo_jumin_%s', $period)] = $jumin;

            $fuyoShotoku = 0;
            $fuyoJumin = 0;

            foreach (self::FUYO_COUNT_KEYS as $category => $format) {
                $count = $this->n($payload[sprintf($format, $period)] ?? null);
                $fuyoShotoku += $count * self::FUYO_SHOTOKU_AMOUNTS[$category];
                $fuyoJumin += $count * self::FUYO_JUMIN_AMOUNTS[$category];
            }

            $result[sprintf('kojo_fuyo_shotoku_%s', $period)] = $fuyoShotoku;
            $result[sprintf('kojo_fuyo_jumin_%s', $period)] = $fuyoJumin;

            if ($period === 'prev' && $kihuYear === 2025) {
                $tokuteiShotoku = 0;
            } else {
                $tokuteiShotoku = 0;
                foreach (self::TOKUTEI_SHINZOKU_KEYS as $format) {
                    $income = $this->n($payload[sprintf($format, $period)] ?? null);
                    $tokuteiShotoku += $this->matchIndex($income, self::TOKUTEI_THRESHOLDS, self::TOKUTEI_VALUES);
                }
            }

            $result[sprintf('kojo_tokutei_shinzoku_shotoku_%s', $period)] = $tokuteiShotoku;
            $result[sprintf('kojo_tokutei_shinzoku_jumin_%s', $period)] = 0;
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
            'kojo_shogaisyo_shotoku_prev', 'kojo_shogaisyo_shotoku_curr',
            'kojo_shogaisyo_jumin_prev', 'kojo_shogaisyo_jumin_curr',
            'kojo_fuyo_shotoku_prev', 'kojo_fuyo_shotoku_curr',
            'kojo_fuyo_jumin_prev', 'kojo_fuyo_jumin_curr',
            'kojo_tokutei_shinzoku_shotoku_prev', 'kojo_tokutei_shinzoku_shotoku_curr',
            'kojo_tokutei_shinzoku_jumin_prev', 'kojo_tokutei_shinzoku_jumin_curr',
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


    /**
     * @param array<int, int> $thresholds
     * @param array<int, int> $values
     */
    private function matchIndex(int $value, array $thresholds, array $values): int
    {
        $result = 0;

        foreach ($thresholds as $index => $threshold) {
            if ($value >= $threshold) {
                $result = $values[$index] ?? $result;
            } else {
                break;
            }
        }

        return $result;
    }
}