<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Dynamic backup scheduling: each BackupConfiguration drives its own schedule
Schedule::command('backup:run-scheduled')->everyMinute()->withoutOverlapping();

// Verify backup integrity every two weeks (biweekly on Sundays at 3 AM)
Schedule::command('backup:verify')->cron('0 3 * * 0/2');
