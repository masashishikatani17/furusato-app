<?php

namespace App\Domain\Tax\Services;

/**
 * ふるさと納税の「実利上限（自己負担<=2,000円）」を探索して返す。
 *
 * - ベースは「ふるさと=0」（他寄附固定）
 * - 候補yで計算した支払額（tax_gokei_shotoku + tax_gokei_jumin）の差分から減税額を算出
 * - 自己負担 = y - 減税額 が 2,000円以下の最大yを探索（二分探索・1円単位）
 * - 追加条件：政党/NPO/公益の所得税税額控除が「現在の入力値」より減らない
 */
final class FurusatoPracticalUpperLimitService
{
    public function __construct(
        private readonly FurusatoDryRunCalculatorRunner $runner,
    ) {}

    /**
     * @param  array<string,mixed> $basePayload  現在のSoT payload（DB/セッションの最新）
     * @param  array<string,mixed> $ctx          calculator ctx（syori_settings等）
     * @return array<string,mixed>
     */
    public function compute(array $basePayload, array $ctx): array
    {
        // y_current（当年ふるさと寄付：SoT）
        $yCurrent = $this->n($basePayload['shotokuzei_shotokukojo_furusato_curr'] ?? 0);

        // (A) NG判定の基準：現在yでの政党/NPO/公益の所得税税額控除
        $payloadAtCurrent = $this->withFurusato($basePayload, $yCurrent);
        $outCurrent = $this->runner->run($payloadAtCurrent, $ctx);
        $creditBase = [
            'seito'  => $this->n($outCurrent['tax_credit_shotoku_seito_curr']  ?? 0),
            'npo'    => $this->n($outCurrent['tax_credit_shotoku_npo_curr']    ?? 0),
            'koueki' => $this->n($outCurrent['tax_credit_shotoku_koueki_curr'] ?? 0),
        ];

        // (B) 自己負担の基準：ふるさと=0 の支払額（復興税込み）
        $payloadZero = $this->withFurusato($basePayload, 0);
        $outZero = $this->runner->run($payloadZero, $ctx);
        $payBase = $this->payTotalCurr($outZero);

        // 探索上界：0.4*S40（S40 = sum_for_sogoshotoku_etc_curr）
        $S40 = $this->n(
            $basePayload['sum_for_sogoshotoku_etc_curr']
            ?? $outCurrent['sum_for_sogoshotoku_etc_curr']
            ?? $outZero['sum_for_sogoshotoku_etc_curr']
            ?? 0
        );
        $upper = max(0, (int) floor($S40 * 0.4));

        // 二分探索：最大OKのy（1円単位）
        $lo = 0;
        $hi = $upper;
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi + 1, 2);
            if ($this->isOk($basePayload, $ctx, $mid, $payBase, $creditBase)) {
                $lo = $mid;
            } else {
                $hi = $mid - 1;
            }
        }
        $yMaxTotal = $lo;

        // 仕上げ：yMaxでの支払額/自己負担（参考）
        $outMax = $this->runner->run($this->withFurusato($basePayload, $yMaxTotal), $ctx);
        $payMax = $this->payTotalCurr($outMax);
        $taxSaved = max(0, $payBase - $payMax);
        $burden = $yMaxTotal - $taxSaved;

        return [
            'y_max_total' => $yMaxTotal,
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
     * OK判定：
     *  - burden<=2000
     *  - 政党/NPO/公益の所得税税額控除が減らない
     *
     * @param array{seito:int,npo:int,koueki:int} $creditBase
     */
    private function isOk(array $basePayload, array $ctx, int $y, int $payBase, array $creditBase): bool
    {
        $out = $this->runner->run($this->withFurusato($basePayload, $y), $ctx);

        // NG条件：政党/NPO/公益の所得税税額控除が減らない
        if ($this->n($out['tax_credit_shotoku_seito_curr']  ?? 0) < $creditBase['seito']) return false;
        if ($this->n($out['tax_credit_shotoku_npo_curr']    ?? 0) < $creditBase['npo']) return false;
        if ($this->n($out['tax_credit_shotoku_koueki_curr'] ?? 0) < $creditBase['koueki']) return false;

        // 自己負担判定：burden = y - (payBase - payY)
        $payY = $this->payTotalCurr($out);
        $taxSaved = max(0, $payBase - $payY);
        $burden = $y - $taxSaved;

        return $burden <= 2000;
    }

    /**
     * ふるさと寄付額の注入（同額コピー運用）
     * - 所得税側：shotokuzei_shotokukojo_furusato_curr = y
     * - 住民税側：pref/muni のふるさとも同額にする（readonlyコピー運用）
     */
    private function withFurusato(array $payload, int $y): array
    {
        $y = max(0, $y);

        $payload['shotokuzei_shotokukojo_furusato_curr'] = $y;
        $payload['juminzei_zeigakukojo_pref_furusato_curr'] = $y;
        $payload['juminzei_zeigakukojo_muni_furusato_curr'] = $y;

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
}
