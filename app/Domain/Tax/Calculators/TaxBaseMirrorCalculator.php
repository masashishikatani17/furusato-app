<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class TaxBaseMirrorCalculator implements ProvidesKeys
{
    public const ID = 'tax.base.mirror';
    public const ORDER = 5050;
    public const ANCHOR = 'tax';
    public const BEFORE = [
        ShotokuTaxCalculator::ID,
        JuminTaxCalculator::ID,
    ];
    public const AFTER = [
        JuminzeiKifukinCalculator::ID,
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

            $keys[] = sprintf('tax_kazeishotoku_shotoku_%s', $period);
            $keys[] = sprintf('tax_kazeishotoku_jumin_%s', $period);

            $keys[] = sprintf('bunri_sogo_gokeigaku_shotoku_%s', $period);
            $keys[] = sprintf('bunri_sogo_gokeigaku_jumin_%s', $period);
            $keys[] = sprintf('bunri_sashihiki_gokei_shotoku_%s', $period);
            $keys[] = sprintf('bunri_sashihiki_gokei_jumin_%s', $period);
            $keys[] = sprintf('bunri_kazeishotoku_sogo_shotoku_%s', $period);
            $keys[] = sprintf('bunri_kazeishotoku_sogo_jumin_%s', $period);

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

            // ▼ 第一表の「所得金額の合計額」は A+B を採用（共通 SoT からミラー）
            $abKey = sprintf('sum_for_ab_total_%s', $period);
            if (array_key_exists($abKey, $payload)) {
                $updates[sprintf('shotoku_gokei_%s', $period)] = (int) $payload[$abKey];
            }

            // ▼ 参考合計（総合課税のみ）が必要なら sum_for_sogoshotoku_* を使う
            //    既存 shotoku_joto_ichiji_* にミラー（後方互換のため／利用箇所がある場合）
            $sumSogoKey = sprintf('sum_for_sogoshotoku_%s', $period);
            if (array_key_exists($sumSogoKey, $payload)) {
                $sumSogo = (int) $payload[$sumSogoKey];
                $updates[sprintf('shotoku_joto_ichiji_shotoku_%s', $period)] = $sumSogo;
                $updates[sprintf('shotoku_joto_ichiji_jumin_%s',  $period)] = $sumSogo;
            }

            // ▼ 課税基礎はこのフェーズでは未置換：何も書かない（= 既存の Shotoku/JuminTax が設定）

            // ▼ bunri_* 系は再計算しない（既に存在すればそのままミラー）
            foreach ([
                'bunri_sogo_gokeigaku_shotoku',
                'bunri_sogo_gokeigaku_jumin',
                'bunri_sashihiki_gokei_shotoku',
                'bunri_sashihiki_gokei_jumin',
                'bunri_kazeishotoku_sogo_shotoku',
                'bunri_kazeishotoku_sogo_jumin',
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