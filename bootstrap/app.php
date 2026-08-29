<?php

use App\Http\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->trustProxies(at: '*');
        //2026.02.17 追加
        $middleware->redirectUsersTo(function () {
            if (auth()->guard('admin')->check()) {
                return '/admin/dashboard'; // 管理者ならここ
            }
            return '/dashboard'; // 一般ユーザーならここ
        });

        // --- 追記: 未認証（ゲスト）がアクセスした時のリダイレクト先（必要な場合） ---
        $middleware->redirectGuestsTo(function () {
            if (request()->is('admin*')) {
                return route('admin.login'); // 管理者URLなら管理用ログインへ
            }
            return route('login'); // それ以外は一般ログインへ
        });
        // ここまで
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        // [今回追加] Stripe Webhookは外部（Stripe）から認証なしで叩かれるため、CSRF検証の対象外にする
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);

        // ❗ Spatie Permission ミドルウェアを正しく全て登録する
        $middleware->alias([
            'auth' => Authenticate::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // [今回追加] admin配下かどうかでホーム/ログイン先を判定
        $resolveHome = function (\Illuminate\Http\Request $request) {
            if (auth()->guard('admin')->check()) {
                return route('admin.dashboard');
            }
            if (auth()->guard('web')->check()) {
                return route('dashboard');
            }
            return $request->is('admin', 'admin/*') ? route('admin.login') : route('login');
        };

        // [今回追加] Inertiaリクエストなら location、通常リクエストなら redirect
        $respond = function (string $url, string $message, \Illuminate\Http\Request $request) {
            if ($request->header('X-Inertia')) {
                return \Inertia\Inertia::location($url);
            }
            return redirect($url)->with('error', $message);
        };

        // 419: CSRFトークン切れ
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) use ($respond) {
            $url = $request->is('admin', 'admin/*') ? route('admin.login') : route('login');
            return $respond($url, 'セッションの有効期限が切れました。もう一度ログインしてください。', $request);
        });

        // 401: 未ログイン
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) use ($respond) {
            $url = $request->is('admin', 'admin/*') ? route('admin.login') : route('login');
            return $respond($url, 'ログインが必要です。', $request);
        });

        // 403: 権限不足（Laravel標準のAuthorize/Policy）
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, \Illuminate\Http\Request $request) use ($resolveHome, $respond) {
            return $respond($resolveHome($request), 'この操作を行う権限がありません。', $request);
        });

        // 403: 権限不足（Spatie Permission）
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, \Illuminate\Http\Request $request) use ($resolveHome, $respond) {
            return $respond($resolveHome($request), 'この操作を行う権限がありません。', $request);
        });

        // 404 / モデル未検出
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) use ($resolveHome, $respond) {
            return $respond($resolveHome($request), 'お探しのページは見つかりませんでした。', $request);
        });

    })->create();