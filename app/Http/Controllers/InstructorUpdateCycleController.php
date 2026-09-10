<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InstructorUpdateCycle;

class InstructorUpdateCycleController extends Controller
{
    public function updateStatus(Request $request)
    {
        $request->validate([
            'id' => ['required', 'exists:instructor_update_cycles,id'],
            'status' => ['required', 'in:updated,before_update,no_update,pending'],
        ]);

        $cycle = InstructorUpdateCycle::findOrFail($request->id);

        // [今回追加] 更新申請（status → pending）は、年会費が納入済みでなければ受け付けない
        if ($request->status === 'pending') {
            $member = $cycle->member;
            if (!$member || !$member->isAnnualFeePaid()) {
                return back()->withErrors(['status' => '年会費が未納のため、更新申請できません。']);
            }
        }

        if ($request->status === 'no_update') {
            // 「更新しない」に切り替える。直前が承認済み（approved）だった場合のみ、
            // その状態をスナップショットとして chief_status に残しておく。
            $cycle->chief_status = $cycle->status === 'approved' ? 'approved' : null;
            $cycle->status = 'no_update';
        } elseif ($request->status === 'before_update' && $cycle->status === 'no_update') {
            // 「更新しない」から「やっぱり更新する」へ戻す場合のみ特別処理する。
            // chief_status があれば approved に戻し、無ければ before_update のまま。
            if ($cycle->chief_status === 'approved') {
                $cycle->status = 'approved';
            } else {
                $cycle->status = 'before_update';
                // 再申請扱いになるため、担当審査員の割り当てをクリアする
                $cycle->reviewer_admin_id = null;
                $cycle->reviewer_judgment = 'unreviewed';
                $cycle->reviewer_judged_at = null;
            }
            $cycle->chief_status = null;
        } elseif ($request->status === 'pending' && $cycle->status === 'reject') {
            // 却下（reject）後に会員が再申請する場合も「再申請」扱いとし、
            // 前回の審査結果を必ずクリアする。
            $cycle->status = 'pending';
            $cycle->reviewer_admin_id = null;
            $cycle->reviewer_judgment = 'unreviewed';
            $cycle->reviewer_judged_at = null;
            $cycle->reason = null;
            $cycle->chief_feedback = null;
            $cycle->reviewer_response_message = null;
        } else {
            // それ以外（updated・pending 等、従来通りの単純な切り替え）
            $cycle->status = $request->status;
        }

        $cycle->save();

        $message = match ($request->status) {
            'no_update' => 'キャンセルを受け付けました。',
            default => '更新申請を送信しました。',
        };

        return back()->with('success', $message);
    }
}
