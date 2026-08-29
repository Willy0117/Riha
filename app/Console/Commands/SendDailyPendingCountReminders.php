<?php

namespace App\Console\Commands;

use App\Mail\AssignPendingCountMail;
use App\Mail\ChiefPendingCountMail;
use App\Mail\ReviewerPendingCountMail;
use App\Models\Admin;
use App\Models\ApplicationSchedule;
use App\Models\InstructorUpdateCycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDailyPendingCountReminders extends Command
{
    protected $signature = 'pending-count:send';
    protected $description = 'アサイン担当者・審査員・審査委員長それぞれの作業期間中、毎日1回、現在の件数を通知するメールを送信する';

    public function handle(): int
    {
        $this->sendToAssignStaff();
        $this->sendToReviewers();
        $this->sendToChiefs();

        return self::SUCCESS;
    }

    /**
     * アサイン担当者：サブリーダー作業期間中、未アサイン件数を通知（全員共通の値）
     */
    private function sendToAssignStaff(): void
    {
        $isWithinPeriod = ApplicationSchedule::whereDate('subleader_start', '<=', now()->toDateString())
            ->whereDate('subleader_end', '>=', now()->toDateString())
            ->exists();

        if (!$isWithinPeriod) {
            $this->info('本日はアサイン担当者作業期間ではないため、未アサイン件数の通知は送信しません。');
            return;
        }

        // SubLeaderAssignmentController@index() の unassignedCount と同じ条件
        $unassignedCount = InstructorUpdateCycle::where('status', 'pending')
            ->whereNull('reviewer_admin_id')
            ->whereDate('updated_at', '<=', now()->toDateString())
            ->count();

        $assignStaff = Admin::role(['アサイン担当者'], 'admin')->get();

        foreach ($assignStaff as $admin) {
            if (!$admin->email) {
                continue;
            }
            Mail::to($admin->email)->send(new AssignPendingCountMail($admin, $unassignedCount));
        }

        $this->info("未アサイン件数（{$unassignedCount}件）を{$assignStaff->count()}名のアサイン担当者へ送信しました。");
    }

    /**
     * 審査員：審査員作業期間中、各自の未判定件数を通知（個人ごとに異なる値）
     */
    private function sendToReviewers(): void
    {
        $isWithinPeriod = ApplicationSchedule::whereDate('reviewer_start', '<=', now()->toDateString())
            ->whereDate('reviewer_end', '>=', now()->toDateString())
            ->exists();

        if (!$isWithinPeriod) {
            $this->info('本日は審査員作業期間ではないため、担当件数の通知は送信しません。');
            return;
        }

        // アサイン担当者も審査員ロールを兼務しているため、審査員ロールで一括取得すれば両方に届く
        $reviewers = Admin::role(['審査員'], 'admin')->get();

        foreach ($reviewers as $reviewer) {
            if (!$reviewer->email) {
                continue;
            }

            // ReviewerController@index() の絞り込みと同じ条件（自分が担当・未判定のみ）
            $pendingCount = InstructorUpdateCycle::where('reviewer_admin_id', $reviewer->id)
                ->where('status', 'pending')
                ->whereIn('reviewer_judgment', ['unreviewed', 're_review'])
                ->count();

            Mail::to($reviewer->email)->send(new ReviewerPendingCountMail($reviewer, $pendingCount));
        }

        $this->info("担当件数を{$reviewers->count()}名の審査員へ送信しました。");
    }

    /**
     * 審査委員長：審査員長作業期間中、最終判定待ち件数を通知（全員共通の値）
     */
    private function sendToChiefs(): void
    {
        $isWithinPeriod = ApplicationSchedule::whereDate('chief_start', '<=', now()->toDateString())
            ->whereDate('chief_end', '>=', now()->toDateString())
            ->exists();

        if (!$isWithinPeriod) {
            $this->info('本日は審査員長作業期間ではないため、要審査件数の通知は送信しません。');
            return;
        }

        // ChiefReviewController@index() の絞り込みと同じ条件
        $pendingCount = InstructorUpdateCycle::where('status', 'pending')
            ->whereIn('reviewer_judgment', ['pass', 'fail'])
            ->count();

        $chiefs = Admin::role(['審査委員長'], 'admin')->get();

        foreach ($chiefs as $chief) {
            if (!$chief->email) {
                continue;
            }
            Mail::to($chief->email)->send(new ChiefPendingCountMail($chief, $pendingCount));
        }

        $this->info("要審査件数（{$pendingCount}件）を{$chiefs->count()}名の審査委員長へ送信しました。");
    }
}
