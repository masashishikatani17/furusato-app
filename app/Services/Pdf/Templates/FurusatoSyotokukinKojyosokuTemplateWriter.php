<?php

namespace App\Services\Pdf\Templates;

use setasign\Fpdi\Tcpdf\Fpdi as TcpdfFpdi;

/**
 * 2_syotokukinkojyosoku（所得金額・所得控除額の予測）を
 * 「背景テンプレPDF + mm座標印字」で生成する。
 *
 * 背景PDF（罫線/ラベル/「－」）の上に「数値（カンマ付き）だけ」を mm 座標で配置する。
 * 座標は後で詰める前提。ここでは「座標配置できる形（layout + textBox）」を用意する。
 *
 * 背景テンプレ（1ページ）には、
 * - タイトル「年の所得金額・所得控除額の予測」
 * - 左：所得金額等（総合課税/分離課税）
 * - 右：所得から差し引かれる金額（各控除）
 * - 住民税側が「－」固定のセル（寄附金控除）
 * が印刷済みなので、ここでは数値のみ印字する。
 */
final class FurusatoSyotokukinKojyosokuTemplateWriter
{
    // ============================================================
    // レイアウト（mm）
    //  - A4横: 297 x 210
    //  - 背景PDFは “完成済み背景” なので、ページ原点(0,0)基準で合わせ込む
    //  - まずは「だいたい当たる」初期値。最終調整は mm 単位で詰めてください。
    // ============================================================
    private const LAYOUT = [

        // 旧Bladeのレイアウト幅（250mm）をページ中央へ
        'page' => [
            'w' => 297.0,
            'h' => 210.0,
            'content_w' => 250.0,
            'x' => 23.5, // (297-250)/2
        ],

        // タイトル行：背景には「年の所得金額・所得控除額の予測」があるので、
        // ここでは「令和7」など “年の手前” を印字する（末尾の「年」は除去して出す）。
        'title_year' => [
            'x' => 80,  // ★仮：中央付近（要調整）
            'y' => 16.4,   // ★仮：上端（要調整）
            'w' => 40.0,
            'h' => 7.5,
            'size' => 14.0,
            'align' => 'R',
            'style' => 'B',
        ],

        // 左ブロック（所得金額等）：幅 118mm、gap 10mm、右ブロック 122mm
        'left' => [
            'x' => 21,
            'y' =>32, // ★仮：左表 上端（要調整）
            'w' => 118.0,
            // colgroup: [11,11,11,27,29,29]
            // 数値列（所得税/住民税）は最後の2列
            'cols' => [11.0, 11.0, 11.0, 24.0, 27.2, 27.2],
            'header_h' => 8.0, // ★仮：ヘッダー1行（要調整）
            'row_h' => 6.28,    // ★仮：データ行高（要調整）
            'pad_r' => 0,
            'font' => 10.0,
            // ★数値の横幅が厳しい時だけ：%ストレッチ（例 90.0）
            'stretch' => 90.0,
        ],

        // 右ブロック（所得控除）：幅 122mm（背景上の右表は 119〜122mm 相当）
        'right' => [
            'x' => 24.5 + 118.0 + 10.0,
            'y' => 32, // ★仮：右表 上端（要調整）
            'w' => 120.0,
            // colgroup: [11,54,27,27]
            'cols' => [11.0, 51.0, 27.5, 27.5],
            'header_h' => 8.0, // ★仮：ヘッダー1行（要調整）
            'row_h' => 6.28,    // ★仮：データ行高（要調整）
            'pad_r' => 0,
            'font' => 10.0,
            // ★数値の横幅が厳しい時だけ：%ストレッチ（例 90.0）
            'stretch' => 90.0,
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
        // 0) タイトル年号（背景の「年の…」の手前）
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
                'B' // ★太字：タイトル年号（令和7）
            );
            if ($showTest) $this->debugRect($pdf, (float)$t['x'], (float)$t['y'], (float)$t['w'], (float)$t['h']);
        }

        // ============================================================
        // 1) 左表：所得金額等（income_table_curr）
        // ============================================================
        $income = is_array($vars['income_table_curr'] ?? null) ? $vars['income_table_curr'] : [];
        $sogo  = is_array($income['sogo'] ?? null) ? $income['sogo'] : [];
        $bunri = is_array($income['bunri'] ?? null) ? $income['bunri'] : [];

        $L = self::LAYOUT['left'];
        $lx = (float)$L['x'];
        $ly = (float)$L['y'];
        $lcols = $L['cols'];
        $lcolX = $this->colLefts($lx, $lcols);
        $lHeaderH = (float)$L['header_h'];
        $lRowH = (float)$L['row_h'];
        $lPadR = (float)$L['pad_r'];
        $lFont = (float)$L['font'];
        $lStretch = array_key_exists('stretch', $L) ? (float)$L['stretch'] : null;
        // 数値列 index（所得税=4, 住民税=5）
        $itaxColIdx = 4;
        $rtaxColIdx = 5;

