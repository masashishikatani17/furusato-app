<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

/**
 * 人的控除額の差の合計額（所得税 − 住民税）を算出する Calculator。
 *
 * 対象：
 *  - 基礎控除（KisoKojoCalculator）
 *  - 扶養控除・障害者控除・寡婦／ひとり親控除・勤労学生控除・特定親族控除（JintekiKojoCalculator）
 *  - 配偶者控除・配偶者特別控除（HaigushaKojoCalculator）
 *
 * 出力：
 *  - human_diff_sum_prev
 *  - human_diff_sum_curr
 *
 * この値は
 *  - 調整控除額の算定
 *  - TokureiRateCalculator における K（合計課税所得金額 − 人的控除差調整額）
 * の両方で利用する前提。
 */
class JintekiKojoDiffCalculator implements ProvidesKeys
{
    public const ID    = 'kojo.jinteki.diff';
    // フェーズC：人的控除各種（Jinteki/Haigusha/Kiso）確定後 → 集約前（KojoAggregation）の間
    public const ORDER = 3250;
    public const ANCHOR = 'deductions';

    // 合計所得金額・人的控除各種が確定していること
    public const AFTER = [
        CommonSumsCalculator::ID,
        JintekiKojoCalculator::ID,
        HaigushaKojoCalculator::ID,
        KisoKojoCalculator::ID,
    ];

