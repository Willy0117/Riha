<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 旧 exams テーブルを削除する。
     *
     * 役割は applications（申請者本人の情報）+ case_reports（症例報告）に分割済み。
     * 現状のExams系画面（Index/Show/Edit）はモックデータのみで実データは無い前提のため、
     * データ移行処理は行わない。もし本番相当のデータが入っている場合は、
     * このマイグレーションを実行する前に、member_idごとにグルーピングして
     * applications レコードを作成し、exams の内容を case_reports へ移す
     * 個別の移行スクリプトが必要になる。
     */
    public function up(): void
    {
        Schema::dropIfExists('exams');
    }

    public function down(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('birthdate')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }
};
