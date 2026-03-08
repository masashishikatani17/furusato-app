<?php

use App\Http\Controllers\JpaymentWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;

// J-Payment（ROBOT PAYMENT）キックバック受信口（段階1：保存のみ）
Route::post('/jpayment/kickback/payment', [JpaymentWebhookController::class, 'payment'])
    ->name('jpayment.kickback.payment');
Route::post('/jpayment/kickback/auto_charge', [JpaymentWebhookController::class, 'autoCharge'])
    ->name('jpayment.kickback.auto_charge');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// トークン発行（ログイン後に叩く想定）
Route::post('/tokens/create', function (Request $request) {
    $request->validate(['name' => 'required|string']);
    $token = $request->user()->createToken($request->name)->plainTextToken;
    return response()->json(['token' => $token], 201);
})->middleware('auth:sanctum');

// お試しAPI
Route::get('/me', fn(Request $r) => ['id'=>$r->user()->id, 'roles'=>$r->user()->getRoleNames()])
    ->middleware('auth:sanctum');