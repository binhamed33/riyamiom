<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Setting;
use App\Models\User;
use App\Support\OfficeEngines;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الإعداداتُ فهرسٌ، والمفاتيحُ الخطِرة خلف بابٍ واحدٍ لصاحبه.
 *
 * ═══ ما تحرسه ═══
 *
 * ١) الفهرس: بطاقةٌ لكلّ قسم، وكلُّ قسمٍ حاضرٌ في الصفحة.
 * ٢) الوسومُ متوازنة: قسمٌ يُلفّ بوسمٍ لا يُغلق يكسر الصفحة كلَّها.
 * ٣) الذكاءُ الاصطناعيّ للمطوّر وحدَه — إخفاءً ومنعاً على المسار.
 * ٤) مفتاحا الأتمتة والقوالب لمدير المكتب وحدَه، ولا بابَ ثانيَ لهما.
 * ٥) والفتحُ ينزّل القواعدَ الجاهزة، والإغلاقُ يُطفئها ولا يحذفها.
 */
class SettingsHubTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    // ─────────────────────────────────────────────── الفهرس

    /** فهرسٌ فيه بطاقةٌ لكلّ قسم — ورجوعٌ من كلّ قسم. */
    public function test_the_page_opens_as_an_index_with_a_card_per_section(): void
    {
        $html = $this->actingAs($this->user('admin'))->get(route('settings.index'))->assertOk()->getContent();

        $this->assertStringContainsString("sec === 'home'", $html, 'لا فهرسَ يُفتح عليه');
        $this->assertStringContainsString('رجوع', $html, 'لا مخرجَ من القسم');

        foreach (['office', 'brand', 'whatsapp', 'mail', 'notify', 'portal', 'system', 'engines'] as $sec) {
            $this->assertStringContainsString("go('{$sec}')", $html, "لا بطاقةَ للقسم «{$sec}»");
            $this->assertStringContainsString("sec === '{$sec}'", $html, "بطاقةٌ بلا قسمٍ خلفها: «{$sec}»");
        }
    }

    /**
     * المواعيدُ والحضورُ للمطوّر وحدَه — بطاقةً وقسماً معاً.
     *
     * إخفاءُ البطاقة وحدَه يترك القسمَ يُفتح بـ?sec= ويُرسَل حقولُه.
     * والقيمُ المحفوظةُ لا تُمسّ: المتحكّمُ لا يكتب أيّامَ العمل ولا
     * الحضورَ إلا إذا وصله القسمُ صراحةً (appt_section / hr_section).
     */
    public function test_appointments_and_attendance_belong_to_the_developer(): void
    {
        $admin = $this->actingAs($this->user('admin'))->get(route('settings.index', ['sec' => 'appointments']))->assertOk()->getContent();

        foreach (['appointments', 'attendance'] as $sec) {
            $this->assertStringNotContainsString("go('{$sec}')", $admin, "بطاقةُ «{$sec}» معروضةٌ لصاحب المكتب");
            $this->assertStringNotContainsString("sec === '{$sec}'", $admin, "قسمُ «{$sec}» يُرسَم لصاحب المكتب");
        }

        // ومن طلبه بالرابط عاد إلى الفهرس لا إلى قسمٍ لا يملكه
        $this->assertMatchesRegularExpression('/<div x-show="sec === \'home\'" class=/', $admin);

        $dev = $this->actingAs($this->user('developer'))->get(route('settings.index'))->assertOk()->getContent();
        foreach (['appointments', 'attendance'] as $sec) {
            $this->assertStringContainsString("go('{$sec}')", $dev, "المطوّرُ فقد بطاقةَ «{$sec}»");
        }
    }

    /** ووسومُ الصفحة متوازنة — لكلّ دورٍ على حدة. */
    public function test_the_page_closes_every_tag(): void
    {
        foreach (['admin', 'developer'] as $role) {
            $html = $this->actingAs($this->user($role))->get(route('settings.index'))->assertOk()->getContent();

            $this->assertSame(
                preg_match_all('/<\/div>/i', $html),
                preg_match_all('/<div\b/i', $html),
                "وسمُ div مفتوحٌ بلا إغلاقٍ عند {$role}"
            );
        }
    }

    /**
     * القسمُ يُحسب في الخادم — الصفحةُ ليست رهينةَ جافاسكربت.
     *
     * لو تُرك الأمرُ للمتصفّح وحدَه لصارت البطاقاتُ أزراراً لا تفتح
     * شيئاً إن تعثّر التحميل. فالبطاقاتُ روابطُ حقيقيةٌ بعناوينَ صحيحة،
     * و?sec= يقرّر الظاهرَ قبل أن يعمل سطرُ جافاسكربت واحد.
     */
    public function test_a_named_section_renders_open_without_javascript(): void
    {
        $admin = $this->user('admin');

        $html = $this->actingAs($admin)->get(route('settings.index', ['sec' => 'mail']))->assertOk()->getContent();

        // قسمُ البريد ظاهرٌ ابتداءً، والفهرسُ مخفيٌّ ابتداءً — من الخادم
        $this->assertMatchesRegularExpression('/<div x-show="sec === \'mail\'" class=/', $html, 'قسمُ البريد مخفيٌّ رغم طلبه');
        $this->assertMatchesRegularExpression('/<div x-show="sec === \'home\'" style="display:none"/', $html, 'الفهرسُ ظاهرٌ فوق القسم');
        $this->assertMatchesRegularExpression('/<div x-show="inForm" class=/', $html, 'زرُّ الحفظ مخفيٌّ في قسمٍ يُحفظ');

        // والبطاقاتُ روابطُ حقيقية لا أزرارٌ تحتاج مستمعاً
        $this->assertStringContainsString('href="' . route('settings.index', ['sec' => 'office']) . '"', $html);

        // وقسمٌ خارج النموذج لا يُظهر زرَّ حفظٍ لا يحفظ شيئاً
        $wa = $this->actingAs($admin)->get(route('settings.index', ['sec' => 'whatsapp']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div x-show="inForm" style="display:none"/', $wa, 'زرُّ حفظٍ وحيدٌ تحت قسمٍ لا يخصّه');

        // ومجهولٌ يعود إلى الفهرس بلا خطأ
        $bad = $this->actingAs($admin)->get(route('settings.index', ['sec' => '"><script>']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div x-show="sec === \'home\'" class=/', $bad);
    }

    // ─────────────────────────────────────────────── الذكاء الاصطناعي

    /**
     * مفتاحُ الذكاء الاصطناعيّ لا يُعرض لصاحب المكتب ولا يقبل طلبَه.
     *
     * الإخفاءُ وحدَه ليس حارساً: من عرف العنوان أرسل الطلبَ بلا زرّ.
     */
    public function test_ai_settings_belong_to_the_developer_alone(): void
    {
        $admin = $this->user('admin');

        $html = $this->actingAs($admin)->get(route('settings.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('ai_api_key', $html, 'مفتاحُ الذكاء معروضٌ لصاحب المكتب');

        // الحارسُ يردّ بتحويلٍ لا بـ403 (سياسةُ RoleMiddleware في هذا
        // التطبيق) — والمهمُّ أنّ الطلبَ لا يصل، لا شكلُ الردّ
        $before = \App\Support\AiSettings::provider();

        $this->actingAs($admin)->post(route('settings.ai.update'), [
            'ai_provider' => 'gemini', 'ai_model' => 'gemini-2.0-flash',
        ])->assertRedirect();

        $this->actingAs($admin)->delete(route('settings.ai.destroy'))->assertRedirect();

        $this->assertSame($before, \App\Support\AiSettings::provider(), 'مرّ طلبُ صاحب المكتب رغم المنع');

        // والمطوّرُ يراه كاملاً
        $dev = $this->actingAs($this->user('developer'))->get(route('settings.index'))->assertOk()->getContent();
        $this->assertStringContainsString('ai_api_key', $dev);
    }

    // ─────────────────────────────────────────────── المحرّكان

    /** القوالبُ الذكية مفتوحةٌ في كلّ مكتبٍ بلا إعداد. */
    public function test_smart_templates_ship_open_in_every_office(): void
    {
        $this->assertTrue(OfficeEngines::templatesOn(), 'مكتبٌ جديدٌ يفتح والقوالبُ مغلقة');

        $this->actingAs($this->user('admin'))->get(route('case-templates.index'))->assertOk();
    }

    /** وإغلاقُها يُغلق البابَ لا الرابطَ وحدَه. */
    public function test_closing_templates_closes_the_route_too(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->put(route('settings.engines'), ['engine' => 'templates', 'on' => '0'])
            ->assertRedirect();

        $this->assertFalse(OfficeEngines::templatesOn());

        $this->actingAs($admin)->get(route('case-templates.index'))
            ->assertRedirect(route('dashboard'));

        // ولا يظهر في الشريط الجانبيّ
        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('القوالب الذكية', $html);
    }

    /**
     * مفتاحُ الأتمتة لمدير المكتب وحدَه — ولا بابَ ثانيَ إليه.
     *
     * كان داخل مركز الأتمتة، فمن يملك automations.manage — ولو
     * موظّفاً — يُطفئ محرّكَ المكتب كلَّه.
     */
    public function test_only_the_office_manager_holds_the_engine_switch(): void
    {
        $this->actingAs($this->user('staff'))
            ->put(route('settings.engines'), ['engine' => 'automation', 'on' => '1'])
            ->assertRedirect();

        $this->assertFalse(OfficeEngines::automationOn(), 'موظّفٌ فتح محرّكَ المكتب');

        // والبابُ القديمُ لم يُترك مفتوحاً بلا زرّ
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('automations.engine'), 'بقي مسارٌ ثانٍ للمفتاح');
    }

    /** الفتحُ ينزّل القواعدَ الجاهزة كاملةً وينشّطها. */
    public function test_opening_automation_brings_the_ready_rules_down(): void
    {
        $admin = $this->user('admin');

        $this->assertSame(0, Automation::count());

        $this->actingAs($admin)->put(route('settings.engines'), ['engine' => 'automation', 'on' => '1'])
            ->assertRedirect();

        $this->assertTrue(OfficeEngines::automationOn());
        $this->assertGreaterThan(0, Automation::count(), 'فُتح المحرّكُ بلا قاعدةٍ واحدة');
        $this->assertSame(0, Automation::where('is_active', false)->count(), 'نزلت قواعدُ مطفأة');
    }

    /** والإغلاقُ يُطفئها ولا يحذف منها شيئاً — والعودةُ بضغطة. */
    public function test_closing_automation_silences_the_rules_without_deleting_one(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->put(route('settings.engines'), ['engine' => 'automation', 'on' => '1']);
        $seeded = Automation::count();

        $this->actingAs($admin)->put(route('settings.engines'), ['engine' => 'automation', 'on' => '0']);

        $this->assertFalse(OfficeEngines::automationOn());
        $this->assertSame($seeded, Automation::count(), 'حُذفت قاعدةٌ عند الإغلاق');
        $this->assertSame($seeded, Automation::where('is_active', false)->count(), 'بقيت قواعدُ نشطةً بعد الإغلاق');

        // والعودة
        $this->actingAs($admin)->put(route('settings.engines'), ['engine' => 'automation', 'on' => '1']);
        $this->assertSame($seeded, Automation::where('is_active', true)->count(), 'لم تعد القواعدُ بالفتح');
    }

    /** ومركزُ الأتمتة لم يعد يحمل زرَّ إطفاءٍ في متن صفحته. */
    public function test_the_automation_page_no_longer_carries_the_switch(): void
    {
        $admin = $this->user('admin');
        Setting::set(OfficeEngines::KEY_AUTOMATION, '1', 'automation');

        $html = $this->actingAs($admin)->get(route('automations.index'))->assertOk()->getContent();

        $this->assertStringContainsString('المفتاح في الإعدادات', $html, 'لا دلالةَ على موضع المفتاح');
        $this->assertStringNotContainsString('toggle-engine', $html, 'بقي زرُّ الإطفاء في الصفحة');
    }
}
