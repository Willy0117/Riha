<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * instructor_cycles は「合格後の認定期間」（start_date/end_date）のマスターだったが、
     * それとは別に「その回の申請受付期間」を持たせる。
     *
     * My Page側の受験申込画面は、現在日時がどの回の application_start〜application_end に
     * 該当するかで「今募集中の回（exam_round）」を判定する
     * （更新フローの ApplicationSchedule / PdfUploadController::currentSchedule() と同じ考え方）。
     *
     * 事務局が次回分を試験実施前にあらかじめ登録しておく運用のため、nullable にしておき、
     * 認定期間だけ先に登録して募集期間は後から追記する、という順序にも対応できるようにする。
     */
    public function up(): void
    {
        Schema::table('instructor_cycles', function (Blueprint $table) {
            $table->date('application_start')->nullable()->after('exam_round');
            $table->date('application_end')->nullable()->after('application_start');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_cycles', function (Blueprint $table) {
            $table->dropColumn(['application_start', 'application_end']);
        });
    }
};
