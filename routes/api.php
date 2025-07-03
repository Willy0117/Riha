<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InsuranceSimulationController; // 追加

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
// ★★★ ここから追加 ★★★
Route::post('/calculate-premium', [InsuranceSimulationController::class, 'calculate']);
// ★★★ ここまで追加 ★★★