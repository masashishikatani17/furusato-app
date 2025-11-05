<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

/**
 * 先物取引（分離課税） 内訳のサーバ側確定計算
 *
 * - shotoku_sakimono_{prev,curr} = syunyu - keihi
 * - shotoku_sakimono_after_kurikoshi_{prev,curr} = max(shotoku - kurikoshi, 0)
 * - 第三表ブリッジ（税目共通で同額）
 *   - bunri_syunyu_sakimono_{shotoku/jumin}_{prev,curr}  ← syunyu_sakimono_*
 *   - bunri_shotoku_sakimono_{shotoku/jumin}_{prev,curr} ← shotoku_sakimono_after_kurikoshi_*
 */
final class SakimonoCalculator implements ProvidesKeys
{
    /**
     * @return array<int,string>
     */
    public static function provides(): array
    {
        $keys = [];
        foreach (['prev','curr'] as $p) {
            $keys[] = "shotoku_sakimono_{$p}";
            $keys[] = "shotoku_sakimono_after_kurikoshi_{$p}";
            $keys[] = "bunri_syunyu_sakimono_shotoku_{$p}";
            $keys[] = "bunri_syunyu_sakimono_jumin_{$p}";
            $keys[] = "bunri_shotoku_sakimono_shotoku_{$p}";
            $keys[] = "bunri_shotoku_sakimono_jumin_{$p}";
        }
        return $keys;
    }

    /**
     * @param array<string,mixed> $payload
     * @param 'prev'|'curr' $period
     * @return array<string,int>
     */
    public function compute(array $payload, string $period): array
    {
        $r = [];
        $getInt = static function ($v): int {
            if ($v === null || $v === '') { return 0; }
            if (is_int($v)) { return $v; }
            if (is_numeric($v)) { return (int)$v; }
            $s = preg_replace('/[^\-0-9]/', '', (string)$v) ?? '';
            if ($s === '' || $s === '-') { return 0; }
            return (int)$s;
        };

        $syunyu    = $getInt($payload["syunyu_sakimono_{$period}"]    ?? 0);
        $keihi     = $getInt($payload["keihi_sakimono_{$period}"]     ?? 0);
        $kurikoshi = $getInt($payload["kurikoshi_sakimono_{$period}"] ?? 0);

        $shotoku = $syunyu - $keihi;
        $after   = $shotoku - $kurikoshi;
        if ($after < 0) { $after = 0; }

        // SoT（サーバ確定）
        $r["shotoku_sakimono_{$period}"] = $shotoku;
        $r["shotoku_sakimono_after_kurikoshi_{$period}"] = $after;

        // 第三表ブリッジ（税目共通ミラー）
        $r["bunri_syunyu_sakimono_shotoku_{$period}"] = $syunyu;
        $r["bunri_syunyu_sakimono_jumin_{$period}"]   = $syunyu;
        $r["bunri_shotoku_sakimono_shotoku_{$period}"] = $after;
        $r["bunri_shotoku_sakimono_jumin_{$period}"]   = $after;

        return $r;
    }
}