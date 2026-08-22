<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\PdfUpload;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    // Index: 会員一覧
    public function index(Request $request)
    {
        $search = $request->search;
        $page = $request->page ?? 1;
        $per_page = $request->per_page ?? 20; 

        $query = Member::whereHas('user')
            ->whereHas('updateCycles', fn ($q) => $q->where('status', 'pending'))
            ->with([
                'updateCycles' => fn ($q) => $q->where('status', 'pending'),
                'pdfUploads',
                'invoices'
            ]);

        if (!empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        // ★ paginate にする（これが最重要）
        $members = $query->paginate($per_page)->through(function ($member) {
            // 各サイクルの集計
            $member->updateCycles->each(function($cycle) use ($member) {

                $cycle->conference_count = PdfUpload::where('member_id', $member->id)
                    ->where('status', 'approved') // ←これ追加
                    ->whereHas('creditCategory', fn($q) => $q->where('name', '学術集会'))
                    ->whereHas('creditConference', fn($q) => $q->where('name', '日本腎臓リハビリテーション学会'))
                    ->whereBetween('issued_date', [$cycle->start_date, $cycle->end_date])
                    ->whereHas('creditRole', fn($q) => $q->where('role', '参加'))
                    ->count();

                $cycle->pending_count = PdfUpload::where('member_id', $member->id)
                    ->where('status', 'pending')
                    ->whereBetween('issued_date', [$cycle->start_date, $cycle->end_date])
                    ->count();

                $cycle->total_points = PdfUpload::where('member_id', $member->id)
                    ->where('status', 'approved') // ←これだけ追加
                    ->whereBetween('issued_date', [$cycle->start_date, $cycle->end_date])
                    ->sum('points');
            });

            return $member;
        });

        return inertia('Admin/Approvals/Index', [
            'members' => $members,
            'filters' => [
                'search' => $search,
                'page' => $page,
                'per_page' => $per_page,
                
            ]
        ]);
    }

    // Show: 会員の PDF 一覧
    public function show(Request $request, Member $member)
    {
        // 会員関連データを読み込む
        $member->load([
            'updateCycles',
            'pdfUploads.creditCategory',
            'pdfUploads.creditConference',
            'pdfUploads.creditRole',
            'pdfUploads.reviewer',
            'invoices'
        ]);

        // 最新のサイクル
        $cycle = $member->updateCycles->first();

        if ($cycle) {

            // ★ 学術集会（日本腎臓リハビリテーション学会）の参加回数
            $cycle->conference_count = PdfUpload::where('member_id', $member->id)
                ->whereHas('creditCategory', fn($q) => $q->where('name', '学術集会'))
                ->whereHas('creditConference', fn($q) => $q->where('name', '日本腎臓リハビリテーション学会'))
                ->whereBetween('issued_date', [$cycle->start_date, $cycle->end_date])
                ->whereHas('creditRole', fn($q) => $q->where('role', '参加'))
                ->count();

            // ★ 合計単位（学術集会カテゴリ）
            $cycle->total_points = PdfUpload::where('member_id', $member->id)
                ->whereHas('creditCategory', fn($q) => $q->where('name', '学術集会'))
                ->whereBetween('issued_date', [$cycle->start_date, $cycle->end_date])
                ->sum('points');
        }

        // ★ session + 区分 + 学会 でグループ化
        $groupedUploads = $member->pdfUploads
            ->groupBy(function ($upload) {
                return implode('|', [
                    $upload->session,
                    $upload->credit_category_id,
                    $upload->credit_conference_id,
                ]);
            })
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'session'                => $first->session,
                    'credit_category_id'     => $first->credit_category_id,
                    'credit_category_name'   => $first->creditCategory?->name,
                    'credit_conference_id'   => $first->credit_conference_id,
                    'credit_conference_name' => $first->creditConference?->name,
                    'points'                 => $items->sum('points'),
                    'date'                   => $first->issued_date,
                    'roles'                  => $items->pluck('creditRole.role')->filter()->unique()->values(),
                    'statuses'               => $items->pluck('status')->unique()->values(),
                    'items'                  => $items->values(), // グループ内の個々のアップロード明細
                ];
            })
            ->values();

        return inertia('Admin/Approvals/Show', [
            'member'  => $member,
            'uploads' => $groupedUploads,
            'filters' => [
                'search' => $request->search,
                'page'   => $request->page,
            ]
        ]);
    }
    /**
     * インストラクター更新サイクルの審査結果を保存
     */
    public function review(Request $request, InstructorUpdateCycle $updateCycle)
    {
        $request->validate([
            'status' => 'required|in:updated,no_update',
            'reason' => 'nullable|string|max:1000',
        ]);

        $updateCycle->status = $request->status;
        $updateCycle->reason = $request->reason;
        $updateCycle->save();

        return redirect()->back()->with('success', __('Review updated successfully.'));
    }

    // PDF 承認
    public function approve($id)
    {
        $upload = PdfUpload::findOrFail($id);
        $upload->status = 'approved';
        $upload->reviewed_by = auth()->id();
        $upload->save();

        return back()->with('success', 'PDF approved.');
    }

    // PDF リジェクト
    public function reject(Request $request, $id)
    {
        $upload = PdfUpload::findOrFail($id);
        $upload->status = 'rejected';
        $upload->rejection_message = $request->reason ?? '';
        $upload->reviewed_by = auth()->id();
        $upload->save();

        return back()->with('success', 'PDF rejected.');
    }
}

