<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Pdf\SamplePdfController;
use App\Http\Controllers\Pdf\PdfOutputController;
use App\Http\Controllers\UploadController;
use Illuminate\Http\Request;
use Laravel\Horizon\Horizon;
use Illuminate\Support\Facades\Response;
use App\Jobs\Diagnostics\HelloQueueJob;
use App\Http\Controllers\DataController;
use App\Http\Controllers\Tax\FurusatoController;

Route::get('/', function () {
    return view('welcome');
});
// 開発用：Horizonダッシュボード
if (app()->environment('local')) {
    Route::get('/horizon', fn() => redirect('/horizon/dashboard'));
}
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn() => 'dashboard')->name('dashboard');
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin', fn() => 'admin area');
    });
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

// ========== PDF サンプル ==========
Route::get('/pdf/sample', [SamplePdfController::class, 'stream'])
    ->name('pdf.sample');
Route::get('/pdf/sample/download', [SamplePdfController::class, 'download'])
    ->name('pdf.sample.download');
    
// ========== 画像アップロード ==========
Route::middleware('auth')->group(function () {
    // ======== data_master 画面（/data） ========
    Route::get('/data', [DataController::class, 'index'])->name('data.index');
    // 作成フロー
    Route::get('/data/create', [DataController::class, 'create'])->name('data.create');
    Route::post('/data', [DataController::class, 'store'])->name('data.store');
    // コピーフロー
    Route::get('/data/copyForm', [DataController::class, 'copyForm'])->name('data.copyForm');
    Route::post('/data/copy', [DataController::class, 'copy'])->name('data.copy');
    // 編集（年度の変更に読み替え）
    Route::get('/data/editForm', [DataController::class, 'editForm'])->name('data.editForm');
    Route::post('/data/{id}/edit', [DataController::class, 'edit'])->name('data.edit');
    Route::get('/upload', [UploadController::class, 'index'])->name('upload.index');
    Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');
    Route::delete('/upload', [UploadController::class, 'destroy'])->name('upload.destroy');

    // ======== Queue Diagnostics（認証版：ブラウザで利用） ========
    Route::get('/diag/queue/dispatch', function (Request $r) {
        $sleepMs = (int) $r->query('sleepMs', 0);
        $fail    = (bool) $r->boolean('fail', false);
        HelloQueueJob::dispatch($sleepMs ?: null, $fail)->onQueue('default');
        return response()->json([
            'queued'  => true,
            'queue'   => 'default',
            'sleepMs' => $sleepMs ?: null,
            'fail'    => $fail,
            'at'      => now()->toIso8601String(),
        ]);
    })->name('diag.queue.dispatch');

    // 連続でN件ディスパッチ（?n=50&sleepMs=10）
    Route::get('/diag/queue/burst', function (Request $r) {
        $n = max(1, min(500, (int) $r->query('n', 25)));
        $sleepMs = (int) $r->query('sleepMs', 0);
        for ($i=0; $i<$n; $i++) {
            HelloQueueJob::dispatch($sleepMs ?: null, false)->onQueue('default');
        }
        return response()->json([
            'queued'  => $n,
            'queue'   => 'default',
            'sleepMs' => $sleepMs ?: null,
            'at'      => now()->toIso8601String(),
        ]);
    })->name('diag.queue.burst');

    // Horizon ダッシュボードへショートカット
    Route::get('/diag/queue/horizon', fn() => redirect('/horizon'))->name('diag.queue.horizon');    

    // ======== data_master API（認証下に集約） ========
    // 右ペイン用：id/kihu_year だけ返却
    Route::get('/api/guest/{guest}/datas', [DataController::class, 'datasJson'])
        ->name('api.guest.datas');
    // 部署内ユーザー一覧（担当者プルダウン）
    Route::get('/api/group/{group}/users', [DataController::class, 'groupUsersJson'])
        ->name('api.group.users');
    // （任意）担当者基準でゲスト絞り込み
    Route::get('/api/guests', [DataController::class, 'guestsJson'])
        ->name('api.guests');
    // 年度変更モーダルからの複製API
    Route::post('/api/data/{data}/clone-year', [DataController::class, 'cloneWithYear'])
        ->name('api.data.clone_year');
    // お客様・データの削除API（モーダルから呼び出し）
    Route::delete('/api/guest/{guest}', [DataController::class, 'destroyGuest'])
    ->name('api.guest.destroy');
    Route::delete('/api/data/{data}', [DataController::class, 'destroyData'])
        ->name('api.data.destroy');

    // ======== PDF 出力（拡張可能なレポート基盤） ========
    // プレビュー（HTML）
    Route::get('/pdf/{report}/preview', [PdfOutputController::class, 'preview'])
        ->where('report', '[a-z0-9_\-]+')
        ->name('pdf.preview');
    // ダウンロード（PDF）
    Route::get('/pdf/{report}', [PdfOutputController::class, 'download'])
        ->where('report', '[a-z0-9_\-]+')
        ->name('pdf.download');    
});


if (config('feature.health')) {
    Route::get('/health', \Spatie\Health\Http\Controllers\HealthCheckResultsController::class)->name('health');
    Route::get('/health.json', \Spatie\Health\Http\Controllers\HealthCheckJsonResultsController::class);
}

// ======== CSP Diagnostics (Spatie非依存) ========
Route::get('/diag/csp/status', function () {
    return response()->json([
        'feature.csp'      => config('feature.csp'),
        'csp.enabled'      => config('csp.enabled'),
        'csp.report_only'  => config('csp.report_only'),
        'mode'             => 'builtin-middleware',
    ]);
});

Route::get('/diag/csp/ping', function () {
    // 単純なHTMLレスポンス（ここにCSPヘッダが付くかを確認）
    return Response::make('<!doctype html><title>CSP ping</title><h1>pong</h1>', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
});

Route::prefix('furusato')->group(function () {
    Route::get('/', [FurusatoController::class, 'index'])->name('furusato.index');
    Route::get('/input', [FurusatoController::class, 'index'])->name('furusato.input');
    Route::post('/save', [FurusatoController::class, 'save'])->name('furusato.save');
    Route::post('/calc', [FurusatoController::class, 'calc'])->name('furusato.calc');
    // 直打ちやリロードで GET /furusato/calc に来たら入力画面へ戻す
    Route::get('/calc', fn() => redirect()->route('furusato.index'));
});