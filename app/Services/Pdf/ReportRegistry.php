<?php

namespace App\Services\Pdf;

use InvalidArgumentException;
use App\Reports\Contracts\ReportInterface;
use App\Reports\Pdf\UnifiedReport;

class ReportRegistry
{
    /**
     * @var array<string, class-string<ReportInterface>|array<string,mixed>>
     */
    private array $map;

    public function __construct()
    {
        /** @var array<string, class-string<ReportInterface>|array<string,mixed>> $cfg */
        $cfg = (array) config('pdf_reports', []);
        $this->map = $cfg;
    }

    public function resolve(string $key): ReportInterface
    {
        $key = strtolower($key);
        if (!isset($this->map[$key])) {
            throw new InvalidArgumentException("Unknown report: {$key}");
        }

        $entry = $this->map[$key];

        // 1) 従来互換：class-string
        if (is_string($entry)) {
            /** @var ReportInterface $obj */
            $obj = app($entry);
            return $obj;
        }

        // 2) 新方式：配列定義 → 統合Reportを生成
        if (is_array($entry)) {
            return new UnifiedReport($key, $entry);
        }

        throw new InvalidArgumentException("Invalid pdf_reports entry for: {$key}");
    }
}