<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class UsernamePasswordResetLinkController extends Controller
{
    /**
     * 申請画面（ID＝会員番号 入力フォーム）を表示する。
     */
    public function create(Request $request)
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * 会員番号（username）を受け取り、紐づくメールアドレス宛にリセットリンクを送信する。
     */
    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !$user->email) {
            // [要確認] IDの存在有無を知らせるかどうかはセキュリティ方針次第。
            // 今回は分かりやすさを優先し、明示的にエラーを返す実装にしている。
            return back()->withErrors([
                'username' => 'この会員番号（ID）は登録されていません。',
            ]);
        }

        $status = Password::broker(config('fortify.passwords'))->sendResetLink(
            ['email' => $user->email]
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'ご登録のメールアドレス宛に、パスワード再設定用のリンクを送信しました。');
        }

        return back()->withErrors([
            'username' => 'メールの送信に失敗しました。時間を置いて再度お試しください。',
        ]);
    }
}
