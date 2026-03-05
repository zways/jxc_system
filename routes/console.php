<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 每日凌晨 2 点执行备份（仅生产/预发），保留最近 7 天
Schedule::command('backup:run')
    ->dailyAt('02:00')
    ->timezone(config('app.timezone', 'Asia/Shanghai'))
    ->environments(['production', 'staging'])
    ->withoutOverlapping(60)
    ->runInBackground();
