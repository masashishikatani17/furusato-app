<?php

namespace App\Services\Pdf\Templates;

use setasign\Fpdi\Tcpdf\Fpdi as TcpdfFpdi;

/**
 * 1_kifukingendogaku（寄附金上限額）を「背景テンプレPDF + mm座標印字」で生成する
 * - 背景PDF（罫線/ラベル/「円」/「－」）の上に「数値だけ」を mm 座標で配置する
 * - 座標は後で詰める前提。ここでは「座標配置できる形（fields配列 + textBox）」を用意する
 */
final class FurusatoKifukinGendogakuTemplateWriter
{
    // ============================================================
    // レイアウト（mm）
    //  - A4横: 297 x 210
    //  - 背景PDFは「余白込みの完成版」なので、ここはページ原点(0,0)基準で合わせ込む
    //  - まずは “だいたい当たる” 初期値。最終調整は mm 単位で詰めてください。
    // ============================================================
    private const LAYOUT = [
        // タイトル行：背景の「年の〜」の手前に「令和◯」だけ置く
        'title_year' => [
            'x' => 9.0,
            'y' => 13.5,
            'w' => 40.0,
            'h' => 7.5,
            'size' => 12.0,
            'align' => 'R',
        ],
        
        // 上段（寄附金上限額テーブル）：幅 248mm をページ中央へ
        //   col: [61,38,37,37,37,38] ＝ 248
        'upper' => [
            'x' => 20.5,     // (297-248)/2
            'y' => 40.5,     // ★仮：上段テーブル上端（要調整）
            'cols' => [61.0, 38.0, 37.0, 37.0, 37.0, 38.0],
            // header(2段)は背景固定なので、data行だけ使う。data行の上端yは header 高さ分ずらす。
            'header_h' => 21.0,   // 旧bladeの data-height-mm=21 と整合
            'row_h' => 9,       // ★仮：データ行高（要調整）
            // 数字セルは col[1]..col[5]（寄附金額〜負担額）に出す
            'pad_r' => 2.0,       // 右寄せの内側余白
            // ★行ごとのY補正（mm）：0行目/1行目/2行目...
            // 例：3行目（row=2）だけ下げたい → [0.0, 0.0, 1.1]
            'row_offsets' => [0.0, 0.0, 1.1],
        ],

        // 下段左：内訳タイトル（（令和◯年））
        'breakdown_title' => [
            'x' => 97.0,  // ★仮：左ブロック内のタイトル位置（ps-5相当を見込む）
            'y' => 102,        // ★仮：タイトル行Y（要調整）
            'w' => 80.0,
            'h' => 7.5,
            'size' => 14.0,
            'align' => 'L',
        ],

        // 下段左：内訳テーブル（幅 162mm）
        //   col: [59,21,19,21,21,21] ＝ 162
        'breakdown' => [
            'x' => 30,     // 左ブロック開始（上段と同じ起点で仮置き）
            'y' => 106.8,    // ★仮：内訳テーブル上端（要調整）
            'cols' => [61.5, 19.5, 19.5, 19.4, 19.4, 18.9],
            'header_h' => 18.0, // ★仮：ヘッダー3段分の高さ（背景固定のため数値行開始で使う）
            'row_h' => 5.38,      // ★仮：データ行高（要調整）
            'pad_r' => 1.6,
        ],

        // Debug表示（枠線）
        'debug' => [
            'on' => false,
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
        // 0) タイトル年号（背景の「年」の手前）
        // ============================================================
        $wareki = trim((string)($vars['wareki_year'] ?? ''));
        $warekiShort = $this->stripTrailingNen($wareki); // 例: 令和7年 → 令和7
        if ($warekiShort !== '') {
            $t = self::LAYOUT['title_year'];
            $this->textBox(
                $pdf, $font,
                (float)$t['x'], (float)$t['y'],
                (float)$t['w'], (float)$t['h'],
                $warekiShort,
                (string)$t['align'],
                (float)$t['size'],
                0.0, 0.0,
                (string)($t['style'] ?? '')
            );
            if ($showTest) $this->debugRect($pdf, (float)$t['x'], (float)$t['y'], (float)$t['w'], (float)$t['h']);
        }

        // ============================================================
        // 1) 上段：寄附金上限額・現在・差額（数値のみ、右寄せ）
        // ============================================================
        $upperBaseX = (float)self::LAYOUT['upper']['x'];
        $upperBaseY = (float)self::LAYOUT['upper']['y'];
        $upperCols  = self::LAYOUT['upper']['cols'];
        $upperHeaderH = (float)self::LAYOUT['upper']['header_h'];
        $upperRowH    = (float)self::LAYOUT['upper']['row_h'];
        $upperPadR    = (float)self::LAYOUT['upper']['pad_r'];
        $upperRowOffsets = self::LAYOUT['upper']['row_offsets'] ?? [];

        // 数字セル列（col index 1..5）
        $upperColX = $this->colLefts($upperBaseX, $upperCols);

        // 3行のデータ（limit/current/diff）
        $rowsUpper = [
            ['key' => 'kifukin_upper_table.limit',   'row' => 0],
            ['key' => 'kifukin_upper_table.current', 'row' => 1],
            ['key' => 'kifukin_upper_table.diff',    'row' => 2],
        ];
        $colsUpper = [
            ['k' => 'donation', 'col' => 1],
            ['k' => 'itax',     'col' => 2],
            ['k' => 'jumin',    'col' => 3],
            ['k' => 'total',    'col' => 4],
            ['k' => 'burden',   'col' => 5],
        ];

        foreach ($rowsUpper as $r) {
            $rowNo = (int)$r['row'];
            $rowOff = 0.0;
            if (is_array($upperRowOffsets) && array_key_exists($rowNo, $upperRowOffsets)) {
                $rowOff = (float)$upperRowOffsets[$rowNo];
            }
            $y = $upperBaseY + $upperHeaderH + ($upperRowH * (float)$rowNo) + $rowOff;
            foreach ($colsUpper as $c) {
                $colIdx = (int)$c['col'];
                $x = (float)$upperColX[$colIdx];
                $w = (float)$upperCols[$colIdx];
                $h = $upperRowH;
                $val = $this->getInt($vars, (string)$r['key'] . '.' . (string)$c['k'], 0);
                $txt = $this->fmtYen($val); // 背景に「円」あり → 数字のみ
                $this->textBox($pdf, $font, $x, $y, $w, $h, $txt, 'R', 12.0, $upperPadR);
                if ($showTest) $this->debugRect($pdf, $x, $y, $w, $h);
            }
        }

        // ============================================================
        // 2) 下段左：内訳タイトル（（令和◯年））
        //   - 背景PDFは括弧がある前提。ここは「令和7年」等をそのまま置く（必要なら調整）
        // ============================================================
        $wareki = trim((string)($vars['wareki_year'] ?? ''));
        $warekiShort = $this->stripTrailingNen($wareki); // 例: 令和7年 → 令和7
        if ($warekiShort !== '') {
            $bx = (float)self::LAYOUT['breakdown_title']['x'];
            $by = (float)self::LAYOUT['breakdown_title']['y'];
            $bw = (float)self::LAYOUT['breakdown_title']['w'];
            $bh = (float)self::LAYOUT['breakdown_title']['h'];
            $bs = (float)self::LAYOUT['breakdown_title']['size'];
            $ba = (string)self::LAYOUT['breakdown_title']['align'];
            // ★太字（B）
            $this->textBox($pdf, $font, $bx, $by, $bw, $bh, $warekiShort, $ba, $bs, 0.0, 0.0, 'B');
            if ($showTest) $this->debugRect($pdf, $bx, $by, $bw, $bh);
        }

        // ============================================================
        // 3) 下段左：内訳テーブル（当年=curr）
        //   - 背景に「－」が印刷済みのセルは“印字しない”
        // ============================================================
        $bdBaseX = (float)self::LAYOUT['breakdown']['x'];
        $bdBaseY = (float)self::LAYOUT['breakdown']['y'];
        $bdCols  = self::LAYOUT['breakdown']['cols'];
        $bdHeaderH = (float)self::LAYOUT['breakdown']['header_h'];
        $bdRowH    = (float)self::LAYOUT['breakdown']['row_h'];
        $bdPadR    = (float)self::LAYOUT['breakdown']['pad_r'];
        $bdColX = $this->colLefts($bdBaseX, $bdCols);

        // 行順（旧Bladeと同じ）
        $bdRowCats = [
            'furusato',
            'kyodobokin_nisseki',
            'seito',
            'npo',
            'koueki',
            'kuni',
            'sonota',
            'total',
        ];
        // 列（0=寄附先は背景に文字ありなので“印字しない”）
        // col index: 1=所得控除, 2=税額控除, 3=合計, 4=市区町村, 5=都道府県
        foreach ($bdRowCats as $i => $cat) {
            $rowY = $bdBaseY + $bdHeaderH + ($bdRowH * (float)$i);
            // ★太字にしたい行：1行目(furusato=0) と 8行目(total=7)
            $rowStyle = ($i === 0 || $i === 7) ? 'B' : '';

            // 参照キー
            $base = 'kifukin_breakdown_curr.' . (string)$cat . '.';
            $itaxIncome = $this->getInt($vars, $base . 'itax_income', 0);
            $itaxCredit = $this->getInt($vars, $base . 'itax_credit', 0);
            $itaxTotal  = $this->getInt($vars, $base . 'itax_total',  0);
            $rtaxMuni   = $this->getInt($vars, $base . 'rtax_muni',   0);
            $rtaxPref   = $this->getInt($vars, $base . 'rtax_pref',   0);

            // 印字対象の列を cat ごとに決める（背景「－」セルは印字しない）
            //   ふるさと/共同募金/その他：税額控除(所得税)は背景「－」
            //   政党/国：住民税（市・県）は背景「－」
            //   国：所得税 税額控除も背景「－」
            $print = [
                1 => $itaxIncome,
                2 => null,
                3 => $itaxTotal,
                4 => $rtaxMuni,
                5 => $rtaxPref,
            ];
            if (in_array($cat, ['seito'], true)) {
                $print[2] = $itaxCredit; // 政党等は税額控除が数値
                $print[4] = null;        // 住民税は「－」
                $print[5] = null;
            } elseif (in_array($cat, ['npo','koueki'], true)) {
                $print[2] = $itaxCredit; // NPO/公益は税額控除が数値
            } elseif (in_array($cat, ['kuni'], true)) {
                $print[2] = null;        // 国は税額控除「－」
                $print[4] = null;        // 住民税は「－」
                $print[5] = null;
            } elseif (in_array($cat, ['furusato','kyodobokin_nisseki','sonota'], true)) {
                $print[2] = null;        // 税額控除（所得税）は「－」
            } elseif ($cat === 'total') {
                $print[2] = $itaxCredit; // 合計行は税額控除も数値
            }

            // 出力（右寄せ）
            foreach ($print as $colIdx => $val) {
                if ($val === null) continue; // 背景「－」セルは印字しない
                $x = (float)$bdColX[(int)$colIdx];
                $w = (float)$bdCols[(int)$colIdx];
                $h = $bdRowH;
                $this->textBox($pdf, $font, $x, $rowY, $w, $h, $this->fmtYen((int)$val), 'R', 11.0, $bdPadR, 0.0, $rowStyle);
                if ($showTest) $this->debugRect($pdf, $x, $rowY, $w, $h);
            }
        }

        return $pdf->Output('', 'S');
    }

    // ============================================================
    // Helpers
    // ============================================================

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

    /** dot記法で配列から取得（見つからなければfallback） */
    private function get(mixed $vars, string $path, mixed $fallback = null): mixed
    {
        if (!is_array($vars)) return $fallback;
        if ($path === '') return $fallback;
        $cur = $vars;
        foreach (explode('.', $path) as $seg) {
            if ($seg === '') continue;
            if (!is_array($cur) || !array_key_exists($seg, $cur)) return $fallback;
            $cur = $cur[$seg];
        }
        return $cur;
    }

    private function getInt(array $vars, string $path, int $fallback = 0): int
    {
        $v = $this->get($vars, $path, null);
        if ($v === null || $v === '') return $fallback;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int)floor((float)$v) : $fallback;
    }

