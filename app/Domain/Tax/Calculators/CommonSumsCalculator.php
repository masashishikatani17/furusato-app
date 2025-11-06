<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;
use Illuminate\Support\Facades\Log;

use App\Domain\Tax\Calculators\KojoAggregationCalculator;
use App\Domain\Tax\Calculators\JintekiKojoCalculator;
use App\Domain\Tax\Calculators\HaigushaKojoCalculator;

class CommonSumsCalculator implements ProvidesKeys
{
    public const ID = 'common.sums';
    // 控除系より前に実行される（period系は UseCase 側で先に実行されるため AFTER は空）
    public const ORDER = 3500;
    public const AFTER = [];  // Missing dependencies 回避のため空
    public const BEFORE = [
        \App\Domain\Tax\Calculators\JintekiKojoCalculator::ID,
        \App\Domain\Tax\Calculators\HaigushaKojoCalculator::ID,
        \App\Domain\Tax\Calculators\KojoAggregationCalculator::ID,
    ];

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
            $out[] = sprintf('sum_for_sogoshotoku_%s', $p);
            $out[] = sprintf('sum_for_sogoshotoku_etc_%s', $p);
            $out[] = sprintf('sum_for_pension_bucket_%s', $p);
            // UI第一表の合計(A+B)もSoTとして保持
            $out[] = sprintf('sum_for_ab_total_%s', $p);
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
                + max(0, $this->n($payload[sprintf('shotoku_joto_tanki_sogo_%s', $period)] ?? null))
                + max(0, $this->n($payload[sprintf('shotoku_joto_choki_sogo_%s', $period)] ?? null))
                + max(0, $this->n($payload[sprintf('shotoku_ichiji_%s',          $period)] ?? null));

            // B_p（退職・山林：正値のみ）
            $Bp =
                  max(0, $this->n($payload[sprintf('shotoku_taishoku_%s', $period)] ?? null))
                + max(0, $this->n($payload[sprintf('shotoku_sanrin_%s',   $period)] ?? null));

            // UI用：第一表の合計(A+B)
            $payload[sprintf('sum_for_ab_total_%s', $period)] = $Ap + $Bp;

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

            // ===== 2) 総所得金額 sum_for_sogoshotoku_{p}（総合課税のみ）=====
            // 総合課税のみなので A_p をそのまま採用（0下限は既に適用済み）
            $sumSogo = $Ap;
            $payload[sprintf('sum_for_sogoshotoku_%s', $period)] = $sumSogo;

            // ===== 3) 総所得金額等 sum_for_sogoshotoku_etc_{p} =====
            // 総所得金額 + 退職/山林 + 分離（繰越控除“後”）の正値合計
            $Cafter =
                  max(0, $this->n($payload[sprintf('tsusango_tanki_ippan_%s',   $period)] ?? null))
                + max(0, $this->n($payload[sprintf('tsusango_tanki_keigen_%s',  $period)] ?? null))
                + max(0, $this->n($payload[sprintf('tsusango_choki_ippan_%s',   $period)] ?? null))
                + max(0, $this->n($payload[sprintf('tsusango_choki_tokutei_%s', $period)] ?? null))
                + max(0, $this->n($payload[sprintf('tsusango_choki_keika_%s',   $period)] ?? null))
                + max(0, $this->n($payload[sprintf('shotoku_after_kurikoshi_ippan_joto_%s', $period)] ?? null))
                + max(0, $this->n($payload[sprintf('shotoku_after_kurikoshi_jojo_joto_%s',  $period)] ?? null))
                + max(0, $this->n($payload[sprintf('shotoku_after_kurikoshi_jojo_haito_%s', $period)] ?? null))
                + max(0, $this->n($payload[sprintf('shotoku_sakimono_after_kurikoshi_%s',   $period)] ?? null));
            $sogoshotokuEtc = $sumSogo + $Bp + $Cafter;
            $payload[sprintf('sum_for_sogoshotoku_etc_%s', $period)] = $sogoshotokuEtc;

            // ===== 4) 年金バケット用：年金“以外”の外側合計(代理) =====
            // v1互換のまま（本ターンは合計所得金額のみ厳密化）
            $suffix = '_' . $period;
            $exclude = sprintf('shotoku_zatsu_nenkin_shotoku_%s', $period);
            $bucketOther = 0;
            foreach ($payload as $k => $v) {
                if (!is_string($k)) continue;
                if (!str_starts_with($k, 'shotoku_')) continue;
                if (!str_ends_with($k, $suffix)) continue;
                if ($k === $exclude) continue;
                $bucketOther += $this->n($v);
            }
            $payload[sprintf('sum_for_pension_bucket_%s', $period)] = $bucketOther;

            // ===== 5) Δログ（debug時のみ） =====
            if (config('app.debug')) {
                // 既存 shotoku_gokei_* が存在する場合のみ比較（監視用）
                $existingGokei = $this->intOrNull($payload[sprintf('shotoku_gokei_%s', $period)] ?? null);
                if ($existingGokei !== null && $existingGokei !== $gokei) {
                    Log::warning(sprintf(
                        '[common.sums.v2] Δ(sum_for_gokeishotoku_%s)=%d (legacy=%d new=%d; A=%d, B=%d, C=%d)',
                        $period, $gokei - $existingGokei, $existingGokei, $gokei, $Ap, $Bp, $Cp
                    ));
                }
                // 参考ログ：ブロック別の可視化
                Log::info(sprintf(
                    '[common.sums.v2] parts.%s A=%d B=%d C_pre=%d C_after=%d sogo=%d sogo_etc=%d',
                    $period, $Ap, $Bp, $Cp, $Cafter, $sumSogo, $sogoshotokuEtc
                ));
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