<?php

namespace App\Mail;

use App\Models\Admin;
use App\Models\ApplicationSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PeriodStartReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Admin $admin,
        public ApplicationSchedule $schedule,
        public string $phaseName,
        public $startDate
    ) {}

    public function build()
    {
        return $this->subject("【{$this->schedule->period_name}】{$this->phaseName}期間が3日後に開始します")
            ->view('mail.period_start_reminder');
    }
}
