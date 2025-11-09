<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;
use App\Domain\Tax\Calculators\CommonSumsCalculator;
use App\Domain\Tax\Calculators\JintekiKojoCalculator;
use App\Domain\Tax\Calculators\HaigushaKojoCalculator;
use App\Domain\Tax\Calculators\KojoSeimeiJishinCalculator;
use App\Domain\Tax\Calculators\CommonTaxableBaseCalculator;
use App\Domain\Tax\Calculators\ShotokuTaxCalculator;
use App\Domain\Tax\Calculators\SeitotoTokubetsuZeigakuKojoCalculator;
use App\Domain\Tax\Calculators\JuminTaxCalculator;
use App\Domain\Tax\Calculators\JuminzeiKifukinCalculator;
use App\Domain\Tax\Calculators\TokureiRateCalculator;
use App\Domain\Tax\Calculators\BunriSeparatedMinRateCalculator;
use App\Domain\Tax\Calculators\FurusatoResultCalculator;

class TaxBaseMirrorCalculator implements ProvidesKeys
{
    public const ID = 'tax.base.mirror';
    // 【制度順】フェーズE：最終ミラー（最後に実行）
    public const ORDER = 9990;
    public const ANCHOR = 'tax';
    public const BEFORE = []; // ミラーは最後に走らせたいので BEFORE は空
    public const AFTER = [
        CommonSumsCalculator::ID,
        JintekiKojoCalculator::ID,
        HaigushaKojoCalculator::ID,
        KojoSeimeiJishinCalculator::ID,
        CommonTaxableBaseCalculator::ID,
        ShotokuTaxCalculator::ID,
        SeitotoTokubetsuZeigakuKojoCalculator::ID,
        JuminTaxCalculator::ID,
        JuminzeiKifukinCalculator::ID,
        TokureiRateCalculator::ID,
        BunriSeparatedMinRateCalculator::ID,
        FurusatoResultCalculator::ID,
    ];
    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [];

        foreach (self::PERIODS as $period) {
            $keys[] = sprintf('tsusanmae_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('tsusanmae_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('tsusanmae_ichiji_%s', $period);

            $keys[] = sprintf('shotoku_keijo_%s', $period);
            $keys[] = sprintf('shotoku_joto_tanki_sogo_%s', $period);
            $keys[] = sprintf('shotoku_joto_choki_sogo_%s', $period);
            $keys[] = sprintf('shotoku_ichiji_%s', $period);
            $keys[] = sprintf('shotoku_sanrin_%s', $period);
            $keys[] = sprintf('shotoku_taishoku_%s', $period);
            $keys[] = sprintf('shotoku_gokei_%s', $period);

            $keys[] = sprintf('shotoku_joto_ichiji_shotoku_%s', $period);
            $keys[] = sprintf('shotoku_joto_ichiji_jumin_%s', $period);

            $keys[] = sprintf('bunri_sogo_gokeigaku_shotoku_%s', $period);
            $keys[] = sprintf('bunri_sogo_gokeigaku_jumin_%s', $period);
            $keys[] = sprintf('bunri_sashihiki_gokei_shotoku_%s', $period);
            $keys[] = sprintf('bunri_sashihiki_gokei_jumin_%s', $period);

            $keys[] = sprintf('tokurei_kojo_sanrin_%s', $period);

            $keys[] = sprintf('after_2jitsusan_taishoku_%s', $period);
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
        $updates = [];

        foreach (self::PERIODS as $period) {
            // ▼ tsusanmae_* / shotoku_* は “そのまま” ミラー（存在すればセット）
            foreach ([
                'tsusanmae_joto_tanki_sogo',
                'tsusanmae_joto_choki_sogo',
                'tsusanmae_ichiji',
                'shotoku_keijo',
                'shotoku_joto_tanki_sogo',
                'shotoku_joto_choki_sogo',
                'shotoku_ichiji',
                'shotoku_sanrin',
                'shotoku_taishoku',
            ] as $k) {
                $key = sprintf('%s_%s', $k, $period);
                if (array_key_exists($key, $payload)) {
                    $updates[$key] = (int) $payload[$key];
                }
            }

            $sumSogoKey = sprintf('sum_for_sogoshotoku_%s', $period);
            if (array_key_exists($sumSogoKey, $payload)) {
                $sumSogo = (int) $payload[$sumSogoKey];
                // 第一表「所得金額の合計額」（表示用合計）は A（総所得金額）を採用
                $updates[sprintf('shotoku_gokei_%s', $period)] = $sumSogo;
            }

            // ▼ 課税基礎（tb_*）は別Calculatorで確定。ここでは設定しない。

            // ▼ bunri_* 系は再計算しない（既に存在すればそのままミラー）
            foreach ([
                'bunri_sogo_gokeigaku_shotoku',
                'bunri_sogo_gokeigaku_jumin',
                'bunri_sashihiki_gokei_shotoku',
                'bunri_sashihiki_gokei_jumin',
                'tokurei_kojo_sanrin',
                'after_2jitsusan_taishoku',
            ] as $k) {
                $key = sprintf('%s_%s', $k, $period);
                if (array_key_exists($key, $payload)) {
                    $updates[$key] = (int) $payload[$key];
                }
            }
        }

        return array_replace($payload, $updates);
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
}