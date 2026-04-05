<?php

namespace App\Services\Pdf\Templates;

use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Tcpdf\Fpdi as TcpdfFpdi;

/**
 * 5_sonntokusimulation（寄附金額別損得シミュレーション）を
 * 「背景テンプレPDF + mm座標印字」で生成する。
 *
 * 背景PDF（左右2つの表・各30行）の上に「数値だけ」を mm 座標で配置する。
 * - 背景にはタイトル、列見出し、①〜④、罫線が印刷済み
 * - Writer は rows[1..30] の数値（寄附金額/減税額/差引/返戻品額/実質負担額）だけを右寄せで入れる
 * 座標は後で詰める前提。ここでは「座標配置できる形（layout + textBox + debug枠）」を用意する。
 */
final class FurusatoSonntokuSimulationTemplateWriter
{
    // ============================================================
    // レイアウト（mm）
    //  - A4横: 297 x 210
    //  - 背景PDFは “完成済み背景” なので、ページ原点(0,0)基準で合わせ込む
    //  - まずは「だいたい当たる」初期値。最終調整は mm 単位で詰めてください。
    // ============================================================
    private const LAYOUT = [
        // 旧Bladeの横並び：123 + 6 + 123 = 252mm（ページ中央に配置）
        'page' => [
            'w' => 297.0,
            'content_w' => 252.0,
            'x' => 22, // (297-252)/2
            'gap' => 10.8,
        ],
        // 1表の列幅（旧Bladeと同じ）
        // colgroup: [8,23,23,23,23,23] = 123
        'table' => [
            'w' => 123.0,
            'cols' => [8.0, 23.0, 23.0, 23.0, 22.8, 22.8],
            // ヘッダー2段は背景固定なので、データ開始位置を header_h でずらす
            'y' => 26.0,        // ★仮：表の上端（要調整）
            'header_h' => 14.0, // ★仮：ヘッダー2段分（要調整）
            'row_h' => 4.88,     // ★仮：1行の高さ（30行ぶんが収まる値に後で調整）
            'pad_r' => 1.6,
            'font' => 9.5,
        ],
        // 表の見出し（■〇〇ごとの区分）を上書きしたい場合用（背景が固定なら使わなくてOK）
        'label' => [
            'y' => 21.8,   // ★仮
            'h' => 7.5,
            'font' => 14.0,
            'x_off' => 43,  // ★表の左端から数字位置まで（仮：調整）
            'w' => 11.8,      // ★数字2桁用の幅（仮：調整）
        ],
        'debug' => [
            'rgb' => [200, 0, 0],
            'line_w' => 0.2,
        ],
    ];

