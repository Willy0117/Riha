<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\RehabApplication;
use App\Models\ApplicationSchedule;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $applications = array();

        // [今回追加] 申請受付スケジュール一覧（Create.vueと同じ内容をMyPageダッシュボードにも表示する）
        $allSchedules = ApplicationSchedule::orderBy('application_start')->get([
            'period_name', 'application_start', 'application_end', 'subleader_start', 'chief_end',
        ]);

        return Inertia::render('Dashboard', [
            'user' => $user, 
            'applications' => $applications,
            'allSchedules' => $allSchedules,
        ]);
    }
}
