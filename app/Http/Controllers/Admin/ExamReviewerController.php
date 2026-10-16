<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ExamReviewerController extends Controller
{
    // 自分（ログイン中の審査員）にアサインされた申請の一覧
    // [ReviewerControllerとの違い] 単位数・学会参加回数の自動集計は無い
    // （合否は審査員が全書類を見て手動で判定するため、一覧では件数のみ表示する）
    public function index(Request $request)
    {
        $adminId = Auth::guard('admin')->id();

        $sortableColumns = ['applied_at', 'reviewer_assigned_at'];
        $sortBy = in_array($request->sort_by, $sortableColumns) ? $request->sort_by : 'applied_at';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';

        $exams = Exam::with(['member'])
            ->where('reviewer_admin_id', $adminId)
            ->where('status', 'pending')
            ->whereIn('reviewer_judgment', ['unreviewed', 're_review'])
            ->orderBy($sortBy, $sortDir)
            ->paginate($request->per_page ?? 20);

        $exams->getCollection()->transform(function (Exam $exam) {
            $exam->loadCount(['documents', 'caseReports']);
            return $exam;
        });

        return inertia('Admin/ExamReviewer/Index', [
            'exams' => $exams,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    // アサインされた1申請の書類一式（証明書・推薦状・自験例報告書）を閲覧して判定する画面
    public function show(Request $request, Exam $exam)
    {
        $adminId = Auth::guard('admin')->id();

        abort_unless($exam->reviewer_admin_id === $adminId, 403, 'この申請の担当審査員ではありません。');

        $exam->load(['member', 'caseReports']);

        $documents = $exam->documents()
            ->get()
            ->map(function ($doc) {
                return array_merge($doc->toArray(), [
                    'type_label' => $doc->type_label,
                    'thumbnail_url' => $this->thumbnailUrl($doc->thumbnail_path),
                    'is_image' => in_array(strtolower(pathinfo($doc->file_path ?? '', PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']),
                ]);
            });

        $flaggedItems = $exam->chief_flagged_items ?? [];
        $flaggedCaseReportIds = collect($flaggedItems)
            ->where('kind', 'case_report')
            ->pluck('id')
            ->all();

        $caseReports = $exam->caseReports->map(function ($cr) use ($flaggedCaseReportIds) {
            $data = $cr->toArray();
            $data['chief_flagged'] = in_array($cr->id, $flaggedCaseReportIds);
            return $data;
        });

        $exam->reviewer_judgment = $exam->reviewer_judgment ?? 'unreviewed';

        return inertia('Admin/ExamReviewer/Show', [
            'exam' => $exam,
            'member' => $exam->member,
            'documents' => $documents,
            'caseReports' => $caseReports,
        ]);
    }

    // 添付書類（証明書・推薦状）本体プレビュー
    public function view(Request $request, $id)
    {
        $document = \App\Models\ExamDocument::findOrFail($id);
        $this->authorizeForDocument($document);

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
        $this->authorizeForDocument($document);

        if (!$document->thumbnail_path || !$this->disk()->exists($document->thumbnail_path)) {
            abort(404);
        }

        return redirect(
            $this->disk()->temporaryUrl($document->thumbnail_path, now()->addMinutes(10))
        );
    }

    /**
     * 審査員による合格/不合格の判定を確定する。
     * [ReviewerControllerとの違い] 単位数・参加回数の基準チェックは無く、
     * 審査員が書類一式（証明書・推薦状・自験例報告書）を見て手動で判定するだけ。
     * サーバー側での妥当性チェックは行わない（人による審査そのものが根拠のため）。
     */
    public function judge(Request $request, Exam $exam)
    {
        $adminId = Auth::guard('admin')->id();

        abort_unless($exam->reviewer_admin_id === $adminId, 403, 'この申請の担当審査員ではありません。');

        $request->validate([
            'judgment' => 'required|in:pass,fail',
            'message' => 'nullable|string|max:1000',
        ]);

        // [ReviewerControllerと同様] statusの変更は審査員には行わせない（委員長の承認操作で確定する）
        $exam->reviewer_judgment = $request->judgment;
        $exam->reviewer_judged_at = now();
        $exam->reviewer_response_message = $request->message ?: null;
        $exam->save();

        return redirect()->route('admin.examReviewer.index')
            ->with('success', $request->judgment === 'pass' ? '合格と判定しました。' : '不合格と判定しました。');
    }

    // その書類が、ログイン中審査員にアサインされた申請に属するかチェック
    private function authorizeForDocument(\App\Models\ExamDocument $document): void
    {
        $adminId = Auth::guard('admin')->id();

        $belongs = Exam::where('id', $document->exam_id)
            ->where('reviewer_admin_id', $adminId)
            ->exists();

        abort_unless($belongs, 403, 'この書類の担当審査員ではありません。');
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
