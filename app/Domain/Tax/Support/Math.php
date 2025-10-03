<?php
namespace App\Domain\Tax\Support;

final class Math
{
    /** Excel: ROUNDDOWN(value, 0) 相当（0桁で床） */
    public static function roundDown0(float|int $v): int
    {
        return (int)floor((float)$v);
    }

    /** Excel: ROUND(value, 0) 相当（四捨五入・0.5切上げ） */
    public static function round0(float|int $v): int
    {
        return (int)round((float)$v, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Excel: ROUNDDOWN(value, digits) の簡易対応（digits <= 0 のみに対応）
     * 例）digits=-3 → 千円単位で切り捨て
     */
    public static function roundDownN(float|int $v, int $digits): int
    {
        if ($digits >= 0) return self::roundDown0($v);
        $scale = 10 ** (-$digits);
        return (int) (floor((float)$v / $scale) * $scale);
    }
}