<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstructorCycle;
use Illuminate\Http\Request;

class InstructorCycleController extends Controller
{
    // 一覧（第N回昇順で全件表示。件数が少ないためページングは行わない）
    public function index(Request $request)
    {
        $cycles = InstructorCycle::orderBy('exam_round')->get();

        return inertia('Admin/InstructorCycles/Index', [
            'cycles' => $cycles,
            // [今回追加] Members側と同じ「閲覧はできるが保存できない」ガード用フラグ
            'can_edit' => $request->user('admin')->can('instructorMembers.edit'),
        ]);
    }

    // 新規追加
    public function store(Request $request)
    {
        if (! $request->user('admin')->can('instructorMembers.edit')) {
            return back()->withErrors([
                'permission' => 'あなたには編集権限がありません。登録・変更はできません。',
            ]);
        }

        $validated = $request->validate([
            'exam_round' => 'required|integer|min:1|unique:instructor_cycles,exam_round',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        InstructorCycle::create($validated);

        return redirect()->route('admin.instructorCycles.index')
            ->with('success', '指導士回数マスターを追加しました。');
    }

    // 編集
    public function update(Request $request, InstructorCycle $instructorCycle)
    {
        if (! $request->user('admin')->can('instructorMembers.edit')) {
            return back()->withErrors([
                'permission' => 'あなたには編集権限がありません。登録・変更はできません。',
            ]);
        }

        $validated = $request->validate([
            'exam_round' => 'required|integer|min:1|unique:instructor_cycles,exam_round,' . $instructorCycle->id,
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $instructorCycle->update($validated);

        return redirect()->route('admin.instructorCycles.index')
            ->with('success', '指導士回数マスターを更新しました。');
    }
}
