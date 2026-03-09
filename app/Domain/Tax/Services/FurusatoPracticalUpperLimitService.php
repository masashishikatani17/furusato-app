<?php

namespace App\Domain\Tax\Services;

use App\Domain\Tax\Contracts\MasterProviderContract;
use Illuminate\Support\Facades\Log;

/**
 * ふるさと納税の「実利上限（自己負担<=2,000円）」を探索して返す。
 *
 * - ベースは「ふるさと=0」（他寄附固定）
 * - 候補yで計算した支払額（tax_gokei_shotoku + tax_gokei_jumin）の差分から減税額を算出
 * - 自己負担 = y - 減税額 が 2,000円以下の最大yを探索
 *   （高速化：粗いステップ探索→最後に1円で局所探索＝「速い＋1円精度」）
 * - 追加条件：政党/NPO/公益の所得税税額控除が「現在の入力値」より減らない
 */
final class FurusatoPracticalUpperLimitService
{
    public function __construct(
        private readonly FurusatoDryRunCalculatorRunner $runner,
        private readonly MasterProviderContract $masterProvider,
    ) {}

    /**
     * @param  array<string,mixed> $basePayload  現在のSoT payload（DB/セッションの最新）
     * @param  array<string,mixed> $ctx          calculator ctx（syori_settings等）
     * @return array<string,mixed>
     */
    public function compute(array $basePayload, array $ctx): array
    {
        // ------------------------------------------------------------
        // safety: return/ログで参照する変数は先に初期化（Undefined防止）
        // ------------------------------------------------------------
        $payBase  = 0;
        $payMax   = 0;
        $taxSaved = 0;
        $burden   = 0;

        // ============================================================
        // dry-run ctx（上限探索中は「計算ログ（特に住宅ローン控除など）」を抑制する）
        // - 下流 Calculator が ctx['dry_run'] を見てログを出さないようにする
        // ============================================================
        $ctxDry = $this->withDryRunFlag($ctx);
        // ============================================================
        // perf log はデフォルト OFF（必要時のみ env でON）
        //  - FURUSATO_PERF_LOG=1 のときだけ計測する
        // ============================================================
        $enablePerf = (string) env('FURUSATO_PERF_LOG', '0') === '1';
        $dryRunMetrics = null;
        $isOkCalls = 0;
        $okCount = 0;
        $ngCount = 0;
        $stageCounts = ['base'=>0,'bracket'=>0,'binary'=>0,'local'=>0];
        $currentStage = 'base';
        if ($enablePerf) {
            // ctx は配列で値渡しになるため、オブジェクトを入れて参照的に共有する
            $dryRunMetrics = (object) ['runs'=>0,'total_ms'=>0.0,'max_ms'=>0.0];
            $ctxDry['dry_run_metrics'] = $dryRunMetrics;
        }
        // y_current（当年ふるさと寄付：帳票/上限制御の実体）
        // - ワンストップ等で「所得税側=0 / 住民税側だけ入力」のケースがあり得るため、
        //   ふるさと寄付額は (所得税SoT, 住民税pref, 住民税muni) の最大値を採用する。
        // - pref/muni は同額コピー運用が前提なので max() で二重計上にならない。
        $yCurrent = $this->resolveFurusatoDonationCurrent($basePayload);

        $isOnestop = $this->isOnestopContext($ctx);

        // (A) NG判定の基準：現在yでの政党/NPO/公益の所得税税額控除
        $payloadAtCurrent = $this->withFurusato($basePayload, $yCurrent, $ctx);
        $stageCounts['base']++;
        $outCurrent = $this->runner->run($payloadAtCurrent, $ctxDry);
        $creditBase = [
            'seito'  => $this->n($outCurrent['tax_credit_shotoku_seito_curr']  ?? 0),
            'npo'    => $this->n($outCurrent['tax_credit_shotoku_npo_curr']    ?? 0),
            'koueki' => $this->n($outCurrent['tax_credit_shotoku_koueki_curr'] ?? 0),
        ];

        // (B) 自己負担の基準：ふるさと=0 の支払額（復興税込み）
        $payloadZero = $this->withFurusato($basePayload, 0, $ctx);
        $stageCounts['base']++;
        $outZero = $this->runner->run($payloadZero, $ctxDry);
        // ★上限探索の定義は「税額総額差」：所得税 + 住民税（総額）
        $payBase = $this->payTotalCurr($outZero);
        $baseFuruOnly = $isOnestop ? $this->furusatoOnlyJuminFinal($outZero, $payloadZero, $ctx, 'curr') : 0;

        // 探索上界：0.4*S40（S40 = sum_for_sogoshotoku_etc_curr）
        $S40 = $this->n(
            $basePayload['sum_for_sogoshotoku_etc_curr']
            ?? $outCurrent['sum_for_sogoshotoku_etc_curr']
            ?? $outZero['sum_for_sogoshotoku_etc_curr']
            ?? 0
        );
        $upper = max(0, (int) floor($S40 * 0.4));

        // ============================================================
        // 非単調（ギザり）対応の高速探索：
        //  - 単調性を仮定しない（途中にNGが混ざっても、最大OKを取りこぼさない）
        //  - 桁探索（10進）：各桁で 0..9 候補を試して “最大OK” を選ぶ（最大 9回/桁）
        //  - 最後に “周辺+30円” を 1円刻みで確認（端数ギザりの取りこぼし防止）
        // ============================================================

        $isOk = function (int $y) use (
            $basePayload,
            $ctxDry,
            $ctx,
            $isOnestop,
            $payBase,
            $creditBase,
            $baseFuruOnly,
            $enablePerf,
            &$isOkCalls,
            &$okCount,
            &$ngCount,
            &$stageCounts,
            &$currentStage
        ): bool {
            if ($enablePerf) {
                $isOkCalls++;
                $stageKey = $currentStage ?: 'unknown';
                $stageCounts[$stageKey] = ($stageCounts[$stageKey] ?? 0) + 1;
            }
            $ok = $this->isOk($basePayload, $ctxDry, $ctx, $y, $payBase, $creditBase, $isOnestop, $baseFuruOnly);
            if ($enablePerf) {
                if ($ok) $okCount++; else $ngCount++;
            }
            return $ok;
        };

        // 0 がNGなら異常（ただし落とさず0を返す）
        $yMaxTotalRaw = 0;
        if ($upper > 0 && $isOk(0)) {
            $currentStage = 'digit';

            // upper 以下の最大桁（10^k）を作る（例：2,912,800 → 1,000,000）
            $top = 1;
            while ($top <= intdiv($upper, 10)) {
                $top *= 10;
            }

            $best = 0;
            for ($inc = $top; $inc >= 1; $inc = intdiv($inc, 10)) {
                $bestAtThis = $best;
                for ($d = 1; $d <= 9; $d++) {
                    $cand = $best + ($inc * $d);
                    if ($cand > $upper) break;
                    if ($isOk($cand)) {
                        $bestAtThis = $cand;
                    }
                }
                $best = $bestAtThis;
            }

            // 端数ギザり対策：周辺 +30円 だけ 1円刻みで確認（必要最小限）
            $currentStage = 'local';
            $span = 30;
            $to = min($upper, $best + $span);
            for ($yy = $best + 1; $yy <= $to; $yy++) {
                if ($isOk($yy)) {
                    $best = $yy;
                }
            }

            $yMaxTotalRaw = $best;
        }

        // ★ 最終：1円単位の最大OKをそのまま採用（精緻な実利上限）
        //   探索は1円精度で行い、最後に1000円未満切捨てを「最終上限」として採用する。
        //   ※ 切捨て後の値がOKであることを保険で再確認し、NGなら1000円刻みで下げる。
        $yMaxTotalRaw = max(0, (int)$yMaxTotalRaw);
        $yMaxFloor = $yMaxTotalRaw > 0 ? (int)(intdiv($yMaxTotalRaw, 1000) * 1000) : 0;
        // 保険：切捨て後がOKでないケースを潰す（通常は起きない想定）
        $yMaxTotal = $yMaxFloor;
        if ($yMaxTotal > 0 && !$this->isOk($basePayload, $ctxDry, $ctx, $yMaxTotal, $payBase, $creditBase, $isOnestop, $baseFuruOnly)) {
            // 最大でも数回で落ちる想定（念のため上限を置く）
            for ($guard = 0; $guard < 10 && $yMaxTotal > 0; $guard++) {
                $yMaxTotal = max(0, $yMaxTotal - 1000);
                if ($this->isOk($basePayload, $ctxDry, $ctx, $yMaxTotal, $payBase, $creditBase, $isOnestop, $baseFuruOnly)) {
                    break;
                }
            }
        }

        // 仕上げ：yMaxでの支払額/自己負担（参考）
        $payloadMax = $this->withFurusato($basePayload, $yMaxTotal, $ctx);
        $stageCounts['base']++;
        $outMax = $this->runner->run($payloadMax, $ctxDry);
        $payMax = $this->payTotalCurr($outMax);
        if ($isOnestop) {
            $maxFuruOnly = $this->furusatoOnlyJuminFinal($outMax, $payloadMax, $ctx, 'curr');
            $taxSaved = max(0, $maxFuruOnly - $baseFuruOnly);
            $burden = max(0, $yMaxTotal - $taxSaved);
        } else {
            $taxSaved = max(0, $payBase - $payMax);
            $burden = max(0, $yMaxTotal - $taxSaved);
        }
  
        // perf log（必要時のみ：FURUSATO_PERF_LOG=1）
        if ($enablePerf && is_object($dryRunMetrics)) {
            try {
                $runs = (int) ($dryRunMetrics->runs ?? 0);
                $totalMs = (float) ($dryRunMetrics->total_ms ?? 0.0);
                $maxMs = (float) ($dryRunMetrics->max_ms ?? 0.0);
                $avgMs = $runs > 0 ? ($totalMs / $runs) : 0.0;
                $dataId = $this->n($basePayload['data_id'] ?? $ctx['data_id'] ?? 0);
                Log::debug('perf.furusato.upper', [
                    'data_id'        => $dataId > 0 ? $dataId : null,
                    'upper_bound'    => $upper,
                    'y_current'      => $yCurrent,
                    'y_max_total'    => $yMaxTotal,
                    'calls_is_ok'    => $isOkCalls,
                    'ok'             => $okCount,
                    'ng'             => $ngCount,
                    'stage_counts'   => $stageCounts,
                    'runner_runs'    => $runs,
                    'runner_total_ms'=> round($totalMs, 1),
                    'runner_avg_ms'  => round($avgMs, 2),
                    'runner_max_ms'  => round($maxMs, 1),
                ]);
            } catch (\Throwable $e) {
                // 無視
            }
        }

        return [
            // ▼ 最終上限（1円単位の最大OK）
            // ▼ 最終上限（1000円未満切捨て後の値）
            'y_max_total' => $yMaxTotal,
            // ▼ 互換：1円精度の最大OKも保持（デバッグや比較に使える）
            'y_max_total_raw' => $yMaxTotalRaw,
            'y_max_total_floor' => $yMaxFloor,
            'y_current'   => $yCurrent,
            'y_add'       => max(0, $yMaxTotal - $yCurrent),
            // デバッグ・表示拡張用（必要なら使う）
            'pay_base'    => $payBase,
            'pay_at_max'  => $payMax,
            'tax_saved'   => $taxSaved,
            'burden'      => $burden,
            'upper_bound' => $upper,
        ];
    }

