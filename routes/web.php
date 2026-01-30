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
use App\Http\Controllers\DevTenantController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\Tax\FurusatoController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\GroupsController;
use App\Http\Controllers\Admin\BillingReceiptsController;
use App\Http\Controllers\Admin\OwnerTransferController;
use App\Http\Controllers\Admin\DataDownloadController;
use App\Http\Controllers\Billing\SetupController;
use App\Http\Controllers\Admin\AuditLogsController;
use App\Http\Controllers\Admin\InvitationsController;

Route::get('/', function () {
    return redirect()->route('login');
});
// 開発用：Horizonダッシュボード
if (app()->environment('local')) {
    Route::get('/horizon', fn() => redirect('/horizon/dashboard'));
}
if (app()->environment('local')) {
    Route::middleware(['auth'])->group(function () {
        Route::get('/dev/whoami', [DevTenantController::class, 'whoami'])->name('dev.whoami');
        Route::get('/dev/seats', [DevTenantController::class, 'seats'])->name('dev.seats');
    });
}
Route::middleware('auth')->group(function () {
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
        return redirect()->route('admin.settings');
    })->name('dashboard');
});

Route::middleware(['auth', 'reject.client'])->group(function () {
    Route::get('/admin/settings', [SettingsController::class, 'index'])->name('admin.settings');
    Route::prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UsersController::class, 'index'])->name('index');
        Route::get('/create', [UsersController::class, 'create'])->name('create');
        Route::post('/', [UsersController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UsersController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UsersController::class, 'update'])->name('update');
        Route::patch('/{user}/deactivate', [UsersController::class, 'deactivate'])->name('deactivate');
        Route::patch('/{user}/activate', [UsersController::class, 'activate'])->name('activate');
    });
    Route::get('/admin/groups', [GroupsController::class, 'index'])->name('admin.groups.index');
    Route::get('/admin/billing/receipts', [BillingReceiptsController::class, 'index'])->name('admin.billing.receipts.index');
    Route::get('/admin/owner-transfer', [OwnerTransferController::class, 'form'])->name('admin.ownerTransfer.form');
    Route::get('/admin/data-download', [DataDownloadController::class, 'index'])->name('admin.data_download.index');
    Route::get('/billing/setup', [SetupController::class, 'index'])->name('billing.setup');
    // 監査ログ（Owner/Registrar のみ）
    Route::get('/admin/audit-logs', [AuditLogsController::class, 'index'])->name('admin.audit_logs.index');
    Route::get('/admin/audit-logs/{id}', [AuditLogsController::class, 'show'])->whereNumber('id')->name('admin.audit_logs.show');

    // 招待一覧（Owner/Registrar/GroupAdmin）
    Route::get('/admin/invitations', [InvitationsController::class, 'index'])->name('admin.invitations.index');
    Route::post('/admin/invitations/{invitation}/cancel', [InvitationsController::class, 'cancel'])
        ->whereNumber('invitation')
        ->name('admin.invitations.cancel');
    Route::post('/admin/invitations/{invitation}/revoke', [InvitationsController::class, 'revoke'])
        ->whereNumber('invitation')
        ->name('admin.invitations.revoke');
    Route::post('/admin/invitations/{invitation}/resend', [InvitationsController::class, 'resend'])
        ->whereNumber('invitation')
        ->name('admin.invitations.resend');
});

// 招待承諾（ログイン不要）
Route::get('/invitations/accept/{token}', [InvitationAcceptController::class, 'show'])
    ->name('invitations.accept');
Route::post('/invitations/accept/{token}', [InvitationAcceptController::class, 'store'])
    ->name('invitations.accept.store');

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
    Route::post('/data/birth-date', [DataController::class, 'updateBirthDate'])->name('data.birthdate.update');
    // コピーフロー
    Route::get('/data/copyForm', [DataController::class, 'copyForm'])->name('data.copyForm');
    Route::post('/data/copy', [DataController::class, 'copy'])->name('data.copy');
    // 編集（メタ編集＋年度変更（移動/上書き））
    Route::get('/data/{data}/edit', [DataController::class, 'edit'])->name('data.edit');
    Route::put('/data/{data}', [DataController::class, 'update'])->name('data.update');
    Route::delete('/data/{data}', [DataController::class, 'destroy'])->name('data.destroy');
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
    // ステータス（JSON）
    Route::get('/pdf/{report}/status', [PdfOutputController::class, 'status'])
        ->where('report', '[a-z0-9_\-]+')
        ->name('pdf.status');
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

Route::post('/furusato/calc', [FurusatoController::class, 'calc'])->name('furusato.calc');

