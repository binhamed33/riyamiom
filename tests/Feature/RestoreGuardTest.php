<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RestoreGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * الاستعادةُ لا تصنع مطوّراً ولا تنفّذ ما لا تحويه نسخةٌ احتياطية.
 *
 * ═══ الثغرة ═══
 *
 * ملفُّ SQL المرفوع كان يُصبّ في قاعدة المكتب كما هو. ومن يملك
 * الزرَّ يملك بملفٍّ يكتبه بيده أن يرفع نفسَه مطوّراً، أو يغيّر أيَّ
 * صفّ، أو يزرع أمراً (CREATE USER، LOAD DATA، INTO OUTFILE).
 *
 * ═══ الحارسان ═══
 *
 * ١) الملفُّ يُفحص قبل الصبّ: بصمةُ mysqldump مطلوبة، والأوامرُ
 *    الغريبةُ مرفوضة.
 * ٢) الأدوارُ تُلتقط قبل الصبّ وتُعاد بعده: مَن صار مطوّراً ولم يكن
 *    يعود إلى ما كان — مهما كُتب في الملفّ.
 *
 * والزرُّ نفسُه لمدير المكتب وحدَه، لا لمن مُنح backup.manage.
 */
class RestoreGuardTest extends TestCase
{
    use RefreshDatabase;

    private const DUMP_HEAD = "-- MySQL dump 10.19  Distrib 10.11.6-MariaDB\n--\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";

    // ───────────────────────────────────────── الحارسُ الأوّل

    /** تصديرٌ حقيقيٌّ يمرّ. */
    public function test_a_genuine_dump_passes(): void
    {
        $sql = self::DUMP_HEAD . "DROP TABLE IF EXISTS `clients`;\nCREATE TABLE `clients` (`id` int);\nINSERT INTO `clients` VALUES (1);\n";

        $this->assertNull(RestoreGuard::inspect($sql));
    }

    /** ملفٌّ بلا بصمةِ تصديرٍ يُرفض — ولو كان SQL سليماً. */
    public function test_a_file_without_a_dump_signature_is_refused(): void
    {
        $this->assertNotNull(RestoreGuard::inspect("UPDATE users SET role='developer' WHERE id=7;"));
    }

    /** أوامرُ لا تحويها نسخةٌ احتياطيةٌ قطّ تُرفض — وتُسمّى. */
    #[DataProvider('forbiddenStatements')]
    public function test_foreign_statements_are_refused(string $statement, string $name): void
    {
        $sql = self::DUMP_HEAD . "CREATE TABLE `t` (`id` int);\n" . $statement . "\n";

        $reason = RestoreGuard::inspect($sql);

        $this->assertNotNull($reason, "مرّ: {$statement}");
        $this->assertStringContainsString($name, $reason);
    }

    public static function forbiddenStatements(): array
    {
        return [
            ["CREATE USER 'x'@'%' IDENTIFIED BY 'p';", 'CREATE USER'],
            ["GRANT ALL ON *.* TO 'x'@'%';", 'GRANT'],
            ["LOAD DATA LOCAL INFILE '/etc/passwd' INTO TABLE t;", 'LOAD DATA'],
            ["SELECT 1 INTO OUTFILE '/var/www/x.php';", 'INTO OUTFILE'],
            ["SELECT LOAD_FILE('/etc/passwd');", 'LOAD_FILE'],
            ["SET GLOBAL general_log = 1;", 'SET GLOBAL'],
            ["CREATE PROCEDURE p() BEGIN END;", 'CREATE PROCEDURE'],
            ["USE mysql;", 'USE'],
            ["system id", 'system'],
        ];
    }

