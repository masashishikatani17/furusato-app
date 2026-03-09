<?php

namespace App\Reports\Jumin;

use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoResult;
use App\Domain\Tax\Factory\SyoriSettingsFactory;
use App\Domain\Tax\Support\PayloadNormalizer;
use App\Domain\Tax\Services\FurusatoDryRunCalculatorRunner;
use App\Domain\Tax\Services\FurusatoPracticalUpperLimitService;
use App\Domain\Tax\Services\FurusatoScenarioTaxSummaryService;
use App\Domain\Tax\Contracts\MasterProviderContract;
use App\Reports\Contracts\ReportInterface;

class JuminKeigengakuOnestopReport implements ReportInterface
{
    public function viewName(): string
    {
        // Blade名は番号付き、URLキーは数字なし（A案）
        return 'pdf/4_juminkeigengaku_onestop';
    }

    public function buildViewData(Data $data): array
    {
        // 既定：上限額まで寄付（従来互換）
        return $this->buildViewDataWithContext($data, ['report_key' => 'juminkeigengaku_onestop']);
    }

    /**
     * @param array<string,mixed> $context
     */
    public function buildViewDataWithContext(Data $data, array $context): array
    {
        $guestName = $data->guest?->name ?? '（名称未登録）';
        $year = (int)($data->kihu_year ?? now()->year);

        // payload 読込
        $payload = [];
        $storedResults = FurusatoResult::query()
            ->where('data_id', (int)$data->id)
            ->value('payload');
        if (is_array($storedResults)) {
            $candidate = $storedResults['payload'] ?? $storedResults['upper'] ?? null;
            if (is_array($candidate)) $payload = $candidate;
        }
        if ($payload === []) {
            $storedInput = FurusatoInput::query()
                ->where('data_id', (int)$data->id)
                ->value('payload');
            if (is_array($storedInput)) $payload = $storedInput;
        }
        /** @var PayloadNormalizer $normalizer */
        $normalizer = app(PayloadNormalizer::class);
        $payload = $normalizer->normalize($payload);

        /** @var SyoriSettingsFactory $syoriFactory */
        $syoriFactory = app(SyoriSettingsFactory::class);
        $syoriSettings = $syoriFactory->buildInitial($data);

        $masterYear = $year;
        $ctx = [
            'master_kihu_year' => $masterYear,
            'kihu_year'        => $year,
            'company_id'       => $data->company_id !== null ? (int)$data->company_id : null,
            'data_id'          => $data->id !== null ? (int)$data->id : null,
            'syori_settings'   => $syoriSettings,
            'data'             => $data,
            'guest_birth_date' => ($data->guest?->birth_date instanceof \DateTimeInterface)
                ? $data->guest->birth_date->format('Y-m-d')
                : (is_string($data->guest?->birth_date) ? $data->guest->birth_date : null),
            'taxpayer_sex'     => data_get($data, 'guest.sex')
                ?? data_get($data, 'guest.gender')
                ?? data_get($data, 'guest.sex_code'),
            'is_onestop'       => true,
            'report_key'       => (string)($context['report_key'] ?? ''),
        ];

        /** @var FurusatoDryRunCalculatorRunner $runner */
        $runner = app(FurusatoDryRunCalculatorRunner::class);

        $yMaxUpper = 0;
        try {
            /** @var FurusatoPracticalUpperLimitService $upperSvc */
            $upperSvc = app(FurusatoPracticalUpperLimitService::class);
            $upper = $upperSvc->compute($payload, $ctx);
            $yMaxUpper = (int)($upper['y_max_total'] ?? 0);
        } catch (\Throwable $e) {
            $yMaxUpper = 0;
        }

        /** @var FurusatoScenarioTaxSummaryService $scenarioSummary */
        $scenarioSummary = app(FurusatoScenarioTaxSummaryService::class);
        $scenario = $scenarioSummary->build($payload, $ctx, $yMaxUpper);

        // --------------------------------------------
        // ★出力モード判定（*_curr なら current）
        // --------------------------------------------
        $reportKey = strtolower((string)($context['report_key'] ?? ''));
        $mode = str_ends_with($reportKey, '_curr') ? 'current' : 'max';

        // branch 参照事故防止
        $payloadAt = null; $payloadAtMax = null;

        if ($mode === 'current') {
            // current寄附額は scenario service と同じ定義（所得税/住民税pref/住民税muni の最大値）を使う。
            $y = $this->n($scenario['y_current'] ?? 0);
            $payloadAt = $this->withFurusatoForOnestop($payload, $y);
            $payloadAtMax = $payloadAt;
            $yMax = $y;
            $taxSavedTotal = $this->n($scenario['compare_total_saved_2_3'] ?? 0);
        } else {
            $payloadAtMax = $this->withFurusatoForOnestop($payload, $yMaxUpper);
            $yMax = $yMaxUpper;
            $taxSavedTotal = $this->n($scenario['compare_total_saved_2_4'] ?? 0);
        }

        // 仕様: ①〜⑰は説明用、上部比較表は結果用。
        // 比較表の減税額は scenario service の compare 専用値（所得税+住民税）を使う。
        // ワンストップでは所得税減税額が 0 となるため、結果として住民税のみが表示される。
        $taxSavedTotal = max(0, $taxSavedTotal);
        $burden = max(0, $yMax - $taxSavedTotal);

        $outAtMax  = $runner->run($payloadAtMax,  $ctx);

        $p = 'curr';

        // ④〜⑬などは通常版と同様のキーを利用（ワンストップでは ⑯ が追加で表示される）
        $maeMuni = $this->n($outAtMax["chosei_mae_shotokuwari_muni_{$p}"] ?? 0);
        $maePref = $this->n($outAtMax["chosei_mae_shotokuwari_pref_{$p}"] ?? 0);
        $kojoMuni = $this->n($outAtMax["chosei_kojo_muni_{$p}"] ?? 0);
        $kojoPref = $this->n($outAtMax["chosei_kojo_pref_{$p}"] ?? 0);
        $goMuni  = $this->n($outAtMax["choseigo_shotokuwari_muni_{$p}"] ?? 0);
        $goPref  = $this->n($outAtMax["choseigo_shotokuwari_pref_{$p}"] ?? 0);

        // 基本控除（表示用再計算）
        $sumPref = $this->sumJuminDonationSide($payloadAtMax, 'pref', $p);
        $sumMuni = $this->sumJuminDonationSide($payloadAtMax, 'muni', $p);
        $kifuTotal = max(0, $this->n($outAtMax["kifu_gaku_{$p}"] ?? 0));
        $mother = max(0, $this->n($outAtMax["sum_for_sogoshotoku_etc_{$p}"] ?? 0));
        $cap30  = (int) floor($mother * 0.3);
        $eligiblePref = ($sumPref > 2_000) ? max(min($sumPref, $cap30) - 2_000, 0) : 0;
        $eligibleMuni = ($sumMuni > 2_000) ? max(min($sumMuni, $cap30) - 2_000, 0) : 0;
        [$basicPrefRate, $basicMuniRate, $basicPrefPct, $basicMuniPct] = $this->resolveKihonRates($masterYear, $ctx, $syoriSettings);
        $minPref = min($eligiblePref, $cap30);
        $minMuni = min($eligibleMuni, $cap30);
        $kihonPref = (int) ceil(max($minPref, 0) * $basicPrefRate);
        $kihonMuni = (int) ceil(max($minMuni, 0) * $basicMuniRate);

        // 特例控除（⑪小数表示）
        $prefF = $this->n($payloadAtMax["juminzei_zeigakukojo_pref_furusato_{$p}"] ?? 0);
        $muniF = $this->n($payloadAtMax["juminzei_zeigakukojo_muni_furusato_{$p}"] ?? 0);
        $furusatoTotal = max(0, max($prefF, $muniF));
        $tokureiBase = max($furusatoTotal - 2_000, 0);
        $tokureiRateFinalPct = (float)($outAtMax["tokurei_rate_final_{$p}"] ?? 0.0);
        $tokureiRateFinalRatio = $tokureiRateFinalPct > 0 ? ($tokureiRateFinalPct / 100.0) : 0.0;
        $shitei = (int)($syoriSettings["shitei_toshi_flag_{$p}"] ?? $syoriSettings['shitei_toshi_flag'] ?? 0) === 1;
        // ★表示仕様：特例控除割合の按分（市/県）
        //   - 指定都市：市80% / 県20%
        //   - 指定都市以外：市60% / 県40%
        //   ※計算は丸めず、表示側で小数3位に整形する（TemplateWriter側）
        $shareMuniForRate = $shitei ? 0.8 : 0.6;
        $sharePrefForRate = 1.0 - $shareMuniForRate;
        [$sharePref, $shareMuni] = $this->resolveTokureiSharesFromMaster($masterYear, $ctx, $shitei);
        $tokurei11Total = $tokureiBase * $tokureiRateFinalRatio;
        $tokurei11Pref  = $tokurei11Total * $sharePref;
        $tokurei11Muni  = $tokurei11Total * $shareMuni;
        $cap20Pref = $this->n($outAtMax["shotokuwari20_pref_{$p}"] ?? 0);
        $cap20Muni = $this->n($outAtMax["shotokuwari20_muni_{$p}"] ?? 0);
        $tokureiJogenPref = $this->n($outAtMax["tokurei_kojo_jogen_pref_{$p}"] ?? 0);
        $tokureiJogenMuni = $this->n($outAtMax["tokurei_kojo_jogen_muni_{$p}"] ?? 0);

        // 申告特例（⑯）
        $shinkokuPref = $this->n($outAtMax["shinkokutokurei_kojo_pref_{$p}"] ?? 0);
        $shinkokuMuni = $this->n($outAtMax["shinkokutokurei_kojo_muni_{$p}"] ?? 0);

        // ⑮ 所得税率の割合（表示用）
        // - マスター(申告特例率)の ratio_a（例: 20.42）を所得税率(復興込み)として採用
        // - ⑮ = 所得税率 ÷ 特例控除割合（= ratio_a / tokurei_rate_final_percent）
        $ratioA = $this->resolveShinkokutokureiRatioA($masterYear, $ctx, $this->n($outAtMax["human_adjusted_taxable_{$p}"] ?? 0));
        $ratio15PctStr = '0.00%';
        if ($tokureiRateFinalPct > 0.0 && $ratioA > 0.0) {
            $ratio15 = $ratioA / $tokureiRateFinalPct; // 0.xx
            $ratio15PctStr = number_format($ratio15 * 100.0, 2, '.', '') . '%';
        }

        // ------------------------------------------------------------
        // ▼ ⑰（寄附金税額控除 合計）表示仕様：
        //   - 表示は「天井適用前」（基本 + 上限後特例 + 申告特例）
        //   - SoT（天井適用後）は別途保持し、下段4行（final/★）に使う
        // ------------------------------------------------------------
        $kifukinPostPref  = $this->n($outAtMax["kifukin_zeigaku_kojo_pref_{$p}"] ?? 0);
        $kifukinPostMuni  = $this->n($outAtMax["kifukin_zeigaku_kojo_muni_{$p}"] ?? 0);
        $kifukinPostGokei = $this->n($outAtMax["kifukin_zeigaku_kojo_gokei_{$p}"] ?? ($kifukinPostPref + $kifukinPostMuni));

        // 天井前（表示用）⑰ = ⑧(基本控除額) + ⑬(特例控除額) + ⑯(申告特例控除額)
        // ※⑭は⑬の再表示。⑰は「表示済み整数値」の単純合計で組み立てる。
        $kifukinPrePref  = max(0, $kihonPref) + max(0, $tokureiJogenPref) + max(0, $shinkokuPref);
        $kifukinPreMuni  = max(0, $kihonMuni) + max(0, $tokureiJogenMuni) + max(0, $shinkokuMuni);
        $kifukinPreGokei = $kifukinPrePref + $kifukinPreMuni;

        // ------------------------------------------------------------
        // ▼ 下段4行（ワンストップ版も通常版と同じロジックで出す）
        //  1) other：ふるさと以外の減税額（基本控除のみ。-2,000 はふるさと優先）
        //  2) furusato_only（天井前）= ⑰(天井前) - other
        //  3) unable（★）= furusato_only - final
        //  4) final（天井後のふるさと分）= ⑰(天井後SoT) - other
        // ------------------------------------------------------------
        $otherCats = ['kyodobokin_nisseki', 'npo', 'koueki', 'sonota'];
        $otherPrefSum = 0;
        $otherMuniSum = 0;
        foreach ($otherCats as $cat) {
            $otherPrefSum += $this->n($payloadAtMax["juminzei_zeigakukojo_pref_{$cat}_{$p}"] ?? 0);
            $otherMuniSum += $this->n($payloadAtMax["juminzei_zeigakukojo_muni_{$cat}_{$p}"] ?? 0);
        }
        // ふるさと寄付があるときは other 側で -2,000 を引かない
        // ▼ ルール（改）：-2,000円は「ふるさと優先」だが、
        //   ふるさと寄付が 2,000 円未満なら残りを other 側へ回す。
        $deductFuru  = min(2_000, max(0, $furusatoTotal));
        $deductOther = max(0, 2_000 - $deductFuru);
        $eligibleOtherPref = max(min($otherPrefSum, $cap30) - $deductOther, 0);
        $eligibleOtherMuni = max(min($otherMuniSum, $cap30) - $deductOther, 0);
        $otherPref = (int) ceil($eligibleOtherPref * $basicPrefRate);
        $otherMuni = (int) ceil($eligibleOtherMuni * $basicMuniRate);
        $otherGokei = max(0, $otherPref + $otherMuni);

        // final（天井後のふるさと分）
        $furuPostGokei = max(0, $kifukinPostGokei - $otherGokei);
        $furuPostPref  = (int) floor($furuPostGokei * $sharePref);
        $furuPostMuni  = max(0, $furuPostGokei - $furuPostPref);

        // furusato_only（天井前）
        $furuPreGokei = max(0, $kifukinPreGokei - $otherGokei);
        $furuPrePref  = (int) floor($furuPreGokei * $sharePref);
        $furuPreMuni  = max(0, $furuPreGokei - $furuPrePref);

        // unable（★）
        $unableGokei = max(0, $furuPreGokei - $furuPostGokei);
        $unablePref  = (int) floor($unableGokei * $sharePref);
        $unableMuni  = max(0, $unableGokei - $unablePref);

        return [
            'title'      => '住民税の軽減額（ワンストップ特例）',
            'year'       => $year,
            'wareki_year'=> $this->toWarekiYear($year),
            'guest_name' => $guestName,
            'data_id'    => (int)$data->id,

            'donation_amount' => (int)$yMax,
            'tax_saved_total' => (int)$taxSavedTotal,
            'burden_amount'   => (int)$burden,

            'jumin_rows' => [
                'mae' => ['muni'=>$maeMuni, 'pref'=>$maePref, 'total'=>$maeMuni+$maePref],
                'chosei_kojo' => ['muni'=>$kojoMuni, 'pref'=>$kojoPref, 'total'=>$kojoMuni+$kojoPref],
                'go'  => ['muni'=>$goMuni,  'pref'=>$goPref,  'total'=>$goMuni+$goPref],
                'kihon' => [
                    'target' => ['muni'=>$eligibleMuni, 'pref'=>$eligiblePref, 'total'=>max(0, $kifuTotal - 2_000)],
                    'cap30'  => ['muni'=>$cap30, 'pref'=>$cap30, 'total'=>$cap30],
                    'min'    => ['muni'=>$minMuni, 'pref'=>$minPref, 'total'=>min(max(0, $kifuTotal - 2_000), $cap30)],
                    'rate'   => ['muni'=>$basicMuniPct, 'pref'=>$basicPrefPct, 'total'=>($basicMuniPct + $basicPrefPct)],
                    'amount' => ['muni'=>$kihonMuni, 'pref'=>$kihonPref, 'total'=>$kihonMuni+$kihonPref],
                ],
                'tokurei' => [
                    'target' => ['muni'=>$tokureiBase, 'pref'=>$tokureiBase, 'total'=>$tokureiBase],
                    'rate_final_pct' => (float)$tokureiRateFinalPct,
                    // ★⑩表示用（按分後の割合）
                    'rate_final_pct_split' => [
                        'muni'  => $tokureiRateFinalPct * $shareMuniForRate,
                        'pref'  => $tokureiRateFinalPct * $sharePrefForRate,
                        'total' => $tokureiRateFinalPct,
                    ],
                    'calc11' => [
                        'muni'  => number_format($tokurei11Muni, 2, '.', ''),
                        'pref'  => number_format($tokurei11Pref, 2, '.', ''),
                        'total' => number_format($tokurei11Total, 2, '.', ''),
                    ],
                    'cap20' => ['muni'=>$cap20Muni, 'pref'=>$cap20Pref, 'total'=>$cap20Muni+$cap20Pref],
                    'jogen' => ['muni'=>$tokureiJogenMuni, 'pref'=>$tokureiJogenPref, 'total'=>$tokureiJogenMuni+$tokureiJogenPref],
                ],
                'shinkoku' => ['muni'=>$shinkokuMuni, 'pref'=>$shinkokuPref, 'total'=>$shinkokuMuni+$shinkokuPref],
                // ⑰：天井前（表示）
                'kifukin_total' => ['muni'=>$kifukinPreMuni, 'pref'=>$kifukinPrePref, 'total'=>$kifukinPreGokei],
                'shinkoku_ratio15_pct' => $ratio15PctStr,
            ],

            // ⑮（表示用）
            'shinkoku_ratio15_pct' => $ratio15PctStr,

            // ▼ 下段4行（通常版と同じ意味付け）
            'jumin_summary' => [
                'other' => ['muni'=>$otherMuni, 'pref'=>$otherPref, 'total'=>$otherGokei],
                'furusato_only' => ['muni'=>$furuPreMuni, 'pref'=>$furuPrePref, 'total'=>$furuPreGokei],
                'unable' => ['muni'=>$unableMuni, 'pref'=>$unablePref, 'total'=>$unableGokei],
                'final'  => ['muni'=>$furuPostMuni, 'pref'=>$furuPostPref, 'total'=>$furuPostGokei],
            ],
        ];
    }

