<?php

namespace App\Domain\Tax\Services;

use Illuminate\Support\Facades\Log;

/**
 * 寄附金額別損得シミュレーション（PDF用）
 *
 * - 計算の正は Calculator（dry-run runner）
 * - DB/Session へ副作用を出さない（runnerのみ）
 * - ふるさと以外の寄付は basePayload のまま固定し、ふるさと寄付だけを y として動かす
 * - 基準：表示用上限 y_center = floor(y_max_total / 1000) * 1000（千円未満切捨て）
 * - 区分：区分15が y_center、区分kは y = max(0, y_center + (15-k)*step)
 * - 左表stepは右表より大きい（粗い）
 */
final class FurusatoSonntokuSimulationService
{
    public function __construct(
        private readonly FurusatoDryRunCalculatorRunner $runner,
        private readonly FurusatoPracticalUpperLimitService $upperSvc,
    ) {}

    /**
     * デバッグログ（デフォルト無効）
     * - .env: FURUSATO_DEBUG_LOG=1 のときのみ出す
     */
    private function dbg(string $message, array $context = []): void
    {
        if ((string) env('FURUSATO_DEBUG_LOG', '0') !== '1') {
            return;
        }
        Log::debug($message, $context);
    }

    /**
     * @param  array<string,mixed> $basePayload  現在のSoT payload（FurusatoInput.payload 等）
     * @param  array<string,mixed> $ctx          calculator ctx（syori_settings等）
     * @return array<string,mixed>
     */
    public function build(array $basePayload, array $ctx): array
    {
        // ---- DEBUG: 入口の状態 ----
        $this->dbg('[sonntoku] build start', [
            'has_payload' => $basePayload !== [],
            'payload_keys_count' => is_array($basePayload) ? count($basePayload) : 0,
            'ctx_data_id' => $ctx['data_id'] ?? null,
            'ctx_company_id' => $ctx['company_id'] ?? null,
            'ctx_kihu_year' => $ctx['kihu_year'] ?? null,
            'ctx_master_kihu_year' => $ctx['master_kihu_year'] ?? null,
            'has_syori_settings' => is_array($ctx['syori_settings'] ?? null),
        ]);

        // 1) 実利上限（1円単位）→ 表示用上限（千円未満切捨て）
        $upper = $this->upperSvc->compute($basePayload, $ctx);
        $yMaxTotal = $this->n($upper['y_max_total'] ?? 0);
        $yCenter = intdiv(max(0, $yMaxTotal), 1000) * 1000;

        $this->dbg('[sonntoku] upper computed', [
            'y_max_total' => $yMaxTotal,
            'y_center' => $yCenter,
            'upper_debug' => [
                'pay_base' => $upper['pay_base'] ?? null,
                'pay_at_max' => $upper['pay_at_max'] ?? null,
                'tax_saved' => $upper['tax_saved'] ?? null,
                'burden' => $upper['burden'] ?? null,
                'upper_bound' => $upper['upper_bound'] ?? null,
            ],
        ]);

        // 2) 左右step（左が粗い）
        [$leftStep, $rightStep] = $this->resolveSteps($yCenter);

        $this->dbg('[sonntoku] steps resolved', [
            'y_center' => $yCenter,
            'left_step' => $leftStep,
            'right_step' => $rightStep,
        ]);
 
        // 3) ベース（ふるさと=0）の税額SoT
        $outBase = $this->runner->run($this->withFurusato($basePayload, 0), $ctx);
        $baseTax = $this->extractTax($outBase);

        $this->dbg('[sonntoku] base tax (y=0)', [
            'itax' => $baseTax['itax'],
            'j_pref' => $baseTax['j_pref'],
            'j_muni' => $baseTax['j_muni'],
            'j_total' => $baseTax['j_total'],
            'total' => $baseTax['total'],
            'raw_keys_present' => [
                'tax_gokei_shotoku_curr' => array_key_exists('tax_gokei_shotoku_curr', $outBase),
                'tax_gokei_jumin_curr' => array_key_exists('tax_gokei_jumin_curr', $outBase),
                'tax_gokei_jumin_pref_curr' => array_key_exists('tax_gokei_jumin_pref_curr', $outBase),
                'tax_gokei_jumin_muni_curr' => array_key_exists('tax_gokei_jumin_muni_curr', $outBase),
            ],
        ]);

        // 4) 左右 30行を生成
        $leftRows  = $this->buildRows($basePayload, $ctx, $yCenter, $leftStep,  $baseTax);
        $rightRows = $this->buildRows($basePayload, $ctx, $yCenter, $rightStep, $baseTax);

        // ---- DEBUG: 代表行（区分15=中心） ----
        $midL = $leftRows[15] ?? null;
        $midR = $rightRows[15] ?? null;
        $this->dbg('[sonntoku] mid rows snapshot', [
            'left15' => is_array($midL) ? [
                'y' => $midL['y'] ?? null,
                'saved_total' => $midL['saved_total'] ?? null,
                'diff' => $midL['diff'] ?? null,
                'gift' => $midL['gift'] ?? null,
                'net' => $midL['net'] ?? null,
                'total_amount' => $midL['total_amount'] ?? null,
            ] : null,
            'right15' => is_array($midR) ? [
                'y' => $midR['y'] ?? null,
                'saved_total' => $midR['saved_total'] ?? null,
                'diff' => $midR['diff'] ?? null,
                'gift' => $midR['gift'] ?? null,
                'net' => $midR['net'] ?? null,
                'total_amount' => $midR['total_amount'] ?? null,
            ] : null,
        ]);

        return [
            'y_max_total' => $yMaxTotal,
            'y_center'    => $yCenter,
            'base'        => $baseTax,
            'left'  => ['step' => $leftStep,  'rows' => $leftRows],
            'right' => ['step' => $rightStep, 'rows' => $rightRows],
        ];
    }

