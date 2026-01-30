<?php

namespace App\Reports\Kazei;

use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoResult;
use App\Domain\Tax\Factory\SyoriSettingsFactory;
use App\Domain\Tax\Support\PayloadNormalizer;
use App\Domain\Tax\Services\FurusatoDryRunCalculatorRunner;
use App\Domain\Tax\Services\FurusatoPracticalUpperLimitService;
use App\Reports\Contracts\ReportInterface;

class KazeigakuZeigakuYosokuReport implements ReportInterface
{
    public function viewName(): string
    {
        // Blade名は番号付き、URLキーは数字なし（A案）
        return 'pdf/3_kazeigakuzeigakuyosoku';
    }

    public function buildViewData(Data $data): array
    {
        // 既定：上限額まで寄付（従来互換）
        return $this->buildViewDataWithContext($data, ['report_key' => 'kazeigakuzeigakuyosoku']);
    }

    /**
     * Bundle から「今までの寄付額（_curr）」と「上限額（通常）」を切り替えるための拡張
     * - PdfOutputController から report_key が渡される
     *
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

        $ctx = [
            'master_kihu_year' => 2025,
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

        // ------------------------------
        // 3) 実利上限（千円未満切捨て後の最終上限額）を当年ふるさと寄附として注入し dry-run
        // ------------------------------
        /** @var FurusatoPracticalUpperLimitService $upperSvc */
        $upperSvc = app(FurusatoPracticalUpperLimitService::class);
        $upper = $upperSvc->compute($payload, $ctx);
        $yMaxTotal = (int)($upper['y_max_total'] ?? 0); // ← 仕様：千円切捨て後の最終上限額

        /** @var FurusatoDryRunCalculatorRunner $runner */
        $runner = app(FurusatoDryRunCalculatorRunner::class);

        // --------------------------------------------
        // ★出力モード判定
        //   - report_key が *_curr なら「今までに寄付した額」
        //   - それ以外は「上限額まで寄付」
        // --------------------------------------------
        $reportKey = strtolower((string)($context['report_key'] ?? ''));
        $mode = str_ends_with($reportKey, '_curr') ? 'current' : 'max';

        $y = 0;
        if ($mode === 'current') {
            $payloadAt = $this->withFurusatoCurrent($payload);
            $y = $this->resolveCurrentFurusatoDonationCurr($payloadAt);
            $outFull = $runner->run($payloadAt, $ctx);
        } else {
            $y = (int)$yMaxTotal;
            $payloadAtMax = $this->withFurusatoMax($payload, $y);
            $outFull = $runner->run($payloadAtMax, $ctx);
        }

        // other-only（ふるさと＝0、その他寄附はそのまま）…「下記以外」用
        $payloadOther = $this->withFurusatoMax($payload, 0);
        $outOther = $runner->run($payloadOther, $ctx);

        // ------------------------------
        // 4) 帳票3（当年 curr）用に値を整形
        // ------------------------------
        $p = 'curr';
        $I = fn(array $src, string $key): int => $this->n($src[$key] ?? 0);

        // 課税所得金額（SoT: tb_*）
        $taxable = [
            'sogo'     => ['itax' => $I($outFull, "tb_sogo_shotoku_{$p}"),                'jumin' => $I($outFull, "tb_sogo_jumin_{$p}")],
            'tanki'    => ['itax' => $I($outFull, "tb_joto_tanki_shotoku_{$p}"),          'jumin' => $I($outFull, "tb_joto_tanki_jumin_{$p}")],
            'choki'    => ['itax' => $I($outFull, "tb_joto_choki_shotoku_{$p}"),          'jumin' => $I($outFull, "tb_joto_choki_jumin_{$p}")],
            'kabujoto' => ['itax' => $I($outFull, "tb_ippan_kabuteki_joto_shotoku_{$p}") + $I($outFull, "tb_jojo_kabuteki_joto_shotoku_{$p}"),
                           'jumin'=> $I($outFull, "tb_ippan_kabuteki_joto_jumin_{$p}")  + $I($outFull, "tb_jojo_kabuteki_joto_jumin_{$p}")],
            'haito'    => ['itax' => $I($outFull, "tb_jojo_kabuteki_haito_shotoku_{$p}"), 'jumin' => $I($outFull, "tb_jojo_kabuteki_haito_jumin_{$p}")],
            'sakimono' => ['itax' => $I($outFull, "tb_sakimono_shotoku_{$p}"),             'jumin' => $I($outFull, "tb_sakimono_jumin_{$p}")],
            'sanrin'   => ['itax' => $I($outFull, "tb_sanrin_shotoku_{$p}"),               'jumin' => $I($outFull, "tb_sanrin_jumin_{$p}")],
            'taishoku' => ['itax' => $I($outFull, "tb_taishoku_shotoku_{$p}"),             'jumin' => $I($outFull, "tb_taishoku_jumin_{$p}")],
        ];