    private function pdfDebugContext(array $extra = []): array
    {
        return array_merge([
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 1),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ], $extra);
    }

    public function __construct(
        private readonly string $templatePdfPath,
        private readonly string $fontTtfPath,
    ) {}

    /**
     * @param array<string,mixed> $vars
     *   - Controller側で作る想定：
     *     $vars['sonntoku'] = FurusatoSonntokuSimulationService::build(...) の返り値
     */
    public function render(array $vars): string
    {
        Log::info('[sonntoku][template] render.start', $this->pdfDebugContext([
            'template_pdf_path' => $this->templatePdfPath,
            'template_pdf_exists' => is_file($this->templatePdfPath),
            'template_pdf_size' => is_file($this->templatePdfPath) ? filesize($this->templatePdfPath) : null,
            'font_ttf_path' => $this->fontTtfPath,
            'font_ttf_exists' => is_file($this->fontTtfPath),
            'font_ttf_size' => is_file($this->fontTtfPath) ? filesize($this->fontTtfPath) : null,
        ]));

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

        Log::info('[sonntoku][template] font.ready', $this->pdfDebugContext([
            'resolved_font' => $font,
        ]));

        // 背景テンプレ
        $pdf->AddPage('L', 'A4');
        Log::info('[sonntoku][template] source.open.start', $this->pdfDebugContext([
            'template_pdf_path' => $this->templatePdfPath,
        ]));
        try {
            $pageCount = $pdf->setSourceFile($this->templatePdfPath);
        } catch (\Throwable $e) {
            Log::warning('[sonntoku][template] source.open.failed', $this->pdfDebugContext([
                'template_pdf_path' => $this->templatePdfPath,
                'err' => get_class($e),
                'msg' => $e->getMessage(),
            ]));
            throw $e;
        }
        Log::info('[sonntoku][template] source.open.done', $this->pdfDebugContext([
            'page_count' => $pageCount,
        ]));
        if ($pageCount < 1) {
            throw new \RuntimeException('Template PDF has no pages: ' . $this->templatePdfPath);
        }
        $tpl = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tpl);
        $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);


        $son = is_array($vars['sonntoku'] ?? null) ? $vars['sonntoku'] : [];
        $leftRows  = is_array($son['left']['rows']  ?? null) ? $son['left']['rows']  : [];
        $rightRows = is_array($son['right']['rows'] ?? null) ? $son['right']['rows'] : [];
        $leftStep  = $this->n($son['left']['step']  ?? 0);
        $rightStep = $this->n($son['right']['step'] ?? 0);

        Log::info('[sonntoku][template] rows.ready', $this->pdfDebugContext([
            'left_rows_count' => count($leftRows),
            'right_rows_count' => count($rightRows),
            'left_step' => $leftStep,
            'right_step' => $rightStep,
        ]));

        // テーブル座標
        $pageX = (float)self::LAYOUT['page']['x'];
        $gap   = (float)self::LAYOUT['page']['gap'];
        $tbl   = self::LAYOUT['table'];
        $cols  = $tbl['cols'];
        $y0    = (float)$tbl['y'];
        $hHead = (float)$tbl['header_h'];
        $rh    = (float)$tbl['row_h'];
        $padR  = (float)$tbl['pad_r'];
        $fs    = (float)$tbl['font'];

        // 左/右テーブルの起点
        $xL = $pageX;
        $xR = $pageX + (float)$tbl['w'] + $gap;

        // 列X（0..5）
        $colXL = $this->colLefts($xL, $cols);
        $colXR = $this->colLefts($xR, $cols);

        // ============================================================
        // 0) 表見出し（■〇〇ごとの区分）を太字で上書き
        //   - 背景にある想定でも、ここで同じ文言を重ねて“太字化”できる
        // ============================================================
        $lbl = self::LAYOUT['label'];
        $lblY = (float)$lbl['y'];
        $lblH = (float)$lbl['h'];
        $lblFs = (float)$lbl['font'];
        $wTbl = (float)$tbl['w'];
        $lbl = self::LAYOUT['label'];
        $lblY  = (float)$lbl['y'];
        $lblH  = (float)$lbl['h'];
        $lblFs = (float)$lbl['font'];
        $lblXoff = (float)($lbl['x_off'] ?? 43);
        $lblW    = (float)($lbl['w'] ?? 11.8);
        
        $leftNum  = (string)max(0, (int)floor($leftStep  / 10000));
        $rightNum = (string)max(0, (int)floor($rightStep / 10000));
        
        $this->textBox($pdf, $font, $xL + $lblXoff, $lblY, $lblW, $lblH, $leftNum,  'L', $lblFs, 0.0, 0.0, 'B');
        $this->textBox($pdf, $font, $xR + $lblXoff, $lblY, $lblW, $lblH, $rightNum, 'L', $lblFs, 0.0, 0.0, 'B');

        // 数値列（区分=0 は背景に番号があるので基本は印字しない）
        // 1:寄附金額(y) 2:減税額(saved_total) 3:差引(diff) 4:返戻品額(gift) 5:実質負担額(net)
        for ($k = 1; $k <= 30; $k++) {
            $y = $y0 + $hHead + ($rh * (float)($k - 1));

            $lr = is_array($leftRows[$k] ?? null) ? $leftRows[$k] : [];
            $rr = is_array($rightRows[$k] ?? null) ? $rightRows[$k] : [];

            $this->putRow($pdf, $font, $colXL, $cols, $y, $rh, $lr, $fs, $padR);
            $this->putRow($pdf, $font, $colXR, $cols, $y, $rh, $rr, $fs, $padR);
        }

        Log::info('[sonntoku][template] output.start', $this->pdfDebugContext());
        try {
            $out = $pdf->Output('', 'S');
        } catch (\Throwable $e) {
            Log::error('[sonntoku][template] output.failed', $this->pdfDebugContext([
                'err' => get_class($e),
                'msg' => $e->getMessage(),
            ]));
            throw $e;
        }

        Log::info('[sonntoku][template] output.done', $this->pdfDebugContext([
            'pdf_bytes' => strlen($out),
        ]));

        return $out;
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',', ' '], '', $v);
        return is_numeric($v) ? (int)floor((float)$v) : 0;
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function fmtYen(int $v): string
    {
        return number_format($v);
    }

    private function stepLabel(int $step): string
    {
        $s = max(0, $step);
        if ($s > 0 && $s % 10_000 === 0) {
            return (string)intdiv($s, 10_000) . '万円';
        }
        return number_format($s) . '円';
    }

    /** @return float[] col index => x */
    private function colLefts(float $baseX, array $cols): array
    {
        $x = $baseX;
        $out = [];
        foreach ($cols as $i => $w) {
            $out[(int)$i] = (float)$x;
            $x += (float)$w;
        }
        return $out;
    }

    /**
     * 1行分を出力（背景の区分番号列は印字しない）
     * @param array<string,mixed> $row
     */
    private function putRow(
        TcpdfFpdi $pdf,
        string $font,
        array $colX,
        array $cols,
        float $y,
        float $h,
        array $row,
        float $fontSize,
        float $padR
    ): void {
        $yVal    = $this->n($row['y'] ?? 0);
        $saved   = $this->n($row['saved_total'] ?? 0);
        $diff    = $this->n($row['diff'] ?? 0);
        $gift    = $this->n($row['gift'] ?? 0);
        $net     = $this->n($row['net'] ?? 0);

        $this->putCell($pdf, $font, $colX, $cols, 1, $y, $h, $this->fmtYen($yVal),  $fontSize, $padR);
        $this->putCell($pdf, $font, $colX, $cols, 2, $y, $h, $this->fmtYen($saved), $fontSize, $padR);
        $this->putCell($pdf, $font, $colX, $cols, 3, $y, $h, $this->fmtYen($diff),  $fontSize, $padR);
        $this->putCell($pdf, $font, $colX, $cols, 4, $y, $h, $this->fmtYen($gift),  $fontSize, $padR);
        $this->putCell($pdf, $font, $colX, $cols, 5, $y, $h, $this->fmtYen($net),   $fontSize, $padR);
    }

    private function putCell(
        TcpdfFpdi $pdf,
        string $font,
        array $colX,
        array $cols,
        int $colIdx,
        float $y,
        float $h,
        string $text,
        float $fontSize,
        float $padR
    ): void {
        $x = (float)$colX[$colIdx];
        $w = (float)$cols[$colIdx];
        $this->textBox($pdf, $font, $x, $y, $w, $h, $text, 'R', $fontSize, $padR);
   }
   
