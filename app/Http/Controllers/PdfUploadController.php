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
            'file' => 'required|mimes:pdf|max:10240',
            'credit_category_id' => 'required|exists:credit_categories,id',
            'credit_conference_id' => 'required|exists:credit_conferences,id',
            'role_id' => 'required|exists:credit_role_points,id',
            'session' => 'nullable|string|max:50',
        ]);

        $user = Auth::user();
        
        // サムネイル生成
        try {

            [$file_path, $thumbnail_path] =
                $fileService->storeUploadedFile(
                    $request->file('file'),
                    'pdf_uploads'
                );

            // role_id でポイントを取得
            $role = CreditRolePoint::find($request->role_id);
            $points = $role ? $role->points : 0;

            $upload = PdfUpload::create([
                'member_id' => $user->member_id,
                'file_path' => $file_path,
                'thumbnail_path' => $thumbnail_path,
                'credit_category_id' => $request->credit_category_id,
                'credit_conference_id' => $request->credit_conference_id,
                'credit_role_id' => $request->role_id,
                'session' => $request->session ?? '',
                'points' => $points,
                'status' => 'pending',
            ]);
            return back()->with('success', __('PDF uploaded successfully.'));
        } catch (\Exception $e) {
            \Log::error('Thumbnail generation failed: ' . $e->getMessage());
            return back()->with('error', __('PDF upload failed.'));
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
            ->whereBetween('fiscal_year', [now()->year - 4, now()->year])
            ->get();

        $totalFee = $fees->sum(fn($f) => $f->annual_fee + $f->renewal_fee);
        $totalPaid = $fees->sum('payment_amount');

        $isFeeOk = $totalFee <= $totalPaid;

        $conference_count = PdfUpload::where('member_id', $user->member_id)
            ->where('status', 'approved')
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
            'conference_count' => $conference_count,
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