        // 税額（算出税額：分離含む）…SoT: bunri_zeigaku_*_*_curr
        $itaxZeigaku = [
            'sogo'     => $I($outFull, "bunri_zeigaku_sogo_shotoku_{$p}"),
            'tanki'    => $I($outFull, "bunri_zeigaku_tanki_shotoku_{$p}"),
            'choki'    => $I($outFull, "bunri_zeigaku_choki_shotoku_{$p}"),
            'kabujoto' => $I($outFull, "bunri_zeigaku_joto_shotoku_{$p}"),
            'haito'    => $I($outFull, "bunri_zeigaku_haito_shotoku_{$p}"),
            'sakimono' => $I($outFull, "bunri_zeigaku_sakimono_shotoku_{$p}"),
            'sanrin'   => $I($outFull, "bunri_zeigaku_sanrin_shotoku_{$p}"),
            'taishoku' => $I($outFull, "bunri_zeigaku_taishoku_shotoku_{$p}"),
            'gokei'    => $I($outFull, "bunri_zeigaku_gokei_shotoku_{$p}"),
        ];

        // 住民税の「算出税額」市/県/合計（カテゴリ別）を master の県/市率で再計算して作る
        // ※JuminTaxCalculator と同じ考え方（県・市で別々に floor→合算）
        $shitei = $this->resolveFlag($syoriSettings, $outFull, 'shitei_toshi_flag', $p);
        $rateRows = $this->buildJuminRateRows(2025, $ctx['company_id'] ?? null, $ctx['data_id'] ?? null);

        $juminZeigakuByCategory = $this->buildJuminZeigakuBreakdownCurr($outFull, $rateRows, $shitei);

        // ①調整控除（SoT）
        $choseiPref = $I($outFull, "chosei_kojo_pref_{$p}");
        $choseiMuni = $I($outFull, "chosei_kojo_muni_{$p}");

        // ②配当控除（適用額）
        $haitoItaxApplied = $I($outFull, "tax_haito_applied_shotoku_{$p}");
        $haitoJuminApplied = $I($outFull, "tax_haito_applied_jumin_{$p}");

        // ③住宅（適用額）
        $jutakuItaxApplied = $I($outFull, "tax_jutaku_shotoku_{$p}");
        $jutakuJuminApplied = $I($outFull, "tax_jutaku_jumin_{$p}");

        // ④政党等（所得税）
        $seitotoItax = $I($outFull, "tax_credit_shotoku_total_{$p}");

        // 「①〜④控除後の所得割額」：所得税は tax_sashihiki_shotoku を採用（百円未満切捨てしない）
        $after14Itax = max(0, (int) $I($outFull, "tax_sashihiki_shotoku_{$p}"));

        // 住民税は、TaxGokeiCalculator と同じ share で「配当→住宅」を按分して残額を作る（100円未満切捨て）
        $basePref = $I($outFull, "choseigo_shotokuwari_pref_{$p}");
        $baseMuni = $I($outFull, "choseigo_shotokuwari_muni_{$p}");
        [$sharePref, $shareMuni] = $this->resolveTokureiSharesFromRates($rateRows, $shitei);
        [$haitoPref, $haitoMuni] = $this->splitByShare($haitoJuminApplied, $sharePref, $shareMuni, 1);
        $afterHaitoPref = max(0, $basePref - $haitoPref);
        $afterHaitoMuni = max(0, $baseMuni - $haitoMuni);
        [$jutakuPref, $jutakuMuni] = $this->splitByShare($jutakuJuminApplied, $sharePref, $shareMuni, 1);
        $afterJutakuPref = max(0, $afterHaitoPref - $jutakuPref);
        $afterJutakuMuni = max(0, $afterHaitoMuni - $jutakuMuni);
        $after14JuminTotal = max(0, (int) ($afterJutakuPref + $afterJutakuMuni));
        [$after14JuminPref, $after14JuminMuni] = $this->splitByShare($after14JuminTotal, $sharePref, $shareMuni, 1);

