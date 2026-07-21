<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto backup every 8 hours
Schedule::command('backup:daily')->cron('0 */8 * * *');

// Server status to Discord every 5 minutes
Schedule::command('discord:status')->everyFiveMinutes();
