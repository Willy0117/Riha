<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Member;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\InvoiceItem;
use Stripe\Invoice as StripeInvoice;

class StripeInvoiceService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Stripe Invoice API で請求書を作成・確定（finalize）する。
     * JSRR側の Invoice レコードに stripe_invoice_id を保存し、
     * finalize 後に得られる hosted_invoice_url をメール送信で使えるよう返す。
     */
    public function createAndFinalize(Member $member, Invoice $invoice): string
    {
        $customer = $this->findOrCreateCustomer($member);

        // 請求項目（更新料）を Customer に紐づけて作成
        InvoiceItem::create([
            'customer'    => $customer->id,
            'amount'      => $invoice->total_amount,
            'currency'    => 'jpy',
            'description' => $invoice->invoice_name,
        ]);

        // Invoice 作成（collection_method: send_invoice ＝ 自動引き落としではなく請求書払い）
        $stripeInvoice = StripeInvoice::create([
            'customer'         => $customer->id,
            'collection_method' => 'send_invoice',
            'days_until_due'   => now()->diffInDays($invoice->due_date),
            'metadata'         => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ],
        ]);

        // 確定（finalize）すると hosted_invoice_url が発行される
        $finalized = $stripeInvoice->finalizeInvoice();

        $invoice->update([
            'stripe_invoice_id' => $finalized->id,
        ]);

        return $finalized->hosted_invoice_url;
    }

    /**
     * メールアドレスで既存の Stripe Customer を検索し、無ければ新規作成する。
     * [要確認] members テーブルに stripe_customer_id を保存するカラムが無いため、
     * 毎回メールアドレスで検索している。件数が増えると非効率なので、
     * 恒久的には member 側に stripe_customer_id を持たせてキャッシュすべき。
     */
    private function findOrCreateCustomer(Member $member): Customer
    {
        $existing = Customer::all([
            'email' => $member->email,
            'limit' => 1,
        ]);

        if (!empty($existing->data)) {
            return $existing->data[0];
        }

        return Customer::create([
            'email' => $member->email,
            'name'  => $member->name,
        ]);
    }
}
