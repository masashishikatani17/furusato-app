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
            $keys[] = sprintf('after_naibutsusan_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('after_naibutsusan_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('after_naibutsusan_ichiji_%s', $period);
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
        $longKey  = sprintf('sashihiki_joto_choki_sogo_%s', $period);
        $ichijiSourceKey = sprintf('sashihiki_ichiji_%s', $period);

        $short = $this->n($payload[$shortKey] ?? null);
        $long  = $this->n($payload[$longKey] ?? null);
        $ichiji = $this->n($payload[$ichijiSourceKey] ?? null);

        // ============================================================
        // ▼ 内部通算（短期⇔長期）を「after_naibutsusan_*」として SoT 確定
        //   - 一時は仕様どおり min0
        // ============================================================
        [$naibuShort, $naibuLong] = $this->netBetweenShortAndLong($short, $long);
        $naibuIchiji = max(0, (int) $ichiji);

        // 以降（特別控除・譲渡⇔一時通算）は Support へ委譲
        $jotoIchiji = JotoIchijiNetting::compute($short, $long, $ichiji);

        $tsusangoShortKey = sprintf('tsusango_joto_tanki_sogo_%s', $period);
        $tsusangoLongKey = sprintf('tsusango_joto_choki_sogo_%s', $period);
        $tokubetsuShortKey = sprintf('tokubetsukojo_joto_tanki_sogo_%s', $period);
        $tokubetsuLongKey = sprintf('tokubetsukojo_joto_choki_sogo_%s', $period);
        $afterShortKey = sprintf('after_joto_ichiji_tousan_joto_tanki_sogo_%s', $period);

        $outputs = [
            $shortKey => $jotoIchiji['sashihiki_joto_tanki_sogo'],
            $longKey => $jotoIchiji['sashihiki_joto_choki_sogo'],
            sprintf('after_naibutsusan_joto_tanki_sogo_%s', $period) => (int) $naibuShort,
            sprintf('after_naibutsusan_joto_choki_sogo_%s', $period) => (int) $naibuLong,
            sprintf('after_naibutsusan_ichiji_%s',          $period) => (int) $naibuIchiji,
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


    /**
     * 短期⇔長期の内部通算（符号が反対なら相殺して片方が0になるまで）
     * @return array{0:int,1:int} [short, long]
     */
    private function netBetweenShortAndLong(int $short, int $long): array
    {
        $s = $short;
        $l = $long;
        if ($s * $l < 0) {
            $m = min(abs($s), abs($l));
            if ($s < 0) {
                $s += $m;
                $l -= $m;
            } else {
                $s -= $m;
                $l += $m;
            }
        }
        return [(int)$s, (int)$l];
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