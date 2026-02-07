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
        // 表紙テンプレWriter（FurusatoHyoshiTemplateWriter）が期待するキーで返す
        // - fast bundle では Controller の hyoshi 特例を通らず、Report::buildViewData がそのまま Writer に渡るため
        // - 互換のため cover_* も残す
        $guest = (string)($data->guest?->name ?? '');
        $dateIso = (string)($data->proposal_date ?? $data->data_created_on ?? now()->toDateString());
        $dateWareki = \App\Support\WarekiDate::format($dateIso);
        $date = $dateWareki !== '' ? $dateWareki : $dateIso;

        return [
            'title' => '表紙',
            // Writer 用（★こちらが本命）
            'guest_name' => $guest !== '' ? ($guest . '様') : '',
            'date'       => $date,
            'org'        => '',
            // 互換（Blade側が cover_* を見ている場合の保険）
            'cover_guest_name' => $guest !== '' ? ($guest . '様') : '',
            'cover_date'       => $date,
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


