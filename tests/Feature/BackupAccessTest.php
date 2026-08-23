<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §14: النسخ الاحتياطية ليست عامّة — تنزيلها واسترجاعها وحذفها
 * للإدارة المخوَّلة وحدها، والرابط المباشر ليس مفتاحاً.
 */
class BackupAccessTest extends TestCase
{
    use RefreshDatabase;

    private function tryAs(?User $user, string $method, string $uri): int
    {
        $req = $user ? $this->actingAs($user) : $this;

        return $req->call($method, $uri)->getStatusCode();
    }

    public function test_a_guest_never_reaches_a_backup(): void
    {
        foreach ([['GET', '/backup'], ['GET', '/backup/backup-2026-01-01-000000.zip/download'],
                  ['POST', '/backup/backup-2026-01-01-000000.zip/restore']] as [$m, $u]) {
            $status = $this->tryAs(null, $m, $u);
            $this->assertContains($status, [302, 401, 419], "زائر وصل {$u}");
        }
    }

    public function test_a_staff_member_cannot_download_or_restore(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $download = $this->actingAs($staff)->get('/backup/backup-2026-01-01-000000.zip/download');
        $this->assertNotSame(200, $download->getStatusCode());

        $restore = $this->actingAs($staff)->post('/backup/backup-2026-01-01-000000.zip/restore');
        $this->assertContains($restore->getStatusCode(), [302, 403], 'الموظف لا يسترجع نسخاً');
        if ($restore->getStatusCode() === 302) {
            $this->assertStringContainsString('dashboard', (string) $restore->headers->get('Location'));
        }
    }

    public function test_a_path_traversal_filename_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // الموجّه نفسه لا يطابق شرطات مائلة مرمّزة (404)، والنمط الصارم
        // في المتحكّم يرفض ما عداها (400) — كلاهما بابٌ موصد
        $status = $this->actingAs($admin)->get('/backup/..%2F..%2F.env/download')->getStatusCode();
        $this->assertContains($status, [400, 404]);

        // المعالج العام يحوّل الرفض إلى إعادة توجيه برسالة — المهم
        // أنّ الملف لا يُقدَّم أبداً
        $res = $this->actingAs($admin)->get('/backup/evil.zip/download');
        $this->assertNotSame(200, $res->getStatusCode(), 'اسم خارج النمط الصارم يُرفض');
        $this->assertNull($res->headers->get('Content-Disposition'));
    }
}
