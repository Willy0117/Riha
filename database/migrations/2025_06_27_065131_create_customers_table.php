<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id(); // 自動増分のID (Primary Key)
            $table->string('company_name'); // 会社名 (必須)
            $table->string('zip_code', 8)->nullable(); // 郵便番号 (8文字まで, null許容)
            $table->string('address')->nullable(); // 住所 (null許容)
            $table->string('phone_number', 20)->nullable(); // 電話番号 (20文字まで, null許容)
            $table->string('representative_name')->nullable(); // 代表者名 (null許容)
            $table->string('contact_person_name')->nullable(); // 担当者名 (null許容)
            $table->timestamps(); // created_at と updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