    public function fileName(Data $data): string
    {
        $year = (int)($data->kihu_year ?? now()->year);
        return "住民税の軽減額_ワンストップ特例_{$year}_data{$data->id}.pdf";
    }

    // 全帳票：6_jintekikojosatyosei と同じ（A4横）
    public function pdfOptions(Data $data): array
    {
        return [
            'paper'  => 'a4',
            'orient' => 'landscape',
        ];
    }

    /**
     * ワンストップ特例版の説明表（①〜⑰）用に、ふるさと寄附入力を整形する。
     * - 所得税側は常に 0
     * - 住民税 pref/muni は同額 y を注入
     */
    private function withFurusatoForOnestop(array $payload, int $y): array
    {
        $y = max(0, $y);
        $payload['shotokuzei_shotokukojo_furusato_curr'] = 0;
        $payload['juminzei_zeigakukojo_pref_furusato_curr'] = $y;
        $payload['juminzei_zeigakukojo_muni_furusato_curr'] = $y;
        return $payload;
    }

    private function sumJuminDonationSide(array $payload, string $side, string $period): int
    {
        $side = ($side === 'pref') ? 'pref' : 'muni';
        $cats = ['furusato','kyodobokin_nisseki','npo','koueki','sonota'];
        $sum = 0;
        foreach ($cats as $c) {
            $sum += $this->n($payload["juminzei_zeigakukojo_{$side}_{$c}_{$period}"] ?? 0);
        }
        return max(0, $sum);
    }

