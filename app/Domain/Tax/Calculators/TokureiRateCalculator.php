<?php

namespace App\Domain\Tax\Calculators;

use App\Domain\Tax\Contracts\MasterProviderContract;
use App\Services\Tax\Contracts\ProvidesKeys;
use Illuminate\Support\Facades\Log;

class TokureiRateCalculator implements ProvidesKeys
{
    public const ID = 'tokurei.bundle';
    // 【制度順】フェーズD：標準/90%/山林1/5/退職の率（税額の後）
    public const ORDER = 5400;
    public const ANCHOR = 'credits';
    public const BEFORE = [];
    public const AFTER = [JuminTaxCalculator::ID];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public function __construct(private readonly MasterProviderContract $masterProvider)
    {
    }

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        return [
            'tokurei_K_prev',
            'tokurei_K_curr',
            'tokurei_rate_standard_prev',
            'tokurei_rate_standard_curr',
            'tokurei_rate_90_prev',
            'tokurei_rate_90_curr',
            'tokurei_rate_sanrin_div5_prev',
            'tokurei_rate_sanrin_div5_curr',
            'tokurei_rate_taishoku_prev',
            'tokurei_rate_taishoku_curr',
            'tokurei_rate_bunri_min_prev',
            'tokurei_rate_bunri_min_curr',
            // 採用率（最終的にふるさと納税特例控除で用いる率）
            'tokurei_rate_final_prev',
            'tokurei_rate_final_curr',
            // 互換用エイリアス（従来の「adopted」は final と同義にする）
            'tokurei_rate_adopted_prev',
            'tokurei_rate_adopted_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $updates = array_fill_keys(self::provides(), null);

        $year = $this->resolveMasterYear($ctx);
        $companyId = isset($ctx['company_id']) && $ctx['company_id'] !== ''
            ? (int) $ctx['company_id']
            : null;

        $rows = $year > 0 ? $this->buildTokureiRows($year, $companyId) : [];

        if ($rows === [] && $year > 0 && config('app.debug')) {
            Log::debug('TokureiRateCalculator: no tokurei rates found', [
                'year' => $year,
                'company_id' => $companyId,
            ]);
        }

        // マスタが無ければ何もせず返す
        if ($rows === []) {
            return array_replace($payload, $updates);
        }
        foreach (self::PERIODS as $period) {
            // ── 1) 合計課税所得金額（住民税側）と人的控除差から K(D) を算出 ──
            $taxableTotal = $this->taxableTotalJumin($payload, $period);
            $humanDiff    = $this->n($payload[sprintf('human_diff_sum_%s', $period)] ?? null);

            $Kraw = $taxableTotal - $humanDiff;
            $K    = $this->floorToThousands($Kraw);

            $updates[sprintf('tokurei_K_%s', $period)] = $K;

            // ── 2) 標準率（D = 課税総所得金額 − 人的控除差調整額 に基づく一般ケース用） ──
            // 法令上は D を千円単位に切り捨てて表イに当てるので、rateForAmount には K(D) を渡す。
            $standardAmount = $K;
            $standardRate   = $this->rateForAmount($rows, $standardAmount);
            $updates[sprintf('tokurei_rate_standard_%s', $period)] = $standardRate;

            // ── 3) 90% 行（K<0 & 山林/退職/分離等なしのケース） ──
            $updates[sprintf('tokurei_rate_90_%s', $period)] = $this->roundPercent(90.0);

            // ── 4) 山林・退職用の補助率 ──
            $sanrinRate = $this->computeSanrinRate($rows, $payload, $period);
            $updates[sprintf('tokurei_rate_sanrin_div5_%s', $period)] = $sanrinRate;

            $taishokuRate = $this->computeTaishokuRate($rows, $payload, $period);
            $updates[sprintf('tokurei_rate_taishoku_%s', $period)] = $taishokuRate;

            // ── 5) 分離課税所得に対する最小特例率（59.37% / 74.685%） ──
            $bunriMinRate = $this->computeBunriMinRate($payload, $period);
            $updates[sprintf('tokurei_rate_bunri_min_%s', $period)] = $bunriMinRate;

            // ── 6) 各種フラグ（総合・山林・退職・分離） ──
            $hasSogo      = $this->n($payload[sprintf('tb_sogo_shotoku_%s',   $period)] ?? null) > 0;
            $hasSanrin    = $this->n($payload[sprintf('tb_sanrin_shotoku_%s', $period)] ?? null) > 0;
            $hasTaishoku  = $this->n($payload[sprintf('tb_taishoku_shotoku_%s', $period)] ?? null) > 0;

            $hasBunriShort = $this->n($payload[sprintf('tb_joto_tanki_shotoku_%s', $period)] ?? null) > 0;
            $hasBunriOther =
                (
                    $this->n($payload[sprintf('tb_joto_choki_shotoku_%s',            $period)] ?? null)
                  + $this->n($payload[sprintf('tb_ippan_kabuteki_joto_shotoku_%s',   $period)] ?? null)
                  + $this->n($payload[sprintf('tb_jojo_kabuteki_joto_shotoku_%s',    $period)] ?? null)
                  + $this->n($payload[sprintf('tb_jojo_kabuteki_haito_shotoku_%s',   $period)] ?? null)
                  + $this->n($payload[sprintf('tb_sakimono_shotoku_%s',             $period)] ?? null)
                ) > 0;
            $hasBunri = $hasBunriShort || $hasBunriOther;
            // 表ロの対象となる所得（山林・退職・分離）があるか
            $hasHyoro = $hasSanrin || $hasTaishoku || $hasBunri;

            // ── 7) 最終採用率の決定（法令上の A/B/C ケースに対応） ──
            $finalRate = null;

            // Case A: 課税総所得あり & D(K) >= 0 → 表イに基づく標準率
            if ($hasSogo && $K >= 0 && $standardRate !== null) {
                $finalRate = $standardRate;
            }

            // Case B: 課税総所得あり & D(K) < 0 かつ 表ロの所得なし → 90%
            if ($finalRate === null && $hasSogo && $K < 0 && ! $hasHyoro) {
                $finalRate = $this->roundPercent(90.0);
            }

            // Case C: 表ロケース（D(K) < 0 または課税総所得なし）で、表ロの所得（山林・退職・分離）がある
            if (
                $finalRate === null
                && (
                    ($hasSogo && $K < 0 && $hasHyoro)
                    || (! $hasSogo && $hasHyoro)
                )
            ) {
                $candidates = [];

                // 山林所得に対する特例率（tb_sanrin_shotoku > 0 のとき）
                if ($hasSanrin && $sanrinRate !== null) {
                    $candidates[] = $sanrinRate;
                }

                // 退職所得に対する特例率（tb_taishoku_shotoku > 0 のとき）
                if ($hasTaishoku && $taishokuRate !== null) {
                    $candidates[] = $taishokuRate;
                }

                // 分離短期がある場合：59.37%
                if ($hasBunriShort) {
                    $candidates[] = $this->roundPercent(59.37);
                }

                // 分離長期・株式譲渡・配当・先物等がある場合：74.685%
                if ($hasBunriOther) {
                    $candidates[] = $this->roundPercent(74.685);
                }

                if ($candidates !== []) {
                    $finalRate = min($candidates);
                }
            }

            // Fallback（理論上到達しづらいが、安全弁として残す）
            if ($finalRate === null) {
                if ($standardRate !== null) {
                    $finalRate = $standardRate;
                } elseif ($bunriMinRate !== null) {
                    $finalRate = $bunriMinRate;
                } else {
                    $finalRate = $this->roundPercent(90.0);
                }
            }

            $updates[sprintf('tokurei_rate_final_%s', $period)]   = $finalRate;
            // 互換用：従来の adopted は最終採用率と同義にする
            $updates[sprintf('tokurei_rate_adopted_%s', $period)] = $finalRate;
        }

        return array_replace($payload, $updates);
    }

