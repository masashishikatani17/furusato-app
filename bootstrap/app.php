<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
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

        // 請求管理ロボ：入金同期（10分ごと）
        // - pending/issued/failed の invoice を対象に同期ジョブを dispatch
        $schedule->command('billing:sync-outstanding-invoices')
            ->everyTenMinutes()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

