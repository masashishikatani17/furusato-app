<?php

namespace App\Services\Pdf;

use InvalidArgumentException;
use App\Reports\Contracts\ReportInterface;

class ReportRegistry
{
    /** @var array<string,class-string<ReportInterface>> */
    private array $map;

    public function __construct()
    {
        /** @var array<string,class-string<ReportInterface>> $cfg */
        $cfg = (array) config('pdf_reports', []);
        $this->map = $cfg;
    }

    public function resolve(string $key): ReportInterface
    {
        $key = strtolower($key);
        if (!isset($this->map[$key])) {
            throw new InvalidArgumentException("Unknown report: {$key}");
        }
        /** @var ReportInterface $obj */
        $obj = app($this->map[$key]);
        return $obj;
    }
}