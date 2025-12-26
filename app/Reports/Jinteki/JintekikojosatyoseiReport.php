<?php

namespace App\Reports\Jinteki;

use App\Models\Data;
use App\Reports\Contracts\ReportInterface;

class JintekikojosatyoseiReport implements ReportInterface
{
    public function viewName(): string
    {
        // resources/views/pdf/jintekikojosatyosei.blade.php
        return 'pdf/jintekikojosatyosei';
    }

    public function buildViewData(Data $data): array
    {
        $guestName = $data->guest?->name ?? '（名称未登録）';
        $year = (int)($data->kihu_year ?? now()->year);
        return [
            'title'      => '人的控除差調整額',
            'year'       => $year,
            'guest_name' => $guestName,
            'data_id'    => (int)$data->id,
        ];
    }

    public function fileName(Data $data): string
    {
        $guest = $data->guest?->name ?? '名称未登録';
        $year  = (int)($data->kihu_year ?? now()->year);
        return "人的控除差調整額_{$year}_{$guest}_data{$data->id}.pdf";
    }

    /** PdfOutputController が存在確認して PdfRenderer に渡す（任意） */
    public function pdfOptions(Data $data): array
    {
        return [
            'paper'  => 'a4',
            'orient' => 'landscape',
        ];
    }
}
