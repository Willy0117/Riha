<?php

namespace App\Mail;

use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReviewerPendingCountMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Admin $admin,
        public int $pendingCount
    ) {}

    public function build()
    {
        return $this->subject('【指導士資格更新】あなたの現在の担当件数のお知らせ')
            ->view('email.reviewer_pending_count');
    }
}
