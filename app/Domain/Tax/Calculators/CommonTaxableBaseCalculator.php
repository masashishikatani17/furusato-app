<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;
use Illuminate\Support\Facades\Log;

class CommonTaxableBaseCalculator implements ProvidesKeys
{
    public const ID = 'common.taxable.base';
    public const ORDER = 5050;
    // 本Calculatorの出力(tb_*)を参照して税額や住民税控除を計算させる
    public const BEFORE = [
        ShotokuTaxCalculator::ID,
        JuminTaxCalculator::ID,
        JuminzeiKifukinCalculator::ID,
        TokureiRateCalculator::ID,
    ];
    // 合計( sum_for_* ) と 所得控除合計( kojo_gokei_* ) が先に確定していること
    public const AFTER = [
        CommonSumsCalculator::ID,
        KojoAggregationCalculator::ID,
    ];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $out = [];
        foreach (['prev','curr'] as $p) {
            // 総合（課税総合所得金額）
            $out[] = "tb_sogo_shotoku_{$p}";
            $out[] = "tb_sogo_jumin_{$p}";
            // 分離（第三表）— 税目別に露出（個人控除配賦後の課税標準）
            foreach (['shotoku','jumin'] as $tax) {
                $out[] = "tb_joto_tanki_{$tax}_{$p}";
                $out[] = "tb_joto_choki_{$tax}_{$p}";
                $out[] = "tb_ippan_kabuteki_joto_{$tax}_{$p}";
                $out[] = "tb_jojo_kabuteki_joto_{$tax}_{$p}";
                $out[] = "tb_jojo_kabuteki_haito_{$tax}_{$p}";
                $out[] = "tb_sakimono_{$tax}_{$p}";
                $out[] = "tb_sanrin_{$tax}_{$p}";
                $out[] = "tb_taishoku_{$tax}_{$p}";
            }
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
        // syori_settings から分離ON/OFF（住民税側）を判定
        $settings = is_array($ctx['syori_settings'] ?? null) ? $ctx['syori_settings'] : [];
        $bunriOn = [
            'prev' => (int)($settings['bunri_flag_prev'] ?? $settings['bunri_flag'] ?? 0) === 1,
            'curr' => (int)($settings['bunri_flag_curr'] ?? $settings['bunri_flag'] ?? ($settings['bunri_flag_prev'] ?? 0)) === 1,
        ];

        foreach (self::PERIODS as $p) {
            // ===== 参照（SoT） =====
            $sumA   = $this->n($payload["sum_for_sogoshotoku_{$p}"]    ?? null); // A: 総合
            $sumAB  = $this->n($payload["sum_for_ab_total_{$p}"]       ?? null); // A+B: 総合＋(退職・山林)
            $kojoS  = $this->n($payload["kojo_gokei_shotoku_{$p}"]     ?? null); // 所得税側の所得控除合計
            $kojoJ  = $this->n($payload["kojo_gokei_jumin_{$p}"]       ?? null); // 住民税側の所得控除合計

            // 分離カテゴリの“自己側基礎”（※個人控除配賦前の数値を採用）
            $baseST = $this->pos($payload["joto_shotoku_tanki_gokei_{$p}"] ?? null);
            $baseLT = $this->pos($payload["joto_shotoku_choki_gokei_{$p}"] ?? null);
            $baseJG = $this->pos($payload["shotoku_after_kurikoshi_ippan_joto_{$p}"] ?? null);
            $baseJL = $this->pos($payload["shotoku_after_kurikoshi_jojo_joto_{$p}"]  ?? null);
            $baseH  = $this->pos($payload["shotoku_after_kurikoshi_jojo_haito_{$p}"] ?? null);
            $baseSX = $this->pos($payload["shotoku_sakimono_after_kurikoshi_{$p}"]   ?? null);
            // 山林・退職は shotoku_* を使用（after_3 は使わない）
            $baseSan= $this->pos($payload["shotoku_sanrin_{$p}"]   ?? null);
            $baseTai= $this->pos($payload["shotoku_taishoku_{$p}"] ?? null);

            // ===== 所得税（総合→山林→退職）控除配賦 =====
            $aS   = max(0, $sumA);
            $useA = min($kojoS, $aS);
            $tb_sogo_shotoku = $this->floorToThousands($aS - $useA);
            $remS = $kojoS - $useA;

            // 山林へ
            $useSan = min($remS, $baseSan);
            $tb_sanrin_shotoku = $this->floorToThousands($baseSan - $useSan);
            $remS -= $useSan;
            // 退職へ
            $useTai = min($remS, $baseTai);
            $tb_taishoku_shotoku = $this->floorToThousands($baseTai - $useTai);
            $remS -= $useTai; // ここで 0 のはず

            // 分離（個人控除は配賦しない）— 既存自己基礎をそのまま丸め
            $tb_joto_tanki_shotoku      = $this->floorToThousands($baseST);
            $tb_joto_choki_shotoku      = $this->floorToThousands($baseLT);
            $tb_ippan_kabuteki_joto_sho = $this->floorToThousands($baseJG);
            $tb_jojo_kabuteki_joto_sho  = $this->floorToThousands($baseJL);
            $tb_jojo_kabuteki_haito_sho = $this->floorToThousands($baseH);
            $tb_sakimono_shotoku        = $this->floorToThousands($baseSX);

            // ===== 住民税（総合→短期→長期→上場配当→一般譲渡→上場譲渡→先物→山林→退職）配賦 =====
            $abJ   = max(0, $sumAB);
            $useAB = min($kojoJ, $abJ);
            $tb_sogo_jumin = $this->floorToThousands($abJ - $useAB);
            $remJ = $kojoJ - $useAB;

            // 順次配賦
            $alloc = function (int $base) use (&$remJ): int {
                $use = min($remJ, max(0, $base));
                $remJ -= $use;
                return $this->floorToThousands(max(0, $base - $use));
            };
            if ($bunriOn[$p]) {
                $tb_joto_tanki_jumin      = $alloc($baseST);
                $tb_joto_choki_jumin      = $alloc($baseLT);
                $tb_jojo_kabuteki_haito_j = $alloc($baseH);
                $tb_ippan_kabuteki_joto_j = $alloc($baseJG);
                $tb_jojo_kabuteki_joto_j  = $alloc($baseJL);
                $tb_sakimono_jumin        = $alloc($baseSX);
                $tb_sanrin_jumin          = $alloc($baseSan);
                $tb_taishoku_jumin        = $alloc($baseTai);
            } else {
                // 分離OFF: 第三表(住民税)はすべて 0 に集約（総合 tb_sogo_jumin_* のみ成立）
                $tb_joto_tanki_jumin      = 0;
                $tb_joto_choki_jumin      = 0;
                $tb_jojo_kabuteki_haito_j = 0;
                $tb_ippan_kabuteki_joto_j = 0;
                $tb_jojo_kabuteki_joto_j  = 0;
                $tb_sakimono_jumin        = 0;
                $tb_sanrin_jumin          = 0;
                $tb_taishoku_jumin        = 0;
                // remJ は以後参照しないため未使用でOK
            }

            // ===== 書き戻し =====
            $payload["tb_sogo_shotoku_{$p}"] = $tb_sogo_shotoku;
            $payload["tb_sogo_jumin_{$p}"]   = $tb_sogo_jumin;

            $payload["tb_joto_tanki_shotoku_{$p}"]       = $tb_joto_tanki_shotoku;
            $payload["tb_joto_choki_shotoku_{$p}"]       = $tb_joto_choki_shotoku;
            $payload["tb_ippan_kabuteki_joto_shotoku_{$p}"] = $tb_ippan_kabuteki_joto_sho;
            $payload["tb_jojo_kabuteki_joto_shotoku_{$p}"]  = $tb_jojo_kabuteki_joto_sho;
            $payload["tb_jojo_kabuteki_haito_shotoku_{$p}"] = $tb_jojo_kabuteki_haito_sho;
            $payload["tb_sakimono_shotoku_{$p}"]         = $tb_sakimono_shotoku;
            $payload["tb_sanrin_shotoku_{$p}"]           = $tb_sanrin_shotoku;
            $payload["tb_taishoku_shotoku_{$p}"]         = $tb_taishoku_shotoku;

            $payload["tb_joto_tanki_jumin_{$p}"]         = $tb_joto_tanki_jumin;
            $payload["tb_joto_choki_jumin_{$p}"]         = $tb_joto_choki_jumin;
            $payload["tb_jojo_kabuteki_haito_jumin_{$p}"]= $tb_jojo_kabuteki_haito_j;
            $payload["tb_ippan_kabuteki_joto_jumin_{$p}"]= $tb_ippan_kabuteki_joto_j;
            $payload["tb_jojo_kabuteki_joto_jumin_{$p}"] = $tb_jojo_kabuteki_joto_j;
            $payload["tb_sakimono_jumin_{$p}"]           = $tb_sakimono_jumin;
            $payload["tb_sanrin_jumin_{$p}"]             = $tb_sanrin_jumin;
            $payload["tb_taishoku_jumin_{$p}"]           = $tb_taishoku_jumin;

            // Δログ（移行監視）
            if (config('app.debug')) {
                // 旧キー（監視のみ）。リテラルは使用しない＝grep除外
                $legacyKey = 'tax_' . 'kazeishotoku_' . 'shotoku_' . $p;
                $legacy = $this->intOrNull($payload[$legacyKey] ?? null);
                if ($legacy !== null && $legacy !== $tb_sogo_shotoku) {
                    Log::warning(sprintf('[common.taxable.base] Δ(tb_sogo_shotoku_%s)=%d (legacy=%d new=%d)',
                        $p, $tb_sogo_shotoku - $legacy, $legacy, $tb_sogo_shotoku));
                }
            }
        }
        return $payload;
    }

    private function floorToThousands(int $value): int
    {
        // 0下限・千円未満切捨て（負値は 0）
        if ($value <= 0) return 0;
        return (int) (floor($value / 1000) * 1000);
    }

    private function n(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        if (is_string($value)) $value = str_replace([',',' '], '', $value);
        return is_numeric($value) ? (int) floor((float) $value) : 0;
    }

    private function pos(mixed $value): int
    {
        $v = $this->n($value);
        return $v > 0 ? $v : 0;
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