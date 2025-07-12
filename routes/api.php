<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InsuranceSimulationController; // 追加
use App\Http\Controllers\Api\CustomerController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/calculate-premium', [InsuranceSimulationController::class, 'calculate']);

Route::get('/customers', [CustomerController::class, 'index']);
