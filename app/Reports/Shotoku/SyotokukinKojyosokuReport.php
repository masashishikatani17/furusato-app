<?php

namespace App\Reports\Shotoku;

use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoResult;
use App\Domain\Tax\Factory\SyoriSettingsFactory;
use App\Domain\Tax\Support\PayloadNormalizer;
use App\Domain\Tax\Services\FurusatoDryRunCalculatorRunner;
use App\Domain\Tax\Services\FurusatoPracticalUpperLimitService;
use App\Reports\Contracts\ReportInterface;

class SyotokukinKojyosokuReport implements ReportInterface
{
    public function viewName(): string
    {
        // Blade名は番号付き、URLキーは数字なし（A案）
        return 'pdf/2_syotokukinkojyosoku';
    }

    public function buildViewData(Data $data): array
    {
        // 既定：上限額まで寄付（従来互換）
        return $this->buildViewDataWithContext($data, ['report_key' => 'syotokukinkojyosoku']);
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
        $payload = $this->forceJuminTaishokuZero($payload);

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
        // 3) 実利上限（自己負担<=2,000円）を当年ふるさと寄附として注入（※帳票は千円未満切捨て）
        // ------------------------------
        // ★重要：
        //   - 計算（控除額）は「実利上限の生値（1円単位）」で行う
        //   - 帳票の“表示”だけ 1,000円未満切捨てを使う（表示と計算を混ぜない）
        $yMaxTotalRaw = 0;
        $yMaxTotalDisplay = 0;

        /** @var FurusatoDryRunCalculatorRunner $runner */
        $runner = app(FurusatoDryRunCalculatorRunner::class);
        $out = $payload; // 失敗時でも後続が壊れないように初期化
        $bunriSpotcheckKeys = [
            'bunri_shotoku_tanki_ippan_shotoku_curr',
            'bunri_shotoku_tanki_ippan_jumin_curr',
            'bunri_shotoku_tanki_keigen_shotoku_curr',
            'bunri_shotoku_tanki_keigen_jumin_curr',
            'bunri_shotoku_choki_ippan_shotoku_curr',
            'bunri_shotoku_choki_ippan_jumin_curr',
            'bunri_shotoku_choki_tokutei_shotoku_curr',
            'bunri_shotoku_choki_tokutei_jumin_curr',
            'bunri_shotoku_choki_keika_shotoku_curr',
            'bunri_shotoku_choki_keika_jumin_curr',
            'bunri_shotoku_ippan_kabuteki_joto_shotoku_curr',
            'bunri_shotoku_ippan_kabuteki_joto_jumin_curr',
            'bunri_shotoku_jojo_kabuteki_joto_shotoku_curr',
            'bunri_shotoku_jojo_kabuteki_joto_jumin_curr',
            'bunri_shotoku_jojo_kabuteki_haito_shotoku_curr',
            'bunri_shotoku_jojo_kabuteki_haito_jumin_curr',
            'bunri_shotoku_sanrin_shotoku_curr',
            'bunri_shotoku_sanrin_jumin_curr',
            'shotoku_after_kurikoshi_ippan_joto_curr',
            'shotoku_after_kurikoshi_jojo_joto_curr',
            'shotoku_after_kurikoshi_jojo_haito_curr',
            'shotoku_sanrin_curr',
            'joto_shotoku_tanki_ippan_curr',
            'joto_shotoku_tanki_keigen_curr',
            'joto_shotoku_choki_ippan_curr',
            'joto_shotoku_choki_tokutei_curr',
            'joto_shotoku_choki_keika_curr',
        ];
        $collectSpotcheck = static function (array $src, array $keys): array {
            $values = [];
            $exists = [];
            foreach ($keys as $key) {
                $exists[$key] = array_key_exists($key, $src);
                $values[$key] = $exists[$key] ? $src[$key] : null;
            }
            return [
                'values' => $values,
                'exists' => $exists,
            ];
        };

        // --------------------------------------------
        // ★出力モード判定
        //   - report_key が *_curr なら「今までに寄付した額」
        //   - それ以外は「上限額まで寄付」
        // --------------------------------------------
        $reportKey = strtolower((string)($context['report_key'] ?? ''));
        $mode = str_ends_with($reportKey, '_curr') ? 'current' : 'max';

        try {
            if ($mode === 'current') {
                // ▼ 今までに寄付した額（当年）：
                //   - 所得税側が 0 でも、住民税側（pref/muni）に入力があれば“その額”で計算・表示したい
                //   - ただし current では「所得税側まで勝手に同額コピーしない」（ワンストップを壊すため）
                $payloadAt = $this->withFurusatoCurrent($payload);
                $payloadAt = $this->forceJuminTaishokuZero($payloadAt);
                $yCurrent = $this->resolveCurrentFurusatoDonationCurr($payloadAt);
                $yMaxTotalRaw = $yCurrent;
                $yMaxTotalDisplay = $yCurrent;
                $out = $runner->run($payloadAt, $ctx);
                $out = $this->forceJuminTaishokuZero($out);
                \Log::debug('syotokukin.report.bunri_left_table_spotcheck', [
                    'data_id' => (int) $data->id,
                    'branch' => 'current',
                    'mode' => $mode,
                    'payloadAt' => $collectSpotcheck($payloadAt, $bunriSpotcheckKeys),
                    'out' => $collectSpotcheck($out, $bunriSpotcheckKeys),
                ]);
            } else {
                /** @var FurusatoPracticalUpperLimitService $upperSvc */
                $upperSvc = app(FurusatoPracticalUpperLimitService::class);
                $upper = $upperSvc->compute($payload, $ctx);
                $yMaxTotalRaw = (int)($upper['y_max_total'] ?? 0);
                $yMaxTotalDisplay = $this->floorToThousands($yMaxTotalRaw);

                // ▼ 計算は「生値」で注入（控除額を正確にする）
                $payloadAtMax = $this->withFurusatoMax($payload, $yMaxTotalRaw);
                $payloadAtMax = $this->forceJuminTaishokuZero($payloadAtMax);
                $out = $runner->run($payloadAtMax, $ctx);
                $out = $this->forceJuminTaishokuZero($out);
                \Log::debug('syotokukin.report.bunri_left_table_spotcheck', [
                    'data_id' => (int) $data->id,
                    'branch' => 'max',
                    'mode' => $mode,
                    'payloadAtMax' => $collectSpotcheck($payloadAtMax, $bunriSpotcheckKeys),
                    'out' => $collectSpotcheck($out, $bunriSpotcheckKeys),
                ]);
            }
        } catch (\Throwable $e) {
            // 帳票生成は落とさない（0扱いで続行）
            $yMaxTotalRaw = 0;
            $yMaxTotalDisplay = 0;
            $safePayload = $this->forceJuminTaishokuZero($payload);
            $out = $runner->run($safePayload, $ctx);
            $out = $this->forceJuminTaishokuZero($out);
            \Log::debug('syotokukin.report.bunri_left_table_spotcheck', [
                'data_id' => (int) $data->id,
                'branch' => 'catch',
                'mode' => $mode,
                'error' => $e->getMessage(),
                'out' => $collectSpotcheck($out, $bunriSpotcheckKeys),
            ]);
        }
        \Log::debug('syotokukin.report.taishoku_jumin_spotcheck', [
            'data_id' => (int) $data->id,
            'mode' => $mode,
            'payload_before_run' => $collectSpotcheck($payload, [
                'bunri_shotoku_taishoku_jumin_curr',
                'tb_taishoku_jumin_curr',
            ]),
            'out_after_run' => $collectSpotcheck($out, [
                'bunri_shotoku_taishoku_jumin_curr',
                'tb_taishoku_jumin_curr',
            ]),
        ]);
        $out = $this->bridgeBunriLeftTableKeys($out);
        \Log::debug('syotokukin.report.bunri_left_table_spotcheck_after_bridge', [
            'data_id' => (int) $data->id,
            'mode' => $mode,
            'out' => $collectSpotcheck($out, $bunriSpotcheckKeys),
        ]);
        // ------------------------------
        // 4) 帳票（2ページ）用：所得金額等（当年）と所得控除額（当年）を組み立て
        // ------------------------------
        $p = 'curr';

        $get = function (array $src, string $key) {
            return $src[$key] ?? 0;
        };
        $I = function (array $src, string $key) {
            return $this->n($src[$key] ?? 0);
        };

        // 所得金額等（左表）
        $sogo = [
            'jigyo_eigyo' => ['itax' => $I($out, "shotoku_jigyo_eigyo_shotoku_{$p}"), 'rtax' => $I($out, "shotoku_jigyo_eigyo_jumin_{$p}")],
            'jigyo_nogyo' => ['itax' => $I($out, "shotoku_jigyo_nogyo_shotoku_{$p}"), 'rtax' => $I($out, "shotoku_jigyo_nogyo_jumin_{$p}")],
            'fudosan'     => ['itax' => $I($out, "shotoku_fudosan_shotoku_{$p}"),     'rtax' => $I($out, "shotoku_fudosan_jumin_{$p}")],
            'rishi'       => ['itax' => $I($out, "shotoku_rishi_shotoku_{$p}"),       'rtax' => $I($out, "shotoku_rishi_jumin_{$p}")],
            'haito'       => ['itax' => $I($out, "shotoku_haito_shotoku_{$p}"),       'rtax' => $I($out, "shotoku_haito_jumin_{$p}")],
            'kyuyo'       => ['itax' => $I($out, "shotoku_kyuyo_shotoku_{$p}"),       'rtax' => $I($out, "shotoku_kyuyo_jumin_{$p}")],
            'zatsu_nenkin'=> ['itax' => $I($out, "shotoku_zatsu_nenkin_shotoku_{$p}"),'rtax' => $I($out, "shotoku_zatsu_nenkin_jumin_{$p}")],
            'zatsu_gyomu' => ['itax' => $I($out, "shotoku_zatsu_gyomu_shotoku_{$p}"), 'rtax' => $I($out, "shotoku_zatsu_gyomu_jumin_{$p}")],
            'zatsu_sonota'=> ['itax' => $I($out, "shotoku_zatsu_sonota_shotoku_{$p}"),'rtax' => $I($out, "shotoku_zatsu_sonota_jumin_{$p}")],
            // 総合譲渡・一時（合算済みキーを優先。無い場合は内訳から合算）
            'joto_ichiji' => [
                'itax' => $I($out, "shotoku_joto_ichiji_shotoku_{$p}") ?: (
                    $I($out, "shotoku_joto_tanki_sogo_{$p}") + $I($out, "shotoku_joto_choki_sogo_{$p}") + max(0, $I($out, "shotoku_ichiji_{$p}"))
                ),
                'rtax' => $I($out, "shotoku_joto_ichiji_jumin_{$p}") ?: (
                    $I($out, "shotoku_joto_tanki_sogo_{$p}") + $I($out, "shotoku_joto_choki_sogo_{$p}") + max(0, $I($out, "shotoku_ichiji_{$p}"))
                ),
            ],
        ];
        $sogoTotal = ['itax'=>0,'rtax'=>0];
        foreach ($sogo as $v) { $sogoTotal['itax'] += (int)$v['itax']; $sogoTotal['rtax'] += (int)$v['rtax']; }

        $bunri = [
            'tanki_ippan'   => ['itax' => $I($out, "bunri_shotoku_tanki_ippan_shotoku_{$p}"),   'rtax' => $I($out, "bunri_shotoku_tanki_ippan_jumin_{$p}")],
            'tanki_keigen'  => ['itax' => $I($out, "bunri_shotoku_tanki_keigen_shotoku_{$p}"),  'rtax' => $I($out, "bunri_shotoku_tanki_keigen_jumin_{$p}")],
            'choki_ippan'   => ['itax' => $I($out, "bunri_shotoku_choki_ippan_shotoku_{$p}"),   'rtax' => $I($out, "bunri_shotoku_choki_ippan_jumin_{$p}")],
            'choki_tokutei' => ['itax' => $I($out, "bunri_shotoku_choki_tokutei_shotoku_{$p}"), 'rtax' => $I($out, "bunri_shotoku_choki_tokutei_jumin_{$p}")],
            'choki_keika'   => ['itax' => $I($out, "bunri_shotoku_choki_keika_shotoku_{$p}"),   'rtax' => $I($out, "bunri_shotoku_choki_keika_jumin_{$p}")],
            'ippan_kabu'    => ['itax' => $I($out, "bunri_shotoku_ippan_kabuteki_joto_shotoku_{$p}"), 'rtax' => $I($out, "bunri_shotoku_ippan_kabuteki_joto_jumin_{$p}")],
            'jojo_kabu'     => ['itax' => $I($out, "bunri_shotoku_jojo_kabuteki_joto_shotoku_{$p}"),  'rtax' => $I($out, "bunri_shotoku_jojo_kabuteki_joto_jumin_{$p}")],
            'jojo_haito'    => ['itax' => $I($out, "bunri_shotoku_jojo_kabuteki_haito_shotoku_{$p}"), 'rtax' => $I($out, "bunri_shotoku_jojo_kabuteki_haito_jumin_{$p}")],
            'sakimono'      => ['itax' => $I($out, "bunri_shotoku_sakimono_shotoku_{$p}"),            'rtax' => $I($out, "bunri_shotoku_sakimono_jumin_{$p}")],
            'sanrin'        => ['itax' => $I($out, "bunri_shotoku_sanrin_shotoku_{$p}"),              'rtax' => $I($out, "bunri_shotoku_sanrin_jumin_{$p}")],
            'taishoku'      => ['itax' => $I($out, "bunri_shotoku_taishoku_shotoku_{$p}"),            'rtax' => $I($out, "bunri_shotoku_taishoku_jumin_{$p}")],
        ];
        $bunriTotal = ['itax'=>0,'rtax'=>0];
        foreach ($bunri as $v) { $bunriTotal['itax'] += (int)$v['itax']; $bunriTotal['rtax'] += (int)$v['rtax']; }

        $incomeTable = [
            'sogo' => $sogo,
            'sogo_total' => $sogoTotal,
            'bunri' => $bunri,
            'bunri_total' => $bunriTotal,
            'grand_total' => [
                'itax' => $sogoTotal['itax'] + $bunriTotal['itax'],
                'rtax' => $sogoTotal['rtax'] + $bunriTotal['rtax'],
            ],
        ];

        // 所得控除（右表）
        $kojo = [
            'shakaihoken' => ['itax'=>$I($out, "kojo_shakaihoken_shotoku_{$p}"), 'rtax'=>$I($out, "kojo_shakaihoken_jumin_{$p}")],
            'shokibo'     => ['itax'=>$I($out, "kojo_shokibo_shotoku_{$p}"),     'rtax'=>$I($out, "kojo_shokibo_jumin_{$p}")],
            'seimei'      => ['itax'=>$I($out, "kojo_seimei_shotoku_{$p}"),      'rtax'=>$I($out, "kojo_seimei_jumin_{$p}")],
            'jishin'      => ['itax'=>$I($out, "kojo_jishin_shotoku_{$p}"),      'rtax'=>$I($out, "kojo_jishin_jumin_{$p}")],
            'kafu'        => ['itax'=>$I($out, "kojo_kafu_shotoku_{$p}"),        'rtax'=>$I($out, "kojo_kafu_jumin_{$p}")],
            'hitorioya'   => ['itax'=>$I($out, "kojo_hitorioya_shotoku_{$p}"),   'rtax'=>$I($out, "kojo_hitorioya_jumin_{$p}")],
            'kinrogakusei'=> ['itax'=>$I($out, "kojo_kinrogakusei_shotoku_{$p}"),'rtax'=>$I($out, "kojo_kinrogakusei_jumin_{$p}")],
            'shogaisha'   => ['itax'=>$I($out, "kojo_shogaisha_shotoku_{$p}"),   'rtax'=>$I($out, "kojo_shogaisha_jumin_{$p}")],
            'haigusha'    => ['itax'=>$I($out, "kojo_haigusha_shotoku_{$p}"),    'rtax'=>$I($out, "kojo_haigusha_jumin_{$p}")],
            'haigusha_tok'=> ['itax'=>$I($out, "kojo_haigusha_tokubetsu_shotoku_{$p}"), 'rtax'=>$I($out, "kojo_haigusha_tokubetsu_jumin_{$p}")],
            'fuyo'        => ['itax'=>$I($out, "kojo_fuyo_shotoku_{$p}"),        'rtax'=>$I($out, "kojo_fuyo_jumin_{$p}")],
            'tokutei_shinz'=> ['itax'=>$I($out, "kojo_tokutei_shinzoku_shotoku_{$p}"), 'rtax'=>$I($out, "kojo_tokutei_shinzoku_jumin_{$p}")],
            // 基礎控除は override キー（shotokuzei_/juminzei_）
            'kiso'        => ['itax'=>$I($out, "shotokuzei_kojo_kiso_{$p}"),     'rtax'=>$I($out, "juminzei_kojo_kiso_{$p}")],
        ];
        $shokei = ['itax'=>0,'rtax'=>0];
        foreach ($kojo as $v) { $shokei['itax'] += (int)$v['itax']; $shokei['rtax'] += (int)$v['rtax']; }

        $zasson = ['itax'=>$I($out, "kojo_zasson_shotoku_{$p}"), 'rtax'=>$I($out, "kojo_zasson_jumin_{$p}")];
        $iryo   = ['itax'=>$I($out, "kojo_iryo_shotoku_{$p}"),   'rtax'=>$I($out, "kojo_iryo_jumin_{$p}")];
        // 寄附金控除（所得控除）は所得税のみ表示（住民税は「-」固定）
        $kifukinItax = $I($out, "shotokuzei_kojo_kifukin_{$p}");

        $kojoTotal = [
            'itax' => $shokei['itax'] + (int)$zasson['itax'] + (int)$iryo['itax'] + (int)$kifukinItax,
            'rtax' => $shokei['rtax'] + (int)$zasson['rtax'] + (int)$iryo['rtax'],
        ];

        $kojoTable = [
            'rows' => $kojo,
            'shokei' => $shokei,
            'zasson' => $zasson,
            'iryo'   => $iryo,
            'kifukin_itax' => $kifukinItax,
            'total'  => $kojoTotal,
        ];

        return [
            'title'      => '所得金額・所得控除額の予測',
            'year'       => $year,
            'wareki_year'=> $this->toWarekiYear($year),
            'guest_name' => $guestName,
            'data_id'    => (int)$data->id,
            // ▼ 表示用（帳票上の「上限まで寄附」見出しなどに使うならこちら）
            'furusato_y_max_total'     => $yMaxTotalDisplay,
            // ▼ 計算の生値（デバッグや将来の「円単位表示」が必要ならこちら）
            'furusato_y_max_total_raw' => $yMaxTotalRaw,
            // ▼ どちらで出したか（帳票ヘッダ文言を変えるなら Blade 側で参照）
            'furusato_pdf_variant'     => $mode,
            'income_table_curr' => $incomeTable,
            'kojo_table_curr'   => $kojoTable,
        ];
    }

