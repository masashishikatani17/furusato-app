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
                $sumKifu += $this->n($payload["juminzei_zeigakukojo_{$category}_{$period}"] ?? null);
            }
            $out["kifu_gaku_{$period}"] = $sumKifu;

            $out["furusato_kifu_gaku_{$period}"] = $this->n($payload["juminzei_zeigakukojo_furusato_{$period}"] ?? null);

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

            $tokureiRateFinal = $this->decimal($payload[sprintf('tokurei_rate_final_%s', $period)] ?? null) / 100.0;
            $tokureiPrefRate = $this->juminRate($rateRows, '特例控除', null, $shitei, 'pref');
            $tokureiMuniRate = $this->juminRate($rateRows, '特例控除', null, $shitei, 'city');

            $out[$tokureiPrefKey] = $tokureiBase > 0 && $tokureiRateFinal > 0.0
                ? $this->mulRate($tokureiBase, $tokureiRateFinal * $tokureiPrefRate)
                : 0;

            $out[$tokureiMuniKey] = $tokureiBase > 0 && $tokureiRateFinal > 0.0
                ? $this->mulRate($tokureiBase, $tokureiRateFinal * $tokureiMuniRate)
                : 0;
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

            return ((float) $value) / 100.0;
        }

        return 0.0;
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