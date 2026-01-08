<?php

namespace App\Reports\Kazei;

use App\Models\Data;
use App\Reports\Contracts\ReportInterface;

class KazeigakuZeigakuYosokuReport implements ReportInterface
{
    public function viewName(): string
    {
        // Blade名は番号付き、URLキーは数字なし（A案）
        return 'pdf/3_kazeigakuzeigakuyosoku';
    }

    public function buildViewData(Data $data): array
    {
        // 現時点では帳票内の数値・文言は固定表示（送付Blade通り）
        $guestName = $data->guest?->name ?? '（名称未登録）';
        $year = (int)($data->kihu_year ?? now()->year);
        return [
            'title'      => '課税所得金額・税額の予測',
            'year'       => $year,
            'guest_name' => $guestName,
            'data_id'    => (int)$data->id,
        ];
    }

    public function fileName(Data $data): string
    {
        $year = (int)($data->kihu_year ?? now()->year);
        return "課税所得金額_税額予測_{$year}_data{$data->id}.pdf";
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


