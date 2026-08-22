<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class RenewalInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member,
        public Invoice $invoice,
        public string $pdfPath
    ) {}

    public function build()
    {
        $disk = Storage::disk(config('filesystems.default'));

        return $this->subject('【日本腎臓リハビリテーション学会】指導士更新料 請求書のご案内')
            ->view('mail.renewal_invoice')
            ->attachData($disk->get($this->pdfPath), "{$this->invoice->invoice_number}.pdf", [
                'mime' => 'application/pdf',
            ]);
    }
}
