<?php

namespace App\Services\Pdf;

use setasign\Fpdi\Tcpdf\Fpdi as TcpdfFpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * PDFの各ページ右下に「Nページ」等のラベルをスタンプする（背景テンプレを改変しない）。
 * - A4横を前提にしているが、実寸(width/height)から右下基準で配置するため他サイズでも崩れにくい
 * - フォントはTTFを埋め込み（日本語OK）
 */
final class PdfPageLabelStamper
{
    /**
     * @param array<int, string> $labelsByPage 1-indexed. 例: [1=>'1ページ', 2=>'2ページ']
     */
    public function stampPerPage(string $pdfBinary, array $labelsByPage, string $fontTtfPath): string
    {
        if ($pdfBinary === '') {
            return $pdfBinary;
        }

        $pdf = new TcpdfFpdi('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // 日本語フォント埋め込み（TTF）
        $font = 'helvetica';
        if (is_file($fontTtfPath) && class_exists(\TCPDF_FONTS::class)) {
            $added = \TCPDF_FONTS::addTTFfont($fontTtfPath, 'TrueTypeUnicode', '', 96);
            if (is_string($added) && $added !== '') {
                $font = $added;
            }
        }

        $pageCount = $pdf->setSourceFile(StreamReader::createByString($pdfBinary));
        for ($i = 1; $i <= $pageCount; $i++) {
            $tpl  = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);

            $label = trim((string)($labelsByPage[$i] ?? ''));
            if ($label === '') {
                continue;
            }

            // 右下固定（幅/高さから逆算）
            $w = (float)$size['width'];
            $h = (float)$size['height'];
            $boxW = 40.0;
            $boxH = 6.0;
            $marginR = 10.0;
            $marginB = 8.0;
            $x = max(0.0, $w - $boxW - $marginR);
            $y = max(0.0, $h - $boxH - $marginB);

            $this->textBox($pdf, $font, $x, $y, $boxW, $boxH, $label);
        }

        return $pdf->Output('', 'S');
    }

    private function textBox(TcpdfFpdi $pdf, string $font, float $x, float $y, float $w, float $h, string $text): void
    {
        $pdf->SetFont($font, '', 10.5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($w, $h, $text, 0, 'R', false, 0, '', '', true, 0, false, false, 0, 'M', false);
    }
}