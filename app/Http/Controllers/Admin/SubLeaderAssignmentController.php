<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\InstructorUpdateCycle;
use App\Models\PdfUpload;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class SubLeaderAssignmentController extends Controller
{
    // 申請一覧（filter: unassigned=未アサインのみ / assigned=アサイン済みのみ / all=すべて）
    // + 審査員ごとの現在の担当件数
    public function index(Request $request)
    {
        $filter = in_array($request->filter, ['unassigned', 'assigned', 'all'])
            ? $request->filter
            : 'unassigned';

        $reviewerId = $request->reviewer_id;

        // 申請日（updated_at）・アサイン日（reviewer_assigned_at）でソート可能にする
        $sortableColumns = ['updated_at', 'reviewer_assigned_at'];
        $sortBy = in_array($request->sort_by, $sortableColumns) ? $request->sort_by : 'updated_at';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';

        $query = InstructorUpdateCycle::with(['member', 'reviewerAdmin'])
            ->where('status', 'pending')
            // 前日までに申請されたものだけを表示する
            // （My Page からリアルタイムで上がってくる申請を、即座にこの画面に反映させないためのバッファ）
            // ->whereDate('updated_at', '<=', now()->subDay()->toDateString());
            // [変更] 一旦リアルタイム反映に近づけるため、当日分まで含めて取得する
            ->whereDate('updated_at', '<=', now()->toDateString());

        if ($reviewerId) {
            // 特定の審査員（先生）のバッジをクリックした場合：その先生の担当分のみに絞り込む
            $query->where('reviewer_admin_id', $reviewerId);
        } elseif ($filter === 'unassigned') {
            $query->whereNull('reviewer_admin_id');
        } elseif ($filter === 'assigned') {
            $query->whereNotNull('reviewer_admin_id');
        }
        // 'all' の場合はアサイン状況で絞り込まない

        $cycles = $query->orderBy($sortBy, $sortDir)->paginate($request->per_page ?? 20);

        $isConferenceParticipation = function ($u) {
            return $u->creditCategory?->name === '学術集会'
                && $u->creditConference?->name === '日本腎臓リハビリテーション学会'
                && $u->creditRole?->creditRole?->name === '参加';
        };

        // 審査状況・承認済み単位数・承認済み参加回数を付与し、
        // 審査（承認/差し戻し）が1件でも行われているかも合わせて判定する
        $cycles->getCollection()->transform(function ($cycle) use ($isConferenceParticipation) {
            $uploads = PdfUpload::with(['creditCategory', 'creditConference', 'creditRole.creditRole'])
                ->where('member_id', $cycle->member_id)
                ->whereDate('issued_date', '>=', $cycle->start_date)
                ->whereDate('issued_date', '<=', $cycle->end_date)
                ->get();

            $totalCount = $uploads->count();
            $reviewedCount = $uploads->whereIn('status', ['approved', 'rejected'])->count();
            $approvedUploads = $uploads->where('status', 'approved');

            $cycle->document_review_status = "{$reviewedCount} / {$totalCount}";
            $cycle->approved_points_total = $approvedUploads->sum('points');
            $cycle->approved_conference_count = $approvedUploads->filter($isConferenceParticipation)->count();
            $cycle->review_started = $reviewedCount > 0;

            return $cycle;
        });

        // 審査員一覧 + 現在の担当件数（負荷の目安）
        // アサイン担当者も審査員としてアサイン対象に含める
        $reviewers = Admin::role(['審査員', 'アサイン担当者'], 'admin')
            ->withCount([
                'reviewingCycles as active_count' => function ($q) {
                    $q->where('status', 'pending');
                },
            ])
            ->get(['id', 'name']);

        // 全体の未アサイン件数（先生ごとのバッジに共通で表示する）
        $unassignedCount = InstructorUpdateCycle::where('status', 'pending')
            ->whereNull('reviewer_admin_id')
            // ->whereDate('updated_at', '<=', now()->subDay()->toDateString())
            ->whereDate('updated_at', '<=', now()->toDateString())
            ->count();

        return inertia('Admin/SubLeader/Index', [
            'cycles' => $cycles,
            'reviewers' => $reviewers,
            'filter' => $filter,
            'reviewerId' => $reviewerId ? (int) $reviewerId : null,
            'unassignedCount' => $unassignedCount,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    // 1申請に審査員をアサイン（初回・再アサインいずれも常に可能。
    // 審査着手済みの申請を別の審査員に変更した場合、フロント側で警告を出す運用とする）
    // [今回追加] 審査員自身が申請者本人（member_codeが一致）の場合は利益相反のためアサイン不可
    // [重要] updated_at は My Page 側で「申請した瞬間」の基準時刻として使われているため、
    // アサイン操作では更新しない（timestamps を一時的に無効化して保存する）
    public function assign(Request $request, InstructorUpdateCycle $cycle)
    {
        $request->validate([
            'reviewer_admin_id' => 'required|exists:admins,id',
        ]);

        $reviewer = Admin::find($request->reviewer_admin_id);
        $cycle->loadMissing('member');

        if ($this->isConflictOfInterest($reviewer, $cycle)) {
            return back()->withErrors(['reviewer_admin_id' => 'この審査員は申請者本人のため、利益相反によりアサインできません。']);
        }

        $cycle->timestamps = false;
        $cycle->reviewer_admin_id = $request->reviewer_admin_id;
        $cycle->reviewer_assigned_at = now();
        $cycle->save();
        $cycle->timestamps = true;

        return back()->with('success', '審査員をアサインしました。');
    }

    /**
     * 選択した複数の申請を、指定した1名の審査員にまとめてアサインする。
     * 審査着手済みの申請も対象に含む（変更は常に可能）。
     * [今回追加] 利益相反（審査員本人＝申請者）に該当する申請はスキップする。
     */
    public function bulkAssign(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:instructor_update_cycles,id',
            'reviewer_admin_id' => 'required|exists:admins,id',
        ]);

        $reviewer = Admin::find($request->reviewer_admin_id);

        $cycles = InstructorUpdateCycle::with('member')
            ->whereIn('id', $request->ids)
            ->where('status', 'pending')
            ->get();

        $assignedCount = 0;
        $skippedForConflict = 0;

        foreach ($cycles as $cycle) {
            if ($this->isConflictOfInterest($reviewer, $cycle)) {
                $skippedForConflict++;
                continue;
            }

            $cycle->timestamps = false;
            $cycle->reviewer_admin_id = $request->reviewer_admin_id;
            $cycle->reviewer_assigned_at = now();
            $cycle->save();
            $cycle->timestamps = true;

            $assignedCount++;
        }

        $message = "{$assignedCount}件をアサインしました。";
        if ($skippedForConflict > 0) {
            $message .= "（申請者本人のため{$skippedForConflict}件をスキップしました）";
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * 未アサインの申請を、審査員一覧の並び順（上から順）にラウンドロビンで均等に割り振る。
     * 既にアサイン済みの申請は対象外（上書きしない）。
     * [今回追加] 利益相反（審査員本人＝申請者）に該当する場合は、次の審査員にスライドする。
     * 全審査員が利益相反に該当する場合のみ、その申請は未アサインのままスキップする。
     */
    public function autoAssign(Request $request)
    {
        // アサイン担当者も審査員としてアサイン対象に含める
        $reviewers = Admin::role(['審査員', 'アサイン担当者'], 'admin')
            ->orderBy('id') // 上から順（＝一覧の並び順）に固定
            ->get(['id', 'name', 'member_code']);

        if ($reviewers->isEmpty()) {
            return back()->withErrors(['reviewer' => '割り振り可能な審査員がいません。']);
        }

        $unassigned = InstructorUpdateCycle::with('member')
            ->where('status', 'pending')
            ->whereNull('reviewer_admin_id')
            // 一覧画面と同じく、前日までに申請されたものだけを自動割振の対象にする
            // ->whereDate('updated_at', '<=', now()->subDay()->toDateString())
            // [変更] 一旦リアルタイム反映に近づけるため、当日分まで含めて対象にする
            ->whereDate('updated_at', '<=', now()->toDateString())
            ->orderBy('updated_at')
            ->get();

        if ($unassigned->isEmpty()) {
            return back()->with('success', '未アサインの申請はありません。');
        }

        $reviewerCount = $reviewers->count();
        $rotationIndex = 0;
        $assignedCount = 0;
        $skippedForConflict = 0;
        $assignedAt = now();

        foreach ($unassigned as $cycle) {
            $assigned = false;

            // 利益相反の審査員はスキップしながら、次の候補を順に試す（最大 reviewerCount 回）
            for ($attempt = 0; $attempt < $reviewerCount; $attempt++) {
                $candidate = $reviewers[$rotationIndex % $reviewerCount];
                $rotationIndex++;

                if ($this->isConflictOfInterest($candidate, $cycle)) {
                    continue;
                }

                $cycle->timestamps = false;
                $cycle->reviewer_admin_id = $candidate->id;
                $cycle->reviewer_assigned_at = $assignedAt;
                $cycle->save();
                $cycle->timestamps = true;

                $assignedCount++;
                $assigned = true;
                break;
            }

            if (!$assigned) {
                // 全審査員が利益相反に該当（＝審査員が本人1名しかいない等）：未アサインのまま残す
                $skippedForConflict++;
            }
        }

        $perReviewer = $reviewerCount > 0 ? intdiv($assignedCount, $reviewerCount) : 0;

        $message = "{$assignedCount}件を{$reviewerCount}名の審査員に自動割振しました（1人あたり約{$perReviewer}件）。";
        if ($skippedForConflict > 0) {
            $message .= "（申請者本人のため{$skippedForConflict}件は未アサインのままです）";
        }

        return back()->with('success', $message);
    }

    /**
     * 利益相反判定：審査員（アサイン先候補）の member_code が、
     * 申請者本人（cycle.member.code）と一致するかどうか。
     * どちらかが未設定（null/空文字）の場合は判定不能として false（相反なし）を返す。
     */
    private function isConflictOfInterest(?Admin $reviewer, InstructorUpdateCycle $cycle): bool
    {
        if (!$reviewer || !$reviewer->member_code) {
            return false;
        }

        $applicantCode = $cycle->member?->code;

        if (!$applicantCode) {
            return false;
        }

        return $reviewer->member_code === $applicantCode;
    }
}