        // 行順は旧Bladeと同じ（背景PDFも同順）:
        // 総合課税 10行 + 合計 1行、分離課税 9行 + 山林/退職 + 合計
        $rowsLeft = [
            // 総合課税
            ['src' => 'sogo',  'key' => 'jigyo_eigyo'],
            ['src' => 'sogo',  'key' => 'jigyo_nogyo'],
            ['src' => 'sogo',  'key' => 'fudosan'],
            ['src' => 'sogo',  'key' => 'rishi'],
            ['src' => 'sogo',  'key' => 'haito'],
            ['src' => 'sogo',  'key' => 'kyuyo'],
            ['src' => 'sogo',  'key' => 'zatsu_nenkin'],
            ['src' => 'sogo',  'key' => 'zatsu_gyomu'],
            ['src' => 'sogo',  'key' => 'zatsu_sonota'],
            ['src' => 'sogo',  'key' => 'joto_ichiji'],
            ['src' => 'income','key' => 'sogo_total'],
            // 分離課税
            ['src' => 'bunri', 'key' => 'tanki_ippan'],
            ['src' => 'bunri', 'key' => 'tanki_keigen'],
            ['src' => 'bunri', 'key' => 'choki_ippan'],
            ['src' => 'bunri', 'key' => 'choki_tokutei'],
            ['src' => 'bunri', 'key' => 'choki_keika'],
            ['src' => 'bunri', 'key' => 'ippan_kabu'],
            ['src' => 'bunri', 'key' => 'jojo_kabu'],
            ['src' => 'bunri', 'key' => 'jojo_haito'],
            ['src' => 'bunri', 'key' => 'sakimono'],
            ['src' => 'bunri', 'key' => 'sanrin'],
            ['src' => 'bunri', 'key' => 'taishoku'],
            ['src' => 'income','key' => 'grand_total'],
        ];

        foreach ($rowsLeft as $i => $r) {
            $rowY = $ly + $lHeaderH + ($lRowH * (float)$i);
            $pair = $this->readPair($income, $sogo, $bunri, (string)$r['src'], (string)$r['key']);

            $ix = (float)$lcolX[$itaxColIdx];
            $iw = (float)$lcols[$itaxColIdx];
            $rx = (float)$lcolX[$rtaxColIdx];
            $rw = (float)$lcols[$rtaxColIdx];

             $this->textBox($pdf, $font, $ix, $rowY, $iw, $lRowH, $this->fmtYen((int)$pair['itax']), 'R', $lFont, $lPadR, 0.0, '', $lStretch);
             $this->textBox($pdf, $font, $rx, $rowY, $rw, $lRowH, $this->fmtYen((int)$pair['rtax']), 'R', $lFont, $lPadR, 0.0, '', $lStretch);
            if ($showTest) {
                $this->debugRect($pdf, $ix, $rowY, $iw, $lRowH);
                $this->debugRect($pdf, $rx, $rowY, $rw, $lRowH);
            }
        }

        // ============================================================
        // 2) 右表：所得控除（kojo_table_curr）
        // ============================================================
        $kojo = is_array($vars['kojo_table_curr'] ?? null) ? $vars['kojo_table_curr'] : [];
        $kRows = is_array($kojo['rows'] ?? null) ? $kojo['rows'] : [];

        $R = self::LAYOUT['right'];
        $rx0 = (float)$R['x'];
        $ry0 = (float)$R['y'];
        $rcols = $R['cols'];
        $rcolX = $this->colLefts($rx0, $rcols);
        $rHeaderH = (float)$R['header_h'];
        $rRowH = (float)$R['row_h'];
        $rPadR = (float)$R['pad_r'];
        $rFont = (float)$R['font'];
        $rStretch = array_key_exists('stretch', $R) ? (float)$R['stretch'] : null;

        // 数値列 index（所得税=2, 住民税=3）
        $ritaxColIdx = 2;
        $rrtaxColIdx = 3;

