<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id')->comment('所属組織');
            $table->string('personal_email')->nullable()->after('email')->comment('個人メールアドレス');
            $table->string('member_type', 50)->nullable()->after('status_id')->comment('会員種別');
            $table->date('joined_at')->nullable()->after('member_type')->comment('入会日');
            $table->date('withdrawn_at')->nullable()->after('joined_at')->comment('退会日');

            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'personal_email', 'member_type', 'joined_at', 'withdrawn_at']);
        });
    }
};
