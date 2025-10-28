<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

final class ResultToDetailsAliasCalculator implements ProvidesKeys
{
    public const ID = 'furusato.result-to-details-alias';
    public const ORDER = 4040;

    private const PERIODS = ['prev', 'curr'];
    private const SANRIN_TOKUBETSU_LIMIT = 500_000;

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [];

        foreach (self::PERIODS as $period) {
            $keys[] = sprintf('tsusango_sanrin_%s', $period);
            $keys[] = sprintf('tokubetsukojo_sanrin_%s', $period);
            $keys[] = sprintf('shotoku_sanrin_%s', $period);

            $keys[] = sprintf('after_naibutsusan_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('after_naibutsusan_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('after_naibutsusan_ichiji_%s', $period);
            $keys[] = sprintf('tokubetsukojo_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('tokubetsukojo_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('tokubetsukojo_ichiji_%s', $period);
            $keys[] = sprintf('after_joto_ichiji_tousan_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('after_joto_ichiji_tousan_ichiji_%s', $period);
            $keys[] = sprintf('tsusango_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('tsusango_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('tsusango_ichiji_%s', $period);
            $keys[] = sprintf('shotoku_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('shotoku_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('shotoku_ichiji_%s', $period);

            $keys[] = sprintf('tsusango_tanki_ippan_%s', $period);
            $keys[] = sprintf('tsusango_tanki_keigen_%s', $period);
            $keys[] = sprintf('tsusango_choki_ippan_%s', $period);
            $keys[] = sprintf('tsusango_choki_tokutei_%s', $period);
            $keys[] = sprintf('tsusango_choki_keika_%s', $period);
            $keys[] = sprintf('tokubetsukojo_tanki_ippan_%s', $period);
            $keys[] = sprintf('tokubetsukojo_tanki_keigen_%s', $period);
            $keys[] = sprintf('tokubetsukojo_choki_ippan_%s', $period);
            $keys[] = sprintf('tokubetsukojo_choki_tokutei_%s', $period);
            $keys[] = sprintf('tokubetsukojo_choki_keika_%s', $period);
            $keys[] = sprintf('joto_shotoku_tanki_ippan_%s', $period);
            $keys[] = sprintf('joto_shotoku_tanki_keigen_%s', $period);
            $keys[] = sprintf('joto_shotoku_choki_ippan_%s', $period);
            $keys[] = sprintf('joto_shotoku_choki_tokutei_%s', $period);
            $keys[] = sprintf('joto_shotoku_choki_keika_%s', $period);

            $keys[] = sprintf('tsusango_jojo_joto_%s', $period);
            $keys[] = sprintf('tsusango_jojo_haito_%s', $period);
            $keys[] = sprintf('tsusango_ippan_joto_%s', $period);
            $keys[] = sprintf('shotoku_after_kurikoshi_jojo_joto_%s', $period);
            $keys[] = sprintf('shotoku_after_kurikoshi_ippan_joto_%s', $period);
            $keys[] = sprintf('shotoku_after_kurikoshi_jojo_haito_%s', $period);
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, int>
    */
    public function compute(array $payload, array $context = []): array
    {
        // 追加：既定で全提供キーを 0 で初期化（単体実行でも keys を必ず埋める）
        $defaults = array_fill_keys(self::provides(), 0);
        $updates = [];

        $updates = array_replace(
            $updates,
            $this->computeSanrin($payload, 'prev'),
            $this->computeSanrin($payload, 'curr'),
            $this->computeJotoIchiji($payload, 'prev'),
            $this->computeJotoIchiji($payload, 'curr'),
            $this->computeBunriJoto($payload, 'prev'),
            $this->computeBunriJoto($payload, 'curr'),
            $this->computeKabuteki($payload, 'prev'),
            $this->computeKabuteki($payload, 'curr')
        );

        return array_replace($payload, $defaults, $updates);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string  $period
     * @return array<string, int>
     */
    private function computeSanrin(array $payload, string $period): array
    {
        $afterKey = sprintf('after_1jitsusan_sanrin_%s', $period);
        $tsusango = $this->toInt($payload[$afterKey] ?? null);
        $tokubetsu = min(self::SANRIN_TOKUBETSU_LIMIT, max(0, $tsusango));

        return [
            sprintf('tsusango_sanrin_%s', $period) => $tsusango,
            sprintf('tokubetsukojo_sanrin_%s', $period) => $tokubetsu,
            sprintf('shotoku_sanrin_%s', $period) => $tsusango - $tokubetsu,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string  $period
     * @return array<string, int>
     */
    private function computeJotoIchiji(array $payload, string $period): array
    {
        $tsusangoShortKey = sprintf('tsusango_joto_tanki_sogo_%s', $period);
        $tsusangoLongKey = sprintf('tsusango_joto_choki_sogo_%s', $period);

        $afterNaibutsuShortRaw = $this->normalizeNullable($payload[sprintf('after_naibutsusan_joto_tanki_sogo_%s', $period)] ?? null);
        $afterNaibutsuLongRaw = $this->normalizeNullable($payload[sprintf('after_naibutsusan_joto_choki_sogo_%s', $period)] ?? null);
        $afterNaibutsuOne = $this->toInt($payload[sprintf('after_naibutsusan_ichiji_%s', $period)] ?? null);

        $tsusangoShortRaw = $this->normalizeNullable($payload[$tsusangoShortKey] ?? null);
        $tsusangoLongRaw = $this->normalizeNullable($payload[$tsusangoLongKey] ?? null);

        if ($tsusangoShortRaw === null) {
            foreach ([
                'after_3jitsusan_joto_tanki_sogo_%s',
                'after_3jitsusan_tanki_sogo_%s',
                'after_3jitsusan_joto_tanki_%s',
                'after_3jitsusan_tanki_%s',
            ] as $format) {
                $candidate = $this->normalizeNullable($payload[sprintf($format, $period)] ?? null);
                if ($candidate !== null) {
                    $tsusangoShortRaw = $candidate;
                    break;
                }
            }
        }

        if ($tsusangoLongRaw === null) {
            foreach ([
                'after_3jitsusan_joto_choki_sogo_%s',
                'after_3jitsusan_choki_sogo_%s',
                'after_3jitsusan_joto_choki_%s',
                'after_3jitsusan_choki_%s',
            ] as $format) {
                $candidate = $this->normalizeNullable($payload[sprintf($format, $period)] ?? null);
                if ($candidate !== null) {
                    $tsusangoLongRaw = $candidate;
                    break;
                }
            }
        }

        if (($afterNaibutsuShortRaw === null || $afterNaibutsuShortRaw === 0) && $tsusangoShortRaw !== null) {
            $afterNaibutsuShortRaw = $tsusangoShortRaw;
        }

        if (($afterNaibutsuLongRaw === null || $afterNaibutsuLongRaw === 0) && $tsusangoLongRaw !== null) {
            $afterNaibutsuLongRaw = $tsusangoLongRaw;
        }

        $afterNaibutsuShort = $afterNaibutsuShortRaw ?? 0;
        $afterNaibutsuLong = $afterNaibutsuLongRaw ?? 0;

        $tokubetsuShort = $this->toInt($payload[sprintf('tokubetsukojo_joto_tanki_sogo_%s', $period)] ?? null);
        $tokubetsuLong = $this->toInt($payload[sprintf('tokubetsukojo_joto_choki_sogo_%s', $period)] ?? null);
        $tokubetsuOne = $this->toInt($payload[sprintf('tokubetsukojo_ichiji_%s', $period)] ?? null);

        $afterTousanShort = $this->toInt($payload[sprintf('after_joto_ichiji_tousan_joto_tanki_sogo_%s', $period)] ?? null);
        $afterTousanLong = $this->toInt($payload[sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period)] ?? null);
        $afterTousanOne = $this->toInt($payload[sprintf('after_joto_ichiji_tousan_ichiji_%s', $period)] ?? null);

        $tsusangoShort = $tsusangoShortRaw ?? 0;
        $tsusangoLong = $tsusangoLongRaw ?? 0;
        $tsusangoOne = $this->toInt($payload[sprintf('after_3jitsusan_ichiji_%s', $period)] ?? null);

        $shotokuShort = $this->toInt($payload[sprintf('shotoku_joto_tanki_sogo_%s', $period)] ?? ($payload[sprintf('shotoku_joto_tanki_%s', $period)] ?? null));
        $shotokuLong = $this->toInt($payload[sprintf('shotoku_joto_choki_sogo_%s', $period)] ?? ($payload[sprintf('shotoku_joto_choki_%s', $period)] ?? null));
        $shotokuOne = $this->toInt($payload[sprintf('shotoku_ichiji_%s', $period)] ?? null);

        return [
            sprintf('after_naibutsusan_joto_tanki_sogo_%s', $period) => $afterNaibutsuShort,
            sprintf('after_naibutsusan_joto_choki_sogo_%s', $period) => $afterNaibutsuLong,
            sprintf('after_naibutsusan_ichiji_%s', $period) => $afterNaibutsuOne,
            sprintf('tokubetsukojo_joto_tanki_sogo_%s', $period) => $tokubetsuShort,
            sprintf('tokubetsukojo_joto_choki_sogo_%s', $period) => $tokubetsuLong,
            sprintf('tokubetsukojo_ichiji_%s', $period) => $tokubetsuOne,
            sprintf('after_joto_ichiji_tousan_joto_tanki_sogo_%s', $period) => $afterTousanShort,
            sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period) => $afterTousanLong,
            sprintf('after_joto_ichiji_tousan_ichiji_%s', $period) => $afterTousanOne,
            $tsusangoShortKey => $tsusangoShort,
            $tsusangoLongKey => $tsusangoLong,
            sprintf('tsusango_ichiji_%s', $period) => $tsusangoOne,
            sprintf('shotoku_joto_tanki_sogo_%s', $period) => $shotokuShort,
            sprintf('shotoku_joto_choki_sogo_%s', $period) => $shotokuLong,
            sprintf('shotoku_ichiji_%s', $period) => $shotokuOne,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string  $period
     * @return array<string, int>
     */
    private function computeBunriJoto(array $payload, string $period): array
    {
        $rows = [
            'tanki_ippan',
            'tanki_keigen',
            'choki_ippan',
            'choki_tokutei',
            'choki_keika',
        ];

        $updates = [];

        foreach ($rows as $row) {
            $tsusangoKey = sprintf('after_2jitsusan_%s_%s', $row, $period);
            $tokubetsuKey = sprintf('tokubetsukojo_%s_%s', $row, $period);

            $tsusango = $this->toInt($payload[$tsusangoKey] ?? null);
            $tokubetsuRaw = $this->normalizeNullable($payload[$tokubetsuKey] ?? null);
            $tokubetsu = min(max($tokubetsuRaw ?? 0, 0), max($tsusango, 0));
            $shotoku = $tsusango - $tokubetsu;

            $updates[sprintf('tsusango_%s_%s', $row, $period)] = $tsusango;
            $updates[$tokubetsuKey] = $tokubetsu;
            $updates[sprintf('joto_shotoku_%s_%s', $row, $period)] = $shotoku;
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string  $period
     * @return array<string, int>
     */
    private function computeKabuteki(array $payload, string $period): array
    {
        $listedTransfer = $this->toInt($payload[sprintf('after_tsusan_jojo_joto_%s', $period)] ?? null);
        $listedDividend = $this->toInt($payload[sprintf('after_tsusan_jojo_haito_%s', $period)] ?? null);
        $generalTransfer = $this->toInt($payload[sprintf('shotoku_ippan_joto_%s', $period)] ?? null);

        $tsusangoGeneral = $generalTransfer;

        $carryForwardRaw = $this->normalizeNullable($payload[sprintf('kurikoshi_jojo_joto_%s', $period)] ?? null);
        $carryForward = max($carryForwardRaw ?? 0, 0);
        $listedDeduction = min(max($listedTransfer, 0), $carryForward);

        return [
            sprintf('tsusango_jojo_joto_%s', $period) => $listedTransfer,
            sprintf('tsusango_jojo_haito_%s', $period) => $listedDividend,
            sprintf('tsusango_ippan_joto_%s', $period) => $tsusangoGeneral,
            sprintf('shotoku_after_kurikoshi_jojo_joto_%s', $period) => $listedTransfer - $listedDeduction,
            sprintf('shotoku_after_kurikoshi_ippan_joto_%s', $period) => $tsusangoGeneral,
            sprintf('shotoku_after_kurikoshi_jojo_haito_%s', $period) => $listedDividend,
        ];
    }

    private function toInt(mixed $value): int
    {
        $normalized = $this->normalizeNullable($value);

        return $normalized ?? 0;
    }

    private function normalizeNullable(mixed $value): ?int
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