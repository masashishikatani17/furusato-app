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
        // y_current（当年ふるさと寄付：帳票/上限制御の実体）
        // - ワンストップ等で「所得税側=0 / 住民税側だけ入力」のケースがあり得るため、
        //   ふるさと寄付額は (所得税SoT, 住民税pref, 住民税muni) の最大値を採用する。
        // - pref/muni は同額コピー運用が前提なので max() で二重計上にならない。
        $yCurrent = $this->resolveFurusatoDonationCurrent($basePayload);

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
        // ★ まずは 1円単位の最大OK
        $yMaxTotalRaw = $lo;

        // ★ 最終仕様：千円未満切捨てを「最終上限額」とする
        $yMaxTotal = $this->floorToThousands($yMaxTotalRaw);

        // 念のため：切捨て後でもOKか再確認し、NGなら 1,000円単位で下げる
        // （段差や丸めでレアケースが出ても破綻しないように）
        while ($yMaxTotal > 0 && ! $this->isOk($basePayload, $ctx, $yMaxTotal, $payBase, $creditBase)) {
            $yMaxTotal = max(0, $yMaxTotal - 1000);
        }

        // 仕上げ：yMaxでの支払額/自己負担（参考）
        $outMax = $this->runner->run($this->withFurusato($basePayload, $yMaxTotal), $ctx);
        $payMax = $this->payTotalCurr($outMax);
        // 減税額は 1円単位（百円未満切捨てしない）
        $taxSavedRaw = max(0, $payBase - $payMax);
        $taxSaved = (int) floor($taxSavedRaw);
        $burden = $yMaxTotal - $taxSaved;

        return [
            // ▼ 最終上限（千円未満切捨て後）
            'y_max_total' => $yMaxTotal,
            // ▼ 参考：探索で得た 1円単位の最大OK（必要ならデバッグ表示に使う）
            'y_max_total_raw' => $yMaxTotalRaw,
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
    private function isOk(array $basePayload, array $ctx, int $y, int $payBase, array $creditBase): bool
    {
        $out = $this->runner->run($this->withFurusato($basePayload, $y), $ctx);

        // NG条件：政党/NPO/公益の所得税税額控除が減らない
        if ($this->n($out['tax_credit_shotoku_seito_curr']  ?? 0) < $creditBase['seito']) return false;
        if ($this->n($out['tax_credit_shotoku_npo_curr']    ?? 0) < $creditBase['npo']) return false;
        if ($this->n($out['tax_credit_shotoku_koueki_curr'] ?? 0) < $creditBase['koueki']) return false;

        // 自己負担判定：burden = y - (payBase - payY)
        $payY = $this->payTotalCurr($out);
        // 減税額は 1円単位（百円未満切捨てしない）
        $taxSavedRaw = max(0, $payBase - $payY);
        $taxSaved = (int) floor($taxSavedRaw);
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

    /**
     * 千円未満切捨て（0下限）
     */
    private function floorToThousands(int $v): int
    {
        if ($v <= 0) return 0;
        return (int) (floor($v / 1000) * 1000);
    }
}
