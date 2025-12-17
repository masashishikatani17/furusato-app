<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;
use App\Domain\Tax\Contracts\MasterProviderContract;

class JuminTaxCalculator implements ProvidesKeys
{
    public const ID = 'tax.jumin';
    // 【制度順】フェーズD：住民税額（所得税額の後）
    public const ORDER = 5200;
    public const ANCHOR = 'tax';
    public const BEFORE = [];
    public const AFTER = [ShotokuTaxCalculator::ID];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public function __construct(private readonly MasterProviderContract $masterProvider)
    {
    }

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [
            // 合計課税所得金額（調整控除・Tokurei用）
            'jumin_kazeishotoku_total_prev',
            'jumin_kazeishotoku_total_curr',
            // 調整控除前の所得割額（都道府県・市区町村）
            'chosei_mae_shotokuwari_pref_prev',
            'chosei_mae_shotokuwari_pref_curr',
            'chosei_mae_shotokuwari_muni_prev',
            'chosei_mae_shotokuwari_muni_curr',
            // 調整控除額（都道府県・市区町村・合計）
            'chosei_kojo_pref_prev',
            'chosei_kojo_pref_curr',
            'chosei_kojo_muni_prev',
            'chosei_kojo_muni_curr',
            'jumin_choseikojo_total_prev',
            'jumin_choseikojo_total_curr',
            // 調整控除後の所得割額（都道府県・市区町村）
            'choseigo_shotokuwari_pref_prev',
            'choseigo_shotokuwari_pref_curr',
            'choseigo_shotokuwari_muni_prev',
            'choseigo_shotokuwari_muni_curr',
            // 20%cap の母数（退職分を除外した後の所得割額ベース）
            'choseigo_shotokuwari_capbase_pref_prev',
            'choseigo_shotokuwari_capbase_pref_curr',
            'choseigo_shotokuwari_capbase_muni_prev',
            'choseigo_shotokuwari_capbase_muni_curr',
            // 住民税（所得割）の算出税額（市＋県）
            'tax_zeigaku_jumin_prev',
            'tax_zeigaku_jumin_curr',
        ];

