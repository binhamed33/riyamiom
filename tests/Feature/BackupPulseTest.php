<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\BackupStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * المكتب يخبر اللوحة عن نسخه هو.
 *
 * ═══ العطل الذي وُضع له ═══
 *
 * كل مكتب ينسخ نفسه كل ليلة، ولا يعلم بذلك أحد. ومركزُ النسخ في اللوحة
 * كان يقرأ حقلاً لا تكتبه إلا وظيفةُ اللوحة نفسها — فمكتبٌ ينسخ بانتظام
 * يظهر «لا نسخ بعد»، ومكتبٌ عطبت نسخُه منذ أسبوع يظهر مثله تماماً.
 *
 * وهذه أسوأ حالات الشاشة: لا تكذب في اتجاه بل تُسوّي بين السليم
 * والمعطوب، فلا يُقرأ منها شيء.
 */
class BackupPulseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        File::deleteDirectory(BackupStatus::directory());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(BackupStatus::directory());
        parent::tearDown();
    }

    private function makeArchive(string $name, int $ageHours = 0, int $bytes = 2048): string
    {
        File::ensureDirectoryExists(BackupStatus::directory());
        $path = BackupStatus::directory() . '/' . $name;
        File::put($path, str_repeat('x', $bytes));
        touch($path, now()->subHours($ageHours)->timestamp);

        return $path;
    }

    // ─────────────────────────────────────────── الملفّاتُ هي الحقيقة

    /** بلا ملفات لا ادّعاء. */
    public function test_an_office_with_no_archives_reports_none(): void
    {
        $summary = BackupStatus::summary();

        $this->assertSame(0, $summary['count']);
        $this->assertNull($summary['last_at']);
    }

    /** والأحدثُ هو ما يُبلَّغ عنه، لا الأول ولا الأكبر. */
    public function test_the_newest_archive_is_the_one_reported(): void
    {
        $this->makeArchive('backup-old.zip', ageHours: 72, bytes: 9000);
        $this->makeArchive('backup-new.zip', ageHours: 2, bytes: 1000);

        $summary = BackupStatus::summary();

        $this->assertSame(2, $summary['count']);
        $this->assertSame(1000, $summary['size_bytes']);
        $this->assertSame(10000, $summary['total_bytes']);
        $this->assertLessThan(3, now()->diffInHours($summary['last_at']));
    }

    /**
     * ولا يُقرأ زمنُ النسخة من إعدادٍ مسجَّل.
     *
     * إعدادٌ يبقى بعد أن يُحذف الملف، فيقول «نُسخ أمسِ» ولا نسخة. أما
     * الملفُّ فلا يكذب عن نفسه — فإن حُذف سقط الادّعاء معه.
     */
    public function test_a_recorded_success_does_not_survive_the_file_being_gone(): void
    {
        $path = $this->makeArchive('backup-x.zip');
        BackupStatus::record(true, tables: 47);

        $this->assertNotNull(BackupStatus::summary()['last_at']);

        File::delete($path);

        $summary = BackupStatus::summary();
        $this->assertNull($summary['last_at'], 'ادّعى نسخةً لا ملف لها');
        $this->assertSame(0, $summary['count']);
        // ويبقى خبرُ آخر نجاح شاهداً على ما جرى — وهو غير ادّعاء الوجود
        $this->assertNotNull($summary['last_ok_at']);
    }

    // ─────────────────────────────────────────── الإخفاق يُقال

    public function test_a_failure_is_recorded_with_its_reason(): void
    {
        BackupStatus::record(false, 'تعذّر تفريغ قاعدة البيانات ولا بديل لها');

        $summary = BackupStatus::summary();

        $this->assertSame('تعذّر تفريغ قاعدة البيانات ولا بديل لها', $summary['error']);
        $this->assertNotNull($summary['last_run_at']);
    }

    /** والنجاحُ بعده يمحو السبب — وإلا بقي المكتب «معطوباً» إلى الأبد. */
    public function test_a_later_success_clears_the_recorded_failure(): void
    {
        BackupStatus::record(false, 'فشل الفحص');
        $this->assertNotNull(BackupStatus::summary()['error']);

        BackupStatus::record(true, tables: 47);

        $this->assertNull(BackupStatus::summary()['error']);
        $this->assertSame(47, BackupStatus::summary()['tables']);
    }

    /**
     * وسببُ الإخفاق يُنقّى قبل أن يُرسَل.
     *
     * رسائل mysqldump تحمل اسم المستخدم والمضيف، وهذا السطر يُرسَل إلى
     * اللوحة ويُعرض على شاشة.
     */
    public function test_the_reason_is_scrubbed_before_it_leaves_the_office(): void
    {
        config([
            'mail.mailers.smtp.username' => 'mudawalah@gmail.com',
            'mail.mailers.smtp.password' => 'sixteenlowercase',
        ]);

        BackupStatus::record(false, "Access denied for user 'mudawalah@gmail.com' using password sixteenlowercase");

        $error = BackupStatus::summary()['error'];

        $this->assertStringNotContainsString('sixteenlowercase', (string) $error);
        $this->assertStringNotContainsString('mudawalah@gmail.com', (string) $error);
    }

    /** والسببُ يُقصّ: عمودُ اللوحة ٣٠٠ محرف، ونصٌّ أطول يُرفض عند التحقق. */
    public function test_a_very_long_reason_is_trimmed_to_fit_the_panel(): void
    {
        BackupStatus::record(false, str_repeat('ط', 900));

        $this->assertLessThanOrEqual(300, mb_strlen((string) BackupStatus::summary()['error']));
    }

    /**
     * وتدوينُ الخبر لا يُسقط النسخة أبداً.
     *
     * وقع فعلاً: مكتبٌ بلا جدول audit_logs — لم تُنفَّذ هجراتُه — رمت
     * الكتابةُ فيه استثناءً بعد أن تمّت النسخة، فمات الأمرُ ورمزُ خروجه
     * غير صفر، فامتنع تحديثُ ذلك المكتب لأنّ «النسخة لم تنجح» وهي نجحت.
     */
    public function test_recording_never_throws_even_with_no_settings_table(): void
    {
        \Illuminate\Support\Facades\Schema::drop('settings');

        BackupStatus::record(true, tables: 5);
        BackupStatus::record(false, 'أيّ سبب');

        $this->assertSame(0, BackupStatus::summary()['count']);
    }

    /**
     * واسمُ ما يكتبه أمرُ النسخ هو ما يبحث عنه هذا الملخّص.
     *
     * ارتباطٌ صامت: لو غُيّرت بادئةُ اسم الملف في DailyBackup ولم تُغيَّر
     * هنا، لعمِيت اللوحةُ عن كل المكاتب دفعةً واحدة ولم يُصدر ذلك خطأً
     * — تعود الشاشةُ إلى «لا نسخ بعد» للجميع، وهو العطلُ نفسه الذي
     * وُضعت هذه القناة لإصلاحه.
     */
    public function test_the_daily_command_writes_the_name_this_summary_looks_for(): void
    {
        $source = file_get_contents(app_path('Console/Commands/DailyBackup.php'));

        // الاسم الثابت الجديد يبدأ بما يبحث عنه الملخّص
        $this->assertStringContainsString("LATEST = 'backup-", $source,
            'غُيّرت بادئةُ اسم النسخة — يجب تغيير BackupStatus::GLOB معها');

        // ونثبتُ الارتباط سلوكياً لا بالنصّ وحده
        $this->makeArchive(\App\Console\Commands\DailyBackup::LATEST);
        $this->assertSame(1, BackupStatus::summary()['count']);

        // والنسخ اليدوية لها بادئتُها، فلا تُحسب متجددةً ولا تُغطّي
        // على متجددةٍ أخفقت
        $this->makeArchive('manual-' . date('Y-m-d-His') . '.zip');
        $this->assertSame(1, BackupStatus::summary()['count'], 'نسخةٌ يدوية حُسبت متجددة');
    }

    // ─────────────────────────────────────────── النبضة تحمله

    public function test_the_heartbeat_payload_carries_the_backup_block(): void
    {
        $this->makeArchive('backup-a.zip', ageHours: 1, bytes: 4096);
        BackupStatus::record(true, tables: 47);

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        config(['panel.ingest_url' => 'https://panel.example', 'panel.ingest_token' => 'tok']);

        \App\Services\PanelReporter::heartbeat();

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            $backup = $request->data()['backup'] ?? null;

            return is_array($backup)
                && $backup['count'] === 1
                && $backup['size_bytes'] === 4096
                && $backup['tables'] === 47
                && $backup['last_at'] !== null;
        });
    }

    /** ولا يحمل اسمَ ملفٍ ولا محتوى: بيانات المكتب لا تغادر خادمه. */
    public function test_the_payload_carries_no_file_names_or_content(): void
    {
        $this->makeArchive('backup-2026-08-25-140000.zip');

        $summary = BackupStatus::summary();

        $this->assertSame(
            ['last_at', 'last_ok_at', 'last_run_at', 'count', 'size_bytes', 'total_bytes', 'oldest_at', 'error', 'tables'],
            array_keys($summary),
        );

        $this->assertStringNotContainsString('backup-2026', json_encode($summary, JSON_UNESCAPED_UNICODE));
    }
}
