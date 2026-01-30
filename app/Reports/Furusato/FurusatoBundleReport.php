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
        $variant = strtolower((string)($context['pdf_variant'] ?? 'max')); // max|current|both
        
        // 1ページ目（表紙/上限額）は常に共通で1回だけ
        $keys = [
            'hyoshi',
            'kifukingendogaku',
        ];

        // 2〜4ページ（条件で切替・bothは2枚ずつ）
        $page4Max = ($oneStop === '1') ? 'juminkeigengaku_onestop' : 'juminkeigengaku';
        $page4Curr = ($oneStop === '1') ? 'juminkeigengaku_onestop_curr' : 'juminkeigengaku_curr';

        if ($variant === 'current') {
            $keys[] = 'syotokukinkojyosoku_curr';
            $keys[] = 'kazeigakuzeigakuyosoku_curr';
            $keys[] = $page4Curr;
        } elseif ($variant === 'both') {
            // 2→3→4 を「今まで→上限」の順で並べる
            $keys[] = 'syotokukinkojyosoku_curr';
            $keys[] = 'syotokukinkojyosoku';
            $keys[] = 'kazeigakuzeigakuyosoku_curr';
            $keys[] = 'kazeigakuzeigakuyosoku';
            $keys[] = $page4Curr;
            $keys[] = $page4Max;
        } else {
            // 既定：上限額まで寄付（従来）
            $keys[] = 'syotokukinkojyosoku';
            $keys[] = 'kazeigakuzeigakuyosoku';
            $keys[] = $page4Max;
        }

        // 5〜7ページは常に共通で1回だけ
        $keys[] = 'sonntokusimulation';
        $keys[] = 'jintekikojosatyosei';
        $keys[] = 'tokureikojowariai';

        return $keys;
    }

    public function bundleFileName(Data $data, array $context = []): string
    {
        $year = (int)($data->kihu_year ?? now()->year);
        // ★重要：mode=fast の生成物キャッシュが「ファイル名（or その派生キー）」を使うことが多い。
        // pdf_variant を含めないと current/both でも max の生成物が流用される。
        $variant = strtolower((string)($context['pdf_variant'] ?? 'max')); // max|current|both
        if (!in_array($variant, ['max','current','both'], true)) {
            $variant = 'max';
        }
        // one_stop も同様にキーに含めておく（混線防止）
        $oneStop = (string)($context['one_stop_flag_curr'] ?? '1');
        $oneStopTag = ($oneStop === '1') ? 'onestop' : 'normal';

        return "ふるさと納税_帳票一括_{$year}_{$oneStopTag}_{$variant}_data{$data->id}.pdf";
    }
}
