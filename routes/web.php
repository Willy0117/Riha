<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SetLocaleController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\MemberController as AdminMemberController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\ApplicationController;

use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest:admin')->group(function () {

        Route::get('/login', [\App\Http\Controllers\Admin\AuthController::class, 'showLogin'])->name('login');

        Route::post('/login', [\App\Http\Controllers\Admin\AuthController::class, 'login'])->name('login.post');
    });

    // 認証後
    Route::middleware(['auth:admin'])->group(function () {

        Route::post('/logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])
            ->name('logout');

        Route::get('/dashboard', fn () => inertia('Admin/Dashboard'))
            ->name('dashboard');
        // Tenant
        Route::resource('tenants', \App\Http\Controllers\Admin\TenantController::class);
        Route::post('tenants/bulk-delete', [\App\Http\Controllers\Admin\TenantController::class, 'bulkDelete'])->name('tenants.bulkDelete');
        // Role
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class);
        Route::post('roles/bulk-delete', [\App\Http\Controllers\Admin\RoleController::class, 'bulkDelete'])->name('roles.bulkDelete');
        // Permission
        Route::resource('permissions', \App\Http\Controllers\Admin\PermissionController::class);
        Route::post('permissions/bulk-delete', [\App\Http\Controllers\Admin\PermissionController::class, 'bulkDelete'])->name('permissions.bulkDelete');
        Route::post('permissions/assign', [\App\Http\Controllers\Admin\PermissionController::class, 'assign'])->name('permissions.assign');
        // user
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class);

        Route::prefix('member')->name('member.')->group(function () {
            Route::get('/', [AdminMemberController::class, 'index'])->name('index');
            Route::get('/pdf/{id}', [AdminMemberController::class, 'pdfPreview'])->name('pdf.preview');
            Route::get('/{member}', [AdminMemberController::class, 'show'])->name('show');
            Route::get('/{member}/edit', [AdminMemberController::class, 'edit'])->name('edit');
            Route::put('/{member}', [AdminMemberController::class, 'update'])->name('update');
            // routes/admin.php
            Route::get('{member}/status/edit', [AdminMemberController::class, 'editStatus'])
                ->name('editStatus');

            Route::put('{member}/status', [AdminMemberController::class, 'updateStatus'])
                ->name('updateStatus');

            Route::get('/{member}/progress/edit', [AdminMemberController::class, 'editProgress'])
                ->name('editProgress');
            Route::put('/{member}/progress', [AdminMemberController::class, 'updateProgress'])
                ->name('updateProgress');
            Route::post('/{member}/upload-document', [AdminMemberController::class, 'uploadDocument'])
                ->name('uploadDocument');
              
        });
    });
});

// 未ログインユーザー用
//Route::middleware('guest:web')->group(function () {
//    Route::get('/login', [\App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
//    Route::post('/login', [\App\Http\Controllers\Auth\LoginController::class, 'login']);
//});

Route::prefix('members')->group(function () {

    Route::get('register/{token}', 
        [MemberRegController::class, 'showRegistrationForm']
    )->name('members.register');

    Route::get('register/{token}/register', 
        [MemberRegController::class, 'showRegisterForm']
    )->name('members.register.register');

    Route::post('register/{token}/register',[MemberRegController::class, 'register'])->name('members.register.register');

    // DB登録・完了処理（POST データなしでも session から処理）
    Route::get('register/{token}/complete-registration', [MemberRegController::class, 'completeRegistration'])
        ->name('members.completeRegistration');

    // 完了画面
    Route::get('complete',[MemberRegController::class, 'showComplete'])->name('members.complete');

    // 加盟団体加入で拒否された場合のメッセージ画面
    Route::get('register/{token}/rejected', [MemberRegController::class, 'showRejectedMessage'])
        ->name('members.register.rejected');

    Route::get('resend', [MemberRegController::class, 'resend'])
        ->name('members.resend');

    Route::get('bank', [MemberRegController::class, 'bank'])
        ->name('members.bank');

    Route::get('pdfcreate', [MemberRegController::class, 'pdfCreate'])
        ->name('members.pdfcreate');

    Route::post('pdfgenerate/{token}', [MemberRegController::class, 'pdfGenerate']
        )->name('members.pdfgenerate');
/*
    Route::post('pdfgenerate', [MemberRegController::class, 'pdfGenerate'])
        ->name('members.pdfgenerate');   
*/
    Route::get('pdf-preview/{token}', 
        [MemberRegController::class, 'pdfPreview']
    )->name('members.pdf.preview');

});

Route::get('/test-mail', function () {
    \Mail::raw('SES Production Test', function ($message) {
        $message->to('dev@coo-net.co.jp')
                ->subject('SES OK');
    });

    return 'sent';
});


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

Route::get('/insurance-simulation', function () {
    return Inertia::render('InsuranceSimulation');
});

Route::post('/locale', function (Request $request) {
    $locale = $request->input('locale', 'en');
    session(['locale' => $locale]);
    app()->setLocale($locale);
    return response()->json(['status' => 'ok']);
});

Route::middleware([
    'auth:web',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('applications', ApplicationController::class);


});
/*
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
*/