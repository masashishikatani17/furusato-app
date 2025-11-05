<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;
use Illuminate\Support\Facades\Log;

class CommonSumsCalculator implements ProvidesKeys
{
    public const ID = 'common.sums';
    public const ORDER = 4100;
    public const BEFORE = [];
    public const AFTER = [];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $out = [];
        foreach (self::PERIODS as $p) {
            $out[] = sprintf('sum_for_gokeishotoku_%s', $p);
            $out[] = sprintf('sum_for_sogoshotoku_etc_%s', $p);
            $out[] = sprintf('sum_for_pension_bucket_%s', $p);
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $ctx
     * @return array<string,mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        foreach (self::PERIODS as $period) {
            // ===== 1) 合計所得金額(代理) =====
            // まずは現行 shotoku_gokei_* があればそれを採用（完全互換）
            $gokeiKey = sprintf('shotoku_gokei_%s', $period);
            $gokei = $this->intOrNull($payload[$gokeiKey] ?? null);
            if ($gokei === null) {
                // 無ければ現行と同値の合成：keijo + joto短期 + joto長期 + 1時(半額済) + 山林 + 退職
                $gokei = 0;
                $gokei += $this->n($payload[sprintf('shotoku_keijo_%s', $period)] ?? null);
                $gokei += $this->n($payload[sprintf('shotoku_joto_tanki_%s', $period)] ?? null);
                $gokei += $this->n($payload[sprintf('shotoku_joto_choki_sogo_%s', $period)] ?? null);
                $gokei += $this->n($payload[sprintf('shotoku_ichiji_%s', $period)] ?? null);
                $gokei += $this->n($payload[sprintf('shotoku_sanrin_%s', $period)] ?? null);
                $gokei += $this->n($payload[sprintf('shotoku_taishoku_%s', $period)] ?? null);
            }
            $payload[sprintf('sum_for_gokeishotoku_%s', $period)] = $gokei;

            // ===== 2) 総所得金額等(代理) =====
            // 現行 TaxBaseMirror の sumComprehensive と等価：山林・退職を含めず、一時は 0 下限
            $keijo = $this->n($payload[sprintf('shotoku_keijo_%s', $period)] ?? null);
            $st    = $this->n($payload[sprintf('shotoku_joto_tanki_%s', $period)] ?? null);
            $lt    = $this->n($payload[sprintf('shotoku_joto_choki_sogo_%s', $period)] ?? null);
            $it    = max(0, $this->n($payload[sprintf('shotoku_ichiji_%s', $period)] ?? null));
            $sogoshotokuEtc = $keijo + $st + $lt + $it;
            $payload[sprintf('sum_for_sogoshotoku_etc_%s', $period)] = $sogoshotokuEtc;

            // ===== 3) 年金バケット用：年金“以外”の外側合計(代理) =====
            // v1は KyuyoNenkin の従来合算と同値：'shotoku_' かつ当該periodで終わるキーの総和から
            // 'shotoku_zatsu_nenkin_shotoku_%s' を除外
            $suffix = '_' . $period;
            $exclude = sprintf('shotoku_zatsu_nenkin_shotoku_%s', $period);
            $bucketOther = 0;
            foreach ($payload as $k => $v) {
                if (!is_string($k)) continue;
                if (!str_starts_with($k, 'shotoku_')) continue;
                if (!str_ends_with($k, $suffix)) continue;
                if ($k === $exclude) continue;
                $bucketOther = $this->n($v);
            }
            $payload[sprintf('sum_for_pension_bucket_%s', $period)] = $bucketOther;

            // ===== 4) Δログ（debug時のみ） =====
            if (config('app.debug')) {
                // 既存 shotoku_gokei_* が存在する場合のみ比較
                $existingGokei = $this->intOrNull($payload[$gokeiKey] ?? null);
                if ($existingGokei !== null && $existingGokei !== $gokei) {
                    Log::warning(sprintf('[common.sums] Δ(sum_for_gokeishotoku_%s)=%d (existing=%d new=%d)',
                        $period, $gokei - $existingGokei, $existingGokei, $gokei));
                }
                // 総所得金額等は TaxBaseMirror の内部合成と比較（同値のはず）
                $mirrorSum = $keijo + $st + $lt + $it;
                if ($mirrorSum !== $sogoshotokuEtc) {
                    Log::warning(sprintf('[common.sums] Δ(sum_for_sogoshotoku_etc_%s)=%d (mirror=%d new=%d)',
                        $period, $sogoshotokuEtc - $mirrorSum, $mirrorSum, $sogoshotokuEtc));
                }
            }
        }

        return $payload;
    }

    private function n(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        if (is_string($value)) $value = str_replace([',',' '], '', $value);
        return is_numeric($value) ? (int) floor((float) $value) : 0;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (is_string($value)) {
            $value = str_replace([',',' '], '', $value);
            if ($value === '') return null;
        }
        return is_numeric($value) ? (int) floor((float) $value) : null;
    }
}