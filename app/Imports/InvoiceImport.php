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

        $invoiceNumbers = $dataRows->pluck(self::COL_INVOICE_NUMBER)->filter();
        $existingMap = Invoice::whereIn('invoice_number', $invoiceNumbers)
            ->get()
            ->keyBy('invoice_number');

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
            }
        }
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
            return;
        }

        // [今回修正] members テーブルに payment_method カラムは存在しないため、
        // member 側への反映は行わない（invoices 側にはそのまま保存する）
        $paymentMethod = $row[self::COL_PAYMENT_METHOD] ?? null;

        $billingStart = $row[self::COL_BILLING_START] ?? null;
        $fiscalYear   = $this->extractYear($billingStart);

        $invoiceData = [
            'member_id'               => $member->id,
            'invoice_number'          => $invoiceNumber,
            'fiscal_year'             => $fiscalYear,
            'billing_start'           => $this->toDate($billingStart, 'first'),
            'billing_end'             => $this->toDate($row[self::COL_BILLING_END] ?? null, 'last'),
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

        $existing = $existingMap->get($invoiceNumber);

        if (!$existing) {
            Invoice::create($invoiceData);
            $this->insertCount++;
        } elseif ($this->hasChanges($existing, $invoiceData)) {
            $existing->update($invoiceData);
            $this->updateCount++;
        } else {
            $this->skipCount++;
        }
    }

    // ============================================================
    // ヘルパー
    // ============================================================

    // [今回追加] fiscal_year の抽出を、toDate() と同じく複数形式に対応させる
    private function extractYear($value): ?int
    {
        if (empty($value)) {
            return null;
        }

        // "2012/12" 形式
        if (preg_match('/^(\d{4})\/(\d{1,2})$/', (string) $value, $m)) {
            return (int) $m[1];
        }

        // Excelシリアル値
        if (is_numeric($value)) {
            try {
                return (int) \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y');
            } catch (\Throwable) {
                return null;
            }
        }

        // その他の文字列（Carbonでパースできる形式）
        try {
            return (int) \Carbon\Carbon::parse($value)->format('Y');
        } catch (\Throwable) {
            return null;
        }
    }

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

    public function chunkSize(): int
    {
        return 500;
    }
}
