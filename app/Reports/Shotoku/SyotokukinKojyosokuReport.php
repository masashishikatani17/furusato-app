<?php

namespace App\Reports\Shotoku;

use App\Models\Data;
use App\Reports\Contracts\ReportInterface;

class SyotokukinKojyosokuReport implements ReportInterface
{
    public function viewName(): string
    {
        // Blade名は番号付き、URLキーは数字なし（A案）
        return 'pdf/2_syotokukinkojyosoku';
    }

    public function buildViewData(Data $data): array
    {
        // 現時点では帳票内の数値・文言は固定表示（送付Blade通り）
        $guestName = $data->guest?->name ?? '（名称未登録）';
        $year = (int)($data->kihu_year ?? now()->year);
        return [
            'title'      => '所得金額・所得控除額の予測',
            'year'       => $year,
            'guest_name' => $guestName,
            'data_id'    => (int)$data->id,
        ];
    }

    public function fileName(Data $data): string
    {
        $year = (int)($data->kihu_year ?? now()->year);
        return "所得金額_所得控除額予測_{$year}_data{$data->id}.pdf";
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

