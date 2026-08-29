<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 毎日9時に、3日後に開始する作業期間のリマインダーメールを送信する
Schedule::command('schedule-reminders:send')->dailyAt('09:00');

// 各作業期間中、毎日9時に、アサイン担当者・審査員・審査委員長へ現在の件数を通知する
Schedule::command('pending-count:send')->dailyAt('09:00');
