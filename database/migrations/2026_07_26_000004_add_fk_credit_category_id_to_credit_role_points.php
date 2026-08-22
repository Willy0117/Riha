<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_role_points', function (Blueprint $table) {
            $table->foreign('credit_category_id')
                ->references('id')->on('credit_categories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credit_role_points', function (Blueprint $table) {
            $table->dropForeign(['credit_category_id']);
        });
    }
};
