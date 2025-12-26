<?php

namespace App\Domain\Tax\Calculators;

use App\Domain\Tax\Contracts\MasterProviderContract;
use App\Services\Tax\Contracts\ProvidesKeys;
use App\Domain\Tax\Calculators\TokureiRateCalculator;
use Illuminate\Support\Facades\Log;

class FurusatoResultCalculator implements ProvidesKeys
{
    public const ID = 'results.furusato';
    // 【制度順】フェーズE：最終結果（率・控除確定後、Mirrorの前）
    public const ORDER = 9800;
    public const ANCHOR = 'results';
    public const BEFORE = [];
    public const AFTER = [
        TokureiRateCalculator::ID,
    ];

    private const PERIODS = ['prev', 'curr'];

    private const HUMAN_DIFF_BASES = [
        'kojo_kafu',
        'kojo_hitorioya',
        'kojo_kinrogakusei',
        'kojo_shogaisyo',
        'kojo_haigusha',
        'kojo_haigusha_tokubetsu',
        'kojo_fuyo',
        'kojo_tokutei_shinzoku',
        'kojo_kiso',
    ];

    // tb_* SoTに移行：短期以外で最小率判定に用いる科目の識別子
    private const SEPARATED_OTHER_KINDS = ['choki', 'haito', 'sakimono', 'joto'];

    private const FIXED_NINETY_RATE = 0.90;
    private const BUNRI_SHORT_TERM_RATE = 0.59370;
    private const BUNRI_OTHER_RATE = 0.74685;

