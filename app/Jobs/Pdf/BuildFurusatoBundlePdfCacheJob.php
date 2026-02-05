<?php

namespace App\Jobs\Pdf;

use App\Models\Data;
use App\Models\FurusatoResult;
use App\Reports\Contracts\BundleReportInterface;
use App\Services\Pdf\FastBundlePdfBuilder;
use App\Services\Pdf\FurusatoBundleHybridPdfBuilder;
use App\Services\Pdf\PdfCacheService;
use App\Services\Pdf\ReportRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BuildFurusatoBundlePdfCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $dataId,
        public string $oneStopFlagCurr = '1',
        public string $pdfVariant = 'max', // max|current|both
        public string $mode = 'fast',
        public string $engine = 'dompdf',
    ) {}

    public function handle(
        ReportRegistry $reports,
        FastBundlePdfBuilder $builder,
        FurusatoBundleHybridPdfBuilder $hybrid,
        PdfCacheService $cache,
    ): void {
        $data = Data::with('guest')->findOrFail($this->dataId);

        // 直近の FurusatoResult が無ければスキップ（再計算未実施）
        $r = FurusatoResult::query()->where('data_id', $data->id)->first();
        if (!$r) {
            Log::warning('pdf.cache.job skipped: no FurusatoResult', ['data_id' => $data->id]);
            return;
        }

        $context = [
            'one_stop_flag_curr' => $this->oneStopFlagCurr,
            'pdf_variant'        => $this->pdfVariant,
            'mode' => $this->mode,
            'engine' => $this->engine,
        ];

        $cacheKey = $cache->cacheKey('furusato_bundle', $data, $context);

        if ($cache->exists($cacheKey)) {
            $cache->setStatus($cacheKey, ['status' => 'ready']);
            return;
        }

        $cache->setStatus($cacheKey, ['status' => 'building']);

        $t0 = microtime(true);
        try {
            $bundle = $reports->resolve('furusato_bundle');
            if (!$bundle instanceof BundleReportInterface) {
                throw new \RuntimeException('furusato_bundle is not a BundleReportInterface');
            }

            // bundleはA4横固定（現状に合わせる）
            $options = [
                'paper'  => 'a4',
                'orient' => 'landscape',
                'engine' => $this->engine,
            ];

            // 表紙だけテンプレ＋座標印字、残りは従来fast（dompdf 1回）で生成して結合
            $pdf = $hybrid->build($data, $bundle, [
                'one_stop_flag_curr' => $this->oneStopFlagCurr,
                'pdf_variant'        => $this->pdfVariant,
            ], $options);

            $cache->put($cacheKey, $pdf);

            Log::info('pdf.cache.job done', [
                'data_id' => $data->id,
                'cache_key' => $cacheKey,
                'ms' => (int)round((microtime(true) - $t0) * 1000),
                'bytes' => strlen($pdf),
            ]);
        } catch (\Throwable $e) {
            $cache->setStatus($cacheKey, ['status' => 'failed', 'message' => $e->getMessage()]);
            Log::error('pdf.cache.job failed', [
                'data_id' => $data->id,
                'cache_key' => $cacheKey,
                'err' => get_class($e),
                'msg' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}