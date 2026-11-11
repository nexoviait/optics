<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expired password reset tokens (run with `php artisan schedule:work`, or cron `schedule:run` in production).
Schedule::command('auth:clear-resets')->daily();

// Photo renditions and 360° archives are queued. This lets a single cron entry run them
// on hosts without a long-running worker; it is harmless alongside a Supervisor worker.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')->everyMinute()->withoutOverlapping(10);
