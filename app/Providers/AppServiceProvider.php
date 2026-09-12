<?php

namespace App\Providers;

use Inertia\Inertia;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use App\Models\Member;
use App\Observers\MemberObserver;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
       //
       if (env('APP_ENV') !== 'local') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
       }
       Inertia::share([
            'csrf_token' => fn () => csrf_token(),
       ]);

       // [今回追加] パスワードルール：本番相当環境（local以外）では
       // 8文字以上・英字＋数字＋記号を必須にする。local（開発中）は従来通り緩い既定値のまま。
       if (env('APP_ENV') !== 'local') {
           Password::defaults(function () {
               return Password::min(8)
                   ->letters()
                   ->numbers()
                   ->symbols();
           });
       }
    }
}