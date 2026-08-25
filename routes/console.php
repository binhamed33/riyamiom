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
// كل ربع ساعة لا كل ساعة: تغييرُ باقةٍ من اللوحة يسري هنا خلال دقائق،
// والنبضة رخيصة — طلب واحد صغير.
Schedule::command('panel:heartbeat')->everyFifteenMinutes();

// شبكة أمان الاقتراحات: ما لم يصل اللوحة يُعاد إرساله — خامد إن لم
// يكن المكتب مربوطاً.
Schedule::command('suggestions:retry-delivery')->hourly()->withoutOverlapping();

// قناة العودة: حالة الاقتراح وردّ المطوّر تُجلب من اللوحة إلى المكتب،
// فيرى الموظّف مصير اقتراحه ويصله إشعار بلغته — خامدة إن لم يكن
// المكتب مربوطاً.
Schedule::command('suggestions:sync-replies')->everyFifteenMinutes()->withoutOverlapping();

// ═══ عاملُ طابور البريد ═══
//
// لا يعمل على هذا الخادم عاملُ طابورٍ دائم (لا supervisor ولا systemd)،
// فكلُّ ما يُلقى في الطابور يبقى فيه إلى الأبد. ولمّا صار البريد
// مؤجَّلاً — كي لا تنتظر «حفظ القضية» مصافحةَ Gmail — لزم من يحمله.
//
// فالمجدولُ نفسه هو العامل: كلَّ دقيقة يُصرَّف ما في طابور «mail» ثم
// يتوقّف (--stop-when-empty)، بسقفٍ زمنيّ دون الدقيقة كي لا تتراكب
// العمليات، و withoutOverlapping حارسٌ ثانٍ لو تأخّرت واحدة.
//
// والطابوران معاً — «mail» أولاً ثم «default».
//
// ولم يكن كذلك في أول الأمر: صُرِّف طابور البريد وحده، فبقي الطابور
// العام بلا قارئ. ولم يُلحَظ لأنّ QUEUE_CONNECTION كان sync في المكاتب،
// فمهمّةُ توصيل الاقتراحات تُنفَّذ داخل الطلب ولا تمرّ بطابور أصلاً.
// فلمّا صار database — وهو الصواب كي لا ينتظر «حفظ القضية» مصافحةَ
// Gmail — نزلت الاقتراحاتُ طابوراً لا أحد يقرؤه، فتوقّف تسليمُها.
//
// ترتيبُ الأسماء أولويّةٌ لا تعداد: ما في «mail» يخرج قبل ما في
// «default»، فلا يؤخّر إشعارَ موكّلٍ عملٌ خلفيّ طويل.
Schedule::command('queue:work --queue=mail,default --stop-when-empty --tries=3 --max-time=50 --sleep=1')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// الرسائل التي أخفقت محاولاتها الثلاث تبقى في failed_jobs. تُنظَّف
// القديمة أسبوعياً كي لا يكبر الجدول بلا حدّ — والأسبوع يكفي لمن
// يريد أن يقرأ سبب الإخفاق.
Schedule::command('queue:prune-failed --hours=168')->weekly();
