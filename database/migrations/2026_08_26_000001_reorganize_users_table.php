<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ⚠️ 破壊的操作：member_id が NULL のユーザーアカウントを削除する。
        // 現時点で18件該当する想定。実行前に必ずバックアップを取得し、
        // 本当に削除してよいアカウントか事務局側で確認してから流してください。
        DB::table('users')->whereNull('member_id')->delete();

        Schema::table('users', function (Blueprint $table) {
            // name は member.name（last_name+first_name のアクセサ）に一本化するため削除
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table) {
            // 会員に紐付かないユーザーは存在しなくなったため、NOT NULL 制約を追加
            $table->unsignedBigInteger('member_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('member_id')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('username');
        });

        // 削除したユーザーの復元はできません（down()では対応不可）
    }
};
