<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PDFController; // PDFControllerをインポート

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    // ★★★ ここから保険料シミュレーションのルートを追加 ★★★
    })->name('dashboard');

    Route::get('/greeting/{locale}', function($locale) {
        session()->put('locale', $locale);
        return redirect()->back();
    })->name('lang.change');

    // 顧客一覧PDF出力ルート

    Route::get('/customers/export/pdf', [CustomerController::class, 'exportPdf'])->name('customers.export.pdf');

    Route::delete('/customers/bulk-destroy', [CustomerController::class, 'bulkDestroy'])->name('customers.bulk-destroy');

    Route::resource('customers', CustomerController::class);

/*
    Route::get('/insurance-simulation', function () {
        return Inertia::render('InsuranceSimulation');
    })->name('insurance.simulation');
*/
    // ★★★ ここまで追加 ★★★    
});

Route::get('/test', function () {
    return Inertia::render('TestPage'); // TestPage.vue をレンダリング
});

Route::get('/insurance-simulation', function () {
    return Inertia::render('InsuranceSimulation');
})->name('insurance.simulation');

Route::get('/test-i18n', function () {
    return Inertia::render('TestI18n');
})->name('test.i18n');// ★★★ ここまで追加 ★★★

// シンプルなPDF表示テスト用のルート
Route::get('/test-pdf', [PDFController::class, 'showSimplePdf'])->name('test.pdf');

