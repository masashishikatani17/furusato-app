<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;

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