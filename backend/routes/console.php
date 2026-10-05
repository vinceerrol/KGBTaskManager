<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// The 10 minute lock expiry stops a crashed run from blocking the scheduler for the default 24 hours.
Schedule::command('tasks:check-scheduled')->everyMinute()->withoutOverlapping(10);
Schedule::command('sanctum:prune-expired --hours=24')->daily();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
