<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class KojoAggregationCalculator implements ProvidesKeys
{
    public const ID = 'kojo.aggregate';
    // 【制度順】フェーズC：控除集計（各控除の後）
    public const ORDER = 3900;
    public const ANCHOR = 'deductions';
    public const BEFORE = [];
    public const AFTER = [
        KifukinCalculator::ID,
        KisoKojoCalculator::ID,
        JintekiKojoCalculator::ID,
        HaigushaKojoCalculator::ID,
    ];

    /** @var string[] */
    private const TAX_TYPES = ['shotoku', 'jumin'];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    /** @var string[] */
    private const SHOKEI_BASES = [
        'kojo_shakaihoken',
        'kojo_shokibo',
        'kojo_seimei',
        'kojo_jishin',
        'kojo_kafu',
        'kojo_hitorioya',
        'kojo_kinrogakusei',
        'kojo_shogaisha',
        'kojo_haigusha',
        'kojo_haigusha_tokubetsu',
        'kojo_fuyo',
        'kojo_tokutei_shinzoku',
        'kojo_kiso',
    ];

    /** @var string[] */
    private const GOKEI_EXTRAS = ['kojo_zasson', 'kojo_iryo', 'kojo_kifukin'];

    private const FIELD_OVERRIDES = [
        'kojo_kiso' => [
            'shotoku' => 'shotokuzei_kojo_kiso_%s',
            'jumin' => 'juminzei_kojo_kiso_%s',
        ],
        'kojo_kifukin' => [
            'shotoku' => 'shotokuzei_kojo_kifukin_%s',
            'jumin' => 'juminzei_kojo_kifukin_%s',
        ],
        'kojo_shogaisha' => [
            'shotoku' => 'kojo_shogaisyo_shotoku_%s',
            'jumin' => 'kojo_shogaisyo_jumin_%s',
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [];
        foreach (self::TAX_TYPES as $tax) {
            foreach (self::PERIODS as $period) {
                $keys[] = sprintf('kojo_shokei_%s_%s', $tax, $period);
                $keys[] = sprintf('kojo_gokei_%s_%s', $tax, $period);
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $updates = array_fill_keys(self::provides(), 0);

        foreach (self::TAX_TYPES as $tax) {
            foreach (self::PERIODS as $period) {
                $shokei = 0;
                foreach (self::SHOKEI_BASES as $base) {
                    $shokei += $this->valueFor($payload, $base, $tax, $period);
                }

                $gokei = $shokei;
                foreach (self::GOKEI_EXTRAS as $base) {
                    $gokei += $this->valueFor($payload, $base, $tax, $period);
                }

                $updates[sprintf('kojo_shokei_%s_%s', $tax, $period)] = $shokei;
                $updates[sprintf('kojo_gokei_%s_%s', $tax, $period)] = $gokei;
            }
        }

        return array_replace($payload, $updates);
    }

    private function valueFor(array $payload, string $base, string $tax, string $period): int
    {
        $override = self::FIELD_OVERRIDES[$base][$tax] ?? null;
        $key = $override ? sprintf($override, $period) : sprintf('%s_%s_%s', $base, $tax, $period);

        return $this->n($payload[$key] ?? null);
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