    private function fmtYen(int $v): string
    {
        return number_format($v);
    }


    /** 例: "令和7年" → "令和7" */
    private function stripTrailingNen(string $wareki): string
    {
        $w = trim($wareki);
        if ($w === '') return '';
        return preg_match('/年$/u', $w) === 1 ? (string)preg_replace('/年$/u', '', $w) : $w;
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
        string $fontStyle = ''   // ★追加: '' or 'B' etc.
    ): void {
         // NOTE: IPAex等は Bold 面を持たず 'B' が効かないことがあるため、
        //       'B' は「疑似太字（2回描画）」で対応する
        $isBold = ($fontStyle === 'B');
        $pdf->SetFont($font, '', $fontSize);
        $pdf->SetTextColor(0, 0, 0);

        // 内側余白（右寄せ時は padRight を効かせる）
        $innerX = $x + max(0.0, $padLeft);
        $innerW = $w - max(0.0, $padLeft) - max(0.0, $padRight);
        if ($innerW < 0.1) $innerW = 0.1;

         // MultiCell：border=0, ln=0, fill=0
        // valign は TCPDF のパラメータで制御しにくいので、h を小さめにして y を合わせる運用でOK
        $pdf->SetXY($innerX, $y);
        $pdf->MultiCell($innerW, $h, $text, 0, $align, false, 0, '', '', true, 0, false, true, $h, 'M', false);

        // ★疑似太字：ごく小さいオフセットで同じ文字をもう一度描く（見た目が確実に太くなる）
        if ($isBold) {
            $pdf->SetXY($innerX + 0.2, $y); // 0.2mm 右へ（必要なら 0.15〜0.30 で調整）
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


