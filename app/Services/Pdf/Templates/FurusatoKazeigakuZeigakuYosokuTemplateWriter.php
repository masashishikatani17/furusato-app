<?php

namespace App\Services\Pdf\Templates;

use setasign\Fpdi\Tcpdf\Fpdi as TcpdfFpdi;

/**
 * 3_kazeigakuzeigakuyosoku（課税所得金額・税額の予測）を
 * 「背景テンプレPDF + mm座標印字」で生成する。
 *
 * 背景PDF（罫線/ラベル/斜線/「★」等）の上に「数値（カンマ付き）だけ」を mm 座標で配置する。
 * 座標は後で詰める前提。ここでは「座標配置できる形（layout + textBox）」を用意する。
 *
 * 背景テンプレ（1ページ）には、
 * - 9行：課税所得/税額（総合〜退職 + 合計）
 * - 8行：税額控除（調整控除〜災害減免）
 * - 3行：税額（差引所得税額〜合計）
 * の枠が印刷済み。よって Writer は数値のみ印字する（斜線セルは印字しない）。
 */
final class FurusatoKazeigakuZeigakuYosokuTemplateWriter
{
    // ============================================================
    // レイアウト（mm）
    //  - A4横: 297 x 210
    //  - 背景PDFは “完成済み背景” なので、ページ原点(0,0)基準で合わせ込む
    //  - まずは「だいたい当たる」初期値。最終調整は mm 単位で詰めてください。
    // ============================================================
    private const LAYOUT = [

        // メイン表（幅 237mm）をページ中央へ
        // colgroup: [10,20,31,30,30,29,29,29,29] = 237
        'table' => [
            'x' => 30.5,   // (297-237)/2
            'y' => 27.2,   // ★仮：表の上端（要調整）
            'cols' => [10.0, 20.0, 37.5, 28, 27.5, 27.5, 28.5, 28.5, 28.5],
            'header_h' => 24.0, // ★仮：ヘッダー3段分（要調整）
            'row_h' => 6.33,     // ★仮：データ行高（要調整）
            'pad_r' => 0.8,
            'font' => 9.0,
        ],

        // タイトル年号：背景の「年の課税所得金額・税額の予測」の「年」の前に「令和7」等を置く
        'title_year' => [
            'x' => 84,  // ★仮：中央付近（要調整）
            'y' => 13.2,   // ★仮：上端（要調整）
            'w' => 40.0,
            'h' => 8.0,
            'size' => 14.0,
            'align' => 'R',
            'style' => 'B',
        ],

        // Debug枠（show_test=true のときだけ Rect を描く）
        'debug' => [
            'rgb' => [200, 0, 0],
            'line_w' => 0.2,
        ],
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
        $showTest = (bool)($vars['show_test'] ?? false);

        // ============================================================
        // 0) タイトル年号（背景の「年の…」の「年」の前）
        // ============================================================
        $wareki = trim((string)($vars['wareki_year'] ?? ''));
        $warekiShort = $this->stripTrailingNen($wareki); // 例: 令和7年 → 令和7
        if ($warekiShort !== '') {
            $t = self::LAYOUT['title_year'];
            $this->textBox(
                $pdf,
                $font,
                (float)$t['x'],
                (float)$t['y'],
                (float)$t['w'],
                (float)$t['h'],
                $warekiShort,
                (string)$t['align'],
                (float)$t['size'],
                0.0,
                0.0,
                (string)($t['style'] ?? '') // ★太字対応（LAYOUTのstyleを使う）
            );
            if ($showTest) $this->debugRect($pdf, (float)$t['x'], (float)$t['y'], (float)$t['w'], (float)$t['h']);
        }

        // ============================================================
        // データ解決（Report::buildViewData() の配列）
        // ============================================================
        $r = is_array($vars['report3_curr'] ?? null) ? $vars['report3_curr'] : [];
        $taxable = is_array($r['taxable'] ?? null) ? $r['taxable'] : [];
        $zeigaku = is_array($r['zeigaku'] ?? null) ? $r['zeigaku'] : [];
        $itaxZ = is_array($zeigaku['itax'] ?? null) ? $zeigaku['itax'] : [];
        $juminZ = is_array($zeigaku['jumin'] ?? null) ? $zeigaku['jumin'] : [];
        $credits = is_array($r['credits'] ?? null) ? $r['credits'] : [];
        $final = is_array($r['final_tax'] ?? null) ? $r['final_tax'] : [];
        $finalJ = is_array($final['gokei_jumin'] ?? null) ? $final['gokei_jumin'] : ['muni'=>0,'pref'=>0,'total'=>0];

        // ============================================================
        // 表レイアウト
        // ============================================================
        $T = self::LAYOUT['table'];
        $bx = (float)$T['x'];
        $by = (float)$T['y'];
        $cols = $T['cols'];
        $colX = $this->colLefts($bx, $cols);
        $headerH = (float)$T['header_h'];
        $rowH = (float)$T['row_h'];
        $padR = (float)$T['pad_r'];
        $fontSize = (float)$T['font'];

        // 数字列 index
        $cTaxableItax = 3;
        $cTaxableJumin= 4;
        $cTaxItax     = 5;
        $cTaxMuni     = 6;
        $cTaxPref     = 7;
        $cTaxTotal    = 8;

        // ============================================================
        // 1) 課税所得/税額（9行）
        //   - 合計行は「課税所得金額」が斜線なので印字しない（税額だけ印字）
        // ============================================================
        $rowsTop = [
            ['key'=>'sogo',     'taxable'=>true],
            ['key'=>'tanki',    'taxable'=>true],
            ['key'=>'choki',    'taxable'=>true],
            ['key'=>'kabujoto', 'taxable'=>true],
            ['key'=>'haito',    'taxable'=>true],
            ['key'=>'sakimono', 'taxable'=>true],
            ['key'=>'sanrin',   'taxable'=>true],
            ['key'=>'taishoku', 'taxable'=>true],
            ['key'=>'gokei',    'taxable'=>false],
        ];

        foreach ($rowsTop as $i => $row) {
            $y = $by + $headerH + ($rowH * (float)$i);
            $k = (string)$row['key'];

            if ($row['taxable']) {
                $vIt = $this->n($taxable[$k]['itax'] ?? 0);
                $vJu = $this->n($taxable[$k]['jumin'] ?? 0);
                $this->putCell($pdf, $font, $colX, $cols, $cTaxableItax, $y, $rowH, $this->fmtYen($vIt), $fontSize, $padR, $showTest);
                $this->putCell($pdf, $font, $colX, $cols, $cTaxableJumin, $y, $rowH, $this->fmtYen($vJu), $fontSize, $padR, $showTest);
            }

            // 税額（所得税）
            $zIt = $this->n($itaxZ[$k] ?? 0);
            // ★税額→所得税（列5）は全ブロックで同じ右余白補正に統一
            $this->putCell($pdf, $font, $colX, $cols, $cTaxItax, $y, $rowH, $this->fmtYen($zIt), $fontSize, $padR + 1.2, $showTest);
@@

            // 税額（住民税：muni/pref/total）
            $jRow = is_array($juminZ[$k] ?? null) ? $juminZ[$k] : [];
            $jm = $this->n($jRow['muni'] ?? 0);
            $jp = $this->n($jRow['pref'] ?? 0);
            $jt = $this->n($jRow['total'] ?? 0);
            $this->putCell($pdf, $font, $colX, $cols, $cTaxMuni,  $y, $rowH, $this->fmtYen($jm), $fontSize, $padR, $showTest);
            $this->putCell($pdf, $font, $colX, $cols, $cTaxPref,  $y, $rowH, $this->fmtYen($jp), $fontSize, $padR, $showTest);
            $this->putCell($pdf, $font, $colX, $cols, $cTaxTotal, $y, $rowH, $this->fmtYen($jt), $fontSize, $padR, $showTest);
        }

        // ============================================================
        // 2) 税額控除（8行）
        //   - 課税所得金額列は斜線/空欄なので印字しない
        // ============================================================
        $baseIdx = count($rowsTop); // 9
        $rowsCredits = [
            ['key'=>'chosei',        'itax'=>false, 'jumin'=>true],
            ['key'=>'haito',         'itax'=>true,  'jumin'=>true],
            ['key'=>'jutaku',        'itax'=>true,  'jumin'=>true],
            ['key'=>'seitoto',       'itax'=>true,  'jumin'=>false],
            ['key'=>'after14',       'itax'=>true,  'jumin'=>true],
            ['key'=>'kifukin_other', 'itax'=>false, 'jumin'=>true],
            ['key'=>'kifukin_furu',  'itax'=>false, 'jumin'=>true],
            ['key'=>'saigai',        'itax'=>true,  'jumin'=>true],
        ];

        foreach ($rowsCredits as $i => $row) {
            $y = $by + $headerH + ($rowH * (float)($baseIdx + $i));
            $k = (string)$row['key'];
            $c = is_array($credits[$k] ?? null) ? $credits[$k] : [];
            $isFuru = ($k === 'kifukin_furu'); // ★ふるさと(※) 行

            if ($row['itax']) {
                $v = $this->n($c['itax'] ?? 0);
+                // ★上段と同じ位置に揃える（列5のみ右余白補正）
+                $this->putCell($pdf, $font, $colX, $cols, $cTaxItax, $y, $rowH, $this->fmtYen($v), $fontSize, $padR + 1.2, $showTest);
               }
               
            if ($row['jumin']) {
                $jm = $this->n($c['muni'] ?? 0);
                $jp = $this->n($c['pref'] ?? 0);
                $jt = $this->n($c['total'] ?? 0);
               // ★ふるさと(※) 行だけ 住民税3列を太字
                $style = $isFuru ? 'B' : '';
                $this->putCell($pdf, $font, $colX, $cols, $cTaxMuni,  $y, $rowH, $this->fmtYen($jm), $fontSize, $padR, $showTest, $style);
                $this->putCell($pdf, $font, $colX, $cols, $cTaxPref,  $y, $rowH, $this->fmtYen($jp), $fontSize, $padR, $showTest, $style);
                $this->putCell($pdf, $font, $colX, $cols, $cTaxTotal, $y, $rowH, $this->fmtYen($jt), $fontSize, $padR, $showTest, $style);
     }
        }

        // ============================================================
        // 3) 税額（3行）
        //   - 課税所得金額列は斜線/空欄なので印字しない
        // ============================================================
        $baseIdx2 = $baseIdx + count($rowsCredits); // 17
        // (a) 差引所得税額（所得割額）★（kijun_itax / gokei_jumin）
        $y0 = $by + $headerH + ($rowH * (float)$baseIdx2);
        $kijunItax = $this->n($final['kijun_itax'] ?? 0);
        $this->putCell($pdf, $font, $colX, $cols, $cTaxItax, $y0, $rowH, $this->fmtYen($kijunItax), $fontSize, $padR + 1.2, $showTest);
        $this->putCell($pdf, $font, $colX, $cols, $cTaxMuni,  $y0, $rowH, $this->fmtYen($this->n($finalJ['muni'] ?? 0)), $fontSize, $padR, $showTest);
        $this->putCell($pdf, $font, $colX, $cols, $cTaxPref,  $y0, $rowH, $this->fmtYen($this->n($finalJ['pref'] ?? 0)), $fontSize, $padR, $showTest);
        $this->putCell($pdf, $font, $colX, $cols, $cTaxTotal, $y0, $rowH, $this->fmtYen($this->n($finalJ['total'] ?? 0)), $fontSize, $padR, $showTest);

        // (b) 復興特別所得税額（fukkou_itax）※住民税は空欄
        $y1 = $by + $headerH + ($rowH * (float)($baseIdx2 + 1));
        $fukkou = $this->n($final['fukkou_itax'] ?? 0);
        $this->putCell($pdf, $font, $colX, $cols, $cTaxItax, $y1, $rowH, $this->fmtYen($fukkou), $fontSize, $padR + 1.2, $showTest);

        // (c) 合計（gokei_itax / gokei_jumin）
        $y2 = $by + $headerH + ($rowH * (float)($baseIdx2 + 2));
        $gokeiItax = $this->n($final['gokei_itax'] ?? 0);
        // ★税額の「合計」行：所得税列だけ太字
        $this->putCell($pdf, $font, $colX, $cols, $cTaxItax, $y2, $rowH, $this->fmtYen($gokeiItax), $fontSize, $padR + 1.2, $showTest, 'B');
 
        $this->putCell($pdf, $font, $colX, $cols, $cTaxMuni,  $y2, $rowH, $this->fmtYen($this->n($finalJ['muni'] ?? 0)), $fontSize, $padR, $showTest);
        $this->putCell($pdf, $font, $colX, $cols, $cTaxPref,  $y2, $rowH, $this->fmtYen($this->n($finalJ['pref'] ?? 0)), $fontSize, $padR, $showTest);
        $this->putCell($pdf, $font, $colX, $cols, $cTaxTotal, $y2, $rowH, $this->fmtYen($this->n($finalJ['total'] ?? 0)), $fontSize, $padR, $showTest);

        return $pdf->Output('', 'S');
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

    /** 例: "令和7年" → "令和7" */
    private function stripTrailingNen(string $wareki): string
    {
        $w = trim($wareki);
        if ($w === '') return '';
        return preg_match('/年$/u', $w) === 1 ? preg_replace('/年$/u', '', $w) : $w;
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
        float $padR,
        bool $debug,
        string $fontStyle = '' // ★追加: '' or 'B'
    ): void {
        $x = (float)$colX[$colIdx];
        $w = (float)$cols[$colIdx];
        $this->textBox($pdf, $font, $x, $y, $w, $h, $text, 'R', $fontSize, $padR, 0.0, $fontStyle);
        if ($debug) $this->debugRect($pdf, $x, $y, $w, $h);
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
        float $fontSize = 11.0,
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
        $pdf->MultiCell($innerW, $h, $text, 0, $align, false, 0, '', '', true, 0, false, true, $h, 'M', false);
    
        // ★疑似太字：ごく小さいオフセットで同じ文字をもう一度描く
        if ($isBold) {
            $pdf->SetXY($innerX + 0.2, $y); // 0.2mm（必要なら 0.15〜0.30 で調整）
            $pdf->MultiCell($innerW, $h, $text, 0, $align, false, 0, '', '', true, 0, false, true, $h, 'M', false);
        }
    }

    private function debugRect(TcpdfFpdi $pdf, float $x, float $y, float $w, float $h): void
    {
        $rgb = self::LAYOUT['debug']['rgb'] ?? [200, 0, 0];
        $lw  = (float)(self::LAYOUT['debug']['line_w'] ?? 0.2);
        $pdf->SetDrawColor((int)$rgb[0], (int)$rgb[1], (int)$rgb[2]);
        $pdf->SetLineWidth($lw);
        $pdf->Rect($x, $y, $w, $h);
    }
}