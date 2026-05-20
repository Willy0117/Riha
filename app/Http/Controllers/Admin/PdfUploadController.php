<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\PdfUpload;
use App\Models\CreditCategory;
use App\Models\CreditConference;
use App\Models\CreditRolePoint;
use App\Models\InstructorCycle;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class PdfUploadController extends Controller
{
    // PDFアップロード一覧
    public function index(Request $request)
    {
        $query = PdfUpload::with([
            'member:id,name',
            'creditCategory:id,name',
            'creditConference:id,name',
            'creditRole:id,role',
        ])
        ->when($request->filled('category_id'), fn($q) =>
            $q->where('credit_category_id', $request->category_id)
        )
        ->when($request->filled('conference_id'), fn($q) =>
            $q->where('credit_conference_id', $request->conference_id)
        )
        ->when($request->filled('role_id'), fn($q) =>
            $q->where('credit_role_id', $request->role_id)
        )
        ->when($request->filled('end_date'), fn($q) =>
            $q->whereHas('member.updateCycles', fn($cq) =>
                $cq->where('end_date', $request->end_date)
            )
        )
        ->when($request->filled('exam_round'), fn($q) =>
            $q->whereHas('member.updateCycles', fn($cq) =>
                $cq->where('exam_round', $request->exam_round)
            )
        );

        $sortBy  = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $allowedSorts = [
            'name',
            'status',
            'created_at',
            'credit_category_id',
            'credit_conference_id',
            'credit_role_id',
            'session',
        ];

        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortDir);

        $perPage = (int) $request->input('per_page', 20);

        $uploads = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($u) => [
                ...$u->toArray(),
                'thumbnail_url' => $u->thumbnail_path
                    ? Storage::url($u->thumbnail_path)
                    : null,
            ]);

        $categories = CreditCategory::all();
        $conferences = CreditConference::all();
        $instructorcycles = InstructorCycle::all();

        $roles = CreditRolePoint::all()->map(fn($r) => [
            'id' => $r->id,
            'name' => $r->role,
            'points' => $r->points,
            'credit_category_id' => $r->credit_category_id,
            'credit_conference_id' => $r->credit_conference_id,
        ]);

        $targetDate = Carbon::now()->addYears(5)->month(11)->day(30)->format('Y-m-d');

        return inertia('Admin/PdfUploads/Index', [
            'uploads' => $uploads,
            'categories' => $categories,
            'conferences' => $conferences,
            'instructorcycles' => $instructorcycles,
            'roles' => $roles,
            'filters' => 
            [
                'name'      => $request->name ?? '',
                'status'    => $request->status ?? '',
                'category_id'    => $request-> category_id ?? '',
                'conference_id'  => $request-> conference_id ?? '',
                'role_id'    => $request->role_id ?? '',
                'exam_round' => $request->exam_round ?? '',
                'end_date' => $request->filled('end_date') ? $request->end_date : '',//$targetDate,
                'per_page'  => $request->per_page ?? 20,
                'sort_by'   => $request->sort_by ?? 'created_at',  // ← 初期値
                'sort_dir'  => $request->sort_dir ?? 'desc',       // ← 初期値
            ],
        ]);
    }
   // 承認
    public function approve(PdfUpload $pdf)
    {
        $pdf->status = 'approved';
        $pdf->save();

        return back()->with('success', __('PDF approved successfully.'));
    }

    // 差し戻し
    public function reject(Request $request, PdfUpload $pdf)
    {
        $request->validate([
            'rejection_message' => 'required|string|max:500',
        ]);

        $pdf->status = 'rejected';
        $pdf->rejection_message = $request->rejection_message;
        $pdf->points = 0; // 差し戻しは単位なし
        $pdf->save();

        return back()->with('success', __('PDF rejected.'));
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


