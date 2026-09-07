<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // 直近の請求（年会費・更新料を問わない）で使われた支払い方法。都度、最新のもので上書きする。
            $table->string('payment_method')->nullable()->after('member_type');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
