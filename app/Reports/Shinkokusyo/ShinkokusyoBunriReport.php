<?php

namespace App\Reports\Shinkokusyo;

use App\Models\Data;
use App\Reports\Contracts\ReportInterface;

class ShinkokusyoBunriReport implements ReportInterface
{
    public function viewName(): string
    {
        return 'pdf/shinkokusyo_bunri';
    }

    public function buildViewData(Data $data): array
    {
        $guestName = $data->guest?->name ?? '（名称未登録）';
        $year = (int)($data->kihu_year ?? now()->year);
        return [
            'title'      => '確定申告書（分離課税用）',
            'year'       => $year,
            'guest_name' => $guestName,
            'data_id'    => (int)$data->id,
        ];
    }

    public function fileName(Data $data): string
    {
        $guest = $data->guest?->name ?? '名称未登録';
        $year  = (int)($data->kihu_year ?? now()->year);
        return "確定申告書_分離課税_{$year}_{$guest}_data{$data->id}.pdf";
    }
}