<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Newsletter scheduled tasks
Schedule::command('newsletter:send-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('newsletter:process-bounces')->everyFifteenMinutes();
