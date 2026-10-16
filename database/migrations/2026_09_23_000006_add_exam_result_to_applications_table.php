<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * applications に exam_result（CBT試験本番の合否）を追加する。
     *
     * status(approved/reject)は「書類審査を通過し受験資格が認められたか」を表すのみで、
     * 試験本番（CBT）の合否とは別物。受験資格ありでも、本番で不合格になるケースがあるため、
     * exam_result を別カラムとして持つ。
     *
     * pending: 未受験・結果未登録 / pass: 合格 / fail: 不合格
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->enum('exam_result', ['pending', 'pass', 'fail'])
                ->default('pending')
                ->after('reason');
            $table->timestamp('exam_result_recorded_at')->nullable()->after('exam_result');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['exam_result', 'exam_result_recorded_at']);
        });
    }
};
