<?php

use Illuminate\Support\Facades\Schedule;

// Newsletter scheduled tasks
Schedule::command('newsletter:send-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('newsletter:process-pending')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('newsletter:process-bounces')->everyFifteenMinutes()->withoutOverlapping();

// Backup scheduled tasks
Schedule::command('backup:run')->daily()->at('03:00');
Schedule::command('backup:clean')->daily()->at('04:00');