Route::middleware(['auth'])->prefix('furusato')->group(function () {
    Route::get('/', [FurusatoController::class, 'index'])->name('furusato.index');
    Route::get('/input', [FurusatoController::class, 'index'])->name('furusato.input');
    Route::get('/master', [FurusatoController::class, 'master'])->name('furusato.master');
    Route::get('/master/shotoku_master', [FurusatoController::class, 'shotokuMaster'])->name('furusato.master.shotoku');
    Route::get('/master/jumin_master', [FurusatoController::class, 'juminMaster'])->name('furusato.master.jumin');
    Route::post('/master/jumin_master/save', [FurusatoController::class, 'juminMasterSave'])->name('furusato.master.jumin.save');
    Route::get('/master/tokurei_master', [FurusatoController::class, 'tokureiMaster'])->name('furusato.master.tokurei');
    Route::get('/master/shinkokutokurei_master', [FurusatoController::class, 'shinkokutokureiMaster'])->name('furusato.master.shinkokutokurei');
    Route::get('/syori', [FurusatoController::class, 'syoriIndex'])->name('furusato.syori');
    Route::post('/syori/save', [FurusatoController::class, 'syoriSave'])->name('furusato.syori.save');
    Route::post('/save', [FurusatoController::class, 'save'])->name('furusato.save');
    // 直打ちやリロードで GET /furusato/calc に来たら入力画面へ戻す
    Route::get('/calc', fn() => redirect()->route('furusato.index'));

    Route::get('/details/jigyo_eigyo', [FurusatoController::class, 'jigyoEigyoDetails'])
        ->name('furusato.details.jigyo');
    Route::post('/details/jigyo_eigyo/save', [FurusatoController::class, 'saveJigyoEigyoDetails'])
        ->name('furusato.details.jigyo.save');
    Route::get('/details/fudosan', [FurusatoController::class, 'fudosanDetails'])
        ->name('furusato.details.fudosan');
    Route::post('/details/fudosan/save', [FurusatoController::class, 'saveFudosanDetails'])
        ->name('furusato.details.fudosan.save');
    Route::get('/details/joto_ichiji', [FurusatoController::class, 'jotoIchijiDetails'])
        ->name('furusato.details.joto_ichiji');
    Route::post('/details/joto_ichiji/save', [FurusatoController::class, 'saveJotoIchijiDetails'])
        ->name('furusato.details.joto_ichiji.save');
    Route::get('/details/bunri_joto', [FurusatoController::class, 'bunriJotoDetails'])
        ->name('furusato.details.bunri_joto');
    Route::post('/details/bunri_joto/save', [FurusatoController::class, 'saveBunriJotoDetails'])
        ->name('furusato.details.bunri_joto.save');
    Route::get('/details/bunri_kabuteki', [FurusatoController::class, 'bunriKabutekiDetails'])
        ->name('furusato.details.bunri_kabuteki');
    Route::post('/details/bunri_kabuteki/save', [FurusatoController::class, 'saveBunriKabutekiDetails'])
        ->name('furusato.details.bunri_kabuteki.save');
    Route::get('/details/bunri_sakimono', [FurusatoController::class, 'bunriSakimonoDetails'])
        ->name('furusato.details.bunri_sakimono');
    Route::post('/details/bunri_sakimono/save', [FurusatoController::class, 'saveBunriSakimonoDetails'])
        ->name('furusato.details.bunri_sakimono.save');
    Route::get('/details/bunri_sanrin', [FurusatoController::class, 'bunriSanrinDetails'])
        ->name('furusato.details.bunri_sanrin');
    Route::post('/details/bunri_sanrin/save', [FurusatoController::class, 'saveBunriSanrinDetails'])
        ->name('furusato.details.bunri_sanrin.save');
    Route::get('/details/kojo_seimei_jishin', [FurusatoController::class, 'kojoSeimeiJishinDetails'])
        ->name('furusato.details.kojo_seimei_jishin');
    Route::post('/details/kojo_seimei_jishin/save', [FurusatoController::class, 'saveKojoSeimeiJishinDetails'])
        ->name('furusato.details.kojo_seimei_jishin.save');
    Route::get('/details/kojo_jinteki', [FurusatoController::class, 'kojoJintekiDetails'])
        ->name('furusato.details.kojo_jinteki');
    Route::post('/details/kojo_jinteki/save', [FurusatoController::class, 'saveKojoJintekiDetails'])
        ->name('furusato.details.kojo_jinteki.save');
    Route::get('/details/kojo_iryo', [FurusatoController::class, 'kojoIryoDetails'])
        ->name('furusato.details.kojo_iryo');
    Route::post('/details/kojo_iryo/save', [FurusatoController::class, 'saveKojoIryoDetails'])
        ->name('furusato.details.kojo_iryo.save');
    Route::get('/details/kifukin', [FurusatoController::class, 'kifukinDetails'])
        ->name('furusato.details.kifukin');
    Route::post('/details/kifukin/save', [FurusatoController::class, 'saveKifukinDetails'])
        ->name('furusato.details.kifukin.save');
    Route::get('/details/kyuyo_zatsu', [\App\Http\Controllers\Tax\FurusatoController::class, 'kyuyoZatsuDetails'])
        ->name('furusato.details.kyuyo_zatsu');
    Route::post('/details/kyuyo_zatsu/save', [\App\Http\Controllers\Tax\FurusatoController::class, 'saveKyuyoZatsuDetails'])
        ->name('furusato.details.kyuyo_zatsu.save');
    Route::get('/details/kojo_tokubetsu_jutaku_loan', [\App\Http\Controllers\Tax\FurusatoController::class, 'kojoTokubetsuJutakuLoanDetails'])
        ->name('furusato.details.kojo_tokubetsu_jutaku_loan');
    Route::post('/details/kojo_tokubetsu_jutaku_loan', [\App\Http\Controllers\Tax\FurusatoController::class, 'saveKojoTokubetsuJutakuLoanDetails'])
        ->name('furusato.details.kojo_tokubetsu_jutaku_loan.save');
});

