<?php

namespace App\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;

class PdfRenderer
{
    /**
     * BladeビューをPDF化して DomPDF インスタンスを返す
     * 既定: A4縦
     */
    public function render(string $view, array $data = [], array $options = [])
    {
        $paper  = $options['paper']  ?? 'a4';
        $orient = $options['orient'] ?? 'portrait';
        $pdf = Pdf::loadView($view, $data)->setPaper($paper, $orient);
        return $pdf;
    }
}