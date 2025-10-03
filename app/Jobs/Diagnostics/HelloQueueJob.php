<?php
namespace App\Jobs\Diagnostics;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HelloQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 診断用パラメータ */
    public function __construct(
        public ?int $sleepMs = null,
        public ?bool $shouldFail = false,
    ) {}

    public function handle(): void
    {
        if ($this->sleepMs && $this->sleepMs > 0) {
            usleep($this->sleepMs * 1000);
        }
        if ($this->shouldFail) {
            throw new \RuntimeException('HelloQueueJob: intentional failure');
        }
        Log::info('HelloQueueJob handled', [
            'ts'      => now()->toIso8601String(),
            'sleepMs' => $this->sleepMs,
        ]);
    }
}
