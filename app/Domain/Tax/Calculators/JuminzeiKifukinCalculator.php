<?php

namespace App\Domain\Tax\Calculators;

use App\Domain\Tax\Contracts\MasterProviderContract;
use App\Services\Tax\Contracts\ProvidesKeys;

final class JuminzeiKifukinCalculator implements ProvidesKeys
{
    public const ID    = 'kojo.kifukin.jumin';
    public const ORDER = 4050;
    public const BEFORE = [];
    public const AFTER  = [
        \App\Domain\Tax\Calculators\KojoAggregationCalculator::ID,
        \App\Domain\Tax\Calculators\TokureiRateCalculator::ID,
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

        $rateRows = $year > 0
            ? $this->buildJuminRateRows($year, $companyId)
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
            $shotokuGokei = $this->n($payload["shotoku_gokei_shotoku_{$period}"] ?? null);
            $sanrin = $this->n($payload["bunri_shotoku_sanrin_shotoku_{$period}"] ?? null);
            $taishoku = $this->n($payload["bunri_shotoku_taishoku_shotoku_{$period}"] ?? null);
            $kojoJumin = $this->n($payload["kojo_gokei_jumin_{$period}"] ?? null);

            $tmp = $shotokuGokei + $sanrin + $taishoku - $kojoJumin;
            $tmpFloorThousand = $this->floorToThousands($tmp);
            $kazeisoushotoku = max(0, $tmpFloorThousand);
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

            $out[sprintf('chosei_mae_shotokuwari_pref_%s', $period)] = $this->calculateChoseiMae(
                $rateRows,
                $payload,
                $period,
                $kazeisoushotoku,
                $prefApplied,
                $shitei,
                'pref'
            );

            $out[sprintf('chosei_mae_shotokuwari_muni_%s', $period)] = $this->calculateChoseiMae(
                $rateRows,
                $payload,
                $period,
                $kazeisoushotoku,
                $muniApplied,
                $shitei,
                'city'
            );

            $guardedTotal = $shotokuGokei + $sanrin + $taishoku;
            $baseTotal = 0;

            if ($guardedTotal <= 25_000_000 && $kazeisoushotoku > 0) {
                $humanDiffSum = $this->n($payload[sprintf('human_diff_sum_%s', $period)] ?? null);
                $humanDiffKiso = $this->n($payload[sprintf('human_diff_kiso_%s', $period)] ?? null);

                $baseValue = $humanDiffSum - $humanDiffKiso + 50_000;

                if ($kazeisoushotoku <= 2_000_000) {
                    $baseValue = max(min($baseValue, $kazeisoushotoku), 0);
                    $baseTotal = $this->mulRate($baseValue, 0.05);
                } else {
                    $baseValue = max($baseValue - ($kazeisoushotoku - 2_000_000), 0);
                    $baseTotal = max($this->mulRate($baseValue, 0.05), 2_500);
                }
            }

            $prefKey = sprintf('chosei_kojo_pref_%s', $period);
            $muniKey = sprintf('chosei_kojo_muni_%s', $period);

            if ($baseTotal === 0) {
                $prefKojo = 0;
                $muniKojo = 0;
            } else {
                $prefRatio = $shitei ? 0.2 : 0.4;
                $prefKojo = $this->mulRate($baseTotal, $prefRatio);
                $muniKojo = max($baseTotal - $prefKojo, 0);
            }

            $out[$prefKey] = $prefKojo;
            $out[$muniKey] = $muniKojo;

            $prefAfterKey = sprintf('choseigo_shotokuwari_pref_%s', $period);
            $muniAfterKey = sprintf('choseigo_shotokuwari_muni_%s', $period);

            $prefBefore = $out[sprintf('chosei_mae_shotokuwari_pref_%s', $period)] ?? 0;
            $muniBefore = $out[sprintf('chosei_mae_shotokuwari_muni_%s', $period)] ?? 0;

            $out[$prefAfterKey] = $prefBefore - $prefKojo;
            $out[$muniAfterKey] = $muniBefore - $muniKojo;

            $kihonPrefKey = sprintf('kihon_kojo_pref_%s', $period);
            $kihonMuniKey = sprintf('kihon_kojo_muni_%s', $period);

            $kihonPrefRate = $this->juminRate($rateRows, '基本控除', null, $shitei, 'pref');
            $kihonMuniRate = $this->juminRate($rateRows, '基本控除', null, $shitei, 'city');

            $eligibleKifu = 0;
            if ($out[sprintf('kifu_gaku_%s', $period)] > 2_000) {
                $guardedCap = $this->mulRate($guardedTotal, 0.3);
                $limit = min($out[sprintf('kifu_gaku_%s', $period)], $guardedCap);
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

            $prefAfter = max($out[$prefAfterKey], 0);
            $muniAfter = max($out[$muniAfterKey], 0);

            $shotokuwari20PrefKey = sprintf('shotokuwari20_pref_%s', $period);
            $shotokuwari20MuniKey = sprintf('shotokuwari20_muni_%s', $period);
            $shotokuwari20Pref = (int) floor($prefAfter * 0.2);
            $shotokuwari20Muni = (int) floor($muniAfter * 0.2);

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
    private function buildJuminRateRows(int $year, ?int $companyId): array
    {
        $collection = $this->masterProvider->getJuminRates($year, $companyId);

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
    private function calculateChoseiMae(
        array $rates,
        array $payload,
        string $period,
        int $kazeisoushotoku,
        float $baseRate,
        bool $shitei,
        string $target
    ): int {
        $total = $this->mulRate($kazeisoushotoku, $baseRate);

        $total += $this->mulRate(
            $this->n($payload[sprintf('bunri_shotoku_tanki_ippan_shotoku_%s', $period)] ?? null),
            $this->juminRate($rates, '短期譲渡', '一般', $shitei, $target)
        );

        $total += $this->mulRate(
            $this->n($payload[sprintf('bunri_shotoku_tanki_keigen_shotoku_%s', $period)] ?? null),
            $this->juminRate($rates, '短期譲渡', '軽減', $shitei, $target)
        );

        $total += $this->mulRate(
            $this->n($payload[sprintf('bunri_shotoku_choki_ippan_shotoku_%s', $period)] ?? null),
            $this->juminRate($rates, '長期譲渡', '一般', $shitei, $target)
        );

        $tokutei = $this->n($payload[sprintf('bunri_shotoku_choki_tokutei_shotoku_%s', $period)] ?? null);
        $total += $this->mulRate(
            min($tokutei, 20_000_000),
            $this->juminRate($rates, '長期譲渡', '特定', $shitei, $target, '以下')
        );
        $total += $this->mulRate(
            max($tokutei - 20_000_000, 0),
            $this->juminRate($rates, '長期譲渡', '特定', $shitei, $target, '超')
        );

        $keika = $this->n($payload[sprintf('bunri_shotoku_choki_keika_shotoku_%s', $period)] ?? null);
        $total += $this->mulRate(
            min($keika, 60_000_000),
            $this->juminRate($rates, '長期譲渡', '軽課', $shitei, $target, '以下')
        );
        $total += $this->mulRate(
            max($keika - 60_000_000, 0),
            $this->juminRate($rates, '長期譲渡', '軽課', $shitei, $target, '超')
        );

        $total += $this->mulRate(
            $this->n($payload[sprintf('bunri_shotoku_ippan_kabuteki_joto_shotoku_%s', $period)] ?? null),
            $this->juminRate($rates, '一般株式等の譲渡', null, $shitei, $target)
        );

        $total += $this->mulRate(
            $this->n($payload[sprintf('bunri_shotoku_jojo_kabuteki_joto_shotoku_%s', $period)] ?? null),
            $this->juminRate($rates, '上場株式等の譲渡', null, $shitei, $target)
        );

        $total += $this->mulRate(
            $this->n($payload[sprintf('bunri_shotoku_jojo_kabuteki_haito_shotoku_%s', $period)] ?? null),
            $this->juminRate($rates, '上場株式等の配当等', null, $shitei, $target)
        );

        $total += $this->mulRate(
            $this->n($payload[sprintf('bunri_shotoku_sakimono_shotoku_%s', $period)] ?? null),
            $this->juminRate($rates, '先物取引', null, $shitei, $target)
        );

        $total += $this->mulRate(
            $this->n($payload[sprintf('bunri_shotoku_sanrin_shotoku_%s', $period)] ?? null),
            $this->juminRate($rates, '山林', null, $shitei, $target)
        );

        $total += $this->mulRate(
            $this->n($payload[sprintf('bunri_shotoku_taishoku_shotoku_%s', $period)] ?? null),
            $this->juminRate($rates, '退職', null, $shitei, $target)
        );

        return $total;
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
        foreach ($rates as $rate) {
            if (($rate['category'] ?? '') !== $category) {
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

            if ($category === '特例控除' || $category === '基本控除') {
                return $numeric;
            }

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