<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('webhooks')
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Spatie Permission middleware aliases（Laravel 12 ではここに書く）
        $middleware->alias([
            'auth'        => \App\Http\Middleware\Authenticate::class,
            'role'        => \Spatie\Permission\Middlewares\RoleMiddleware::class,
            'roles'       => \Spatie\Permission\Middlewares\RoleMiddleware::class, // 互換
            'permission'  => \Spatie\Permission\Middlewares\PermissionMiddleware::class,
            'permissions' => \Spatie\Permission\Middlewares\PermissionMiddleware::class,
            'reject.client' => \App\Http\Middleware\RejectClientForAdmin::class,
            'subscription.active' => \App\Http\Middleware\EnsureSubscriptionActive::class,
        ]);
        // Cloud9/ALB の X-Forwarded-* を信頼（https 判定に使う）
        // すべてのプロキシを信頼し、AWS ELB ヘッダを採用
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_AWS_ELB
        );
        // 有効時かつパッケージ存在時のみ CSP ヘッダを付与（安全）
        $middleware->web(append: [
            \App\Http\Middleware\StoreIntendedOnUnauthenticated::class,
            \App\Http\Middleware\AddCspIfEnabled::class,
            // artisan serve / Cloud9 vfs 経由でも転送量を減らす（HTML/JSON等をgzip）
            \App\Http\Middleware\GzipResponse::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // 期限切れ招待の確定（最大遅延を抑えるなら everyMinute）
        $schedule->command('invitations:expire-scan')->everyMinute();

        // 請求管理ロボ：初回クレカ決済成功の差分同期（1分ごと）
        // - bill/search の update_date 差分で、初回クレカ請求の状態変化だけを拾う
        // - 決済成功時は subscription を active にし、翌年度 recurring master を作成する
        $schedule->command('billing:sync-initial-credit-settlements')
            ->everyMinute()
            ->withoutOverlapping();

        // 請求管理ロボ：入金同期（10分ごと）
        // - 上記差分同期で取りこぼした invoice を救済する
        // - 銀行振込や初回クレカ以外も含めて invoice 単位で同期ジョブを投げる
        $schedule->command('billing:sync-outstanding-invoices')
            ->everyTenMinutes()
            ->withoutOverlapping();

        // 請求管理ロボ：更新請求作成
        $schedule->command('billing:issue-renewal-invoices')
            ->dailyAt('01:00')
            ->withoutOverlapping();

        // 銀行振込：満了2日前時点の未入金通知
        $schedule->command('billing:notify-bank-transfer-overdue')
            ->dailyAt('09:00')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
