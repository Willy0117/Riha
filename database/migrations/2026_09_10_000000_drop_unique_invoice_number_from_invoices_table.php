<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // [今回追加] invoice_number 単体のUNIQUE制約を解除する。
            // テストデータ等で同じ請求書番号が複数会員に存在するケースがあるため、
            // 一意性は member_id + invoice_number の組み合わせでアプリ側（InvoiceImport.php）が担保する。
            $table->dropUnique('invoices_invoice_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unique('invoice_number', 'invoices_invoice_number_unique');
        });
    }
};