    /**
     * 当年ふるさと寄付（curr）を「入力実体」で解決する。
     * - 所得税側が 0 でも、住民税側が入っていれば拾う（ワンストップ等）
     * - pref/muni 同額コピー運用のため max() で二重計上しない
     */
    private function resolveFurusatoDonationCurrent(array $payload): int
    {
        $itax = $this->n($payload['shotokuzei_shotokukojo_furusato_curr'] ?? 0);
        $pref = $this->n($payload['juminzei_zeigakukojo_pref_furusato_curr'] ?? 0);
        $muni = $this->n($payload['juminzei_zeigakukojo_muni_furusato_curr'] ?? 0);
        return max(0, max($itax, $pref, $muni));
    }
    /**
     * OK判定：
     *  - burden<=2000
     *  - 政党/NPO/公益の所得税税額控除が減らない
     *
     * @param array{seito:int,npo:int,koueki:int} $creditBase
     */
    private function isOk(array $basePayload, array $ctxDry, array $ctx, int $y, int $payBase, array $creditBase, bool $isOnestop, int $baseFuruOnly = 0): bool
    {
        $payloadY = $this->withFurusato($basePayload, $y, $ctx);
        $out = $this->runner->run($payloadY, $ctxDry);

        if (! $isOnestop) {
            // NG条件：政党/NPO/公益の所得税税額控除が減らない
            $cSeito  = $this->n($out['tax_credit_shotoku_seito_curr']  ?? 0);
            $cNpo    = $this->n($out['tax_credit_shotoku_npo_curr']    ?? 0);
            $cKoueki = $this->n($out['tax_credit_shotoku_koueki_curr'] ?? 0);
            if ($cSeito < $creditBase['seito'] || $cNpo < $creditBase['npo'] || $cKoueki < $creditBase['koueki']) {
                return false;
            }
        }

        if ($isOnestop) {
            $yFuruOnly = $this->furusatoOnlyJuminFinal($out, $payloadY, $ctx, 'curr');
            $taxSaved = max(0, $yFuruOnly - $baseFuruOnly);
        } else {
            // 自己負担判定：burden = y - (pay(0) - pay(y))
            $payY = $this->payTotalCurr($out);
            $taxSaved = max(0, $payBase - $payY);
        }
        $burden = max(0, $y - $taxSaved);

        return $burden <= 2000;
    }

