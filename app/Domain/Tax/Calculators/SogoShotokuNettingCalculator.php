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
            $keys[] = sprintf('tsusango_joto_tanki_%s', $period);
            $keys[] = sprintf('tsusango_joto_choki_%s', $period);
            $keys[] = sprintf('tsusango_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('tsusango_ichiji_%s', $period);
            $keys[] = sprintf('tokubetsukojo_joto_tanki_%s', $period);
            $keys[] = sprintf('tokubetsukojo_joto_choki_%s', $period);
            $keys[] = sprintf('tokubetsukojo_ichiji_%s', $period);
            $keys[] = sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $period);
            $keys[] = sprintf('after_joto_ichiji_tousan_joto_choki_%s', $period);
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

        $shortSourceKey = sprintf('sashihiki_joto_tanki_%s', $period);
        $longSourceKey = sprintf('sashihiki_joto_choki_%s', $period);
        $ichijiSourceKey = sprintf('sashihiki_ichiji_%s', $period);

        $short = $this->readWithFallback($payload, $shortKey, $shortSourceKey);
        $long = $this->readWithFallback($payload, $longKey, $longSourceKey);
        $ichiji = $this->n($payload[$ichijiSourceKey] ?? null);

        $jotoIchiji = JotoIchijiNetting::compute($short, $long, $ichiji);

        $outputs = [
            $shortKey => $jotoIchiji['sashihiki_joto_tanki_sogo'],
            $longKey => $jotoIchiji['sashihiki_joto_choki_sogo'],
            sprintf('tsusango_joto_tanki_%s', $period) => $jotoIchiji['tsusango_joto_tanki'],
            sprintf('tsusango_joto_choki_sogo_%s', $period) => $jotoIchiji['tsusango_joto_choki_sogo'],
            sprintf('tsusango_ichiji_%s', $period) => $jotoIchiji['tsusango_ichiji'],
            sprintf('tokubetsukojo_joto_tanki_%s', $period) => $jotoIchiji['tokubetsukojo_joto_tanki'],
            sprintf('tokubetsukojo_joto_choki_%s', $period) => $jotoIchiji['tokubetsukojo_joto_choki'],
            sprintf('tokubetsukojo_ichiji_%s', $period) => $jotoIchiji['tokubetsukojo_ichiji'],
            sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $period) => $jotoIchiji['after_joto_ichiji_tousan_joto_tanki'],
            sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period) => $jotoIchiji['after_joto_ichiji_tousan_joto_choki_sogo'],
            sprintf('after_joto_ichiji_tousan_ichiji_%s', $period) => $jotoIchiji['after_joto_ichiji_tousan_ichiji'],
        ];

        // --- Add aliases for UI (result_details.blade.php) ---
        $tsusangoChokiKeySogo = sprintf('tsusango_joto_choki_sogo_%s', $period);
        $tsusangoChokiKey = sprintf('tsusango_joto_choki_%s', $period);
        if (array_key_exists($tsusangoChokiKeySogo, $outputs)) {
            $outputs[$tsusangoChokiKey] = $outputs[$tsusangoChokiKeySogo];
        }

        $afterChokiSogoKey = sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period);
        $afterChokiKey = sprintf('after_joto_ichiji_tousan_joto_choki_%s', $period);
        if (array_key_exists($afterChokiSogoKey, $outputs)) {
            $outputs[$afterChokiKey] = $outputs[$afterChokiSogoKey];
        }

        $tsusangoIchijiKey = sprintf('tsusango_ichiji_%s', $period);
        if (array_key_exists($tsusangoIchijiKey, $outputs)) {
            $outputs[$tsusangoIchijiKey] = max(0, (int) $outputs[$tsusangoIchijiKey]);
        }

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

    private function readWithFallback(array $payload, string $primary, ?string $fallback = null): int
    {
        if (array_key_exists($primary, $payload)) {
            return $this->n($payload[$primary]);
        }

        if ($fallback !== null) {
            return $this->n($payload[$fallback] ?? null);
        }

        return 0;
    }
}