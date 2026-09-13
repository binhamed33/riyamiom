<?php

namespace Tests\Feature;

use App\Support\ErrorPulse;
use Tests\TestCase;

/**
 * ما الأخطاء في هذا المكتب — مجموعةً، وبلا نصوصها.
 *
 * ═══ ما يخدمه هذا ═══
 *
 * ‏«ما أريد ولا خطأ» يبدأ بمعرفة ما الأخطاء. و‎laravel.log يبلغ عشرات
 * الميغابايت، فيه الخطأُ الواحد مكرَّراً ألفَ مرّة — فمن يفتحه يرى آخرَ
 * عشرين سطراً، وقد تكون كلُّها نسخاً من عطبٍ واحد، فيظنّ العشرةَ واحداً
 * أو الواحدَ عشرة.
 *
 * ═══ وأخصُّ ما يُحرَس ═══
 *
 * أنّ المخرَجَ لا يحمل نصَّ الخطأ. فهو يُنسخ ويُرسَل لمن يُصلح، ورسالةُ
 * خطأ قاعدة البيانات تحمل ما في الصفّ نفسِه:
 *
 *     Duplicate entry 'أحمد الريامي' for key 'clients_phone'
 *
 * فلو طُبع النصُّ خرج اسمُ موكّلٍ من خادم مكتبه في أوّل لصقة.
 */
