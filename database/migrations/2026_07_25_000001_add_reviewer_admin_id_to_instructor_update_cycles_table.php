<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_update_cycles', function (Blueprint $table) {
            $table->foreignId('reviewer_admin_id')
                ->nullable()
                ->after('status')
                ->constrained('admins')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('instructor_update_cycles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewer_admin_id');
        });
    }
};
