<?php

namespace App\Domain\Tax\Calculators;

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
            // ▼ 合計課税所得金額（住民税）
            //    1) まず JuminTaxCalculator が算出した jumin_kazeishotoku_total_* を優先
            //    2) 無ければ tb_sogo_jumin + tb_sanrin_jumin + tb_taishoku_jumin から算出
            $kazeisoushotoku = $this->n(
                $payload[sprintf('jumin_kazeishotoku_total_%s', $period)] ?? null
            );

            if ($kazeisoushotoku <= 0) {
                $sogo     = $this->n($payload[sprintf('tb_sogo_jumin_%s',   $period)] ?? null);
                $sanrin   = $this->n($payload[sprintf('tb_sanrin_jumin_%s', $period)] ?? null);
                $taishoku = $this->n($payload[sprintf('tb_taishoku_jumin_%s', $period)] ?? null);

                $kazeisoushotoku = max(0, $sogo) + max(0, $sanrin) + max(0, $taishoku);
            }

            $out["kazeisoushotoku_{$period}"] = $kazeisoushotoku;

            $categories = ['furusato', 'kyodobokin_nisseki', 'npo', 'koueki', 'sonota'];
            $sumKifu = 0;
            foreach ($categories as $category) {
                $pref = $this->n($payload["juminzei_zeigakukojo_pref_{$category}_{$period}"] ?? null);
                $muni = $this->n($payload["juminzei_zeigakukojo_muni_{$category}_{$period}"] ?? null);
                $both = $pref + $muni;

                if ($both === 0) {
                    $legacy = $this->n($payload["juminzei_zeigakukojo_{$category}_{$period}"] ?? null);
                    $sumKifu += $legacy;
                } else {
                    $sumKifu += $both;
                }
            }
            $out["kifu_gaku_{$period}"] = $sumKifu;

            $prefF = $this->n($payload["juminzei_zeigakukojo_pref_furusato_{$period}"] ?? null);
            $muniF = $this->n($payload["juminzei_zeigakukojo_muni_furusato_{$period}"] ?? null);
            $splitFurusato = $prefF + $muniF;

            if ($splitFurusato === 0) {
                $legacyF = $this->n($payload["juminzei_zeigakukojo_furusato_{$period}"] ?? null);
                $out["furusato_kifu_gaku_{$period}"] = $legacyF;
            } else {
                $out["furusato_kifu_gaku_{$period}"] = $splitFurusato;
            }

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

            // ▼ 30%ガード：所得税側の「総所得金額等」を母数にする
            $eligibleKifu = 0;
            if ($out[sprintf('kifu_gaku_%s', $period)] > 2_000) {
                $incomeTotal = $this->shotokuTaxableTotal($payload, $period);
                $guardedCap  = $this->mulRate($incomeTotal, 0.3);
                $limit       = min($out[sprintf('kifu_gaku_%s', $period)], $guardedCap);
                $eligibleKifu = max($limit - 2_000, 0);
            }

            $out[$kihonPrefKey] = $this->mulRate($eligibleKifu, $kihonPrefRate);
            $out[$kihonMuniKey] = $this->mulRate($eligibleKifu, $kihonMuniRate);

            $tokureiPrefKey = sprintf('tokurei_kojo_pref_%s', $period);
            $tokureiMuniKey = sprintf('tokurei_kojo_muni_%s', $period);

            $tokureiBase = 0;
            if ($out[sprintf('furusato_kifu_gaku_%s', $period)] > 2_000) {
                $tokureiBase = $out[sprintf('furusato_kifu_gaku_%s', $period)] - 2_000;
            }

            $tokureiRateFinalPercent = $this->decimal($payload[sprintf('tokurei_rate_final_%s', $period)] ?? null);
            $tokureiRateFinalRatio = $tokureiRateFinalPercent > 0.0
                ? $tokureiRateFinalPercent / 100.0
                : 0.0;
            $tokureiPrefRate = $this->juminRate($rateRows, '特例控除', null, $shitei, 'pref');
            $tokureiMuniRate = $this->juminRate($rateRows, '特例控除', null, $shitei, 'city');

            $tokureiBaseAfterRate = 0;
            if ($tokureiBase > 0 && $tokureiRateFinalPercent > 0.0) {
                $basisPoints = (int) max(round($tokureiRateFinalPercent * 100), 0);

                if ($basisPoints > 0) {
                    $tokureiBaseAfterRate = intdiv($tokureiBase * $basisPoints, 10_000);
                }
            }

            $out[$tokureiPrefKey] = $tokureiBaseAfterRate > 0 && $tokureiPrefRate > 0.0
                ? $this->applyThousandRate($tokureiBaseAfterRate, $tokureiPrefRate)
                : 0;

            $out[$tokureiMuniKey] = $tokureiBaseAfterRate > 0 && $tokureiMuniRate > 0.0
                ? $this->applyThousandRate($tokureiBaseAfterRate, $tokureiMuniRate)
                : 0;

            // ▼ 20%上限：退職除外後の cap 母数（JuminTax 側の capbase_*）を使って合計20%を算出し、pref/muniに按分
            $capbasePref = $this->n($payload[sprintf('choseigo_shotokuwari_capbase_pref_%s', $period)] ?? null);
            $capbaseMuni = $this->n($payload[sprintf('choseigo_shotokuwari_capbase_muni_%s', $period)] ?? null);
            $capBaseSum  = $capbasePref + $capbaseMuni;

            $shotokuwari20PrefKey = sprintf('shotokuwari20_pref_%s', $period);
            $shotokuwari20MuniKey = sprintf('shotokuwari20_muni_%s', $period);

            if ($capBaseSum > 0) {
                $capTotal = (int) floor($capBaseSum * 0.2);
                // 案A：pref/muni への按分は capbase の比率で行う
                $prefRatio = $capbasePref / $capBaseSum;
                $capPref   = (int) floor($capTotal * $prefRatio);
                $capMuni   = $capTotal - $capPref;
            } else {
                $capPref = 0;
                $capMuni = 0;
            }

            $shotokuwari20Pref = $capPref;
            $shotokuwari20Muni = $capMuni;

            $out[$shotokuwari20PrefKey] = $shotokuwari20Pref;
            $out[$shotokuwari20MuniKey] = $shotokuwari20Muni;

            $tokureiKojoJogenPrefKey = sprintf('tokurei_kojo_jogen_pref_%s', $period);
            $tokureiKojoJogenMuniKey = sprintf('tokurei_kojo_jogen_muni_%s', $period);

            $tokureiKojoJogenPref = min(max($out[$tokureiPrefKey], 0), $shotokuwari20Pref);
            $tokureiKojoJogenMuni = min(max($out[$tokureiMuniKey], 0), $shotokuwari20Muni);

            $out[$tokureiKojoJogenPrefKey] = $tokureiKojoJogenPref;
            $out[$tokureiKojoJogenMuniKey] = $tokureiKojoJogenMuni;

            $oneStop = $this->resolveFlag($settings, $payload, 'one_stop_flag', $period);
            $eligibleFurusato = max($out[sprintf('furusato_kifu_gaku_%s', $period)] - 2_000, 0);
            $humanAdjustedTaxable = $this->n($payload[sprintf('human_adjusted_taxable_%s', $period)] ?? null);
            $shinkokuRatio = $this->resolveShinkokutokureiRatio($shinkokuRateRows, $humanAdjustedTaxable);

            $shinkokuPrefKey = sprintf('shinkokutokurei_kojo_pref_%s', $period);
            $shinkokuMuniKey = sprintf('shinkokutokurei_kojo_muni_%s', $period);

            if ($oneStop) {
                if ($shitei) {
                    $prefShare = 0.2;
                    $muniShare = 0.8;
                } else {
                    $prefShare = 0.4;
                    $muniShare = 0.6;
                }

                $upperPref = max($out[$prefAfterKey] * 0.2, 0.0);
                $upperMuni = max($out[$muniAfterKey] * 0.2, 0.0);

                $tmpPref = min($eligibleFurusato * $tokureiRateFinalRatio * $shinkokuRatio['ratio_a'], $upperPref);
                $tmpMuni = min($eligibleFurusato * $tokureiRateFinalRatio * $shinkokuRatio['ratio_b'], $upperMuni);

                $shinkokuPref = $this->ceilPositive($tmpPref * $prefShare);
                $shinkokuMuni = $this->ceilPositive($tmpMuni * $muniShare);
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

            $prefAddition = $oneStop ? $shinkokuPref : $tokureiKojoJogenPref;
            $muniAddition = $oneStop ? $shinkokuMuni : $tokureiKojoJogenMuni;

            $prefTotal = $this->ceilPositive($kihonPref + $prefAddition);
            $muniTotal = $this->ceilPositive($kihonMuni + $muniAddition);
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

    /**
     * 寄附金30%ガード用の「総所得金額等」。
     * rtax_taxable_total_* があればそれを優先し、無ければ tb_*_shotoku の合計から算出。
     */
    private function shotokuTaxableTotal(array $payload, string $period): int
    {
        $key = sprintf('rtax_taxable_total_%s', $period);
        $direct = $this->n($payload[$key] ?? null);
        if ($direct > 0) {
            return $direct;
        }

        $sogo     = max(0, $this->n($payload[sprintf('tb_sogo_shotoku_%s',     $period)] ?? null));
        $sanrin   = max(0, $this->n($payload[sprintf('tb_sanrin_shotoku_%s',   $period)] ?? null));
        $taishoku = max(0, $this->n($payload[sprintf('tb_taishoku_shotoku_%s', $period)] ?? null));

        return $sogo + $sanrin + $taishoku;
    }
}