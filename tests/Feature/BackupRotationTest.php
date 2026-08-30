<?php

namespace Tests\Feature;

use App\Support\BackupRotation;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * §14: نسخة يومية تُستبدل، وأسبوعية وشهرية وسنوية تُرقّى وتبقى.
 *
 * الاحتفاظ بسبع نسخ فقط يعني أن أقدم ما تملكه عمره أسبوع — وعطبٌ دخل
 * البيانات قبل شهر لا يُكتشف في أسبوع. الترقية تحفظ تاريخاً أطول
 * دون أن تُراكم كل ليلة إلى الأبد.
 */
class BackupRotationTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('app/testing-backups-' . uniqid());
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->dir);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $item) {
            is_dir($item) ? $this->rrmdir($item) : @unlink($item);
        }
        @rmdir($dir);
    }

    private function makeDaily(CarbonImmutable $at): string
    {
        $file = $this->dir . '/backup-' . $at->format('Y-m-d-His') . '.zip';
        file_put_contents($file, 'zip-' . $at->toDateString());
        touch($file, $at->getTimestamp());

        return $file;
    }

    public function test_the_first_backup_of_a_period_is_promoted_to_every_level(): void
    {
        $at = CarbonImmutable::parse('2026-01-05 14:00:00'); // اثنين، أول أسبوع
        $file = $this->makeDaily($at);

        $result = BackupRotation::rotate($this->dir, $file, $at);

        $this->assertCount(3, $result['promoted'], 'أسبوعية وشهرية وسنوية');
        $this->assertFileExists($this->dir . '/weekly/weekly-2026-W02.zip');
        $this->assertFileExists($this->dir . '/monthly/monthly-2026-01.zip');
        $this->assertFileExists($this->dir . '/yearly/yearly-2026.zip');
        $this->assertFileExists($file, 'النسخة اليومية تبقى مكانها — الترقية نسخٌ لا نقل');
    }

    public function test_later_backups_in_the_same_period_do_not_overwrite_the_promoted_one(): void
    {
        $first = CarbonImmutable::parse('2026-03-02 14:00:00');
        BackupRotation::rotate($this->dir, $this->makeDaily($first), $first);

        $weekly = $this->dir . '/weekly/weekly-2026-W10.zip';
        $original = file_get_contents($weekly);

        $later = CarbonImmutable::parse('2026-03-05 14:00:00');
        $result = BackupRotation::rotate($this->dir, $this->makeDaily($later), $later);

        $this->assertSame($original, file_get_contents($weekly), 'الأسبوعية تمثّل بداية أسبوعها');
        $this->assertEmpty($result['promoted'], 'لا ترقية ثانية في نفس المدد');
    }

    public function test_each_level_prunes_by_its_own_rule_only(): void
    {
        // ستة أسابيع متتالية: تبقى أربع أسبوعيات، والشهرية والسنوية لا تُمسّ
        for ($week = 0; $week < 6; $week++) {
            $at = CarbonImmutable::parse('2026-01-05 14:00:00')->addWeeks($week);
            BackupRotation::rotate($this->dir, $this->makeDaily($at), $at);
        }

        $this->assertCount(BackupRotation::KEEP['weekly'], glob($this->dir . '/weekly/*.zip'));
        $this->assertCount(2, glob($this->dir . '/monthly/*.zip'), 'يناير وفبراير');
        $this->assertCount(1, glob($this->dir . '/yearly/*.zip'));
    }

    public function test_a_year_of_daily_backups_keeps_twelve_months_and_one_year(): void
    {
        for ($month = 0; $month < 14; $month++) {
            $at = CarbonImmutable::parse('2026-01-15 14:00:00')->addMonths($month);
            BackupRotation::rotate($this->dir, $this->makeDaily($at), $at);
        }

        $this->assertCount(BackupRotation::KEEP['monthly'], glob($this->dir . '/monthly/*.zip'));
        $this->assertCount(2, glob($this->dir . '/yearly/*.zip'), '2026 و2027');
    }

    public function test_a_missing_source_file_changes_nothing(): void
    {
        $result = BackupRotation::rotate($this->dir, $this->dir . '/does-not-exist.zip');

        $this->assertSame(['promoted' => [], 'removed' => []], $result);
        $this->assertDirectoryDoesNotExist($this->dir . '/weekly');
    }

    public function test_the_inventory_reports_every_level(): void
    {
        $at = CarbonImmutable::parse('2026-05-04 14:00:00');
        BackupRotation::rotate($this->dir, $this->makeDaily($at), $at);

        $inventory = BackupRotation::inventory($this->dir);

        $this->assertCount(1, $inventory['daily']);
        $this->assertCount(1, $inventory['weekly']);
        $this->assertCount(1, $inventory['monthly']);
        $this->assertCount(1, $inventory['yearly']);
        $this->assertArrayHasKey('size_mb', $inventory['daily'][0]);
    }
}
