<?php

namespace App\Reports\Contracts;

use App\Models\Data;

interface ReportInterface
{
    /** 使用する Blade ビュー名（例：'pdf/shinkokusyo_bunri'） */
    public function viewName(): string;

    /** ビューへ渡すデータ配列を構築（Presenter/集計を済ませる） */
    public function buildViewData(Data $data): array;

    /** ダウンロード時のファイル名（UTF-8想定／日本語可） */
    public function fileName(Data $data): string;
}