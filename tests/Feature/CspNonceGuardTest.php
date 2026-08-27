<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * كل سكربت يحمل nonce.
 *
 * سياسة الأمان هنا `script-src 'nonce-…'` — والمتصفّح يحجب أي سكربت
 * بلا nonce **بصمت**: لا خطأ في الصفحة، ولا سطر في سجلّ الخادم، فقط
 * ميزةٌ لا تعمل ولا أحد يعرف لماذا. وقع هذا في سكربت طيّ الأقسام،
 * وضاعت جولةُ نشرٍ كاملة في البحث عنه.
 *
 * الاختبار يفحص الصفحة المُصيَّرة لا الملف: القالب قد يُقسَّم غداً،
 * والمهمّ ما يصل المتصفّح.
 */
class CspNonceGuardTest extends TestCase
{
    use RefreshDatabase;

    /** الصفحات التي يفتحها الفريق يومياً. */
    public static function pages(): array
    {
        return [
            'لوحة التحكم' => ['dashboard'],
            'الموارد البشرية' => ['hr.index'],
            'القضايا' => ['cases.index'],
            'الإعدادات' => ['settings.index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_every_script_tag_carries_a_nonce(string $route): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($user)->get(route($route))->getContent();

        preg_match_all('/<script\b([^>]*)>/i', $html, $m);

        $this->assertNotEmpty($m[1], 'الصفحة بلا سكربتات — الاختبار لا يحرس شيئاً');

        foreach ($m[1] as $attrs) {
            $this->assertMatchesRegularExpression(
                '/\bnonce=/i',
                $attrs,
                "سكربت بلا nonce في {$route} — سيحجبه المتصفّح بصمت: <script{$attrs}>"
            );
        }
    }

    /** والسياسة نفسها ما زالت تشترطه — لا يُفتح الباب بـunsafe-inline. */
    public function test_policy_still_requires_a_nonce(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $csp = $this->actingAs($user)->get(route('dashboard'))
            ->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertMatchesRegularExpression('/script-src[^;]*nonce-/', $csp);
        $this->assertDoesNotMatchRegularExpression(
            "/script-src[^;]*'unsafe-inline'/",
            $csp,
            'unsafe-inline في script-src يُبطل الحماية كلها'
        );
    }

    /** وسكربت طيّ الأقسام تحديداً — هو الذي سقط. */
    public function test_sidebar_accordion_script_is_allowed(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        $pos = strpos($html, "var KEY = 'sbSections'");
        $this->assertNotFalse($pos, 'سكربت الطيّ غائب عن الصفحة');

        // وسم <script> الذي يسبقه مباشرةً يجب أن يحمل nonce
        $open = strrpos(substr($html, 0, $pos), '<script');
        $tag = substr($html, $open, strpos($html, '>', $open) - $open);

        $this->assertStringContainsString('nonce=', $tag, "الوسم: <{$tag}>");
    }
}