    /**
     * 左右の刻み幅（左が粗い）
     * @return array{0:int,1:int}
     */
    private function resolveSteps(int $yCenter): array
    {
        $y = max(0, $yCenter);

        // 500,000 未満：左2万 / 右1万
        if ($y < 500_000) {
            return [20_000, 10_000];
        }
        // 500,000 以上 1,000,000 未満：左3万 / 右1万
        if ($y < 1_000_000) {
            return [30_000, 10_000];
        }
        // 1,000,000 以上 2,000,000 未満：左5万 / 右2万
        if ($y < 2_000_000) {
            return [50_000, 20_000];
        }
        // 2,000,000 以上 5,000,000 未満：左10万 / 右3万
        if ($y < 5_000_000) {
            return [100_000, 30_000];
        }
        // 5,000,000 以上：左25万 / 右5万
        return [250_000, 50_000];
    }

    /**
     * 30行分を生成（区分15が中心）
     *
     * @param  array{itax:int,j_pref:int,j_muni:int,j_total:int,total:int} $baseTax
     * @return array<int, array<string,int>>
     */
    private function buildRows(array $basePayload, array $ctx, int $yCenter, int $step, array $baseTax): array
    {
        $rows = [];

        for ($k = 1; $k <= 30; $k++) {
            $offset = (15 - $k) * $step;
            $y = max(0, $yCenter + $offset);

            $out = $this->runner->run($this->withFurusato($basePayload, $y), $ctx);
            $tax = $this->extractTax($out);

            // DEBUG: 最初の1回だけ（k=15）で「tax_gokei_* が 0 になっていないか」を観測
            if ($k === 15) {
                $this->dbg('[sonntoku] row15 tax snapshot', [
                    'step' => $step,
                    'k' => $k,
                    'y' => $y,
                    'tax' => $tax,
                    'raw_tax_keys' => [
                        'tax_gokei_shotoku_curr' => $out['tax_gokei_shotoku_curr'] ?? null,
                        'tax_gokei_jumin_curr' => $out['tax_gokei_jumin_curr'] ?? null,
                        'tax_gokei_jumin_pref_curr' => $out['tax_gokei_jumin_pref_curr'] ?? null,
                        'tax_gokei_jumin_muni_curr' => $out['tax_gokei_jumin_muni_curr'] ?? null,
                    ],
                    // SoTの母数が0なら upstream が死んでいる可能性が高い
                    'sum_for_sogoshotoku_etc_curr' => $out['sum_for_sogoshotoku_etc_curr'] ?? null,
                    'tb_sogo_shotoku_curr' => $out['tb_sogo_shotoku_curr'] ?? null,
                    'tb_sogo_jumin_curr' => $out['tb_sogo_jumin_curr'] ?? null,
                ]);
            }

            // 減税額（ベースとの差）
            $savedItax  = max(0, $baseTax['itax']    - $tax['itax']);
            $savedPref  = max(0, $baseTax['j_pref']  - $tax['j_pref']);
            $savedMuni  = max(0, $baseTax['j_muni']  - $tax['j_muni']);
            $savedJumin = max(0, $baseTax['j_total'] - $tax['j_total']);
            $savedTotal = max(0, $baseTax['total']   - $tax['total']);

            // ③ 差引（自己負担）= y - 減税額
            $burden = $y - $savedTotal;

            // ④ 返礼品額（将来）= y × 30%（推定・帳票前提）
            $gift = (int) floor($y * 0.30);

            // ⑤ 実質負担 = ③ - ④
            $net = $burden - $gift;

            $rows[$k] = [
                'k' => $k,
                'y' => $y,

                // 税額（表示用）
                'itax_amount'      => $tax['itax'],
                'jumin_pref_amount'=> $tax['j_pref'],
                'jumin_muni_amount'=> $tax['j_muni'],
                'jumin_amount'     => $tax['j_total'],
                'total_amount'     => $tax['total'],

                // 減税額（表示用）
                'saved_itax'       => $savedItax,
                'saved_jumin_pref' => $savedPref,
                'saved_jumin_muni' => $savedMuni,
                'saved_jumin'      => $savedJumin,
                'saved_total'      => $savedTotal,

                // PDF列
                'diff'             => $burden, // ①-②=③
                'gift'             => $gift,   // ①×30%=④
                'net'              => $net,    // ③-④
            ];
        }

        return $rows;
    }

