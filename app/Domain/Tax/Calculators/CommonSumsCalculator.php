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
            // ===== v2 厳密式：合計所得金額 sum_for_gokeishotoku_{p} =====
            // A_p（総合課税：損益通算後の正値のみ）
            $Ap =
                  max(0, $this->n($payload[sprintf('shotoku_keijo_%s',           $period)] ?? null))
                + max(0, $this->n($payload[sprintf('shotoku_joto_tanki_%s',      $period)] ?? null))
                + max(0, $this->n($payload[sprintf('shotoku_joto_choki_sogo_%s', $period)] ?? null))
                + max(0, $this->n($payload[sprintf('shotoku_ichiji_%s',          $period)] ?? null));

            // B_p（退職・山林：正値のみ）
            $Bp =
                  max(0, $this->n($payload[sprintf('shotoku_taishoku_%s', $period)] ?? null))
                + max(0, $this->n($payload[sprintf('shotoku_sanrin_%s',   $period)] ?? null));

            // C_p（分離課税：繰越控除“前”の正値のみ、時点を tsusango_* に統一）
            $Cp =
                  max(0, $this->n($payload[sprintf('tsusango_tanki_ippan_%s',   $period)] ?? null))
                + max(0, $this->n($payload[sprintf('tsusango_tanki_keigen_%s',  $period)] ?? null))
                + max(0, $this->n($payload[sprintf('tsusango_choki_ippan_%s',   $period)] ?? null))
                + max(0, $this->n($payload[sprintf('tsusango_choki_tokutei_%s', $period)] ?? null))
                + max(0, $this->n($payload[sprintf('tsusango_choki_keika_%s',   $period)] ?? null))
                + max(0, $this->n($payload[sprintf('tsusango_ippan_joto_%s',    $period)] ?? null))
                + max(0, $this->n($payload[sprintf('tsusango_jojo_joto_%s',     $period)] ?? null))
                + max(0, $this->n($payload[sprintf('tsusango_jojo_haito_%s',    $period)] ?? null))
                + max(0, $this->n($payload[sprintf('shotoku_sakimono_%s',       $period)] ?? null));

            $gokei = $Ap + $Bp + $Cp;
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
            // v1互換のまま（本ターンは合計所得金額のみ厳密化）
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
                // 既存 shotoku_gokei_* が存在する場合のみ比較（監視用）
                $existingGokei = $this->intOrNull($payload[sprintf('shotoku_gokei_%s', $period)] ?? null);
                if ($existingGokei !== null && $existingGokei !== $gokei) {
                    Log::warning(sprintf(
                        '[common.sums.v2] Δ(sum_for_gokeishotoku_%s)=%d (legacy=%d new=%d; A=%d, B=%d, C=%d)',
                        $period, $gokei - $existingGokei, $existingGokei, $gokei, $Ap, $Bp, $Cp
                    ));
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