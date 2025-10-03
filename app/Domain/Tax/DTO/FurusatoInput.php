<?php
namespace App\Domain\Tax\DTO;

final class FurusatoInput
{
    public function __construct(
        public int $w17,   // 計算結果!W17
        public int $w18,   // 計算結果!W18
        public int $ab6,   // 計算結果!AB6
        public int $ab56,  // 計算結果!AB56（vol23）
    ) {}
}