    private function resolveKihonRates(int $masterYear, array $ctx, array $syoriSettings): array
    {
        /** @var MasterProviderContract $mp */
        $mp = app(MasterProviderContract::class);
        $companyId = isset($ctx['company_id']) && $ctx['company_id'] !== '' ? (int)$ctx['company_id'] : null;
        $dataId = isset($ctx['data_id']) && $ctx['data_id'] !== '' ? (int)$ctx['data_id'] : null;
        $rows = $mp->getJuminRates($masterYear, $companyId, $dataId)->all();

        $shitei = (int)($syoriSettings['shitei_toshi_flag_curr'] ?? $syoriSettings['shitei_toshi_flag'] ?? 0) === 1;
        $prefPct = 0;
        $muniPct = 0;
        foreach ($rows as $r) {
            $r = is_array($r) ? $r : (array)$r;
            if ((string)($r['category'] ?? '') !== '基本控除') continue;
            $prefPct = (int)($shitei ? ($r['pref_specified'] ?? 0) : ($r['pref_non_specified'] ?? 0));
            $muniPct = (int)($shitei ? ($r['city_specified'] ?? 0) : ($r['city_non_specified'] ?? 0));
            break;
        }
        $prefRate = max(0.0, ((float)$prefPct) / 100.0);
        $muniRate = max(0.0, ((float)$muniPct) / 100.0);
        return [$prefRate, $muniRate, $prefPct, $muniPct];
    }

