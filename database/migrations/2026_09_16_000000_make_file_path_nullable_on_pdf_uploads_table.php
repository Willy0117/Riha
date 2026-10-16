<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_uploads', function (Blueprint $table) {
            // [今回追加] 学術講習会インポート機能のため、添付資料が無い書類（file_path null）を許容する
            $table->string('file_path')->nullable()->change();
            $table->string('thumbnail_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pdf_uploads', function (Blueprint $table) {
            $table->string('file_path')->nullable(false)->change();
            $table->string('thumbnail_path')->nullable(false)->change();
        });
    }
};
