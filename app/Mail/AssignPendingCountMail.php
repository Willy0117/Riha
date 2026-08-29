<?php

namespace App\Mail;

use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AssignPendingCountMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Admin $admin,
        public int $pendingCount
    ) {}

    public function build()
    {
        return $this->subject('【指導士資格更新】現在の未アサイン件数のお知らせ')
            ->view('email.assign_pending_count');
    }
}
