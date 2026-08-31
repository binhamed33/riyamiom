<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * النسخة اليومية تقع في الوقت المطلوب — ٢ ظهراً بتوقيت مسقط.
 *
 * التوقيت كان 3:00 والتعليق بجانبه يقول شيئاً آخر: إشعارات الاشتراك
 * كُتب عليها «٨ صباحاً بتوقيت مسقط» وهي مجدولة 4:00، وتوقيت التطبيق
 * أصلاً Asia/Muscat — فكانت تصل ٤ فجراً. تعليقٌ يخالف الكود لا يُكتشف
 * إلا حين يشتكي أحد، ولا أحد يشتكي من نسخة تُؤخذ في وقت غير الذي ظنّه.
 */
class BackupScheduleTest extends TestCase
{
    private function expression(string $command): string
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        foreach ($schedule->events() as $event) {
            if (str_contains($event->command ?? '', $command)) {
                return $event->expression;
            }
        }

        $this->fail("الأمر {$command} غير مجدول إطلاقاً");
    }

    private function event(string $command): Event
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        foreach ($schedule->events() as $event) {
            if (str_contains($event->command ?? '', $command)) {
                return $event;
            }
        }

        $this->fail("الأمر {$command} غير مجدول إطلاقاً");
    }

    public function test_the_daily_backup_runs_at_two_in_the_afternoon_muscat(): void
    {
        $this->assertSame('0 14 * * *', $this->expression('backup:daily'));
        $this->assertSame('Asia/Muscat', (string) $this->event('backup:daily')->timezone);
    }

    public function test_subscription_notices_reach_the_manager_in_the_morning(): void
    {
        $this->assertSame('0 8 * * *', $this->expression('subscription:notices'));
    }

    public function test_the_backup_command_still_exists(): void
    {
        $this->assertArrayHasKey('backup:daily', \Illuminate\Support\Facades\Artisan::all());
    }

    /** التذكير الشهري — أول الشهر ٩ صباحاً بتوقيت مسقط. */
    public function test_the_monthly_backup_reminder_is_scheduled(): void
    {
        $this->assertSame('0 9 1 * *', $this->expression('backup:remind'));
        $this->assertSame('Asia/Muscat', (string) $this->event('backup:remind')->timezone);
    }

    /**
     * النصف-ساعية أُلغيت — سياسة «نسخة واحدة تتجدد».
     *
     * كانت تراكم عشرين ملفاً من بيانات الموكلين لكل مكتب. عودتُها
     * تعيد أكوام المساحة وسطح التسريب الذي أُلغيت من أجله.
     */
    public function test_the_half_hourly_auto_backup_is_gone(): void
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        foreach ($schedule->events() as $event) {
            $this->assertStringNotContainsString('backup:auto', $event->command ?? '');
        }

        $this->assertArrayNotHasKey('backup:auto', \Illuminate\Support\Facades\Artisan::all());
    }
}
