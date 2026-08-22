<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('period_name'); // 例: 第1期
            $table->date('application_start');
            $table->date('application_end');
            $table->date('subleader_start');
            $table->date('subleader_end');
            $table->date('reviewer_start');
            $table->date('reviewer_end');
            $table->date('chief_start');
            $table->date('chief_end');
            // 各フェーズの開始通知を送信済みかどうか（cronの二重送信防止）
            $table->boolean('subleader_notified')->default(false);
            $table->boolean('reviewer_notified')->default(false);
            $table->boolean('chief_notified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_schedules');
    }
};
