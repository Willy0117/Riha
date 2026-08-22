<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * credit_role_points は「区分×学会×role」の組み合わせごとに1件であるべきなので、
     * アプリ側バリデーションに加えてDB側でも重複を防止する。
     */
    public function up(): void
    {
        Schema::table('credit_role_points', function (Blueprint $table) {
            $table->unique(
                ['credit_category_id', 'credit_conference_id', 'credit_role_id'],
                'credit_role_points_category_conference_role_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('credit_role_points', function (Blueprint $table) {
            $table->dropUnique('credit_role_points_category_conference_role_unique');
        });
    }
};
