<?php

namespace App\Reports\Pdf;

use App\Models\Data;
use App\Reports\Contracts\ReportInterface;

/**
 * 定義駆動の統合Report
 * - 帳票ごとの違い（view / title / filename / paper/orient 等）は config 側で管理する
 */
class UnifiedReport implements ReportInterface
{
    /** @param array<string,mixed> $def */
    public function __construct(
        private string $key,
        private array $def
    ) {}

    public function viewName(): string
    {
        $view = (string)($this->def['view'] ?? '');
        if ($view === '') {
            throw new \InvalidArgumentException("pdf_reports[{$this->key}].view is required");
        }
        return $view;
    }

    public function buildViewData(Data $data): array
    {
        $guestName = $data->guest?->name ?? '（名称未登録）';
        $year = (int)($data->kihu_year ?? now()->year);

        $base = [
            'report_key' => $this->key,
            'title'      => (string)($this->def['title'] ?? ''),
            'year'       => $year,
            'guest_name' => $guestName,
            'data_id'    => (int)$data->id,
        ];

        // 任意の追加変数（固定値）を足したい場合は config 側で vars を渡す
        $vars = $this->def['vars'] ?? [];
        if (is_array($vars)) {
            $base = array_merge($base, $vars);
        }
        return $base;
    }

    public function fileName(Data $data): string
    {
        $tpl = (string)($this->def['filename_template'] ?? '');
        if ($tpl === '') {
            // 既定フォールバック
            $tpl = "{title}_{year}_{guest}_data{data_id}.pdf";
        }

        $guest = $data->guest?->name ?? '名称未登録';
        $year  = (int)($data->kihu_year ?? now()->year);
        $title = (string)($this->def['title'] ?? 'PDF');
        $id    = (int)$data->id;

        $name = strtr($tpl, [
            '{title}'   => $title,
            '{year}'    => (string)$year,
            '{guest}'   => $guest,
            '{data_id}' => (string)$id,
        ]);

        return $this->sanitizeFilename($name);
    }

    /**
     * PdfOutputController が存在確認して PdfRenderer に渡す用（任意）
     * @return array{paper?:string, orient?:string}
     */
    public function pdfOptions(Data $data): array
    {
        $paper  = $this->def['paper']  ?? null;
        $orient = $this->def['orient'] ?? null;

        $opt = [];
        if (is_string($paper) && $paper !== '')  $opt['paper']  = $paper;
        if (is_string($orient) && $orient !== '') $opt['orient'] = $orient;
        return $opt;
    }

    private function sanitizeFilename(string $name): string
    {
        // Windows禁止文字 + 制御文字を除去
        $name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|\\x00-\\x1F]/u', '_', $name) ?? $name;
        $name = preg_replace('/\\s+/u', ' ', $name) ?? $name;
        $name = trim($name);
        if ($name === '') $name = 'report.pdf';
        // 拡張子が無ければ付与
        if (!str_ends_with(strtolower($name), '.pdf')) $name .= '.pdf';
        return $name;
    }
}