        // 寄附金税額控除（下記以外 / ふるさと）…other-only を1回引いて差分で分解
        $fullPref = $I($outFull, "kifukin_zeigaku_kojo_pref_{$p}");
        $fullMuni = $I($outFull, "kifukin_zeigaku_kojo_muni_{$p}");
        $fullTot  = $I($outFull, "kifukin_zeigaku_kojo_gokei_{$p}");

        $otherPref = $I($outOther, "kifukin_zeigaku_kojo_pref_{$p}");
        $otherMuni = $I($outOther, "kifukin_zeigaku_kojo_muni_{$p}");
        $otherTot  = $I($outOther, "kifukin_zeigaku_kojo_gokei_{$p}");

        $furuPref = max(0, $fullPref - $otherPref);
        $furuMuni = max(0, $fullMuni - $otherMuni);
        $furuTot  = max(0, $fullTot  - $otherTot);

        // 災害減免（入力値＝適用額として扱う。住民税は share で按分）
        $saigaiItax = $I($outFull, "tax_saigai_genmen_shotoku_{$p}");
        $saigaiJumin = $I($outFull, "tax_saigai_genmen_jumin_{$p}");
        [$saigaiPref, $saigaiMuni] = $this->splitByShare($saigaiJumin, $sharePref, $shareMuni, 1);

        // 最終税額（SoT：TaxGokeiCalculator）
        $kijunItax  = $I($outFull, "tax_kijun_shotoku_{$p}");
        $fukkouItax = $I($outFull, "tax_fukkou_shotoku_{$p}"); // 1円単位
        $gokeiItax  = $I($outFull, "tax_gokei_shotoku_{$p}");

        $gokeiJuminPref = $I($outFull, "tax_gokei_jumin_pref_{$p}");
        $gokeiJuminMuni = $I($outFull, "tax_gokei_jumin_muni_{$p}");
        $gokeiJuminTot  = $I($outFull, "tax_gokei_jumin_{$p}");