    public function fileName(Data $data): string
    {
        $year = (int)($data->kihu_year ?? now()->year);
        return "所得金額_所得控除額予測_{$year}_data{$data->id}.pdf";
    }

    // 全帳票：6_jintekikojosatyosei と同じ（A4横）
    public function pdfOptions(Data $data): array
    {
        return [
            'paper'  => 'a4',
            'orient' => 'landscape',
        ];
    }

    private function withFurusato(array $payload, int $y): array
    {
        // 旧互換：外部から呼ばれない想定にし、max/current を明示関数へ分離
        return $this->withFurusatoMax($payload, $y);
    }

    /**
     * 上限額（max）用：所得税側/住民税側とも「同額」で注入する（従来互換）
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
     * - 住民税側の pref/muni は「どちらか入っていれば同額コピー」で揃える
     * - 所得税側は“現状の入力”を尊重（0なら0のまま）
     */
    private function withFurusatoCurrent(array $payload): array
    {
        $itax = $this->n($payload['shotokuzei_shotokukojo_furusato_curr'] ?? 0);
        $pref = $this->n($payload['juminzei_zeigakukojo_pref_furusato_curr'] ?? 0);
        $muni = $this->n($payload['juminzei_zeigakukojo_muni_furusato_curr'] ?? 0);

        // 住民税側は「どちらか入っていれば同額コピー」で同期（UIの運用に合わせる）
        $j = max(0, max($pref, $muni));
        if ($j > 0) {
            $payload['juminzei_zeigakukojo_pref_furusato_curr'] = $j;
            $payload['juminzei_zeigakukojo_muni_furusato_curr'] = $j;
        }

        // 所得税側は current では勝手に上書きしない（ワンストップを壊さない）
        $payload['shotokuzei_shotokukojo_furusato_curr'] = max(0, $itax);
        return $payload;
    }

