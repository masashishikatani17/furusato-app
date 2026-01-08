<?php

namespace App\Reports\Furusato;

use App\Models\Data;
use App\Reports\Contracts\ReportInterface;
use App\Reports\Contracts\BundleReportInterface;

/**
 * ふるさと帳票一括PDF（0〜7を結合）
 * - 4ページは当年度(one_stop_flag_curr)により通常/ワンストップを切替
 */
class FurusatoBundleReport implements ReportInterface, BundleReportInterface
{
    public function viewName(): string
    {
        // preview は使わない想定だが、必須なのでダミー
        return 'pdf/0_hyoshi';
    }

    public function buildViewData(Data $data): array
    {
        // bundle は PDF生成専用（view は使わない）
        return [
            'data_id' => (int)$data->id,
        ];
    }

    public function fileName(Data $data): string
    {
        // download側で bundleFileName を使うため、ここはフォールバック
        return "furusato_bundle_data{$data->id}.pdf";
    }

    public function bundleKeys(Data $data, array $context = []): array
    {
        $oneStop = (string)($context['one_stop_flag_curr'] ?? '1'); // '1'=利用する, '0'=利用しない

        $keys = [
            'hyoshi',               // 0_hyoshi
            'kifukingendogaku',      // 1_kifukingendogaku
            'syotokukinkojyosoku',   // 2_syotokukinkojyosoku
            'kazeigakuzeigakuyosoku',// 3_kazeigakuzeigakuyosoku
            // 4 は条件で切替
            ($oneStop === '1') ? 'juminkeigengaku_onestop' : 'juminkeigengaku',
            'sonntokusimulation',    // 5_sonntokusimulation
            'jintekikojosatyosei',   // 6_jintekikojosatyosei
            'tokureikojowariai',     // 7_tokureikojowariai
        ];

        return $keys;
    }

    public function bundleFileName(Data $data, array $context = []): string
    {
        $year = (int)($data->kihu_year ?? now()->year);
        return "ふるさと納税_帳票一括_{$year}_data{$data->id}.pdf";
    }
}
