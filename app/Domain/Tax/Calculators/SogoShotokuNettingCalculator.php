<?php

namespace App\Domain\Tax\Calculators;

use App\Domain\Tax\Calculators\Support\JotoIchijiNetting;
use App\Services\Tax\Contracts\ProvidesKeys;

class SogoShotokuNettingCalculator implements ProvidesKeys
{
    public const ID = 'sogo.shotoku.netting';
    public const ORDER = 4000;
    public const BEFORE = [];
    public const AFTER = [];

    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [];

        foreach (self::PERIODS as $period) {
            $keys[] = sprintf('sashihiki_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('sashihiki_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('tsusango_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('tsusango_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('tsusango_ichiji_%s', $period);
            $keys[] = sprintf('tokubetsukojo_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('tokubetsukojo_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('tokubetsukojo_ichiji_%s', $period);
            $keys[] = sprintf('after_joto_ichiji_tousan_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('after_joto_ichiji_tousan_ichiji_%s', $period);
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string  $period
     * @return array<string, int>
     */
    public function compute(array $payload, string $period): array
    {
        if (! in_array($period, self::PERIODS, true)) {
            return [];
        }

        $shortKey = sprintf('sashihiki_joto_tanki_sogo_%s', $period);
        $longKey = sprintf('sashihiki_joto_choki_sogo_%s', $period);

        $ichijiSourceKey = sprintf('sashihiki_ichiji_%s', $period);

        $short = $this->n($payload[$shortKey] ?? null);
        $long = $this->n($payload[$longKey] ?? null);
        // 仕様：一時は min0（入力SoT側でも min0 だが保険としてここでも矯正）
        $ichiji = max(0, $this->n($payload[$ichijiSourceKey] ?? null));

        $jotoIchiji = JotoIchijiNetting::compute($short, $long, $ichiji);

        $tsusangoShortKey = sprintf('tsusango_joto_tanki_sogo_%s', $period);
        $tsusangoLongKey = sprintf('tsusango_joto_choki_sogo_%s', $period);
        $tokubetsuShortKey = sprintf('tokubetsukojo_joto_tanki_sogo_%s', $period);
        $tokubetsuLongKey = sprintf('tokubetsukojo_joto_choki_sogo_%s', $period);
        $afterShortKey = sprintf('after_joto_ichiji_tousan_joto_tanki_sogo_%s', $period);

        $outputs = [
            $shortKey => $jotoIchiji['sashihiki_joto_tanki_sogo'],
            $longKey => $jotoIchiji['sashihiki_joto_choki_sogo'],
            // 互換：内部通算後（表示列が参照していても事故らないように）
            $tsusangoShortKey => $jotoIchiji['tsusango_joto_tanki_sogo'],
            $tsusangoLongKey => $jotoIchiji['tsusango_joto_choki_sogo'],
            sprintf('tsusango_ichiji_%s', $period) => $jotoIchiji['tsusango_ichiji'],
            $tokubetsuShortKey => $jotoIchiji['tokubetsukojo_joto_tanki_sogo'],
            $tokubetsuLongKey => $jotoIchiji['tokubetsukojo_joto_choki_sogo'],
            sprintf('tokubetsukojo_ichiji_%s', $period) => $jotoIchiji['tokubetsukojo_ichiji'],
            $afterShortKey => $jotoIchiji['after_joto_ichiji_tousan_joto_tanki_sogo'],
            sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period) => $jotoIchiji['after_joto_ichiji_tousan_joto_choki_sogo'],
            sprintf('after_joto_ichiji_tousan_ichiji_%s', $period) => $jotoIchiji['after_joto_ichiji_tousan_ichiji'],
        ];

        return $outputs;
    }

    private function n(mixed $value): int
    {
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
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

        return 0;
    }
}