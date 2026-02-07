<?php

namespace App\Services\Pdf;

use App\Models\Data;
use App\Reports\Contracts\BundleReportInterface;
use App\Services\Pdf\Templates\FurusatoHyoshiTemplateWriter;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * ふるさと帳票一括：ハイブリッド生成
 * - 0表紙：背景テンプレPDF＋座標印字
 * - それ以外：既存の FastBundlePdfBuilder（HTML結合→dompdf 1回）
 * - 最後にFPDIで1本に結合
 */
final class FurusatoBundleHybridPdfBuilder
{
    public function __construct(
        private readonly ReportRegistry $reports,
        private readonly FastBundlePdfBuilder $fastBuilder,
        private readonly PdfPageLabelStamper $pageStamper,
    ) {}

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $options
     */
    public function build(Data $data, BundleReportInterface $bundle, array $context, array $options): string
    {
        $keys = $bundle->bundleKeys($data, $context);
        $keys = array_values(array_filter(array_map('strval', $keys), fn($k) => $k !== ''));
        $keys = array_values(array_unique($keys));

        // 0表紙キー（現行bundleKeysでは 'hyoshi'）
        $coverKey = 'hyoshi';

        // --- 0表紙（テンプレ＋座標） ---
        $coverPdf = $this->buildCoverPdf($data);

        // --- 残り（表紙を除外してfast bundle 1回） ---
        $restKeys = array_values(array_filter($keys, fn($k) => strtolower($k) !== $coverKey));
        $restPdf = '';
        if ($restKeys !== []) {
            $restPdf = $this->fastBuilder->buildBundlePdfForKeys($data, $restKeys, $context, $options);
        }

        // --- ページ番号スタンプ（表紙は除外） ---
        if ($restPdf !== '') {
            $labels = $this->buildPageLabelsForKeys($restKeys, $context);
            $fontPath = public_path('fonts/ipaexg.ttf');
            $restPdf = $this->pageStamper->stampPerPage($restPdf, $labels, $fontPath);
        }

        // --- 結合 ---
        $fpdi = new Fpdi();
        $fpdi->SetAutoPageBreak(false);
        $fpdi->SetMargins(0, 0, 0);

        // cover
        $this->appendPdfString($fpdi, $coverPdf);
        // rest
        if ($restPdf !== '') {
            $this->appendPdfString($fpdi, $restPdf);
        }

        return $fpdi->Output('S');
    }

    /**
     * @param array<int,string> $keys (表紙除外後)
     * @return array<int,string> 1-indexed labels
     */
    private function buildPageLabelsForKeys(array $keys, array $context): array
    {
        $variant = strtolower((string)($context['pdf_variant'] ?? 'max')); // max|current|both
        if (!in_array($variant, ['max','current','both'], true)) {
            $variant = 'max';
        }

        $seen = [];
        $labels = [];
        $i = 1;
        foreach ($keys as $key) {
            $k = strtolower((string)$key);
            $base = $this->basePageNoForKey($k);
            if ($base === null) {
                $i++;
                continue;
            }
            // both のときだけ 2〜4 は枝番
            if ($variant === 'both' && in_array($base, [2,3,4], true)) {
                $seen[$base] = ($seen[$base] ?? 0) + 1;
                if ($seen[$base] === 1) {
                    $labels[$i] = "{$base}ページ";
                } else {
                    $suffix = $seen[$base] - 1; // 2回目→1
                    $labels[$i] = "{$base}-{$suffix}ページ";
                }
            } else {
                $labels[$i] = "{$base}ページ";
            }
            $i++;
        }
        return $labels;
    }

    private function basePageNoForKey(string $k): ?int
    {
        if ($k === 'kifukingendogaku') return 1;
        if ($k === 'syotokukinkojyosoku' || $k === 'syotokukinkojyosoku_curr') return 2;
        if ($k === 'kazeigakuzeigakuyosoku' || $k === 'kazeigakuzeigakuyosoku_curr') return 3;
        if (str_starts_with($k, 'juminkeigengaku')) return 4; // onestop含む
        if ($k === 'sonntokusimulation') return 5;
        if ($k === 'jintekikojosatyosei') return 6;
        if ($k === 'tokureikojowariai') return 7;
        return null;
    }

    private function buildCoverPdf(Data $data): string
    {
        // 背景テンプレ
        $tpl = resource_path('pdf_templates/furusato/0_hyoshi_bg.pdf');
        // フォント（TTF）
        $font = public_path('fonts/ipaexg.ttf');

        $writer = new FurusatoHyoshiTemplateWriter($tpl, $font);

        // 表紙に載せたい値（暫定）
        $guestName = (string)($data->guest?->name ?? '');
        $dateIso = (string)($data->proposal_date ?? $data->data_created_on ?? now()->toDateString());
        $dateWareki = \App\Support\WarekiDate::format($dateIso);
        $date = $dateWareki !== '' ? $dateWareki : $dateIso;
        $org  = ''; // 必要なら会社名/事務所名を入れる

        return $writer->render([
            'guest_name' => $guestName !== '' ? ($guestName . '様') : '',
            'date'       => $date,
            'org'        => $org,
        ]);
    }

    private function appendPdfString(Fpdi $fpdi, string $pdfStr): void
    {
        $pageCount = $fpdi->setSourceFile(StreamReader::createByString($pdfStr));
        for ($i = 1; $i <= $pageCount; $i++) {
            $tpl  = $fpdi->importPage($i);
            $size = $fpdi->getTemplateSize($tpl);
            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $fpdi->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);
        }
    }
}
