<?php

namespace App\Services\Pdf\Templates;

use setasign\Fpdi\Tcpdf\Fpdi as TcpdfFpdi;

/**
 * 6_jintekikojosatyosei（人的控除差調整額）を
 * 「背景テンプレPDF + mm座標印字」で生成する。
 */
final class FurusatoJintekiKojoSaTyoseiTemplateWriter
{
    /**
      * ★座標は mm（仮）
      * - テンプレに合わせて調整してください（デバッグ印字は一切しない）
     */
    private const POS = [
         // ▼ 左側：①「200万円以下」ブロック内（a/b/c）
         'case1_a' => ['x' => 100.0, 'y' => 126.0, 'w' => 36.0, 'h' => 6.5, 'size' => 12.0, 'rgb' => [0, 0, 0], 'align' => 'R', 'pad_r' => 1.6],
         'case1_b' => ['x' => 100.0, 'y' => 133.0, 'w' => 36.0, 'h' => 6.5, 'size' => 12.0, 'rgb' => [0, 0, 0], 'align' => 'R', 'pad_r' => 1.6],
         'case1_c' => ['x' => 100.0, 'y' => 140.0, 'w' => 36.0, 'h' => 6.5, 'size' => 12.0, 'rgb' => [0, 0, 0], 'align' => 'R', 'pad_r' => 1.6],
 
         // ▼ 左側：②「200万円超」ブロック内（a/b/c）
         // ★下段3行は少し下げて揃える（+1.0mm）。必要なら微調整してください。
         'case2_a' => ['x' => 100.0, 'y' => 162.0, 'w' => 36.0, 'h' => 6.5, 'size' => 12.0, 'rgb' => [0, 0, 0], 'align' => 'R', 'pad_r' => 1.6],
         'case2_b' => ['x' => 100.0, 'y' => 169.0, 'w' => 36.0, 'h' => 15.0, 'size' => 12.0, 'rgb' => [0, 0, 0], 'align' => 'R', 'pad_r' => 1.6],
         'case2_c' => ['x' => 100.0, 'y' => 176.0, 'w' => 36.0, 'h' => 6.5, 'size' => 12.0, 'rgb' => [0, 0, 0], 'align' => 'R', 'pad_r' => 1.6],
         // ▼ 最終：調整控除額（合計）※置き場所は後で調整
         'final_total' => ['x' => 165.0, 'y' => 210.0, 'size' => 12.0, 'rgb' => [0, 0, 0]],

         // ==========================================================
         // ▼ 右側：控除の種類 表（最右列：〇 / n人）※座標は仮
         // ==========================================================
         // 障害者控除
         'mk_shogaisha_ippan'   => ['x' => 270.0, 'y' => 29.2,  'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_shogaisha_tokubetsu'=>['x' => 270.0, 'y' => 35.8,  'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_shogaisha_doukyo'  => ['x' => 270.0, 'y' => 42.4,  'size' => 12.0, 'rgb' => [0, 0, 0]],
         // 寡婦/ひとり親/勤労学生
         'mk_kafu'              => ['x' => 270.0, 'y' => 48.9, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_hitorioya_father'  => ['x' => 270.0, 'y' => 55.5, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_hitorioya_mother'  => ['x' => 270.0, 'y' => 62.1, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_kinro'             => ['x' => 270.0, 'y' => 68.7, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         // 配偶者控除（一般：3行、老人：3行）
         'mk_haigusha_ippan_900'   => ['x' => 270.0, 'y' => 81.9, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_haigusha_ippan_950'   => ['x' => 270.0, 'y' => 88.5, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_haigusha_ippan_1000'  => ['x' => 270.0, 'y' => 95.1, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_haigusha_roujin_900'  => ['x' => 270.0, 'y' => 101.7, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_haigusha_roujin_950'  => ['x' => 270.0, 'y' => 108.3, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_haigusha_roujin_1000' => ['x' => 270.0, 'y' => 114.9, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         // 扶養控除（人数）
         'mk_fuyo_ippan'         => ['x' => 270.0, 'y' => 123.0, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_fuyo_tokutei'       => ['x' => 270.0, 'y' => 129.6, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_fuyo_roujin_sonota' => ['x' => 270.0, 'y' => 136.1, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_fuyo_roujin_doukyo' => ['x' => 270.0, 'y' => 142.2, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         // 基礎控除（〇）
         'mk_kiso_under'         => ['x' => 270.0, 'y' => 155.7, 'size' => 12.0, 'rgb' => [0, 0, 0]],
         'mk_kiso_over'          => ['x' => 270.0, 'y' => 162.2, 'size' => 12.0, 'rgb' => [0, 0, 0]],
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

         // ---- 帳票に出す値（curr：JuminTaxCalculator の中間SoT）----
         $case  = $this->n($vars['jumin_chosei_case_curr'] ?? 0); // 0/1/2
         $a     = $this->n($vars['jumin_chosei_a_curr'] ?? 0);
         $b     = $this->n($vars['jumin_chosei_b_curr'] ?? 0);
         $cAmt  = $this->n($vars['jumin_chosei_c_amount_curr'] ?? 0);
         $final = $this->n($vars['jumin_choseikojo_total_curr'] ?? 0);
 
         // 表示形式：テンプレ側に合わせて「数値のみ」（カンマあり）
         // - case が立っているブロックは 0 も「0」として出す（要件）
         $disp = static fn(int $v): string => number_format($v);

         // ケースに応じて該当ブロックのみ埋める（非該当側は空欄）
         if ($case === 1) {
             // 200万円以下：a=human_diff_sum（またはその採用値）、b=合計課税所得金額、c=調整控除額（算出）
             $this->textBox($pdf, $font, self::POS['case1_a'], $disp($a));
             $this->textBox($pdf, $font, self::POS['case1_b'], $disp($b));
             $this->textBox($pdf, $font, self::POS['case1_c'], $disp($cAmt));
          } elseif ($case === 2) {
             // 200万円超：a=rawBase、b=50,000（最低母数）、c=調整控除額（算出）
             $this->textBox($pdf, $font, self::POS['case2_a'], $disp($a));
             $this->textBox($pdf, $font, self::POS['case2_b'], $disp(50_000));
             $this->textBox($pdf, $font, self::POS['case2_c'], $disp($cAmt));
        }
 
         // 最終（上限適用後）
         // - 対象外(case=0)は何も出さない
         // - 対象内(case=1/2)は 0 でも表示する
         if ($case === 1 || $case === 2) {
             $this->text($pdf, $font, self::POS['final_total'], $disp($final));
         }

         // ==========================================================
         // 右側：〇 / n人（curr）
         // ==========================================================
         $this->putIfNotEmpty($pdf, $font, 'mk_shogaisha_ippan',    (string)($vars['jinteki_mark_shogaisha_cnt_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_shogaisha_tokubetsu',(string)($vars['jinteki_mark_tokubetsu_shogaisha_cnt_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_shogaisha_doukyo',   (string)($vars['jinteki_mark_doukyo_tokubetsu_cnt_curr'] ?? ''));

         $this->putIfNotEmpty($pdf, $font, 'mk_kafu',             (string)($vars['jinteki_mark_kafu_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_hitorioya_father', (string)($vars['jinteki_mark_hitorioya_father_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_hitorioya_mother', (string)($vars['jinteki_mark_hitorioya_mother_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_kinro',            (string)($vars['jinteki_mark_kinro_curr'] ?? ''));

         $this->putIfNotEmpty($pdf, $font, 'mk_haigusha_ippan_900',  (string)($vars['jinteki_mark_haigusha_ippan_900_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_haigusha_ippan_950',  (string)($vars['jinteki_mark_haigusha_ippan_950_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_haigusha_ippan_1000', (string)($vars['jinteki_mark_haigusha_ippan_1000_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_haigusha_roujin_900',  (string)($vars['jinteki_mark_haigusha_roujin_900_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_haigusha_roujin_950',  (string)($vars['jinteki_mark_haigusha_roujin_950_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_haigusha_roujin_1000', (string)($vars['jinteki_mark_haigusha_roujin_1000_curr'] ?? ''));

         $this->putIfNotEmpty($pdf, $font, 'mk_fuyo_ippan',         (string)($vars['jinteki_mark_fuyo_ippan_cnt_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_fuyo_tokutei',       (string)($vars['jinteki_mark_fuyo_tokutei_cnt_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_fuyo_roujin_sonota', (string)($vars['jinteki_mark_fuyo_roujin_sonota_cnt_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_fuyo_roujin_doukyo', (string)($vars['jinteki_mark_fuyo_roujin_doukyo_cnt_curr'] ?? ''));

         $this->putIfNotEmpty($pdf, $font, 'mk_kiso_under', (string)($vars['jinteki_mark_kiso_under_curr'] ?? ''));
         $this->putIfNotEmpty($pdf, $font, 'mk_kiso_over',  (string)($vars['jinteki_mark_kiso_over_curr'] ?? ''));

        return $pdf->Output('', 'S');
    }

    private function yen(mixed $v): string
    {
        return number_format($this->n($v));
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


    /**
     * ★右寄せボックス版（枠に重ならない・マイナスでも右揃え維持）
     * @param array{x:float,y:float,size:float,rgb:array{0:int,1:int,2:int},w:float,h:float,align?:string,pad_r?:float} $pos
     */
    private function textBox(TcpdfFpdi $pdf, string $font, array $pos, string $text): void
    {
        $pdf->SetFont($font, '', (float)($pos['size'] ?? 12.0));
        $rgb = $pos['rgb'] ?? [0, 0, 0];
        $pdf->SetTextColor((int)$rgb[0], (int)$rgb[1], (int)$rgb[2]);

        $x = (float)$pos['x'];
        $y = (float)$pos['y'];
        $w = (float)($pos['w'] ?? 0.0);
        $h = (float)($pos['h'] ?? 6.0);
        $align = (string)($pos['align'] ?? 'R');
        $padR  = (float)($pos['pad_r'] ?? 0.0);

        // 右寄せ内側余白
        $innerX = $x;
        $innerW = max(0.1, $w - $padR);

        $pdf->SetXY($innerX, $y);
        // ★autopadding/maxh の制約で文字が消えるのを防ぐ（前回と同じ方針：A）
        $pdf->MultiCell($innerW, $h, $text, 0, $align, false, 0, '', '', true, 0, false, false, 0, 'M', false);
    }

    private function putIfNotEmpty(TcpdfFpdi $pdf, string $font, string $posKey, string $text): void
    {
        $t = trim($text);
        if ($t === '') return;
        $pos = self::POS[$posKey] ?? null;
        if (!is_array($pos)) return;
        $this->text($pdf, $font, $pos, $t);
    }
}
