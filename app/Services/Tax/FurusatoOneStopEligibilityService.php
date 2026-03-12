<?php

namespace App\Services\Tax;

final class FurusatoOneStopEligibilityService
{
    public const BLOCK_MESSAGE = "給与所得者であっても、次のいずれかに当てはまるなど一定の条件を満たす人は、確定申告が必要です。\n"
        . "1．給与の年間収入金額が2,000万円を超える人\n"
        . "2．給与所得、退職所得を除く、その他の所得金額の合計額が20万円を超える人\n"
        . "現在の入力内容ではワンストップ特例の対象外となる可能性があるため、上限額の再計算および帳票出力はできません。処理メニューに戻り、ワンストップ特例を「利用しない」に変更してください。";
    public const DATA_MISSING_MESSAGE = '判定に必要なデータが不足しているため、PDF出力を実行できません。再計算後に再度お試しください。';

    /**
     * evaluate() と同じ思想で、概念ごとに候補キーのいずれかが存在すれば必須要件を満たす。
     * @var array<string,array<int,string>>
     */
    public const REQUIRED_KEY_CANDIDATES = [
        'salary_income' => ['salary_income_curr', 'syunyu_kyuyo_curr', 'kyuyo_syunyu_curr'],
        'total_income' => ['sum_for_gokeishotoku_curr', 'shotoku_gokei_curr'],
        'salary_shotoku' => ['shotoku_kyuyo_shotoku_curr'],
        // 退職所得は存在しない通常ケースがあり得るため required にはしない（未存在時は evaluate() 側で 0 扱い）
        'human_adjusted_taxable' => ['human_adjusted_taxable_curr'],
    ];

    /**
     * PDF出力ガード用の業務簡略判定。
     * - No.1900 を簡略化した業務判定であり、法令の完全再現ではない。
     * - 最終的な税務判断は別途必要。
     * - 本判定は PDF出力時の誤案内防止ガードのみを目的に使用する。
     *
     * @param  array<string,mixed>  $payload フラットな計算SoT配列のみを受け取る（ラッパ配列は不可）
     * @param  array<string,mixed>  $syoriSettings SyoriSettingsFactory::buildInitial($data) 由来を想定
     * @return array{is_blocked:bool,reasons:array<string,bool>,values:array<string,int>,one_stop_enabled:bool}
     */
    public function evaluate(array $payload, array $syoriSettings): array
    {
        $oneStopFlag = (int) ($syoriSettings['one_stop_flag_curr'] ?? $syoriSettings['one_stop_flag'] ?? 0);
        $oneStopEnabled = $oneStopFlag === 1;

        $salaryIncomeCurr = $this->valueByKeys($payload, [
            'salary_income_curr',
            'syunyu_kyuyo_curr',
            'kyuyo_syunyu_curr',
        ]);

        $totalIncomeCurr = $this->valueByKeys($payload, [
            'sum_for_gokeishotoku_curr',
            'shotoku_gokei_curr',
        ]);
        $salaryShotokuCurr = $this->valueByKeys($payload, ['shotoku_kyuyo_shotoku_curr']);
        $retirementShotokuCurr = $this->valueByKeys($payload, [
            'shotoku_taishoku_curr',
            'bunri_shotoku_taishoku_shotoku_curr',
        ]);

        // No.1900簡略判定用の業務ロジック（給与所得・退職所得を除く各種所得金額の合計）
        $otherIncomeCurr = max(0, $totalIncomeCurr - max(0, $salaryShotokuCurr) - max(0, $retirementShotokuCurr));

        $humanAdjustedTaxableCurr = $this->valueByKeys($payload, [
            'human_adjusted_taxable_curr',
        ]);

        $reasons = [
            'salary_over_20m' => $salaryIncomeCurr > 20_000_000,
            'other_income_over_200k' => $otherIncomeCurr > 200_000,
            'resident_taxable_minus_human_diff_over_18m' => $humanAdjustedTaxableCurr > 18_000_000,
        ];

        $isBlocked = $oneStopEnabled && in_array(true, $reasons, true);

        return [
            'is_blocked' => $isBlocked,
            'reasons' => $reasons,
            'values' => [
                'salary_income_curr' => $salaryIncomeCurr,
                'other_income_curr' => $otherIncomeCurr,
                'human_adjusted_taxable_curr' => $humanAdjustedTaxableCurr,
            ],
            'one_stop_enabled' => $oneStopEnabled,
        ];
    }


    /** @param array<string,mixed> $payload */
    public function hasRequiredKeys(array $payload): bool
    {
        foreach (self::REQUIRED_KEY_CANDIDATES as $keys) {
            $found = false;
            foreach ($keys as $key) {
                if (array_key_exists($key, $payload)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $payload @param array<int,string> $keys */
    private function valueByKeys(array $payload, array $keys): int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            return $this->toInt($payload[$key]);
        }

        return 0;
    }

    private function toInt(mixed $value): int
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