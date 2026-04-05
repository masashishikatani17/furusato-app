<?php

namespace App\Services\Pdf;

use Illuminate\Support\Arr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PdfRenderer
{
    private function pdfDebugContext(array $extra = []): array
    {
        return array_merge([
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 1),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ], $extra);
    }

    /**
     * BladeビューをPDF化して DomPDF インスタンスを返す
     * 既定: A4縦
     */
    public function render(string $view, array $data = [], array $options = [])
    {
        $paper  = $options['paper']  ?? 'a4';
        $orient = $options['orient'] ?? 'portrait';
        // ★重要：今回のレンダリングエンジン（options優先）をBladeへ渡す
        $engine = (string) ($options['engine'] ?? config('pdf_renderer.engine', 'dompdf'));        
        // PDF生成時：レイアウト側で public_path() を使えるようフラグを渡す
        $data = array_merge($data, [
            'is_pdf' => true,
            'pdf_engine' => $engine,
        ]);
        $pdf = Pdf::loadView($view, $data)->setPaper($paper, $orient);
        return $pdf;
    }

    /**
     * ★HTMLの見た目をそのままPDF化したい場合は、Headless Chrome でPDF文字列を生成する。
     * - engine=chrome のとき：Browsershot (Chromium) で生成
     * - engine=dompdf のとき：従来通り DomPDF
     */
    public function renderToString(string $view, array $data = [], array $options = []): string
    {
        // options['engine'] があればリクエスト単位で上書き可能
        $engine = (string) ($options['engine'] ?? config('pdf_renderer.engine', 'dompdf'));

        Log::info('pdf.renderer.render_to_string.start', $this->pdfDebugContext([
            'view' => $view,
            'engine' => $engine,
            'paper' => $options['paper'] ?? null,
            'orient' => $options['orient'] ?? null,
        ]));
        if ($engine === 'chrome') {
            $out = $this->renderByChrome($view, $data, $options);
            Log::info('pdf.renderer.render_to_string.done', $this->pdfDebugContext([
                'view' => $view,
                'engine' => $engine,
                'pdf_bytes' => strlen($out),
            ]));
            return $out;
        }
        // dompdfでも、Blade側の pdf_engine を一致させるため options['engine'] を渡す
        $options['engine'] = $engine;
        $pdf = $this->render($view, $data, $options);

        Log::info('pdf.renderer.render_to_string.output.start', $this->pdfDebugContext([
            'view' => $view,
            'engine' => $engine,
        ]));

        $out = $pdf->output();

        Log::info('pdf.renderer.render_to_string.done', $this->pdfDebugContext([
            'view' => $view,
            'engine' => $engine,
            'pdf_bytes' => strlen($out),
        ]));

        return $out;
    }

    /**
     * HTML文字列をPDF化して返す（bundle fast 用）
     * - engine=chrome: Browsershot
     * - engine=dompdf: DomPDF
     */
    public function renderHtmlToString(string $html, array $options = []): string
    {
        $engine = (string) ($options['engine'] ?? config('pdf_renderer.engine', 'dompdf'));

        Log::info('pdf.renderer.render_html_to_string.start', $this->pdfDebugContext([
            'engine' => $engine,
            'paper' => $options['paper'] ?? null,
            'orient' => $options['orient'] ?? null,
            'html_bytes' => strlen($html),
        ]));

        if ($engine === 'chrome') {
            // ChromeはHTMLをそのまま渡せる
            $out = $this->renderHtmlByChrome($html, $options);
            Log::info('pdf.renderer.render_html_to_string.done', $this->pdfDebugContext([
                'engine' => $engine,
                'pdf_bytes' => strlen($out),
            ]));
            return $out;
        }

        $paper  = $options['paper']  ?? 'a4';
        $orient = $options['orient'] ?? 'portrait';
        $pdf = Pdf::loadHTML($html)->setPaper($paper, $orient);

        Log::info('pdf.renderer.render_html_to_string.output.start', $this->pdfDebugContext([
            'engine' => $engine,
            'paper' => $paper,
            'orient' => $orient,
        ]));

        $out = $pdf->output();

        Log::info('pdf.renderer.render_html_to_string.done', $this->pdfDebugContext([
            'engine' => $engine,
            'paper' => $paper,
            'orient' => $orient,
            'pdf_bytes' => strlen($out),
        ]));

        return $out;
    }

    private function renderHtmlByChrome(string $html, array $options = []): string
    {
        if (!class_exists(\Spatie\Browsershot\Browsershot::class)) {
            throw new \RuntimeException('Browsershot が未インストールです。composer require spatie/browsershot を実行してください。');
        }
        $paper  = (string) ($options['paper']  ?? 'a4');
        $orient = (string) ($options['orient'] ?? 'portrait');

        $bs = \Spatie\Browsershot\Browsershot::html($html)
            ->showBackground()
            ->waitUntilNetworkIdle();
        $bs->setNodeBinary((string) config('pdf_renderer.node_bin', 'node'));
        $bs->setNpmBinary((string) config('pdf_renderer.npm_bin', 'npm'));
        $bs->setNodeModulePath((string) config('pdf_renderer.node_modules_path', base_path('node_modules')));
        $bs->addChromiumArguments([
            'no-sandbox',
            'disable-setuid-sandbox',
            'disable-dev-shm-usage',
        ]);
        $chromeBin = (string) config('pdf_renderer.chrome_bin', '');
        if ($chromeBin !== '') {
            $bs->setChromePath($chromeBin);
        }
        if (strtolower($paper) === 'a4') {
            $bs->format('A4');
        }
        if (strtolower($orient) === 'landscape') {
            $bs->landscape();
        }
        return $bs->pdf();
    }

    private function renderByChrome(string $view, array $data = [], array $options = []): string
    {
        if (!class_exists(\Spatie\Browsershot\Browsershot::class)) {
            throw new \RuntimeException('Browsershot が未インストールです。composer require spatie/browsershot を実行してください。');
        }

        $paper  = (string) ($options['paper']  ?? 'a4');
        $orient = (string) ($options['orient'] ?? 'portrait');

        // ★Chromeはブラウザと同等なので、asset() を前提に描画させる（public_path 参照はしない）
        $data = array_merge($data, [
            'is_pdf' => true,
            'pdf_engine' => 'chrome',
        ]);

        $html = view($view, $data)->render();

        // ペーパー・向き
        $bs = \Spatie\Browsershot\Browsershot::html($html)
            ->showBackground()
            ->waitUntilNetworkIdle();
        // ★Cloud9：プロジェクトの node_modules から puppeteer を解決させる
        $bs->setNodeBinary((string) config('pdf_renderer.node_bin', 'node'));
        $bs->setNpmBinary((string) config('pdf_renderer.npm_bin', 'npm'));
        $bs->setNodeModulePath((string) config('pdf_renderer.node_modules_path', base_path('node_modules')));

        // ★Cloud9：sandbox/shm 起因のクラッシュ回避（よく効く定番3点）
        $bs->addChromiumArguments([
            'no-sandbox',
            'disable-setuid-sandbox',
            'disable-dev-shm-usage',
        ]);

        // ★OSのChrome/Chromiumを明示したい場合のみ
        $chromeBin = (string) config('pdf_renderer.chrome_bin', '');
        if ($chromeBin !== '') {
            $bs->setChromePath($chromeBin);
        }
        // A4/Letter などの差が出ないよう format を優先
        if (strtolower($paper) === 'a4') {
            $bs->format('A4');
        }
        if (strtolower($orient) === 'landscape') {
            $bs->landscape();
        }

        // ★APP_URL が空だと asset() が相対になり、CSS/フォントが外れるのでログだけ出す
        $appUrl = (string) config('app.url');
        if ($appUrl === '') {
            Log::warning('APP_URL is empty. asset() may become relative and PDF may lose CSS/fonts.');
        }

        return $bs->pdf();
    }
}