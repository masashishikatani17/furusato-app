<?php

namespace App\Reports\Jumin;

use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoResult;
use App\Domain\Tax\Factory\SyoriSettingsFactory;
use App\Domain\Tax\Support\PayloadNormalizer;
use App\Domain\Tax\Services\FurusatoDryRunCalculatorRunner;
use App\Domain\Tax\Services\FurusatoPracticalUpperLimitService;
use App\Domain\Tax\Contracts\MasterProviderContract;
use App\Reports\Contracts\ReportInterface;

class JuminKeigengakuReport implements ReportInterface
{
    public function viewName(): string
    {
        // Blade名は番号付き、URLキーは数字なし（A案）
        return 'pdf/4_juminkeigengaku';
    }

    public function buildViewData(Data $data): array
    {
        // 既定：上限額まで寄付（従来互換）
        return $this->buildViewDataWithContext($data, ['report_key' => 'juminkeigengaku']);
    }

    /**
     * @param array<string,mixed> $context
     */
    public function buildViewDataWithContext(Data $data, array $context): array
    {
        $guestName = $data->guest?->name ?? '（名称未登録）';
        $year = (int)($data->kihu_year ?? now()->year);

        // ------------------------------
        // 1) SoT payload（優先：FurusatoResult.payload['payload'] → 次点：FurusatoInput.payload）
        // ------------------------------
        $payload = [];
        $storedResults = FurusatoResult::query()
            ->where('data_id', (int)$data->id)
            ->value('payload');
        if (is_array($storedResults)) {
            $candidate = $storedResults['payload'] ?? $storedResults['upper'] ?? null;
            if (is_array($candidate)) {
                $payload = $candidate;
            }
        }
        if ($payload === []) {
            $storedInput = FurusatoInput::query()
                ->where('data_id', (int)$data->id)
                ->value('payload');
            if (is_array($storedInput)) {
                $payload = $storedInput;
            }
        }

        /** @var PayloadNormalizer $normalizer */
        $normalizer = app(PayloadNormalizer::class);
        $payload = $normalizer->normalize($payload);

        // ------------------------------
        // 2) ctx（syori_settings + master year + company/data）
        // ------------------------------
        /** @var SyoriSettingsFactory $syoriFactory */
        $syoriFactory = app(SyoriSettingsFactory::class);
        $syoriSettings = $syoriFactory->buildInitial($data);

        $masterYear = $year; // 例：令和7年=2025
        $ctx = [
            'master_kihu_year' => $masterYear,
            'kihu_year'        => $year,
            'company_id'       => $data->company_id !== null ? (int)$data->company_id : null,
            'data_id'          => $data->id !== null ? (int)$data->id : null,
            'syori_settings'   => $syoriSettings,
            // dry-run calculators が参照する可能性があるため、Recalculate と同様に渡す（保険）
            'data'             => $data,
            'guest_birth_date' => ($data->guest?->birth_date instanceof \DateTimeInterface)
                ? $data->guest->birth_date->format('Y-m-d')
                : (is_string($data->guest?->birth_date) ? $data->guest->birth_date : null),
            'taxpayer_sex'     => data_get($data, 'guest.sex')
                ?? data_get($data, 'guest.gender')
                ?? data_get($data, 'guest.sex_code'),
        ];

        /** @var FurusatoDryRunCalculatorRunner $runner */
        $runner = app(FurusatoDryRunCalculatorRunner::class);

        // ------------------------------
        // 3) 実利上限（最終=千円未満切捨て済み）を取得し、比較2ケースで計算
        //   A：ふるさと=0（他寄附は現状のまま）
        //   B：ふるさと=上限（他寄附は現状のまま）
        // ------------------------------
        $yMax = 0;
        try {
            /** @var FurusatoPracticalUpperLimitService $upperSvc */
            $upperSvc = app(FurusatoPracticalUpperLimitService::class);
            $upper = $upperSvc->compute($payload, $ctx);
            $yMax = (int)($upper['y_max_total'] ?? 0); // 最終上限（千円未満切捨て済み）
        } catch (\Throwable $e) {
            $yMax = 0;
        }

        $reportKey = strtolower((string)($context['report_key'] ?? ''));
        $mode = str_ends_with($reportKey, '_curr') ? 'current' : 'max';

        $payloadNoFuru = $this->withFurusatoMax($payload, 0);
        if ($mode === 'current') {
            $payloadAt = $this->withFurusatoCurrent($payload);
            $y = $this->resolveCurrentFurusatoDonationCurr($payloadAt);
            $payloadAtMax = $payloadAt;
            $yMax = $y;
        } else {
            $payloadAtMax  = $this->withFurusatoMax($payload, $yMax);
        }

        $outNoFuru = $runner->run($payloadNoFuru, $ctx);
        $outAtMax  = $runner->run($payloadAtMax,  $ctx);

        // ------------------------------
        // 4) 所得税・住民税の軽減額（税額差）
        // ------------------------------
        $itaxNoFuru = $this->n($outNoFuru['tax_gokei_shotoku_curr'] ?? 0);
        $itaxAtMax  = $this->n($outAtMax['tax_gokei_shotoku_curr'] ?? 0);
        $itaxSaved  = max(0, $itaxNoFuru - $itaxAtMax);

        $juminNoFuru = $this->n($outNoFuru['tax_gokei_jumin_curr'] ?? 0);
        $juminAtMax  = $this->n($outAtMax['tax_gokei_jumin_curr'] ?? 0);
        $juminSaved  = max(0, $juminNoFuru - $juminAtMax);

        $savedTotal = $itaxSaved + $juminSaved;
        $burden = max(0, $yMax - $savedTotal);

        // ------------------------------
        // 5) 住民税の計算過程（①〜⑭）を組み立て（currのみ）
        // ------------------------------
        $p = 'curr';

        // ①〜③（JuminTaxCalculator のSoT）
        $maeMuni = $this->n($outAtMax["chosei_mae_shotokuwari_muni_{$p}"] ?? 0);
        $maePref = $this->n($outAtMax["chosei_mae_shotokuwari_pref_{$p}"] ?? 0);
        $kojoMuni = $this->n($outAtMax["chosei_kojo_muni_{$p}"] ?? 0);
        $kojoPref = $this->n($outAtMax["chosei_kojo_pref_{$p}"] ?? 0);
        $goMuni  = $this->n($outAtMax["choseigo_shotokuwari_muni_{$p}"] ?? 0);
        $goPref  = $this->n($outAtMax["choseigo_shotokuwari_pref_{$p}"] ?? 0);

        // ④〜⑧（基本控除）…JuminzeiKifukinCalculator と同じ式で“表示用に再計算”
        $sumPref = $this->sumJuminDonationSide($payloadAtMax, 'pref', $p);
        $sumMuni = $this->sumJuminDonationSide($payloadAtMax, 'muni', $p);
        $kifuTotal = max(0, $this->n($outAtMax["kifu_gaku_{$p}"] ?? 0)); // 参考（表示は 1ページ合計）

        $mother = max(0, $this->n($outAtMax["sum_for_sogoshotoku_etc_{$p}"] ?? 0));
        $cap30  = (int) floor($mother * 0.3);

        $eligiblePref = 0;
        if ($sumPref > 2_000) {
            $eligiblePref = max(min($sumPref, $cap30) - 2_000, 0);
        }
        $eligibleMuni = 0;
        if ($sumMuni > 2_000) {
            $eligibleMuni = max(min($sumMuni, $cap30) - 2_000, 0);
        }

        // 基本控除率（jumin_master: 基本控除）
        [$basicPrefRate, $basicMuniRate, $basicPrefPct, $basicMuniPct] = $this->resolveKihonRates($masterYear, $ctx, $syoriSettings);

        $minPref = min($eligiblePref, $cap30);
        $minMuni = min($eligibleMuni, $cap30);

        $kihonPref = (int) ceil(max($minPref, 0) * $basicPrefRate);
        $kihonMuni = (int) ceil(max($minMuni, 0) * $basicMuniRate);

        // ⑨〜⑬（特例控除）…SoTを使いつつ、⑪だけ小数2位表示用に再計算
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

        // ⑭（合計=天井後SoT）
        $kifukinPref = $this->n($outAtMax["kifukin_zeigaku_kojo_pref_{$p}"] ?? 0);
        $kifukinMuni = $this->n($outAtMax["kifukin_zeigaku_kojo_muni_{$p}"] ?? 0);
        $kifukinGokei = $this->n($outAtMax["kifukin_zeigaku_kojo_gokei_{$p}"] ?? ($kifukinPref + $kifukinMuni));

        // 下段（ふるさと以外 / ふるさとだけ / 控除不能 / 最終）
        $otherPref = $this->n($outNoFuru["kifukin_zeigaku_kojo_pref_{$p}"] ?? 0);
        $otherMuni = $this->n($outNoFuru["kifukin_zeigaku_kojo_muni_{$p}"] ?? 0);
        $otherGokei = $this->n($outNoFuru["kifukin_zeigaku_kojo_gokei_{$p}"] ?? ($otherPref + $otherMuni));

        $furuOnlyPref = max(0, $kifukinPref - $otherPref);
        $furuOnlyMuni = max(0, $kifukinMuni - $otherMuni);
        $furuOnlyGokei = max(0, $kifukinGokei - $otherGokei);

        // 控除不能分（= 算定合計 - 天井後）を“pref/muni別”に近似（表示用）
        $partsPref = max(0, $kihonPref) + max(0, $tokureiJogenPref);
        $partsMuni = max(0, $kihonMuni) + max(0, $tokureiJogenMuni);
        $unablePref = max(0, $partsPref - $kifukinPref);
        $unableMuni = max(0, $partsMuni - $kifukinMuni);
        $unableGokei = $unablePref + $unableMuni;

        return [
            'title'      => '住民税の軽減額',
            'year'       => $year,
            'wareki_year'=> $this->toWarekiYear($year),
            'guest_name' => $guestName,
            'data_id'    => (int)$data->id,

            // 上限寄附
            'furusato_y_max_total' => (int)$yMax,

            // 所得税の軽減額
            'itax_no_furusato' => $itaxNoFuru,
            'itax_at_max'      => $itaxAtMax,
            'itax_saved'       => $itaxSaved,

            // 寄附金額と減税額の比較
            'donation_amount'  => (int)$yMax,
            'tax_saved_total'  => (int)$savedTotal,
            'burden_amount'    => (int)$burden,

            // 住民税（①〜⑭）
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
                    // ⑪（小数2位まで表示）
                    'calc11' => [
                        'muni'  => number_format($tokurei11Muni, 2, '.', ''),
                        'pref'  => number_format($tokurei11Pref, 2, '.', ''),
                        'total' => number_format($tokurei11Total, 2, '.', ''),
                    ],
                    'cap20' => ['muni'=>$cap20Muni, 'pref'=>$cap20Pref, 'total'=>$cap20Muni+$cap20Pref],
                    'jogen' => ['muni'=>$tokureiJogenMuni, 'pref'=>$tokureiJogenPref, 'total'=>$tokureiJogenMuni+$tokureiJogenPref],
                ],

                'kifukin_total' => ['muni'=>$kifukinMuni, 'pref'=>$kifukinPref, 'total'=>$kifukinGokei],
            ],