    public function __construct(private readonly MasterProviderContract $masterProvider)
    {
    }

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        return [
            'furusato_result_details_prev',
            'furusato_result_details_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $humanAdjustedPairs = $this->buildHumanAdjustedPairs($payload, $ctx);

        foreach (self::PERIODS as $period) {
            $pair = $humanAdjustedPairs[$period] ?? ['value' => null];
            $payload[sprintf('human_adjusted_taxable_%s', $period)] = $pair['value'];
        }

        $details = $this->buildDetailsFromPairs($payload, $ctx, $humanAdjustedPairs);

        foreach (self::PERIODS as $period) {
            $payload[sprintf('furusato_result_details_%s', $period)] = $details[$period] ?? [];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array{prev: array<string, float|null>, curr: array<string, float|null>}
     */
    public function buildDetails(array $payload, array $ctx): array
    {
        $humanAdjustedPairs = $this->buildHumanAdjustedPairs($payload, $ctx);
        return $this->buildDetailsFromPairs($payload, $ctx, $humanAdjustedPairs);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @param  array<string, array{value:int|null}>  $humanAdjustedPairs
     * @return array{prev: array<string, float|null>, curr: array<string, float|null>}
     */
    private function buildDetailsFromPairs(array $payload, array $ctx, array $humanAdjustedPairs): array
    {
        $year = $this->resolveMasterYear($ctx);
        $companyId = isset($ctx['company_id']) && $ctx['company_id'] !== ''
            ? (int) $ctx['company_id']
            : null;

        $rows = $year > 0 ? $this->buildTokureiRows($year, $companyId) : [];

        $details = [];
        foreach (self::PERIODS as $period) {
            $pair = $humanAdjustedPairs[$period] ?? ['value' => null];
            $details[$period] = $this->buildPeriodDetails($payload, $rows, $period, $pair);
        }

        return [
            'prev' => $details['prev'] ?? $this->emptyDetails(),
            'curr' => $details['curr'] ?? $this->emptyDetails(),
        ];
    }

    /**
     * @param  array<int, array{lower:int, upper:int|null, rate:float}>  $rows
     * @return array<string, float|null>
     */
    private function buildPeriodDetails(array $payload, array $rows, string $period, array $pair): array
    {
        $raw = $pair['value'] ?? null;
        $aa50Base = $raw !== null ? $this->floorToThousands(max(0, $raw)) : null;
        $aa50 = $aa50Base !== null
            ? $this->lowerBoundRate($aa50Base, $rows)
            : null;

        $aa51 = self::FIXED_NINETY_RATE;

        $aa52 = $this->sanrinRate($rows, $payload, $period);
        $aa53 = $this->taishokuRate($rows, $payload, $period);

        $aa54 = null;
        if ($aa52 !== null || $aa53 !== null) {
            $candidates = array_filter([$aa52, $aa53], static fn (?float $value): bool => $value !== null);
            $aa54 = $candidates === [] ? null : min($candidates);
        }

        $aa55 = $this->bunriMinRate($payload, $period);

        $finalCandidates = array_filter([
            $aa50,
            $aa51,
            $aa54,
            $aa55,
        ], static fn (?float $value): bool => $value !== null);
        $aa56 = $finalCandidates === [] ? null : min($finalCandidates);

        return [
            'AA50' => $aa50,
            'AA51' => $aa51,
            'AA52' => $aa52,
            'AA53' => $aa53,
            'AA54' => $aa54,
            'AA55' => $aa55,
            'AA56' => $aa56,
        ];
    }

    /**
     * @return array<string, array{value:int|null}>
     */
    private function buildHumanAdjustedPairs(array $payload, array $ctx): array
    {
        $pairs = [];
        foreach (self::PERIODS as $period) {
            $pairs[$period] = $this->humanAdjustedPair($payload, $ctx, $period);
        }

        return $pairs;
    }

    /**
     * @return array{value:int|null}
     */
    private function humanAdjustedPair(array $payload, array $ctx, string $period): array
    {
        $taxable = $this->taxableBase($payload, $ctx, $period);
        if ($taxable === null) {
            return ['value' => null];
        }

        $humanDiffSum = $this->humanDiffSum($payload, $period);
        $raw = $taxable - $humanDiffSum;
        return ['value' => $raw];
    }

    /**
     * @return array<string, float|null>
     */
    private function emptyDetails(): array
    {
        return [
            'AA50' => null,
            'AA51' => self::FIXED_NINETY_RATE,
            'AA52' => null,
            'AA53' => null,
            'AA54' => null,
            'AA55' => null,
            'AA56' => null,
        ];
    }

    private function humanDiffSum(array $payload, string $period): int
    {
        $sum = 0;

        foreach (self::HUMAN_DIFF_BASES as $base) {
            // 基本キー（新SoT）
            $shotokuKeys = [sprintf('%s_shotoku_%s', $base, $period)];
            $juminKeys   = [sprintf('%s_jumin_%s',   $base, $period)];

            // ▼ key alias（UI/保存キーが override されているケースを吸収）
            //  - kojo_kiso: UI/保存は shotokuzei_kojo_kiso_* / juminzei_kojo_kiso_* を使っているため
            //              kojo_kiso_* が無い場合でも人的控除差に必ず含める
            if ($base === 'kojo_kiso') {
                $shotokuKeys[] = sprintf('shotokuzei_kojo_kiso_%s', $period);
                $juminKeys[]   = sprintf('juminzei_kojo_kiso_%s',   $period);
            }

            //  - kojo_shogaisyo: typo/旧名 -> kojo_shogaisha に統合されている場合がある
            if ($base === 'kojo_shogaisyo') {
                $shotokuKeys[] = sprintf('kojo_shogaisha_shotoku_%s', $period);
                $juminKeys[]   = sprintf('kojo_shogaisha_jumin_%s',   $period);
                // さらに旧キー（入力互換）が残っている場合
                $shotokuKeys[] = sprintf('kojo_shogaisyo_shotoku_%s', $period);
                $juminKeys[]   = sprintf('kojo_shogaisyo_jumin_%s',   $period);
            }

            $shotoku = $this->firstIntOrZero($payload, $shotokuKeys);
            $jumin   = $this->firstIntOrZero($payload, $juminKeys);

            $sum += ($shotoku - $jumin);
        }

        return $sum;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<int,string>    $keys
     */
    private function firstIntOrZero(array $payload, array $keys): int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $v = $this->intOrNull($payload[$key] ?? null);
            if ($v !== null) {
                return $v;
            }
        }
        return 0;
    }

    private function taxableBase(array $payload, array $ctx, string $period): ?int
    {
        unset($ctx);
        /**
         * ▼ 人的控除差調整（課税総所得金額-人的控除差調整額）は住民税側の概念。
         * よって基準は tb_sogo_jumin_*（住民税：総合課税の課税標準）を用いる。
         */
        $raw = $this->intOrNull($payload[sprintf('tb_sogo_jumin_%s', $period)] ?? null);
        if ($raw === null) {
            return null;
        }

        return $this->floorToThousands($raw);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function syoriSettings(array $ctx): array
    {
        $settings = $ctx['syori_settings'] ?? [];

        return is_array($settings) ? $settings : [];
    }

    /**
     * @return array<int, array{lower:int, upper:int|null, rate:float}>
     */
    private function buildTokureiRows(int $year, ?int $companyId): array
    {
        $collection = $this->masterProvider->getTokureiRates($year, $companyId);

        $rows = [];
        foreach ($collection as $row) {
            $lower = $this->normalizeBound($row->lower ?? null);
            if ($lower === null) {
                continue;
            }

            $upper = $this->normalizeUpperBound($row->upper ?? null);
            $rate = $this->normalizeRate($row->tokurei_deduction_rate ?? null);

            if ($rate === null) {
                continue;
            }

            $rows[] = [
                'lower' => $lower,
                'upper' => $upper,
                'rate' => $rate,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $lowerCmp = $a['lower'] <=> $b['lower'];
            if ($lowerCmp !== 0) {
                return $lowerCmp;
            }

            $upperA = $a['upper'];
            $upperB = $b['upper'];

            if ($upperA === $upperB) {
                return 0;
            }

            if ($upperA === null) {
                return 1;
            }

            if ($upperB === null) {
                return -1;
            }

            return $upperA <=> $upperB;
        });

        return $rows;
    }

    private function sanrinRate(array $rows, array $payload, string $period): ?float
    {
        // tb_sanrin_shotoku_*
        $amount = $this->intOrNull($payload[sprintf('tb_sanrin_shotoku_%s', $period)] ?? null) ?? 0;
        if ($amount <= 0) {
            return null;
        }

        $divided = $this->floorToThousands($amount / 5);
        if ($divided <= 0) {
            return null;
        }

        return $this->lowerBoundRate($divided, $rows);
    }

    private function taishokuRate(array $rows, array $payload, string $period): ?float
    {
        // tb_taishoku_shotoku_*
        $amount = $this->intOrNull($payload[sprintf('tb_taishoku_shotoku_%s', $period)] ?? null) ?? 0;
        if ($amount <= 0) {
            return null;
        }

        $base = $this->floorToThousands($amount);
        if ($base <= 0) {
            return null;
        }

        return $this->lowerBoundRate($base, $rows);
    }

    private function bunriMinRate(array $payload, string $period): ?float
    {
        // 短期：tb_joto_tanki_shotoku_*
        $shortAmount = $this->floorToThousands(
            $this->intOrNull($payload[sprintf('tb_joto_tanki_shotoku_%s', $period)] ?? null) ?? 0
        );

        if ($shortAmount > 0) {
            return self::BUNRI_SHORT_TERM_RATE;
        }

        foreach (self::SEPARATED_OTHER_KINDS as $kind) {
            $amount = $this->floorToThousands($this->fromTb($payload, $kind, $period));

            if ($amount > 0) {
                return self::BUNRI_OTHER_RATE;
            }
        }

        return null;
    }

    /**
     * tb_* SoT から分離科目金額（所得税側）を取得
     * kind: choki|haito|sakimono|joto
     */
    private function fromTb(array $payload, string $kind, string $period): int
    {
        $k = fn(string $name) => $this->intOrNull($payload[sprintf('%s_shotoku_%s', $name, $period)] ?? null) ?? 0;
        return match ($kind) {
            'choki'    => $k('tb_joto_choki'),
            'haito'    => $k('tb_jojo_kabuteki_haito'),
            'sakimono' => $k('tb_sakimono'),
            'joto'     => $k('tb_ippan_kabuteki_joto') + $k('tb_jojo_kabuteki_joto'),
            default    => 0,
        };
    }

    private function lowerBoundRate(int $amount, array $rows): ?float
    {
        if ($rows === []) {
            return null;
        }

        $amount = $this->floorToThousands(max(0, $amount));

        $fallbackRate = null;
        $fallbackLower = PHP_INT_MAX;

        foreach ($rows as $row) {
            $lower = (int) $row['lower'];
            if ($lower < $fallbackLower) {
                $fallbackLower = $lower;
                $fallbackRate = $row['rate'];
            }

            if ($lower > $amount) {
                continue;
            }

            $upper = $row['upper'];
            if ($upper !== null && $amount > $upper) {
                continue;
            }

            return $row['rate'];
        }

        return $fallbackRate;
    }

    private function resolveMasterYear(array $ctx): int
    {
        $year = isset($ctx['master_kihu_year']) ? (int) $ctx['master_kihu_year'] : 0;
        if ($year > 0) {
            return $year;
        }

        $fallback = isset($ctx['kihu_year']) ? (int) $ctx['kihu_year'] : 0;

        return max(0, $fallback);
    }

    private function normalizeBound(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeUpperBound(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeRate(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return ((float) $value) / 100.0;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
            if ($value === '') {
                return null;
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) floor((float) $value);
    }

    private function floorToThousands(int|float $value): int
    {
        $v = (float) $value;
        if ($v >= 0) {
            return (int) (floor($v / 1000) * 1000);
        }
        // 負は “より小さいほうへ” の切り捨て（-1,234 → -2,000）
        $abs = abs($v);
        $thousand = (int) (ceil($abs / 1000) * 1000);
        return -$thousand;
    }
}