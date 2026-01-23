<?php

namespace App\Services\Pdf\Templates;

use setasign\Fpdi\Tcpdf\Fpdi as TcpdfFpdi;

/**
 * 7_tokureikojowariai（特例控除割合）を
 * 「背景テンプレPDF + mm座標印字」で生成する。
 *
 * まずは動作確認として左上に TEST-7 と代表値を仮置きする（座標は後で詰める）。
 */
final class FurusatoTokureiKojowariaiTemplateWriter
{
    /**
     * ★座標は mm（仮）
     * - 左上付近に出して「テンプレ読込 + 印字」の確認をする
     */
    private const POS = [
        'test'  => ['x' => 20.0, 'y' => 20.0, 'size' => 22.0, 'rgb' => [200, 0, 0]],
        'line1' => ['x' => 20.0, 'y' => 35.0, 'size' => 12.0, 'rgb' => [0, 0, 0]],
        'line2' => ['x' => 20.0, 'y' => 42.0, 'size' => 12.0, 'rgb' => [0, 0, 0]],
        'line3' => ['x' => 20.0, 'y' => 49.0, 'size' => 12.0, 'rgb' => [0, 0, 0]],
    ];

    public function __construct(
        private readonly string $templatePdfPath,
        private readonly string $fontTtfPath,
    ) {}

    /**
     * @param array<string,mixed> $vars  Report::buildViewData() の結果
     */
    public function render(array $vars): string
    {
        if (!is_file($this->templatePdfPath)) {
            throw new \RuntimeException('Template PDF not found: ' . $this->templatePdfPath);
        }
        if (!is_file($this->fontTtfPath)) {
            throw new \RuntimeException('Font TTF not found: ' . $this->fontTtfPath);
        }

        $pdf = new TcpdfFpdi('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // 日本語フォント埋め込み（TTF）
        $font = null;
        if (class_exists(\TCPDF_FONTS::class)) {
            $font = \TCPDF_FONTS::addTTFfont($this->fontTtfPath, 'TrueTypeUnicode', '', 96);
        }
        if (!is_string($font) || $font === '') {
            $font = 'helvetica';
        }

        // 背景テンプレ
        $pdf->AddPage('L', 'A4');
        $pageCount = $pdf->setSourceFile($this->templatePdfPath);
        if ($pageCount < 1) {
            throw new \RuntimeException('Template PDF has no pages: ' . $this->templatePdfPath);
        }
        $tpl = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tpl);
        $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);

        // ---- 動作確認 ----
        $showTest = array_key_exists('show_test', $vars) ? (bool)$vars['show_test'] : true;
        if ($showTest) {
            $this->text($pdf, $font, self::POS['test'], 'TEST-7');
        }

        // 代表値（この帳票は現状ほぼ固定文なので、年/氏名/IDだけ出して確認できるようにする）
        $year  = $this->n($vars['year'] ?? 0);
        $guest = (string)($vars['guest_name'] ?? '');
        $dataId = $this->n($vars['data_id'] ?? 0);

        $this->text($pdf, $font, self::POS['line1'], 'year=' . (string)$year . ' / guest=' . $guest);
        $this->text($pdf, $font, self::POS['line2'], 'data_id=' . (string)$dataId);
        $this->text($pdf, $font, self::POS['line3'], 'note: tokureikojowariai template ok');

        return $pdf->Output('', 'S');
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',', ' '], '', $v);
        return is_numeric($v) ? (int)floor((float)$v) : 0;
    }

    /**
     * @param array{x:float,y:float,size:float,rgb:array{0:int,1:int,2:int}} $pos
     */
    private function text(TcpdfFpdi $pdf, string $font, array $pos, string $text): void
    {
        $pdf->SetFont($font, '', (float)$pos['size']);
        $rgb = $pos['rgb'] ?? [0, 0, 0];
        $pdf->SetTextColor((int)$rgb[0], (int)$rgb[1], (int)$rgb[2]);
        $pdf->Text((float)$pos['x'], (float)$pos['y'], $text);
    }
}
