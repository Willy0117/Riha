<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\BankCategoryController;
use App\Http\Controllers\InsuranceSimulationController;
use App\Models\Organization;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/calculate-premium', [InsuranceSimulationController::class, 'calculate']);

Route::get('/banks', [BankController::class, 'index']);
Route::get('/branches', [BankController::class, 'branches']);
Route::get('/bank-categories', [BankCategoryController::class, 'index']);

Route::get('/zipcode/{zip}', function ($zip) {
    $zip = preg_replace('/[^0-9]/', '', $zip);

    if (strlen($zip) !== 7) {
        return response()->json(['results' => []]);
    }

    $response = Http::get(
        'https://zipcloud.ibsnet.co.jp/api/search',
        ['zipcode' => $zip]
    );

    return $response->json();
});

// Autocomplete用
Route::get('/organizations/search', function (Request $request) {

    $keyword = $request->q;

    return Organization::query()
        ->where('name', 'like', "%{$keyword}%")
        ->orderBy('name')
        ->limit(20)
        ->get(['id', 'name', 'abbr'])
        ->map(fn ($o) => [
            'id' => $o->id,
            'name' => $o->name,
            'label' => $o->name . ($o->abbr ? ' (' . $o->abbr . ')' : ''),
        ]);
});

