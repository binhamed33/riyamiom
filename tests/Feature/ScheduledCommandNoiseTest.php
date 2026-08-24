<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * أمرٌ مجدول لا يجد إعداداً اختيارياً يخمد، ولا يسجّل خطأً.
 *
 * ظهر على الخادم الحقيقي: discord:status مجدول كل خمس دقائق على كل
 * مكتب، ويرجع FAILURE لأنّ رابط Discord غير مضبوط — وهو مراقبة
 * داخلية عند مُداوَلة لا عند العميل. النتيجة ٢٨٨ خطأً في اليوم لكل
 * مكتب، حتى رأى فحص الصحة ٣٧٣ خطأً في يوم واحد كلّها من هذا، فلم
 * يعد يميّز الجدّ من الضجيج.
 */
class ScheduledCommandNoiseTest extends TestCase
{
    public function test_discord_status_is_quiet_when_unconfigured()
    {
        config(['services.discord.monitor_webhook' => null]);

        $this->artisan('discord:status')->assertExitCode(0);
    }

    public function test_the_heartbeat_is_quiet_when_the_panel_link_is_absent()
    {
        config(['panel.ingest_url' => null, 'panel.ingest_token' => null]);

        $this->artisan('panel:heartbeat')->assertExitCode(0);
    }

    public function test_every_scheduled_command_still_exists()
    {
        // أمرٌ مجدول وغير موجود يفشل كل خمس دقائق بلا سبب مقروء
        $scheduled = [];
        foreach (file(base_path('routes/console.php')) as $line) {
            if (preg_match("/Schedule::command\('([^']+)'/", $line, $m)) {
                // الاسم وحده: «queue:work --queue=mail …» أمرٌ واحد
                // ومعامِلاتُه ليست جزءاً من اسمه المسجَّل.
                $scheduled[] = strtok($m[1], ' ');
            }
        }

        $this->assertNotEmpty($scheduled);

        $known = array_keys(\Illuminate\Support\Facades\Artisan::all());

        foreach ($scheduled as $cmd) {
            $this->assertContains($cmd, $known, "أمر مجدول غير موجود: {$cmd}");
        }
    }

    public function test_discord_status_survives_a_configured_webhook_that_fails(): void
    {
        // الحالة التي وقعت في الإنتاج فعلاً: الرابط مضبوط، وما بعده
        // يرمي — gather() تعتمد على shell_exec و/proc. كان يخرج بـ1
        // كل خمس دقائق فيسجّل المُجدوِل ERROR: ٤٧٤ في اليوم لكل مكتب،
        // طُمرت تحتها أخطاء حقيقية.
        config(['services.discord.monitor_webhook' => 'https://discord.test/hook']);

        $this->mock(\App\Services\ServerMonitor::class, function ($mock) {
            $mock->shouldReceive('gather')->andThrow(new \RuntimeException('shell_exec() has been disabled'));
        });

        $this->artisan('discord:status')->assertSuccessful();
    }
}
