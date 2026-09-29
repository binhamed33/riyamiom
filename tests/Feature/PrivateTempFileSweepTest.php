<?php

namespace Tests\Feature;

use App\Support\PrivateTempFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * بقايا نسخٍ قُتلت في منتصفها تُكنس — بشرطٍ دقيق.
 *
 * ملفُّ mysqldump المؤقّت — قاعدةُ المكتب نصّاً صريحاً — كان يبقى إن قُطعت
 * النسخة (قرصٌ امتلأ، عمليةٌ أُوقفت): الحذفُ في مسار النجاح وحدَه.
 */
class PrivateTempFileSweepTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('app/backups/tmp');
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            if (str_contains(basename($f), 'sweep-test')) {
                @unlink($f);
            }
        }

        parent::tearDown();
    }

    public function test_old_orphans_go_and_fresh_or_foreign_files_stay(): void
    {
        $hex = fn () => bin2hex(random_bytes(12));

        $old = $this->dir . '/backup-' . $hex() . '.sql';
        $fresh = $this->dir . '/db-' . $hex() . '.sql';
        $foreign = $this->dir . '/sweep-test-notes.txt';

        foreach ([$old, $fresh, $foreign] as $f) {
            file_put_contents($f, 'x');
        }
        touch($old, time() - 2 * 86400);
        touch($foreign, time() - 2 * 86400);

        $swept = PrivateTempFile::sweep();

        $this->assertFileDoesNotExist($old, 'الأقدمُ من يوم بصيغة create() يُكنس');
        $this->assertFileExists($fresh, 'الحديثُ قد يكون قيد الكتابة — لا يُلمس');
        $this->assertFileExists($foreign, 'ما ليس بصيغة create() ليس منّا — لا يُلمس');
        $this->assertSame(1, $swept);

        @unlink($fresh);
    }

    /** الأمرُ اليوميُّ يكنس قبل أن يبدأ — لا بعد نجاحٍ قد لا يأتي. */
    public function test_the_daily_backup_sweeps_before_it_starts(): void
    {
        $src = file_get_contents(app_path('Console/Commands/DailyBackup.php'));
        $sweep = strpos($src, 'PrivateTempFile::sweep()');
        $create = strpos($src, "PrivateTempFile::create('backup-'");

        $this->assertNotFalse($sweep);
        $this->assertLessThan($create, $sweep);
    }
}
