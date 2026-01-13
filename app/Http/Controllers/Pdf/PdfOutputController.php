<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Data;
use Illuminate\Support\Facades\Auth;
use App\Services\Pdf\ReportRegistry;
use App\Services\Pdf\PdfRenderer;
use App\Reports\Contracts\BundleReportInterface;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

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

        // Bundle の場合：骨組みHTML（即返却）＋JSONでページHTMLを一括取得して埋め込む
        if ($reportObj instanceof BundleReportInterface) {
            $context = [
                // 当年度ワンストップ（input側からクエリで渡す）
                'one_stop_flag_curr' => (string)$request->query('one_stop_flag_curr', '1'),
            ];

            $keys = $reportObj->bundleKeys($data, $context);
            // 念のため：重複/空/自己参照を除外して順序維持
            $keys = array_values(array_filter(array_map('strval', $keys), function ($k) use ($report) {
                return $k !== '' && strtolower($k) !== strtolower($report);
            }));
            $keys = array_values(array_unique($keys));

            // ★JSON要求：ここで各帳票HTMLを一括生成して返す（iframeのsrcdoc用）
            if ((string)$request->query('format') === 'json') {
                $t0 = microtime(true);
                $mem0 = function_exists('memory_get_usage') ? memory_get_usage(true) : null;
                $pages = [];
                foreach ($keys as $key) {
                    $view = null;
                    try {
                        $tKey0 = microtime(true);
                        $obj  = $this->reports->resolve((string)$key);
                        $tBuild0 = microtime(true);
                        $vars = $obj->buildViewData($data);
                        $tBuild1 = microtime(true);
                        $view = $obj->viewName();
                        $vars = array_merge($vars, $context);
                        $tRender0 = microtime(true);
                        $html = view($view, $vars)->render();
                        $tRender1 = microtime(true);
                        $pages[] = $html;

                        Log::info('pdf.bundle.preview.page_timing', [
                            'report'   => (string)$report,
                            'data_id'  => (int)$data->id,
                            'key'      => (string)$key,
                            'view'     => (string)$view,
                            'build_ms' => (int)round(($tBuild1 - $tBuild0) * 1000),
                            'render_ms'=> (int)round(($tRender1 - $tRender0) * 1000),
                            'total_ms' => (int)round((microtime(true) - $tKey0) * 1000),
                            'bytes'    => strlen($html),
                        ]);
                    } catch (\Throwable $e) {
                        throw new \RuntimeException(
                            'Bundle HTML preview build failed: report_key=' . (string)$key
                            . ' view=' . (string)($view ?? 'unknown')
                            . ' err=' . get_class($e) . ' msg=' . $e->getMessage(),
                            0,
                            $e
                        );
                    }
                }

                $t1 = microtime(true);
                $mem1 = function_exists('memory_get_usage') ? memory_get_usage(true) : null;
                Log::info('pdf.bundle.preview.total_timing', [
                    'report'    => (string)$report,
                    'data_id'   => (int)$data->id,
                    'keys'      => $keys,
                    'pages'     => count($pages),
                    'total_ms'  => (int)round(($t1 - $t0) * 1000),
                    'mem_mb'    => is_null($mem1) ? null : round($mem1 / 1024 / 1024, 1),
                    'mem_delta_mb' => (is_null($mem0) || is_null($mem1)) ? null : round(($mem1 - $mem0) / 1024 / 1024, 1),
                ]);

                return response()->json([
                    'keys'  => $keys,
                    'pages' => $pages,
                ]);
            }

            // ★通常：骨組みだけ即返す（ここで重いHTML生成はしない）
            $pagesUrl = route('pdf.preview', ['report' => (string)$report])
                . '?data_id=' . urlencode((string)$data->id)
                . '&one_stop_flag_curr=' . urlencode((string)$context['one_stop_flag_curr'])
                . '&format=json';

            return view('pdf.bundle_preview', [
                'data_id'    => (int)$data->id,
                'report'     => (string)$report,
                'keys'       => $keys,
                'context'    => $context,
                'pages_url'  => $pagesUrl,
            ]);
        }
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

            //  先にファイル名を確定（後段の response ヘッダで使う）
            $file = $reportObj->bundleFileName($data, $context);

            $keys = $reportObj->bundleKeys($data, $context);

            // 念のため：重複/空/自己参照を除外して順序維持
            $keys = array_values(array_filter(array_map('strval', $keys), function ($k) use ($report) {
                return $k !== '' && strtolower($k) !== strtolower($report);
            }));
            $keys = array_values(array_unique($keys));

            //  自己参照・重複を除去（重複の主原因になりがち）
            $keys = array_values(array_filter(array_map('strval', $keys), function ($k) use ($report) {
                return $k !== '' && $k !== $report; // $report === 'furusato_bundle' 自身を除外
            }));
            $keys = array_values(array_unique($keys));

            Log::debug('PDF bundle: keys', [
                'report'  => $report,
                'data_id' => $data->id,
                'context' => $context,
                'keys'    => $keys,
            ]);

            // 単体PDFを生成→FPDIで結合（単体表示と“完全一致”させる）
            $fpdi = new Fpdi();
            $fpdi->SetAutoPageBreak(false);
            $fpdi->SetMargins(0, 0, 0);

            foreach ($keys as $key) {
                $view = null;
                try {
                    $obj  = $this->reports->resolve((string)$key);
                    $vars = $obj->buildViewData($data);
                    $view = $obj->viewName();

                    // bundleの文脈を各帳票に渡す（必要な帳票があるため）
                    $vars = array_merge($vars, $context, ['is_pdf' => true]);

                    // 帳票ごとの paper/orient を反映（無ければ config 定義から拾う）
                    $options = [];
                    if (method_exists($obj, 'pdfOptions')) {
                        $options = (array)$obj->pdfOptions($data);
                    } else {
                        $cfg = (array) config('pdf_reports', []);
                        $entry = $cfg[strtolower((string)$key)] ?? null;
                        if (is_array($entry)) {
                            if (!empty($entry['paper']))  $options['paper']  = (string)$entry['paper'];
                            if (!empty($entry['orient'])) $options['orient'] = (string)$entry['orient'];
                        }
                    }

                    // ★単体PDFを生成（engine=chrome ならブラウザ同等、engine=dompdf なら従来通り）
                    $pdfStr = $this->renderer->renderToString($view, $vars, $options);

                    // FPDIでページを取り込み結合
                    $pageCount = $fpdi->setSourceFile(StreamReader::createByString($pdfStr));
                    Log::debug('PDF bundle: part', [
                        'key'       => (string)$key,
                        'view'      => (string)$view,
                        'pageCount' => (int)$pageCount,
                        'bytes'     => strlen($pdfStr),
                        'options'   => $options,
                    ]);
                    for ($i = 1; $i <= $pageCount; $i++) {
                        $tpl  = $fpdi->importPage($i);
                        $size = $fpdi->getTemplateSize($tpl);
                        $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $fpdi->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);
                    }
                } catch (\Throwable $e) {
                    throw new \RuntimeException(
                        'Bundle PDF merge failed: report_key=' . (string)$key
                        . ' view=' . (string)($view ?? 'unknown')
                        . ' err=' . get_class($e) . ' msg=' . $e->getMessage(),
                        0,
                        $e
                    );
                }
            }

            $merged = $fpdi->Output('S');
            return response($merged, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $file . '"',
            ]);
        }
        $vars = $reportObj->buildViewData($data);
        $view = $reportObj->viewName();
        $file = $reportObj->fileName($data);
        // 帳票ごとの paper/orient を反映（無ければ既定）
        $options = [];
        if (method_exists($reportObj, 'pdfOptions')) {
            $options = (array)$reportObj->pdfOptions($data);
        }
        $pdfStr = $this->renderer->renderToString($view, $vars, $options);
        return response($pdfStr, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
        ]);
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