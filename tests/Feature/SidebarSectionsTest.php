<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * أقسام الشريط الجانبي القابلة للطيّ.
 *
 * الاختبار يحرس العقد لا الحركة: أن يُبنى الجسم من الصفحة نفسها،
 * وأن يُحفظ الاختيار، وأن الشريط المطويّ يفتح الأقسام قسراً — لأنّ
 * عناوينه مخفيّة، وقسمٌ مطويٌّ حينها يبتلع أيقوناته بلا عنوانٍ يُعيدها.
 */
class SidebarSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        return $this->actingAs($user)->get(route('dashboard'))->getContent();
    }

    public function test_sections_are_wrapped_at_load(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("querySelectorAll('.sidebar-section-title')", $html);
        $this->assertStringContainsString("className = 'sb-section-body'", $html);
    }

    public function test_choice_is_persisted(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("var KEY = 'sbSections'", $html);
        $this->assertStringContainsString('localStorage.setItem(KEY', $html);
        $this->assertStringContainsString('localStorage.getItem(KEY', $html);
    }

    /** التخزين محميّ: متصفّحٌ يمنعه لا يكسر الشريط. */
    public function test_storage_is_guarded(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression('/try \{ return JSON\.parse\(localStorage/', $html);
        $this->assertMatchesRegularExpression('/catch \(e\) \{ return \{\}; \}/', $html);
    }

    /** الشريط المطويّ يفتح كل الأقسام قسراً. */
    public function test_collapsed_sidebar_forces_sections_open(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('.sb-closed .sb-section-body { max-height: none !important', $html);
        $this->assertStringContainsString('.sb-closed .sb-section-head { pointer-events: none; }', $html);
    }

    /** الرأس زرّ حقيقي يقبل لوحة المفاتيح ويصرّح بحالته. */
    public function test_head_is_an_accessible_button(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("createElement('button')", $html);
        $this->assertStringContainsString("head.type = 'button'", $html);
        $this->assertStringContainsString("setAttribute('aria-expanded'", $html);
        $this->assertStringContainsString("setAttribute('aria-controls'", $html);
        $this->assertStringContainsString('focus-visible', $html);
    }

    /** الارتفاع يُقاس لا يُكتب — قسمٌ يكبر لاحقاً لا يُقصّ. */
    public function test_height_is_measured_not_hardcoded(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('body.scrollHeight', $html);
        $this->assertStringNotContainsString('max-height: 500px', $html);
    }

    /** عنوانٌ بلا روابط يُترك كما هو — لا رأسَ لقسمٍ فارغ. */
    public function test_empty_section_is_left_alone(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('if (!body.children.length) return;', $html);
    }

    /** ويحترم من أطفأ الحركة في نظامه. */
    public function test_reduced_motion_respected(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression(
            '/prefers-reduced-motion: reduce\)\s*\{\s*\.sb-section-body/',
            $html
        );
    }

    /**
     * موضع تمرير الشريط يبقى بين الصفحات.
     *
     * ═══ العطل الذي وُضع له ═══
     *
     * القوائم أطول من الشاشة، فمن يعمل في قسمٍ أسفل الشريط ينزل إليه
     * ثم ينتقل من لوحة التحكّم إلى المستخدمين — فيرتدّ الشريط إلى
     * أعلاه وينزل ثانية. كلُّ انتقالٍ تحميلٌ كامل للصفحة، وموضعُ
     * التمرير لا ينجو منه ما لم يُحفظ.
     *
     * والحركة نفسها لا يبلغها اختبارٌ بلا متصفّح — فهذا يحرس أن
     * الوصل باقٍ: مفتاحٌ في تخزين اللسان، وقراءةٌ وكتابةٌ عليه.
     */
    public function test_the_sidebar_scroll_position_survives_navigation(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("var SCROLL_KEY = 'sbScroll'", $html);
        $this->assertStringContainsString('sessionStorage.setItem(SCROLL_KEY', $html);
        $this->assertStringContainsString('sessionStorage.getItem(SCROLL_KEY', $html);
        $this->assertStringContainsString('keepScroll(nav)', $html);
    }

    /**
     * وهو في تخزين اللسان لا في تخزين المتصفّح كلِّه.
     *
     * لسانان مفتوحان على قسمين متباعدين يتجاذبان موضعاً واحداً لو
     * كان مشتركاً، فيقفز شريط كلٍّ منهما إلى حيث تركه الآخر.
     */
    public function test_the_scroll_position_is_per_tab(): void
    {
        $html = $this->page();

        $this->assertStringNotContainsString('localStorage.setItem(SCROLL_KEY', $html);
        $this->assertStringNotContainsString('localStorage.getItem(SCROLL_KEY', $html);
    }
}
