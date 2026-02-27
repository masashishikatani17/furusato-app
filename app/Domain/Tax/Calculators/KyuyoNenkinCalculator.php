<?php

namespace App\Domain\Tax\Calculators;

use App\Models\Data;
use App\Services\Tax\Contracts\ProvidesKeys;
use App\Domain\Tax\Calculators\Support\JotoIchijiNetting;
use App\Domain\Tax\Calculators\Support\NettingHelpers;
use DateTimeInterface;

class KyuyoNenkinCalculator implements ProvidesKeys
{
    public const ID = 'kyuyo.nenkin';
    // 【制度順】Sakimono／Bunri後に実行（OTPで年金雑を確定）。Sogo/Stagesより前
    public const ORDER = 1150;
    public const BEFORE = [];
    public const AFTER = [];

    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [];
        foreach (self::PERIODS as $period) {
            // 給与（所得）
            $keys[] = sprintf('shotoku_kyuyo_shotoku_%s', $period);
            // 住民税側キー命名を統一（shotoku_kyuyo_jumin_*）
            $keys[] = sprintf('shotoku_kyuyo_jumin_%s',   $period);
            // 雑・公的年金（所得）
            $keys[] = sprintf('shotoku_zatsu_nenkin_shotoku_%s', $period);
            $keys[] = sprintf('shotoku_zatsu_nenkin_jumin_%s',   $period);
            // 雑・業務（所得）
            $keys[] = sprintf('shotoku_zatsu_gyomu_shotoku_%s',  $period);
            $keys[] = sprintf('shotoku_zatsu_gyomu_jumin_%s',    $period);
            // 雑・その他（所得）
            $keys[] = sprintf('shotoku_zatsu_sonota_shotoku_%s', $period);
            $keys[] = sprintf('shotoku_zatsu_sonota_jumin_%s',   $period);
            // ▼ 出力：所得金額調整控除の算出額（可視化・検証用）
            // 子育て・介護（850万超）の調整額
            $keys[] = sprintf('kyuyo_chosei_childcare_shotoku_%s', $period);
            $keys[] = sprintf('kyuyo_chosei_childcare_jumin_%s',   $period);
            // 給与＋年金「双方有り」の調整額
            $keys[] = sprintf('kyuyo_chosei_both_shotoku_%s', $period);
            $keys[] = sprintf('kyuyo_chosei_both_jumin_%s',   $period);
        }
        return $keys;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, int>
     */
    public function compute(array $payload, array $ctx): array
    {
        $updates = array_fill_keys(self::provides(), 0);
        $working = $payload;
        $birthDate = $this->resolveBirthDate($ctx);

        foreach (self::PERIODS as $period) {
            $year = $this->resolveYear($ctx['kihu_year'] ?? null, $period);

            // ▼ 給与：detailsの「kyuyo_syunyu_*」を収入として読み、給与所得を算出
            $kyuyoIncomeKey = sprintf('kyuyo_syunyu_%s', $period);
            $kyuyoIncome    = $this->clampIncome($payload[$kyuyoIncomeKey] ?? null);
            $kyuyoAmount = $this->calculateKyuyoShotoku($kyuyoIncome, $year);

            $shotokuKyuyoKey = sprintf('shotoku_kyuyo_shotoku_%s', $period);
            $juminKyuyoKey  = sprintf('shotoku_kyuyo_jumin_%s', $period);
            $updates[$shotokuKyuyoKey] = $kyuyoAmount;
            $updates[$juminKyuyoKey] = $kyuyoAmount;
            $working[$shotokuKyuyoKey] = $kyuyoAmount;
            $working[$juminKyuyoKey] = $kyuyoAmount;

            // ▼ 雑（公的年金等）：収入のみ保持（所得＝収入−控除はこの後の OTP で最終確定）
            $nenkinIncomeKey = sprintf('zatsu_nenkin_syunyu_%s', $period);
            $nenkinIncome    = $this->clampIncome($payload[$nenkinIncomeKey] ?? null);

            // （年金の所得確定は本ブロック末尾：影通算→OTP→控除表）

            // ▼ 雑（業務）：所得＝max(0, 収入−支払) を税目共通でミラー
            $gyomuInc = $this->clampIncome($payload[sprintf('zatsu_gyomu_syunyu_%s',   $period)] ?? null);
            $gyomuPay = $this->clampIncome($payload[sprintf('zatsu_gyomu_shiharai_%s', $period)] ?? null);
            $gyomuShotoku = max(0, $gyomuInc - $gyomuPay);
            $updates[sprintf('shotoku_zatsu_gyomu_shotoku_%s', $period)] = $gyomuShotoku;
            $updates[sprintf('shotoku_zatsu_gyomu_jumin_%s',   $period)] = $gyomuShotoku;
            $working[sprintf('shotoku_zatsu_gyomu_shotoku_%s', $period)] = $gyomuShotoku;
            $working[sprintf('shotoku_zatsu_gyomu_jumin_%s',   $period)] = $gyomuShotoku;

            // ▼ 雑（その他）：所得＝max(0, 収入−支払) を税目共通でミラー
            $sonotaInc = $this->clampIncome($payload[sprintf('zatsu_sonota_syunyu_%s',   $period)] ?? null);
            $sonotaPay = $this->clampIncome($payload[sprintf('zatsu_sonota_shiharai_%s', $period)] ?? null);
            $sonotaShotoku = max(0, $sonotaInc - $sonotaPay);
            $updates[sprintf('shotoku_zatsu_sonota_shotoku_%s', $period)] = $sonotaShotoku;
            $updates[sprintf('shotoku_zatsu_sonota_jumin_%s',   $period)] = $sonotaShotoku;
            $working[sprintf('shotoku_zatsu_sonota_shotoku_%s', $period)] = $sonotaShotoku;
            $working[sprintf('shotoku_zatsu_sonota_jumin_%s',   $period)] = $sonotaShotoku;

            // ===== ここから「影通算」→ OTP → 年金雑所得 =====
            // 1) 経常（年金除外）：v2の正値合算ルールに合わせて構築
            //    payload 由来の値にはカンマ付き文字列等が混ざるため、必ず n() で数値化してから合算する
            $econNoPension =
                  max(0, $this->n($working[sprintf('shotoku_kyuyo_shotoku_%s', $period)] ?? 0))
                + max(0, $this->n($working[sprintf('shotoku_jigyo_eigyo_shotoku_%s', $period)] ?? 0))
                + max(0, $this->n($working[sprintf('shotoku_jigyo_nogyo_shotoku_%s', $period)] ?? 0))
                + max(0, $this->n($working[sprintf('shotoku_fudosan_shotoku_%s', $period)] ?? 0))
                + max(0, $this->n($working[sprintf('shotoku_rishi_%s', $period)] ?? 0))
                + max(0, $this->n($working[sprintf('shotoku_haito_shotoku_%s', $period)] ?? 0))
                + max(0, $this->n($working[sprintf('shotoku_zatsu_gyomu_shotoku_%s', $period)] ?? 0))
                + max(0, $this->n($working[sprintf('shotoku_zatsu_sonota_shotoku_%s', $period)] ?? 0));
            // ※ shotoku_zatsu_nenkin_shotoku_* はここに含めない

            // 2) 短期・長期・一時（影）：Details／ResultToDetails にある差引ベースを使い JotoIchijiNetting を走らせる
            $ji = JotoIchijiNetting::compute(
                $this->n($payload[sprintf('sashihiki_joto_tanki_sogo_%s', $period)] ?? 0),
                $this->n($payload[sprintf('sashihiki_joto_choki_sogo_%s', $period)] ?? 0),
                $this->n($payload[sprintf('sashihiki_ichiji_%s',         $period)] ?? 0),
            );
            $short1  = (int)($ji['after_joto_ichiji_tousan_joto_tanki_sogo'] ?? 0);
            $long1   = (int)($ji['after_joto_ichiji_tousan_joto_choki_sogo'] ?? 0);
            $ichiji1 = (int)($ji['after_joto_ichiji_tousan_ichiji'] ?? 0);

            // 3) 山林の第1次（影）
            $forest1 = max(
                0,
                (int)floor((float)$this->n($payload[sprintf('sashihiki_sanrin_%s', $period)] ?? 0))
                - (int)floor((float)$this->n($payload[sprintf('tokubetsukojo_sanrin_%s', $period)] ?? 0))
            );

            // 4) 第2次通算（影）：forest→long→short→econ
            [$econ2, $short2, $long2, $forest2, $ichiji2] =
                NettingHelpers::netWithForest($econNoPension, $short1, $long1, $forest1, $ichiji1);

            // 5) 第3次通算（影）：退職（long→short→econ→forest）
            $retireInput = max(0, $this->n($payload[sprintf('bunri_shotoku_taishoku_shotoku_%s', $period)] ?? 0));
            [$econ3, $short3, $long3, $forest3, $ichiji3, $retire3] =
                NettingHelpers::netWithRetirement($econ2, $short2, $long2, $forest2, $ichiji2, $retireInput);

            // 6) 影の所得金額化（A’＋B）
            $aPrime =
                  max(0, $econ3)
                + max(0, $short3)
                + max(0, (int)floor($long3 / 2))
                + max(0, (int)floor($ichiji3 / 2));
            $bPart = max(0, $forest3) + max(0, $retire3);

            // 7) C_pre（Sakimono／Bunri 後：CommonSums v2 と同じ採り方）
            $cPre = 0;
            // 短・長 … after_2（= tsusango 相当）の正値合計
            foreach (['tanki_ippan','tanki_keigen','choki_ippan','choki_tokutei','choki_keika'] as $row) {
                $cPre += max(0, $this->n($payload[sprintf('after_2jitsusan_%s_%s', $row, $period)] ?? 0));
            }
            // 上場（R2D で tsusango_jojo_* にミラーされる元）… shotoku_after_kurikoshi_jojo_*
            $cPre += max(0, $this->n($payload[sprintf('shotoku_after_kurikoshi_jojo_joto_%s',  $period)] ?? 0));
            $cPre += max(0, $this->n($payload[sprintf('shotoku_after_kurikoshi_jojo_haito_%s', $period)] ?? 0));
            // 一般の譲渡（繰越控除無し＝pre相当）… shotoku_after_kurikoshi_ippan_joto_*
            $cPre += max(0, $this->n($payload[sprintf('shotoku_after_kurikoshi_ippan_joto_%s', $period)] ?? 0));
            // 先物（繰越前）… shotoku_sakimono_*
            $cPre += max(0, $this->n($payload[sprintf('shotoku_sakimono_%s', $period)] ?? 0));

            // 8) OTP（年金以外の合計）＝ A’ + B + C_pre
            $otp = $aPrime + $bPart + $cPre;

            // 9) 年金雑所得の確定（shotoku／jumin 同一ロジック）
            $isSenior = $this->isSenior($birthDate, $year);
            $shotokuNenkin = $this->calculateNenkinShotoku($nenkinIncome, $isSenior, $otp);
            $juminNenkin   = $this->calculateNenkinShotoku($nenkinIncome, $isSenior, $otp);

            $updates[sprintf('shotoku_zatsu_nenkin_shotoku_%s', $period)] = $shotokuNenkin;
            $updates[sprintf('shotoku_zatsu_nenkin_jumin_%s',   $period)] = $juminNenkin;
            $working[sprintf('shotoku_zatsu_nenkin_shotoku_%s', $period)] = $shotokuNenkin;
            $working[sprintf('shotoku_zatsu_nenkin_jumin_%s',   $period)] = $juminNenkin;

            // ===== 所得金額調整控除（制度上は“所得控除”）を給与所得へ適用 =====
            // 1) 子育て・介護世帯向け（年収850万超かつチェックON）
            $childcareFlag = $this->n($payload[sprintf('kyuyo_chosei_applicable_%s', $period)] ?? 0) === 1;
            $childcareAdj  = 0;
            if ($childcareFlag) {
                // { min(給与収入, 10,000,000) − 8,500,000 } × 10% （負→0）
                $cappedIncome = min($kyuyoIncome, 10_000_000);
                $excess = $cappedIncome - 8_500_000;
                if ($excess > 0) {
                    $childcareAdj = (int) floor($excess * 0.10);
                }
            }
            // 給与所得から控除（下限0）
            if ($childcareAdj > 0) {
                $newShotokuKyuyo = max(0, ($updates[$shotokuKyuyoKey] ?? 0) - $childcareAdj);
                $newJuminKyuyo   = max(0, ($updates[$juminKyuyoKey]   ?? 0) - $childcareAdj);
                $updates[$shotokuKyuyoKey] = $newShotokuKyuyo;
                $updates[$juminKyuyoKey]   = $newJuminKyuyo;
                $working[$shotokuKyuyoKey] = $newShotokuKyuyo;
                $working[$juminKyuyoKey]   = $newJuminKyuyo;
            }
            $updates[sprintf('kyuyo_chosei_childcare_shotoku_%s', $period)] = $childcareAdj;
            $updates[sprintf('kyuyo_chosei_childcare_jumin_%s',   $period)] = $childcareAdj;

            // 2) 給与＋年金“双方有り”の調整控除
            // 控除額 = max( min(給与所得,10万円) + min(年金雑所得,10万円) − 10万円 , 0 )
            $bothAdj = 0;
            $salaryForBoth = $updates[$shotokuKyuyoKey] ?? 0;      // 現在の給与“所得”金額
            $pensionForBoth = $updates[sprintf('shotoku_zatsu_nenkin_shotoku_%s', $period)] ?? 0; // 年金“所得”金額
            $bothAdjBase = min($salaryForBoth, 100_000) + min($pensionForBoth, 100_000) - 100_000;
            if ($bothAdjBase > 0) {
                $bothAdj = (int) $bothAdjBase;
            }
            if ($bothAdj > 0) {
                // 実務上は「所得金額調整控除」として合計所得から控除されますが、
                // 第一表整合のため、ここでは給与所得から控除（下限0）して出力します。
                $newShotokuKyuyo = max(0, ($updates[$shotokuKyuyoKey] ?? 0) - $bothAdj);
                $newJuminKyuyo   = max(0, ($updates[$juminKyuyoKey]   ?? 0) - $bothAdj);
                $updates[$shotokuKyuyoKey] = $newShotokuKyuyo;
                $updates[$juminKyuyoKey]   = $newJuminKyuyo;
                $working[$shotokuKyuyoKey] = $newShotokuKyuyo;
                $working[$juminKyuyoKey]   = $newJuminKyuyo;
            }
            $updates[sprintf('kyuyo_chosei_both_shotoku_%s', $period)] = $bothAdj;
            $updates[sprintf('kyuyo_chosei_both_jumin_%s',   $period)] = $bothAdj;
        }

        return array_replace($payload, $updates);
    }

