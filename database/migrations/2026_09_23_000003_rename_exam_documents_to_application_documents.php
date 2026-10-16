<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * exam_documents を application_documents にリネームし、
     * exam_id（旧examsへの紐付け）を application_id に置き換える。
     *
     * 様式4・5（自験例報告書）は全て電子入力のテキスト項目であり、ファイル提出ではないため
     * case_reports とは紐付けない。application_documents は証明書（様式2）・推薦状（様式3-1/3-2）
     * ・表紙 のような「申請に直属する添付ファイル」専用のテーブルとする。
     */
    public function up(): void
    {
        Schema::rename('exam_documents', 'application_documents');

        Schema::table('application_documents', function (Blueprint $table) {
            $table->foreignId('application_id')->nullable()->after('id')
                ->constrained('applications')->cascadeOnDelete();
        });

        // 旧データ（モックのみのはずだが念のため）は移行しようがないため、
        // exam_id列は落とす。もし旧examsに紐づく実データが残っている場合は、
        // このマイグレーションを実行する前に個別に確認すること。
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropColumn('exam_id');
        });

        // type は certificate / recome1 / recome2 に加えて、事務局が任意で
        // 表紙（チェックリスト）のスキャンを保管したい場合に備えて checklist も許容する
        DB::statement("ALTER TABLE application_documents MODIFY type ENUM('checklist','certificate','recome1','recome2') NOT NULL");
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropForeign(['application_id']);
            $table->dropColumn('application_id');
            $table->foreignId('exam_id')->nullable();
        });

        Schema::rename('application_documents', 'exam_documents');
    }
};
