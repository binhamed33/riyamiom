<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * البحثُ يعرف أبوابَ الموقع لا ما خلفها فقط.
 *
 * ═══ العطل ═══
 *
 * من كتب «مداولة» لم يجد شيئاً: البحثُ يقرأ القضايا والموكّلين
 * والجلسات والمهامّ ولا يعرف أنّ للموقع أقساماً. فيقرأ المستخدمُ
 * النتيجةَ الخالية «غيرُ موجود» — والصفحةُ في القائمة أمامه.
 *
 * ═══ وما يحرسه ═══
 *
 * ١) اسمُ النظام يُوصل إلى شرحه — بالتشكيل وبدونه.
 * ٢) أسماءُ الأقسام ومرادفاتُها التي يكتبها الناسُ فعلاً.
 * ٣) ولا يُعرض بابٌ لا يملكه صاحبُ الحساب: بابٌ يظهر ثمّ يُغلق في
 *    الوجه أسوأُ من بابٍ لا يظهر.
 */
class SearchFindsSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function ask(User $user, string $q): array
    {
        return $this->actingAs($user)->getJson('/command?q=' . urlencode($q))->assertOk()->json();
    }

    private function labels(array $payload): array
    {
        return array_column($payload['groups']['page'] ?? [], 'label');
    }

    /** «مداولة» — بالتشكيل وبدونه — تصل إلى الدليل. */
    public function test_the_product_name_finds_its_own_guide(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        foreach (['مداولة', 'مُداوَلة', 'مداوله', 'mudawala'] as $spelling) {
            $labels = $this->labels($this->ask($admin, $spelling));

            $this->assertNotEmpty($labels, "«{$spelling}» لم تُرجع قسماً واحداً");
            $this->assertContains('دليل الاستخدام', $labels, "«{$spelling}» لا تصل إلى الدليل");
        }
    }

    /** والكلماتُ التي يكتبها الناسُ فعلاً — لا أسماءُ القوائم وحدَها. */
    public function test_everyday_words_reach_their_section(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        foreach ([
            'فاتورة' => 'الإدارة المالية',
            'اجازة' => 'الموارد البشرية',
            'موعد' => 'المواعيد',
            'نسخة' => 'النسخ الاحتياطي',
            'اعدادات' => 'الإعدادات',
            'موكل' => 'العملاء',
        ] as $word => $section) {
            $this->assertContains($section, $this->labels($this->ask($admin, $word)), "«{$word}» لا تصل إلى «{$section}»");
        }
    }

    /** ولا يُعرض بابٌ لا يملكه صاحبُ الحساب. */
    public function test_a_section_the_user_cannot_open_is_never_offered(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $labels = $this->labels($this->ask($staff, 'مستخدم'));
        $this->assertNotContains('المستخدمون', $labels, 'عُرض بابٌ يُغلق في وجه صاحبه');

        // وما يملكه يراه
        $this->assertContains('المستندات', $this->labels($this->ask($staff, 'مستند')));
    }
}
