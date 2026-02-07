<?php

namespace App\Support;

use DateTimeInterface;

final class WarekiDate
{
    private const ERAS = [
        // key, label, start(YYYY-MM-DD)
        ['key' => 'taisho', 'label' => '大正', 'start' => '1912-07-30'],
        ['key' => 'showa',  'label' => '昭和', 'start' => '1926-12-25'],
        ['key' => 'heisei', 'label' => '平成', 'start' => '1989-01-08'],
        ['key' => 'reiwa',  'label' => '令和', 'start' => '2019-05-01'],
    ];

    /**
     * ISO(YYYY-MM-DD) または DateTimeInterface を和暦文字列へ。
     * 例：令和8年2月7日
     */
    public static function format(DateTimeInterface|string|null $value): string
    {
        $iso = self::normalizeIso($value);
        if ($iso === null) {
            return '';
        }

        [$y, $m, $d] = array_map('intval', explode('-', $iso));
        $idx = self::eraIndexForIso($iso);
        if ($idx < 0) {
            // 対応外（大正以前等）は西暦で返す（ここを空にすると表示が消えるため）
            return sprintf('%d年%d月%d日', $y, $m, $d);
        }

        $era = self::ERAS[$idx];
        [$sy, $sm, $sd] = array_map('intval', explode('-', $era['start']));
        $eraYear = ($y - $sy) + 1;
        if ($eraYear <= 0) {
            return sprintf('%d年%d月%d日', $y, $m, $d);
        }

        return sprintf('%s%d年%d月%d日', (string)$era['label'], $eraYear, $m, $d);
    }


    /**
     * 西暦年（年度）を和暦年ラベルへ（年のみ）
     * 例：2025 -> 令和7年
     * ※ 年のみなので、その年の 1/1 を基準に元号判定する（2019->平成31年）
     */
    public static function formatYear(int $year): string
    {
        if ($year <= 0) {
            return '';
        }
        $iso = sprintf('%04d-01-01', $year);
        $idx = self::eraIndexForIso($iso);
        if ($idx < 0) {
            return sprintf('%d年', $year);
        }
        $era = self::ERAS[$idx];
        [$sy] = array_map('intval', explode('-', $era['start']));
        $eraYear = ($year - $sy) + 1;
        if ($eraYear <= 0) {
            return sprintf('%d年', $year);
        }
        return sprintf('%s%d年', (string)$era['label'], $eraYear);
    }

    private static function normalizeIso(DateTimeInterface|string|null $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_string($value)) {
            $v = trim($value);
            if ($v === '') return null;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) !== 1) return null;
            return $v;
        }
        return null;
    }

    private static function eraIndexForIso(string $iso): int
    {
        // 末尾側から「start <= iso」を探す
        for ($i = count(self::ERAS) - 1; $i >= 0; $i--) {
            if ($iso >= self::ERAS[$i]['start']) {
                return $i;
            }
        }
        return -1;
    }
}