    private function clampIncome(mixed $value): int
    {
        return max(0, $this->n($value));
    }

    private function n(mixed $value): int
    {
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (int) ((float) $value);
        }

        return 0;
    }

    private function calculateKyuyoShotoku(int $income, int $year): int
    {
        if ($income <= 0) {
            return 0;
        }

        if ($year <= 0) {
            $year = 2025;
        }

        if ($year < 2025) {
            if ($income <= 1_625_000) {
                return max(0, $income - 550_000);
            }

            if ($income <= 1_800_000) {
                return max(0, $income - ($this->percent($income, 40) - 100_000));
            }

            if ($income <= 3_600_000) {
                return max(0, $income - ($this->percent($income, 30) + 80_000));
            }

            if ($income <= 6_600_000) {
                return max(0, $income - ($this->percent($income, 20) + 440_000));
            }

            if ($income <= 8_500_000) {
                return max(0, $income - ($this->percent($income, 10) + 1_100_000));
            }

            return max(0, $income - 1_950_000);
        }

        // ------------------------------------------------------------
        // 令和7年分（2025年分）以降：国税庁「合計所得金額の計算について」
        //   1,900,000～3,599,999：B = A÷4（千円未満切捨て）→ A - (B×2.8 - 80,000)
        //   3,600,000～6,599,999：B = A÷4（千円未満切捨て）→ A - (B×3.2 - 440,000)
        //  ※ B の千円未満切捨てがポイント（例：6,251,004 → B=1,562,000）
        // ------------------------------------------------------------
        $b = function (int $a): int {
            // B = floor((A / 4) / 1000) * 1000  （千円未満切捨て）
            return intdiv(intdiv($a, 4), 1000) * 1000;
        };

        if ($income <= 1_900_000) {
            return max(0, $income - 650_000);
        }

        // 1,900,000 ～ 3,599,999
        if ($income <= 3_599_999) {
            $B = $b($income);
            // 給与所得金額 = B*2.8 - 80,000  （2.8 = 28/10）
            $shotoku = intdiv($B * 28, 10) - 80_000;
            return max(0, $shotoku);
        }

        // 3,600,000 ～ 6,599,999
        if ($income <= 6_599_999) {
            $B = $b($income);
            // 給与所得金額 = B*3.2 - 440,000 （3.2 = 32/10）
            $shotoku = intdiv($B * 32, 10) - 440_000;
            return max(0, $shotoku);
        }

        if ($income <= 8_500_000) {
            return max(0, $income - ($this->percent($income, 10) + 1_100_000));
        }

        return max(0, $income - 1_950_000);
    }

    private function percent(int $value, int $rate): int
    {
        return intdiv($value * $rate, 100);
    }

    private function calculateNenkinShotoku(int $income, bool $isSenior, int $otherSum): int
    {
        if ($income <= 0) {
            return 0;
        }

        $deduction = $this->calculateNenkinDeduction($income, $isSenior, $otherSum);
        $result = $income - $deduction;

        return $result > 0 ? $result : 0;
    }

    private function calculateNenkinDeduction(int $income, bool $isSenior, int $otherSum): int
    {
        $bucket = 0;
        if ($otherSum > 20_000_000) {
            $bucket = 2;
        } elseif ($otherSum > 10_000_000) {
            $bucket = 1;
        }

        return $isSenior
            ? $this->calculateSeniorDeduction($income, $bucket)
            : $this->calculateUnder65Deduction($income, $bucket);
    }

    private function calculateSeniorDeduction(int $income, int $bucket): int
    {
        if ($income <= 3_300_000) {
            return [1_100_000, 1_000_000, 900_000][$bucket];
        }

        if ($income <= 4_100_000) {
            return $this->percent($income, 25) + [275_000, 175_000, 75_000][$bucket];
        }

        if ($income <= 7_700_000) {
            return $this->percent($income, 15) + [685_000, 585_000, 485_000][$bucket];
        }

        if ($income <= 10_000_000) {
            return $this->percent($income, 5) + [1_455_000, 1_355_000, 1_255_000][$bucket];
        }

        return [1_955_000, 1_855_000, 1_755_000][$bucket];
    }

    private function calculateUnder65Deduction(int $income, int $bucket): int
    {
        if ($income <= 1_300_000) {
            return [600_000, 500_000, 400_000][$bucket];
        }

        if ($income <= 4_100_000) {
            return $this->percent($income, 25) + [275_000, 175_000, 75_000][$bucket];
        }

        if ($income <= 7_700_000) {
            return $this->percent($income, 15) + [685_000, 585_000, 485_000][$bucket];
        }

        if ($income <= 10_000_000) {
            return $this->percent($income, 5) + [1_455_000, 1_355_000, 1_255_000][$bucket];
        }

        return [1_955_000, 1_855_000, 1_755_000][$bucket];
    }

    private function resolveBirthDate(array $ctx): ?string
    {
        $value = $ctx['guest_birth_date'] ?? null;
        $normalized = $this->normalizeBirthDate($value);
        if ($normalized !== null) {
            return $normalized;
        }

        $data = $ctx['data'] ?? null;
        if ($data instanceof Data) {
            $guest = $data->guest;
            if ($guest) {
                return $this->normalizeBirthDate($guest->birth_date ?? null);
            }
        }

        return null;
    }

    private function normalizeBirthDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                return null;
            }

            return $value;
        }

        return null;
    }

    private function isSenior(?string $birthDate, int $year): bool
    {
        if (! $birthDate || $year <= 0) {
            return false;
        }

        $thresholdYear = $year - 65 + 1;
        if ($thresholdYear <= 0) {
            return false;
        }

        $threshold = sprintf('%04d-01-01', $thresholdYear);

        return $birthDate <= $threshold;
    }

    private function resolveYear(mixed $kihuYear, string $period): int
    {
        $year = (int) $kihuYear;

        if ($period === 'prev') {
            return $year > 0 ? $year - 1 : 0;
        }

        return max(0, $year);
    }
}