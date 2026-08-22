<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationSchedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    // スケジュール一覧
    public function index()
    {
        $schedules = ApplicationSchedule::orderBy('application_start')->get();

        return inertia('Admin/Schedules/Index', [
            'schedules' => $schedules,
        ]);
    }

    // 新規登録
    public function store(Request $request)
    {
        $data = $this->validated($request);

        ApplicationSchedule::create($data);

        return back()->with('success', 'スケジュールを登録しました。');
    }

    // 更新
    public function update(Request $request, ApplicationSchedule $schedule)
    {
        $data = $this->validated($request);

        $schedule->update($data);

        return back()->with('success', 'スケジュールを更新しました。');
    }

    // 削除
    public function destroy(ApplicationSchedule $schedule)
    {
        $schedule->delete();

        return back()->with('success', 'スケジュールを削除しました。');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'period_name'       => 'required|string|max:50',
            'application_start' => 'required|date',
            'application_end'   => 'required|date|after_or_equal:application_start',
            'subleader_start'   => 'required|date',
            'subleader_end'     => 'required|date|after_or_equal:subleader_start',
            'reviewer_start'    => 'required|date',
            'reviewer_end'      => 'required|date|after_or_equal:reviewer_start',
            'chief_start'       => 'required|date',
            'chief_end'         => 'required|date|after_or_equal:chief_start',
        ]);
    }
}
