<?php

namespace App\Console\Commands;

use App\Mail\PeriodStartReminderMail;
use App\Models\Admin;
use App\Models\ApplicationSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendScheduleReminders extends Command
{
    protected $signature = 'schedule-reminders:send';
    protected $description = '各作業期間の開始3日前に、サブリーダー/審査員/審査委員長へ開始通知メールを送信する';

    // 何日前に通知するか
    private const DAYS_BEFORE = 3;

    public function handle(): int
    {
        $targetDate = now()->addDays(self::DAYS_BEFORE)->toDateString();

        $schedules = ApplicationSchedule::where(function ($q) use ($targetDate) {
            $q->whereDate('subleader_start', $targetDate)
              ->orWhereDate('reviewer_start', $targetDate)
              ->orWhereDate('chief_start', $targetDate);
        })->get();

        foreach ($schedules as $schedule) {
            if ($schedule->subleader_start->toDateString() === $targetDate && !$schedule->subleader_notified) {
                $this->notifyRole(['サブリーダー'], $schedule, 'サブリーダー作業', $schedule->subleader_start);
                $schedule->update(['subleader_notified' => true]);
            }

            if ($schedule->reviewer_start->toDateString() === $targetDate && !$schedule->reviewer_notified) {
                // サブリーダーには既に「審査員」ロールも別途付与されているため、
                // ここでは「審査員」ロールのみ指定すればサブリーダーも自動的に含まれる
                $this->notifyRole(['審査員'], $schedule, '審査員作業', $schedule->reviewer_start);
                $schedule->update(['reviewer_notified' => true]);
            }

            if ($schedule->chief_start->toDateString() === $targetDate && !$schedule->chief_notified) {
                // [要確認] 審査委員長のロール名は「審査委員長」と仮定している
                $this->notifyRole(['審査委員長'], $schedule, '審査員長作業', $schedule->chief_start);
                $schedule->update(['chief_notified' => true]);
            }
        }

        $this->info(count($schedules) . '件のスケジュールを確認しました。');

        return self::SUCCESS;
    }

    private function notifyRole(array $roles, ApplicationSchedule $schedule, string $phaseName, $startDate): void
    {
        $admins = Admin::role($roles, 'admin')->get();

        foreach ($admins as $admin) {
            if (!$admin->email) {
                continue;
            }

            Mail::to($admin->email)->send(
                new PeriodStartReminderMail($admin, $schedule, $phaseName, $startDate)
            );
        }
    }
}
