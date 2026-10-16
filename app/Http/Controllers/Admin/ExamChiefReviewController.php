<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExamChiefReviewController extends Controller
{
    // 審査員の合格/不合格判定が済み、委員長の最終判定待ちの申請一覧
    // [ChiefReviewControllerとの違い] 書類集計（承認済み単位数・参加回数）が無いため、
    // 一覧取得後の加工処理が不要。ソート・ページングだけで完結する。
    public function index(Request $request)
    {
        $sortableColumns = ['reviewer_judged_at', 'updated_at'];

        $sortBy = in_array($request->sort_by, $sortableColumns) ? $request->sort_by : 'reviewer_judged_at';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        $perPage = $request->per_page ?? 20;

        $exams = Exam::with(['member', 'reviewerAdmin'])
            ->where('status', 'pending')
            ->whereIn('reviewer_judgment', ['pass', 'fail'])
            ->whereDate('reviewer_judged_at', '<=', now()->toDateString())
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        $exams->getCollection()->transform(function (Exam $exam) {
            $exam->loadCount(['documents', 'caseReports']);
            return $exam;
        });

        return inertia('Admin/ExamChief/Index', [
            'exams' => $exams,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    // 1申請の書類詳細（閲覧専用。承認/差し戻し操作は審査員画面で行う）
    public function show(Exam $exam)
    {
        $exam->load(['member', 'reviewerAdmin', 'caseReports']);

        $documents = $exam->documents()
            ->get()
            ->map(function ($doc) {
                return array_merge($doc->toArray(), [
                    'type_label' => $doc->type_label,
                    'thumbnail_url' => $this->thumbnailUrl($doc->thumbnail_path),
                    'is_image' => in_array(strtolower(pathinfo($doc->file_path ?? '', PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']),
                ]);
            });

        return inertia('Admin/ExamChief/Show', [
            'exam' => $exam,
            'member' => $exam->member,
            'documents' => $documents,
            'caseReports' => $exam->caseReports,
        ]);
    }

    // 添付書類本体プレビュー（閲覧専用のため担当審査員チェックなし）
    public function view(Request $request, $id)
    {
        $document = \App\Models\ExamDocument::findOrFail($id);

        if (!$document->file_path || !$this->disk()->exists($document->file_path)) {
            abort(404);
        }

        return redirect(
            $this->disk()->temporaryUrl($document->file_path, now()->addMinutes(10))
        );
    }

    public function thumbnail(Request $request, $id)
    {
        $document = \App\Models\ExamDocument::findOrFail($id);

        if (!$document->thumbnail_path || !$this->disk()->exists($document->thumbnail_path)) {
            abort(404);
        }

        return redirect(
            $this->disk()->temporaryUrl($document->thumbnail_path, now()->addMinutes(10))
        );
    }

    /**
     * 最終判定を保存する（1件ずつ）。
     * [ChiefReviewControllerと同様] 委員長の操作は「承認する」の1種類のみ。
     * 実際に確定する status は、審査員の判定（reviewer_judgment）に応じて自動的に決まる：
     *   pass → approved / fail → reject
     */
    public function review(Request $request, Exam $exam)
    {
        abort_unless(
            in_array($exam->reviewer_judgment, ['pass', 'fail']),
            422,
            '審査員の判定（合格/不合格）が出ていない申請は承認できません。'
        );

        $exam->status = $exam->reviewer_judgment === 'fail' ? 'reject' : 'approved';
        $exam->save();

        // TODO: [後日実装] status が 'approved'（受験資格あり＝受験許可）になったこのタイミングで、
        // invoices に exam_fee（15,000円）の請求レコードを作成する。
        // fiscal_year は ExamOfficeController::certify() と同じ計算式
        // （applied_at が12月ならその年、それ以外なら前年）に揃えること。
        // 募集要項「支払方法については受験許可の下りた時点でご連絡します」に対応する処理。

        return redirect()->back()->with('success', '承認しました。');
    }

    /**
     * 審査員の判定を「再審査」に戻し、審査員に差し戻す。
     * 理由と、指摘対象の症例報告（case_reports.id の配列、1件以上必須）を受け取る。
     * [ChiefReviewControllerとの違い] 差し戻し対象は pdf_uploads ではなく case_reports。
     * 個別書類のstatusを戻す処理は無い（そもそも個別審査の概念が無いため）。
     */
    public function sendBackToReviewer(Request $request, Exam $exam)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
            'flagged_case_report_ids' => 'required|array|min:1',
            'flagged_case_report_ids.*' => 'integer|exists:case_reports,id',
        ]);

        $exam->reviewer_judgment = 're_review';
        $exam->reviewer_judged_at = null;
        $exam->chief_feedback = $request->reason;
        $exam->chief_flagged_items = collect($request->flagged_case_report_ids)
            ->map(fn ($id) => ['kind' => 'case_report', 'id' => $id])
            ->all();
        // 新しいラウンドなので、前回の審査員からの返信メッセージ・不合格理由はクリアする
        $exam->reviewer_response_message = null;
        $exam->reason = null;
        $exam->save();

        return redirect()->route('admin.examChief.index')
            ->with('success', '審査員に差し戻しました。');
    }

    /**
     * 一覧で選択した複数の申請を、まとめて承認する。
     * 各申請の確定ステータスは、それぞれの審査員判定（pass→approved / fail→reject）に
     * 応じて自動的に決まる。
     */
    public function bulkReview(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:exams,id',
        ]);

        $exams = Exam::whereIn('id', $request->ids)
            ->where('status', 'pending')
            ->whereIn('reviewer_judgment', ['pass', 'fail'])
            ->get();

        foreach ($exams as $exam) {
            $exam->status = $exam->reviewer_judgment === 'fail' ? 'reject' : 'approved';
            $exam->save();
            // TODO: [後日実装] 個別review()と同様、approved時にexam_fee請求書を自動作成する
        }

        return redirect()->back()->with('success', "{$exams->count()}件を承認しました。");
    }

    private function disk()
    {
        return Storage::disk(config('filesystems.default'));
    }

    private function thumbnailUrl(?string $thumbnailPath): ?string
    {
        if (!$thumbnailPath) return null;

        return $this->disk()->temporaryUrl($thumbnailPath, now()->addMinutes(30));
    }
}
