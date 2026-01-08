<?php

namespace App\Reports\Tokurei;

use App\Models\Data;
use App\Reports\Contracts\ReportInterface;

class TokureiKojowariaiReport implements ReportInterface
{
    public function viewName(): string
    {
        // Blade名は番号付き、URLキーは番号なし（A案）
        return 'pdf/7_tokureikojowariai';
    }

    public function buildViewData(Data $data): array
    {
        // 表示上は Data を使わないが、共通の変数は渡しておく（将来拡張に備える）
        $guestName = $data->guest?->name ?? '（名称未登録）';
        $year = (int)($data->kihu_year ?? now()->year);
        return [
            'title'      => '特例控除割合',
            'year'       => $year,
            'guest_name' => $guestName,
            'data_id'    => (int)$data->id,
        ];
    }

    public function fileName(Data $data): string
    {
        $guest = $data->guest?->name ?? '名称未登録';
        $year  = (int)($data->kihu_year ?? now()->year);
        return "特例控除割合_{$year}_{$guest}_data{$data->id}.pdf";
    }

    // 6_jintekikojosatyosei と同じ用紙（A4横）
    public function pdfOptions(Data $data): array
    {
        return [
            'paper'  => 'a4',
            'orient' => 'landscape',
        ];
    }
}
