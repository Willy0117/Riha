<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SmsService;
use Illuminate\Http\Request;

/**
 * [今回追加] SMS送信の動作確認用。
 * ログイン中の管理者（Admin）の携帯電話番号宛に、任意のメッセージを送信できる。
 * 本番運用の機能ではなく、AWS SNS連携が正しく動くかを確認するための一時的な画面。
 */
class SmsTestController extends Controller
{
    public function index()
    {
        $admin = auth('admin')->user();

        return inertia('Admin/SmsTest/Index', [
            'adminMobile' => $admin->mobile ?? null,
        ]);
    }

    public function send(Request $request, SmsService $smsService)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'message' => 'required|string|max:670',
        ]);

        $success = $smsService->send($request->phone_number, $request->message);

        if ($success) {
            return back()->with('success', 'SMSを送信しました。');
        }

        return back()->withErrors(['message' => 'SMS送信に失敗しました。ログを確認してください。']);
    }
}