            // 下段まとめ
            'jumin_summary' => [
                'other' => ['muni'=>$otherMuni, 'pref'=>$otherPref, 'total'=>$otherGokei],
                'furusato_only' => ['muni'=>$furuOnlyMuni, 'pref'=>$furuOnlyPref, 'total'=>$furuOnlyGokei],
                'unable' => ['muni'=>$unableMuni, 'pref'=>$unablePref, 'total'=>$unableGokei],
                'final'  => ['muni'=>$kifukinMuni, 'pref'=>$kifukinPref, 'total'=>$kifukinGokei],
            ],
        ];
    }

    public function fileName(Data $data): string
    {
        $year = (int)($data->kihu_year ?? now()->year);
        return "住民税の軽減額_{$year}_data{$data->id}.pdf";
    }

    // 全帳票：6_jintekikojosatyosei と同じ（A4横）
    public function pdfOptions(Data $data): array
    {
        return [
            'paper'  => 'a4',
            'orient' => 'landscape',
        ];
    }

    // ------------------------------
    // helpers
    // ------------------------------
    private function withFurusatoMax(array $payload, int $y): array
    {
        $y = max(0, $y);
        $payload['shotokuzei_shotokukojo_furusato_curr'] = $y;
        $payload['juminzei_zeigakukojo_pref_furusato_curr'] = $y;
        $payload['juminzei_zeigakukojo_muni_furusato_curr'] = $y;
        return $payload;
    }

    private function withFurusatoCurrent(array $payload): array
    {
        $itax = $this->n($payload['shotokuzei_shotokukojo_furusato_curr'] ?? 0);
        $pref = $this->n($payload['juminzei_zeigakukojo_pref_furusato_curr'] ?? 0);
        $muni = $this->n($payload['juminzei_zeigakukojo_muni_furusato_curr'] ?? 0);
        $j = max(0, max($pref, $muni));
        if ($j > 0) {
            $payload['juminzei_zeigakukojo_pref_furusato_curr'] = $j;
            $payload['juminzei_zeigakukojo_muni_furusato_curr'] = $j;
        }
        $payload['shotokuzei_shotokukojo_furusato_curr'] = max(0, $itax);
        return $payload;
    }

    private function resolveCurrentFurusatoDonationCurr(array $payload): int
    {
        $itax = $this->n($payload['shotokuzei_shotokukojo_furusato_curr'] ?? 0);
        $pref = $this->n($payload['juminzei_zeigakukojo_pref_furusato_curr'] ?? 0);
        $muni = $this->n($payload['juminzei_zeigakukojo_muni_furusato_curr'] ?? 0);
        return max(0, max($itax, $pref, $muni));
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

    /**
     * 基本控除率（県/市）を master（jumin_rates: category=基本控除）から取得し、
     * 0.xx の比率 と、表示用の％（例：4,6）を返す。
     *
     * @return array{0:float,1:float,2:int,3:int} [prefRate, muniRate, prefPct, muniPct]
     */
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
            // sub_category は null の想定
            $prefPct = (int)($shitei ? ($r['pref_specified'] ?? 0) : ($r['pref_non_specified'] ?? 0));
            $muniPct = (int)($shitei ? ($r['city_specified'] ?? 0) : ($r['city_non_specified'] ?? 0));
            break;
        }
        $prefRate = max(0.0, ((float)$prefPct) / 100.0);
        $muniRate = max(0.0, ((float)$muniPct) / 100.0);
        return [$prefRate, $muniRate, $prefPct, $muniPct];
    }

    /**
     * 特例控除の按分（pref/muni）を master（category=特例控除）から取得
     *
     * @return array{0:float,1:float} [prefShare, muniShare]
     */
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
            // 念のため 0〜1 に丸め
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
}