class OfficeErrorsCommandTest extends TestCase
{
    private string $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->log = storage_path('logs/laravel.log');
        @mkdir(dirname($this->log), 0755, true);
        file_put_contents($this->log, $this->sample());
    }

    protected function tearDown(): void
    {
        @unlink($this->log);
        parent::tearDown();
    }

    /** سجلٌّ بشكل لارافل الحقيقيّ — بشرطتَي JSON المائلتين وبدونهما. */
    private function sample(): string
    {
        $at = fn (int $mins) => now()->subMinutes($mins)->format('Y-m-d H:i:s');

        $lines = [];

        // عطبٌ متكرّر: جدولٌ ناقص على شاشة المواعيد — ثلاثَ عشرةَ مرّة
        for ($i = 0; $i < 13; $i++) {
            $lines[] = '[' . $at(60 - $i) . '] production.ERROR: Unhandled exception [A1B2C3D4]: '
                . "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'office.appointments' doesn't exist "
                . '{"exception":"[object] (Illuminate\\\\Database\\\\QueryException(code: 42S02): SQLSTATE[42S02] '
                . 'at \\/home\\/riyami\\/htdocs\\/office.riyami.om\\/app\\/Http\\/Controllers\\/AppointmentController.php:41)",'
                . '"url":"https:\\/\\/office.riyami.om\\/appointments","user_id":3}';
        }

        // وعطبٌ آخر مرّتين — ونصُّه يحمل اسمَ موكّلٍ حقيقيّ
        for ($i = 0; $i < 2; $i++) {
            $lines[] = '[' . $at(20 - $i) . '] production.ERROR: Unhandled exception [E5F6A7B8]: '
                . "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'أحمد الريامي' for key 'clients_phone' "
                . '{"exception":"[object] (Illuminate\\\\Database\\\\UniqueConstraintViolationException(code: 23000): '
                . 'at /home/riyami/htdocs/office.riyami.om/app/Http/Controllers/ClientController.php:118)",'
                . '"url":"https://office.riyami.om/clients","user_id":7}';
        }

        // وعطبٌ ثالثٌ بشكل لارافل الحقيقيّ: الرميُ في vendor والسببُ في إطار
        // تتبّعٍ لاحق تحت app/ — وهو ما كان يظهر «—» في الموضع
        for ($i = 0; $i < 4; $i++) {
            $lines[] = '[' . $at(10 - $i) . '] production.ERROR: SQLSTATE[42S22]: Column not found: 1054 Unknown column '
                . "'attempts' in 'where clause' (Connection: mysql, SQL: select * from `whatsapp_webhook_events` where `attempts` < 10) "
                . '{"exception":"[object] (Illuminate\\\\Database\\\\QueryException(code: 42S22): SQLSTATE[42S22]: Column not found: 1054 '
                . 'at /home/office-x/htdocs/x.riyami.om/vendor/laravel/framework/src/Illuminate/Database/Connection.php:825)';
            $lines[] = '[stacktrace]';
            $lines[] = '#0 /home/office-x/htdocs/x.riyami.om/vendor/laravel/framework/src/Illuminate/Database/Connection.php(779): Illuminate\\Database\\Connection->runQueryCallback()';
            $lines[] = '#1 /home/office-x/htdocs/x.riyami.om/vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php(3106): Illuminate\\Database\\Connection->select()';
            $lines[] = '#2 /home/office-x/htdocs/x.riyami.om/app/Console/Commands/WhatsAppSweep.php(191): Illuminate\\Database\\Query\\Builder->pluck()';
            $lines[] = '#3 /home/office-x/htdocs/x.riyami.om/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): App\\Console\\Commands\\WhatsAppSweep->handle()';
            $lines[] = '"}';
        }

        // وخطأُ اتّصالٍ بشكله الآخر: القاعدةُ لا تردّ — ونصُّه يحمل اسمَ مستخدم القاعدة
        $lines[] = '[' . $at(5) . '] production.ERROR: SQLSTATE[HY000] [2002] Connection refused (Connection: mysql, SQL: select 1) '
            . '{"exception":"[object] (Illuminate\\Database\\QueryException(code: 2002): SQLSTATE[HY000] [2002] Connection refused '
            . 'at /home/office-x/htdocs/x.riyami.om/vendor/laravel/framework/src/Illuminate/Database/Connection.php:825)';
        $lines[] = '[stacktrace]';
        $lines[] = '#0 /home/office-x/htdocs/x.riyami.om/vendor/acme/thing/resources/app/Helper.php(7): Acme\\Helper->run()';
        $lines[] = '#1 /home/office-x/htdocs/x.riyami.om/app/Console/Commands/PanelHeartbeat.php(33): Acme\\Helper->run()';
        $lines[] = '"}';

        // وخطأٌ قديمٌ خارج النافذة — لا يُعَدّ
        $lines[] = '[' . now()->subDays(9)->format('Y-m-d H:i:s') . '] production.ERROR: Unhandled exception [OLD]: '
            . '{"exception":"[object] (RuntimeException(code: 0): at /app/Http/Controllers/OldController.php:9)"}';

        return implode("\n", $lines) . "\n";
    }

    /** يجمع النسخَ في مجموعةٍ واحدةٍ بعددها، لا يعرضها ثلاثَ عشرةَ مرّة. */
    public function test_it_groups_repeats_instead_of_listing_them(): void
    {
        $rows = ErrorPulse::breakdown(now()->subDay());

        $this->assertCount(4, $rows, 'المجموعاتُ ليست أربعاً — ' . json_encode($rows, JSON_UNESCAPED_UNICODE));

        $this->assertSame(13, $rows[0]['count'], 'العددُ لا يُجمع');
        $this->assertSame('QueryException', $rows[0]['type']);
        $this->assertSame('app/Http/Controllers/AppointmentController.php:41', $rows[0]['origin'],
            'الموضعُ لا يُستخرج — وهو ما يُفتح لإصلاحه');
        $this->assertSame('Base table or view not found (1146)', $rows[0]['detail']);

        // الرميُ في vendor والسببُ في التتبّع تحت app/ — كان يظهر «—»
        $this->assertSame(4, $rows[1]['count']);
        $this->assertSame('app/Console/Commands/WhatsAppSweep.php:191', $rows[1]['origin'],
            'الإطارُ الأوّل تحت app/ في التتبّع لا يُقرأ — فيبقى الموضعُ «—»');
        $this->assertSame('Column not found (1054)', $rows[1]['detail']);

        $this->assertSame(2, $rows[2]['count']);
        $this->assertSame('UniqueConstraintViolationException', $rows[2]['type']);
        $this->assertSame('app/Http/Controllers/ClientController.php:118', $rows[2]['origin']);
        $this->assertSame('Integrity constraint violation (1062)', $rows[2]['detail']);

        // خطأُ الاتّصال: رمزُه يُقرأ، وإطارُ vendor الذي في مساره ‎/app/‎ يُتخطّى
        $this->assertSame(1, $rows[3]['count']);
        $this->assertSame('Connection (2002)', $rows[3]['detail']);
        $this->assertSame('app/Console/Commands/PanelHeartbeat.php:33', $rows[3]['origin'],
            'إطارُ vendor الذي في مساره /app/ قُرئ موضعاً لنا');
    }

    /** والنافذةُ تُحترم: خطأُ الأسبوع الماضي ليس خطأَ اليوم. */
    public function test_it_respects_the_window(): void
    {
        $this->assertCount(4, ErrorPulse::breakdown(now()->subDay()));
        $this->assertCount(5, ErrorPulse::breakdown(now()->subDays(30)));
    }

    /**
     * ═══ ولا يخرج اسمُ موكّل ═══
     *
     * هذا هو الشرطُ الذي يجعل المخرَجَ صالحاً للّصق في محادثةٍ أو
     * تذكرةِ دعم. ولو سقط، سقط معه شرطُ الخصوصيّة كلُّه.
     */
    public function test_the_output_never_carries_the_error_text(): void
    {
        $this->artisan('office:errors', ['--hours' => 24])
            ->assertExitCode(1)
            ->doesntExpectOutputToContain('أحمد الريامي')
            ->doesntExpectOutputToContain('Duplicate entry')
            ->doesntExpectOutputToContain('SQLSTATE')
            ->doesntExpectOutputToContain('whatsapp_webhook_events')
            ->doesntExpectOutputToContain('Connection refused')
            ->expectsOutputToContain('Column not found (1054)')
            ->expectsOutputToContain('Connection (2002)')
            ->run();
    }

    /** ومكتبٌ سليمٌ يُقال عنه ذلك، ويخرج بنجاح. */
    public function test_a_clean_office_says_so_and_exits_zero(): void
    {
        file_put_contents($this->log, '');

        $this->artisan('office:errors')
            ->expectsOutputToContain('لا أخطاء')
            ->assertExitCode(0);
    }

    /**
     * ═══ النبضةُ تحمل الوقتَ بمنطقته والموضعَ ═══
     *
     * كانت الساعةُ تُرسل عاريةً بتوقيت مسقط فتقرؤها اللوحةُ UTC — فتقول
     * رسالةُ ديسكورد «آخرها بعد ٣ ساعات من الآن» عن خطأٍ وقع قبل ساعة.
     * والموضعُ يجيب «ما الخطأ؟» في القناة نفسِها بلا نصّ الخطأ.
     */
    public function test_the_pulse_carries_an_offset_timestamp_and_the_origin(): void
    {
        $pulse = ErrorPulse::summary(now()->subDay());

        $this->assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$/', $pulse['last_at'],
            'الوقتُ بلا منطقةٍ زمنيّة — اللوحةُ ستقرؤه UTC');
        $this->assertLessThanOrEqual(now()->timestamp, \Carbon\Carbon::parse($pulse['last_at'])->timestamp,
            'آخرُ خطأٍ في المستقبل — المنطقةُ الزمنيّة مقلوبة');
        $this->assertSame('app/Console/Commands/PanelHeartbeat.php:33', $pulse['last_origin'],
            'موضعُ آخر خطأٍ لا يُقرأ من التتبّع');
        $this->assertStringNotContainsString('أحمد', json_encode($pulse, JSON_UNESCAPED_UNICODE));
    }

    /** ورمزُ الخروج يقول «فيه أخطاء» لمن يبني عليه شرطاً في سكربت. */
    public function test_the_exit_code_reports_the_verdict(): void
    {
        $this->artisan('office:errors', ['--hours' => 24])->assertExitCode(1);
    }
}
