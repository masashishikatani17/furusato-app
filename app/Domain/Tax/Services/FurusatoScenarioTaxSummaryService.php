<?php

namespace App\Domain\Tax\Services;

/**
 * ①〜④の税額スナップショット（所得税/住民税 市/住民税 県/合計）を dry-run で生成する。
 *
 * ① ふるさと=0、その他寄付=0
 * ② ふるさと=0、その他寄付=現在入力
 * ③ ふるさと=現在入力、その他寄付=現在入力
 * ④ ふるさと=y_max_total、その他寄付=現在入力
 *
 * 減税額は「①−各ケース」でプラスが“得”になるように返す。
 * さらに別枠で「②−③/④」も返す（追加効果）。
 */
final class FurusatoScenarioTaxSummaryService
{
    public function __construct(
        private readonly FurusatoDryRunCalculatorRunner $runner,
    ) {}

    /**
     * @param  array<string,mixed> $basePayload  現在のSoT payload（previewPayload等）
     * @param  array<string,mixed> $ctx          calculator ctx
     * @param  int                 $yMaxTotal    ④のふるさと寄付額（上限）
     * @return array<string,mixed>
     */
    public function build(array $basePayload, array $ctx, int $yMaxTotal): array
    {
        $yCurrent = $this->n($basePayload['shotokuzei_shotokukojo_furusato_curr'] ?? 0);

        // ①：ふるさと=0、その他=0
        $p1 = $this->withDonations($basePayload, 0, true);
        $o1 = $this->runner->run($p1, $ctx);
        $t1 = $this->extract($o1);

        // ②：ふるさと=0、その他=現状
        $p2 = $this->withDonations($basePayload, 0, false);
        $o2 = $this->runner->run($p2, $ctx);
        $t2 = $this->extract($o2);

        // ③：ふるさと=現状、その他=現状
        $p3 = $this->withDonations($basePayload, $yCurrent, false);
        $o3 = $this->runner->run($p3, $ctx);
        $t3 = $this->extract($o3);

        // ④：ふるさと=上限、その他=現状
        $p4 = $this->withDonations($basePayload, max(0, $yMaxTotal), false);
        $o4 = $this->runner->run($p4, $ctx);
        $t4 = $this->extract($o4);

        // ①基準の減税額（①−n）
        $s12 = $this->diffSaved($t1, $t2);
        $s13 = $this->diffSaved($t1, $t3);
        $s14 = $this->diffSaved($t1, $t4);

        // 差分（左−右：プラスが得）
        $s23 = $this->diffSaved($t2, $t3);
        $s24 = $this->diffSaved($t2, $t4);
        $s34 = $this->diffSaved($t3, $t4);

        return [
            'case1' => $t1,
            'case2' => $t2,
            'case3' => $t3,
            'case4' => $t4,
            'saved_1_2' => $s12,
            'saved_1_3' => $s13,
            'saved_1_4' => $s14,
            'saved_2_3' => $s23,
            'saved_2_4' => $s24,
            'saved_3_4' => $s34,
            'y_current' => $yCurrent,
            'y_max_total' => max(0, $yMaxTotal),
        ];
    }

    /**
     * 税額スナップショット（currのみ）
     * @return array{itax:int,j_muni:int,j_pref:int,j_total:int,total:int}
     */
    private function extract(array $payload): array
    {
        $itax = $this->n($payload['tax_gokei_shotoku_curr'] ?? 0);
        $jPref = $this->n($payload['tax_gokei_jumin_pref_curr'] ?? 0);
        $jMuni = $this->n($payload['tax_gokei_jumin_muni_curr'] ?? 0);
        $jTotal = $jPref + $jMuni;
        return [
            'itax' => $itax,
            'j_muni' => $jMuni,
            'j_pref' => $jPref,
            'j_total' => $jTotal,
            'total' => $itax + $jTotal,
        ];
    }

    /**
     * 減税額 = base − target（プラスが得）
     * @param array{itax:int,j_total:int} $base
     * @param array{itax:int,j_total:int} $target
     * @return array{itax:int,jumin:int}
     */
    private function diffSaved(array $base, array $target): array
    {
        return [
            'itax' => max(0, ($base['itax'] ?? 0) - ($target['itax'] ?? 0)),
            'jumin' => max(0, ($base['j_total'] ?? 0) - ($target['j_total'] ?? 0)),
        ];
    }

    /**
     * ふるさと寄付額を注入し、必要なら「その他寄付」もゼロ化する。
     *
     * @param bool $zeroOther  trueなら「ふるさと以外の寄付」も全て0にする（①）
     */
    private function withDonations(array $payload, int $furusatoY, bool $zeroOther): array
    {
        $furusatoY = max(0, $furusatoY);

        // ふるさと（所得税SoT + 住民税pref/muni同額コピー）
        $payload['shotokuzei_shotokukojo_furusato_curr'] = $furusatoY;
        $payload['juminzei_zeigakukojo_pref_furusato_curr'] = $furusatoY;
        $payload['juminzei_zeigakukojo_muni_furusato_curr'] = $furusatoY;

        if (! $zeroOther) {
            return $payload;
        }

        // ▼ 所得税：所得控除側（ふるさと以外）
        foreach (['kyodobokin_nisseki','seito','npo','koueki','kuni','sonota'] as $cat) {
            $payload["shotokuzei_shotokukojo_{$cat}_curr"] = 0;
        }
        // ▼ 所得税：税額控除側（政党/NPO/公益）
        foreach (['seito','npo','koueki'] as $cat) {
            $payload["shotokuzei_zeigakukojo_{$cat}_curr"] = 0;
        }

        // ▼ 住民税：寄附金入力（pref/muni：ふるさと以外）
        foreach (['pref','muni'] as $area) {
            foreach (['kyodobokin_nisseki','npo','koueki','sonota'] as $cat) {
                $payload["juminzei_zeigakukojo_{$area}_{$cat}_curr"] = 0;
            }
        }

        return $payload;
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int) floor((float) $v) : 0;
    }
}
