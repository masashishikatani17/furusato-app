<?php

namespace App\Reports\Cover;

use App\Models\Data;
use App\Reports\Contracts\ReportInterface;

class HyoshiReport implements ReportInterface
{
    public function viewName(): string
    {
        // Blade名は番号付き（A案）
        return 'pdf/0_hyoshi';
    }

    public function buildViewData(Data $data): array
    {
        // Data表示は将来埋める前提で “いったん空欄” を渡す
        return [
            'title' => '表紙',
            'cover_guest_name' => '',
            'cover_date'       => '',
            'cover_org'        => '',
            'data_id'          => (int)$data->id,
        ];
    }

    public function fileName(Data $data): string
    {
        return "表紙_data{$data->id}.pdf";
    }

    // 全帳票：6_jintekikojosatyosei と同じ（A4横）
    public function pdfOptions(Data $data): array
    {
        return [
            'paper'  => 'a4',
            'orient' => 'landscape',
        ];
    }
}


