<?php

namespace App\Domain\Tax\Calculators;

use Illuminate\Support\Arr;
use App\Domain\Tax\Contracts\MasterProviderContract;
use App\Services\Tax\Contracts\ProvidesKeys;
use App\Domain\Tax\Calculators\KojoAggregationCalculator;
use App\Domain\Tax\Calculators\CommonTaxableBaseCalculator;
use App\Domain\Tax\Calculators\TokureiRateCalculator;

final class JuminzeiKifukinCalculator implements ProvidesKeys
{
    public const ID    = 'kojo.kifukin.jumin';
    // 【制度順】フェーズD：住民税の寄附税額控除（tb_*確定・率確定の後）
    public const ORDER = 5300;
    public const BEFORE = [];
    public const AFTER  = [
        KojoAggregationCalculator::ID,
        CommonTaxableBaseCalculator::ID,
        TokureiRateCalculator::ID,
    ];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public function __construct(private readonly MasterProviderContract $masterProvider)
    {
    }

    public static function provides(): array
    {
        return [
            'kazeisoushotoku_prev',
            'kazeisoushotoku_curr',
            'kifu_gaku_prev',
            'kifu_gaku_curr',
            'furusato_kifu_gaku_prev',
            'furusato_kifu_gaku_curr',
            'juminzei_kojo_kifukin_prev',
            'juminzei_kojo_kifukin_curr',
            'chosei_mae_shotokuwari_pref_prev',
            'chosei_mae_shotokuwari_pref_curr',
            'chosei_mae_shotokuwari_muni_prev',
            'chosei_mae_shotokuwari_muni_curr',
            'chosei_kojo_pref_prev',
            'chosei_kojo_pref_curr',
            'chosei_kojo_muni_prev',
            'chosei_kojo_muni_curr',
            'choseigo_shotokuwari_pref_prev',
            'choseigo_shotokuwari_pref_curr',
            'choseigo_shotokuwari_muni_prev',
            'choseigo_shotokuwari_muni_curr',
            'kihon_kojo_pref_prev',
            'kihon_kojo_pref_curr',
            'kihon_kojo_muni_prev',
            'kihon_kojo_muni_curr',
            'tokurei_kojo_pref_prev',
            'tokurei_kojo_pref_curr',
            'tokurei_kojo_muni_prev',
            'tokurei_kojo_muni_curr',
            'shotokuwari20_pref_prev',
            'shotokuwari20_pref_curr',
            'shotokuwari20_muni_prev',
            'shotokuwari20_muni_curr',
            'tokurei_kojo_jogen_pref_prev',
            'tokurei_kojo_jogen_pref_curr',
            'tokurei_kojo_jogen_muni_prev',
            'tokurei_kojo_jogen_muni_curr',
            'shinkokutokurei_kojo_pref_prev',
            'shinkokutokurei_kojo_pref_curr',
            'shinkokutokurei_kojo_muni_prev',
            'shinkokutokurei_kojo_muni_curr',
            'kifukin_zeigaku_kojo_pref_prev',
            'kifukin_zeigaku_kojo_pref_curr',
            'kifukin_zeigaku_kojo_muni_prev',
            'kifukin_zeigaku_kojo_muni_curr',
            'kifukin_zeigaku_kojo_gokei_prev',
            'kifukin_zeigaku_kojo_gokei_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $settings = is_array($ctx['syori_settings'] ?? null) ? $ctx['syori_settings'] : [];
        $year = isset($ctx['master_kihu_year']) ? (int) $ctx['master_kihu_year'] : 0;
        $companyId = isset($ctx['company_id']) && $ctx['company_id'] !== ''
            ? (int) $ctx['company_id']
            : null;
        // data_id ごとの住民税率マスターを参照するためのキー
        $dataId = isset($ctx['data_id']) && $ctx['data_id'] !== ''
            ? (int) $ctx['data_id']
            : null;
        $rateRows = $year > 0
            ? $this->buildJuminRateRows($year, $companyId, $dataId)
            : [];
        $shinkokuRateRows = $year > 0
            ? $this->buildShinkokutokureiRateRows($year, $companyId)
            : [];

        $out = [
            'kazeisoushotoku_prev'       => 0,
            'kazeisoushotoku_curr'       => 0,
            'kifu_gaku_prev'             => 0,
            'kifu_gaku_curr'             => 0,
            'furusato_kifu_gaku_prev'    => 0,
            'furusato_kifu_gaku_curr'    => 0,
            'juminzei_kojo_kifukin_prev' => (int) ($payload['juminzei_kojo_kifukin_prev'] ?? 0),
            'juminzei_kojo_kifukin_curr' => (int) ($payload['juminzei_kojo_kifukin_curr'] ?? 0),
            'chosei_mae_shotokuwari_pref_prev' => 0,
            'chosei_mae_shotokuwari_pref_curr' => 0,
            'chosei_mae_shotokuwari_muni_prev' => 0,
            'chosei_mae_shotokuwari_muni_curr' => 0,
            'chosei_kojo_pref_prev' => 0,
            'chosei_kojo_pref_curr' => 0,
            'chosei_kojo_muni_prev' => 0,
            'chosei_kojo_muni_curr' => 0,
            'choseigo_shotokuwari_pref_prev' => 0,
            'choseigo_shotokuwari_pref_curr' => 0,
            'choseigo_shotokuwari_muni_prev' => 0,
            'choseigo_shotokuwari_muni_curr' => 0,
            'kihon_kojo_pref_prev' => 0,
            'kihon_kojo_pref_curr' => 0,
            'kihon_kojo_muni_prev' => 0,
            'kihon_kojo_muni_curr' => 0,
            'tokurei_kojo_pref_prev' => 0,
            'tokurei_kojo_pref_curr' => 0,
            'tokurei_kojo_muni_prev' => 0,
            'tokurei_kojo_muni_curr' => 0,
            'shotokuwari20_pref_prev' => 0,
            'shotokuwari20_pref_curr' => 0,
            'shotokuwari20_muni_prev' => 0,
            'shotokuwari20_muni_curr' => 0,
            'tokurei_kojo_jogen_pref_prev' => 0,
            'tokurei_kojo_jogen_pref_curr' => 0,
            'tokurei_kojo_jogen_muni_prev' => 0,
            'tokurei_kojo_jogen_muni_curr' => 0,
            'shinkokutokurei_kojo_pref_prev' => 0,
            'shinkokutokurei_kojo_pref_curr' => 0,
            'shinkokutokurei_kojo_muni_prev' => 0,
            'shinkokutokurei_kojo_muni_curr' => 0,
            'kifukin_zeigaku_kojo_pref_prev' => 0,
            'kifukin_zeigaku_kojo_pref_curr' => 0,
            'kifukin_zeigaku_kojo_muni_prev' => 0,
            'kifukin_zeigaku_kojo_muni_curr' => 0,
            'kifukin_zeigaku_kojo_gokei_prev' => 0,
            'kifukin_zeigaku_kojo_gokei_curr' => 0,
        ];

        foreach (self::PERIODS as $period) {
            /**
             * ▼ 寄附金税額控除の算定表で表示する「課税総所得金額」は
             *   住民税の「総合課税の課税標準（tb_sogo_jumin）」を用いる。
             *
             * ここで山林・退職（tb_sanrin_jumin / tb_taishoku_jumin）を加算すると、
             * 画面上「課税総所得金額」として誤解されやすく、また他行で別表示される場合に混線する。
             */
            $kazeisoushotoku = max(0, $this->n($payload[sprintf('tb_sogo_jumin_%s', $period)] ?? null));
            $out["kazeisoushotoku_{$period}"] = $kazeisoushotoku;

            // ▼ 仕様3：旧合算キーは参照しない（pref/muni のみをSoTに固定）
            $categories = ['furusato', 'kyodobokin_nisseki', 'npo', 'koueki', 'sonota'];
            $sumKifuPref = 0; // 県分：表示用/内訳用（合算計算の材料にも使う）
            $sumKifuMuni = 0; // 市分：表示用/内訳用（合算計算の材料にも使う）
            foreach ($categories as $category) {
                $pref = $this->n($payload["juminzei_zeigakukojo_pref_{$category}_{$period}"] ?? null);
                $muni = $this->n($payload["juminzei_zeigakukojo_muni_{$category}_{$period}"] ?? null);
                $sumKifuPref += $pref;
                $sumKifuMuni += $muni;
            }
            $sumKifu = $sumKifuPref + $sumKifuMuni;
            $out["kifu_gaku_{$period}"] = $sumKifu;

            // ▼ 仕様3：旧合算キーは参照しない（pref/muni のみ）
            $prefF = $this->n($payload["juminzei_zeigakukojo_pref_furusato_{$period}"] ?? null);
            $muniF = $this->n($payload["juminzei_zeigakukojo_muni_furusato_{$period}"] ?? null);
            $out["furusato_kifu_gaku_{$period}"] = $prefF + $muniF;

            $prefApplied = $this->resolveAppliedRate($settings, $payload, 'pref', $period);
            $muniApplied = $this->resolveAppliedRate($settings, $payload, 'muni', $period);
            $shitei = $this->resolveFlag($settings, $payload, 'shitei_toshi_flag', $period);
            // ▼ 調整控除前／控除額／控除後の所得割額は JuminTaxCalculator が算出した SoT を参照する
            $prefBeforeKey = sprintf('chosei_mae_shotokuwari_pref_%s', $period);
            $muniBeforeKey = sprintf('chosei_mae_shotokuwari_muni_%s', $period);
            $prefKojoKey   = sprintf('chosei_kojo_pref_%s', $period);
            $muniKojoKey   = sprintf('chosei_kojo_muni_%s', $period);
            $prefAfterKey  = sprintf('choseigo_shotokuwari_pref_%s', $period);
            $muniAfterKey  = sprintf('choseigo_shotokuwari_muni_%s', $period);

            $prefBefore = $this->n($payload[$prefBeforeKey] ?? null);
            $muniBefore = $this->n($payload[$muniBeforeKey] ?? null);
            $prefKojo   = $this->n($payload[$prefKojoKey]   ?? null);
            $muniKojo   = $this->n($payload[$muniKojoKey]   ?? null);
            $prefAfter  = $this->n($payload[$prefAfterKey] ?? null);
            $muniAfter  = $this->n($payload[$muniAfterKey] ?? null);

            $out[$prefBeforeKey] = $prefBefore;
            $out[$muniBeforeKey] = $muniBefore;
            $out[$prefKojoKey]   = $prefKojo;
            $out[$muniKojoKey]   = $muniKojo;
            $out[$prefAfterKey]  = $prefAfter;
            $out[$muniAfterKey]  = $muniAfter;

            $kihonPrefKey = sprintf('kihon_kojo_pref_%s', $period);
            $kihonMuniKey = sprintf('kihon_kojo_muni_%s', $period);

            $kihonPrefRate = $this->juminRate($rateRows, '基本控除', null, $shitei, 'pref');
            $kihonMuniRate = $this->juminRate($rateRows, '基本控除', null, $shitei, 'city');

            /**
             * ▼ 仕様変更：基本控除は「合計（県+市）で -2,000」を1回だけ行う
             *   eligible = min(寄付合計, 30%cap) - 2,000
             *   県 = ceil(eligible * 県率), 市 = ceil(eligible * 市率)
             */
            $mother = $this->n($payload[sprintf('sum_for_sogoshotoku_etc_%s', $period)] ?? null);
            $guardedCap = $this->mulRate($mother, 0.3);

            $eligible = 0;
            if ($sumKifu > 2_000) {
                $limit = min($sumKifu, $guardedCap);
                $eligible = max($limit - 2_000, 0);
            }

            // 端数処理：県民税/市民税それぞれ「1円未満切上げ」
            $kihonPrefRaw = $eligible > 0 ? ($eligible * $kihonPrefRate) : 0.0;
            $kihonMuniRaw = $eligible > 0 ? ($eligible * $kihonMuniRate) : 0.0;
            $out[$kihonPrefKey] = $this->ceilPositive($kihonPrefRaw);
            $out[$kihonMuniKey] = $this->ceilPositive($kihonMuniRaw);

            $tokureiPrefKey = sprintf('tokurei_kojo_pref_%s', $period);
            $tokureiMuniKey = sprintf('tokurei_kojo_muni_%s', $period);

            /**
             * ▼ 特例控除も「合計（県+市）で -2,000」を1回だけ行う
             *   base = (ふるさと(県+市) - 2,000)
             *   県 = ceil(base * final_rate * 県按分), 市 = ceil(base * final_rate * 市按分)
             */
            $furusatoTotal = max(0, $prefF + $muniF);
            $tokureiBase = ($furusatoTotal > 2_000) ? ($furusatoTotal - 2_000) : 0;

            $tokureiRateFinalPercent = $this->decimal($payload[sprintf('tokurei_rate_final_%s', $period)] ?? null);
            $tokureiRateFinalRatio = $tokureiRateFinalPercent > 0.0
                ? $tokureiRateFinalPercent / 100.0
                : 0.0;
            $tokureiPrefRate = $this->juminRate($rateRows, '特例控除', null, $shitei, 'pref');
            $tokureiMuniRate = $this->juminRate($rateRows, '特例控除', null, $shitei, 'city');

            // 特例控除（生値）：pref/muni それぞれ独立に計算（端数：県市それぞれ切上げ）
            $tokureiBaseAfterRate = ($tokureiBase > 0 && $tokureiRateFinalRatio > 0.0)
                ? ($tokureiBase * $tokureiRateFinalRatio)
                : 0.0;

            $tokureiPrefRaw = ($tokureiBaseAfterRate > 0.0 && $tokureiPrefRate > 0.0)
                ? ($tokureiBaseAfterRate * $tokureiPrefRate)
                : 0.0;
            $tokureiMuniRaw = ($tokureiBaseAfterRate > 0.0 && $tokureiMuniRate > 0.0)
                ? ($tokureiBaseAfterRate * $tokureiMuniRate)
                : 0.0;
            // 表示用（上限適用前）：県市それぞれ切上げ
            $out[$tokureiPrefKey] = $this->ceilPositive($tokureiPrefRaw);
            $out[$tokureiMuniKey] = $this->ceilPositive($tokureiMuniRaw);

            // ▼ 20%上限：退職除外後の cap 母数（JuminTax 側の capbase_*）を用い、
            //   県民税・市民税それぞれで「所得割額×20%」を上限とする（按分後→県市別cap）
            $capbasePref = $this->n($payload[sprintf('choseigo_shotokuwari_capbase_pref_%s', $period)] ?? null);
            $capbaseMuni = $this->n($payload[sprintf('choseigo_shotokuwari_capbase_muni_%s', $period)] ?? null);

            $shotokuwari20PrefKey = sprintf('shotokuwari20_pref_%s', $period);
            $shotokuwari20MuniKey = sprintf('shotokuwari20_muni_%s', $period);

            $capPref = (int) floor(max($capbasePref, 0) * 0.2);
            $capMuni = (int) floor(max($capbaseMuni, 0) * 0.2);
            $shotokuwari20Pref = max($capPref, 0);
            $shotokuwari20Muni = max($capMuni, 0);

            $out[$shotokuwari20PrefKey] = $shotokuwari20Pref;
            $out[$shotokuwari20MuniKey] = $shotokuwari20Muni;

            $tokureiKojoJogenPrefKey = sprintf('tokurei_kojo_jogen_pref_%s', $period);
            $tokureiKojoJogenMuniKey = sprintf('tokurei_kojo_jogen_muni_%s', $period);

            // 上限適用：按分後（生値）に cap を当て、その後 県市それぞれ 1円未満切上げ
            $tokureiKojoJogenPref = $this->ceilPositive(min(max($tokureiPrefRaw, 0.0), (float) $shotokuwari20Pref));
            $tokureiKojoJogenMuni = $this->ceilPositive(min(max($tokureiMuniRaw, 0.0), (float) $shotokuwari20Muni));

            $out[$tokureiKojoJogenPrefKey] = $tokureiKojoJogenPref;
            $out[$tokureiKojoJogenMuniKey] = $tokureiKojoJogenMuni;

            $oneStop = $this->resolveFlag($settings, $payload, 'one_stop_flag', $period);
            $eligibleFurusato = max(($prefF + $muniF) - 2_000, 0);
            $humanAdjustedTaxable = $this->n($payload[sprintf('human_adjusted_taxable_%s', $period)] ?? null);
            $shinkokuRatio = $this->resolveShinkokutokureiRatio($shinkokuRateRows, $humanAdjustedTaxable);

            $shinkokuPrefKey = sprintf('shinkokutokurei_kojo_pref_%s', $period);
            $shinkokuMuniKey = sprintf('shinkokutokurei_kojo_muni_%s', $period);

            if ($oneStop) {
                /**
                 * 申告特例控除（ワンストップ）
                 *  - ratio_a は「所得税率×復興(= income_rate_with_recon)」の％（例: 20.42）
                 *  - よって 0.xx の比率にするため /100 が必要
                 *  - 控除の按分は「特例控除（県/市按分）」と同じ按分（jumin_master: 特例控除）を用いる
                 *
                 * 県: eligible_total * (ratio_a/100) * prefShare
                 * 市: eligible_total * (ratio_a/100) * muniShare
                 *
                 * ※ここで tokureiRateFinalRatio(=69.58%) は掛けない（=二重掛け防止）
                 */

                // ワンストップの申告特例控除も、県市それぞれ「所得割額×20%」を上限とする
                $upperPref = max((float) $shotokuwari20Pref, 0.0);
                $upperMuni = max((float) $shotokuwari20Muni, 0.0);

                // ふるさと納税（県+市）の合計から 2,000 円控除（1回だけ）
                $furusatoTotal = max(0, $prefF + $muniF);
                $eligibleFurusatoTotal = max($furusatoTotal - 2_000, 0);

                // ratio_a（例: 20.42）→比率(0.2042)
                $ratioA = ((float) ($shinkokuRatio['ratio_a'] ?? 0.0)) / 100.0;

                // 県/市按分（特例控除と同じ share）
                $prefShare = (float) $tokureiPrefRate; // non_specified:0.4 / specified:0.2
                $muniShare = (float) $tokureiMuniRate; // non_specified:0.6 / specified:0.8

                $rawPref = $eligibleFurusatoTotal * $ratioA * $prefShare;
                $rawMuni = $eligibleFurusatoTotal * $ratioA * $muniShare;

                // 上限（20%）適用→円未満切上げ
                $shinkokuPref = $this->ceilPositive(min(max($rawPref, 0.0), $upperPref));
                $shinkokuMuni = $this->ceilPositive(min(max($rawMuni, 0.0), $upperMuni));
            } else {
                $shinkokuPref = 0;
                $shinkokuMuni = 0;
            }

            $out[$shinkokuPrefKey] = $shinkokuPref;
            $out[$shinkokuMuniKey] = $shinkokuMuni;

            $kifukinPrefKey = sprintf('kifukin_zeigaku_kojo_pref_%s', $period);
            $kifukinMuniKey = sprintf('kifukin_zeigaku_kojo_muni_%s', $period);
            $kifukinGokeiKey = sprintf('kifukin_zeigaku_kojo_gokei_%s', $period);

            $kihonPref = max($out[$kihonPrefKey], 0);
            $kihonMuni = max($out[$kihonMuniKey], 0);

            // ワンストップ特例:
            // 住民税側は「基本控除 + 特例控除 + 申告特例控除」を合算する
            // （申告しないため所得税側に出ない“所得税相当分”が申告特例控除として上乗せされる）
            $prefAddition = $oneStop ? ($tokureiKojoJogenPref + $shinkokuPref) : $tokureiKojoJogenPref;
            $muniAddition = $oneStop ? ($tokureiKojoJogenMuni + $shinkokuMuni) : $tokureiKojoJogenMuni;

            // 県市とも各パーツが既に整数（切上げ済み）なので、合計は単純加算でOK
            $prefTotal = max(0, (int) ($kihonPref + $prefAddition));
            $muniTotal = max(0, (int) ($kihonMuni + $muniAddition));
            $gokeiTotal = $prefTotal + $muniTotal;

            $out[$kifukinPrefKey] = $prefTotal;
            $out[$kifukinMuniKey] = $muniTotal;
            $out[$kifukinGokeiKey] = $gokeiTotal;
        }

        return array_replace($payload, $out);
    }

    /**
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
     * @return array<int, array{lower: int, upper: int|null, ratio_a: float, ratio_b: float}>
     */
    private function buildShinkokutokureiRateRows(int $year, ?int $companyId): array
    {
        $collection = $this->masterProvider->getShinkokutokureiRates($year, $companyId);

        $rows = [];
        foreach ($collection as $row) {
            $rows[] = [
                'lower' => isset($row->lower) ? (int) $row->lower : 0,
                'upper' => isset($row->upper) && $row->upper !== null ? (int) $row->upper : null,
                'ratio_a' => isset($row->ratio_a) ? (float) $row->ratio_a : 0.0,
                'ratio_b' => isset($row->ratio_b) ? (float) $row->ratio_b : 0.0,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{lower: int, upper: int|null, ratio_a: float, ratio_b: float}>  $rows
     * @return array{ratio_a: float, ratio_b: float}
     */
    private function resolveShinkokutokureiRatio(array $rows, int $taxable): array
    {
        foreach ($rows as $row) {
            $lower = (int) ($row['lower'] ?? 0);
            $upper = $row['upper'] ?? null;

            if ($taxable < $lower) {
                continue;
            }

            if ($upper !== null && $taxable > $upper) {
                continue;
            }

            return [
                'ratio_a' => isset($row['ratio_a']) ? (float) $row['ratio_a'] : 0.0,
                'ratio_b' => isset($row['ratio_b']) ? (float) $row['ratio_b'] : 0.0,
            ];
        }

        return ['ratio_a' => 0.0, 'ratio_b' => 0.0];
    }

    private function resolveAppliedRate(array $settings, array $payload, string $prefix, string $period): float
    {
        $keys = [
            sprintf('%s_applied_rate_%s', $prefix, $period),
            sprintf('%s_applied_rate', $prefix),
        ];

        foreach ($keys as $key) {
            if (array_key_exists($key, $settings)) {
                return $this->decimal($settings[$key]);
            }
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return $this->decimal($payload[$key]);
            }
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

    /**
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

    private function applyThousandRate(int $amount, float $rate): int
    {
        if ($amount <= 0 || $rate <= 0.0) {
            return 0;
        }

        $scaled = (int) max(round($rate * 1000), 0);

        if ($scaled === 0) {
            return 0;
        }

        return intdiv($amount * $scaled, 1000);
    }

    private function ceilPositive(float $value): int
    {
        return (int) ceil(max($value, 0.0));
    }

    private function mulRate(int $amount, float $rate): int
    {
        if ($rate === 0.0 || $amount === 0) {
            return 0;
        }

        return (int) floor($amount * $rate);
    }

    private function n(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        if (is_numeric($value)) {
            return (int) floor((float) $value);
        }

        return 0;
    }

    private function decimal(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function floorToThousands(int $value): int
    {
        if ($value >= 0) {
            return (int) (floor($value / 1000) * 1000);
        }

        $abs = abs($value);
        $thousand = (int) (ceil($abs / 1000) * 1000);

        return -$thousand;
    }
}