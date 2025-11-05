<?php

namespace App\Domain\Tax\Calculators;

use App\Models\Data;
use App\Services\Tax\Contracts\ProvidesKeys;
use DateTimeInterface;

class KyuyoNenkinCalculator implements ProvidesKeys
{
    public const ID = 'kyuyo.nenkin';
    public const ORDER = 2120;
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
            $keys[] = sprintf('jumin_kyuyo_jumin_%s',   $period);
            // 雑・公的年金（所得）
            $keys[] = sprintf('shotoku_zatsu_nenkin_shotoku_%s', $period);
            $keys[] = sprintf('jumin_zatsu_nenkin_jumin_%s',     $period);
            // 雑・業務（所得）
            $keys[] = sprintf('shotoku_zatsu_gyomu_shotoku_%s', $period);
            $keys[] = sprintf('jumin_zatsu_gyomu_jumin_%s',     $period);
            // 雑・その他（所得）
            $keys[] = sprintf('shotoku_zatsu_sonota_shotoku_%s', $period);
            $keys[] = sprintf('jumin_zatsu_sonota_jumin_%s',     $period);
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
            $juminKyuyoKey = sprintf('jumin_kyuyo_jumin_%s', $period);
            $updates[$shotokuKyuyoKey] = $kyuyoAmount;
            $updates[$juminKyuyoKey] = $kyuyoAmount;
            $working[$shotokuKyuyoKey] = $kyuyoAmount;
            $working[$juminKyuyoKey] = $kyuyoAmount;

            // ▼ 雑（公的年金等）：detailsの「zatsu_nenkin_syunyu_*」を収入として読み、所得控除適用後の所得を算出
            $nenkinIncomeKey = sprintf('zatsu_nenkin_syunyu_%s', $period);
            $nenkinIncome    = $this->clampIncome($payload[$nenkinIncomeKey] ?? null);

            // ▼ 年金バケット判定だけは新キー(sum_for_pension_bucket_*)を優先使用（v1要件）
            $bucketOther = $this->n($payload[sprintf('sum_for_pension_bucket_%s', $period)] ?? null);
            $shotokuOther = $this->sumOtherIncome($working, 'shotoku_', sprintf('shotoku_zatsu_nenkin_shotoku_%s', $period), $period);
            $juminOther   = $this->sumOtherIncome($working, 'jumin_',   sprintf('jumin_zatsu_nenkin_jumin_%s',     $period), $period);

            $isSenior = $this->isSenior($birthDate, $year);
            $shotokuResult = $this->calculateNenkinShotoku($nenkinIncome, $isSenior, $bucketOther > 0 ? $bucketOther : $shotokuOther);
            $juminResult   = $this->calculateNenkinShotoku($nenkinIncome, $isSenior, $bucketOther > 0 ? $bucketOther : $juminOther);

            $shotokuNenkinKey = sprintf('shotoku_zatsu_nenkin_shotoku_%s', $period);
            $juminNenkinKey = sprintf('jumin_zatsu_nenkin_jumin_%s', $period);
            $updates[$shotokuNenkinKey] = $shotokuResult;
            $updates[$juminNenkinKey] = $juminResult;
            $working[$shotokuNenkinKey] = $shotokuResult;
            $working[$juminNenkinKey] = $juminResult;

            // ▼ 雑（業務）：所得＝max(0, 収入−支払) を税目共通でミラー
            $gyomuInc = $this->clampIncome($payload[sprintf('zatsu_gyomu_syunyu_%s',   $period)] ?? null);
            $gyomuPay = $this->clampIncome($payload[sprintf('zatsu_gyomu_shiharai_%s', $period)] ?? null);
            $gyomuShotoku = max(0, $gyomuInc - $gyomuPay);
            $updates[sprintf('shotoku_zatsu_gyomu_shotoku_%s', $period)] = $gyomuShotoku;
            $updates[sprintf('jumin_zatsu_gyomu_jumin_%s',     $period)] = $gyomuShotoku;
            $working[sprintf('shotoku_zatsu_gyomu_shotoku_%s', $period)] = $gyomuShotoku;
            $working[sprintf('jumin_zatsu_gyomu_jumin_%s',     $period)] = $gyomuShotoku;

            // ▼ 雑（その他）：所得＝max(0, 収入−支払) を税目共通でミラー
            $sonotaInc = $this->clampIncome($payload[sprintf('zatsu_sonota_syunyu_%s',   $period)] ?? null);
            $sonotaPay = $this->clampIncome($payload[sprintf('zatsu_sonota_shiharai_%s', $period)] ?? null);
            $sonotaShotoku = max(0, $sonotaInc - $sonotaPay);
            $updates[sprintf('shotoku_zatsu_sonota_shotoku_%s', $period)] = $sonotaShotoku;
            $updates[sprintf('jumin_zatsu_sonota_jumin_%s',     $period)] = $sonotaShotoku;
            $working[sprintf('shotoku_zatsu_sonota_shotoku_%s', $period)] = $sonotaShotoku;
            $working[sprintf('jumin_zatsu_sonota_jumin_%s',     $period)] = $sonotaShotoku;
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

        if ($income <= 1_900_000) {
            return max(0, $income - 650_000);
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sumOtherIncome(array $payload, string $prefix, string $excludeKey, string $period): int
    {
        $total = 0;
        $suffix = '_' . $period;

        foreach ($payload as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (! str_starts_with($key, $prefix)) {
                continue;
            }

            if (! str_ends_with($key, $suffix)) {
                continue;
            }

            if ($key === $excludeKey) {
                continue;
            }

            $total += $this->n($value);
        }

        return $total;
    }
}