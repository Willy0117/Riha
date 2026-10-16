<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

use App\Models\Exam;
use App\Models\ExamDocument;
use App\Models\CaseReport;
use App\Models\InstructorCycle;
use App\Models\Member;
use App\Models\Invoice;

use App\Services\FileService;

class ExamController extends Controller
{
    // ──────────────────────────────────────────
    // 一覧兼メイン画面
    // [今回全面修正] PdfUploads/Create.vue と同じ構成に合わせる。
    // 証明書・推薦状・症例報告は、この画面を開いた時点で用意される「下書き（draft）」の
    // Exam に対して、それぞれ個別に保存されていく。全て揃って初めて
    // 「受験申込を行う」（status: draft → submitted）を押せる。
    // ──────────────────────────────────────────
    public function index()
    {
        $member = Auth::user()->member;

        $currentCycle = InstructorCycle::currentlyRecruiting();
        $eligibility = $this->checkEligibility($member);

        $draftExam = null;

        // 募集中かつ受験資格を満たしていれば、下書きのApplicationを用意する（無ければ作る）
        if ($currentCycle && $eligibility['all_ok']) {
            $draftExam = Exam::firstOrCreate(
                [
                    'member_id' => $member->id,
                    'exam_round' => $currentCycle->exam_round,
                ],
                [
                    'route' => 'A',
                    'last_name' => $member->last_name,
                    'first_name' => $member->first_name,
                    'last_name_kana' => $member->last_name_kana,
                    'first_name_kana' => $member->first_name_kana,
                    'birthdate' => $member->birthdate,
                    'gender' => $member->gender,
                    'occupation' => null,
                    'workplace' => null,
                    'email' => $member->email,
                    'tel' => $member->tel,
                    'member_code_snapshot' => $member->code,
                    'member_joined_at_snapshot' => $this->joinedAtSnapshot($member),
                    'status' => 'draft',
                ]
            );

            if ($draftExam->wasRecentlyCreated) {
                $this->ensureCaseReportRows($draftExam);
            }

            $draftExam->load(['documents', 'caseReports']);
        }

        // 過去（今回以外）の申請履歴
        $pastExams = Exam::with(['instructorCycle'])
            ->where('member_id', $member->id)
            ->when($draftExam, fn ($q) => $q->where('id', '!=', $draftExam->id))
            ->orderByDesc('created_at')
            ->get();

        // [今回追加] 年会費納入状況ダイアログ用（Create.vueのfeesと同じ形）
        $fees = Invoice::where('member_id', $member->id)
            ->where('annual_fee', '>', 0)
            ->orderBy('fiscal_year')
            ->get(['id', 'fiscal_year', 'annual_fee', 'payment_amount', 'status']);

        return Inertia::render('Exams/Index', [
            'member' => $member,
            'currentCycle' => $currentCycle,
            'eligibility' => $eligibility,
            'exam' => $draftExam,
            'pastExams' => $pastExams,
            'fees' => $fees,
        ]);
    }

    /**
     * [今回追加] 「主演者・座長経験の有無」を、ページ上でクリックした瞬間に即保存する。
     * 申請者情報ダイアログを開かなくても切り替えられるようにするための軽量アクション。
     * route以外のバリデーションは行わない（他の項目が未入力でも切り替え可能）。
     */
    public function updateRoute(Request $request, Exam $exam)
    {
        $this->authorizeOwnDraft($exam);

        $data = $request->validate([
            'route' => ['required', Rule::in(['A', 'B'])],
        ]);

        $exam->update($data);

        return back()->with('success', '選択を保存しました。');
    }