        // 分離課税（住民税側）の税額（第三表用）
        foreach (self::PERIODS as $p) {
            $keys[] = sprintf('bunri_zeigaku_sogo_jumin_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_tanki_jumin_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_choki_jumin_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_joto_jumin_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_haito_jumin_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_sakimono_jumin_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_sanrin_jumin_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_taishoku_jumin_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_gokei_jumin_%s', $p);
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $updates = array_fill_keys(self::provides(), 0);

        $settings  = is_array($ctx['syori_settings'] ?? null) ? $ctx['syori_settings'] : [];
        $year      = isset($ctx['master_kihu_year']) ? (int) $ctx['master_kihu_year'] : 0;
        $companyId = isset($ctx['company_id']) && $ctx['company_id'] !== ''
            ? (int) $ctx['company_id']
            : null;
        $dataId    = isset($ctx['data_id']) && $ctx['data_id'] !== ''
            ? (int) $ctx['data_id']
            : null;

        // 住民税率マスタ（jumin_master.blade で編集される値）
        $rateRows = $year > 0
            ? $this->buildJuminRateRows($year, $companyId, $dataId)
            : [];

        foreach (self::PERIODS as $period) {
            // ===== 1) 合計課税所得金額（住民税側）tb_sogo_jumin + tb_sanrin_jumin + tb_taishoku_jumin =====
            $sogo   = $this->n($payload[sprintf('tb_sogo_jumin_%s',   $period)] ?? null);
            $sanrin = $this->n($payload[sprintf('tb_sanrin_jumin_%s', $period)] ?? null);
            $taishoku = $this->n($payload[sprintf('tb_taishoku_jumin_%s', $period)] ?? null);

            $totalTaxable = max(0, $sogo) + max(0, $sanrin) + max(0, $taishoku);
            $updates[sprintf('jumin_kazeishotoku_total_%s', $period)] = $totalTaxable;

            // ===== 2) 調整控除前の所得割額（都道府県・市区町村）=====
            // ベースは tb_sogo_jumin（総合）とする（山林・退職は個別の扱いが必要なため）
            $kazeiSogo = max(0, $sogo);

            $shitei = $this->resolveFlag($settings, $payload, 'shitei_toshi_flag', $period);

            // 総合課税の住民税率を jumin_master から取得（カテゴリ '総合課税'）
            $prefRate = $this->juminRate($rateRows, '総合課税', null, $shitei, 'pref'); // 0.04, 0.02 等
            $muniRate = $this->juminRate($rateRows, '総合課税', null, $shitei, 'city');

            $beforePref = $this->mulRate($kazeiSogo, $prefRate);
            $beforeMuni = $this->mulRate($kazeiSogo, $muniRate);

            $updates[sprintf('chosei_mae_shotokuwari_pref_%s', $period)] = $beforePref;
            $updates[sprintf('chosei_mae_shotokuwari_muni_%s', $period)] = $beforeMuni;

            // ===== 3) 合計所得金額（調整控除の適用判定用） =====
            $gokeiKey = sprintf('sum_for_gokeishotoku_%s', $period);
            $sumGokei = $this->n($payload[$gokeiKey] ?? null);

            // ===== 4) 人的控除額の差の合計額（human_diff_sum_*） =====
            $humanDiff = $this->n($payload[sprintf('human_diff_sum_%s', $period)] ?? null);

            // ===== 5) 調整控除額（合計） A_total =====
            $A_total = 0;

            if ($sumGokei <= 25_000_000 && $totalTaxable > 0 && $humanDiff > 0) {
                if ($totalTaxable <= 2_000_000) {
                    // (1) 合計課税所得金額が 200 万円以下
                    //     min(人的控除差の合計額, 合計課税所得金額) × 5%
                    $base = min($humanDiff, $totalTaxable);
                    if ($base > 0) {
                        $A_total = $this->mulRate($base, 0.05);
                    }
                } else {
                    // (2) 合計課税所得金額が 200 万円超
                    //     {人的控除差の合計額 − (合計課税所得金額 − 200 万円)}
                    //     が 5 万円を下回る場合には 5 万円として 5% を乗じる。
                    $rawBase = $humanDiff - ($totalTaxable - 2_000_000);
                    // 法令上「5万円を下回る場合には5万円」とされているため、
                    // 差がマイナスであっても 5 万円フロアを適用する。
                    $base = max($rawBase, 50_000);
                    $A_total = $this->mulRate($base, 0.05);
                }

                // 調整控除額は「調整控除前所得割額（市＋県）」を超えない
                $maxDeductible = max(0, $beforePref + $beforeMuni);
                if ($A_total > $maxDeductible) {
                    $A_total = $maxDeductible;
                }
            }

            $updates[sprintf('jumin_choseikojo_total_%s', $period)] = $A_total;

            // 都道府県／市区町村への按分（指定都市 2:8, 非指定 4:6）
            if ($A_total === 0) {
                $prefKojo = 0;
                $muniKojo = 0;
            } else {
                $prefRatio = $shitei ? 0.2 : 0.4;
                $prefKojo  = $this->mulRate($A_total, $prefRatio);
                $muniKojo  = max($A_total - $prefKojo, 0);
            }

            $updates[sprintf('chosei_kojo_pref_%s', $period)] = $prefKojo;
            $updates[sprintf('chosei_kojo_muni_%s', $period)] = $muniKojo;

            // ===== 6) 調整控除後所得割額 =====
            $afterPref = max($beforePref - $prefKojo, 0);
            $afterMuni = max($beforeMuni - $muniKojo, 0);

            $updates[sprintf('choseigo_shotokuwari_pref_%s', $period)] = $afterPref;
            $updates[sprintf('choseigo_shotokuwari_muni_%s', $period)] = $afterMuni;
            // 現時点では退職分は JuminTax では扱っていないため、
            // cap 用母数（退職除外後）は「調整控除後所得割額」と同一とする。
            // 将来 tb_taishoku_jumin_* を含めるようになったら、ここで退職分を控除する。
            $updates[sprintf('choseigo_shotokuwari_capbase_pref_%s', $period)] = $afterPref;
            $updates[sprintf('choseigo_shotokuwari_capbase_muni_%s', $period)] = $afterMuni;
            // ===== 7) 住民税（所得割）の算出税額（prefmuni 合計）=====
            $baseSogoZeigaku = (int) ($afterPref + $afterMuni);
            $updates[sprintf('tax_zeigaku_jumin_%s', $period)] = $baseSogoZeigaku;

            /**
             * ▼ 分離課税（住民税）用の税額（第三表）
             *   - JS で行っていた recalcBunriZeigakuJuminAll / recalcZeigakuGokeiAll のロジックを移植
             *   - bunri_flag が 0 の期間は 0 のまま
             */
            $bunriOn = $this->resolveFlag($settings, $payload, 'bunri_flag', $period);
            if (! $bunriOn) {
                continue;
            }

            // 第三表の「総合課税」行は、JuminTaxCalculator が確定した総合税額（調整控除後）をそのまま表示
            // （第一表の総合税額と一致させる）
            $updates[sprintf('bunri_zeigaku_sogo_jumin_%s', $period)] = $baseSogoZeigaku;

            // 住民税率（市+県）を jumin_master から取得して税額を算出する（pref/city を別々に floor → 合算）
            // ※ floor((pref+city)*amount) ではなく、制度上の「県・市ごとの算出」前提に合わせる。
            // ※ マスター未設定で rate=0 のまま金額が入っている場合は debug ログに出す（原因追跡用）。
            $taxByMaster = function (int $amount, string $category, ?string $sub, ?string $remarkContains = null) use ($rateRows, $shitei, $period): int {
                return $this->bunriTaxByMaster($rateRows, $shitei, $amount, $category, $sub, $remarkContains, $period);
            };

            // 短期譲渡：一般 / 軽減（区分別に master の率を使う）
            $tIppan  = $this->n($payload[sprintf('bunri_shotoku_tanki_ippan_jumin_%s',  $period)] ?? null);
            $tKeigen = $this->n($payload[sprintf('bunri_shotoku_tanki_keigen_jumin_%s', $period)] ?? null);
            $zeigakuTanki = $taxByMaster($tIppan, '短期譲渡', '一般') + $taxByMaster($tKeigen, '短期譲渡', '軽減');
            $updates[sprintf('bunri_zeigaku_tanki_jumin_%s', $period)] = $zeigakuTanki;

            // 長期譲渡：一般 + 特定(2,000万円以下/超) + 軽課(6,000万円以下/超)
            $cIppan   = $this->n($payload[sprintf('bunri_shotoku_choki_ippan_jumin_%s',   $period)] ?? null);
            $cTokutei = $this->n($payload[sprintf('bunri_shotoku_choki_tokutei_jumin_%s', $period)] ?? null);
            $cKeika   = $this->n($payload[sprintf('bunri_shotoku_choki_keika_jumin_%s',   $period)] ?? null);

            $zeigakuChoki = $taxByMaster($cIppan, '長期譲渡', '一般');
            // 特定：2,000万円以下 / 超（remarkで「以下」「超」を拾う）
            $tokLow  = min(20_000_000, max(0, $cTokutei));
            $tokHigh = max(0, $cTokutei - 20_000_000);
            $zeigakuChoki += $taxByMaster($tokLow,  '長期譲渡', '特定', '以下');
            $zeigakuChoki += $taxByMaster($tokHigh, '長期譲渡', '特定', '超');
            // 軽課：6,000万円以下 / 超
            $keiLow  = min(60_000_000, max(0, $cKeika));
            $keiHigh = max(0, $cKeika - 60_000_000);
            $zeigakuChoki += $taxByMaster($keiLow,  '長期譲渡', '軽課', '以下');
            $zeigakuChoki += $taxByMaster($keiHigh, '長期譲渡', '軽課', '超');
            $updates[sprintf('bunri_zeigaku_choki_jumin_%s', $period)] = $zeigakuChoki;

            // 一般株式等の譲渡 / 上場株式等の譲渡（区分別に master の率）
            $tbIppan = $this->n($payload[sprintf('tb_ippan_kabuteki_joto_jumin_%s', $period)] ?? null);
            $tbJojo  = $this->n($payload[sprintf('tb_jojo_kabuteki_joto_jumin_%s',  $period)] ?? null);
            $zeigakuJoto = $taxByMaster($tbIppan, '一般株式等の譲渡', null) + $taxByMaster($tbJojo, '上場株式等の譲渡', null);
            $updates[sprintf('bunri_zeigaku_joto_jumin_%s', $period)] = $zeigakuJoto;

            // 上場株式等の配当等（master の率）
            $haitoTaxable = $this->n($payload[sprintf('tb_jojo_kabuteki_haito_jumin_%s', $period)] ?? null);
            $zeigakuHaito = $taxByMaster($haitoTaxable, '上場株式等の配当等', null);
            $updates[sprintf('bunri_zeigaku_haito_jumin_%s', $period)] = $zeigakuHaito;

            // 先物取引（master の率）
            $sakiTaxable = $this->n($payload[sprintf('tb_sakimono_jumin_%s', $period)] ?? null);
            $zeigakuSakimono = $taxByMaster($sakiTaxable, '先物取引', null);
            $updates[sprintf('bunri_zeigaku_sakimono_jumin_%s', $period)] = $zeigakuSakimono;

            // 山林（master の率）
            $sanTaxable = $this->n($payload[sprintf('tb_sanrin_jumin_%s', $period)] ?? null);
            $zeigakuSanrin = $taxByMaster($sanTaxable, '山林', null);
            $updates[sprintf('bunri_zeigaku_sanrin_jumin_%s', $period)] = $zeigakuSanrin;

            // 退職（master の率）
            $taiTaxable = $this->n($payload[sprintf('tb_taishoku_jumin_%s', $period)] ?? null);
            $zeigakuTaishoku = $taxByMaster($taiTaxable, '退職', null);
            $updates[sprintf('bunri_zeigaku_taishoku_jumin_%s', $period)] = $zeigakuTaishoku;

            // 合計（第三表の「合計（第一表へ）」行）
            $gokei =
                $baseSogoZeigaku +
                $zeigakuTanki +
                $zeigakuChoki +
                $zeigakuJoto +
                $zeigakuHaito +
                $zeigakuSakimono +
                $zeigakuSanrin +
                $zeigakuTaishoku;
            $updates[sprintf('bunri_zeigaku_gokei_jumin_%s', $period)] = $gokei;
            // 分離ON年度：第一表の tax_zeigaku_jumin_* は「総合＋分離」の合算額を表示
            // （第三表の合計（第一表へ）と一致させる）
            $updates[sprintf('tax_zeigaku_jumin_%s', $period)] = $gokei;
        }

        return array_replace($payload, $updates);
    }

