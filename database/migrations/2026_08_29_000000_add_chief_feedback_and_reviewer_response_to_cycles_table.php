<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_update_cycles', function (Blueprint $table) {
            // 委員長が審査員へ差し戻す際の理由（既存の reason カラムは最終判定用のため、別カラムにする）
            $table->text('chief_feedback')->nullable()->after('reason');
            // 差し戻し時に指摘した書類（pdf_uploads.id の配列）
            $table->json('chief_flagged_upload_ids')->nullable()->after('chief_feedback');
            // [今回追加] 差し戻し（re_review）を受けた審査員が、合格/不合格を再提出する際に
            // 委員長宛に残せる任意のメッセージ
            $table->text('reviewer_response_message')->nullable()->after('chief_flagged_upload_ids');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_update_cycles', function (Blueprint $table) {
            $table->dropColumn(['chief_feedback', 'chief_flagged_upload_ids', 'reviewer_response_message']);
        });
    }
};
