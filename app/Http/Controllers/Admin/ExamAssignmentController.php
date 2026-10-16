<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Exam;
use Illuminate\Http\Request;

class ExamAssignmentController extends Controller
{
    // 申請一覧（filter: unassigned=未アサインのみ / assigned=アサイン済みのみ / all=すべて）
    // + 審査員ごとの現在の担当件数
    // [SubLeaderAssignmentControllerとの違い]
    // ・対象ステータスが status='pending' ではなく status='submitted'
    //   （新規申請は「提出済み・アサイン待ち」の状態が submitted のため）
    // ・書類は期間ではなく exam_id で直接紐づくため、書類進捗の事前集計は行わない
    public function index(Request $request)
    {
        $filter = in_array($request->filter, ['unassigned', 'assigned', 'all'])
            ? $request->filter
            : 'unassigned';

        $reviewerId = $request->reviewer_id;

        $sortableColumns = ['applied_at', 'reviewer_assigned_at'];
        $sortBy = in_array($request->sort_by, $sortableColumns) ? $request->sort_by : 'applied_at';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';

        $query = Exam::with(['member', 'reviewerAdmin'])
            ->where('status', 'submitted')
            // My Pageからリアルタイムで上がってくる申請を、即座にこの画面に反映させないための
            // バッファ。更新フローと同じ考え方を踏襲する。
            ->whereDate('applied_at', '<=', now()->toDateString());

        if ($reviewerId) {
            $query->where('reviewer_admin_id', $reviewerId);
        } elseif ($filter === 'unassigned') {
            $query->whereNull('reviewer_admin_id');
        } elseif ($filter === 'assigned') {
            $query->whereNotNull('reviewer_admin_id');
        }

        $exams = $query->orderBy($sortBy, $sortDir)->paginate($request->per_page ?? 20);

        // ルート(A/B)・添付書類の提出状況を付与（審査員アサイン時の目安として）
        $exams->getCollection()->transform(function (Exam $exam) {
            $exam->loadCount(['documents', 'caseReports']);
            $exam->review_started = $exam->reviewer_judgment !== 'unreviewed';
            return $exam;
        });

        // 審査員一覧 + 現在の担当件数（負荷の目安）
        $reviewers = Admin::role(['審査員', 'アサイン担当者', 'super_admin'], 'admin')
            ->withCount([
                'reviewingExams as active_count' => function ($q) {
                    $q->where('status', 'pending');
                },
            ])
            ->get(['id', 'name']);

        $unassignedCount = Exam::where('status', 'submitted')
            ->whereNull('reviewer_admin_id')
            ->whereDate('applied_at', '<=', now()->toDateString())
            ->count();

        return inertia('Admin/ExamAssignment/Index', [
            'exams' => $exams,
            'reviewers' => $reviewers,
            'filter' => $filter,
            'reviewerId' => $reviewerId ? (int) $reviewerId : null,
            'unassignedCount' => $unassignedCount,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    // 1申請に審査員をアサイン（初回・再アサインいずれも常に可能）
    // アサインすると同時に status を submitted → pending に進める
    // [今回追加] 審査員自身が申請者本人（member_codeが一致）の場合は利益相反のためアサイン不可
    public function assign(Request $request, Exam $exam)
    {
        $request->validate([
            'reviewer_admin_id' => 'required|exists:admins,id',
        ]);

        $reviewer = Admin::find($request->reviewer_admin_id);
        $exam->loadMissing('member');

        if ($this->isConflictOfInterest($reviewer, $exam)) {
            return back()->withErrors(['reviewer_admin_id' => 'この審査員は申請者本人のため、利益相反によりアサインできません。']);
        }

        $exam->reviewer_admin_id = $request->reviewer_admin_id;
        $exam->reviewer_assigned_at = now();
        // submitted（提出済み）から pending（審査中）へ進める。
        // 既に pending の場合（再アサイン）はそのまま。
        if ($exam->status === 'submitted') {
            $exam->status = 'pending';
        }
        $exam->save();

        return back()->with('success', '審査員をアサインしました。');
    }

    /**
     * 選択した複数の申請を、指定した1名の審査員にまとめてアサインする。
     */
    public function bulkAssign(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:exams,id',
            'reviewer_admin_id' => 'required|exists:admins,id',
        ]);

        $reviewer = Admin::find($request->reviewer_admin_id);

        $exams = Exam::with('member')
            ->whereIn('id', $request->ids)
            ->whereIn('status', ['submitted', 'pending'])
            ->get();

        $assignedCount = 0;
        $skippedForConflict = 0;

        foreach ($exams as $exam) {
            if ($this->isConflictOfInterest($reviewer, $exam)) {
                $skippedForConflict++;
                continue;
            }

            $exam->reviewer_admin_id = $request->reviewer_admin_id;
            $exam->reviewer_assigned_at = now();
            if ($exam->status === 'submitted') {
                $exam->status = 'pending';
            }
            $exam->save();

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
     */
    public function autoAssign(Request $request)
    {
        $reviewers = Admin::role(['審査員', 'アサイン担当者', 'super_admin'], 'admin')
            ->orderBy('id')
            ->get(['id', 'name', 'member_code']);

        if ($reviewers->isEmpty()) {
            return back()->withErrors(['reviewer' => '割り振り可能な審査員がいません。']);
        }

        $unassigned = Exam::with('member')
            ->where('status', 'submitted')
            ->whereNull('reviewer_admin_id')
            ->whereDate('applied_at', '<=', now()->toDateString())
            ->orderBy('applied_at')
            ->get();

        if ($unassigned->isEmpty()) {
            return back()->with('success', '未アサインの申請はありません。');
        }

        $reviewerCount = $reviewers->count();
        $rotationIndex = 0;
        $assignedCount = 0;
        $skippedForConflict = 0;
        $assignedAt = now();

        foreach ($unassigned as $exam) {
            $assigned = false;

            for ($attempt = 0; $attempt < $reviewerCount; $attempt++) {
                $candidate = $reviewers[$rotationIndex % $reviewerCount];
                $rotationIndex++;

                if ($this->isConflictOfInterest($candidate, $exam)) {
                    continue;
                }

                $exam->reviewer_admin_id = $candidate->id;
                $exam->reviewer_assigned_at = $assignedAt;
                $exam->status = 'pending';
                $exam->save();

                $assignedCount++;
                $assigned = true;
                break;
            }

            if (!$assigned) {
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
     * 申請者本人（exam.member.code）と一致するかどうか。
     */
    private function isConflictOfInterest(?Admin $reviewer, Exam $exam): bool
    {
        if (!$reviewer || !$reviewer->member_code) {
            return false;
        }

        $applicantCode = $exam->member?->code;

        if (!$applicantCode) {
            return false;
        }

        return $reviewer->member_code === $applicantCode;
    }
}
