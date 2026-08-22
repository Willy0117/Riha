<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\InstructorUpdateCycle;
use App\Models\Member;
use App\Models\PdfUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InstructorMemberController extends Controller
{
    // ファイルを保存しているディスク（S3運用時は 'local' に固定せず、必ずこの共通メソッド経由で取得する）
    private function disk()
    {
        return Storage::disk(config('filesystems.default'));
    }

    private function thumbnailUrl(?string $thumbnailPath): ?string
    {
        if (!$thumbnailPath) return null;

        return $this->disk()->temporaryUrl($thumbnailPath, now()->addMinutes(30));
    }

    // Index: 会員一覧（事務局）
    public function index(Request $request)
    {
        $search = $request->search;
        $renewalYear = $request->renewal_year;
        $page = $request->page ?? 1;
        $per_page = $request->per_page ?? 20;

        $query = Member::whereHas('user')
            ->with([
                'updateCycles',
                'pdfUploads',
                'invoices',
            ]);

        if (!empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        if (!empty($renewalYear)) {
            $query->whereHas('updateCycles', function ($q) use ($renewalYear) {
                $q->whereYear('renewal_start_date', $renewalYear);
            });
        }

        $members = $query->paginate($per_page)->through(function ($member) {
            $member->updateCycles->each(function ($cycle) use ($member) {
                $cycle->conference_count = PdfUpload::where('member_id', $member->id)
                    ->where('status', 'approved')
                    ->whereHas('creditCategory', fn ($q) => $q->where('name', '学術集会'))
                    ->whereHas('creditConference', fn ($q) => $q->where('name', '日本腎臓リハビリテーション学会'))
                    ->whereDate('issued_date', '>=', $cycle->start_date)
                    ->whereDate('issued_date', '<=', $cycle->end_date)
                    // [修正] creditRole は creditRole.creditRole のネストで、属性名は role ではなく name
                    ->whereHas('creditRole.creditRole', fn ($q) => $q->where('name', '参加'))
                    ->count();

                $cycle->total_points = PdfUpload::where('member_id', $member->id)
                    ->where('status', 'approved')
                    ->whereDate('issued_date', '>=', $cycle->start_date)
                    ->whereDate('issued_date', '<=', $cycle->end_date)
                    ->sum('points');
            });

            return $member;
        });

        return inertia('Admin/InstructorMembers/Index', [
            'members' => $members,
            'filters' => [
                'search' => $search,
                'renewal_year' => $renewalYear,
                'page' => $page,
                'per_page' => $per_page,
            ],
        ]);
    }

    // Show: 会員の PDF 一覧（事務局が内容を閲覧する用途。承認/差し戻しは審査員画面へ移動）
    public function show(Request $request, Member $member)
    {
        $member->load([
            'updateCycles',
            'invoices',
        ]);

        $cycle = $member->updateCycles->first();

        $uploads = PdfUpload::with(['creditCategory', 'creditConference', 'creditRole.creditRole'])
            ->where('member_id', $member->id)
            ->latest()
            ->get()
            ->map(function (PdfUpload $upload) {
                return array_merge($upload->toArray(), [
                    'credit_conference_name' => $upload->creditConference?->name ?? '',
                    'category_name' => $upload->creditCategory?->name ?? '',
                    // [修正] creditRole は creditRole.creditRole のネストで、属性名は role ではなく name
                    'role_name' => $upload->creditRole?->creditRole?->name ?? '',
                    'thumbnail_url' => $this->thumbnailUrl($upload->thumbnail_path),
                ]);
            });

        if ($cycle) {
            $cycle->conference_count = PdfUpload::where('member_id', $member->id)
                ->where('status', 'approved')
                ->whereHas('creditCategory', fn ($q) => $q->where('name', '学術集会'))
                ->whereHas('creditConference', fn ($q) => $q->where('name', '日本腎臓リハビリテーション学会'))
                ->whereDate('issued_date', '>=', $cycle->start_date)
                ->whereDate('issued_date', '<=', $cycle->end_date)
                // [修正] creditRole は creditRole.creditRole のネストで、属性名は role ではなく name
                ->whereHas('creditRole.creditRole', fn ($q) => $q->where('name', '参加'))
                ->count();

            $cycle->total_points = PdfUpload::where('member_id', $member->id)
                ->where('status', 'approved')
                ->whereDate('issued_date', '>=', $cycle->start_date)
                ->whereDate('issued_date', '<=', $cycle->end_date)
                ->sum('points');
        }

        return inertia('Admin/InstructorMembers/Show', [
            'member' => $member,
            'uploads' => $uploads,
            'filters' => [
                'search' => $request->search,
                'page' => $request->page,
            ],
        ]);
    }

    /**
     * [バグ修正]
     * - use App\Models\InstructorUpdateCycle; を追加（クラス未定義エラーの修正）
     * - status のバリデーションに approved / reject を追加
     *   （Index.vue の判定モーダルは updated / no_update / rejected の3択のため、
     *     rejected 選択時にバリデーションエラーになっていた問題を修正）
     */
    public function review(Request $request, InstructorUpdateCycle $updateCycle)
    {
        $request->validate([
            'status' => 'required|in:approved,updated,no_update,reject,rejected',
            'reason' => 'nullable|string|max:1000',
        ]);

        // Vue側は 'rejected' を送ってくるが、DBのenumは 'reject' なので変換する
        $status = $request->status === 'rejected' ? 'reject' : $request->status;

        $updateCycle->status = $status;
        $updateCycle->reason = $request->reason;
        $updateCycle->save();

        return redirect()->back()->with('success', __('Review updated successfully.'));
    }

    /**
     * 認定期間の更新処理（事務局が入金確認後に手動実行する）。
     * Stripe決済・振込のいずれであっても、ここでの手動実行がトリガーとなる。
     *
     * - 対象は status = 'approved'（委員長の最終承認済み）の cycle のみ。それ以外はスキップする。
     * - start_date/end_date を renewal_start_date/renewal_end_date で上書きし、次期期間を本期間に昇格させる。
     * - 新しい end_date と同じ年の 4/1〜12/1 を、次の renewal_start_date/renewal_end_date として設定する。
     * - status を 'updated' に変更する。
     * - 同じ cycle レコードを次期の審査でも使い回すため、審査関連カラム
     *   （reviewer_admin_id・reviewer_judgment・reviewer_judged_at）は未アサイン・未判定にリセットする。
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:instructor_update_cycles,id',
        ]);

        $cycles = InstructorUpdateCycle::whereIn('id', $request->ids)
            ->where('status', 'approved')
            ->get();

        $updatedCount = 0;
        $skippedCount = count($request->ids) - $cycles->count();

        foreach ($cycles as $cycle) {
            $cycle->start_date = $cycle->renewal_start_date;
            $cycle->end_date = $cycle->renewal_end_date;
            $cycle->status = 'updated';

            // 新しい end_date と同じ年の 4/1〜12/1 を、次の renewal 期間として設定する
            $year = \Carbon\Carbon::parse($cycle->end_date)->year;
            $cycle->renewal_start_date = \Carbon\Carbon::create($year, 4, 1);
            $cycle->renewal_end_date = \Carbon\Carbon::create($year, 12, 1);

            // 次期の審査に向けてリセット
            $cycle->reviewer_admin_id = null;
            $cycle->reviewer_judgment = 'unreviewed';
            $cycle->reviewer_judged_at = null;

            $cycle->save();
            $updatedCount++;
        }

        $message = "{$updatedCount}件の認定期間を更新しました。";
        if ($skippedCount > 0) {
            $message .= "（承認済みでないため{$skippedCount}件をスキップしました）";
        }

        return redirect()->back()->with('success', $message);
    }

    // PDF本体プレビュー（署名URLへリダイレクト・閲覧専用のため権限チェックなし）
    public function view(Request $request, $id)
    {
        $upload = PdfUpload::findOrFail($id);

        if (!$upload->file_path || !$this->disk()->exists($upload->file_path)) {
            abort(404);
        }

        return redirect(
            $this->disk()->temporaryUrl($upload->file_path, now()->addMinutes(10))
        );
    }

    // サムネイル（署名URLへリダイレクト）
    public function thumbnail(Request $request, $id)
    {
        $upload = PdfUpload::findOrFail($id);

        if (!$upload->thumbnail_path || !$this->disk()->exists($upload->thumbnail_path)) {
            abort(404);
        }

        return redirect(
            $this->disk()->temporaryUrl($upload->thumbnail_path, now()->addMinutes(10))
        );
    }
}
