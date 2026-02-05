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
        $total = count($keys);
        $idx = 0;
        foreach ($keys as $key) {
            $idx++;
            $obj  = $this->reports->resolve((string)$key);
            // ★bundle側から report_key を渡して *_curr などの分岐を有効化する
            $ctxPerKey = array_merge($context, ['report_key' => (string)$key]);
            if (method_exists($obj, 'buildViewDataWithContext')) {
                /** @var array $vars */
                $vars = $obj->buildViewDataWithContext($data, $ctxPerKey);
            } else {
                $vars = $obj->buildViewData($data);
            }
            $view = $obj->viewName();

            // bundle文脈＋PDFフラグ
            $vars = array_merge($vars, $context, ['is_pdf' => true]);

            // ここでは「HTMLだけ」生成
            $html = view($view, $vars)->render();

            // DomPDFの改ページ（最後は付けない＝空白ページ抑止）
            $break = ($idx < $total) ? 'page-break-after: always;' : '';
            $pages[] = '<div style="' . $break . '">' . $html . '</div>';
        }

        // 最後の page-break を軽減（厳密ではないが空白ページが出にくい）
        $body = implode("\n", $pages);

        // 連結HTMLを最低限のHTMLとして包む
        $doc = '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $body . '</body></html>';

        return $this->renderer->renderHtmlToString($doc, $options);
    }
}