    // 所得控除合計・課税標準・住民税・特例率などより前に実行
    public const BEFORE = [
        KojoAggregationCalculator::ID,
        CommonTaxableBaseCalculator::ID,
        JuminTaxCalculator::ID,
        TokureiRateCalculator::ID,
        JuminzeiKifukinCalculator::ID,
    ];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int,string>
     */
    public static function provides(): array
    {
        return [
            'human_diff_sum_prev',
            'human_diff_sum_curr',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $ctx
     * @return array<string,mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $out = [
            'human_diff_sum_prev' => 0,
            'human_diff_sum_curr' => 0,
        ];

        foreach (self::PERIODS as $period) {
            $sumGokei = $this->n($payload["sum_for_gokeishotoku_{$period}"] ?? null);
            if ($sumGokei > 25_000_000) {
                // 地方税法314条の6（調整控除）：合計所得金額が2,500万円超は対象外
                $out["human_diff_sum_{$period}"] = 0;
                continue;
            }

            $sum = 0;

            // 1) 基礎控除差（原則5万円：静岡市の差額表/地方税法314条の6の整理）
            $sum += 50_000;

            // 2) 障害者控除差（人数×差額）
            $sum += $this->n($payload["kojo_shogaisha_count_{$period}"] ?? null) * 10_000;          // 普通：1万円
            $sum += $this->n($payload["kojo_tokubetsu_shogaisha_count_{$period}"] ?? null) * 100_000; // 特別：10万円
            $sum += $this->n($payload["kojo_doukyo_tokubetsu_shogaisha_count_{$period}"] ?? null) * 220_000; // 同居特別：22万円

            // 3) 寡婦／ひとり親（合計所得500万円以下の前提）
            if ($sumGokei <= 5_000_000) {
                $kafu = ($payload["kojo_kafu_applicable_{$period}"] ?? null) === '〇';
                $hitoVal = (string) ($payload["kojo_hitorioya_applicable_{$period}"] ?? '');
                $hitoOn  = in_array($hitoVal, ['父', '母', '〇'], true);

                if ($hitoOn) {
                    // ひとり親：母=5万円、父=1万円
                    // - UIは「父」「母」「×」の3択
                    // - 互換として旧データの「〇」は「母」扱い
                    $sum += ($hitoVal === '父') ? 10_000 : 50_000;
                } elseif ($kafu) {
                    // 寡婦：1万円
                    $sum += 10_000;
                }
            }

            // 4) 勤労学生（1万円：所得要件は入力側/別Calculatorで担保）
            if (($payload["kojo_kinrogakusei_applicable_{$period}"] ?? null) === '〇') {
                $sum += 10_000;
            }

            // 5) 扶養控除（人数×差額）
            $sum += $this->n($payload["kojo_fuyo_ippan_count_{$period}"] ?? null) * 50_000;             // 一般：5万円
            $sum += $this->n($payload["kojo_fuyo_tokutei_count_{$period}"] ?? null) * 180_000;           // 特定：18万円
            $sum += $this->n($payload["kojo_fuyo_roujin_sonota_count_{$period}"] ?? null) * 100_000;     // 老人(その他)：10万円
            $sum += $this->n($payload["kojo_fuyo_roujin_doukyo_count_{$period}"] ?? null) * 130_000;     // 同居老親等：13万円

            // 6) 配偶者控除・配偶者特別控除（差額表ベース）
            $sum += $this->spouseHumanDiffByTable($payload, $ctx, $period, $sumGokei);

            // 7) 特定親族特別控除は「調整控除の人的控除差」には含めない（差額表対象外）
            //    ※控除額（所得控除）としては JintekiKojoCalculator で別途計算・課税所得に反映する。

            $out["human_diff_sum_{$period}"] = max(0, $sum);
        }

        return array_replace($payload, $out);
    }

    private function spouseHumanDiffByTable(array $payload, array $ctx, string $period, int $taxpayerIncome): int
    {
        // 本人が1,000万円超は配偶者控除/特別控除は対象外（差額も0）
        if ($taxpayerIncome > 10_000_000) {
            return 0;
        }

        $category = (string) ($payload["kojo_haigusha_category_{$period}"] ?? 'none'); // ippan/roujin/none
        $spouseIncome = $this->n($payload["kojo_haigusha_tokubetsu_gokeishotoku_{$period}"] ?? null);

        // 対象年（所得年）で分岐：2025年分（住民税令和8年度）以降は配偶者特別控除の“差”は生じない
        $targetYear = $this->targetIncomeYear($ctx, $period);

        $isElderly = ($category === 'roujin');
        $tier = $this->taxpayerTierForSpouse($taxpayerIncome); // 0/1/2（<=900 / <=950 / <=1000）
        if ($tier === null) {
            return 0;
        }

        // 配偶者控除の所得要件起算点：令和7年度分まで=48万円、令和8年度分以降=58万円
        $start = ($targetYear !== null && $targetYear >= 2025) ? 580_000 : 480_000;

        // A) 配偶者控除（配偶者所得が start 以下）
        if ($spouseIncome > 0 && $spouseIncome <= $start && $category !== 'none') {
            // 一般：5/4/2、老人：10/6/3（万円）
            $table = $isElderly
                ? [100_000, 60_000, 30_000]
                : [50_000, 40_000, 20_000];
            return $table[$tier];
        }

        // B) 配偶者特別控除の“人的控除差”
        //    静岡市の整理：令和3〜7年度（所得年=2024まで）は 48万超〜55万未満の一部のみ差額あり、
        //    それ以外（55万以上）は差額0。令和8年度（所得年=2025）以降は差額0。
        if ($targetYear !== null && $targetYear >= 2025) {
            return 0;
        }

        if ($spouseIncome > 480_000 && $spouseIncome < 550_000) {
            if ($spouseIncome <= 500_000) {
                // 48万超〜50万以下：一般 5/4/2、老人 10/6/3
                $table = $isElderly
                    ? [100_000, 60_000, 30_000]
                    : [50_000, 40_000, 20_000];
                return $table[$tier];
            }
            // 50万超〜55万未満：一般 3/2/1、老人 6/4/2（万円）
            $table = $isElderly
                ? [60_000, 40_000, 20_000]
                : [30_000, 20_000, 10_000];
            return $table[$tier];
        }

        return 0;
    }

    private function taxpayerTierForSpouse(int $income): ?int
    {
        if ($income <= 9_000_000) return 0;
        if ($income <= 9_500_000) return 1;
        if ($income <= 10_000_000) return 2;
        return null;
    }

    private function targetIncomeYear(array $ctx, string $period): ?int
    {
        $kihuYear = isset($ctx['kihu_year']) ? (int) $ctx['kihu_year'] : null;
        if ($kihuYear === null || $kihuYear <= 0) {
            return null;
        }
        return $period === 'prev' ? ($kihuYear - 1) : $kihuYear;
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
}
