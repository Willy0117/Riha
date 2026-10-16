<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * [今回] フロント側（Exams）とバックエンド側（Application）で名称が割れていたため、
     * バックエンド側を Exam に統一する。
     *
     * applications          → exams
     * application_documents → exam_documents
     * case_reports.application_id     → case_reports.exam_id
     * exam_documents.application_id   → exam_documents.exam_id
     *
     * [今回修正] Schema::table()->renameColumn() は doctrine/dbal 依存のため、
     * 素のSQL（RENAME TABLE / RENAME COLUMN）を直接叩く形に変更した。
     * MySQL 8.0 以降であれば RENAME COLUMN 構文がそのまま使える。
     */
    public function up(): void
    {
        DB::statement('RENAME TABLE applications TO exams');
        DB::statement('RENAME TABLE application_documents TO exam_documents');

        DB::statement('ALTER TABLE case_reports RENAME COLUMN application_id TO exam_id');
        DB::statement('ALTER TABLE exam_documents RENAME COLUMN application_id TO exam_id');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE exam_documents RENAME COLUMN exam_id TO application_id');
        DB::statement('ALTER TABLE case_reports RENAME COLUMN exam_id TO application_id');

        DB::statement('RENAME TABLE exam_documents TO application_documents');
        DB::statement('RENAME TABLE exams TO applications');
    }
};
