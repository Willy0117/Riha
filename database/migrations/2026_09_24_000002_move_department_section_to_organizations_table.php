<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [今回修正] 部署・学科名が違えば別契約（別Organization）として扱う方針が確定したため、
     * department（所属・学部名）・section（部署・学科名）は members ではなく
     * organizations 側に持たせる。
     *
     * 例：「相模原中央病院・リハビリテーション科」と「相模原中央病院・腎臓内科」は
     * 同じ施設名でも別の Organization（別契約）として扱う。
     *
     * 前のマイグレーション（2026_09_24_000001_add_department_section_to_members_table）で
     * members に追加した場合に備え、Schema::hasColumn で存在確認した上で安全に取り消す。
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('department')->nullable()->after('name');
            $table->string('section')->nullable()->after('department');
        });

        // members側に前回追加してしまっていた場合のみ、安全に取り消す
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'department')) {
                $table->dropColumn('department');
            }
            if (Schema::hasColumn('members', 'section')) {
                $table->dropColumn('section');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['department', 'section']);
        });
    }
};
