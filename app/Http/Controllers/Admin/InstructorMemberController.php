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
        // [今回変更] 複合フィルタ（申請ステータス／更新料／年会費、それぞれ独立してAND絞り込み）
        $cycleStatus = $request->cycle_status;
        $renewalFeeStatus = $request->renewal_fee_status;
        $annualFeeStatus = $request->annual_fee_status;

        $query = Member::whereHas('user')
            // [今回追加] 退会した会員は資格喪失済み（MemberController側で即時処理済み）のため、
            // 指導士一覧そのものの対象から外す
            ->where('status_id', '!=', Member::STATUS_WITHDRAWN)
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

        // [今回追加] 申請ステータスで絞り込み
        if (!empty($cycleStatus)) {
            $query->whereHas('updateCycles', fn ($q) => $q->where('status', $cycleStatus));
        }

        // [今回追加] 更新料の納付状況で絞り込み
        if (!empty($renewalFeeStatus)) {
            if ($renewalFeeStatus === 'unbilled') {
                $query->whereDoesntHave('invoices', fn ($q) => $q->where('renewal_fee', '>', 0));
            } elseif ($renewalFeeStatus === 'unpaid') {
                $query->whereHas('invoices', fn ($q) => $q->where('renewal_fee', '>', 0)->where('status', '!=', 'paid'));
            } elseif ($renewalFeeStatus === 'paid') {
                $query->whereHas('invoices', fn ($q) => $q->where('renewal_fee', '>', 0)->where('status', 'paid'))
                    ->whereDoesntHave('invoices', fn ($q) => $q->where('renewal_fee', '>', 0)->where('status', '!=', 'paid'));
            }
        }

        // [今回追加] 年会費の納付状況で絞り込み
        if (!empty($annualFeeStatus)) {
            if ($annualFeeStatus === 'unpaid') {
                $query->whereHas('invoices', fn ($q) => $q->where('annual_fee', '>', 0)->where('status', '!=', 'paid'));
            } elseif ($annualFeeStatus === 'paid') {
                $query->whereHas('invoices', fn ($q) => $q->where('annual_fee', '>', 0)->where('status', 'paid'))
                    ->whereDoesntHave('invoices', fn ($q) => $q->where('annual_fee', '>', 0)->where('status', '!=', 'paid'));
            }
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
                'cycle_status' => $cycleStatus,
                'renewal_fee_status' => $renewalFeeStatus,
                'annual_fee_status' => $annualFeeStatus,
                'page' => $page,
                'per_page' => $per_page,
            ],
        ]);
    }

    // Show: 会員の PDF 一覧（事務局が内容を閲覧する用途。承認/差し戻しは審査員画面へ移動）
    // [今回変更] 閲覧専用のshow()を廃止し、編集可能なedit()に統一する
    public function edit(Request $request, Member $member)
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
                ->whereHas('creditRole.creditRole', fn ($q) => $q->where('name', '参加'))
                ->count();

            $cycle->total_points = PdfUpload::where('member_id', $member->id)
                ->where('status', 'approved')
                ->whereDate('issued_date', '>=', $cycle->start_date)
                ->whereDate('issued_date', '<=', $cycle->end_date)
                ->sum('points');
        }

        return inertia('Admin/InstructorMembers/Edit', [
            'member' => $member,
            'uploads' => $uploads,
            // [今回追加] 会員一覧と同じ「閲覧はできるが保存できない」ガード用フラグ
            'can_edit' => $request->user('admin')->can('instructorMembers.edit'),
            'filters' => [
                'search' => $request->search,
                'page' => $request->page,
            ],
        ]);
    }

    /**
     * [今回追加] 指導士サイクルの end_date・renewal_end_date を事務局が直接編集する。
     * instructorMembers.edit 権限が無ければ保存を拒否する（Members側と同じガード方式）。
     */
    public function update(Request $request, Member $member)
    {
        if (! $request->user('admin')->can('instructorMembers.edit')) {
            return back()->withErrors([
                'permission' => 'あなたには編集権限がありません。登録・変更はできません。',
            ]);
        }

        $validated = $request->validate([
            'cycle_id' => 'required|integer|exists:instructor_update_cycles,id',
            'end_date' => 'required|date',
            'renewal_end_date' => 'required|date',
        ]);

        $cycle = InstructorUpdateCycle::where('id', $validated['cycle_id'])
            ->where('member_id', $member->id)
            ->firstOrFail();

        $cycle->update([
            'end_date' => $validated['end_date'],
            'renewal_end_date' => $validated['renewal_end_date'],
        ]);

        return redirect()->route('admin.instructorMembers.index')
            ->with('success', '認定期間を更新しました。');
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

    /**
     * [今回追加] 一覧で選択した複数の申請を、まとめて「指導士資格喪失」にする。
     * 事務局が「資格喪失候補」フィルタで抽出した上で、内容を確認してから実行する想定。
     * 通知メールは送信しない。
     */
    public function bulkLapse(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:instructor_update_cycles,id',
        ]);

        $updatedCount = InstructorUpdateCycle::whereIn('id', $request->ids)
            ->update(['status' => 'lapsed']);

        return redirect()->back()->with('success', "{$updatedCount}件を指導士資格喪失にしました。");
    }

    /**
     * [今回追加] 一覧で選択した複数の申請を、まとめて「審査前（before_update）」に戻す。
     * 更新年度に入った会員を、次の更新申請ができる状態にするために事務局が実行する。
     * status を問わず、選択したものは無条件で before_update にする。
     */
    public function bulkResetToBeforeUpdate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:instructor_update_cycles,id',
        ]);

        $updatedCount = InstructorUpdateCycle::whereIn('id', $request->ids)
            ->update(['status' => 'before_update']);

        return redirect()->back()->with('success', "{$updatedCount}件を審査前に戻しました。");
    }

    /**
     * [今回追加・現在未実装] 選択した対象のStripe請求書を作成する。
     * ②更新料支払いスキーム設計時に、具体的な処理を実装する想定でメソッドのみ用意している。
     */
    public function bulkCreateStripeInvoice(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:instructor_update_cycles,id',
        ]);

        // TODO: Stripe請求書の作成処理を実装する

        return redirect()->back()->with('success', '処理内容は未実装です。');
    }

    /**
     * [今回追加・現在未実装] 選択した対象の通常の請求書（PDF）を作成する。
     * ②更新料支払いスキーム設計時に、具体的な処理を実装する想定でメソッドのみ用意している。
     */
    public function bulkCreateInvoice(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:instructor_update_cycles,id',
        ]);

        // TODO: 請求書（PDF）の作成処理を実装する

        return redirect()->back()->with('success', '処理内容は未実装です。');
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
