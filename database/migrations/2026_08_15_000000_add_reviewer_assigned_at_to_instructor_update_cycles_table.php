<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_update_cycles', function (Blueprint $table) {
            // 審査員（サブリーダー含む）にアサインされた日時
            $table->dateTime('reviewer_assigned_at')->nullable()->after('reviewer_admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_update_cycles', function (Blueprint $table) {
            $table->dropColumn('reviewer_assigned_at');
        });
    }
};
