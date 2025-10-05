<?php

namespace App\Domain\Tax\Support;

final class FurusatoMasterCatalog
{
    /**
     * @return array<int, array<string, string|int|float|null>>
     */
    public static function all(): array
    {
        return [
            [
                'code' => 'A001',
                'name' => '基本控除',
                'category' => '基礎控除',
                'rate' => '10%',
                'amount' => '430,000',
                'notes' => '課税所得に応じた基礎控除',
            ],
            [
                'code' => 'A102',
                'name' => '給与所得控除',
                'category' => '所得控除',
                'rate' => '14%〜24%',
                'amount' => '1,950,000',
                'notes' => '給与所得者向けの控除',
            ],
            [
                'code' => 'B210',
                'name' => 'ふるさと納税（特例控除）',
                'category' => '税額控除',
                'rate' => '住民税20%上限',
                'amount' => '自己負担2,000円超',
                'notes' => '寄附金税額控除の特例分',
            ],
            [
                'code' => 'C305',
                'name' => '住宅ローン控除',
                'category' => '税額控除',
                'rate' => '1%',
                'amount' => '400,000',
                'notes' => '住宅借入金等特別控除',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function columns(): array
    {
        return [
            'code' => 'コード',
            'name' => '名称',
            'category' => '区分',
            'rate' => '率・区分',
            'amount' => '上限額等',
            'notes' => '備考',
        ];
    }
}