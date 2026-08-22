<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        // 署名検証
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe Webhook: 署名検証エラー', [
                'message' => $e->getMessage(),
            ]);
            return response('Invalid signature', 400);
        } catch (\Throwable $e) {
            Log::error('Stripe Webhook: ペイロード解析エラー', [
                'message' => $e->getMessage(),
            ]);
            return response('Bad Request', 400);
        }

        Log::info('Stripe webhook HIT', [
            'type'     => $event->type,
            'event_id' => $event->id,
        ]);

        // 受信内容を記録（管理画面の通知表示用）
        WebhookLog::create([
            'source'     => WebhookLog::SOURCE_STRIPE,
            'event_type' => $event->type,
            'payload'    => json_encode($event->toArray()),
            'created_at' => now(),
        ]);

        // [B案] Invoice API を使っているため、Checkout Session ではなく invoice.paid を拾う
        if ($event->type === 'invoice.paid') {
            $this->handleInvoicePaid($event->data->object);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * invoice.paid を受け取り、該当する invoices レコードを支払済みに更新する。
     * 照合は stripe_invoice_id（Stripe Invoiceオブジェクトのid）で行う。
     */
    private function handleInvoicePaid(object $stripeInvoice): void
    {
        $invoice = Invoice::where('stripe_invoice_id', $stripeInvoice->id)->first();

        if (!$invoice) {
            Log::error('Stripe Webhook: 対応するinvoiceが見つかりません', [
                'stripe_invoice_id' => $stripeInvoice->id ?? null,
            ]);
            return;
        }

        // 既に支払済みの場合は二重処理を防ぐ
        if ($invoice->status === 'paid') {
            Log::info('Stripe Webhook: 既に支払済みのためスキップ', [
                'invoice_id' => $invoice->id,
            ]);
            return;
        }

        $invoice->update([
            'status'                   => 'paid',
            'paid_at'                  => now(),
            'payment_date'             => now()->toDateString(),
            'payment_amount'           => $invoice->total_amount,
            'balance'                  => 0,
            'stripe_payment_intent_id' => $stripeInvoice->payment_intent ?? $invoice->stripe_payment_intent_id,
        ]);

        Log::info('Stripe Webhook: 入金処理完了', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);

        // [要確認] My Pageのパスワード設定メール送信など、参考実装にあった
        // 「入金確定後の後続処理」がJSRR側で必要かどうかは未確認。
        // 必要であればここに追加する。
    }
}
