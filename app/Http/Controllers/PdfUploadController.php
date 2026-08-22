<?php

namespace App\Http\Controllers;

use App\Models\PdfUpload;
use App\Models\CreditCategory;
use App\Models\CreditConference;
use App\Models\CreditRolePoint;
use App\Models\Member;
use App\Models\Invoice;//AnnualFee;
use App\Models\ApplicationSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use App\Services\FileService;
use App\Services\PdfService;

class PdfUploadController extends Controller
{
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

    /**
     * 現在の会員の更新サイクルに対応する ApplicationSchedule（期区分）を取得する。
     * 判定順序：
     * 1. 現在日時が cycle.renewal_start_date 〜 renewal_end_date（更新申請受付期間）内であること
     * 2. 1を満たす場合のみ、現在日時が schedule.application_start 〜 application_end 内にある行を検索
     * どちらかを満たさない場合は null（画面側は該当カードを表示しない）。
     */
    private function currentSchedule($cycle): ?ApplicationSchedule
    {
        if (!$cycle || !$cycle->renewal_start_date || !$cycle->renewal_end_date) {
            return null;
        }

        $now = now();

        $isWithinRenewalPeriod = $now->gte(\Carbon\Carbon::parse($cycle->renewal_start_date))
            && $now->lte(\Carbon\Carbon::parse($cycle->renewal_end_date));

        if (!$isWithinRenewalPeriod) {
            return null;
        }

        return ApplicationSchedule::whereDate('application_start', '<=', $now->toDateString())
            ->whereDate('application_end', '>=', $now->toDateString())
            ->first();
    }

