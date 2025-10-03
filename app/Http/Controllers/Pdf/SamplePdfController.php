<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SamplePdfController extends Controller
{
    /**
     * ブラウザ表示（inline）
     */
    public function stream(Request $request)
    {
        // 日本語フォントを入れていない場合は □ になることがあります（下記「日本語フォント」参照）
        $pdf = Pdf::loadView('pdf.sample', [
            'title' => 'PDF Sample',
            'today' => now()->format('Y-m-d H:i'),
        ])->setPaper('A4', 'portrait');

        return $pdf->stream('sample.pdf');
    }

    /**
     * ダウンロード（attachment）
     */
    public function download(Request $request)
    {
        $pdf = Pdf::loadView('pdf.sample', [
            'title' => 'PDF Sample (Download)',
            'today' => now()->format('Y-m-d H:i'),
        ])->setPaper('A4', 'portrait');

        return $pdf->download('sample.pdf');
    }
}