private function debugRect(TcpdfFpdi $pdf, float $x, float $y, float $w, float $h): void
{
    $rgb = self::LAYOUT['debug']['rgb'] ?? [200, 0, 0];
    $lw  = (float)(self::LAYOUT['debug']['line_w'] ?? 0.2);
    $pdf->SetDrawColor((int)$rgb[0], (int)$rgb[1], (int)$rgb[2]);
    $pdf->SetLineWidth($lw);
    $pdf->Rect($x, $y, $w, $h);
}

    /**
     * セル内に文字を配置（MultiCellラップ）
     * - 右寄せ（R）と枠幅（w）を使って「桁位置」を揃える
     */
    private function textBox(
        TcpdfFpdi $pdf,
        string $font,
        float $x,
        float $y,
        float $w,
        float $h,
        string $text,
        string $align = 'R',
        float $fontSize = 10.5,
        float $padRight = 0.0,
        float $padLeft = 0.0,
        string $fontStyle = ''   // ★追加: '' or 'B'
    ): void {
        // NOTE: IPAex等は Bold 面を持たず 'B' が効かない場合があるため、
        //       'B' は「疑似太字（2回描画）」で対応する
        $isBold = ($fontStyle === 'B');
        $pdf->SetFont($font, '', $fontSize);
        $pdf->SetTextColor(0, 0, 0);

        $innerX = $x + max(0.0, $padLeft);
        $innerW = $w - max(0.0, $padLeft) - max(0.0, $padRight);
        if ($innerW < 0.1) $innerW = 0.1;

        $pdf->SetXY($innerX, $y);
        $pdf->MultiCell($innerW, $h, $text, 0, $align, false, 0, '', '', true, 0, false, false, 0, 'M', false);

        // ★疑似太字：ごく小さいオフセットで同じ文字をもう一度描く
        if ($isBold) {
            $pdf->SetXY($innerX + 0.2, $y); // 0.2mm（必要なら 0.15〜0.30 で調整）
            $pdf->MultiCell($innerW, $h, $text, 0, $align, false, 0, '', '', true, 0, false, false, 0, 'M', false);
        }
    } // ← textBox() の閉じ

} // ← class の閉じ