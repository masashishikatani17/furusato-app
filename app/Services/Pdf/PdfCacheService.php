<?php

namespace App\Services\Pdf;

use App\Models\Data;
use App\Models\FurusatoResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PdfCacheService
{
    public const STATUS_TTL_SECONDS = 3600; // 1h

    public function cacheKey(string $report, Data $data, array $context): string
    {
        $dataId  = (int) $data->id;
        $oneStop = (string)($context['one_stop_flag_curr'] ?? '1');
        $mode    = (string)($context['mode'] ?? 'fast');
        $engine  = (string)($context['engine'] ?? 'dompdf');
        // PDF出力条件（max|current|both）
        $variant = strtolower((string)($context['pdf_variant'] ?? 'max'));
        if (!in_array($variant, ['max','current','both'], true)) {
            $variant = 'max';
        }

        $t = FurusatoResult::query()->where('data_id', $dataId)->value('updated_at');
        $updated = $t ? (string)$t : (string)($data->updated_at ?? '');

        return sha1(json_encode([
            'report'  => strtolower($report),
            'data_id' => $dataId,
            'company' => (int)($data->company_id ?? 0),
            'group'   => (int)($data->group_id ?? 0),
            'oneStop' => $oneStop,
            'mode'    => $mode,
            'engine'  => $engine,
            'variant' => $variant,
            'updated' => $updated,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function storagePath(string $cacheKey): string
    {
        return "pdf_cache/{$cacheKey}.pdf";
    }

    public function exists(string $cacheKey): bool
    {
        return Storage::disk('local')->exists($this->storagePath($cacheKey));
    }

    public function absolutePath(string $cacheKey): string
    {
        return Storage::disk('local')->path($this->storagePath($cacheKey));
    }

    public function put(string $cacheKey, string $pdfBinary): void
    {
        Storage::disk('local')->put($this->storagePath($cacheKey), $pdfBinary);
        $this->setStatus($cacheKey, ['status' => 'ready']);
    }

    public function setStatus(string $cacheKey, array $data): void
    {
        Cache::put($this->statusCacheKey($cacheKey), $data, self::STATUS_TTL_SECONDS);
    }

    public function getStatus(string $cacheKey): array
    {
        $v = Cache::get($this->statusCacheKey($cacheKey));
        return is_array($v) ? $v : ['status' => 'none'];
    }

    public function statusCacheKey(string $cacheKey): string
    {
        return "pdf_cache_status:{$cacheKey}";
    }
}