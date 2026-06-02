<?php

namespace App\Http\Controllers;

use App\Models\PdfUpload;
use App\Models\CreditCategory;
use App\Models\CreditConference;
use App\Models\CreditRolePoint;
use App\Models\Member;
use App\Models\AnnualFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Services\FileService;
use App\Services\PdfService;

class PdfUploadController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $cycle = $user->member?->latestCycle;

        $uploads = collect();

        if ($cycle) {

            $uploads = PdfUpload::with([
                    'creditCategory',
                    'creditConference',
                    'creditRole',
                ])
                ->where('member_id', $user->member_id)
                ->latest()
                ->get()
                ->map(fn($u) => [
                    'id' => $u->id,
                    'credit_category_name' => $u->creditCategory?->name,
                    'credit_conference_name' => $u->creditConference?->name,
                    'role_name' => $u->creditRole?->role,
                    'points' => $u->points,
                    'status' => $u->status,
                    'session' => $u->session,

                    'thumbnail_url' => $u->thumbnail_path
                        ? Storage::url($u->thumbnail_path)
                        : null,

                    'rejection_message' => $u->rejection_message,
                ]);
        }

        $creditCategories = CreditCategory::all();

        // 全学術集会・論文・セミナー等
        $conferences = CreditConference::all();

        // 全 roles
        $roles = CreditRolePoint::all()->map(fn($r) => [
            'id' => $r->id,
            'name' => $r->role,
            'points' => $r->points,
            'credit_category_id' => $r->credit_category_id,
            'credit_conference_id' => $r->credit_conference_id,
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
            'session' => 'required|string|max:50',
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
                    'role'        => $role?->role,
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
                    'creditRole',
                ])
                ->where('member_id', $user->member_id)
                ->latest()
                ->get()
                ->map(fn($u) => [
                    'id' => $u->id,
                    'credit_category_name' => $u->creditCategory?->name,
                    'credit_conference_name' => $u->creditConference?->name,
                    'role_name' => $u->creditRole?->role,
                    'points' => $u->points,
                    'status' => $u->status,
                    'session' => $u->session,

                    'thumbnail_url' => $u->thumbnail_path
                        ? Storage::url($u->thumbnail_path)
                        : null,

                    'rejection_message' => $u->rejection_message,
                ]);
        }

        $approvedTotal = $uploads
            ->where('status', 'approved')
            ->sum('points');
            
        $pendingTotal = $uploads
            ->where('status', 'pending')
            ->sum('points');

        $total = $uploads->sum('points');

        $fees = AnnualFee::where('member_id', $user->member_id)
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

        $conference_count = PdfUpload::where('member_id', $user->member_id)
//            ->where('status', 'approved')
            ->whereBetween('created_at', [$cycle->start_date, $cycle->end_date])
            ->whereHas('creditCategory', fn ($q) =>
                $q->where('name', '学術集会')
            )
            ->whereHas('creditConference', fn ($q) =>
                $q->where('name', '日本腎臓リハビリテーション学会')
            )
            ->whereHas('creditRole', fn ($q) =>
                $q->where('role', '参加')
            )
            ->count();

        // 全カテゴリー
        $creditCategories = CreditCategory::all();

        // 全学術集会・論文・セミナー等
        $conferences = CreditConference::all();

        // 全 roles
        $roles = CreditRolePoint::all()->map(fn($r) => [
            'id' => $r->id,
            'name' => $r->role,
            'points' => $r->points,
            'credit_category_id' => $r->credit_category_id,
            'credit_conference_id' => $r->credit_conference_id,
        ]);

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
            'fees' => $fees,
            'annualFeeStatus' => $annualFeeStatus,  
        ]);
    }

    public function view(PdfUpload $pdf)
    {
        $filePath = Storage::path($pdf->file_path);
        if (!file_exists($filePath)) abort(404);
        return response()->file($filePath);
    }

    public function thumbnail(PdfUpload $pdf)
    {
        $thumbPath = Storage::path($pdf->thumbnail_path);
        if (!file_exists($thumbPath)) abort(404);
        return response()->file($thumbPath);
    }
}
