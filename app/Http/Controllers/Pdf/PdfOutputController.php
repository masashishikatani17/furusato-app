<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Data;
use Illuminate\Support\Facades\Auth;
use App\Services\Pdf\ReportRegistry;
use App\Services\Pdf\PdfRenderer;
use App\Reports\Contracts\BundleReportInterface;

class PdfOutputController extends Controller
{
    public function __construct(
        private ReportRegistry $reports,
        private PdfRenderer $renderer,
    ) {}

    /** HTMLプレビュー: GET /pdf/{report}/preview?data_id=◯◯ */
    public function preview(Request $request, string $report)
    {
        $data = $this->resolveAuthorizedDataOrFail($request);
        $reportObj = $this->reports->resolve($report);
        $vars = $reportObj->buildViewData($data);
        return view($reportObj->viewName(), $vars);
    }

    /** PDFダウンロード: GET /pdf/{report}?data_id=◯◯ */
    public function download(Request $request, string $report)
    {
        $data = $this->resolveAuthorizedDataOrFail($request);
        $reportObj = $this->reports->resolve($report);

        // Bundle（1本HTML → 複数ページPDF）の場合
        if ($reportObj instanceof BundleReportInterface) {
            $context = [
                // 当年度ワンストップ（input側からクエリで渡す）
                'one_stop_flag_curr' => (string)$request->query('one_stop_flag_curr', '1'),
            ];

            $keys = $reportObj->bundleKeys($data, $context);
            $pages = [];
            $styleTags = [];
            foreach ($keys as $key) {
                $view = null;
                try {
                    $obj = $this->reports->resolve((string)$key);
                    $vars = $obj->buildViewData($data);
                    $view = $obj->viewName();

                    // DomPDF向け：layout側で public_path を選べるようにする
                    $vars = array_merge($vars, ['is_pdf' => true]);

                    // まずHTMLとしてレンダリング
                    $html = view($view, $vars)->render();

                    // style を収集
                    if (preg_match_all('~<style\\b[^>]*>.*?</style>~siu', $html, $m)) {
                        foreach ($m[0] as $tag) {
                            // layoutベース(style data-pdf-base="1")は bundle に混ぜない
                            if (preg_match('~data-pdf-base\\s*=\\s*["\\\']1["\\\']~i', $tag)) {
                                continue;
                            }
                            $styleTags[] = $tag;
                        }
                    }

                    // 1ページ分として <main class="page">...</main> を「タグごと」抽出
                    //    bundle側でページ単位の原点(.pageの中央寄せ/relative)を復元する
                    $pageHtml = null;
                    if (preg_match('~(<main\\s+class="page"[^>]*>.*?</main>)~siu', $html, $mm)) {
                        $pageHtml = $mm[1];
                    } elseif (preg_match('~<body[^>]*>(.*?)</body>~siu', $html, $mm2)) {
                        // 念のため：bodyしか取れない場合は main.page で包む
                        $pageHtml = '<main class="page">' . $mm2[1] . '</main>';
                    } else {
                        $pageHtml = '<main class="page">' . $html . '</main>';
                    }
                    $pages[] = $pageHtml;
                } catch (\Throwable $e) {
                    throw new \RuntimeException(
                        'Bundle HTML render failed: report_key=' . (string)$key
                        . ' view=' . (string)($view ?? 'unknown')
                        . ' err=' . get_class($e) . ' msg=' . $e->getMessage(),
                        0,
                        $e
                    );
                }
            }

            // style 重複排除（順序維持）
            $uniq = [];
            $bundleStyles = [];
            foreach ($styleTags as $tag) {
                $h = md5($tag);
                if (isset($uniq[$h])) continue;
                $uniq[$h] = true;
                $bundleStyles[] = $tag;
            }

            $file = $reportObj->bundleFileName($data, $context);
            $pdf = $this->renderer->render(
                'pdf/furusato_bundle',
                ['pages' => $pages, 'bundle_styles' => $bundleStyles],
                ['paper' => 'a4', 'orient' => 'landscape']
            );
            return $pdf->download($file);
        }
        $vars = $reportObj->buildViewData($data);
        $view = $reportObj->viewName();
        $file = $reportObj->fileName($data);
        // 帳票ごとの paper/orient を反映（無ければ既定）
        $options = [];
        if (method_exists($reportObj, 'pdfOptions')) {
            $options = (array)$reportObj->pdfOptions($data);
        }
        $pdf = $this->renderer->render($view, $vars, $options);
        return $pdf->download($file);
    }

    /** 親ファースト：Dataのview認可（会社一致＋必要なら部署一致） */
    private function resolveAuthorizedDataOrFail(Request $request): Data
    {
        $id = (int) $request->query('data_id');
        abort_unless($id > 0, 422, 'data_id が指定されていません。');
        $data = Data::with('guest')->findOrFail($id);
        $me = Auth::user();
        if ((int)$data->company_id !== (int)$me->company_id) abort(403);
        $role = strtolower((string)($me->role ?? ''));
        $isOwnerOrRegistrar = (method_exists($me, 'isOwner') && $me->isOwner()) || in_array($role, ['owner','registrar'], true);
        if (!$isOwnerOrRegistrar && (int)$data->group_id !== (int)($me->group_id ?? 0)) abort(403);
        return $data;
    }
}