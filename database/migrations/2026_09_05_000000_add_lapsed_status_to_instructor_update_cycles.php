<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // [今回追加] 指導士資格喪失を表す 'lapsed' を status の enum に追加する
        DB::statement("
            ALTER TABLE instructor_update_cycles
            MODIFY status ENUM('before_update','pending','approved','updated','no_update','reject','lapsed')
            NOT NULL DEFAULT 'before_update'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE instructor_update_cycles
            MODIFY status ENUM('before_update','pending','approved','updated','no_update','reject')
            NOT NULL DEFAULT 'before_update'
        ");
    }
};