    /**
     * ふるさと寄付額の注入（同額コピー運用）
     * - 所得税側：shotokuzei_shotokukojo_furusato_curr = y
     * - 住民税側：pref/muni のふるさとも同額にする（readonlyコピー運用）
     */
    private function withFurusato(array $payload, int $y, array $ctx): array
    {
        $y = max(0, $y);
        $isOnestop = $this->isOnestopContext($ctx);

        $payload['shotokuzei_shotokukojo_furusato_curr'] = $isOnestop ? 0 : $y;
        $payload['juminzei_zeigakukojo_pref_furusato_curr'] = $y;
        $payload['juminzei_zeigakukojo_muni_furusato_curr'] = $y;

        if ($isOnestop) {
            $payload = $this->normalizeOtherDonationsForOnestopCurr($payload);
        }

        return $payload;
    }

    /**
     * ワンストップ特例ONの上限探索では、ふるさと以外の寄附（curr）は常に0扱いに揃える。
     * UI/保存の制御漏れや過去値残留があっても探索前提を一致させるための最終防衛。
     */
    private function normalizeOtherDonationsForOnestopCurr(array $payload): array
    {
        $categories = ['kyodobokin_nisseki', 'seito', 'npo', 'koueki', 'kuni', 'sonota'];

        foreach ($categories as $category) {
            $payload["shotokuzei_shotokukojo_{$category}_curr"] = 0;
            $payload["shotokuzei_zeigakukojo_{$category}_curr"] = 0;
            $payload["juminzei_zeigakukojo_pref_{$category}_curr"] = 0;
            $payload["juminzei_zeigakukojo_muni_{$category}_curr"] = 0;
        }

        return $payload;
    }

