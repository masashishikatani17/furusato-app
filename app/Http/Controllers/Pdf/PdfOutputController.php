<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Data;
use App\Models\FurusatoResult;
use Illuminate\Support\Facades\Auth;
use App\Services\Pdf\ReportRegistry;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pdf\PdfCacheService;
use App\Services\Pdf\FastBundlePdfBuilder;
use App\Services\Pdf\Templates\FurusatoHyoshiTemplateWriter;
use App\Services\Pdf\Templates\FurusatoKifukinGendogakuTemplateWriter;
use App\Services\Pdf\Templates\FurusatoSyotokukinKojyosokuTemplateWriter;
use App\Services\Pdf\Templates\FurusatoKazeigakuZeigakuYosokuTemplateWriter;
use App\Services\Pdf\Templates\FurusatoJuminKeigengakuTemplateWriter;
use App\Services\Pdf\Templates\FurusatoSonntokuSimulationTemplateWriter;
use App\Services\Pdf\Templates\FurusatoJintekiKojoSaTyoseiTemplateWriter;
use App\Services\Pdf\Templates\FurusatoTokureiKojowariaiTemplateWriter;
use App\Domain\Tax\Services\FurusatoSonntokuSimulationService;
use App\Domain\Tax\Factory\SyoriSettingsFactory;
use App\Models\FurusatoInput;
use App\Jobs\Pdf\BuildFurusatoBundlePdfCacheJob;
use App\Reports\Contracts\BundleReportInterface;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class PdfOutputController extends Controller
{
    public function __construct(
        private ReportRegistry $reports,
        private PdfRenderer $renderer,
        private PdfCacheService $cache,
        private FastBundlePdfBuilder $fastBuilder,
    ) {}
 
    /**
     * ステータス（JSON）
     * GET /pdf/{report}/status?data_id=...&one_stop_flag_curr=...&mode=fast&engine=dompdf
     */
    public function status(Request $request, string $report): JsonResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($request);

        // 標準：fast + dompdf（必要ならクエリで切替）
        $variant = strtolower((string)$request->query('pdf_variant', 'max'));
        if (!in_array($variant, ['max','current','both'], true)) {
            $variant = 'max';
        }

        $context = [
            'one_stop_flag_curr' => (string)$request->query('one_stop_flag_curr', '1'),
            'pdf_variant'        => $variant,
            'mode' => (string)$request->query('mode', 'fast'),
            'engine' => (string)$request->query('engine', 'dompdf'),
        ];
        $cacheKey = $this->cache->cacheKey($report, $data, $context);

        // - download側で未生成なら同期生成→キャッシュ保存する
        if (
            in_array(strtolower($report), [
                'hyoshi',
                'kifukingendogaku',
                'syotokukinkojyosoku',
                'kazeigakuzeigakuyosoku',
                'juminkeigengaku',
                'juminkeigengaku_onestop',
                'sonntokusimulation',
                'jintekikojosatyosei',
                'tokureikojowariai',
            ], true)
            || (strtolower($report) === 'furusato_bundle' && ((string)$request->query('mode','') === 'fast'))
        ) {
            $downloadUrl = route('pdf.download', ['report' => $report])
                . '?data_id=' . urlencode((string)$data->id)
                . '&one_stop_flag_curr=' . urlencode((string)$context['one_stop_flag_curr'])
                . '&pdf_variant=' . urlencode((string)$context['pdf_variant'])
                . '&mode=' . urlencode((string)$context['mode'])
                . '&engine=' . urlencode((string)$context['engine']);
            return response()->json([
                'status' => 'ready',
                'cache_key' => $cacheKey,
                'download_url' => $downloadUrl,
                'message' => null,
            ]);
        }

        // 実ファイルがあれば ready 扱い
        if ($this->cache->exists($cacheKey)) {
            $this->cache->setStatus($cacheKey, ['status' => 'ready']);
        }

        $st = $this->cache->getStatus($cacheKey);
        $downloadUrl = route('pdf.download', ['report' => $report])
            . '?data_id=' . urlencode((string)$data->id)
            . '&one_stop_flag_curr=' . urlencode((string)$context['one_stop_flag_curr'])
            . '&pdf_variant=' . urlencode((string)$context['pdf_variant'])
            . '&mode=' . urlencode((string)$context['mode'])
            . '&engine=' . urlencode((string)$context['engine']);

        return response()->json([
            'status' => (string)($st['status'] ?? 'none'),
            'cache_key' => $cacheKey,
            'download_url' => $downloadUrl,
            'message' => $st['message'] ?? null,
        ]);
    }

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
                // PDFの出力条件（max|current|both）
                'pdf_variant'        => (string)$request->query('pdf_variant', 'max'),
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
                        // ★ report_key を context に含めて渡す（_curr の判定などに使用）
                        $ctxPerKey = array_merge($context, ['report_key' => (string)$key]);
                        if (method_exists($obj, 'buildViewDataWithContext')) {
                            /** @var array $vars */
                            $vars = $obj->buildViewDataWithContext($data, $ctxPerKey);
                        } else {
                            $vars = $obj->buildViewData($data);
                        }
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
                . '&pdf_variant=' . urlencode((string)$context['pdf_variant'])
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
 
        // 標準：fast + dompdf（必要ならクエリで切替）
        $mode = (string)$request->query('mode', 'fast');
        $engine = (string)$request->query('engine', 'dompdf'); // .env が chrome でもここは dompdf を標準にする
        $variant = strtolower((string)$request->query('pdf_variant', 'max'));
        if (!in_array($variant, ['max','current','both'], true)) {
            $variant = 'max';
        }
        $contextBase = [
            'one_stop_flag_curr' => (string)$request->query('one_stop_flag_curr', '1'),
            'mode' => $mode,
            'engine' => $engine,
            'pdf_variant' => $variant,
        ];
        $cacheKey = $this->cache->cacheKey($report, $data, $contextBase);

        // ============================================================
        // ★ 特例控除割合（tokureikojowariai）：背景テンプレPDF＋mm座標印字（まずは仮置き）
        // ============================================================
        if (strtolower($report) === 'tokureikojowariai') {
            if ($this->cache->exists($cacheKey)) {
                $file = $reportObj->fileName($data);
                $resp = response()->download(
                    $this->cache->absolutePath($cacheKey),
                    $file,
                    ['Content-Type' => 'application/pdf']
                );
                return $this->attachDownloadCookie($request, $resp);
            }

            // ★方針：7ページ（特例控除割合）は「サーバ値を一切載せない」ため、
            //   背景テンプレPDFをそのまま返す（印字処理は行わない）
            $tplPath = resource_path('pdf_templates/furusato/7_tokureikojowariai_bg.pdf');
            if (!is_file($tplPath)) {
                throw new \RuntimeException('Template PDF not found: ' . $tplPath);
            }
            $pdfStr = file_get_contents($tplPath);
            if (!is_string($pdfStr) || $pdfStr === '') {
                throw new \RuntimeException('Failed to read template PDF: ' . $tplPath);
            }

            $this->cache->put($cacheKey, $pdfStr);

            $file = $reportObj->fileName($data);
            $resp = response($pdfStr, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $file . '"',
            ]);
            return $this->attachDownloadCookie($request, $resp);
        }

        // ============================================================
        // ★ 人的控除差調整額（jintekikojosatyosei）：背景テンプレPDF＋mm座標印字（まずは仮置き）
        // ============================================================
        if (strtolower($report) === 'jintekikojosatyosei') {
            if ($this->cache->exists($cacheKey)) {
                $file = $reportObj->fileName($data);
                $resp = response()->download(
                    $this->cache->absolutePath($cacheKey),
                    $file,
                    ['Content-Type' => 'application/pdf']
                );
                return $this->attachDownloadCookie($request, $resp);
            }

            // 既存Reportの buildViewData() を流用（年/氏名など）
            $vars = $reportObj->buildViewData($data);

            $tplPath  = resource_path('pdf_templates/furusato/6_jintekikojosatyosei_bg.pdf');
            $fontPath = public_path('fonts/ipaexg.ttf');

            $writer = new FurusatoJintekiKojoSaTyoseiTemplateWriter($tplPath, $fontPath);
            // デバッグ印字は一切しない
            $pdfStr = $writer->render($vars);

            $this->cache->put($cacheKey, $pdfStr);

            $file = $reportObj->fileName($data);
            $resp = response($pdfStr, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $file . '"',
            ]);
            return $this->attachDownloadCookie($request, $resp);
        }

        // ============================================================
        // ★ 寄附金額別損得シミュレーション（sonntokusimulation）：背景テンプレPDF＋mm座標印字（まずは仮置き）
        // - Reportは固定値なので、Controller側で sonntoku を組み立てて Writer に渡す
        // ============================================================
        if (strtolower($report) === 'sonntokusimulation') {
            if ($this->cache->exists($cacheKey)) {
                $file = $reportObj->fileName($data);
                $resp = response()->download(
                    $this->cache->absolutePath($cacheKey),
                    $file,
                    ['Content-Type' => 'application/pdf']
                );
                return $this->attachDownloadCookie($request, $resp);
            }

            // SoT payload（優先：FurusatoResult.payload['payload'] → 次点：FurusatoInput.payload）
            $payload = [];
            $stored = FurusatoResult::query()->where('data_id', (int)$data->id)->value('payload');
            if (is_array($stored)) {
                $candidate = $stored['payload'] ?? $stored['upper'] ?? $stored;
                $payload = is_array($candidate) ? $candidate : [];
            }
            if ($payload === []) {
                $inp = FurusatoInput::query()->where('data_id', (int)$data->id)->value('payload');
                $payload = is_array($inp) ? $inp : [];
            }

            /** @var SyoriSettingsFactory $syoriFactory */
            $syoriFactory = app(SyoriSettingsFactory::class);
            $syoriSettings = $syoriFactory->buildInitial($data);

            // ctx（dry-run runner 用）
            $guestBirth = $data->guest?->birth_date ?? null;
            $guestBirthYmd = null;
            if ($guestBirth instanceof \DateTimeInterface) {
                $guestBirthYmd = $guestBirth->format('Y-m-d');
            } elseif (is_string($guestBirth)) {
                $guestBirthYmd = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($guestBirth)) === 1 ? trim($guestBirth) : null;
            }
            $taxpayerSex = data_get($data, 'guest.sex') ?? data_get($data, 'guest.gender') ?? data_get($data, 'guest.sex_code');

            $ctx = [
                'syori_settings'   => $syoriSettings,
                'data'             => $data,
                'data_id'          => (int)$data->id,
                'company_id'       => $data->company_id !== null ? (int)$data->company_id : null,
                'kihu_year'        => $data->kihu_year ? (int)$data->kihu_year : 0,
                'master_kihu_year' => 2025,
                'guest_birth_date' => $guestBirthYmd,
                'taxpayer_sex'     => $taxpayerSex,
            ];

            /** @var FurusatoSonntokuSimulationService $svc */
            $svc = app(FurusatoSonntokuSimulationService::class);
            $sonntoku = $svc->build($payload, $ctx);

            $tplPath  = resource_path('pdf_templates/furusato/5_sonntokusimulation_bg.pdf');
            $fontPath = public_path('fonts/ipaexg.ttf');
            $writer = new FurusatoSonntokuSimulationTemplateWriter($tplPath, $fontPath);
            $pdfStr = $writer->render([
                'sonntoku'   => $sonntoku,
                'show_test'  => true,
            ]);

            $this->cache->put($cacheKey, $pdfStr);

            $file = $reportObj->fileName($data);
            $resp = response($pdfStr, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $file . '"',
            ]);
            return $this->attachDownloadCookie($request, $resp);
        }

        // ============================================================
        // ★ 住民税の軽減額（通常/ワンストップ）：背景テンプレPDF＋mm座標印字（まずは仮置き）
        // ============================================================
        if (in_array(strtolower($report), ['juminkeigengaku', 'juminkeigengaku_onestop'], true)) {
            if ($this->cache->exists($cacheKey)) {
                $file = $reportObj->fileName($data);
                $resp = response()->download(
                    $this->cache->absolutePath($cacheKey),
                    $file,
                    ['Content-Type' => 'application/pdf']
                );
                return $this->attachDownloadCookie($request, $resp);
            }

            // 既存Reportの buildViewData() を流用（数値生成は捨てない）
            $vars = $reportObj->buildViewData($data);

            $tplPath = (strtolower($report) === 'juminkeigengaku_onestop')
                ? resource_path('pdf_templates/furusato/4_juminkeigengaku_onestop_bg.pdf')
                : resource_path('pdf_templates/furusato/4_juminkeigengaku_bg.pdf');
            $fontPath = public_path('fonts/ipaexg.ttf');

            $writer = new FurusatoJuminKeigengakuTemplateWriter($tplPath, $fontPath);
            $pdfStr = $writer->render($vars);

            $this->cache->put($cacheKey, $pdfStr);

            $file = $reportObj->fileName($data);
            $resp = response($pdfStr, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $file . '"',
            ]);
            return $this->attachDownloadCookie($request, $resp);
        }

        // ============================================================
        // 課税所得金額・税額の予測（kazeigakuzeigakuyosoku）：背景テンプレPDF＋mm座標印字（まずは仮置き）
        // ============================================================
        if (strtolower($report) === 'kazeigakuzeigakuyosoku') {
            if ($this->cache->exists($cacheKey)) {
                $file = $reportObj->fileName($data);
                $resp = response()->download(
                    $this->cache->absolutePath($cacheKey),
                    $file,
                    ['Content-Type' => 'application/pdf']
                );
                return $this->attachDownloadCookie($request, $resp);
            }

            // 既存Reportの buildViewData() を流用（数値生成は捨てない）
            $vars = $reportObj->buildViewData($data);

            $tplPath  = resource_path('pdf_templates/furusato/3_kazeigakuzeigakuyosoku_bg.pdf');
            $fontPath = public_path('fonts/ipaexg.ttf');

            $writer = new FurusatoKazeigakuZeigakuYosokuTemplateWriter($tplPath, $fontPath);
            $pdfStr = $writer->render($vars);

            $this->cache->put($cacheKey, $pdfStr);

            $file = $reportObj->fileName($data);
            $resp = response($pdfStr, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $file . '"',
            ]);
            return $this->attachDownloadCookie($request, $resp);
        }

        // ============================================================
        // 所得金額・所得控除額の予測（syotokukinkojyosoku）：背景テンプレPDF＋mm座標印字（まずは仮置き）
        // ============================================================
        if (strtolower($report) === 'syotokukinkojyosoku') {
            if ($this->cache->exists($cacheKey)) {
                $file = $reportObj->fileName($data);
                $resp = response()->download(
                    $this->cache->absolutePath($cacheKey),
                    $file,
                    ['Content-Type' => 'application/pdf']
                );
                return $this->attachDownloadCookie($request, $resp);
            }

            // 既存Reportの buildViewData() を流用（数値生成は捨てない）
            $vars = $reportObj->buildViewData($data);

            $tplPath  = resource_path('pdf_templates/furusato/2_syotokukinkojyosoku_bg.pdf');
            $fontPath = public_path('fonts/ipaexg.ttf');

            $writer = new FurusatoSyotokukinKojyosokuTemplateWriter($tplPath, $fontPath);
            $pdfStr = $writer->render($vars);

            $this->cache->put($cacheKey, $pdfStr);

            $file = $reportObj->fileName($data);
            $resp = response($pdfStr, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $file . '"',
            ]);
            return $this->attachDownloadCookie($request, $resp);
        }

        // ============================================================
        // furusato_bundle (fast)：いったん「表紙＋寄附金上限額（1ページ目）」の2ページをテンプレ方式で結合して返す
        //   - ここが“つなげてほしい”の対応
        //   - 後で 2〜7ページも同じ方式で拡張していく
        // ============================================================
        if (strtolower($report) === 'furusato_bundle' && $mode === 'fast') {
            // キャッシュがあれば即DL
            if ($this->cache->exists($cacheKey)) {
                $file = ($reportObj instanceof BundleReportInterface)
                    ? $reportObj->bundleFileName($data, [
                        'one_stop_flag_curr' => (string)$contextBase['one_stop_flag_curr'],
                        'pdf_variant'        => (string)$contextBase['pdf_variant'],
                    ])
                    : $reportObj->fileName($data);
                $resp = response()->download(
                    $this->cache->absolutePath($cacheKey),
                    $file,
                    ['Content-Type' => 'application/pdf']
                );
                return $this->attachDownloadCookie($request, $resp);
            }

            // ★bundleKeys() を唯一のSoTとして使う（pdf_variant に応じて keys が変わる）
            abort_unless($reportObj instanceof BundleReportInterface, 500, 'furusato_bundle must implement BundleReportInterface');
            $context = [
                'one_stop_flag_curr' => (string)$contextBase['one_stop_flag_curr'],
                'pdf_variant'        => (string)$contextBase['pdf_variant'],
                'mode'               => (string)$contextBase['mode'],
                'engine'             => (string)$contextBase['engine'],
            ];
            $keys = $reportObj->bundleKeys($data, $context);
            $keys = array_values(array_filter(array_map('strval', $keys), fn($k) => $k !== ''));
            // 重複排除（順序維持）
            $seen = [];
            $keys = array_values(array_filter($keys, function ($k) use (&$seen) {
                $lk = strtolower($k);
                if (isset($seen[$lk])) return false;
                $seen[$lk] = true;
                return true;
            }));

            $fontPath = public_path('fonts/ipaexg.ttf');
            $bins = [];

            // 共通：テンプレWriterで落ちたら Blade(dompdf/chrome) にフォールバック
            $renderByBlade = function (string $key, $obj, array $vars) use ($data, $engine) : string {
                $view = $obj->viewName();
                $options = [];
                if (method_exists($obj, 'pdfOptions')) {
                    $options = (array) $obj->pdfOptions($data);
                }
                $options['engine'] = $engine;
                return $this->renderer->renderToString($view, array_merge($vars, ['is_pdf' => true]), $options);
            };

            // ★ *_curr なら「今まで(ima)」の背景テンプレを使う
            $tplPathFor = function (string $key) : string {
                $k = strtolower($key);
                $isCurr = str_ends_with($k, '_curr');
                // 0,1,5,6,7 は共通テンプレ（今まで/上限で見出しを分けない）
                if ($k === 'hyoshi') return resource_path('pdf_templates/furusato/0_hyoshi_bg.pdf');
                if ($k === 'kifukingendogaku') return resource_path('pdf_templates/furusato/1_kifukingendogaku_bg.pdf');
                if ($k === 'sonntokusimulation') return resource_path('pdf_templates/furusato/5_sonntokusimulation_bg.pdf');
                if ($k === 'jintekikojosatyosei') return resource_path('pdf_templates/furusato/6_jintekikojosatyosei_bg.pdf');
                if ($k === 'tokureikojowariai') return resource_path('pdf_templates/furusato/7_tokureikojowariai_bg.pdf');

                // 2〜4 は current/max で背景が変わる（*_ima_bg.pdf）
                if ($k === 'syotokukinkojyosoku' || $k === 'syotokukinkojyosoku_curr') {
                    return $isCurr
                        ? resource_path('pdf_templates/furusato/2_syotokukinkojyosoku_ima_bg.pdf')
                        : resource_path('pdf_templates/furusato/2_syotokukinkojyosoku_bg.pdf');
                }
                if ($k === 'kazeigakuzeigakuyosoku' || $k === 'kazeigakuzeigakuyosoku_curr') {
                    return $isCurr
                        ? resource_path('pdf_templates/furusato/3_kazeigakuzeigakuyosoku_ima_bg.pdf')
                        : resource_path('pdf_templates/furusato/3_kazeigakuzeigakuyosoku_bg.pdf');
                }
                if (in_array($k, ['juminkeigengaku','juminkeigengaku_curr'], true)) {
                    return $isCurr
                        ? resource_path('pdf_templates/furusato/4_juminkeigengaku_ima_bg.pdf')
                        : resource_path('pdf_templates/furusato/4_juminkeigengaku_bg.pdf');
                }
                if (in_array($k, ['juminkeigengaku_onestop','juminkeigengaku_onestop_curr'], true)) {
                    return $isCurr
                        ? resource_path('pdf_templates/furusato/4_juminkeigengaku_onestop_ima_bg.pdf')
                        : resource_path('pdf_templates/furusato/4_juminkeigengaku_onestop_bg.pdf');
                }
                // fallback（使わない想定だが保険）
                return '';
            };

            foreach ($keys as $key) {
                $k = strtolower((string)$key);

                // 7ページ（特例控除割合）は背景そのまま
                if ($k === 'tokureikojowariai') {
                    $tpl7 = $tplPathFor($k);
                    if (!is_file($tpl7)) {
                        throw new \RuntimeException('Template PDF not found: ' . $tpl7);
                    }
                    $pdf = file_get_contents($tpl7);
                    if (!is_string($pdf) || $pdf === '') {
                        throw new \RuntimeException('Failed to read template PDF: ' . $tpl7);
                    }
                    $bins[] = $pdf;
                    continue;
                }

                // 5ページ（損得）は controller 組み立て
                if ($k === 'sonntokusimulation') {
                    $payload5 = [];
                    $stored5 = FurusatoResult::query()->where('data_id', (int)$data->id)->value('payload');
                    if (is_array($stored5)) {
                        $candidate5 = $stored5['payload'] ?? $stored5['upper'] ?? $stored5;
                        $payload5 = is_array($candidate5) ? $candidate5 : [];
                    }
                    if ($payload5 === []) {
                        $inp5 = FurusatoInput::query()->where('data_id', (int)$data->id)->value('payload');
                        $payload5 = is_array($inp5) ? $inp5 : [];
                    }
                    $syoriFactory5 = app(SyoriSettingsFactory::class);
                    $syoriSettings5 = $syoriFactory5->buildInitial($data);
                    $guestBirth5 = $data->guest?->birth_date ?? null;
                    $guestBirthYmd5 = null;
                    if ($guestBirth5 instanceof \DateTimeInterface) {
                        $guestBirthYmd5 = $guestBirth5->format('Y-m-d');
                    } elseif (is_string($guestBirth5)) {
                        $guestBirthYmd5 = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($guestBirth5)) === 1 ? trim($guestBirth5) : null;
                    }
                    $taxpayerSex5 = data_get($data, 'guest.sex') ?? data_get($data, 'guest.gender') ?? data_get($data, 'guest.sex_code');
                    $ctx5 = [
                        'syori_settings'   => $syoriSettings5,
                        'data'             => $data,
                        'data_id'          => (int)$data->id,
                        'company_id'       => $data->company_id !== null ? (int)$data->company_id : null,
                        'kihu_year'        => $data->kihu_year ? (int)$data->kihu_year : 0,
                        'master_kihu_year' => 2025,
                        'guest_birth_date' => $guestBirthYmd5,
                        'taxpayer_sex'     => $taxpayerSex5,
                    ];
                    $svc5 = app(FurusatoSonntokuSimulationService::class);
                    $sonntoku5 = $svc5->build($payload5, $ctx5);
                    $tpl5 = resource_path('pdf_templates/furusato/5_sonntokusimulation_bg.pdf');
                    $writer5 = new FurusatoSonntokuSimulationTemplateWriter($tpl5, $fontPath);
                    try {
                        $bins[] = $writer5->render(['sonntoku' => $sonntoku5, 'show_test' => true]);
                    } catch (\Throwable $e) {
                        Log::warning('pdf.fast.bundle.template_failed', ['key'=>$k,'err'=>get_class($e),'msg'=>$e->getMessage()]);
                        $obj = $this->reports->resolve($k);
                        $bins[] = $renderByBlade($k, $obj, $obj->buildViewData($data));
                    }
                    continue;
                }

                // 通常：report_key を渡して build（*_curr切替を効かせる）
                $obj = $this->reports->resolve($k);
                $ctxPerKey = array_merge($context, ['report_key' => $k]);
                if (method_exists($obj, 'buildViewDataWithContext')) {
                    $vars = $obj->buildViewDataWithContext($data, $ctxPerKey);
                } else {
                    $vars = $obj->buildViewData($data);
                }

                try {
                    // キーごとにテンプレWriterを選択
                    if ($k === 'hyoshi') {
                        $tpl = $tplPathFor($k);
                        $w = new FurusatoHyoshiTemplateWriter($tpl, $fontPath);
                        $bins[] = $w->render($vars);
                        continue;
                    }
                    if ($k === 'kifukingendogaku') {
                        $tpl = $tplPathFor($k);
                        $w = new FurusatoKifukinGendogakuTemplateWriter($tpl, $fontPath);
                        $bins[] = $w->render($vars);
                        continue;
                    }
                    if ($k === 'syotokukinkojyosoku' || $k === 'syotokukinkojyosoku_curr') {
                        $tpl = $tplPathFor($k);
                        $w = new FurusatoSyotokukinKojyosokuTemplateWriter($tpl, $fontPath);
                        $bins[] = $w->render($vars);
                        continue;
                    }
                    if ($k === 'kazeigakuzeigakuyosoku' || $k === 'kazeigakuzeigakuyosoku_curr') {
                        $tpl = $tplPathFor($k);
                        $w = new FurusatoKazeigakuZeigakuYosokuTemplateWriter($tpl, $fontPath);
                        $bins[] = $w->render($vars);
                        continue;
                    }
                    if (in_array($k, ['juminkeigengaku','juminkeigengaku_curr','juminkeigengaku_onestop','juminkeigengaku_onestop_curr'], true)) {
                        $tpl = $tplPathFor($k);
                        $w = new FurusatoJuminKeigengakuTemplateWriter($tpl, $fontPath);
                        $bins[] = $w->render($vars);
                        continue;
                    }
                    if ($k === 'jintekikojosatyosei') {
                        $tpl = $tplPathFor($k);
                        $w = new FurusatoJintekiKojoSaTyoseiTemplateWriter($tpl, $fontPath);
                        $bins[] = $w->render($vars);
                        continue;
                    }

                    // ここまで来たら Blade へ（念のため）
                    $bins[] = $renderByBlade($k, $obj, $vars);
                } catch (\Throwable $e) {
                    // ★テンプレがFPDI圧縮などで落ちても bundle を落とさない
                    Log::warning('pdf.fast.bundle.template_failed', ['key'=>$k,'err'=>get_class($e),'msg'=>$e->getMessage()]);
                    $bins[] = $renderByBlade($k, $obj, $vars);
                }
            }

            // 2) 2つのPDF（各1ページ）を結合
            $m = new \setasign\Fpdi\Tcpdf\Fpdi('L', 'mm', 'A4', true, 'UTF-8', false);
            $m->SetAutoPageBreak(false);
            $m->SetMargins(0, 0, 0);
            $m->setPrintHeader(false);
            $m->setPrintFooter(false);

            foreach ($bins as $bin) {
                $pageCount = $m->setSourceFile(StreamReader::createByString($bin));
                for ($i = 1; $i <= $pageCount; $i++) {
                    $tpl = $m->importPage($i);
                    $size = $m->getTemplateSize($tpl);
                    $m->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $m->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);
                }
            }

            $merged = $m->Output('', 'S');
            $this->cache->put($cacheKey, $merged);

            $file = $reportObj->bundleFileName($data, $context);
            $resp = response($merged, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $file . '"',
            ]);
            return $this->attachDownloadCookie($request, $resp);
        }

        // ============================================================
        // 寄附金上限額（kifukingendogaku）：背景テンプレPDF＋mm座標印字（まずは仮置き）
        // ============================================================
        if (strtolower($report) === 'kifukingendogaku') {
            if ($this->cache->exists($cacheKey)) {
                $file = $reportObj->fileName($data);
                $resp = response()->download(
                    $this->cache->absolutePath($cacheKey),
                    $file,
                    ['Content-Type' => 'application/pdf']
                );
                return $this->attachDownloadCookie($request, $resp);
            }

            // buildViewData() は「既存の数値生成をそのまま流用」するために使う
            $vars = $reportObj->buildViewData($data);

            $tplPath  = resource_path('pdf_templates/furusato/1_kifukingendogaku_bg.pdf');
            $fontPath = public_path('fonts/ipaexg.ttf');

            $writer = new FurusatoKifukinGendogakuTemplateWriter($tplPath, $fontPath);
            $pdfStr = $writer->render($vars);

            $this->cache->put($cacheKey, $pdfStr);

            $file = $reportObj->fileName($data);
            $resp = response($pdfStr, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $file . '"',
            ]);
            return $this->attachDownloadCookie($request, $resp);
        }

        // ============================================================
        // 表紙(hyoshi)：背景テンプレPDF＋mm座標印字
        // - まずは表紙だけ新方式で出す（bundle統合は次段階）
        // ============================================================
        if (strtolower($report) === 'hyoshi') {
            if ($this->cache->exists($cacheKey)) {
                $file = $reportObj->fileName($data);
                $resp = response()->download(
                    $this->cache->absolutePath($cacheKey),
                    $file,
                    ['Content-Type' => 'application/pdf']
                );
                return $this->attachDownloadCookie($request, $resp);
            }

            $tplPath  = resource_path('pdf_templates/furusato/0_hyoshi_bg.pdf');
            $fontPath = public_path('fonts/ipaexg.ttf');

            $writer = new FurusatoHyoshiTemplateWriter($tplPath, $fontPath);

            $guest = (string)($data->guest?->name ?? '');
            $date  = (string)($data->proposal_date ?? $data->data_created_on ?? now()->toDateString());
            $org   = ''; // 必要ならここに事務所名等

            $pdfStr = $writer->render([
                'guest_name' => $guest !== '' ? ($guest . '様') : '',
                'date'       => $date,
                'org'        => $org,
                // 初期はtrue（TEST-XYを表示）。座標が合ったらfalseにしてOK
                'show_test'  => true,
            ]);

            $this->cache->put($cacheKey, $pdfStr);

            $file = $reportObj->fileName($data);
            $resp = response($pdfStr, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $file . '"',
            ]);
            return $this->attachDownloadCookie($request, $resp);
        }

        // キャッシュがあれば即DL
        if ($this->cache->exists($cacheKey)) {
            $file = ($reportObj instanceof BundleReportInterface)
                ? $reportObj->bundleFileName($data, ['one_stop_flag_curr' => $contextBase['one_stop_flag_curr']])
                : $reportObj->fileName($data);
            $resp = response()->download(
                $this->cache->absolutePath($cacheKey),
                $file,
                ['Content-Type' => 'application/pdf']
            );
            return $this->attachDownloadCookie($request, $resp);
        }

        // Bundle（1本HTML → 複数ページPDF）の場合
        if ($reportObj instanceof BundleReportInterface) {
            $context = [
                // 当年度ワンストップ（input側からクエリで渡す）
                'one_stop_flag_curr' => (string)$request->query('one_stop_flag_curr', '1'),
                // PDFの出力条件（max|current|both）
                'pdf_variant'        => (string)$request->query('pdf_variant', 'max'),
            ];

            //  先にファイル名を確定（後段の response ヘッダで使う）
            $file = $reportObj->bundleFileName($data, $context);
            // 未生成：fast 標準は「ジョブ投入→202（building）」にする
            if ($mode === 'fast') {
                // 二重投入を避ける（statusがbuildingなら触らない）
                $st = $this->cache->getStatus($cacheKey);
                if (($st['status'] ?? 'none') !== 'building') {
                    $this->cache->setStatus($cacheKey, ['status' => 'building']);
                    BuildFurusatoBundlePdfCacheJob::dispatch(
                        (int)$data->id,
                        (string)$context['one_stop_flag_curr'],
                        'fast',
                        $engine
                    )->onQueue('default');
                }

                // JSON要求なら 202 を返す（フロントがポーリング）
                if ((string)$request->query('format') === 'json' || $request->wantsJson()) {
                    return response()->json([
                        'status' => 'building',
                        'cache_key' => $cacheKey,
                        'status_url' => route('pdf.status', ['report' => $report])
                            . '?data_id=' . urlencode((string)$data->id)
                            . '&one_stop_flag_curr=' . urlencode((string)$context['one_stop_flag_curr'])
                            . '&mode=fast&engine=' . urlencode($engine),
                    ], 202);
                }

                // 画面遷移（通常クリック）でも 202 JSON を返すとブラウザ表示されてしまうため、
                // 最低限のテキストを返す（input側JSで抑止する想定）
                return response('PDF is building. Please retry shortly.', 202);
            }

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
                    // ★ report_key を context に含めて渡す（_curr の判定などに使用）
                    $ctxPerKey = array_merge($context, ['report_key' => (string)$key]);
                    if (method_exists($obj, 'buildViewDataWithContext')) {
                        /** @var array $vars */
                        $vars = $obj->buildViewDataWithContext($data, $ctxPerKey);
                    } else {
                        $vars = $obj->buildViewData($data);
                    }
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
                    $options['engine'] = $engine;
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
            // strictは生成結果をキャッシュに保存（次回高速化）
            $this->cache->put($cacheKey, $merged);
            $resp = response($merged, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $file . '"',
            ]);
            return $this->attachDownloadCookie($request, $resp);
        }
        // 単体レポート（今回は bundle だけプリ生成対象なので、単体は同期生成→キャッシュ保存）
        $vars = $reportObj->buildViewData($data);
        $view = $reportObj->viewName();
        $file = $reportObj->fileName($data);
        // 帳票ごとの paper/orient を反映（無ければ既定）
        $options = [];
        if (method_exists($reportObj, 'pdfOptions')) {
            $options = (array)$reportObj->pdfOptions($data);
        }
        $options['engine'] = $engine;
        $pdfStr = $this->renderer->renderToString($view, $vars, $options);
        $this->cache->put($cacheKey, $pdfStr);
        $resp = response($pdfStr, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
        ]);
        return $this->attachDownloadCookie($request, $resp);
    }

    /**
     * ダウンロード開始検知用 Cookie を付与する。
     * - クライアントが iframe.src をセットし、レスポンスヘッダを受け取った時点で cookie が立つ
     * - JS は cookie を検知してオーバーレイを閉じる
     */
    private function attachDownloadCookie(Request $request, SymfonyResponse $resp): SymfonyResponse
    {
        // download（BinaryFileResponse）は条件付き(304)になりやすいので潰す
        if ($resp instanceof BinaryFileResponse) {
            // 自動ETag/Last-Modifiedを無効化して 304 を出しにくくする
            if (method_exists($resp, 'setAutoEtag')) {
                $resp->setAutoEtag(false);
            }
            if (method_exists($resp, 'setAutoLastModified')) {
                $resp->setAutoLastModified(false);
            }
            $resp->headers->remove('ETag');
            $resp->headers->remove('Last-Modified');
        }

        // 必ずブラウザキャッシュさせない（cookieでDL開始合図を確実に返すため）
        $resp = $this->noStore($resp);

        $token = (string)$request->query('dl_token', '');
        if ($token === '') {
            return $resp;
        }
        // token はURL由来なので cookie名に安全な範囲へ寄せる
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $token) ?: '';
        if ($safe === '') {
            return $resp;
        }

        $name = 'pdf_dl_' . $safe;
        $secure = app()->environment('production');
        // JSで読むので httpOnly=false、SameSite=Lax（同一サイトiframeなら問題なし）
        // BinaryFileResponse には cookie() が無いので headers に直接 setCookie する
        $cookie = Cookie::create($name)
            ->withValue('1')
            ->withExpires(time() + 300) // 5 minutes
            ->withPath('/')
            ->withSecure($secure)
            ->withHttpOnly(false)
            ->withSameSite('Lax');

        $resp->headers->setCookie($cookie);
        return $resp;
    }

    /**
     * PDFレスポンスはブラウザキャッシュさせない（cookieでDL開始合図を確実に返すため）
     */
    private function noStore(SymfonyResponse $resp): SymfonyResponse
    {
        $resp->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $resp->headers->set('Pragma', 'no-cache');
        $resp->headers->set('Expires', '0');
        return $resp;
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