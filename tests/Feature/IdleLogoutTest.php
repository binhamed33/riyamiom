<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * خروجُ الخمول ثماني ساعاتٍ لا ساعة، ويقول سببَه على صفحة الدخول.
 *
 * محامٍ ترك تبويبَ المكتب في الخلفية ساعةً عاد فوجد صفحةَ الدخول بلا
 * كلمة — «كأنّه طردني». والرقمُ واحدٌ في config/session.php يقرؤه
 * المؤقّتُ والرسالةُ معاً، وعمرُ الجلسة على الخادم فوقه دائماً.
 */
class IdleLogoutTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'locale' => 'ar']);
    }

    /** المهلةُ ثماني ساعات، والمتصفّحُ يقرؤها من الإعداد لا من رقمٍ مكتوبٍ في القالب. */
    public function test_the_browser_timer_is_eight_hours_from_config(): void
    {
        $this->assertSame(480, (int) config('session.idle_logout_minutes'));

        Setting::set('subscription_status', 'active', 'system');
        $html = $this->actingAs($this->staff())->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('var TIMEOUT = 480 * 60 * 1000;', $html, 'المؤقّتُ ما زال على ساعة');
    }

    /**
     * وعمرُ الجلسة فوق المؤقّت بساعةٍ مهما قال .env — ملفّاتُ المكاتب
     * القائمة تحمل 120، ولو احتُرمت لسقط المستخدم في الفراغ بين المهلتين.
     */
    public function test_the_server_session_outlives_the_browser_timer(): void
    {
        $this->assertGreaterThanOrEqual((int) config('session.idle_logout_minutes') + 60, (int) config('session.lifetime'));
    }

    /** خروجُ الخمول يعود بصفحة دخولٍ تقول: خرجتَ بعد ثماني ساعاتٍ بلا نشاط — ولا انصرافَ سُجّل. */
    public function test_an_idle_logout_explains_itself_on_the_login_page(): void
    {
        app()->setLocale('ar');
        $user = $this->staff();

        $response = $this->actingAs($user)->post(route('logout'), ['idle' => '1'])->assertRedirect(route('login'));

        $this->assertGuest();

        $page = $this->get(route('login'))->assertOk();
        $page->assertSee('data-login-notice', false)
            ->assertSee('خرجتَ تلقائياً بعد 8 ساعاتٍ بلا نشاط')
            ->assertSee('لم يُسجَّل لك انصراف');
    }

    /** أمّا الخروجُ بالزرّ فلا رسالة: صاحبُه يعرف لماذا هو على صفحة الدخول. */
    public function test_a_manual_logout_shows_no_notice(): void
    {
        $this->actingAs($this->staff())->post(route('logout'))->assertRedirect(route('login'));

        $this->get(route('login'))->assertOk()->assertDontSee('data-login-notice', false);
    }
}