    /**
     * 分離課税（住民税）税額計算：jumin_master の県・市率をそれぞれ乗算して合算する。
     * - 例：先物取引を「合計 8%」にしたい場合、city_specified + pref_specified（または non_specified）が 8 になるように設定すればOK
     * - 率が取得できず 0 のまま金額だけある場合、debug で追跡できるようログを出す
     *
     * @param  array<int, array<string, mixed>>  $rateRows
     */
    private function bunriTaxByMaster(
        array $rateRows,
        bool $shitei,
        int $amount,
        string $category,
        ?string $sub,
        ?string $remarkContains,
        string $period
    ): int {
        $a = max(0, $amount);
        if ($a === 0) {
            return 0;
        }

        $prefRate = $this->juminRate($rateRows, $category, $sub, $shitei, 'pref', $remarkContains);
        $muniRate = $this->juminRate($rateRows, $category, $sub, $shitei, 'city', $remarkContains);

        // マスターが欠けている疑い（率0なのに金額がある）
        if (config('app.debug') && ($prefRate + $muniRate) === 0.0) {
            \Log::warning('[jumin.bunri.rate.missing]', [
                'period' => $period,
                'category' => $category,
                'sub_category' => $sub,
                'remark_contains' => $remarkContains,
                'shitei' => $shitei ? 1 : 0,
                'amount' => $a,
            ]);
        }

        // 県・市で別々に算出 → 合算（制度上の作りに合わせる）
        return $this->mulRate($a, $prefRate) + $this->mulRate($a, $muniRate);
    }


