<?php

use Illuminate\Support\Facades\Schedule;

// Daily backup at 3:00 AM (keep 60)
Schedule::command('backup:daily')->dailyAt('3:00');

// Auto backup every 30 minutes if changes detected (keep 20)
Schedule::command('backup:auto')->everyThirtyMinutes();

// Server status to Discord every 5 minutes
Schedule::command('discord:status')->everyFiveMinutes();

// Poll Discord channel for developer replies (webhook is send-only, bot reads replies)
Schedule::command('discord:poll')->everyMinute()->withoutOverlapping();
