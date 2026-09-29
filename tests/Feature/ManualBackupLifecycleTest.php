<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * النسخةُ اليدويّة تُحذف وتُنزَّل كما تُعرض.
 *
 * كانت تُعرض في القائمة ثمّ يُرفض حذفُها بـ400: الصيغةُ المقبولة كانت
 * backup-/auto- وحدَهما، وcreate() يسمّيها manual-. فتراكمت — كلُّ واحدةٍ
 * بحجم المكتب كلِّه — ولا يستطيع صاحبُها إزالتها.
 */
class ManualBackupLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $this->file = $dir . '/manual-2026-09-29-101010.zip';

        $zip = new \ZipArchive();
        $zip->open($this->file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('database/database.sql', "CREATE TABLE t (id INT);\nINSERT INTO t VALUES (1);\n");
        $zip->close();
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function admin(): User
    {
        \App\Models\Setting::set('subscription_status', 'active', 'subscription');
        \App\Models\Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        \App\Models\Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_a_manual_backup_can_be_deleted_from_the_page(): void
    {
        $this->actingAs($this->admin())
            ->delete(route('backup.destroy', basename($this->file)))
            ->assertRedirect(route('backup.index'));

        $this->assertFileDoesNotExist($this->file);
    }

    public function test_a_manual_backup_can_be_downloaded(): void
    {
        $this->actingAs($this->admin())
            ->get(route('backup.download', basename($this->file)))
            ->assertOk();
    }

    /** والصيغةُ ما زالت حارساً: اسمٌ حرٌّ لا يمرّ — يُرفض (400) ويعود برسالةٍ لا يحذف شيئاً. */
    public function test_a_name_outside_the_pattern_is_still_refused(): void
    {
        $response = $this->actingAs($this->admin())
            ->from(route('backup.index'))
            ->delete(route('backup.destroy', 'manual-anything.zip'));

        // معالجُ الاستثناءات يحوّل 400 إلى عودةٍ برسالة — لا صفحةَ خطأٍ جافّة
        $response->assertRedirect(route('backup.index'));
        $response->assertSessionHas('error');
        $this->assertFileExists($this->file, 'لم يُحذف شيء');
    }
}
