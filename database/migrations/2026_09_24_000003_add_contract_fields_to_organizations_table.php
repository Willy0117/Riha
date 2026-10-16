<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OrganizationController（契約・請求管理機能を含む、実際に使われている版）が
     * 前提としている列が、実データベースの organizations テーブルには
     * 1つも存在していなかったため、まとめて追加する。
     *
     * [要確認] member_id は NOT NULL のままだと Organization::create() が失敗するため、
     * 一旦 nullable に緩和する。member_id 自体を将来的に廃止するかどうかは別途判断する。
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedInteger('contract_no')->nullable()->after('id');
            $table->string('code', 10)->nullable()->unique()->after('contract_no');
            $table->string('url')->nullable()->after('abbr');
            $table->unsignedTinyInteger('contract_status')->default(0)->after('url');
            $table->date('contract_date')->nullable()->after('contract_status');
            $table->date('new_contract_date')->nullable()->after('contract_date');
            $table->date('license_issued_at')->nullable()->after('new_contract_date');
            $table->unsignedTinyInteger('payment_method')->nullable()->after('license_issued_at');
            $table->string('rep_position', 50)->nullable()->after('name_suffix');
            $table->string('rep_last_name', 100)->nullable()->after('rep_position');
            $table->string('rep_first_name', 100)->nullable()->after('rep_last_name');
            $table->string('register_token', 64)->nullable()->after('rep_first_name');
            $table->timestamp('reminder_sent_at')->nullable()->after('updated_at');
        });

        // [今回修正] Member→Organizationの方向に反転済みのため、
        // Organization側のmember_idはNOT NULL制約だけ緩和しておく（列自体は残す）。
        // change()はdoctrine/dbal依存のため、素のSQLで直接ALTERする。
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE organizations MODIFY member_id BIGINT UNSIGNED NULL'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE organizations MODIFY member_id BIGINT UNSIGNED NOT NULL'
        );

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'contract_no', 'code', 'url', 'contract_status', 'contract_date', 'new_contract_date',
                'license_issued_at', 'payment_method', 'rep_position', 'rep_last_name',
                'rep_first_name', 'register_token', 'reminder_sent_at',
            ]);
        });
    }
};
