<?php

namespace App\Domain\Tax\Services;

use App\Domain\Tax\Contracts\MasterProviderContract;

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
        private readonly MasterProviderContract $masterProvider,
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
        $t1 = $this->extract($o1, $p1, $ctx);

        // ②：ふるさと=0、その他=現状
        $p2 = $this->withDonations($basePayload, 0, false);
        $o2 = $this->runner->run($p2, $ctx);
        $t2 = $this->extract($o2, $p2, $ctx);

        // ③：ふるさと=現状、その他=現状
        $p3 = $this->withDonations($basePayload, $yCurrent, false);
        $o3 = $this->runner->run($p3, $ctx);
        $t3 = $this->extract($o3, $p3, $ctx);

        // ④：ふるさと=上限、その他=現状
        $p4 = $this->withDonations($basePayload, max(0, $yMaxTotal), false);
        $o4 = $this->runner->run($p4, $ctx);
        $t4 = $this->extract($o4, $p4, $ctx);

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
     * @return array{itax:int,j_muni:int,j_pref:int,j_total:int,total:int,furusato_only_jumin_final:int}
     */
    private function extract(array $payload, array $payloadUsed, array $ctx): array
    {
        $itax = $this->n($payload['tax_gokei_shotoku_curr'] ?? 0);
        $jPref = $this->n($payload['tax_gokei_jumin_pref_curr'] ?? 0);
        $jMuni = $this->n($payload['tax_gokei_jumin_muni_curr'] ?? 0);
        $jTotal = $jPref + $jMuni;

        // ★帳票4と同じ定義：住民税の「ふるさと納税 only final（天井後ふるさと分）」
        $furuOnlyFinal = $this->furusatoOnlyJuminFinal($payload, $payloadUsed, $ctx, 'curr');

        return [
            'itax' => $itax,
            'j_muni' => $jMuni,
            'j_pref' => $jPref,
            'j_total' => $jTotal,
            'total' => $itax + $jTotal,
            'furusato_only_jumin_final' => $furuOnlyFinal,
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
            // ★住民税は「ふるさと納税 only final（天井後ふるさと分）」を“控除額そのもの”として扱う。
            //   これは tax_gokei のような「支払額」ではないため、差分は target − base（増えた控除＝得）にする。
            'jumin' => max(0, ($target['furusato_only_jumin_final'] ?? 0) - ($base['furusato_only_jumin_final'] ?? 0)),
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

        // -2,000 はふるさと優先（furusato<2000 の残りは other 側）
        $deductFuru  = min(2_000, $furusatoTotal);
        $deductOther = max(0, 2_000 - $deductFuru);
        $otherAfter  = max(0, $otherTotal - $deductOther);

        // 30% cap（母数：sum_for_sogoshotoku_etc）
        $mother = $this->n($outDryRun["sum_for_sogoshotoku_etc_{$p}"] ?? 0);
        $cap30  = (int) floor(max(0, $mother) * 0.3);

        // 基本控除率（master: 基本控除 → 0.xx）
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

        $eligibleOther = max(min($otherAfter, $cap30), 0);
        $otherBasicPref = (int) ceil($eligibleOther * $prefRate);
        $otherBasicMuni = (int) ceil($eligibleOther * $muniRate);
        $otherBasicTotal = max(0, $otherBasicPref + $otherBasicMuni);

        return max(0, $kifukinPost - $otherBasicTotal);
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int) floor((float) $v) : 0;
    }
}
