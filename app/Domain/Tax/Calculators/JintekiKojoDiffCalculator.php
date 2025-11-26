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
        unset($ctx);

        $out = [
            'human_diff_sum_prev' => 0,
            'human_diff_sum_curr' => 0,
        ];

        foreach (self::PERIODS as $period) {
            $sum = 0;

            // 基礎控除差：shotokuzei_kojo_kiso_* − juminzei_kojo_kiso_*
            $sum += $this->diff(
                $payload,
                "shotokuzei_kojo_kiso_{$period}",
                "juminzei_kojo_kiso_{$period}"
            );

            // 扶養控除差：kojo_fuyo_shotoku_* − kojo_fuyo_jumin_*
            $sum += $this->diff(
                $payload,
                "kojo_fuyo_shotoku_{$period}",
                "kojo_fuyo_jumin_{$period}"
            );

            // 障害者控除差：kojo_shogaisyo_shotoku_* − kojo_shogaisyo_jumin_*
            $sum += $this->diff(
                $payload,
                "kojo_shogaisyo_shotoku_{$period}",
                "kojo_shogaisyo_jumin_{$period}"
            );

            // 寡婦控除差：kojo_kafu_shotoku_* − kojo_kafu_jumin_*
            $sum += $this->diff(
                $payload,
                "kojo_kafu_shotoku_{$period}",
                "kojo_kafu_jumin_{$period}"
            );

            // ひとり親控除差：kojo_hitorioya_shotoku_* − kojo_hitorioya_jumin_*
            $sum += $this->diff(
                $payload,
                "kojo_hitorioya_shotoku_{$period}",
                "kojo_hitorioya_jumin_{$period}"
            );

            // 勤労学生控除差：kojo_kinrogakusei_shotoku_* − kojo_kinrogakusei_jumin_*
            $sum += $this->diff(
                $payload,
                "kojo_kinrogakusei_shotoku_{$period}",
                "kojo_kinrogakusei_jumin_{$period}"
            );

            // 特定親族控除（所得税のみ）：kojo_tokutei_shinzoku_shotoku_* − kojo_tokutei_shinzoku_jumin_*
            $sum += $this->diff(
                $payload,
                "kojo_tokutei_shinzoku_shotoku_{$period}",
                "kojo_tokutei_shinzoku_jumin_{$period}"
            );

            // 配偶者控除・配偶者特別控除の差分（HaigushaKojoCalculator）
            // キー名を固定せず、kojo_haigusha*_shotoku/jumin_* を包括的に拾う。
            $sum += $this->spouseDiff($payload, $period);

            $out["human_diff_sum_{$period}"] = max(0, $sum);
        }

        return array_replace($payload, $out);
    }

    /**
     * 単一ペアの差分（所得税−住民税）を取得。
     */
    private function diff(array $payload, string $shotokuKey, string $juminKey): int
    {
        $shotoku = $this->n($payload[$shotokuKey] ?? null);
        $jumin   = $this->n($payload[$juminKey] ?? null);

        $d = $shotoku - $jumin;

        // 人的控除差としては負の値は持たず 0 下限に揃える
        return $d > 0 ? $d : 0;
    }

    /**
     * 配偶者控除・配偶者特別控除系の差分。
     *
     * キー名を固定せず、'kojo_haigusha' で始まり
     * '_shotoku_{period}' / '_jumin_{period}' で終わるものを自動集計する。
     */
    private function spouseDiff(array $payload, string $period): int
    {
        $shotokuSum = 0;
        $juminSum   = 0;
        $suffixShotoku = "_shotoku_{$period}";
        $suffixJumin   = "_jumin_{$period}";

        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (str_starts_with($key, 'kojo_haigusha')) {
                if (str_ends_with($key, $suffixShotoku)) {
                        $shotokuSum += $this->n($value);
                } elseif (str_ends_with($key, $suffixJumin)) {
                    $juminSum += $this->n($value);
                }
            }
        }

        $d = $shotokuSum - $juminSum;

        return $d > 0 ? $d : 0;
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
