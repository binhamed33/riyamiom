<?php

namespace Tests\Feature;

use App\Support\BackupVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * أمان النسخ الاحتياطي والأوامر المدمّرة.
 *
 * ما تحرسه: العيوب التي وُجدت في نظام النسخ القديم لا تعود —
 * كان يحذف كل النسخ ويُبقي واحدة، وكان النصف-ساعي يلتهم اليومي،
 * وكان أرشيف بلا قاعدة بيانات يُعلَن نجاحاً. وأن أمر مسح قاعدة
 * البيانات يُرفض حين يُفعَّل الحارس.
 */
class BackupSafetyTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/backup-test-' . uniqid();
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function makeZip(string $name, array $entries): string
    {
        $path = $this->dir . '/' . $name;
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);

        foreach ($entries as $entry => $content) {
            $zip->addFromString($entry, $content);
        }

        $zip->close();

        return $path;
    }

    // ---------------------------------------------------------------- الفحص

    public function test_a_backup_with_a_real_dump_passes(): void
    {
        $dump = "CREATE TABLE `clients` (...);\nCREATE TABLE `cases` (...);\nINSERT INTO ...;";
        $path = $this->makeZip('backup-ok.zip', ['database/backup.sql' => $dump, 'storage/private/x.pdf' => 'pdf']);

        $result = BackupVerifier::verify($path);

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['tables']);
        $this->assertSame(2, $result['files']);
    }

    public function test_an_archive_without_a_database_is_rejected(): void
    {
        // هذه كانت الكارثة الصامتة: mysqldump يفشل، فيُنشأ أرشيف من ملفات
        // التخزين وحدها ويُعلَن «Backup created» — وهمُ نسخة
        $path = $this->makeZip('backup-nodb.zip', ['storage/private/x.pdf' => 'pdf']);

        $result = BackupVerifier::verify($path);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('لا قاعدة بيانات', $result['reason']);
    }

    public function test_an_empty_dump_is_rejected(): void
    {
        $path = $this->makeZip('backup-empty.zip', ['database/backup.sql' => '-- no tables here']);

        $this->assertFalse(BackupVerifier::verify($path)['ok']);
    }

    public function test_a_corrupt_file_is_rejected(): void
    {
        $path = $this->dir . '/backup-corrupt.zip';
        file_put_contents($path, 'ليس أرشيفاً إطلاقاً');

        $result = BackupVerifier::verify($path);

        $this->assertFalse($result['ok']);
    }

    public function test_a_missing_file_is_rejected(): void
    {
        $this->assertFalse(BackupVerifier::verify($this->dir . '/لا-وجود-له.zip')['ok']);
    }

    // -------------------------------------------------------------- الحذف

    public function test_pruning_keeps_the_newest_n_of_its_own_pattern_only(): void
    {
        // خمس نصف-ساعية بأعمار متدرّجة + يوميتان
        foreach ([50, 40, 30, 20, 10] as $i => $age) {
            $p = $this->makeZip("auto-2026-0{$i}.zip", ['database/backup.sql' => 'CREATE TABLE `t` ();']);
            touch($p, time() - $age * 60);
        }
        foreach ([45, 15] as $i => $age) {
            $p = $this->makeZip("backup-2026-0{$i}.zip", ['database/backup.sql' => 'CREATE TABLE `t` ();']);
            touch($p, time() - $age * 60);
        }

        $removed = BackupVerifier::prune($this->dir, 'auto-*.zip', keep: 3);

        // حُذفت أقدم نصف-ساعيتين فقط
        $this->assertCount(2, $removed);
        $this->assertCount(3, glob($this->dir . '/auto-*.zip'));

        // العيب القديم بعينه: نمط `*.zip` كان يلتهم اليومية أيضاً،
        // فيُعدم نسخة ما قبل التحديث خلال نصف ساعة من أخذها
        $this->assertCount(2, glob($this->dir . '/backup-*.zip'), 'الحذف النصف-ساعي طال النسخ اليومية');
    }

    public function test_the_daily_command_never_deletes_all_but_one(): void
    {
        $src = file_get_contents(app_path('Console/Commands/DailyBackup.php'))
             . file_get_contents(app_path('Console/Commands/AutoBackup.php'));

        // المنطق القديم كان دالة keepOnlyNewest تحذف كل شيء عدا الأحدث
        $this->assertStringNotContainsString('keepOnlyNewest', $src);
        $this->assertStringContainsString("BackupVerifier::prune", $src);
    }

    // -------------------------------------------------- تشغيل حقيقي

    /**
     * يشغّل backup:daily من أوله لآخره لا يفحص شيفرته: فحص النص لم
     * يلتقط حذف دالة بالخطأ، فوصل العطل إلى الخادم وأنتج نسخة بلا
     * ملفات التخزين. التشغيل الفعلي يلتقطه.
     */
    public function test_the_daily_backup_actually_runs_and_includes_storage_files(): void
    {
        $backupDir = storage_path('app/backups');
        $before = glob($backupDir . '/backup-*.zip') ?: [];

        // ملف تخزين حقيقي يجب أن يظهر داخل الأرشيف
        $probe = storage_path('app/private/backup-probe.txt');
        @mkdir(dirname($probe), 0700, true);
        file_put_contents($probe, 'محتوى تجريبي للفحص');

        try {
            $this->artisan('backup:daily')->assertSuccessful();

            $after = glob($backupDir . '/backup-*.zip') ?: [];
            $new = array_values(array_diff($after, $before));
            $this->assertCount(1, $new, 'لم تُنشأ نسخة جديدة');

            $verify = BackupVerifier::verify($new[0]);
            $this->assertTrue($verify['ok'], 'النسخة لا تجتاز الفحص: ' . $verify['reason']);

            // قاعدة البيانات وملفات التخزين معاً — لا قاعدة وحدها
            $zip = new \ZipArchive();
            $zip->open($new[0]);
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = $zip->getNameIndex($i);
            }
            $zip->close();

            $this->assertContains('storage/private/backup-probe.txt', $names,
                'ملفات التخزين غائبة عن الأرشيف — نسخة ناقصة');

            @unlink($new[0]);
        } finally {
            @unlink($probe);
        }
    }

    // ------------------------------------------------- الأوامر المدمّرة

    public function test_db_wipe_is_refused_when_the_guard_is_armed(): void
    {
        DB::prohibitDestructiveCommands(true);

        try {
            $exit = $this->artisan('db:wipe')->run();

            $this->assertNotSame(0, $exit, 'db:wipe نجح رغم الحارس');

            // القاعدة لم تُمسح فعلاً
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasTable('users'),
                'db:wipe مسح الجداول رغم الحارس'
            );
        } finally {
            DB::prohibitDestructiveCommands(false);
        }
    }

    public function test_the_guard_is_wired_to_production(): void
    {
        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringContainsString('prohibitDestructiveCommands', $provider);
        $this->assertStringContainsString('isProduction()', $provider);
    }

    // --------------------------------------------------------- فحص الصحّة

    public function test_the_health_command_runs_and_reports_missing_backups(): void
    {
        $this->artisan('office:health')
            ->expectsOutputToContain('النسخ الاحتياطية')
            ->expectsOutputToContain('البيانات')
            ->expectsOutputToContain('الوظائف')
            ->expectsOutputToContain('سجلّ الأخطاء')
            ->assertFailed();   // بيئة اختبار عارية: لا نسخ — يجب أن يُخفق لا أن يجامل
    }
}