    // ──────────────────────────────────────────
    // 証明書・推薦状の個別アップロード
    // [PdfUploadControllerと同じ考え方] 1ファイルごとにその場でアップロード・保存する。
    // 同じtypeが既にあれば置き換える（再アップロード＝差し替え）。
    // ──────────────────────────────────────────
    public function uploadDocument(Request $request, Exam $exam, FileService $fileService)
    {
        $this->authorizeOwnDraft($exam);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['certificate', 'recome1', 'recome2'])],
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        [$filePath, $thumbnailPath] = $fileService->storeUploadedFile(
            $request->file('file'),
            'exam_documents'
        );

        // 差し替え：同じtypeの既存レコードがあれば削除してから作り直す
        $exam->documents()->where('type', $validated['type'])->delete();

        $document = ExamDocument::create([
            'exam_id' => $exam->id,
            'type' => $validated['type'],
            'file_path' => $filePath,
            'thumbnail_path' => $thumbnailPath,
        ]);

        return back()->with('success', 'アップロードしました。');
    }

    public function deleteDocument(Exam $exam, ExamDocument $document)
    {
        $this->authorizeOwnDraft($exam);
        abort_unless($document->exam_id === $exam->id, 403);

        $document->delete();

        return back()->with('success', '削除しました。');
    }

    // ──────────────────────────────────────────
    // 症例報告1件の保存（No.1〜10、カード単位でその場保存）
    // ──────────────────────────────────────────
    public function updateCaseReport(Request $request, Exam $exam, CaseReport $caseReport)
    {
        $this->authorizeOwnDraft($exam);
        abort_unless($caseReport->exam_id === $exam->id, 403);

        $data = $request->validate([
            'facility' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0|max:150',
            'gender' => 'nullable|in:male,female,other',
            'visit_type' => 'nullable|in:outpatient,inpatient',
            'diagnosis' => 'nullable|string|max:255',
            'current_history' => 'nullable|string',
            'past_history' => 'nullable|string',
            'rehab' => 'nullable|string',
            'future_plan' => 'nullable|string',
            'patient_profile' => 'nullable|string',
            'examination_findings' => 'nullable|string',
            'rehab_and_patient_education' => 'nullable|string',
            'discussion' => 'nullable|string',
        ]);

        if (!$caseReport->is_detailed) {
            unset(
                $data['patient_profile'],
                $data['examination_findings'],
                $data['rehab_and_patient_education'],
                $data['discussion']
            );
        }

        $caseReport->update($data);

        return back()->with('success', "症例No.{$caseReport->case_no} を保存しました。");
    }

    // ──────────────────────────────────────────
    // 受験申込の最終提出（status: draft → submitted）
    // ここでは新たなデータは受け取らない。既に個別に保存済みの内容が揃っているかだけ確認する。
    // ──────────────────────────────────────────
    public function submit(Request $request, Exam $exam)
    {
        $this->authorizeOwnDraft($exam);

        $exam->load('documents', 'caseReports');
        $missing = $this->missingRequirements($exam);

        abort_if(!empty($missing), 422, '必要な項目が揃っていません：' . implode('、', $missing));

        $exam->status = 'submitted';
        $exam->applied_at = now();
        $exam->save();

        return redirect()->route('exams.index')
            ->with('success', '受験申込を受け付けました。事務局からの連絡をお待ちください。');
    }

    public function show(Exam $exam)
    {
        $member = Auth::user()->member;
        abort_unless($exam->member_id === $member->id, 403);

        $exam->load(['documents', 'caseReports', 'instructorCycle']);

        return Inertia::render('Exams/Show', [
            'exam' => $exam,
        ]);
    }

    /**
     * [今回追加] 先生本人が、自分がアップロードした添付書類（証明書・推薦状）を閲覧する。
     * 管理画面側（Admin\Exam*Controller）には同様のview()があったが、My Page側に抜けていた。
     */
    public function viewDocument(ExamDocument $document)
    {
        $member = Auth::user()->member;
        abort_unless($document->exam && $document->exam->member_id === $member->id, 403);

        $disk = Storage::disk(config('filesystems.default'));

        if (!$document->file_path || !$disk->exists($document->file_path)) {
            abort(404);
        }

        return redirect($disk->temporaryUrl($document->file_path, now()->addMinutes(10)));
    }

    // ──────────────────────────────────────────
    // Private
    // ──────────────────────────────────────────

    private function authorizeOwnDraft(Exam $exam): void
    {
        $member = Auth::user()->member;
        abort_unless($exam->member_id === $member->id, 403);
        abort_unless($exam->status === 'draft', 422, '提出済みの申請は編集できません。');
    }

    // 下書き作成時、10件の空の症例報告レコードを先に用意しておく
    // （route=Bで確定した場合にだけ入力必須になるが、レコード自体はroute確定前から存在させ、
    //   保存のたびに作り直さず「同じ10件を編集していく」形にする）
    private function ensureCaseReportRows(Exam $exam): void
    {
        for ($caseNo = 1; $caseNo <= 10; $caseNo++) {
            CaseReport::firstOrCreate(
                ['exam_id' => $exam->id, 'case_no' => $caseNo],
                ['is_detailed' => false]
            );
        }
    }

    /**
     * [今回追加] 様式5対象（詳細記載する症例）の選択・解除。
     * 募集要項「10症例のうち2症例を選んで」の通り、先生が任意の2件を選ぶ。
     * 上限（CaseReport::DETAILED_LIMIT = 2）を超える選択は拒否する。
     */
    public function toggleDetailed(Request $request, Exam $exam, CaseReport $caseReport)
    {
        $this->authorizeOwnDraft($exam);
        abort_unless($caseReport->exam_id === $exam->id, 403);

        if (!$caseReport->is_detailed) {
            $currentCount = $exam->caseReports()->where('is_detailed', true)->count();

            abort_if(
                $currentCount >= CaseReport::DETAILED_LIMIT,
                422,
                '様式5の詳細記載は' . CaseReport::DETAILED_LIMIT . '症例までです。他の症例の選択を解除してから選んでください。'
            );

            $caseReport->is_detailed = true;
        } else {
            $caseReport->is_detailed = false;
            // 選択解除時は様式5の入力内容もクリアする
            $caseReport->patient_profile = null;
            $caseReport->examination_findings = null;
            $caseReport->rehab_and_patient_education = null;
            $caseReport->discussion = null;
        }

        $caseReport->save();

        return back()->with('success', $caseReport->is_detailed ? '様式5の対象に選択しました。' : '様式5の対象から外しました。');
    }

    // 提出前チェック：ルートに応じて必要な書類・症例報告が揃っているか
    private function missingRequirements(Exam $exam): array
    {
        $missing = [];

        // [今回修正] 申請者情報はMemberプロフィールから自動スナップショットするだけで、
        // 個別編集・必須チェックは行わない（member_idが分かっていれば足りるため）。

        $types = $exam->documents->pluck('type')->all();

        if ($exam->route === 'A') {
            if (!in_array('certificate', $types)) $missing[] = '様式2（証明書）';
            if (!in_array('recome1', $types)) $missing[] = '様式3-1（推薦状）';
        } else {
            if (!in_array('recome2', $types)) $missing[] = '様式3-2（推薦状）';

            $basicIncomplete = $exam->caseReports->contains(fn (CaseReport $cr) => !$cr->facility || !$cr->diagnosis);
            if ($basicIncomplete) $missing[] = '自験例報告書10症例（未入力の症例があります）';

            $detailedReports = $exam->caseReports->where('is_detailed', true);
            if ($detailedReports->count() !== CaseReport::DETAILED_LIMIT) {
                $missing[] = '様式5対象の症例を' . CaseReport::DETAILED_LIMIT . '件選択';
            } elseif ($detailedReports->contains(fn (CaseReport $cr) => !$cr->patient_profile || !$cr->discussion)) {
                $missing[] = '様式5（選択した症例の詳細記載）';
            }
        }

        return $missing;
    }

    /**
     * [今回修正] invoicesベースの逆算（firstFiscalYear）は暫定対応だったため廃止。
     * MemberImport.phpがT列（入会年度）をjoined_atに正式に反映するようになったため、
     * joined_atを直接、年度（12/1〜翌11/30）単位で比較する。
     */
    private function joinedAtSnapshot(Member $member): ?string
    {
        return $member->joined_at ? \Carbon\Carbon::parse($member->joined_at)->format('Y-m-d') : null;
    }

    private function joinedFiscalYear(Member $member): ?int
    {
        if (!$member->joined_at) {
            return null;
        }

        $joinedAt = \Carbon\Carbon::parse($member->joined_at);
        return $joinedAt->month === 12 ? $joinedAt->year : $joinedAt->year - 1;
    }

    private function checkEligibility(Member $member): array
    {
        // [今回追加] 既に指導士資格を持っている（＝instructor_update_cyclesに1件でもレコードがある）
        // 会員は、そもそも「新規」申請の対象外。最優先でブロックする。
        $alreadyInstructor = $member->updateCycles()->exists();

        // [今回修正] 会員要件を「2条件」として個別に○×表示できる構造に変更。
        // 退会済みは別枠のブロッキングチェックとして扱う（条件チェックリストには含めない）。
        $isWithdrawn = $member->status_id === Member::STATUS_WITHDRAWN;

        $joinedFiscalYear = $this->joinedFiscalYear($member);
        $today = now();
        $currentFiscalYear = $today->month === 12 ? $today->year : $today->year - 1;
        $tenureOk = $joinedFiscalYear && ($currentFiscalYear - $joinedFiscalYear) >= 2;

        $feePaid = method_exists($member, 'isAnnualFeePaid') && $member->isAnnualFeePaid();

        $yearsSinceJoined = $joinedFiscalYear ? ($currentFiscalYear - $joinedFiscalYear) : null;

        $conditions = [
            [
                'key' => 'tenure',
                'label' => '入会から2年度以上継続',
                'met' => $tenureOk,
                'joined_fiscal_year' => $joinedFiscalYear,
                'years_since_joined' => $yearsSinceJoined,
            ],
            [
                'key' => 'fee',
                'label' => '年会費完納',
                'met' => $feePaid,
            ],
        ];

        $errors = [];
        if ($alreadyInstructor) {
            $errors[] = '既に指導士資格をお持ちのため、新規申請はできません。';
        }
        if ($isWithdrawn) {
            $errors[] = '退会済みのため申請できません。';
        }
        if (!$tenureOk) {
            $errors[] = '入会から直近2年度以上継続して正会員である必要があります。';
        }
        if (!$feePaid) {
            $errors[] = '年会費が未納のため申請できません。';
        }

        return [
            'all_ok' => !$alreadyInstructor && !$isWithdrawn && $tenureOk && $feePaid,
            'already_instructor' => $alreadyInstructor,
            'is_withdrawn' => $isWithdrawn,
            'conditions' => $conditions,
            'errors' => $errors,
            'message' => implode(' ', $errors),
        ];
    }
}
