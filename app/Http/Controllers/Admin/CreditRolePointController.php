<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditCategory;
use App\Models\CreditConference;
use App\Models\CreditRole;
use App\Models\CreditRolePoint;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreditRolePointController extends Controller
{
    // 一覧（区分・学会・role・単位のフラットな一覧を返す）
    public function index(Request $request)
    {
        $categories = CreditCategory::orderBy('id')->get();
        $roles = CreditRole::orderBy('id')->get();

        $conferences = CreditConference::orderBy('id')->get();

        $rolePoints = CreditRolePoint::with(['creditCategory', 'creditConference', 'creditRole'])
            ->when($request->credit_category_id, function ($q) use ($request) {
                $q->where('credit_role_points.credit_category_id', $request->credit_category_id);
            })
            ->when($request->credit_conference_id, function ($q) use ($request) {
                $q->where('credit_role_points.credit_conference_id', $request->credit_conference_id);
            })
            ->join('credit_conferences', 'credit_conferences.id', '=', 'credit_role_points.credit_conference_id')
            ->orderBy('credit_conferences.name')
            ->select('credit_role_points.*')
            ->paginate($request->per_page ?? 20)
            ->withQueryString()
            ->through(function ($rp) {
                return [
                    'id'                     => $rp->id,
                    'credit_category_id'     => $rp->credit_category_id,
                    'credit_category_name'   => $rp->creditCategory?->name,
                    'credit_conference_id'   => $rp->credit_conference_id,
                    'credit_conference_name' => $rp->creditConference?->name,
                    'credit_role_id'         => $rp->credit_role_id,
                    'credit_role_name'       => $rp->creditRole?->name,
                    'points'                 => $rp->points,
                    'requires_session'       => $rp->requires_session,
                ];
            });

        return inertia('Admin/CreditRolePoints/Index', [
            'categories'  => $categories,
            'roles'       => $roles,
            'conferences' => $conferences,
            'rolePoints'  => $rolePoints,
            'filters'     => [
                'credit_category_id' => $request->credit_category_id,
                'credit_conference_id' => $request->credit_conference_id,
            ],
        ]);
    }

    // --- 学会（credit_conferences） ---

    public function storeConference(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        CreditConference::create($validated);

        return back()->with('success', __('学会を登録しました。'));
    }

    public function updateConference(Request $request, CreditConference $conference)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $conference->update($validated);

        return back()->with('success', __('学会情報を更新しました。'));
    }

    public function destroyConference(CreditConference $conference)
    {
        $conference->delete();

        return back()->with('success', __('学会を削除しました。'));
    }

    // --- 区分マスタ（credit_categories） ---

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        CreditCategory::create($validated);

        return back()->with('success', __('区分を登録しました。'));
    }

    public function updateCategory(Request $request, CreditCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category->update($validated);

        return back()->with('success', __('区分を更新しました。'));
    }

    public function destroyCategory(CreditCategory $category)
    {
        $category->delete();

        return back()->with('success', __('区分を削除しました。'));
    }

    // --- role名マスタ（credit_roles） ---

    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:20|unique:credit_roles,name',
        ]);

        CreditRole::create($validated);

        return back()->with('success', __('roleを登録しました。'));
    }

    // --- role・単位（credit_role_points） ---

    public function storeRolePoint(Request $request)
    {
        $validated = $request->validate([
            'credit_category_id'   => [
                'required', 'exists:credit_categories,id',
                Rule::unique('credit_role_points')->where(fn ($q) => $q
                    ->where('credit_conference_id', $request->credit_conference_id)
                    ->where('credit_role_id', $request->credit_role_id)),
            ],
            'credit_conference_id' => 'required|exists:credit_conferences,id',
            'credit_role_id'       => 'required|exists:credit_roles,id',
            'points'               => 'required|integer|min:0',
            'requires_session'     => 'boolean',
        ], [
            'credit_category_id.unique' => 'この区分・学会・roleの組み合わせは既に登録されています。',
        ]);

        CreditRolePoint::create($validated);

        return back()->with('success', __('role・単位を登録しました。'));
    }

    public function updateRolePoint(Request $request, CreditRolePoint $rolePoint)
    {
        $validated = $request->validate([
            'credit_role_id'    => 'required|exists:credit_roles,id',
            'points'            => 'required|integer|min:0',
            'requires_session'  => 'boolean',
        ]);

        $rolePoint->update($validated);

        return back()->with('success', __('role・単位を更新しました。'));
    }

    public function destroyRolePoint(CreditRolePoint $rolePoint)
    {
        $rolePoint->delete();

        return back()->with('success', __('role・単位を削除しました。'));
    }
}