    /** والتمويهُ داخل تعليقٍ تنفيذيٍّ (الصيغةُ التي تبدأ بشرطةٍ مائلةٍ ونجمةٍ وعلامةِ تعجّب) لا ينفع: الخادمُ ينفّذه. */
    public function test_a_statement_hidden_in_an_executable_comment_is_still_caught(): void
    {
        $sql = self::DUMP_HEAD . "CREATE TABLE `t` (`id` int);\n/*!50003 CREATE USER 'x'@'%' */;\n";

        $this->assertNotNull(RestoreGuard::inspect($sql));
    }

    // ───────────────────────────────────────── الحارسُ الثاني

    /** مَن صار مطوّراً بالاستعادة يعود إلى ما كان. */
    public function test_a_self_promoted_developer_is_demoted_back(): void
    {
        $realDev = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $before = RestoreGuard::snapshotRoles();

        // ما يفعله ملفٌّ خبيث
        User::query()->whereKey($admin->id)->update(['role' => 'developer']);
        User::query()->whereKey($staff->id)->update(['role' => 'developer']);

        $fixed = RestoreGuard::reassertRoles($before);

        $this->assertSame(2, $fixed);
        $this->assertSame('admin', $admin->refresh()->role, 'بقي المديرُ مطوّراً');
        $this->assertSame('staff', $staff->refresh()->role, 'بقي الموظّفُ مطوّراً');
        $this->assertSame('developer', $realDev->refresh()->role, 'أُنزل المطوّرُ الحقيقيّ');
    }

    /** وصفٌّ جديدٌ كلّياً بدور «مطوّر» لا يبقى مطوّراً. */
    public function test_a_brand_new_developer_row_is_never_honoured(): void
    {
        $before = RestoreGuard::snapshotRoles();

        $minted = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        RestoreGuard::reassertRoles($before);

        $this->assertNotSame('developer', $minted->refresh()->role, 'صُنع مطوّرٌ من ملفّ');
    }

    // ───────────────────────────────────────── البابُ نفسُه

    /** موظّفٌ مُنح backup.manage يُنشئ نسخةً ولا يستعيدها. */
    public function test_backup_manage_permission_no_longer_reaches_restore(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $staff->givePermission('backup.manage');

        // ما زال يصل إلى الصفحة (يُنشئ ويُنزّل)
        $this->actingAs($staff)->get(route('backup.index'))->assertOk();

        // ولا يصل إلى الاستعادة بطريقيها
        $restore = $this->actingAs($staff)->post('/backup/backup-2026-01-01-000000.zip/restore');
        $this->assertContains($restore->getStatusCode(), [302, 403]);
        if ($restore->getStatusCode() === 302) {
            $this->assertStringContainsString('dashboard', (string) $restore->headers->get('Location'), 'وصل موظّفٌ إلى الاستعادة');
        }

        $upload = $this->actingAs($staff)->post(route('backup.upload-restore'), [
            'backup_file' => UploadedFile::fake()->create('b.zip', 10, 'application/zip'),
        ]);
        $this->assertContains($upload->getStatusCode(), [302, 403]);
        if ($upload->getStatusCode() === 302) {
            $this->assertStringContainsString('dashboard', (string) $upload->headers->get('Location'), 'وصل موظّفٌ إلى رفع الاستعادة');
        }
    }

    /** ورفعُ ملفٍّ فيه SQL خبيث يُردّ برسالةٍ قبل أن يلمس القاعدة. */
    public function test_an_uploaded_malicious_dump_is_refused_before_import(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $zipPath = tempnam(sys_get_temp_dir(), 'evil') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('database/database.sql', "UPDATE users SET role='developer' WHERE id={$admin->id};");
        $zip->close();

        $response = $this->actingAs($admin)->post(route('backup.upload-restore'), [
            'backup_file' => new UploadedFile($zipPath, 'evil.zip', 'application/zip', null, true),
        ]);

        $response->assertRedirect(route('backup.index'))->assertSessionHas('error');
        $this->assertSame('admin', $admin->refresh()->role, 'صار المديرُ مطوّراً من ملفٍّ مرفوع');

        @unlink($zipPath);
    }
}
