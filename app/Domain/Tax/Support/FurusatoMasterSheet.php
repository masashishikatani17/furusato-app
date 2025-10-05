<?php

namespace App\Domain\Tax\Support;

/**
 * Excelの A1:AA20 を CSV から読み込み、2次元配列で返す。
 * 期待するCSV: UTF-8 / カンマ区切り / ダブルクオート囲み
 */
final class FurusatoMasterSheet
{
    public static function csvPath(): string
    {
        return resource_path('masters/furusato/master_A1_AA20.csv');
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function grid(): array
    {
        $path = self::csvPath();
        if (! is_file($path)) {
            return self::placeholder();
        }

        $grid = [];
        $h = fopen($path, 'r');
        if ($h === false) {
            return self::placeholder();
        }
        while (($row = fgetcsv($h)) !== false) {
            // すべて文字列に正規化（フォーマット崩れ防止）
            $grid[] = array_map(static function ($v): string {
                if ($v === null) return '';
                return (string)$v;
            }, $row);
        }
        fclose($h);

        return $grid;
    }

    /**
     * CSVが未配置のときに表示する簡易グリッド（1行）
     * @return array<int, array<int, string>>
     */
    private static function placeholder(): array
    {
        return [
            ['※ CSV 未配置: resources/masters/furusato/master_A1_AA20.csv をExcelから書き出して配置してください（A1:AA20）'],
        ];
    }
}