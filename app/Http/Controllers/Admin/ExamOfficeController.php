<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\InstructorUpdateCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExamOfficeController extends Controller
{
    // ──────────────────────────────────────────
    // 一覧（事務局：全申請を横断的に管理）
    // ──────────────────────────────────────────
    public function index(Request $request)
    {
        $search = $request->search;
        $status = $request->status;
        $route  = $request->route_filter; // 'route' はPHP予約語的に紛らわしいのでクエリキーはroute_filterにする
        $sortBy = $request->input('sort_by', 'applied_at');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = $request->per_page ?? 20;

        $query = Exam::with(['member', 'reviewerAdmin', 'instructorCycle'])
            ->withCount(['documents', 'caseReports']);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('member_code_snapshot', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('member', function ($mq) use ($search) {
                        $mq->where('code', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($route)) {
            $query->where('route', $route);
        }

        $allowedSorts = ['applied_at', 'reviewer_judged_at', 'certified_at', 'status', 'created_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'applied_at';
        }
        $query->orderBy($sortBy, $sortDir);

        $exams = $query->paginate($perPage)->withQueryString();

        return inertia('Admin/ExamOffice/Index', [
            'exams' => $exams,
            'statusLabels' => Exam::STATUS_LABELS,
            'routeLabels' => Exam::ROUTE_LABELS,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'route_filter' => $route,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'per_page' => $perPage,
            ],
        ]);
    }

    // ──────────────────────────────────────────
    // 詳細（申請者情報・提出書類・症例報告・審査履歴・認定期間設定）
    // ──────────────────────────────────────────
    public function show(Request $request, Exam $exam)
    {
        $exam->load(['member', 'reviewerAdmin', 'caseReports', 'instructorCycle']);

        $documents = $exam->documents()
            ->get()
            ->map(function ($doc) {
                return array_merge($doc->toArray(), [
                    'type_label' => $doc->type_label,
                    'thumbnail_url' => $this->thumbnailUrl($doc->thumbnail_path),
                    'is_image' => in_array(strtolower(pathinfo($doc->file_path ?? '', PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']),
                ]);
            });

        // 既にこの会員が指導士サイクルを持っているか（再受験等で二重に認定されることを防ぐための目安表示）
        $existingCycle = InstructorUpdateCycle::where('member_id', $exam->member_id)->exists();

        return inertia('Admin/ExamOffice/Show', [
            'exam' => array_merge($exam->toArray(), [
                'status_label' => $exam->status_label,
                'route_label' => $exam->route_label,
            ]),
            'member' => $exam->member,
            'documents' => $documents,
            'caseReports' => $exam->caseReports,
            'existingCycle' => $existingCycle,
            'filters' => $request->only(['search', 'status', 'route_filter', 'per_page', 'sort_by', 'sort_dir', 'page']),
        ]);
    }

    // 添付書類本体プレビュー（事務局は閲覧専用でアクセス可）
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
     * [今回追加] CBT試験本番の結果を事務局が登録する。
     * 対象は status = 'approved'（受験資格あり）の申請のみ。
     * 試験自体は学会システム外（CBTベンダー）で実施されるため、結果はここで事務局が手入力する。
     */
    public function recordExamResult(Request $request, Exam $exam)
    {
        abort_unless($exam->status === 'approved', 422, '受験資格ありの申請のみ、試験結果を登録できます。');

        $validated = $request->validate([
            'exam_result' => 'required|in:pass,fail',
        ]);

        $exam->exam_result = $validated['exam_result'];
        $exam->exam_result_recorded_at = now();
        $exam->save();

        return redirect()->back()->with('success', '試験結果を登録しました。');
    }

    /**
     * 「新規認定期間の設定」。更新フローの InstructorMemberController::bulkUpdate() に相当する、
     * 新規版の事務局アクション。
     *
     * - 対象は status = 'approved'（委員長の最終承認済み）の申請のみ。
     * - 認定期間は事務局が個別入力するのではなく、instructor_cycles（第N回ごとの
     *   正しい認定期間マスター）から exam.exam_round で引く。
     *   同じ回に合格した先生は全員同じ認定期間になる。
     * - この会員にとって初めての InstructorUpdateCycle を新規作成する。
     * - renewal_start_date/renewal_end_date は、instructor_cycles.end_date と同じ年の
     *   4/1〜12/1（bulkUpdate() と同じ考え方）。
     * - exam.status を certified に変更し、certified_at を記録する。
     */
    public function certify(Request $request, Exam $exam)
    {
        abort_unless($exam->status === 'approved', 422, '委員長承認済み（受験資格あり）の申請のみ、認定期間を設定できます。');

        // [今回追加] 試験本番（CBT）で合格していなければ認定できない
        abort_unless($exam->exam_result === 'pass', 422, '試験結果が「合格」として登録されていない申請は、認定期間を設定できません。');

        // [今回追加] 受験料（15,000円）の入金確認。Member::isAnnualFeePaid() と同じ考え方で、
        // この申請に紐づく exam_fee の請求が全て支払済みであることを確認する。
        // [今回修正] invoices.fiscal_year は「12/1〜翌11/30」を1年度とする定義のため、
        // applied_at（申請日）から該当年度を逆算し、member_id + fiscal_year で
        // この申請に対応する受験料請求だけに絞り込む（再受験による複数請求の混同を防ぐ）。
        $appliedAt = \Carbon\Carbon::parse($exam->applied_at);
        $fiscalYear = $appliedAt->month === 12 ? $appliedAt->year : $appliedAt->year - 1;

        $examInvoice = \App\Models\Invoice::where('member_id', $exam->member_id)
            ->where('fiscal_year', $fiscalYear)
            ->where('exam_fee', '>', 0)
            ->first();

        abort_if(!$examInvoice, 422, '受験料の請求が作成されていません。先に請求書を発行してください。');
        abort_unless($examInvoice->status === 'paid', 422, '受験料が未納のため、認定期間を設定できません。');

        $cycle = \App\Models\InstructorCycle::where('exam_round', $exam->exam_round)->first();

        abort_unless($cycle, 422, "第{$exam->exam_round}回の認定期間が instructor_cycles に登録されていません。先に事務局でマスターを登録してください。");

        DB::transaction(function () use ($exam, $cycle) {
            $renewalYear = $cycle->end_date->year;

            InstructorUpdateCycle::create([
                'member_id' => $exam->member_id,
                'start_date' => $cycle->start_date,
                'end_date' => $cycle->end_date,
                'renewal_start_date' => \Carbon\Carbon::create($renewalYear, 4, 1),
                'renewal_end_date' => \Carbon\Carbon::create($renewalYear, 12, 1),
                'status' => 'before_update',
                'reviewer_judgment' => 'unreviewed',
            ]);

            $exam->status = 'certified';
            $exam->certified_at = now();
            $exam->save();
        });

        return redirect()->route('admin.examOffice.index')
            ->with('success', '認定期間を設定し、指導士として登録しました。');
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
