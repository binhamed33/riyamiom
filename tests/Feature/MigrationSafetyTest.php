<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * هجرة تمرّ على SQLite وتسقط على MySQL هي أسوأ ما يُكتب: كل اختبار
 * محلّي أخضر، وكل تحديث على الخادم يفشل ويستعيد النسخة الاحتياطية
 * ويقول «migrate failed» بلا سبب مقروء.
 *
 * هذه الاختبارات تحرس هذا الصنف من الخطأ في الكود لا في التشغيل.
 */
class MigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function migrationFiles(): array
    {
        return glob(database_path('migrations/*.php')) ?: [];
    }

    public function test_no_migration_indexes_an_encrypted_text_column(): void
    {
        // MySQL: «BLOB/TEXT column used in key specification without a
        // key length». SQLite يقبلها بصمت — فلا يكشفها إلا هذا الفحص.
        $encrypted = ['national_id', 'phone', 'email_enc', 'company_name'];
        $offenders = [];

        foreach ($this->migrationFiles() as $file) {
            $body = (string) file_get_contents($file);

            foreach ($encrypted as $column) {
                if (preg_match('/->(index|unique)\(\s*[\'"]' . preg_quote($column, '/') . '[\'"]/', $body)) {
                    // مقبولة إن كانت تتخطّى الأعمدة النصّية الطويلة صراحةً
                    if (!str_contains($body, 'longtext') && !str_contains($body, "'text'")) {
                        $offenders[] = basename($file) . " ← {$column}";
                    }
                }
            }
        }

        $this->assertSame([], $offenders,
            "هجرات تفهرس عموداً نصّياً طويلاً — تسقط على MySQL:\n" . implode("\n", $offenders));
    }

    public function test_the_national_id_index_migration_skips_text_columns(): void
    {
        // العمود نصّ طويل في هذا المشروع، فالهجرة يجب أن تتخطّاه
        $this->assertContains(Schema::getColumnType('clients', 'national_id'),
            ['text', 'longtext', 'mediumtext', 'string', 'varchar']);

        $indexes = collect(Schema::getIndexes('clients'))->pluck('name');

        if (in_array(Schema::getColumnType('clients', 'national_id'), ['text', 'longtext', 'mediumtext'], true)) {
            $this->assertFalse($indexes->contains('clients_national_id_index'),
                'العمود نصّ طويل ومع ذلك فُهرِس — هذا يسقط على MySQL.');
        }

        // والبديل الذي يُبحث به فعلاً موجود ومفهرَس
        $this->assertTrue(Schema::hasColumn('clients', 'national_id_hash'));
    }

    public function test_column_changing_migrations_check_before_they_change(): void
    {
        // change() على عمود غير موجود أو ضمن فهرس مركّب يُسقط التحديث.
        //
        // القاعدة تخصّ ما لم يصل كل المكاتب بعد. الهجرات الأقدم نُفِّذت
        // على كل قاعدة منذ زمن، وتعديلها الآن مخاطرة بلا مقابل.
        $offenders = [];

        foreach ($this->migrationFiles() as $file) {
            if (basename($file) < '2026_08_20') {
                continue;
            }

            $body = (string) file_get_contents($file);

            if (!str_contains($body, '->change()')) {
                continue;
            }

            $guarded = str_contains($body, 'Schema::hasColumn')
                || str_contains($body, 'Schema::hasTable')
                || str_contains($body, 'getColumns');

            if (!$guarded) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders,
            "هجرات تعدّل أعمدة بلا تحقّق مسبق:\n" . implode("\n", $offenders));
    }

    public function test_no_migration_destroys_data(): void
    {
        $forbidden = ['dropDatabase', 'truncate(', 'DROP DATABASE', 'TRUNCATE '];
        $offenders = [];

        foreach ($this->migrationFiles() as $file) {
            $body = (string) file_get_contents($file);

            foreach ($forbidden as $needle) {
                if (str_contains($body, $needle)) {
                    $offenders[] = basename($file) . " ← {$needle}";
                }
            }
        }

        $this->assertSame([], $offenders,
            "هجرات تُتلف بيانات:\n" . implode("\n", $offenders));
    }
}
