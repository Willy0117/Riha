<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_update_cycles', function (Blueprint $table) {
            // 「更新しない」に切り替える直前、委員長が既に合格（approved）と裁定していた場合のみ、
            // その状態をスナップショットとして保存しておく（'approved'固定、それ以外は null）。
            // 「やっぱり更新する」を押した際、この値があれば approved に戻し、無ければ before_update に戻す。
            $table->string('chief_status', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_update_cycles', function (Blueprint $table) {
            $table->dropColumn('chief_status');
        });
    }
};