    /**
     * @return array<int, array{lower:int, upper:int|null, rate:float}>
     */
    private function buildTokureiRows(int $year, ?int $companyId): array
    {
        $collection = $this->masterProvider->getTokureiRates($year, $companyId);

        $rows = [];
        foreach ($collection as $row) {
            $lower = $this->normalizeBound($row->lower ?? null);
            if ($lower === null) {
                continue;
            }

            $upper = $this->normalizeUpperBound($row->upper ?? null);
            $rate = $this->normalizeRate($row->tokurei_deduction_rate ?? null);

            if ($rate === null) {
                continue;
            }

            $rows[] = [
                'lower' => $lower,
                'upper' => $upper,
                'rate' => $rate,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $lowerCmp = $a['lower'] <=> $b['lower'];
            if ($lowerCmp !== 0) {
                return $lowerCmp;
            }

            $upperA = $a['upper'];
            $upperB = $b['upper'];

            if ($upperA === $upperB) {
                return 0;
            }

            if ($upperA === null) {
                return 1;
            }

            if ($upperB === null) {
                return -1;
            }

            return $upperA <=> $upperB;
        });

        return $rows;
    }

    private function taxableBase(array $payload, array $ctx, string $period): int
    {
        unset($ctx);
        // SoT統一：常に tb_sogo_shotoku_*
        $raw = $this->n($payload[sprintf('tb_sogo_shotoku_%s', $period)] ?? null);

        return $this->floorToThousands(max(0, $raw));
    }

