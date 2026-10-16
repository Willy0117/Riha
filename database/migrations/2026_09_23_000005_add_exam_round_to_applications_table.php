<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * applications に exam_round（第N回試験）を追加する。
     *
     * [今回修正] instructor_cycles.exam_round への外部キー制約は付けない。
     * 参照先にDBレベルの一意インデックスが無い場合、外部キー作成自体が失敗するため
     * （InstructorCycleController側の unique 制約はアプリケーションレベルの検証のみ）。
     * 整合性の担保はアプリケーション側（InstructorCycle::currentlyRecruiting() 等）に委ねる。
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedInteger('exam_round')->after('member_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('exam_round');
        });
    }
};
