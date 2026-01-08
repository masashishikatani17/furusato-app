<?php

namespace App\Reports\Kifukin;

use App\Models\Data;
use App\Reports\Contracts\ReportInterface;

class KifukinGendogakuReport implements ReportInterface
{
    public function viewName(): string
    {
        // Blade名は番号付き、URLキーは番号なし（A案）
        return 'pdf/1_kifukingendogaku';
    }

    public function buildViewData(Data $data): array
    {
        // 現時点では帳票内の数値・文言は固定表示（送付Blade通り）
        // 将来の差し込み用に最低限だけ渡す
        $guestName = $data->guest?->name ?? '（名称未登録）';
        $year = (int)($data->kihu_year ?? now()->year);
        return [
            'title'      => '寄附金上限額',
            'year'       => $year,
            'guest_name' => $guestName,
            'data_id'    => (int)$data->id,
        ];
    }

    public function fileName(Data $data): string
    {
        $year = (int)($data->kihu_year ?? now()->year);
        return "寄附金上限額_{$year}_data{$data->id}.pdf";
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