    /**
     * jumin_master から住民税率マスタを取得。
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildJuminRateRows(int $year, ?int $companyId, ?int $dataId): array
    {
        $collection = $this->masterProvider->getJuminRates($year, $companyId, $dataId);

        $rows = [];
        foreach ($collection as $row) {
            $rows[] = [
                'category' => isset($row->category) ? (string) $row->category : '',
                'sub_category' => isset($row->sub_category) && $row->sub_category !== ''
                    ? (string) $row->sub_category
                    : null,
                'remark' => isset($row->remark) && $row->remark !== '' ? (string) $row->remark : null,
                'pref_specified' => isset($row->pref_specified) ? (float) $row->pref_specified : 0.0,
                'pref_non_specified' => isset($row->pref_non_specified) ? (float) $row->pref_non_specified : 0.0,
                'city_specified' => isset($row->city_specified) ? (float) $row->city_specified : 0.0,
                'city_non_specified' => isset($row->city_non_specified) ? (float) $row->city_non_specified : 0.0,
            ];
        }

        return $rows;
    }

    /**
     * 住民税率（総合課税・基本控除・特例控除など）を取得。
     *
     * 総合課税等の税率カテゴリでは 0.08, 0.06 のように「率」を返す。
     * 基本控除・特例控除ではマスタに書かれた値をそのまま（8, 6, 0.8 等）返す。
     *
     * @param  array<int, array<string, mixed>>  $rates
     */
    private function juminRate(
        array $rates,
        string $category,
        ?string $subCategory,
        bool $shitei,
        string $target,
        ?string $remarkContains = null
    ): float {
        // 「総合」と「総合課税」がマスタ上で混在しうるため、
        // '総合課税' 指定時は '総合' も同一カテゴリとして扱う
        $categoryAlts = $category === '総合課税'
            ? ['総合課税', '総合']
            : [$category];

        foreach ($rates as $rate) {
            $rateCategory = (string) ($rate['category'] ?? '');
            if (! in_array($rateCategory, $categoryAlts, true)) {
                continue;
            }

            $sub = $rate['sub_category'] ?? null;
            if ($sub !== $subCategory) {
                continue;
            }

            if ($remarkContains !== null) {
                $remark = (string) ($rate['remark'] ?? '');
                if ($remark === '' || ! str_contains($remark, $remarkContains)) {
                    continue;
                }
            }

            $value = $shitei
                ? ($target === 'pref' ? $rate['pref_specified'] : $rate['city_specified'])
                : ($target === 'pref' ? $rate['pref_non_specified'] : $rate['city_non_specified']);

            $numeric = (float) $value;

            if ($category === '特例控除') {
                // 特例控除は市・県の按分比(0.2, 0.8 など)を
                // そのまま「率」として使う
                return $numeric;
            }

            if ($category === '基本控除') {
                // 基本控除は jumin_master 上では「○％」表記なので
                // 0.xx の率に変換して使う（例: 4 → 0.04）
                return $numeric / 100.0;
            }

            // 総合課税など通常の税率は 〇％ → 率(0.xx) に変換
            return $numeric / 100.0;
        }

        return 0.0;
    }

    private function resolveFlag(array $settings, array $payload, string $baseKey, string $period): bool
    {
        $keys = [
            sprintf('%s_%s', $baseKey, $period),
            $baseKey,
        ];

        foreach ($keys as $key) {
            if (array_key_exists($key, $settings)) {
                return $this->n($settings[$key]) === 1;
            }
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return $this->n($payload[$key]) === 1;
            }
        }

        return false;
    }

    private function n(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        return is_numeric($value) ? (int) floor((float) $value) : 0;
    }

    private function mulRate(int $amount, float $rate): int
    {
        if ($rate === 0.0 || $amount === 0) {
            return 0;
        }

        return (int) floor($amount * $rate);
    }
}