    /**
     * 合計課税所得金額（住民税側）tb_sogo_jumin + tb_sanrin_jumin + tb_taishoku_jumin
     * K の計算に用いる。
     */
    private function taxableTotalJumin(array $payload, string $period): int
    {
        $sogo   = $this->n($payload[sprintf('tb_sogo_jumin_%s',   $period)] ?? null);
        $sanrin = $this->n($payload[sprintf('tb_sanrin_jumin_%s', $period)] ?? null);
        $taishoku = $this->n($payload[sprintf('tb_taishoku_jumin_%s', $period)] ?? null);

        $total = max(0, $sogo) + max(0, $sanrin) + max(0, $taishoku);

        return $total;
    }
    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function syoriSettings(array $ctx): array
    {
        $settings = $ctx['syori_settings'] ?? [];

        return is_array($settings) ? $settings : [];
    }

    private function computeSanrinRate(array $rows, array $payload, string $period): ?float
    {
        // tb_sanrin_shotoku_*
        $amount = $this->n($payload[sprintf('tb_sanrin_shotoku_%s', $period)] ?? null);
        if ($amount <= 0) {
            return null;
        }

        $divided = $this->floorToThousands(intdiv($amount, 5));
        if ($divided <= 0) {
            return null;
        }

        return $this->rateForAmount($rows, $divided);
    }

    private function computeTaishokuRate(array $rows, array $payload, string $period): ?float
    {
        // tb_taishoku_shotoku_*
        $amount = $this->n($payload[sprintf('tb_taishoku_shotoku_%s', $period)] ?? null);
        if ($amount <= 0) {
            return null;
        }

        $base = $this->floorToThousands($amount);
        if ($base <= 0) {
            return null;
        }

        return $this->rateForAmount($rows, $base);
    }

    /**
     * 分離課税所得に対する最小特例控除率（59.37%/74.685%）
     *  - 短期譲渡所得を有する場合：59.37%
     *  - 短期が無く、その他の分離課税所得を有する場合：74.685%
     *  - 分離所得が無い場合：null
     *
     * 実務上は K<0 かつ総合等が無いケース等で用いるが、
     * ここでは「分離が存在する場合に候補率として算出」する役割に留める。
     */
    private function computeBunriMinRate(array $payload, string $period): ?float
    {
        $short = $this->n($payload[sprintf('tb_joto_tanki_shotoku_%s', $period)] ?? null);
        $otherSum =
              $this->n($payload[sprintf('tb_joto_choki_shotoku_%s', $period)] ?? null)
            + $this->n($payload[sprintf('tb_ippan_kabuteki_joto_shotoku_%s', $period)] ?? null)
            + $this->n($payload[sprintf('tb_jojo_kabuteki_joto_shotoku_%s',  $period)] ?? null)
            + $this->n($payload[sprintf('tb_jojo_kabuteki_haito_shotoku_%s', $period)] ?? null)
            + $this->n($payload[sprintf('tb_sakimono_shotoku_%s',            $period)] ?? null);

        if ($short > 0) {
            return $this->roundPercent(59.37);
        }

        if ($otherSum > 0) {
            return $this->roundPercent(74.685);
        }

        return null;
    }

    private function rateForAmount(array $rows, int $amount): ?float
    {
        if ($rows === []) {
            return null;
        }

        $rate = $this->lowerBoundRate(max(0, $amount), $rows);

        if ($rate === null) {
            return null;
        }

        return $this->roundPercent($rate * 100);
    }

    private function lowerBoundRate(float $amount, array $rows): ?float
    {
        if ($rows === []) {
            return null;
        }

        $amount = $this->floorToThousands((int) max(0, floor($amount)));

        $fallbackRate = null;
        $fallbackLower = PHP_INT_MAX;

        foreach ($rows as $row) {
            $lower = (int) $row['lower'];
            if ($lower < $fallbackLower) {
                $fallbackLower = $lower;
                $fallbackRate = $row['rate'];
            }

            if ($lower > $amount) {
                continue;
            }

            $upper = $row['upper'];
            if ($upper !== null && $amount > $upper) {
                continue;
            }
            
            return $row['rate'];
        }

        return $fallbackRate;
    }

    private function resolveMasterYear(array $ctx): int
    {
        $year = isset($ctx['master_kihu_year']) ? (int) $ctx['master_kihu_year'] : 0;
        if ($year > 0) {
            return $year;
        }

        $fallback = isset($ctx['kihu_year']) ? (int) $ctx['kihu_year'] : 0;

        return max(0, $fallback);
    }

    private function normalizeBound(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeUpperBound(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeRate(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return ((float) $value) / 100.0;
    }

    private function floorToThousands(int $value): int
    {
        if ($value <= 0) {
            return 0;
        }

        return $value - ($value % 1000);
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

    private function roundPercent(float $value): float
    {
        return round($value, 3);
    }
}