    private function payTotalCurr(array $payload): int
    {
        $shotoku = $this->n($payload['tax_gokei_shotoku_curr'] ?? 0);
        $jumin   = $this->n($payload['tax_gokei_jumin_curr']   ?? 0);
        return max(0, $shotoku) + max(0, $jumin);
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int) floor((float) $v) : 0;
    }

    /**
     * dry-run のときだけ ctx にフラグを付ける（下流Calculatorのログ抑制用）
     *
     * @param array<string,mixed> $ctx
     * @return array<string,mixed>
     */
    private function withDryRunFlag(array $ctx): array
    {
        $ctx['dry_run'] = true;
        return $ctx;
    }

    private function isOnestopContext(array $ctx): bool
    {
        $settings = is_array($ctx['syori_settings'] ?? null) ? $ctx['syori_settings'] : [];

        $curr = $this->n($settings['one_stop_flag_curr'] ?? null);
        if ($curr === 1) {
            return true;
        }

        $base = $this->n($settings['one_stop_flag'] ?? null);
        if ($base === 1) {
            return true;
        }

        $isOnestop = $ctx['is_onestop'] ?? null;
        if ($isOnestop === true || $this->n($isOnestop) === 1) {
            return true;
        }

        $reportKey = strtolower((string) ($ctx['report_key'] ?? ''));
        return str_contains($reportKey, 'onestop');
    }

    /**
     * 住民税：ふるさと only final（天井後ふるさと分）
     * = max(0, kifukin_zeigaku_kojo_gokei - other_basic)
     */
    private function furusatoOnlyJuminFinal(array $outDryRun, array $payloadUsed, array $ctx, string $period): int
    {
        $p = $period;
        $kifukinPost = $this->n($outDryRun["kifukin_zeigaku_kojo_gokei_{$p}"] ?? 0);
        if ($kifukinPost <= 0) return 0;

        // ▼ UI同額コピー対策：カテゴリごと max(pref,muni) を1回だけ採用
        $getCatTotal = function (string $cat) use ($payloadUsed, $p): int {
            $pref = $this->n($payloadUsed["juminzei_zeigakukojo_pref_{$cat}_{$p}"] ?? 0);
            $muni = $this->n($payloadUsed["juminzei_zeigakukojo_muni_{$cat}_{$p}"] ?? 0);
            return max(0, max($pref, $muni));
        };

        $furusatoTotal = $getCatTotal('furusato');
        $otherCats = ['kyodobokin_nisseki', 'npo', 'koueki', 'sonota'];
        $otherTotal = 0;
        foreach ($otherCats as $c) {
            $otherTotal += $getCatTotal($c);
        }

        // -2,000 は furusato 優先（furusato<2000 の残りは other）
        $deductFuru  = min(2_000, $furusatoTotal);
        $deductOther = max(0, 2_000 - $deductFuru);
        $otherAfter  = max(0, $otherTotal - $deductOther);

        $mother = $this->n($outDryRun["sum_for_sogoshotoku_etc_{$p}"] ?? 0);
        $cap30  = (int) floor(max(0, $mother) * 0.3);

        $year = isset($ctx['master_kihu_year']) ? (int) $ctx['master_kihu_year'] : 0;
        $companyId = isset($ctx['company_id']) && $ctx['company_id'] !== '' ? (int) $ctx['company_id'] : null;
        $dataId = isset($ctx['data_id']) && $ctx['data_id'] !== '' ? (int) $ctx['data_id'] : null;
        $settings = is_array($ctx['syori_settings'] ?? null) ? $ctx['syori_settings'] : [];
        $shitei = $this->n($settings["shitei_toshi_flag_{$p}"] ?? $settings['shitei_toshi_flag'] ?? 0) === 1;

        $prefRate = 0.0; $muniRate = 0.0;
        if ($year > 0) {
            $rows = $this->masterProvider->getJuminRates($year, $companyId, $dataId)->all();
            foreach ($rows as $r) {
                $r = is_array($r) ? $r : (array)$r;
                if ((string)($r['category'] ?? '') !== '基本控除') continue;
                $prefPct = (float)($shitei ? ($r['pref_specified'] ?? 0) : ($r['pref_non_specified'] ?? 0));
                $muniPct = (float)($shitei ? ($r['city_specified'] ?? 0) : ($r['city_non_specified'] ?? 0));
                $prefRate = max(0.0, $prefPct / 100.0);
                $muniRate = max(0.0, $muniPct / 100.0);
                break;
            }
        }

        // other の基本控除（合算ベースを1回だけ作り、県/市率で別々にceil）
        $eligibleOther = max(min($otherAfter, $cap30), 0);
        $otherBasicPref = (int) ceil($eligibleOther * $prefRate);
        $otherBasicMuni = (int) ceil($eligibleOther * $muniRate);
        $otherBasicTotal = max(0, $otherBasicPref + $otherBasicMuni);

        return max(0, $kifukinPost - $otherBasicTotal);
    }
}
