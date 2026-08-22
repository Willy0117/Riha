<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\RenewalInvoiceMail;
use App\Mail\RenewalInvoiceStripeMail;
use App\Models\Invoice;
use App\Models\Member;
use App\Services\StripeInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    // 更新料は現状固定額（税込）。将来的に年度等で変動する場合はconfig化する。
    private const RENEWAL_FEE = 10000;
    private const INVOICE_TYPE_RENEWAL = '更新料';

    private function disk()
    {
        return Storage::disk(config('filesystems.default'));
    }

    /**
     * 指導士更新料の対象者一覧（事務局）。
     * 対象：cycle.status = 'approved' かつ、まだ更新料の請求書を発行していない会員。
     */
    public function index(Request $request)
    {
        $search = $request->search;
        $per_page = $request->per_page ?? 20;

        $query = $this->eligibleMembersQuery();

        if (!empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $members = $query->paginate($per_page);

        return inertia('Admin/Invoices/Index', [
            'members' => $members,
            'filters' => [
                'search' => $search,
                'per_page' => $per_page,
            ],
            'renewalFee' => self::RENEWAL_FEE,
        ]);
    }

    /**
     * 選択した会員に対して、振込用の指導士更新料請求書をまとめて発行する。
     * - invoices レコードを作成
     * - Bladeテンプレートから請求書PDFを生成し、S3へ保存
     * - PDFを添付してメール送信
     */
    public function issueTransfer(Request $request)
    {
        $request->validate([
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'integer|exists:members,id',
        ]);

        $members = $this->eligibleMembersQuery()
            ->whereIn('members.id', $request->member_ids)
            ->get();

        $issuedCount = 0;
        $skippedCount = count($request->member_ids) - $members->count();

        foreach ($members as $member) {
            $cycle = $member->updateCycles->first();
            if (!$cycle) {
                $skippedCount++;
                continue;
            }

            DB::transaction(function () use ($member, $cycle, &$issuedCount) {
                $invoice = $this->createInvoiceRecord($member, $cycle, '振込');

                $pdfPath = $this->generateInvoicePdf($member, $invoice);
                $invoice->update(['pdf_path' => $pdfPath]);

                Mail::to($member->email)->send(new RenewalInvoiceMail($member, $invoice, $pdfPath));

                $issuedCount++;
            });
        }

        return redirect()->back()->with('success', $this->resultMessage($issuedCount, $skippedCount));
    }

    /**
     * 選択した会員に対して、Stripe Invoice APIで指導士更新料請求書を発行する（B案）。
     * - Stripe側にInvoiceを作成・確定（finalize）
     * - invoices.stripe_invoice_id を保存
     * - 支払いページのURL（hosted_invoice_url）をメールで案内
     */
    public function issueStripe(Request $request, StripeInvoiceService $stripeInvoiceService)
    {
        $request->validate([
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'integer|exists:members,id',
        ]);

        $members = $this->eligibleMembersQuery()
            ->whereIn('members.id', $request->member_ids)
            ->get();

        $issuedCount = 0;
        $skippedCount = count($request->member_ids) - $members->count();

        foreach ($members as $member) {
            $cycle = $member->updateCycles->first();
            if (!$cycle) {
                $skippedCount++;
                continue;
            }

            try {
                DB::transaction(function () use ($member, $cycle, $stripeInvoiceService, &$issuedCount) {
                    $invoice = $this->createInvoiceRecord($member, $cycle, 'Stripe');

                    $hostedInvoiceUrl = $stripeInvoiceService->createAndFinalize($member, $invoice);

                    Mail::to($member->email)->send(new RenewalInvoiceStripeMail($member, $invoice, $hostedInvoiceUrl));

                    $issuedCount++;
                });
            } catch (\Throwable $e) {
                \Log::error('InvoiceController: Stripe請求書発行に失敗しました', [
                    'member_id' => $member->id,
                    'message' => $e->getMessage(),
                ]);
                $skippedCount++;
            }
        }

        return redirect()->back()->with('success', $this->resultMessage($issuedCount, $skippedCount));
    }

    /**
     * 対象者抽出クエリ（index / issueTransfer / issueStripe で共通利用）。
     * 対象：cycle.status = 'approved' かつ、まだ更新料の請求書を発行していない会員。
     */
    private function eligibleMembersQuery()
    {
        return Member::whereHas('updateCycles', function ($q) {
                $q->where('status', 'approved');
            })
            ->whereDoesntHave('invoices', function ($q) {
                $q->where('invoice_type', self::INVOICE_TYPE_RENEWAL);
            })
            ->with(['updateCycles' => function ($q) {
                $q->where('status', 'approved');
            }]);
    }

    /**
     * invoices レコードを作成し、請求番号（Ymd + 10桁連番）を採番する。
     * 支払方法（振込 / Stripe）に応じて payment_method のみ変える。
     */
    private function createInvoiceRecord(Member $member, $cycle, string $paymentMethod): Invoice
    {
        $invoiceDate = now()->toDateString();
        $dueDate = now()->addMonthNoOverflow()->endOfMonth()->toDateString();

        $taxAmount = (int) round(self::RENEWAL_FEE - self::RENEWAL_FEE / 1.1);

        $invoice = Invoice::create([
            'member_id' => $member->id,
            'invoice_number' => null, // 保存後にIDを使って採番する
            'fiscal_year' => now()->year,
            'billing_start' => $cycle->start_date,
            'billing_end' => $cycle->end_date,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'invoice_type' => self::INVOICE_TYPE_RENEWAL,
            'invoice_name' => '指導士更新料',
            'payment_method' => $paymentMethod,
            'renewal_fee' => self::RENEWAL_FEE,
            'tax_amount' => $taxAmount,
            'total_amount' => self::RENEWAL_FEE,
            'status' => 'unpaid',
        ]);

        // 請求番号：発行日(Ymd) + invoices.id を10桁ゼロ埋め
        $invoice->invoice_number = now()->format('Ymd') . str_pad($invoice->id, 10, '0', STR_PAD_LEFT);
        $invoice->save();

        return $invoice;
    }

    private function resultMessage(int $issuedCount, int $skippedCount): string
    {
        $message = "{$issuedCount}件の請求書を発行しました。";
        if ($skippedCount > 0) {
            $message .= "（対象外のため{$skippedCount}件をスキップしました）";
        }
        return $message;
    }

    /**
     * 請求書PDFを生成し、S3に保存してパスを返す。
     */
    private function generateInvoicePdf(Member $member, Invoice $invoice): string
    {
        $pdf = Pdf::loadView('pdf.renewal_invoice', [
            'member' => $member,
            'invoice' => $invoice,
        ]);

        $path = "invoices/{$invoice->invoice_number}.pdf";

        $this->disk()->put($path, $pdf->output());

        return $path;
    }

    /**
     * 請求書PDFの閲覧（署名URLへリダイレクト）。
     */
    public function viewPdf(Request $request, Invoice $invoice)
    {
        if (!$invoice->pdf_path || !$this->disk()->exists($invoice->pdf_path)) {
            abort(404);
        }

        return redirect(
            $this->disk()->temporaryUrl($invoice->pdf_path, now()->addMinutes(10))
        );
    }
}
