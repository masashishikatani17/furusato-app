<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Commands\DispatchQueueCheckJobsCommand;
use App\Console\Commands\Billing\SyncOutstandingInvoicesCommand;
use App\Console\Commands\Billing\IssueRenewalInvoicesCommand;
use App\Console\Commands\Billing\NotifyBankTransferOverdueCommand;
use App\Console\Commands\Billing\RescueLegacySubscriptionsCommand;

// 請求管理ロボ：未入金（pending/issued/failed）請求書の同期ジョブを dispatch
// ※ オプション(--limit)を保持するため、signature もここで定義する
Artisan::command('billing:sync-outstanding-invoices {--limit=200}', function () {
    $limit = (int) $this->option('limit');
    $this->call(SyncOutstandingInvoicesCommand::class, [
        '--limit' => $limit,
    ]);
})->purpose('Dispatch sync jobs for outstanding subscription invoices');

Artisan::command('billing:issue-renewal-invoices {--date=} {--limit=500}', function () {
    $this->call(IssueRenewalInvoicesCommand::class, [
        '--date' => $this->option('date'),
        '--limit' => (int)$this->option('limit'),
    ]);
})->purpose('Issue renewal invoices by payment method timing rules.');

Artisan::command('billing:notify-bank-transfer-overdue {--date=} {--limit=500}', function () {
    $this->call(NotifyBankTransferOverdueCommand::class, [
        '--date' => $this->option('date'),
        '--limit' => (int)$this->option('limit'),
    ]);
})->purpose('Notify admin when bank-transfer renewal invoices remain unpaid at term_end-2 days.');

Artisan::command('billing:rescue-legacy-subscriptions
    {--dry-run}
    {--force}
    {--company_id=*}
    {--term-start=2026-03-01}
    {--term-end=2027-02-28}
    {--payment-method=credit}
    {--limit=500}', function () {
    $this->call(RescueLegacySubscriptionsCommand::class, [
        '--dry-run' => (bool) $this->option('dry-run'),
        '--force' => (bool) $this->option('force'),
        '--company_id' => (array) $this->option('company_id'),
        '--term-start' => (string) $this->option('term-start'),
        '--term-end' => (string) $this->option('term-end'),
        '--payment-method' => (string) $this->option('payment-method'),
        '--limit' => (int) $this->option('limit'),
    ]);
})->purpose('Backfill legacy subscriptions for robo billing rollout.');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// === Health: Queue チェック用のテストジョブを毎分ディスパッチ ===
Schedule::command(DispatchQueueCheckJobsCommand::class)->everyMinute();
// 監査ログは1年保持（毎日深夜に削除）
Schedule::command('audit:prune --days=365')->dailyAt('03:10');