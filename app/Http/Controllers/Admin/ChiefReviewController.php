<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstructorUpdateCycle;
use App\Models\PdfUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChiefReviewController extends Controller
{
    // 審査員の合格/不合格判定が済み、委員長の最終判定待ちの申請一覧
    public function index(Request $request)
    {
        $sortableColumns = [
            'reviewer_judged_at',
            'updated_at',
            'approved_points_total',
            'approved_conference_count',
        ];

        $sortBy = in_array($request->sort_by, $sortableColumns) ? $request->sort_by : 'reviewer_judged_at';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        $perPage = $request->per_page ?? 20;
        $page = (int) ($request->page ?? 1);

        $query = InstructorUpdateCycle::with(['member', 'reviewerAdmin'])
            ->where('status', 'pending')
            // 審査員が「合格」または「不合格」の判定を出した申請のみを対象にする
            // （未審査・再審査中のものは、まだ審査員側の作業中なのでここには出さない）
            ->whereIn('reviewer_judgment', ['pass', 'fail'])
            // 前日までに審査員が判定を出したものだけを表示する
            // （審査員からリアルタイムで上がってくる判定を、委員長画面に即座に反映させないためのバッファ）
            ->whereDate('reviewer_judged_at', '<=', now()->subDay()->toDateString());

        // DB上の実カラムでの並び替えはここで先に済ませる
        if (in_array($sortBy, ['reviewer_judged_at', 'updated_at'])) {
            $query->orderBy($sortBy, $sortDir);
        }

        // approved_points_total・approved_conference_count は書類集計後にしか分からない動的値のため、
        // 対象がこの2つの場合は一旦全件取得して集計してからソート・手動でページ分割する
        $isDynamicSort = in_array($sortBy, ['approved_points_total', 'approved_conference_count']);

        $allCycles = $isDynamicSort ? $query->get() : $query->paginate($perPage);
        $collection = $isDynamicSort ? $allCycles : $allCycles->getCollection();

        // 各申請ごとの書類集計（審査進捗・未審査件数・承認済み単位数・承認済み参加回数）
        $collection = $collection->map(function ($cycle) {
            $uploads = PdfUpload::with(['creditCategory', 'creditConference', 'creditRole.creditRole'])
                ->where('member_id', $cycle->member_id)
                // issued_date に時刻が付いていても end_date の当日いっぱいを含めるよう、日単位で比較する
                ->whereDate('issued_date', '>=', $cycle->start_date)
                ->whereDate('issued_date', '<=', $cycle->end_date)
                ->get();

            $totalCount = $uploads->count();
            $reviewedCount = $uploads->whereIn('status', ['approved', 'rejected'])->count();
            $pendingCount = $uploads->where('status', 'pending')->count();

            $approvedUploads = $uploads->where('status', 'approved');

            $approvedPoints = $approvedUploads->sum('points');

            $approvedConferenceCount = $approvedUploads
                ->filter(function ($u) {
                    return $u->creditCategory?->name === '学術集会'
                        && $u->creditConference?->name === '日本腎臓リハビリテーション学会'
                        && $u->creditRole?->creditRole?->name === '参加';
                })
                ->count();

            $cycle->document_total_count = $totalCount;
            $cycle->document_reviewed_count = $reviewedCount;
            $cycle->document_pending_count = $pendingCount;
            $cycle->document_review_completed = $totalCount > 0 && $totalCount === $reviewedCount;
            $cycle->approved_points_total = $approvedPoints;
            $cycle->approved_conference_count = $approvedConferenceCount;

            return $cycle;
        });

        if ($isDynamicSort) {
            $collection = $sortDir === 'asc'
                ? $collection->sortBy($sortBy)->values()
                : $collection->sortByDesc($sortBy)->values();

            $cycles = new \Illuminate\Pagination\LengthAwarePaginator(
                $collection->forPage($page, $perPage)->values(),
                $collection->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $allCycles->setCollection($collection);
            $cycles = $allCycles;
        }

        return inertia('Admin/Chief/Index', [
            'cycles' => $cycles,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    // 1申請の書類詳細（閲覧専用。承認/差し戻し操作は審査員画面で行う）
    public function show(InstructorUpdateCycle $cycle)
    {
        $cycle->load(['member', 'reviewerAdmin']);

        $uploads = PdfUpload::with(['creditCategory', 'creditConference', 'creditRole.creditRole'])
            ->where('member_id', $cycle->member_id)
            ->whereDate('issued_date', '>=', $cycle->start_date)
            ->whereDate('issued_date', '<=', $cycle->end_date)
            ->latest()
            ->get()
            ->map(function (PdfUpload $upload) {
                return array_merge($upload->toArray(), [
                    'credit_conference_name' => $upload->creditConference?->name ?? '',
                    'role_name' => $upload->creditRole?->creditRole?->name ?? '',
                    'thumbnail_url' => $this->thumbnailUrl($upload->thumbnail_path),
                ]);
            });

        return inertia('Admin/Chief/Show', [
            'cycle' => $cycle,
            'member' => $cycle->member,
            'uploads' => $uploads,
        ]);
    }

    // PDF本体プレビュー（署名URLへリダイレクト・閲覧専用のため担当審査員チェックなし）
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

    /**
     * 最終判定を保存する（1件ずつ）。
     */
    public function review(Request $request, InstructorUpdateCycle $cycle)
    {
        $request->validate([
            'status' => 'required|in:approved,reject,no_update',
            'reason' => 'nullable|string|max:1000',
        ]);

        if (in_array($request->status, ['reject', 'no_update']) && empty($request->reason)) {
            return back()->withErrors(['reason' => '却下・更新なしの場合は理由の入力が必須です。']);
        }

        $cycle->status = $request->status;
        $cycle->reason = $request->reason;
        $cycle->save();

        return redirect()->back()->with('success', '判定を保存しました。');
    }

    /**
     * 審査員の判定を「再審査」に戻し、審査員に差し戻す。
     * 委員長が審査員の合否判定に納得できない場合に使用する。
     */
    public function sendBackToReviewer(Request $request, InstructorUpdateCycle $cycle)
    {
        $cycle->reviewer_judgment = 're_review';
        $cycle->reviewer_judged_at = null;
        $cycle->save();

        return redirect()->back()->with('success', '審査員に差し戻しました。');
    }

    /**
     * 一覧で選択した複数の申請を、まとめて承認 or 却下する。
     * 書類の審査が全て終わっていない申請は対象外にし、スキップした件数を返す。
     */
    public function bulkReview(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:instructor_update_cycles,id',
            'status' => 'required|in:approved,reject',
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($request->status === 'reject' && empty($request->reason)) {
            return back()->withErrors(['reason' => '却下の場合は理由の入力が必須です。']);
        }

        $cycles = InstructorUpdateCycle::whereIn('id', $request->ids)
            ->where('status', 'pending')
            ->get();

        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($cycles as $cycle) {
            $uploads = PdfUpload::where('member_id', $cycle->member_id)
                ->whereDate('issued_date', '>=', $cycle->start_date)
                ->whereDate('issued_date', '<=', $cycle->end_date)
                ->get(['status']);

            $totalCount = $uploads->count();
            $reviewedCount = $uploads->whereIn('status', ['approved', 'rejected'])->count();
            $completed = $totalCount > 0 && $totalCount === $reviewedCount;

            if (!$completed) {
                $skippedCount++;
                continue;
            }

            $cycle->status = $request->status;
            $cycle->reason = $request->reason;
            $cycle->save();
            $updatedCount++;
        }

        $message = "{$updatedCount}件を更新しました。";
        if ($skippedCount > 0) {
            $message .= " (書類の審査が未完了のため{$skippedCount}件をスキップしました)";
        }

        return redirect()->back()->with('success', $message);
    }

    // ファイルを保存しているディスク（S3運用時は 'local' に固定せず、必ずこの共通メソッド経由で取得する）
    private function disk()
    {
        return Storage::disk(config('filesystems.default'));
    }

    // 一覧・詳細で使うサムネイルURL（S3の場合は有効期限付きの署名URLになる）
    private function thumbnailUrl(?string $thumbnailPath): ?string
    {
        if (!$thumbnailPath) return null;

        return $this->disk()->temporaryUrl($thumbnailPath, now()->addMinutes(30));
    }
}