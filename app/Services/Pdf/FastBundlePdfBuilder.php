<?php

namespace App\Services\Pdf;

use App\Models\Data;
use App\Reports\Contracts\BundleReportInterface;

class FastBundlePdfBuilder
{
    public function __construct(
        private readonly ReportRegistry $reports,
        private readonly PdfRenderer $renderer,
    ) {}

    /**
     * bundleKeys で列挙される各レポートをHTMLで描画し、1つに連結して1回でPDF化する。
     */
    public function buildBundlePdf(Data $data, BundleReportInterface $bundle, array $context, array $options): string
    {
        $keys = $bundle->bundleKeys($data, $context);
        $keys = array_values(array_filter(array_map('strval', $keys), fn($k) => $k !== ''));
        $keys = array_values(array_unique($keys));
 
        return $this->buildBundlePdfForKeys($data, $keys, $context, $options);
    }

    /**
     * 指定キーだけを束ねて 1回でPDF化（dompdf 1回）
     *
     * @param array<int,string> $keys
     */
    public function buildBundlePdfForKeys(Data $data, array $keys, array $context, array $options): string
    {
        $pages = [];
        foreach ($keys as $key) {
            $obj  = $this->reports->resolve((string)$key);
            $vars = $obj->buildViewData($data);
            $view = $obj->viewName();

            // bundle文脈＋PDFフラグ
            $vars = array_merge($vars, $context, ['is_pdf' => true]);

            // ここでは「HTMLだけ」生成
            $html = view($view, $vars)->render();

            // DomPDFの改ページ
            $pages[] = '<div style="page-break-after: always;">' . $html . '</div>';
        }

        // 最後の page-break を軽減（厳密ではないが空白ページが出にくい）
        $body = implode("\n", $pages);

        // 連結HTMLを最低限のHTMLとして包む
        $doc = '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $body . '</body></html>';

        return $this->renderer->renderHtmlToString($doc, $options);
    }
}