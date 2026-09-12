<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UsernameNewPasswordController extends Controller
{
    /**
     * 新しいパスワードを設定する画面を表示する（メール内のリンクから遷移）。
     */
    public function create(Request $request)
    {
        return Inertia::render('Auth/ResetPassword', [
            // [今回修正] 会員（userガード）はusername（会員番号）ベースで統一する
            'username' => $request->username,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * 新しいパスワードを保存する。ロジックはFortify標準のものを踏襲している。
     */
    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'username' => 'required|string',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::broker(config('fortify.passwords'))->reset(
            $request->only('username', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        return back()->withErrors([
            'username' => __($status),
        ]);
    }
}
