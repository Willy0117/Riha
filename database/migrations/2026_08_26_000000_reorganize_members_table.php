<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // 住所は member_addresses テーブルへ移設済みのため、members側からは削除する
            // （tel/mobile/fax は「会員本人の一般連絡先」として members に残し、
            //   member_addresses 側の tel/fax は「その送付先固有の連絡先」として区別する）
            $table->dropColumn(['postal_code', 'address1', 'address2', 'address3']);

            // name/name_kana は last_name+first_name / last_name_kana+first_name_kana の
            // アクセサ（Member::getNameAttribute 等）に置き換えるため、カラム自体は不要
            // name_prefix/name_suffix・registration_number も不要と確定したため削除
            $table->dropColumn(['name', 'name_kana', 'name_prefix', 'name_suffix', 'registration_number']);
        });

        // [要確認] status_id は「1:法人 2:郵送先 3:請求先」という意味で使われていたが、
        // 「1:通常 2:休会 3:退会」（会員のアクティブ状態）に意味を変更する。
        // 値そのものを書き換えるものではないため、既存データが残っている場合は
        // 実際の値の再割り当て（データ移行）が別途必要になる可能性がある。
        DB::statement("ALTER TABLE members MODIFY status_id TINYINT(4) NOT NULL DEFAULT 1 COMMENT '会員状況（1:通常 2:休会 3:退会）'");
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('postal_code', 20)->nullable();
            $table->string('address1', 255)->nullable();
            $table->string('address2', 255)->nullable();
            $table->string('address3', 255)->nullable();
            $table->string('name', 100)->nullable();
            $table->string('name_kana', 100)->nullable();
            $table->string('name_prefix', 50)->nullable();
            $table->string('name_suffix', 50)->nullable();
            $table->string('registration_number', 20)->nullable();
        });

        DB::statement("ALTER TABLE members MODIFY status_id TINYINT(4) NOT NULL DEFAULT 1 COMMENT '1:法人 2:郵送先 3:請求先'");
    }
};