    /**
     * 「今までに寄付した額（当年）」を安全に解決する
     * - ワンストップ等で所得税側が 0 でも、住民税側（pref/muni）に入力があればそれを採用
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
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int) floor((float) $v) : 0;
    }

    private function floorToThousands(int $v): int
    {
        if ($v <= 0) return 0;
        return (int) (floor($v / 1000) * 1000);
    }

    /**
     * 住民税側の退職分離キーを帳票経路で0固定する。
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function forceJuminTaishokuZero(array $payload): array
    {
        $payload['bunri_syunyu_taishoku_jumin_prev'] = 0;
        $payload['bunri_syunyu_taishoku_jumin_curr'] = 0;
        $payload['bunri_shotoku_taishoku_jumin_prev'] = 0;
        $payload['bunri_shotoku_taishoku_jumin_curr'] = 0;
        $payload['tb_taishoku_jumin_prev'] = 0;
        $payload['tb_taishoku_jumin_curr'] = 0;

        return $payload;
    }

    /**
     * 2ページ左表の分離所得キーを、dry-run出力のsourceキーから補完する。
     *
     * @param  array<string,mixed>  $out
     * @return array<string,mixed>
     */
    private function bridgeBunriLeftTableKeys(array $out): array
    {
        foreach (['prev', 'curr'] as $period) {
            $mappings = [
                "joto_shotoku_tanki_ippan_{$period}" => [
                    "bunri_shotoku_tanki_ippan_shotoku_{$period}",
                    "bunri_shotoku_tanki_ippan_jumin_{$period}",
                ],
                "joto_shotoku_tanki_keigen_{$period}" => [
                    "bunri_shotoku_tanki_keigen_shotoku_{$period}",
                    "bunri_shotoku_tanki_keigen_jumin_{$period}",
                ],
                "joto_shotoku_choki_ippan_{$period}" => [
                    "bunri_shotoku_choki_ippan_shotoku_{$period}",
                    "bunri_shotoku_choki_ippan_jumin_{$period}",
                ],
                "joto_shotoku_choki_tokutei_{$period}" => [
                    "bunri_shotoku_choki_tokutei_shotoku_{$period}",
                    "bunri_shotoku_choki_tokutei_jumin_{$period}",
                ],
                "joto_shotoku_choki_keika_{$period}" => [
                    "bunri_shotoku_choki_keika_shotoku_{$period}",
                    "bunri_shotoku_choki_keika_jumin_{$period}",
                ],
                "shotoku_after_kurikoshi_ippan_joto_{$period}" => [
                    "bunri_shotoku_ippan_kabuteki_joto_shotoku_{$period}",
                    "bunri_shotoku_ippan_kabuteki_joto_jumin_{$period}",
                ],
                "shotoku_after_kurikoshi_jojo_joto_{$period}" => [
                    "bunri_shotoku_jojo_kabuteki_joto_shotoku_{$period}",
                    "bunri_shotoku_jojo_kabuteki_joto_jumin_{$period}",
                ],
                "shotoku_after_kurikoshi_jojo_haito_{$period}" => [
                    "bunri_shotoku_jojo_kabuteki_haito_shotoku_{$period}",
                    "bunri_shotoku_jojo_kabuteki_haito_jumin_{$period}",
                ],
                "shotoku_sanrin_{$period}" => [
                    "bunri_shotoku_sanrin_shotoku_{$period}",
                    "bunri_shotoku_sanrin_jumin_{$period}",
                ],
            ];

            foreach ($mappings as $source => $destinations) {
                if (!array_key_exists($source, $out)) {
                    continue;
                }

                $value = $this->n($out[$source]);
                foreach ($destinations as $destination) {
                    $out[$destination] = $value;
                }
            }
        }

        return $out;
    }

    private function toWarekiYear(int $year): string
    {
        if ($year >= 2019) {
            return sprintf('令和%d年', $year - 2018);
        }
        if ($year >= 1989) {
            return sprintf('平成%d年', $year - 1988);
        }
        if ($year >= 1926) {
            return sprintf('昭和%d年', $year - 1925);
        }
        return (string) $year;
    }
}
