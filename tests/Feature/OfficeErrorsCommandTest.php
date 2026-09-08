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

        // وخطأٌ قديمٌ خارج النافذة — لا يُعَدّ
        $lines[] = '[' . now()->subDays(9)->format('Y-m-d H:i:s') . '] production.ERROR: Unhandled exception [OLD]: '
            . '{"exception":"[object] (RuntimeException(code: 0): at /app/Http/Controllers/OldController.php:9)"}';

        return implode("\n", $lines) . "\n";
    }

    /** يجمع النسخَ في مجموعةٍ واحدةٍ بعددها، لا يعرضها ثلاثَ عشرةَ مرّة. */
    public function test_it_groups_repeats_instead_of_listing_them(): void
    {
        $rows = ErrorPulse::breakdown(now()->subDay());

        $this->assertCount(2, $rows, 'المجموعاتُ ليست اثنتين — ' . json_encode($rows, JSON_UNESCAPED_UNICODE));

        $this->assertSame(13, $rows[0]['count'], 'العددُ لا يُجمع');
        $this->assertSame('QueryException', $rows[0]['type']);
        $this->assertSame('app/Http/Controllers/AppointmentController.php:41', $rows[0]['origin'],
            'الموضعُ لا يُستخرج — وهو ما يُفتح لإصلاحه');

        $this->assertSame(2, $rows[1]['count']);
        $this->assertSame('UniqueConstraintViolationException', $rows[1]['type']);
        $this->assertSame('app/Http/Controllers/ClientController.php:118', $rows[1]['origin']);
    }

    /** والنافذةُ تُحترم: خطأُ الأسبوع الماضي ليس خطأَ اليوم. */
    public function test_it_respects_the_window(): void
    {
        $this->assertCount(2, ErrorPulse::breakdown(now()->subDay()));
        $this->assertCount(3, ErrorPulse::breakdown(now()->subDays(30)));
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

    /** ورمزُ الخروج يقول «فيه أخطاء» لمن يبني عليه شرطاً في سكربت. */
    public function test_the_exit_code_reports_the_verdict(): void
    {
        $this->artisan('office:errors', ['--hours' => 24])->assertExitCode(1);
    }
}