    /**
     * ふるさと寄付額の注入（同額コピー運用）
     */
    private function withFurusato(array $payload, int $y): array
    {
        $y = max(0, $y);
        $payload['shotokuzei_shotokukojo_furusato_curr'] = $y;
        $payload['juminzei_zeigakukojo_pref_furusato_curr'] = $y;
        $payload['juminzei_zeigakukojo_muni_furusato_curr'] = $y;
        return $payload;
    }

    /**
     * 税額SoT（curr）を抜き出す
     *
     * @return array{itax:int,j_pref:int,j_muni:int,j_total:int,total:int}
     */
    private function extractTax(array $payload): array
    {
        $itax  = $this->n($payload['tax_gokei_shotoku_curr'] ?? 0);
        $jPref = $this->n($payload['tax_gokei_jumin_pref_curr'] ?? 0);
        $jMuni = $this->n($payload['tax_gokei_jumin_muni_curr'] ?? 0);
        $jTot  = $this->n($payload['tax_gokei_jumin_curr'] ?? ($jPref + $jMuni));

        return [
            'itax'   => max(0, $itax),
            'j_pref' => max(0, $jPref),
            'j_muni' => max(0, $jMuni),
            'j_total'=> max(0, $jTot),
            'total'  => max(0, $itax) + max(0, $jTot),
        ];
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int) floor((float) $v) : 0;
    }
}
