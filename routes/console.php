<?php

use Illuminate\Support\Facades\Schedule;

// Daily backup at 3:00 AM (keep 60)
Schedule::command('backup:daily')->dailyAt('3:00');

// Auto backup every 30 minutes if changes detected (keep 20)
Schedule::command('backup:auto')->everyThirtyMinutes();

// Server status to Discord every 5 minutes
Schedule::command('discord:status')->everyFiveMinutes();

// Staff presence board to Discord every 5 minutes
Schedule::command('discord:staff-status')->everyFiveMinutes();

// إشعارات الاشتراك — لمدير المكتب فقط (الساعة 8 صباحاً بتوقيت مسقط)
Schedule::command('subscription:notices')->dailyAt('4:00');

// محرك الأتمتة — يعمل كل ساعة، ويخرج فوراً ما لم يُفعَّل من لوحة المطور
Schedule::command('mudawala:automation')->hourly();
