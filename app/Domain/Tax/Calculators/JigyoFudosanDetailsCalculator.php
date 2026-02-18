<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

/**
 * details（事業・営業等 / 不動産）から派生値をサーバ側で確定し、payloadへ保存する。
 *
 * 方針（ユーザー要件）：
 * - 事業（営業等）・不動産は損益通算があるため「所得金額」はマイナスも保持する。
 * - ただし、青色申告特別控除（および不動産の負債利子）は
 *     ① base（専従者給与控除後）がマイナスのときは 0 扱い（マイナス幅を広げない）
 *     ② base がプラスでも控除によりマイナスへ転移させない（0下限）
 *
 * 生成キー（period=prev/curr）：
 * - 事業：jigyo_eigyo_sashihiki_1_*, jigyo_eigyo_keihi_gokei_*, jigyo_eigyo_sashihiki_2_*,
 *         jigyo_eigyo_aoi_tokubetsu_kojo_mae_*, jigyo_eigyo_shotoku_*
 * - 不動産：fudosan_keihi_gokei_*, fudosan_sashihiki_*, fudosan_aoi_tokubetsu_kojo_mae_*, fudosan_shotoku_*
 */
final class JigyoFudosanDetailsCalculator implements ProvidesKeys
{
    public const ID = 'jigyo_fudosan_details';
    public const ORDER = 140; // DetailsSourceAlias の直後くらいを想定

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        // 代表キー（debug用）。実際は prev/curr を両方生成する。
        return [
            'jigyo_eigyo_shotoku_prev',
            'jigyo_eigyo_shotoku_curr',
            'fudosan_shotoku_prev',
            'fudosan_shotoku_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string                $period  'prev'|'curr'
     * @return array<string, mixed>
     */
    public function compute(array $payload, string $period): array
    {
        if (!in_array($period, ['prev', 'curr'], true)) {
            return [];
        }

        $out = [];

        // --------------------------
        // 事業・営業等
        // --------------------------
        $uriage   = $this->n($payload[sprintf('jigyo_eigyo_uriage_%s', $period)] ?? null);
        $urigenka = $this->n($payload[sprintf('jigyo_eigyo_urigenka_%s', $period)] ?? null);
        $sashihiki1 = $uriage - $urigenka;
        $out[sprintf('jigyo_eigyo_sashihiki_1_%s', $period)] = $sashihiki1;

        $keihiGokei = 0;
        for ($i = 1; $i <= 7; $i++) {
            $keihiGokei += $this->n($payload[sprintf('jigyo_eigyo_keihi_%d_%s', $i, $period)] ?? null);
        }
        $keihiGokei += $this->n($payload[sprintf('jigyo_eigyo_keihi_sonota_%s', $period)] ?? null);
        $out[sprintf('jigyo_eigyo_keihi_gokei_%s', $period)] = $keihiGokei;

        $sashihiki2 = $sashihiki1 - $keihiGokei;
        $out[sprintf('jigyo_eigyo_sashihiki_2_%s', $period)] = $sashihiki2;

        $senju = $this->n($payload[sprintf('jigyo_eigyo_senjuusha_kyuyo_%s', $period)] ?? null);
        $mae   = $sashihiki2 - $senju; // ここはマイナス可（損益通算用に保持）
        $out[sprintf('jigyo_eigyo_aoi_tokubetsu_kojo_mae_%s', $period)] = $mae;

        $aoiInput = $this->n($payload[sprintf('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_%s', $period)] ?? null);

        // ルール：mae <= 0 のとき、青色控除は 0 扱い（マイナス幅を広げない）
        //         mae > 0 のときも控除によりマイナスへ転移させない（0下限）
        if ($mae <= 0) {
            $shotoku = $mae;
        } else {
            $shotoku = max(0, $mae - $aoiInput);
        }
        $out[sprintf('jigyo_eigyo_shotoku_%s', $period)] = $shotoku;

        // --------------------------
        // 不動産
        // --------------------------
        $syunyu = $this->n($payload[sprintf('fudosan_syunyu_%s', $period)] ?? null);

        $fKeihiGokei = 0;
        for ($i = 1; $i <= 7; $i++) {
            $fKeihiGokei += $this->n($payload[sprintf('fudosan_keihi_%d_%s', $i, $period)] ?? null);
        }
        $fKeihiGokei += $this->n($payload[sprintf('fudosan_keihi_sonota_%s', $period)] ?? null);
        $out[sprintf('fudosan_keihi_gokei_%s', $period)] = $fKeihiGokei;

        $fSashihiki = $syunyu - $fKeihiGokei;
        $out[sprintf('fudosan_sashihiki_%s', $period)] = $fSashihiki;

        $fSenju = $this->n($payload[sprintf('fudosan_senjuusha_kyuyo_%s', $period)] ?? null);
        $fBase  = $fSashihiki - $fSenju; // マイナス可（損益通算用に保持）
        $out[sprintf('fudosan_aoi_tokubetsu_kojo_mae_%s', $period)] = $fBase;

        $fAoiInput = $this->n($payload[sprintf('fudosan_aoi_tokubetsu_kojo_gaku_%s', $period)] ?? null);
        $fusaiInput = $this->n($payload[sprintf('fudosan_fusairishi_%s', $period)] ?? null);

        // ルール：
        // - base <= 0 のとき：青色控除=0、負債利子=0（マイナス幅を広げない）。所得=base を保持
        // - base > 0 のとき：青色控除→0下限、さらに負債利子→0下限（マイナスへ転移させない）
        if ($fBase <= 0) {
            $fShotoku = $fBase;
        } else {
            $afterAoi = max(0, $fBase - $fAoiInput);
            $fShotoku = max(0, $afterAoi - $fusaiInput);
        }
        $out[sprintf('fudosan_shotoku_%s', $period)] = $fShotoku;

        return $out;
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') {
            return 0;
        }
        if (is_int($v)) {
            return $v;
        }
        if (is_float($v)) {
            return (int) round($v);
        }
        if (is_string($v)) {
            $s = str_replace([',', ' ', '　'], '', trim($v));
            $s = str_replace(['－', '−', '―'], '-', $s);
            if (function_exists('mb_convert_kana')) {
                $s = mb_convert_kana($s, 'n', 'UTF-8');
            }
            if ($s === '' || $s === '-') {
                return 0;
            }
            return preg_match('/^-?\d+$/', $s) === 1 ? (int) $s : 0;
        }
        return 0;
    }
}