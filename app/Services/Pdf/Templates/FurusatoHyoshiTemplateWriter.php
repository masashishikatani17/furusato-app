<?php

namespace App\Services\Pdf\Templates;

use setasign\Fpdi\Tcpdf\Fpdi as TcpdfFpdi;

/**
 * 表紙（0_hyoshi）を「背景テンプレPDF + mm座標印字」で生成する
 */
final class FurusatoHyoshiTemplateWriter
{
    /**
     * ★座標は mm
     * - まずは動作確認のため仮置き。テンプレに合わせて調整してください。
     */
    private const POS = [
        // 左上に大きく "TEST-XY" を出す（見えるか確認用）
        'test' => ['x' => 20.0, 'y' => 20.0, 'size' => 24.0, 'rgb' => [200, 0, 0]],
        // お客様名（例：山田太郎様）
        'guest' => ['x' => 37.0, 'y' => 34.0, 'size' => 20.0, 'rgb' => [0, 0, 0]],
        // 日付
        'date'  => ['x' => 130.0, 'y' => 135.0, 'size' => 18.0, 'rgb' => [0, 0, 0]],
        // 事務所名（必要なら）
        'org'   => ['x' => 75.0, 'y' => 155.0, 'size' => 20.0, 'rgb' => [0, 0, 0]],
    ];

    public function __construct(
        private readonly string $templatePdfPath,
        private readonly string $fontTtfPath,
    ) {}

    /**
     * @param array{
     *   guest_name?:string,
     *   date?:string,
     *   org?:string,
     *   show_test?:bool
     * } $vars
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
        // TCPDFはインスタンスメソッド addTTFfont() ではなく TCPDF_FONTS::addTTFfont() を使う
        $font = null;
        if (class_exists(\TCPDF_FONTS::class)) {
            $font = \TCPDF_FONTS::addTTFfont($this->fontTtfPath, 'TrueTypeUnicode', '', 96);
        }
        if (!is_string($font) || $font === '') {
            // 念のためフォールバック（日本語が崩れる可能性あり）
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

        // TEST（初期は常に出してOK：座標印字できているかの確認用）
        $showTest = array_key_exists('show_test', $vars) ? (bool)$vars['show_test'] : true;
        if ($showTest) {
            $this->text($pdf, $font, self::POS['test'], 'TEST-XY');
        }

        // 本番値
        $guest = trim((string)($vars['guest_name'] ?? ''));
        $date  = trim((string)($vars['date'] ?? ''));
        $org   = trim((string)($vars['org'] ?? ''));

        if ($guest !== '') $this->text($pdf, $font, self::POS['guest'], $guest);
        if ($date  !== '') $this->text($pdf, $font, self::POS['date'],  $date);
        if ($org   !== '') $this->text($pdf, $font, self::POS['org'],   $org);

        return $pdf->Output('', 'S');
    }

    /**
     * @param array{x:float,y:float,size:float,rgb:array{0:int,1:int,2:int}} $pos
     */
    private function text(TcpdfFpdi $pdf, string $font, array $pos, string $text): void
    {
        // ★表紙は全部太字（IPAex等で 'B' が効かない場合があるため疑似太字で確実に）
        $size = (float)$pos['size'];
        $pdf->SetFont($font, '', $size);
        $rgb = $pos['rgb'] ?? [0, 0, 0];
        $pdf->SetTextColor((int)$rgb[0], (int)$rgb[1], (int)$rgb[2]);
       // Text(x,y) は mm 指定
       $x = (float)$pos['x'];
       $y = (float)$pos['y'];
       $pdf->Text($x, $y, $text);

        // ★疑似太字：ごく小さいオフセットで同じ文字をもう一度描く（見た目が確実に太くなる）
        $pdf->Text($x + 0.2, $y, $text); // 0.2mm（必要なら 0.15〜0.30 で調整）
    }
}
