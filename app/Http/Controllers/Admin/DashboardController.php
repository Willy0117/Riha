<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstructorUpdateCycle;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // [今回追加] 「今年度」は renewal_start_date の年をそのまま使う（シンプルな判定、後日精緻化予定）
        $currentYear = (int) ($request->year ?? now()->year);

        $baseQuery = InstructorUpdateCycle::whereYear('renewal_start_date', $currentYear);

        // 今年度対象者数（今年度が更新受付期間に該当する会員の数）
        $totalTargets = (clone $baseQuery)->count();

        // ステータスごとの内訳
        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $statusLabels = [
            'before_update' => '更新前',
            'pending'       => '審査中',
            'approved'      => '承認',
            'reject'        => '却下',
            'no_update'     => '更新しない',
            'updated'       => '更新済',
            'lapsed'        => '資格喪失',
        ];

        $breakdown = [];
        foreach ($statusLabels as $status => $label) {
            $count = $statusCounts->get($status, 0);
            $breakdown[] = [
                'status' => $status,
                'label' => $label,
                'count' => $count,
                'percentage' => $totalTargets > 0 ? round($count / $totalTargets * 100, 1) : 0,
            ];
        }

        // 更新完了者数・完了率（updatedのみ）
        $completedCount = $statusCounts->get('updated', 0);
        $completedPercentage = $totalTargets > 0 ? round($completedCount / $totalTargets * 100, 1) : 0;

        // 申請済み（pending以降、before_update以外の全て）
        $appliedCount = $totalTargets - $statusCounts->get('before_update', 0);
        $appliedPercentage = $totalTargets > 0 ? round($appliedCount / $totalTargets * 100, 1) : 0;

        // [今回追加] 対応待ち件数（年度フィルタとは無関係に全件を対象にする。
        // 他の年度で発生していれば、それ自体が異常事態のため、常に全件監視する）
        // ログイン中の管理者が該当する view 権限を持っている場合のみ算出し、
        // 権限が無い項目は null を返す（Vue側でその項目のカードを非表示にする）。
        $adminUser = $request->user('admin');

        // アサイン担当者：未アサイン（pending かつ 担当者未割当）
        $unassignedCount = $adminUser->can('subleaders.view')
            ? InstructorUpdateCycle::where('status', 'pending')
                ->whereNull('reviewer_admin_id')
                ->count()
            : null;

        // 審査員：未審査（担当者は割り当て済みだが、まだ判定していない）
        $unreviewedCount = $adminUser->can('reviewers.view')
            ? InstructorUpdateCycle::where('status', 'pending')
                ->whereNotNull('reviewer_admin_id')
                ->where('reviewer_judgment', 'unreviewed')
                ->count()
            : null;

        // 審査委員長：未承認（審査員の判定は出ているが、まだ委員長の最終承認が済んでいない）
        $unapprovedCount = $adminUser->can('chiefs.view')
            ? InstructorUpdateCycle::where('status', 'pending')
                ->whereIn('reviewer_judgment', ['pass', 'fail'])
                ->count()
            : null;

        // 年度選択用（直近5年分）
        $yearOptions = range($currentYear - 2, $currentYear + 2);

        return inertia('Admin/Dashboard', [
            'currentYear' => $currentYear,
            'yearOptions' => $yearOptions,
            'totalTargets' => $totalTargets,
            'appliedCount' => $appliedCount,
            'appliedPercentage' => $appliedPercentage,
            'completedCount' => $completedCount,
            'completedPercentage' => $completedPercentage,
            'breakdown' => $breakdown,
            'unassignedCount' => $unassignedCount,
            'unreviewedCount' => $unreviewedCount,
            'unapprovedCount' => $unapprovedCount,
        ]);
    }
}
