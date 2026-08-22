<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RenewalInvoiceStripeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member,
        public Invoice $invoice,
        public string $hostedInvoiceUrl
    ) {}

    public function build()
    {
        return $this->subject('【日本腎臓リハビリテーション学会】指導士更新料 お支払いのご案内')
            ->view('mail.renewal_invoice_stripe');
    }
}