        // 旧Blade順（背景も同順）
        $rowsRight = [
            ['src' => 'rows',  'key' => 'shakaihoken'],
            ['src' => 'rows',  'key' => 'shokibo'],
            ['src' => 'rows',  'key' => 'seimei'],
            ['src' => 'rows',  'key' => 'jishin'],
            ['src' => 'rows',  'key' => 'kafu'],
            ['src' => 'rows',  'key' => 'hitorioya'],
            ['src' => 'rows',  'key' => 'kinrogakusei'],
            ['src' => 'rows',  'key' => 'shogaisha'],
            ['src' => 'rows',  'key' => 'haigusha'],
            ['src' => 'rows',  'key' => 'haigusha_tok'],
            ['src' => 'rows',  'key' => 'fuyo'],
            ['src' => 'rows',  'key' => 'tokutei_shinz'],
            ['src' => 'rows',  'key' => 'kiso'],
            ['src' => 'kojo',  'key' => 'shokei'],
            ['src' => 'kojo',  'key' => 'zasson'],
            ['src' => 'kojo',  'key' => 'iryo'],
            // 寄附金控除：所得税のみ、住民税は背景「－」固定
            ['src' => 'kojo',  'key' => 'kifukin_itax_only'],
            ['src' => 'kojo',  'key' => 'total'],
        ];

        foreach ($rowsRight as $i => $r) {
            $rowY = $ry0 + $rHeaderH + ($rRowH * (float)$i);

            $pair = $this->readKojoPair($kojo, $kRows, (string)$r['src'], (string)$r['key']);

            $ix = (float)$rcolX[$ritaxColIdx];
            $iw = (float)$rcols[$ritaxColIdx];
            $jx = (float)$rcolX[$rrtaxColIdx];
            $jw = (float)$rcols[$rrtaxColIdx];

           // ★寄附金控除（所得税のみ）は太字にしたい
            $style = ((string)$r['key'] === 'kifukin_itax_only') ? 'B' : '';
            $this->textBox($pdf, $font, $ix, $rowY, $iw, $rRowH, $this->fmtYen((int)$pair['itax']), 'R', $rFont, $rPadR, 0.0, $style, $rStretch);

            if ($pair['rtax'] !== null) {
                $this->textBox($pdf, $font, $jx, $rowY, $jw, $rRowH, $this->fmtYen((int)$pair['rtax']), 'R', $rFont, $rPadR, 0.0, '', $rStretch);
            }
            if ($showTest) {
                $this->debugRect($pdf, $ix, $rowY, $iw, $rRowH);
                if ($pair['rtax'] !== null) 
                $this->debugRect($pdf, $jx, $rowY, $jw, $rRowH);
            }
        }

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

    /** @return array{itax:int,rtax:int} */
    private function readPair(array $income, array $sogo, array $bunri, string $src, string $key): array
    {
        $row = null;
        if ($src === 'sogo') {
            $row = is_array($sogo[$key] ?? null) ? $sogo[$key] : null;
        } elseif ($src === 'bunri') {
            $row = is_array($bunri[$key] ?? null) ? $bunri[$key] : null;
        } elseif ($src === 'income') {
            $row = is_array($income[$key] ?? null) ? $income[$key] : null;
        }
        if (!is_array($row)) {
            return ['itax' => 0, 'rtax' => 0];
        }
        return [
            'itax' => $this->n($row['itax'] ?? 0),
            'rtax' => $this->n($row['rtax'] ?? 0),
        ];
    }

    /** @return array{itax:int,rtax:?int} rtax=null は「背景が － 固定なので印字しない」 */
    private function readKojoPair(array $kojo, array $rows, string $src, string $key): array
    {
        if ($key === 'kifukin_itax_only') {
            $it = $this->n($kojo['kifukin_itax'] ?? 0);
            return ['itax' => $it, 'rtax' => null]; // 住民税は背景「－」
        }

        $row = null;
        if ($src === 'rows') {
            $row = is_array($rows[$key] ?? null) ? $rows[$key] : null;
        } elseif ($src === 'kojo') {
            $row = is_array($kojo[$key] ?? null) ? $kojo[$key] : null;
        }
        if (!is_array($row)) {
            return ['itax' => 0, 'rtax' => 0];
        }
        return [
            'itax' => $this->n($row['itax'] ?? 0),
            'rtax' => $this->n($row['rtax'] ?? 0),
        ];
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
        string $fontStyle = '',   // '' or 'B'
        ?float $stretchPct = null // ★追加: 例 90.0（%）
    ): void {
        // NOTE: IPAex等は Bold 面を持たず 'B' が効きにくい場合があるため、
        //       'B' は「疑似太字（2回描画）」で対応する
        $isBold = ($fontStyle === 'B');
        $pdf->SetFont($font, '', $fontSize);
     
        $pdf->SetTextColor(0, 0, 0);

         // ★ストレッチ（指定がある時だけ効かせ、最後に必ず戻す）
         $stretchInt = null;
         if ($stretchPct !== null) {
             $stretchInt = (int)max(50, min(150, round($stretchPct))); // 安全域: 50%〜150%
             $pdf->SetFontStretching($stretchInt);
         }

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
         
         // ★ストレッチを戻す（他の出力に影響させない）
         if ($stretchInt !== null) {
             $pdf->SetFontStretching(100);
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