    public function index()
    {
        $user = Auth::user();

        $cycle = $user->member?->latestCycle;

        $uploads = collect();

        if ($cycle) {

            $uploads = PdfUpload::with([
                    'creditCategory',
                    'creditConference',
                    'creditRole.creditRole',
                ])
                ->where('member_id', $user->member_id)
                ->latest()
                ->get()
                ->map(fn($u) => [
                    'id' => $u->id,
                    'credit_category_name' => $u->creditCategory?->name,
                    'credit_conference_name' => $u->creditConference?->name,
                    'role_name' => $u->creditRole?->creditRole?->name,
                    'points' => $u->points,
                    'status' => $u->status,
                    'session' => $u->session,

                    'thumbnail_url' => $this->thumbnailUrl($u->thumbnail_path),

                    'rejection_message' => $u->rejection_message,
                ]);
        }

        $creditCategories = CreditCategory::all();

        // 全学術集会・論文・セミナー等（どの区分で使えるかを credit_role_points から動的算出して付与）
        $conferences = CreditConference::all()->map(function ($conf) {
            return [
                'id' => $conf->id,
                'name' => $conf->name,
                'available_category_ids' => CreditRolePoint::where('credit_conference_id', $conf->id)
                    ->pluck('credit_category_id')
                    ->unique()
                    ->values(),
            ];
        });

        // 全 roles
        $roles = CreditRolePoint::with('creditRole')->get()->map(fn($r) => [
            'id' => $r->id,
            'name' => $r->creditRole?->name,
            'points' => $r->points,
            'credit_category_id' => $r->credit_category_id,
            'credit_conference_id' => $r->credit_conference_id,
            'requires_session' => $r->requires_session,
        ]);

        return inertia('PdfUploads/Index', [
            'member' => $user->member,
            'uploads' => $uploads,
            'cycle' => $cycle,
            'creditCategories' => $creditCategories,
            'conferences' => $conferences,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request, FileService $fileService)
    {
        $request->validate([
            'file' => 'required|mimes:pdf,png,jpeg,jpg|max:10240',
            'credit_category_id' => 'required|exists:credit_categories,id',
            'credit_conference_id' => 'required|exists:credit_conferences,id',
            'role_id' => 'required|exists:credit_role_points,id',
            'session' => [
                Rule::requiredIf(function () use ($request) {
                    $role = CreditRolePoint::find($request->role_id);
                    return $role && $role->requires_session;
                }),
                'nullable',
                'string',
                'max:50',
            ],
            'issued_date' => 'required|date',
        ]);

        $user = Auth::user();

        try {
            [$file_path, $thumbnail_path] = $fileService->storeUploadedFile(
                $request->file('file'),
                'pdf_uploads'
            );

            $role = CreditRolePoint::find($request->role_id);
            $conference = CreditConference::find($request->credit_conference_id);
            $points = $role ? $role->points : 0;

            $verificationResult = $this->verifyPdfWithGroq(
                $request->file('file'),
                [
                    'date'        => $request->issued_date,
                    'conference'  => $conference?->name,
                    'role'        => $role?->creditRole?->name,
                    'member_name' => $user->name,
                ]
            );

            PdfUpload::create([
                'member_id'           => $user->member_id,
                'file_path'           => $file_path,
                'thumbnail_path'      => $thumbnail_path,
                'credit_category_id'  => $request->credit_category_id,
                'credit_conference_id'=> $request->credit_conference_id,
                'credit_role_id'      => $request->role_id,
                'session'             => $request->session ?? '',
                'points'              => $points,
                'issued_date'         => $request->issued_date,
                'status'              => 'pending',
                'ai_verification'     => json_encode($verificationResult),
            ]);

            $warnings = [];
            if (!($verificationResult['date_match'] ?? true)) {
                $warnings[] = "日付が一致していません。PDFには「{$verificationResult['pdf_date']}」と記載されています。";
            }
            if (!($verificationResult['conference_match'] ?? true)) {
                $warnings[] = "学会名が一致していません。PDFには「{$verificationResult['pdf_conference']}」と記載されています。";
            }
            if (!($verificationResult['role_match'] ?? true)) {
                $warnings[] = "参加種別が一致していません。PDFには「{$verificationResult['pdf_role']}」と記載されています。";
            }
            if (!($verificationResult['name_match'] ?? true)) {
                $warnings[] = "氏名が一致していません。PDFには「{$verificationResult['pdf_name']}」と記載されています。";
            }

            return back()->with([
                'success'  => __('PDF uploaded successfully.'),
                'warnings' => $warnings,
            ]);

        } catch (\Exception $e) {
            \Log::error('Upload failed: ' . $e->getMessage());
            return back()->with('error', __('PDF upload failed.'));
        }
    }


    private function verifyPdfWithGroq($file, array $inputData): array
    {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($file->path());
        $pdfText = $pdf->getText();

        \Log::info('PDF text: ' . $pdfText);

        $prompt = <<<EOT
    以下のPDFテキストから情報を抽出し、入力データと照合してください。
    結果はJSONのみで返してください。余分なテキストは不要です。

    PDFテキスト：
    {$pdfText}

    入力データ：
    - 日付: {$inputData['date']}（PDFの日付がこの日付を含んでいればOK）
    - 学会名: {$inputData['conference']}
    - 参加種別: {$inputData['role']}
    - 氏名: {$inputData['member_name']}（スペースは無視して照合してください）

    以下のJSON形式で返してください：
    {
    "date_match": true/false,
    "conference_match": true/false,
    "role_match": true/false,
    "name_match": true/false,
    "pdf_date": "PDFに記載の日付",
    "pdf_conference": "PDFに記載の学会名",
    "pdf_role": "PDFに記載の参加種別",
    "pdf_name": "PDFに記載の氏名",
    "notes": "特記事項があれば"
    }
    EOT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
                'Content-Type'  => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'    => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
            ]);

            \Log::info('Groq response: ' . json_encode($response->json()));

            $content = $response->json('choices.0.message.content');
            $content = preg_replace('/```json|```/', '', $content);

            return json_decode(trim($content), true) ?? ['error' => 'parse failed'];

        } catch (\Exception $e) {
            \Log::error('Groq API error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }



    private function verifyPdfWithGemini($file, array $inputData): array
    {
        $base64 = base64_encode(file_get_contents($file->path()));
        $mimeType = $file->getMimeType();

        $prompt = <<<EOT
    このファイルから以下の情報を抽出し、入力データと照合してください。
    結果はJSON形式のみで返してください。余分なテキストは不要です。

    入力データ：
    - 日付: {$inputData['date']}
    - 学会名: {$inputData['conference']}
    - 参加種別: {$inputData['role']}
    - 氏名: {$inputData['member_name']}

    以下のJSON形式で返してください：
    {
    "date_match": true/false,
    "conference_match": true/false,
    "role_match": true/false,
    "name_match": true/false,
    "pdf_date": "ファイルに記載の日付",
    "pdf_conference": "ファイルに記載の学会名",
    "pdf_role": "ファイルに記載の参加種別",
    "pdf_name": "ファイルに記載の氏名",
    "notes": "特記事項があれば"
    }
    EOT;

        try {
            $response = Http::post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . env('GEMINI_API_KEY'),
                [
                    'contents' => [[
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data'      => $base64,
                                ],
                            ],
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ]],
                ]
            );

            $content = $response->json('candidates.0.content.parts.0.text');
            \Log::info('Gemini response: ' . $content); 
            $content = preg_replace('/```json|```/', '', $content);

            return json_decode(trim($content), true) ?? ['error' => 'parse failed'];

        } catch (\Exception $e) {
            \Log::error('Gemini API error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    public function create()
    {
        $user = Auth::user();

        $cycle = $user->member?->latestCycle;

        $uploads = collect();

        if ($cycle) {
            $uploads = PdfUpload::with([
                    'creditCategory',
                    'creditConference',
                    'creditRole.creditRole',
                ])
                ->where('member_id', $user->member_id)
                ->latest()
                ->get()
                ->map(function ($u) use ($cycle) {
                    $issuedDate = $u->issued_date ? \Carbon\Carbon::parse($u->issued_date) : null;

                    $isWithinPeriod = $issuedDate
                        && $issuedDate->gte(\Carbon\Carbon::parse($cycle->start_date))
                        && $issuedDate->lte(\Carbon\Carbon::parse($cycle->end_date));

                    return [
                        'id' => $u->id,
                        'credit_category_name' => $u->creditCategory?->name,
                        'credit_conference_name' => $u->creditConference?->name,
                        'role_name' => $u->creditRole?->creditRole?->name,
                        'points' => $u->points,
                        'status' => $u->status,
                        'session' => $u->session,
                        'issued_date' => $issuedDate?->format('Y-m-d'),
                        'updated_at' => $u->updated_at,
                        'is_within_period' => $isWithinPeriod,

                        'thumbnail_url' => $this->thumbnailUrl($u->thumbnail_path),

                        'rejection_message' => $u->rejection_message,
                    ];
                });
        }

        // 単位集計・学会参加カウントは、認定期間内（issued_date基準）の書類のみを対象にする
        $uploadsWithinPeriod = $uploads->where('is_within_period', true);

        $approvedTotal = $uploadsWithinPeriod
            ->where('status', 'approved')
            ->sum('points');

        $pendingTotal = $uploadsWithinPeriod
            ->where('status', 'pending')
            ->sum('points');

        $total = $uploadsWithinPeriod->sum('points');

        // 更新申請の審査中（cycle.status === 'pending'）は、cycle.updated_at（申請した瞬間）を基準に判定する。
        $isCycleUnderReview = $cycle?->status === 'pending';
        $appliedAt = $cycle?->updated_at;

        $uploads = $uploads->map(function ($u) use ($isCycleUnderReview, $appliedAt) {
            if (!$u['is_within_period']) {
                $u['status'] = 'out_of_period';
                $u['rejection_message'] = null;
                return $u;
            }

            if ($isCycleUnderReview) {
                $updatedAt = $u['updated_at'] ?? null;

                $changedAfterApplied = $appliedAt && $updatedAt
                    && \Carbon\Carbon::parse($updatedAt)->gt($appliedAt);

                if ($u['status'] === 'pending' || $changedAfterApplied) {
                    $u['status'] = 'under_review';
                    $u['rejection_message'] = null;
                }
            }

            return $u;
        });

        // 画面には不要なので、内部用フィールドはレスポンスから外す
        $uploads = $uploads->map(fn($u) => \Illuminate\Support\Arr::except($u, ['updated_at', 'is_within_period']));

        $fees = Invoice::where('member_id', $user->member_id)
            ->whereBetween('fiscal_year', [
                date('Y', strtotime($cycle->start_date)),
                date('Y', strtotime($cycle->end_date)),
            ])
            ->get();

        $annualFeeStatus = $fees
            ->filter(fn($f) => $f->annual_fee > 0)
            ->every(fn($f) => $f->status === 'paid');

        $totalFee = $fees->sum(fn($f) => $f->annual_fee + $f->renewal_fee);
        $totalPaid = $fees->sum('payment_amount');

        $isFeeOk = $totalFee <= $totalPaid;

        $conferenceUploadsQuery = PdfUpload::where('member_id', $user->member_id)
            ->whereBetween('issued_date', [$cycle->start_date, $cycle->end_date])
            ->whereHas('creditCategory', fn ($q) =>
                $q->where('name', '学術集会')
            )
            ->whereHas('creditConference', fn ($q) =>
                $q->where('name', '日本腎臓リハビリテーション学会')
            )
            ->whereHas('creditRole', fn ($q) =>
                $q->whereHas('creditRole', fn ($q2) => $q2->where('name', '参加'))
            );

        $conference_count = (clone $conferenceUploadsQuery)->count();
        $approvedConferenceCount = (clone $conferenceUploadsQuery)->where('status', 'approved')->count();
        $pendingConferenceCount = (clone $conferenceUploadsQuery)->where('status', 'pending')->count();

        // 全カテゴリー
        $creditCategories = CreditCategory::all();

        // 全学術集会・論文・セミナー等（どの区分で使えるかを credit_role_points から動的算出して付与）
        $conferences = CreditConference::all()->map(function ($conf) {
            return [
                'id' => $conf->id,
                'name' => $conf->name,
                'available_category_ids' => CreditRolePoint::where('credit_conference_id', $conf->id)
                    ->pluck('credit_category_id')
                    ->unique()
                    ->values(),
            ];
        });

        // 全 roles
        $roles = CreditRolePoint::with('creditRole')->get()->map(fn($r) => [
            'id' => $r->id,
            'name' => $r->creditRole?->name,
            'points' => $r->points,
            'credit_category_id' => $r->credit_category_id,
            'credit_conference_id' => $r->credit_conference_id,
            'requires_session' => $r->requires_session,
        ]);

        // [今回追加] 現在の期区分（ApplicationSchedule）を取得し、応募期間・審査期間（委員長）を画面に渡す
        $schedule = $this->currentSchedule($cycle);

        return inertia('PdfUploads/Create', [
            'member' => $user->member,
            'uploads' => $uploads,
            'cycle' => $cycle,
            'creditCategories' => $creditCategories,
            'conferences' => $conferences,
            'roles' => $roles,
            'approvedTotal' => $approvedTotal,
            'pendingTotal' => $pendingTotal,
            'total' => $total,
            'totalFee' => $totalFee,
            'totalPaid' => $totalPaid,
            'isFeeOk' => $isFeeOk,
            'requiredUnits' => 50,
            'conference_count' => $conference_count,
            'pendingConferenceCount' => $pendingConferenceCount,
            'approvedConferenceCount' => $approvedConferenceCount,
            'fees' => $fees,
            'annualFeeStatus' => $annualFeeStatus,
            'schedule' => $schedule,
        ]);
    }

    /**
     * PDF本体の閲覧。S3署名URLへリダイレクトする（非公開バケット前提）。
     */
    public function view(PdfUpload $pdf)
    {
        abort_unless($pdf->member_id === Auth::user()->member_id, 403);

        if (!$pdf->file_path || !$this->disk()->exists($pdf->file_path)) {
            abort(404);
        }

        $url = $this->disk()->temporaryUrl($pdf->file_path, now()->addMinutes(10));

        return redirect($url);
    }

    /**
     * サムネイルの閲覧。S3署名URLへリダイレクトする（非公開バケット前提）。
     */
    public function thumbnail(PdfUpload $pdf)
    {
        abort_unless($pdf->member_id === Auth::user()->member_id, 403);

        if (!$pdf->thumbnail_path || !$this->disk()->exists($pdf->thumbnail_path)) {
            abort(404);
        }

        $url = $this->disk()->temporaryUrl($pdf->thumbnail_path, now()->addMinutes(10));

        return redirect($url);
    }

    public function destroy(PdfUpload $pdfUpload)
    {
        // 自分の書類以外は削除できないようにする
        abort_unless($pdfUpload->member_id === Auth::user()->member_id, 403);

        // ストレージ上のファイルも一緒に削除
        if ($pdfUpload->file_path) {
            $this->disk()->delete($pdfUpload->file_path);
        }
        if ($pdfUpload->thumbnail_path) {
            $this->disk()->delete($pdfUpload->thumbnail_path);
        }

        $pdfUpload->delete();

        return back()->with('success', '書類を削除しました。');
    }
}
