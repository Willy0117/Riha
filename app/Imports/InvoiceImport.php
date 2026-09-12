<?php

namespace App\Imports;

use App\Models\Invoice;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class InvoiceImport implements ToCollection, WithChunkReading
{
    private const HEADER_ROWS = 4;

    private const COL_MEMBER_NUMBER          = 0;
    private const COL_INVOICE_NUMBER         = 17;
    private const COL_PAYMENT_METHOD         = 18;
    private const COL_INVOICE_TYPE           = 20;
    private const COL_INVOICE_NAME           = 21;
    private const COL_BILLING_START          = 23;
    private const COL_BILLING_END            = 24;
    private const COL_INVOICE_DATE           = 25;
    private const COL_DUE_DATE               = 26;

    private const COL_ANNUAL_FEE             = 29;
    private const COL_EXAM_FEE               = 33;
    private const COL_RENEWAL_FEE            = 37;
    private const COL_SEMINAR_FEE            = 41;
    private const COL_MEMBER_ADJUSTMENT      = 43;
    private const COL_MEMBER_ADJUSTMENT_TEMP = 44;
    private const COL_INVOICE_ADJUSTMENT     = 45;
    private const COL_TAX_AMOUNT             = 47;
    private const COL_TOTAL_AMOUNT           = 48;
    private const COL_PAYMENT_AMOUNT         = 49;
    private const COL_BALANCE                = 50;
    private const COL_STATUS                 = 51;
    private const COL_MEMO_MEMBER            = 52;
    private const COL_MEMO_ADMIN             = 53;

    public int $insertCount = 0;
    public int $updateCount = 0;
    public int $skipCount   = 0;
    public array $errors    = [];

    public function collection(Collection $rows)
    {
        $dataRows = $rows->slice(self::HEADER_ROWS)->filter(
            fn($row) => !empty($row[self::COL_MEMBER_NUMBER])
        );

        if ($dataRows->isEmpty()) {
            return;
        }

        $memberNumbers = $dataRows->pluck(self::COL_MEMBER_NUMBER)->map(fn($v) => (string) $v);
        $memberMap = Member::whereIn('code', $memberNumbers)
            ->get()
            ->keyBy('code');

        // [今回修正] invoice_number は複数会員間で重複しうる（実際に発生していた）。
        // invoice_number 単体をキーにすると、別会員の行が同じキーで上書きしてしまうバグがあったため、
        // member_id + invoice_number の複合キーで既存レコードを引く。
        $invoiceNumbers = $dataRows->pluck(self::COL_INVOICE_NUMBER)->filter();
        $existingMap = Invoice::whereIn('invoice_number', $invoiceNumbers)
            ->get()
            ->keyBy(fn($invoice) => $invoice->member_id . '_' . $invoice->invoice_number);

        foreach ($dataRows as $row) {
            try {
                DB::transaction(function () use ($row, $memberMap, $existingMap) {
                    $this->processRow($row, $memberMap, $existingMap);
                });
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'member_number'  => $row[self::COL_MEMBER_NUMBER] ?? null,
                    'invoice_number' => $row[self::COL_INVOICE_NUMBER] ?? null,
                    'message'        => $e->getMessage(),
                ];
                // エラー内容を必ずログファイルにも出力する（原因調査のため）
                \Log::error('InvoiceImport row failed', [
                    'member_number'  => $row[self::COL_MEMBER_NUMBER] ?? null,
                    'invoice_number' => $row[self::COL_INVOICE_NUMBER] ?? null,
                    'message'        => $e->getMessage(),
                    'file'           => $e->getFile(),
                    'line'           => $e->getLine(),
                    'trace'          => $e->getTraceAsString(),
                ]);
            }
        }

        // 処理結果のサマリーを必ずログに残す
        \Log::info('InvoiceImport finished', [
            'insertCount' => $this->insertCount,
            'updateCount' => $this->updateCount,
            'skipCount'   => $this->skipCount,
            'errorCount'  => count($this->errors),
        ]);
    }

    private function processRow($row, Collection $memberMap, Collection $existingMap): void
    {
        $memberNumber  = (string) $row[self::COL_MEMBER_NUMBER];
        $invoiceNumber = $row[self::COL_INVOICE_NUMBER] ?? null;

        $member = $memberMap->get($memberNumber);
        if (!$member) {
            $this->errors[] = [
                'member_number'  => $memberNumber,
                'invoice_number' => $invoiceNumber,
                'message'        => '会員番号が存在しません',
            ];
            // [今回追加]
            \Log::warning('InvoiceImport: member not found', [
                'member_number'  => $memberNumber,
                'invoice_number' => $invoiceNumber,
            ]);
            return;
        }

        // [今回修正] members.payment_method カラムを追加したため、直近の支払い方法として反映する
        // （年会費・更新料を問わず、処理された最新の請求の値で上書きする）
        // 「銀行振込」「クレジットカード」の2値に正規化して保存する（invoices側の生データは変更しない）
        $paymentMethod = $row[self::COL_PAYMENT_METHOD] ?? null;
        if ($paymentMethod) {
            $normalized = $this->normalizePaymentMethod($paymentMethod);
            if ($member->payment_method !== $normalized) {
                $member->update(['payment_method' => $normalized]);
            }
        }

        $billingStart = $row[self::COL_BILLING_START] ?? null;
        $billingEnd   = $this->toDate($row[self::COL_BILLING_END] ?? null, 'last');
        // [今回修正] このプロジェクトの年度は「12/1〜翌11/30」。
        // fiscal_year は billing_end の年をそのまま使う
        // （例：billing_start=2025-12-01, billing_end=2026-11-30 → fiscal_year=2026）。
        $fiscalYear = $billingEnd ? (int) \Carbon\Carbon::parse($billingEnd)->format('Y') : null;

        $invoiceData = [
            'member_id'               => $member->id,
            'invoice_number'          => $invoiceNumber,
            'fiscal_year'             => $fiscalYear,
            'billing_start'           => $this->toDate($billingStart, 'first'),
            'billing_end'             => $billingEnd,
            'invoice_date'            => $this->toDate($row[self::COL_INVOICE_DATE] ?? null),
            'due_date'                => $this->toDate($row[self::COL_DUE_DATE] ?? null),
            'invoice_type'            => $row[self::COL_INVOICE_TYPE] ?? null,
            'invoice_name'            => $row[self::COL_INVOICE_NAME] ?? null,
            'payment_method'          => $paymentMethod,
            'annual_fee'              => (int) ($row[self::COL_ANNUAL_FEE] ?? 0),
            'exam_fee'                => (int) ($row[self::COL_EXAM_FEE] ?? 0),
            'renewal_fee'             => (int) ($row[self::COL_RENEWAL_FEE] ?? 0),
            'seminar_fee'             => (int) ($row[self::COL_SEMINAR_FEE] ?? 0),
            'member_adjustment'       => (int) ($row[self::COL_MEMBER_ADJUSTMENT] ?? 0),
            'member_adjustment_temp'  => (int) ($row[self::COL_MEMBER_ADJUSTMENT_TEMP] ?? 0),
            'invoice_adjustment'      => (int) ($row[self::COL_INVOICE_ADJUSTMENT] ?? 0),
            'tax_amount'              => (int) ($row[self::COL_TAX_AMOUNT] ?? 0),
            'total_amount'            => (int) ($row[self::COL_TOTAL_AMOUNT] ?? 0),
            'payment_amount'          => (int) ($row[self::COL_PAYMENT_AMOUNT] ?? 0),
            'balance'                 => (int) ($row[self::COL_BALANCE] ?? 0),
            'status'                  => $this->resolveStatus($row[self::COL_STATUS] ?? null),
            'paid_at'                 => $this->resolvePaidAt($row),
            'memo_member'             => $row[self::COL_MEMO_MEMBER] ?? null,
            'memo_admin'              => $row[self::COL_MEMO_ADMIN] ?? null,
        ];

        // [今回修正] member_id + invoice_number の複合キーで検索する
        $existing = $existingMap->get($member->id . '_' . $invoiceNumber);

        // [今回追加] invoice_number は DB上グローバルにUNIQUE（会員ごとではない）。
        // 既存レコードが見つかったが、紐づく会員が今回の行の会員と異なる場合、
        // 上書きすると他の会員のデータを奪ってしまう（実際に起きていた事故）ため、
        // 更新せずエラーとして記録し、処理をスキップする。
        if ($existing && $existing->member_id !== $member->id) {
            $this->errors[] = [
                'member_number'  => $memberNumber,
                'invoice_number' => $invoiceNumber,
                'message'        => "請求番号が別の会員（member_id={$existing->member_id}）に既に使用されています。データを確認してください。",
            ];
            \Log::error('InvoiceImport: invoice_number belongs to a different member', [
                'invoice_number'   => $invoiceNumber,
                'excel_member_id'  => $member->id,
                'existing_member_id' => $existing->member_id,
            ]);
            return;
        }

        if (!$existing) {
            $created = Invoice::create($invoiceData);
            $this->insertCount++;
            // [今回追加] 実際にDBへ保存できたか（IDが採番されたか）を必ずログに残す
            \Log::info('InvoiceImport: created', [
                'invoice_number' => $invoiceNumber,
                'created_id'     => $created->id,
            ]);
        } elseif ($this->hasChanges($existing, $invoiceData)) {
            $existing->update($invoiceData);
            $this->updateCount++;
            \Log::info('InvoiceImport: updated', [
                'invoice_number' => $invoiceNumber,
                'existing_id'    => $existing->id,
            ]);
        } else {
            $this->skipCount++;
        }
    }

    // ============================================================
    // ヘルパー
    // ============================================================

    private function toDate($value, string $edge = 'none'): ?string
    {
        if (empty($value)) {
            return null;
        }
        // 2012/12 形式
        if (preg_match('/^(\d{4})\/(\d{1,2})$/', (string) $value, $m)) {
            $year  = $m[1];
            $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            if ($edge === 'first') return "{$year}-{$month}-01";
            if ($edge === 'last') {
                return \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');
            }
            return "{$year}-{$month}-01";
        }
        // Excelシリアル値
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        // 文字列
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveStatus(?string $status): string
    {
        return match ($status) {
            '入金済み', '支払済', '済' => 'paid',
            '一部入金'               => 'partial',
            default                  => 'unpaid',
        };
    }

    private function resolvePaidAt($row): ?string
    {
        $status = $row[self::COL_STATUS] ?? null;
        if (in_array($status, ['入金済み', '支払済', '済'])) {
            return $this->toDate($row[self::COL_INVOICE_DATE] ?? null);
        }
        return null;
    }

    // [今回修正] null/空文字/Carbon型の揺れを吸収する
    private function hasChanges($model, array $data): bool
    {
        foreach ($data as $key => $value) {
            $modelValue = $model->{$key};

            $normalizedModel = $this->normalizeForCompare($modelValue);
            $normalizedNew   = $this->normalizeForCompare($value);

            if ($normalizedModel !== $normalizedNew) {
                // [今回追加] どのカラムで不一致と判定されたか、必ずログに残す（原因調査のため）
                \Log::info('InvoiceImport: hasChanges detected diff', [
                    'invoice_number' => $model->invoice_number,
                    'column'         => $key,
                    'db_value'       => $normalizedModel,
                    'excel_value'    => $normalizedNew,
                ]);
                return true;
            }
        }
        return false;
    }

    private function normalizeForCompare($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \Carbon\Carbon) {
            return $value->format('Y-m-d');
        }
        return (string) $value;
    }

    // [今回追加] members.payment_method 用に「銀行振込」「クレジットカード」の2値へ正規化する。
    // 「クレジットカード決済（都度決済）」のような詳細付きの表記も、クレジットカードとして扱う。
    private function normalizePaymentMethod(string $raw): string
    {
        return str_contains($raw, 'クレジット') ? 'クレジットカード' : '銀行振込';
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
