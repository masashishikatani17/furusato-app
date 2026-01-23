<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Commands\DispatchQueueCheckJobsCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// === Health: Queue チェック用のテストジョブを毎分ディスパッチ ===
Schedule::command(DispatchQueueCheckJobsCommand::class)->everyMinute();
// 監査ログは1年保持（毎日深夜に削除）
Schedule::command('audit:prune --days=365')->dailyAt('03:10');