        return [
            'title'       => '課税所得金額・税額の予測',
            'year'        => $year,
            'wareki_year' => $this->toWarekiYear($year),
            'guest_name'  => $guestName,
            'data_id'     => (int)$data->id,
            // この帳票で使った“寄付額”（maxなら上限、currentなら今まで）
            'furusato_y_max_total' => $y,
            'furusato_pdf_variant' => $mode,
            'report3_curr' => [
                'taxable' => $taxable,
                'zeigaku' => [
                    'itax' => $itaxZeigaku,
                    'jumin' => $juminZeigakuByCategory,
                ],
                'credits' => [
                    'chosei' => ['muni'=>$choseiMuni, 'pref'=>$choseiPref, 'total'=>$choseiMuni + $choseiPref],
                    'haito'  => ['itax'=>$haitoItaxApplied, 'muni'=>$this->splitByShare($haitoJuminApplied, $sharePref, $shareMuni, 100)[1], 'pref'=>$this->splitByShare($haitoJuminApplied, $sharePref, $shareMuni, 100)[0], 'total'=>$haitoJuminApplied],
                    'jutaku' => ['itax'=>$jutakuItaxApplied,'muni'=>$this->splitByShare($jutakuJuminApplied,$sharePref,$shareMuni,100)[1], 'pref'=>$this->splitByShare($jutakuJuminApplied,$sharePref,$shareMuni,100)[0], 'total'=>$jutakuJuminApplied],
                    'seitoto'=> ['itax'=>$seitotoItax],
                    'after14'=> ['itax'=>$after14Itax, 'muni'=>$after14JuminMuni, 'pref'=>$after14JuminPref, 'total'=>$after14JuminTotal],
                    'kifukin_other'=> ['muni'=>$otherMuni, 'pref'=>$otherPref, 'total'=>$otherTot],
                    'kifukin_furu' => ['muni'=>$furuMuni,  'pref'=>$furuPref,  'total'=>$furuTot],
                    'saigai' => ['itax'=>$saigaiItax, 'muni'=>$saigaiMuni, 'pref'=>$saigaiPref, 'total'=>$saigaiJumin],
                ],
                'final_tax' => [
                    'kijun_itax'  => $kijunItax,
                    'fukkou_itax' => $fukkouItax,
                    'gokei_itax'  => $gokeiItax,
                    'gokei_jumin' => ['muni'=>$gokeiJuminMuni, 'pref'=>$gokeiJuminPref, 'total'=>$gokeiJuminTot],
                ],
            ],
        ];
    }

    public function fileName(Data $data): string
    {
        $year = (int)($data->kihu_year ?? now()->year);
        return "課税所得金額_税額予測_{$year}_data{$data->id}.pdf";
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
     * 上限額（max）用：所得税側/住民税側とも「同額」で注入する
     */
    private function withFurusatoMax(array $payload, int $y): array
    {
        $y = max(0, $y);
        $payload['shotokuzei_shotokukojo_furusato_curr'] = $y;
        $payload['juminzei_zeigakukojo_pref_furusato_curr'] = $y;
        $payload['juminzei_zeigakukojo_muni_furusato_curr'] = $y;
        return $payload;
    }

    /**
     * 今まで（current）用：
     * - 住民税側 pref/muni は「どちらか入っていれば同額コピー」で同期
     * - 所得税側は現状入力を尊重（ワンストップを壊さない）
     */
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

    /**
     * 「今までに寄付した額（当年）」を安全に解決
     */
    private function resolveCurrentFurusatoDonationCurr(array $payload): int
    {
        $itax = $this->n($payload['shotokuzei_shotokukojo_furusato_curr'] ?? 0);
        $pref = $this->n($payload['juminzei_zeigakukojo_pref_furusato_curr'] ?? 0);
        $muni = $this->n($payload['juminzei_zeigakukojo_muni_furusato_curr'] ?? 0);
        return max(0, max($itax, $pref, $muni));
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',', ' '], '', $v);
        return is_numeric($v) ? (int) floor((float) $v) : 0;
    }

    private function floorToStep(int $v, int $step): int
    {
        if ($v <= 0) return 0;
        if ($step <= 0) return $v;
        return (int) (floor($v / $step) * $step);
    }

    private function toWarekiYear(int $year): string
    {
        if ($year >= 2019) return sprintf('令和%d年', $year - 2018);
        if ($year >= 1989) return sprintf('平成%d年', $year - 1988);
        if ($year >= 1926) return sprintf('昭和%d年', $year - 1925);
        return (string) $year;
    }

    /**
     * 特例控除（pref/muni）の按分比を jumin_master から取得（無ければ 0.4/0.6）
     * @return array{0:float,1:float} [prefShare, muniShare]
     */
    private function resolveTokureiSharesFromRates(array $rateRows, bool $shitei): array
    {
        foreach ($rateRows as $r) {
            if ((string)($r['category'] ?? '') !== '特例控除') continue;
            if ($shitei) {
                return [(float)($r['pref_specified'] ?? 0.2), (float)($r['city_specified'] ?? 0.8)];
            }
            return [(float)($r['pref_non_specified'] ?? 0.4), (float)($r['city_non_specified'] ?? 0.6)];
        }
        return [0.4, 0.6];
    }

    /**
     * 合計額を pref/muni に按分（prefはstep切捨て、muni残り寄せ）
     * @return array{0:int,1:int} [pref, muni]
     */
    private function splitByShare(int $total, float $prefShare, float $muniShare, int $step = 1): array
    {
        $t = max(0, $total);
        if ($t === 0) return [0, 0];
        $pref = (int) floor($t * $prefShare);
        if ($step > 1) {
            $pref = $this->floorToStep($pref, $step);
        }
        $muni = $t - $pref;
        return [max(0, $pref), max(0, $muni)];
    }

    private function resolveFlag(array $settings, array $payload, string $baseKey, string $period): bool
    {
        $keys = [sprintf('%s_%s', $baseKey, $period), $baseKey];
        foreach ($keys as $k) {
            if (array_key_exists($k, $settings)) return $this->n($settings[$k]) === 1;
        }
        foreach ($keys as $k) {
            if (array_key_exists($k, $payload)) return $this->n($payload[$k]) === 1;
        }
        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildJuminRateRows(int $year, mixed $companyId, mixed $dataId): array
    {
        /** @var \App\Domain\Tax\Contracts\MasterProviderContract $master */
        $master = app(\App\Domain\Tax\Contracts\MasterProviderContract::class);
        $cid = $companyId !== null ? (int)$companyId : null;
        $did = $dataId !== null ? (int)$dataId : null;
        $collection = $master->getJuminRates($year, $cid, $did);
        $rows = [];
        foreach ($collection as $row) {
            $rows[] = [
                'category' => isset($row->category) ? (string)$row->category : '',
                'sub_category' => isset($row->sub_category) && $row->sub_category !== '' ? (string)$row->sub_category : null,
                'remark' => isset($row->remark) && $row->remark !== '' ? (string)$row->remark : null,
                'pref_specified' => isset($row->pref_specified) ? (float)$row->pref_specified : 0.0,
                'pref_non_specified' => isset($row->pref_non_specified) ? (float)$row->pref_non_specified : 0.0,
                'city_specified' => isset($row->city_specified) ? (float)$row->city_specified : 0.0,
                'city_non_specified' => isset($row->city_non_specified) ? (float)$row->city_non_specified : 0.0,
            ];
        }
        return $rows;
    }

    /**
     * 帳票3用：住民税（算出税額）をカテゴリ別に [muni,pref,total] で返す（curr）
     * @return array<string, array{muni:int,pref:int,total:int}>
     */
    private function buildJuminZeigakuBreakdownCurr(array $out, array $rateRows, bool $shitei): array
    {
        // 総合課税：tb_sogo_jumin × 総合課税率（県/市を別々にfloorして合算）
        $prefRateSogo = $this->juminRate($rateRows, '総合課税', null, $shitei, 'pref');
        $muniRateSogo = $this->juminRate($rateRows, '総合課税', null, $shitei, 'city');
        $sogoBase = max(0, $this->n($out['tb_sogo_jumin_curr'] ?? 0));
        $sogoPref = (int) floor($sogoBase * $prefRateSogo);
        $sogoMuni = (int) floor($sogoBase * $muniRateSogo);

        // 短期（一般/軽減）
        $taxByMaster = function (int $amount, string $category, ?string $sub = null, ?string $remarkContains = null) use ($rateRows, $shitei): array {
            $a = max(0, $amount);
            if ($a === 0) return [0, 0];
            $pr = $this->juminRate($rateRows, $category, $sub, $shitei, 'pref', $remarkContains);
            $mr = $this->juminRate($rateRows, $category, $sub, $shitei, 'city', $remarkContains);
            return [(int) floor($a * $pr), (int) floor($a * $mr)];
        };

        $tIppan  = $this->n($out['bunri_shotoku_tanki_ippan_jumin_curr'] ?? 0);
        $tKeigen = $this->n($out['bunri_shotoku_tanki_keigen_jumin_curr'] ?? 0);
        [$tp1,$tm1] = $taxByMaster($tIppan,  '短期譲渡', '一般');
        [$tp2,$tm2] = $taxByMaster($tKeigen, '短期譲渡', '軽減');
        $tankiPref = $tp1 + $tp2;
        $tankiMuni = $tm1 + $tm2;

        // 長期（一般/特定/軽課：以下/超）
        $cIppan   = $this->n($out['bunri_shotoku_choki_ippan_jumin_curr'] ?? 0);
        $cTokutei = $this->n($out['bunri_shotoku_choki_tokutei_jumin_curr'] ?? 0);
        $cKeika   = $this->n($out['bunri_shotoku_choki_keika_jumin_curr'] ?? 0);
        [$cpI,$cmI] = $taxByMaster($cIppan, '長期譲渡', '一般');
        $tokLow  = min(20_000_000, max(0, $cTokutei));
        $tokHigh = max(0, $cTokutei - 20_000_000);
        [$cpTL,$cmTL] = $taxByMaster($tokLow,  '長期譲渡', '特定', '以下');
        [$cpTH,$cmTH] = $taxByMaster($tokHigh, '長期譲渡', '特定', '超');
        $keiLow  = min(60_000_000, max(0, $cKeika));
        $keiHigh = max(0, $cKeika - 60_000_000);
        [$cpKL,$cmKL] = $taxByMaster($keiLow,  '長期譲渡', '軽課', '以下');
        [$cpKH,$cmKH] = $taxByMaster($keiHigh, '長期譲渡', '軽課', '超');
        $chokiPref = $cpI + $cpTL + $cpTH + $cpKL + $cpKH;
        $chokiMuni = $cmI + $cmTL + $cmTH + $cmKL + $cmKH;

        // 株式等譲渡（一般/上場）
        $tbIppan = $this->n($out['tb_ippan_kabuteki_joto_jumin_curr'] ?? 0);
        $tbJojo  = $this->n($out['tb_jojo_kabuteki_joto_jumin_curr'] ?? 0);
        [$jp1,$jm1] = $taxByMaster($tbIppan, '一般株式等の譲渡', null);
        [$jp2,$jm2] = $taxByMaster($tbJojo,  '上場株式等の譲渡', null);
        $jotoPref = $jp1 + $jp2;
        $jotoMuni = $jm1 + $jm2;

        // 上場配当
        $haitoBase = $this->n($out['tb_jojo_kabuteki_haito_jumin_curr'] ?? 0);
        [$hp,$hm] = $taxByMaster($haitoBase, '上場株式等の配当等', null);

        // 先物
        $sakiBase = $this->n($out['tb_sakimono_jumin_curr'] ?? 0);
        [$sp,$sm] = $taxByMaster($sakiBase, '先物取引', null);

        // 山林・退職
        $sanBase = $this->n($out['tb_sanrin_jumin_curr'] ?? 0);
        [$sap,$sam] = $taxByMaster($sanBase, '山林', null);
        $taiBase = $this->n($out['tb_taishoku_jumin_curr'] ?? 0);
        [$tap,$tam] = $taxByMaster($taiBase, '退職', null);

        return [
            'sogo'     => ['pref'=>$sogoPref,  'muni'=>$sogoMuni,  'total'=>$sogoPref + $sogoMuni],
            'tanki'    => ['pref'=>$tankiPref, 'muni'=>$tankiMuni, 'total'=>$tankiPref + $tankiMuni],
            'choki'    => ['pref'=>$chokiPref, 'muni'=>$chokiMuni, 'total'=>$chokiPref + $chokiMuni],
            'kabujoto' => ['pref'=>$jotoPref,  'muni'=>$jotoMuni,  'total'=>$jotoPref + $jotoMuni],
            'haito'    => ['pref'=>$hp,        'muni'=>$hm,        'total'=>$hp + $hm],
            'sakimono' => ['pref'=>$sp,        'muni'=>$sm,        'total'=>$sp + $sm],
            'sanrin'   => ['pref'=>$sap,       'muni'=>$sam,       'total'=>$sap + $sam],
            'taishoku' => ['pref'=>$tap,       'muni'=>$tam,       'total'=>$tap + $tam],
            'gokei'    => [
                'pref'  => $sogoPref + $tankiPref + $chokiPref + $jotoPref + $hp + $sp + $sap + $tap,
                'muni'  => $sogoMuni + $tankiMuni + $chokiMuni + $jotoMuni + $hm + $sm + $sam + $tam,
                'total' => ($sogoPref + $tankiPref + $chokiPref + $jotoPref + $hp + $sp + $sap + $tap)
                         + ($sogoMuni + $tankiMuni + $chokiMuni + $jotoMuni + $hm + $sm + $sam + $tam),
            ],
        ];
    }

    private function juminRate(
        array $rates,
        string $category,
        ?string $subCategory,
        bool $shitei,
        string $target,
        ?string $remarkContains = null
    ): float {
        $categoryAlts = $category === '総合課税' ? ['総合課税', '総合'] : [$category];
        foreach ($rates as $rate) {
            $rateCategory = (string)($rate['category'] ?? '');
            if (!in_array($rateCategory, $categoryAlts, true)) continue;
            $sub = $rate['sub_category'] ?? null;
            if ($sub !== $subCategory) continue;
            if ($remarkContains !== null) {
                $remark = (string)($rate['remark'] ?? '');
                if ($remark === '' || !str_contains($remark, $remarkContains)) continue;
            }
            $value = $shitei
                ? ($target === 'pref' ? ($rate['pref_specified'] ?? 0.0) : ($rate['city_specified'] ?? 0.0))
                : ($target === 'pref' ? ($rate['pref_non_specified'] ?? 0.0) : ($rate['city_non_specified'] ?? 0.0));
            $numeric = (float)$value;
            // 総合課税など通常税率は 〇％ → 率(0.xx)
            return $numeric / 100.0;
        }
        return 0.0;
    }
}


