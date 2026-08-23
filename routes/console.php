<?php

use Illuminate\Support\Facades\Schedule;

// النسخة اليومية — الساعة ٢ ظهراً بتوقيت مسقط.
//
// التوقيت مكتوب بمنطقته صراحةً لا محسوباً في الرأس: الخادم على UTC،
// وكان الجدول يقول 3:00 والتعليق يقول شيئاً آخر، فأي تعديل لاحق
// يحتاج طرحاً وجمعاً — ومن يخطئ فيه لا يكتشفه إلا حين يبحث عن نسخة
// فلا يجدها. ->timezone يجعل المكتوب هو المقصود.
Schedule::command('backup:daily')->dailyAt('14:00')->timezone('Asia/Muscat');

// Auto backup every 30 minutes if changes detected (keep 20)
Schedule::command('backup:auto')->everyThirtyMinutes();

// Server status to Discord every 5 minutes
Schedule::command('discord:status')->everyFiveMinutes();

// Staff presence board to Discord every 5 minutes
Schedule::command('discord:staff-status')->everyFiveMinutes();

// إشعارات الاشتراك — لمدير المكتب فقط، ٨ صباحاً بتوقيت مسقط
Schedule::command('subscription:notices')->dailyAt('8:00')->timezone('Asia/Muscat');

// محرك الأتمتة — يعمل كل ساعة، ويخرج فوراً ما لم يُفعَّل من لوحة المطور
Schedule::command('mudawala:automation')->hourly();

// نبضة إلى لوحة مُداوَلة — خامدة ما لم يُضبط الربط
Schedule::command('panel:heartbeat')->hourly();

// شبكة أمان الاقتراحات: ما لم يصل اللوحة يُعاد إرساله — خامد إن لم
// يكن المكتب مربوطاً.
Schedule::command('suggestions:retry-delivery')->hourly()->withoutOverlapping();

// قناة العودة: حالة الاقتراح وردّ المطوّر تُجلب من اللوحة إلى المكتب،
// فيرى الموظّف مصير اقتراحه ويصله إشعار بلغته — خامدة إن لم يكن
// المكتب مربوطاً.
Schedule::command('suggestions:sync-replies')->everyFifteenMinutes()->withoutOverlapping();
