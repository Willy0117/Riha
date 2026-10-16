<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 自験例報告書。旧 exams テーブルを置き換える。
     *
     * 旧 exams は「氏名・性別・生年月日」を持っていたが、実際の様式4に患者氏名欄は無く、
     * それらは申請者本人の情報だった（applications 側に既に持っている）ため引き継がない。
     *
     * 様式2（証明書）を提出できるルート(A)の申請者は、このテーブルにレコードを作らない
     * （自験例報告書の提出自体が不要なため）。
     *
     * 1申請につき最大10件（case_no: 1〜10）。うち No.1・No.2 のみ様式5（詳細）の対象。
     */
    public function up(): void
    {
        Schema::create('case_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();

            // 1〜10。様式4の No.1〜No.10 に対応
            $table->unsignedTinyInteger('case_no');

            // ── 様式4：自験例報告書（１）10症例の概略 ──
            $table->string('facility')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('visit_type', ['outpatient', 'inpatient'])->nullable();
            $table->string('diagnosis')->nullable();
            $table->text('current_history')->nullable();   // 現病歴：治療内容・経過
            $table->text('past_history')->nullable();       // 既往歴
            $table->text('rehab')->nullable();               // リハビリテーション：目標・実施プログラム
            $table->text('future_plan')->nullable();         // 今後の課題と方針

            // ── 様式5：自験例報告書（２）詳細。case_no が 1・2 のときのみ入力される ──
            $table->boolean('is_detailed')->default(false);
            $table->text('patient_profile')->nullable();          // 患者プロフィール：年齢・性別・体格等
            $table->text('examination_findings')->nullable();     // 検査所見・評価
            $table->text('rehab_and_patient_education')->nullable(); // リハビリテーション：目標・実施プログラム・患者教育等
            $table->text('discussion')->nullable();                // 考察：今後の課題と方針等

            $table->timestamps();

            $table->unique(['application_id', 'case_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_reports');
    }
};
