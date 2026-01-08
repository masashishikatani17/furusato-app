<?php

namespace App\Reports\Contracts;

use App\Models\Data;

interface BundleReportInterface
{
    /**
     * @return array<int,string> report keys (pdf_reports.php のキー)
     */
    public function bundleKeys(Data $data, array $context = []): array;

    public function bundleFileName(Data $data, array $context = []): string;
}
