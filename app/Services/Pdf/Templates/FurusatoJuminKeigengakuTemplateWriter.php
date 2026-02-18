<?php

namespace App\Services\Pdf\Templates;

use setasign\Fpdi\Tcpdf\Fpdi as TcpdfFpdi;

/**
 * 4_juminkeigengaku / 4_juminkeigengaku_onestop を
 * 「背景テンプレPDF + mm座標印字」で生成する。
 *
 * 背景PDF（罫線/ラベル/丸番号/注意書き）の上に「数値だけ」を mm 座標で配置する。
 * - 通常版（4_juminkeigengaku_bg.pdf）: 所得税軽減 + 比較 + 住民税(①〜⑭) + 下段まとめ
 * - ワンストップ版（4_juminkeigengaku_onestop_bg.pdf）: 比較 + 住民税(①〜⑰)（※所得税軽減なし）
 * 座標は後で詰める前提。ここでは「座標配置できる形（layout + textBox + debug枠）」を用意する。
 */
final class FurusatoJuminKeigengakuTemplateWriter
{
    // ============================================================
    // レイアウト（mm）
    //  - A4横: 297 x 210
    //  - 背景PDFは “完成済み背景” なので、ページ原点(0,0)基準で合わせ込む
    //  - まずは「だいたい当たる」初期値。最終調整は mm 単位で詰めてください。
    // ============================================================
    private const LAYOUT = [
        // 通常版（4_juminkeigengaku）
        'normal' => [
            // 右上「寄附金額と減税額の比較」小表（幅52mm想定）
            'compare' => [
                'x' => 192.0, // ★仮
                'y' => 38.5,  // ★仮
                'w' => 52.5,
                'row_h' => 6.5,
                 // ★行ごとのY補正（mm）：0行目/1行目/2行目
                // 例：3行目（差引:負担額）だけ少し上げる → [0.0, 0.0, -1.0]
                'row_offsets' => [0.0, 0.0, -1.0],
                'val_x' => 22.0, // 左列(30mm)の右隣が数値列(22mm)
                'val_w' => 30.5,
                'font'  => 11.5,
                'pad_r' => 0,
                // ★比較小表（3つの数字）だけ横幅を詰める
                'stretch' => 90.0,
            ],
            // 左上「所得税の軽減額」2列（幅105mm、右列30mm）
            'itax' => [
                'x' => 38.0, // ★仮
                'y' => 38.0, // ★仮
                'val_x' => 75.0, // 左列75mm + 右列30mm
                'val_w' => 30.0,
                'row_h' => 6.5,
                'font'  => 11.5,
                'pad_r' => 1.8,
            ],
            // 中央大表（住民税：①〜⑭）
            // colgroup: [8,9,54,8,27,27,27,90]（数値列は muni/pref/total）
            'jumin' => [
                'x' => 23.5, // ★仮：幅250mmを中央へ
                'y' => 67.5, // ★仮：表上端（要調整）
                'cols' => [8.0, 9.0, 54.0, 8.0, 27.0, 27.0, 27.0, 90.0],
                'header_h' => 6.3, // ヘッダー1行（背景固定）
                'row_h' => 6.4,    // ★仮
                'font'  => 11.0,
                'pad_r' => 0.8,
                'stretch' => 90.0, // ★数値が入らない時だけ：90%ストレッチ（％行は除外）
            ],
            // 下段まとめ（4行） col: [79,27,27,27]
            'summary' => [
                'x' => 23.5,
                'y' => 165.8, // ★仮：まとめ表上端（要調整）
                'cols' => [79.0, 27.0, 27.0, 27.0],
                'row_h' => 6.5,
                'font'  => 11.0,
                'pad_r' => 0.8,
                'stretch' => 90.0, // ★下段まとめも90%ストレッチ
            ],
        ],

        // ワンストップ版（4_juminkeigengaku_onestop）
        // ※背景が違うので座標は別で持つ（とりあえず枠だけ）
        'onestop' => [
            'compare' => [
                // 背景（ワンストップ）左上の小表（寄附金額/減税額/差引：負担額）
                //  - 2列: 左ラベル + 右数値枠
                'x' => 32.0,   // ★初期値（要微調整）
                'y' => 45.2,   // ★初期値（要微調整）
                'w' => 72.0,   // 小表の全幅（参考）
                'row_h' => 7.2,
                // 右列（数値枠）の開始位置（左列 40mm, 右列 32mm 相当）
                'val_x' => 36.5,
                'val_w' => 35.5,
                'font'  => 11.5,
                'pad_r' => 0.6,
                // ★比較小表（3つの数字）だけ横幅を詰める
                'stretch' => 90.0,
            ],
            // メイン表（①〜⑰）※通常より行数多い想定
            'jumin' => [
                // 背景（ワンストップ）大表：①〜⑰ が固定行で並ぶ
                'x' => 27.8,  // (297-242)/2
                'y' => 73.4,  // ★初期値（要微調整）
                // colgroup: [9,54,8,27,27,27,90]（onestop blade側）
                'cols' => [9.0, 54.0, 7.2, 27.3, 27.3, 27.3, 90.0],
                'header_h' => 5.75,
                'row_h' => 6.4,
                'font'  => 11.0,
                'pad_r' => 0,
                // ★ワンストップの数値：基本は 90% ストレッチ（※例外行は処理側で無効化）
                'stretch' => 90.0,
            ],
            // 下段まとめ（4行） col: [79,27,27,27] 相当（通常版と同じ配列で出す）
            // ※背景に罫線が無い可能性があるため、まずはページ下部の空きに印字だけする
            'summary' => [
                'x' => 27.8,     // jumin表と同じ左端に合わせる
                'y' => 186.0,    // ★仮：表の直下（要調整）
                'cols' => [79.0, 27.0, 27.0, 27.0],
                'row_h' => 4.8,  // ★仮：4行入るように少し詰める
                'font'  => 9.5,
                'pad_r' => 0.8,
                'stretch' => 90.0,
            ],
        ],

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
        // 印刷時の自動縮小を抑制（ViewerPreferences）
        // - Edge/ブラウザ印刷やドライバ側の強制縮小は別途発生しうるが、
        //   PDF側で「拡大縮小しない」意図を明示しておく。
        if (method_exists($pdf, 'setViewerPreferences')) {
            $pdf->setViewerPreferences([
                'PrintScaling' => 'None',
            ]);
        }
 
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
        // itax_no_furusato が無いケースは onestop 扱い（通常版は itax_* が必ずある想定）
        $isOnestop = !array_key_exists('itax_no_furusato', $vars) && !array_key_exists('itax_at_max', $vars);

        if ($isOnestop) {
            $this->renderOnestop($pdf, $font, $vars, $showTest);
            return $pdf->Output('', 'S');
        }

        $this->renderNormal($pdf, $font, $vars, $showTest);

        return $pdf->Output('', 'S');
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',', ' '], '', $v);
        return is_numeric($v) ? (int)floor((float)$v) : 0;
    }

    // ============================================================
    // Normal (4_juminkeigengaku)
    // ============================================================
    private function renderNormal(TcpdfFpdi $pdf, string $font, array $vars, bool $debug): void
    {
        $L = self::LAYOUT['normal'];

        // 右上：寄附金額と減税額の比較
        $donation = $this->n($vars['donation_amount'] ?? 0);
        $savedTot = $this->n($vars['tax_saved_total'] ?? 0);
        $burden   = $this->n($vars['burden_amount'] ?? 0);
        $cmp = $L['compare'];
        $cx = (float)$cmp['x'];
        $cy = (float)$cmp['y'];
        $rowH = (float)$cmp['row_h'];
        $rowOffsets = is_array($cmp['row_offsets'] ?? null) ? $cmp['row_offsets'] : [];
        $vx = $cx + (float)$cmp['val_x'];
        $vw = (float)$cmp['val_w'];
        $fs = (float)$cmp['font'];
        $pr = (float)$cmp['pad_r'];
        $cmpStretch = array_key_exists('stretch', $cmp) ? (float)$cmp['stretch'] : null;
        foreach ([
            0 => $donation,
            1 => $savedTot,
            2 => $burden,
        ] as $i => $val) {
            $off = array_key_exists((int)$i, $rowOffsets) ? (float)$rowOffsets[(int)$i] : 0.0;
            $y = $cy + ($rowH * (float)$i) + $off;
            $this->textBox($pdf, $font, $vx, $y, $vw, $rowH, $this->fmtYen($val), 'R', $fs, $pr, 0.0, 'B', $cmpStretch);
            if ($debug) $this->debugRect($pdf, $vx, $y, $vw, $rowH);
        }

        // 左上：所得税の軽減額（3行）
        $itNo  = $this->n($vars['itax_no_furusato'] ?? 0);
        $itMax = $this->n($vars['itax_at_max'] ?? 0);
        $itSav = $this->n($vars['itax_saved'] ?? 0);
        $it = $L['itax'];
        $ix = (float)$it['x'];
        $iy = (float)$it['y'];
        $iRowH = (float)$it['row_h'];
        $vix = $ix + (float)$it['val_x'];
        $viw = (float)$it['val_w'];
        $ifs = (float)$it['font'];
        $ipr = (float)$it['pad_r'];
        foreach ([
            0 => $itNo,
            1 => $itMax,
            2 => $itSav,
        ] as $i => $val) {
            $y = $iy + ($iRowH * (float)$i);
           // ★太字：3行目（差額＝所得税の軽減額）だけ
            $style = ((int)$i === 2) ? 'B' : '';
            $this->textBox($pdf, $font, $vix, $y, $viw, $iRowH, $this->fmtYen($val), 'R', $ifs, $ipr, 0.0, $style);
             
            if ($debug) $this->debugRect($pdf, $vix, $y, $viw, $iRowH);
        }

        // 住民税の軽減額（①〜⑭）
        $rows = is_array($vars['jumin_rows'] ?? null) ? $vars['jumin_rows'] : [];
        $sum  = is_array($vars['jumin_summary'] ?? null) ? $vars['jumin_summary'] : [];

        $tbl = $L['jumin'];
        $tx = (float)$tbl['x'];
        $ty = (float)$tbl['y'];
        $cols = $tbl['cols'];
        $colX = $this->colLefts($tx, $cols);
        $headerH = (float)$tbl['header_h'];
        $rowH2 = (float)$tbl['row_h'];
        $fs2 = (float)$tbl['font'];                 // 既定フォント
        $fs2Small = max(1.0, $fs2 - 1.0);            // ★数値だけ 1pt(相当) 小さく
        $pr2 = (float)$tbl['pad_r'];
        $jStretch = array_key_exists('stretch', $tbl) ? (float)$tbl['stretch'] : null;
        // 数値列 index: muni=4, pref=5, total=6
        $cM = 4; $cP = 5; $cT = 6;

        // 行順（背景どおり）
        $seq = [
            ['t'=>'mae'],                 // ①
            ['t'=>'chosei_kojo'],          // ②
            ['t'=>'go'],                  // ③
            ['t'=>'kihon.target'],        // ④
            ['t'=>'kihon.cap30'],         // ⑤
            ['t'=>'kihon.min'],           // ⑥
            ['t'=>'kihon.rate_pct'],      // ⑦（％表示）
            ['t'=>'kihon.amount'],        // ⑧
            ['t'=>'tokurei.target'],      // ⑨
            ['t'=>'tokurei.rate_final'],  // ⑩（％小数2桁）
            ['t'=>'tokurei.calc11'],      // ⑪（小数2位 “文字列”）
            ['t'=>'tokurei.cap20'],       // ⑫
            ['t'=>'tokurei.jogen'],       // ⑬
            ['t'=>'kifukin_total'],       // ⑭
        ];

        foreach ($seq as $i => $spec) {
            $y = $ty + $headerH + ($rowH2 * (float)$i);
            $t = (string)$spec['t'];
            // ★例外：%の2行だけはフォント据え置き（行が詰まらない/見やすさ優先）
            $fsRow = ($t === 'kihon.rate_pct' || $t === 'tokurei.rate_final') ? $fs2 : $fs2Small;
            // ★⑩（特例控除割合）は小数3位で桁が増えるため、ストレッチを有効にする
            $stretchRow = ($t === 'kihon.rate_pct') ? null : (($t === 'tokurei.rate_final') ? $jStretch : $jStretch);

            if ($t === 'kihon.rate_pct') {
                $rate = is_array($rows['kihon']['rate'] ?? null) ? $rows['kihon']['rate'] : [];
                $muniPct = (int)($rate['muni'] ?? 0);
                $prefPct = (int)($rate['pref'] ?? 0);
                $totPct  = (int)($rate['total'] ?? ($muniPct + $prefPct));
                $this->putJumin3(
                    $pdf, $font, $colX, $cols, $cM, $cP, $cT, $y, $rowH2,
                    $this->fmtPctInt($muniPct), $this->fmtPctInt($prefPct), $this->fmtPctInt($totPct),
                    $fsRow, $pr2, $debug, '', $stretchRow
                );
                continue;
            }
            if ($t === 'tokurei.rate_final') {
                $tok = is_array($rows['tokurei'] ?? null) ? $rows['tokurei'] : [];
                // ★按分後割合があればそれを使用（市/県/合計）
                $split = is_array($tok['rate_final_pct_split'] ?? null) ? $tok['rate_final_pct_split'] : null;
                if (is_array($split)) {
                    $m = $this->fmtPct3((float)($split['muni'] ?? 0.0));
                    $p = $this->fmtPct3((float)($split['pref'] ?? 0.0));
                    $t3 = $this->fmtPct3((float)($split['total'] ?? 0.0));
                    $this->putJumin3($pdf, $font, $colX, $cols, $cM, $cP, $cT, $y, $rowH2, $m, $p, $t3, $fsRow, $pr2, $debug, '', $stretchRow);
                } else {
                    $pct = (float)($tok['rate_final_pct'] ?? 0.0);
                    $pp = $this->fmtPct3($pct);
                    $this->putJumin3($pdf, $font, $colX, $cols, $cM, $cP, $cT, $y, $rowH2, $pp, $pp, $pp, $fsRow, $pr2, $debug, '', $stretchRow);
                }
                 continue;
            }
            if ($t === 'tokurei.calc11') {
                $tok = is_array($rows['tokurei'] ?? null) ? $rows['tokurei'] : [];
                $c11 = is_array($tok['calc11'] ?? null) ? $tok['calc11'] : ['muni'=>'0.00','pref'=>'0.00','total'=>'0.00'];
                $this->putJumin3($pdf, $font, $colX, $cols, $cM, $cP, $cT, $y, $rowH2,
                    $this->fmtYen2($c11['muni'] ?? '0.00'),
                    $this->fmtYen2($c11['pref'] ?? '0.00'),
                    $this->fmtYen2($c11['total'] ?? '0.00'),
                    $fsRow, $pr2, $debug, '', $stretchRow
                );
                continue;
            }

            $v = $this->getTripleByPath($rows, $t);
            $muniTxt  = $this->fmtYen((int)$v['muni']);
            $prefTxt  = $this->fmtYen((int)$v['pref']);
            $totalTxt = $this->fmtYen((int)$v['total']);
            // ★原稿PDF側に「－」が入っているため、ここは空欄にする（値も「－」も出さない）
            if (in_array($t, ['kihon.target','kihon.cap30','kihon.min','tokurei.target'], true)) {
                $totalTxt = '';
            }
            $this->putJumin3($pdf, $font, $colX, $cols, $cM, $cP, $cT, $y, $rowH2,
                $muniTxt,
                $prefTxt,
                $totalTxt,
                $fsRow, $pr2, $debug, '', $stretchRow
            );
        }

        // 下段まとめ（4行）
        $s = $L['summary'];
        $sx = (float)$s['x'];
        $sy = (float)$s['y'];
        $scols = $s['cols'];
        $scolX = $this->colLefts($sx, $scols);
        $sRowH = (float)$s['row_h'];
        $sFs = (float)$s['font'];
        $sFsSmall = max(1.0, $sFs - 1.0);            // ★下段まとめも数値だけ 1pt(相当) 小さく
        $sPr = (float)$s['pad_r'];
        $sStretch = array_key_exists('stretch', $s) ? (float)$s['stretch'] : null;
        // 数値列 index: muni=1, pref=2, total=3
        $sM = 1; $sP = 2; $sT = 3;
        $sumSeq = ['other','furusato_only','unable','final'];
        foreach ($sumSeq as $i => $k) {
            $y = $sy + ($sRowH * (float)$i);
            $row = is_array($sum[$k] ?? null) ? $sum[$k] : ['muni'=>0,'pref'=>0,'total'=>0];
            $this->textBox($pdf, $font, (float)$scolX[$sM], $y, (float)$scols[$sM], $sRowH, $this->fmtYen($this->n($row['muni'] ?? 0)), 'R', $sFsSmall, $sPr, 0.0, '', $sStretch);
            $this->textBox($pdf, $font, (float)$scolX[$sP], $y, (float)$scols[$sP], $sRowH, $this->fmtYen($this->n($row['pref'] ?? 0)), 'R', $sFsSmall, $sPr, 0.0, '', $sStretch);
    // ★太字：下段まとめ「2行目」「4行目」の合計列だけ
            $sumStyle = (((int)$i === 1) || ((int)$i === 3)) ? 'B' : '';
            $this->textBox($pdf, $font, (float)$scolX[$sT], $y, (float)$scols[$sT], $sRowH, $this->fmtYen($this->n($row['total'] ?? 0)), 'R', $sFsSmall, $sPr, 0.0, $sumStyle, $sStretch);
             if ($debug) {
                $this->debugRect($pdf, (float)$scolX[$sM], $y, (float)$scols[$sM], $sRowH);
                $this->debugRect($pdf, (float)$scolX[$sP], $y, (float)$scols[$sP], $sRowH);
                $this->debugRect($pdf, (float)$scolX[$sT], $y, (float)$scols[$sT], $sRowH);
            }
        }
    }

    // ============================================================
    // Onestop (4_juminkeigengaku_onestop)
    //  - とりあえず「比較小表 + jumin_rows（ある分だけ）」を出す
    //  - ⑮（ratio）などは後で必要に応じて fields を追加して詰める
    // ============================================================
    private function renderOnestop(TcpdfFpdi $pdf, string $font, array $vars, bool $debug): void
    {
        $L = self::LAYOUT['onestop'];
        $donation = $this->n($vars['donation_amount'] ?? 0);
        $savedTot = $this->n($vars['tax_saved_total'] ?? 0);
        $burden   = $this->n($vars['burden_amount'] ?? 0);

        // 比較小表（寄附/減税/負担）
        $cmp = $L['compare'];
        $cx = (float)$cmp['x'];
        $cy = (float)$cmp['y'];
        $rowH = (float)$cmp['row_h'];
        $vx = $cx + (float)$cmp['val_x'];
        $vw = (float)$cmp['val_w'];
        $fs = (float)$cmp['font'];
        $pr = (float)$cmp['pad_r'];
        $cmpStretch = array_key_exists('stretch', $cmp) ? (float)$cmp['stretch'] : null;
        foreach ([0=>$donation,1=>$savedTot,2=>$burden] as $i=>$val) {
            $y = $cy + ($rowH * (float)$i);
            // ★太字：比較小表の3行すべて
            $this->textBox($pdf, $font, $vx, $y, $vw, $rowH, $this->fmtYen($val), 'R', $fs, $pr, 0.0, 'B', $cmpStretch);
  
            if ($debug) $this->debugRect($pdf, $vx, $y, $vw, $rowH);
        }

        // 住民税表（①〜⑰）
        // - 背景は「行が固定」なので、スキップせず 17 行分を固定出力する
        $rows = is_array($vars['jumin_rows'] ?? null) ? $vars['jumin_rows'] : [];
        $tbl = $L['jumin'];
        $tx = (float)$tbl['x'];
        $ty = (float)$tbl['y'];
        $cols = $tbl['cols'];
        $colX = $this->colLefts($tx, $cols);
        $headerH = (float)$tbl['header_h'];
        $rowH2 = (float)$tbl['row_h'];
        $fs2 = (float)$tbl['font'];
        $pr2 = (float)$tbl['pad_r'];
        $st2 = array_key_exists('stretch', $tbl) ? (float)$tbl['stretch'] : null;
        // 数値列 index: muni=3, pref=4, total=5（onestop colgroup）
        $cM = 3; $cP = 4; $cT = 5;

        // 固定 17 行（背景PDFの番号①〜⑰に対応）:contentReference[oaicite:1]{index=1}
        $lines = [
            // ①〜③
            ['kind'=>'triple', 'path'=>'mae'],
            ['kind'=>'triple', 'path'=>'chosei_kojo'],
            ['kind'=>'triple', 'path'=>'go'],
            // ④〜⑧ 基本控除
            ['kind'=>'triple', 'path'=>'kihon.target'],
            ['kind'=>'triple', 'path'=>'kihon.cap30'],
            ['kind'=>'triple', 'path'=>'kihon.min'],
            ['kind'=>'pct_int', 'path'=>'kihon.rate'],     // %（整数）
            ['kind'=>'triple', 'path'=>'kihon.amount'],
            // ⑨〜⑬ 特例控除
            ['kind'=>'triple', 'path'=>'tokurei.target'],
            ['kind'=>'pct_2',  'path'=>'tokurei.rate_final_pct'], // %（小数2桁、3列同値）
            ['kind'=>'calc11', 'path'=>'tokurei.calc11'],         // 小数2位文字列
            ['kind'=>'triple', 'path'=>'tokurei.cap20'],
            ['kind'=>'triple', 'path'=>'tokurei.jogen'],
            // ⑭ 特例控除額（=⑬を再掲）
            ['kind'=>'triple', 'path'=>'tokurei.jogen'],
            // ⑮ 所得税率の割合（%文字列を3列同値）
            ['kind'=>'pct_str', 'path'=>'shinkoku_ratio15_pct'],
            // ⑯ 申告特例控除額
            ['kind'=>'triple', 'path'=>'shinkoku'],
            // ⑰ 合計
            ['kind'=>'triple', 'path'=>'kifukin_total'],
        ];

        foreach ($lines as $i => $spec) {
            $y = $ty + $headerH + ($rowH2 * (float)$i);
            $kind = (string)$spec['kind'];
            $path = (string)$spec['path'];
            // ★太字：一番下（⑰ 合計）の行だけ
            $isBoldTotalRow = ((int)$i === (count($lines) - 1));
            // ★例外（修正なし）：% の行は「フォントサイズもストレッチも変えない」
            //   7行目: 基本控除額→控除率（idx=6）
            //  10行目: 特例控除額→特例控除割合（idx=9）
            //  15行目: 申告特例控除額→所得税率の割合（idx=14）
            $isNoChangePctRow = in_array((int)$i, [6, 9, 14], true);
            // ★基本方針：数値は「1つ小さく」+「90%ストレッチ」
            $rowFont = $isNoChangePctRow ? $fs2 : max(1.0, $fs2 - 1.0);
            $rowStretch = $isNoChangePctRow ? null : $st2;
 
            if ($kind === 'pct_int') {
                $rate = $this->getTripleByPath($rows, $path); // muni/pref/total を使う（無ければ0）
                $this->putJumin3(
                    $pdf, $font, $colX, $cols, $cM, $cP, $cT, $y, $rowH2,
                    $this->fmtPctInt((int)$rate['muni']),
                    $this->fmtPctInt((int)$rate['pref']),
                    $this->fmtPctInt((int)$rate['total']),
                    $rowFont, $pr2, $debug,
                    $isBoldTotalRow ? 'B' : ''
                    , $rowStretch
                );
                continue;
            }
            if ($kind === 'pct_2') {
                $tok = is_array($rows['tokurei'] ?? null) ? $rows['tokurei'] : [];
                $split = is_array($tok['rate_final_pct_split'] ?? null) ? $tok['rate_final_pct_split'] : null;
                if (is_array($split)) {
                    $m = $this->fmtPct3((float)($split['muni'] ?? 0.0));
                    $p = $this->fmtPct3((float)($split['pref'] ?? 0.0));
                    $t3 = $this->fmtPct3((float)($split['total'] ?? 0.0));
                    $this->putJumin3($pdf, $font, $colX, $cols, $cM, $cP, $cT, $y, $rowH2, $m, $p, $t3, $fs2, $pr2, $debug, $isBoldTotalRow ? 'B' : '', $rowStretch);
                } else {
                    $pct = (float)($tok['rate_final_pct'] ?? 0.0);
                    $pp = $this->fmtPct3($pct);
                    $this->putJumin3($pdf, $font, $colX, $cols, $cM, $cP, $cT, $y, $rowH2, $pp, $pp, $pp, $fs2, $pr2, $debug, $isBoldTotalRow ? 'B' : '', $rowStretch);
                }
                continue;
            }
            if ($kind === 'pct_str') {
                $p = trim((string)($rows['shinkoku_ratio15_pct'] ?? '0.00%'));
                if ($p === '') $p = '0.00%';
                $this->putJumin3($pdf, $font, $colX, $cols, $cM, $cP, $cT, $y, $rowH2, $p, $p, $p, $fs2, $pr2, $debug, $isBoldTotalRow ? 'B' : '', $rowStretch);
                continue;
            }
            if ($kind === 'calc11') {
                $tok = is_array($rows['tokurei'] ?? null) ? $rows['tokurei'] : [];
                $c11 = is_array($tok['calc11'] ?? null) ? $tok['calc11'] : ['muni'=>'0.00','pref'=>'0.00','total'=>'0.00'];
                $this->putJumin3(
                    $pdf, $font, $colX, $cols, $cM, $cP, $cT, $y, $rowH2,
                    $this->fmtYen2($c11['muni'] ?? '0.00'),
                    $this->fmtYen2($c11['pref'] ?? '0.00'),
                    $this->fmtYen2($c11['total'] ?? '0.00'),
                    $fs2, $pr2, $debug,
                    $isBoldTotalRow ? 'B' : ''
                     , $rowStretch
                );
                continue;
            }

            // triple
            $v = $this->getTripleByPath($rows, $path);
            $muniTxt  = $this->fmtYen((int)$v['muni']);
            $prefTxt  = $this->fmtYen((int)$v['pref']);
            $totalTxt = $this->fmtYen((int)$v['total']);
            // ★原稿PDF側に「－」が入っているため、ここは空欄にする（値も「－」も出さない）
            if (in_array($path, ['kihon.target','kihon.cap30','kihon.min','tokurei.target'], true)) {
                $totalTxt = '';
            }
            $this->putJumin3(
                $pdf, $font, $colX, $cols, $cM, $cP, $cT, $y, $rowH2,
                $muniTxt,
                $prefTxt,
                $totalTxt,
                $rowFont, $pr2, $debug,
                $isBoldTotalRow ? 'B' : ''
            );
        }

        // ------------------------------------------------------------
        // ▼ 下段まとめ（4行）：jumin_summary があれば印字
        //   - other / furusato_only / unable / final
        //   - 合計列は「furusato_only」「final」を太字（通常版と揃える）
        // ------------------------------------------------------------
        $sum = is_array($vars['jumin_summary'] ?? null) ? $vars['jumin_summary'] : [];
        if ($sum !== [] && isset($L['summary'])) {
            $s = $L['summary'];
            $sx = (float)$s['x'];
            $sy = (float)$s['y'];
            $scols = $s['cols'];
            $scolX = $this->colLefts($sx, $scols);
            $sRowH = (float)$s['row_h'];
            $sFs = (float)$s['font'];
            $sFsSmall = max(1.0, $sFs - 0.5);
            $sPr = (float)$s['pad_r'];
            $sStretch = array_key_exists('stretch', $s) ? (float)$s['stretch'] : null;

            // 数値列 index: muni=1, pref=2, total=3
            $sM = 1; $sP = 2; $sT = 3;
            $sumSeq = ['other','furusato_only','unable','final'];
            foreach ($sumSeq as $i => $k) {
                $y = $sy + ($sRowH * (float)$i);
                $row = is_array($sum[$k] ?? null) ? $sum[$k] : ['muni'=>0,'pref'=>0,'total'=>0];
                $this->textBox($pdf, $font, (float)$scolX[$sM], $y, (float)$scols[$sM], $sRowH, $this->fmtYen($this->n($row['muni'] ?? 0)), 'R', $sFsSmall, $sPr, 0.0, '', $sStretch);
                $this->textBox($pdf, $font, (float)$scolX[$sP], $y, (float)$scols[$sP], $sRowH, $this->fmtYen($this->n($row['pref'] ?? 0)), 'R', $sFsSmall, $sPr, 0.0, '', $sStretch);
                // 太字：furusato_only と final の合計列
                $style = (((int)$i === 1) || ((int)$i === 3)) ? 'B' : '';
                $this->textBox($pdf, $font, (float)$scolX[$sT], $y, (float)$scols[$sT], $sRowH, $this->fmtYen($this->n($row['total'] ?? 0)), 'R', $sFsSmall, $sPr, 0.0, $style, $sStretch);
                if ($debug) {
                    $this->debugRect($pdf, (float)$scolX[$sM], $y, (float)$scols[$sM], $sRowH);
                    $this->debugRect($pdf, (float)$scolX[$sP], $y, (float)$scols[$sP], $sRowH);
                    $this->debugRect($pdf, (float)$scolX[$sT], $y, (float)$scols[$sT], $sRowH);
                }
            }
        }
    }

    // ============================================================
    // Generic helpers
    // ============================================================
    private function fmtYen(int $v): string
    {
        return number_format($v);
    }

    /**
     * 金額（小数2位）を 3桁カンマ付きで表示する（例：12,345.67）
     * - ⑪（⑨×⑩）など、少数がある金額欄用
     */
    private function fmtYen2(mixed $v): string
    {
        if ($v === null || $v === '') return '0.00';
        if (is_string($v)) {
            $v = str_replace([',', ' '], '', $v);
        }
        if (!is_numeric($v)) return '0.00';
        return number_format((float)$v, 2, '.', ',');
    }

    private function fmtPctInt(int $pct): string
    {
        return (string)$pct . '%';
    }

    private function fmtPct2(float $pct): string
    {
        return number_format($pct, 2, '.', '') . '%';
    }

    private function fmtPct3(float $pct): string
    {
        return number_format($pct, 3, '.', '') . '%';
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

    private function putJumin3(
        TcpdfFpdi $pdf,
        string $font,
        array $colX,
        array $cols,
        int $cM,
        int $cP,
        int $cT,
        float $y,
        float $h,
        string $m,
        string $p,
        string $t,
        float $fontSize,
        float $padR,
        bool $debug,
        string $style = '',        // '' or 'B'
        ?float $stretchPct = null  // ★追加: 例 90.0（%）
     ): void {
        $this->textBox($pdf, $font, (float)$colX[$cM], $y, (float)$cols[$cM], $h, $m, 'R', $fontSize, $padR, 0.0, $style, $stretchPct);
        $this->textBox($pdf, $font, (float)$colX[$cP], $y, (float)$cols[$cP], $h, $p, 'R', $fontSize, $padR, 0.0, $style, $stretchPct);
        $this->textBox($pdf, $font, (float)$colX[$cT], $y, (float)$cols[$cT], $h, $t, 'R', $fontSize, $padR, 0.0, $style, $stretchPct);
 
 
        if ($debug) {
            $this->debugRect($pdf, (float)$colX[$cM], $y, (float)$cols[$cM], $h);
            $this->debugRect($pdf, (float)$colX[$cP], $y, (float)$cols[$cP], $h);
            $this->debugRect($pdf, (float)$colX[$cT], $y, (float)$cols[$cT], $h);
        }
    }

    /** dot-path の存在確認（配列のみ） */
    private function hasPath(array $root, string $path): bool
    {
        if ($path === '') return false;
        $cur = $root;
        foreach (explode('.', $path) as $seg) {
            if ($seg === '') continue;
            if (!is_array($cur) || !array_key_exists($seg, $cur)) return false;
            $cur = $cur[$seg];
        }
        return true;
    }

    /** @return array{muni:int,pref:int,total:int} */
    private function getTripleByPath(array $root, string $path): array
    {
        $cur = $root;
        foreach (explode('.', $path) as $seg) {
            if ($seg === '') continue;
            if (!is_array($cur) || !array_key_exists($seg, $cur)) {
                return ['muni'=>0,'pref'=>0,'total'=>0];
            }
            $cur = $cur[$seg];
        }
        if (!is_array($cur)) return ['muni'=>0,'pref'=>0,'total'=>0];
        return [
            'muni'  => $this->n($cur['muni']  ?? 0),
            'pref'  => $this->n($cur['pref']  ?? 0),
            'total' => $this->n($cur['total'] ?? 0),
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
        // NOTE: IPAex等は Bold 面が無く 'B' が効かない事があるため、'B' は疑似太字（2回描画）で対応
        $isBold = ($fontStyle === 'B');
        // ★ストレッチ（指定がある時だけ効かせ、最後に必ず戻す）
        $stretchInt = null;
        if ($stretchPct !== null) {
            $stretchInt = (int)max(50, min(150, round($stretchPct))); // 安全域: 50%〜150%
            $pdf->SetFontStretching($stretchInt);
        }

        $pdf->SetFont($font, '', $fontSize);
        $pdf->SetTextColor(0, 0, 0);

        $innerX = $x + max(0.0, $padLeft);
        $innerW = $w - max(0.0, $padLeft) - max(0.0, $padRight);
        if ($innerW < 0.1) $innerW = 0.1;

        $pdf->SetXY($innerX, $y);
        $pdf->MultiCell($innerW, $h, $text, 0, $align, false, 0, '', '', true, 0, false, true, $h, 'M', false);
   
        // ★疑似太字：ごく小さいオフセットで同じ文字をもう一度描く
        if ($isBold) {
            $pdf->SetXY($innerX + 0.2, $y);
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
