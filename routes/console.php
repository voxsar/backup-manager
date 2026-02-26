<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Dynamic backup scheduling: each BackupConfiguration drives its own schedule
Schedule::command('backup:run-scheduled')->everyMinute()->withoutOverlapping();
