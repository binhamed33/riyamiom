<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * قشرة الصفحة — TEST 13 و14.
 *
 * الاختبار هنا يفحص العقد الذي تقوم عليه الحركة، لا الحركة نفسها:
 * أن القيمة تُقرأ قبل أول إطار، وأن كل تغيّرٍ يُحفظ، وأن الشريط
 * على الهاتف يبدأ خارج الشاشة بترجمةٍ كاملة لا بعرضٍ صفري.
 * أما نعومة الانتقال فيراها الإنسان — ولا يدّعيها اختبار.
 */
class SidebarShellTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        return $this->actingAs($user)->get(route('dashboard'))->getContent();
    }

    /** الحالة تُقرأ قبل رسم أول إطار — فلا يُرسم مفتوحاً ثم ينطوي. */
    public function test_sidebar_state_is_read_before_first_paint(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("localStorage.getItem('sidebarOpen')", $html);
        $this->assertStringContainsString('window.__sbOpen', $html);

        // القراءة قبل <body>: موضعها في الصفحة هو ما يمنع الوميض
        $readAt = strpos($html, "localStorage.getItem('sidebarOpen')");
        $bodyAt = strpos($html, '<body');
        $this->assertLessThan($bodyAt, $readAt, 'القراءة بعد <body> — الوميض يعود');
    }

    /** وكل تبديل يُحفظ، فلا يُعاد الضبط في كل صفحة. */
    public function test_sidebar_choice_is_persisted(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("\$watch('sidebarOpen'", $html);
        $this->assertStringContainsString("localStorage.setItem('sidebarOpen'", $html);
    }

    /** القراءة محميّة: متصفحٌ يمنع التخزين لا يكسر الصفحة. */
    public function test_storage_access_is_guarded(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression(
            '/try\s*\{\s*window\.__sbOpen/',
            $html,
            'قراءة التخزين بلا try — متصفّحٌ يمنعه يُسقط الصفحة'
        );
    }

    /** على الهاتف يبدأ الشريط خارج الشاشة بترجمةٍ كاملة. */
    public function test_mobile_drawer_starts_fully_off_screen(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('translate-x-full', $html);
        $this->assertStringContainsString('md:translate-x-0', $html);
        // غطاءٌ يُغلق باللمس خارج القائمة
        $this->assertStringContainsString("mobileOpen = false", $html);
    }

    /** زرّ الطيّ على الحاسوب موجود ومخفيّ على الهاتف. */
    public function test_desktop_collapse_button_exists(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("sidebarOpen = !sidebarOpen", $html);
        $this->assertStringContainsString('hidden md:inline-flex', $html);
    }

    /** الشريط المطويّ يعرض الأيقونات وحدها — النصّ يختفي بالصنف لا بالعرض. */
    public function test_collapsed_sidebar_hides_labels_only(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('.sb-closed .sidebar-link span', $html);
        $this->assertStringContainsString('.sb-open { width: 16rem; }', $html);
        $this->assertStringContainsString('.sb-closed { width: 72px; }', $html);
    }

    /** رابط الرواتب لا يُرسَم لموظف — والحجب الحقيقي في الخادم. */
    public function test_salary_link_absent_for_employee(): void
    {
        $employee = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $html = $this->actingAs($employee)->get(route('dashboard'))->getContent();

        // الرواتب والحضور صارا تبويبين داخل الموارد البشرية —
        // فلا رابط منفصل لأيّهما في الشريط، ولا رابط رواتب لموظف.
        $this->assertStringNotContainsString(route('salaries.index'), $html);
        $this->assertStringContainsString(route('hr.index'), $html);
    }

    /** ويُرسَم للمدير. */
    public function test_salary_has_no_separate_sidebar_link(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($manager)->get(route('dashboard'))->getContent();

        // لا رابط منفصل — المدخل من الموارد البشرية
        $this->assertStringNotContainsString(route('salaries.index'), $html);
        $this->assertStringContainsString(route('hr.index'), $html);
    }
}