    private function resolveTokureiSharesFromMaster(int $masterYear, array $ctx, bool $shitei): array
    {
        /** @var MasterProviderContract $mp */
        $mp = app(MasterProviderContract::class);
        $companyId = isset($ctx['company_id']) && $ctx['company_id'] !== '' ? (int)$ctx['company_id'] : null;
        $dataId = isset($ctx['data_id']) && $ctx['data_id'] !== '' ? (int)$ctx['data_id'] : null;
        $rows = $mp->getJuminRates($masterYear, $companyId, $dataId)->all();
        foreach ($rows as $r) {
            $r = is_array($r) ? $r : (array)$r;
            if ((string)($r['category'] ?? '') !== '特例控除') continue;
            $pref = (float)($shitei ? ($r['pref_specified'] ?? 0.2) : ($r['pref_non_specified'] ?? 0.4));
            $muni = (float)($shitei ? ($r['city_specified'] ?? 0.8) : ($r['city_non_specified'] ?? 0.6));
            $pref = max(0.0, min(1.0, $pref));
            $muni = max(0.0, min(1.0, $muni));
            return [$pref, $muni];
        }
        return [0.4, 0.6];
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int) floor((float) $v) : 0;
    }

    private function toWarekiYear(int $year): string
    {
        if ($year >= 2019) return sprintf('令和%d年', $year - 2018);
        if ($year >= 1989) return sprintf('平成%d年', $year - 1988);
        if ($year >= 1926) return sprintf('昭和%d年', $year - 1925);
        return (string) $year;
    }

    /**
     * 申告特例率マスターから ratio_a を解決（該当レンジの ratio_a を返す）
     */
    private function resolveShinkokutokureiRatioA(int $year, array $ctx, int $taxable): float
    {
        /** @var MasterProviderContract $mp */
        $mp = app(MasterProviderContract::class);
        $companyId = isset($ctx['company_id']) && $ctx['company_id'] !== '' ? (int)$ctx['company_id'] : null;
        $rows = $mp->getShinkokutokureiRates($year, $companyId)->all();

        $t = max(0, $taxable);
        foreach ($rows as $row) {
            $r = is_array($row) ? $row : (array)$row;
            $lower = (int)($r['lower'] ?? 0);
            $upper = array_key_exists('upper', $r) ? $r['upper'] : null;
            $upper = ($upper === null || $upper === '') ? null : (int)$upper;
            if ($t < $lower) continue;
            if ($upper !== null && $t > $upper) continue;
            return (float)($r['ratio_a'] ?? 0.0);
        }
        return 0.0;
    }
}


