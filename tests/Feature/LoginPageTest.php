<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * صفحة تسجيل الدخول بهوية مُداوَلة — الاختبارات تحرس ما يجب ألّا ينكسر:
 * المصادقة، تذكّرني، إظهار كلمة المرور، التحقق، الرسائل، الجلسة، الأمان.
 */
class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_carries_mudawala_identity_and_links_to_the_platform(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('مُداوَلة', false)
            ->assertSee(\App\Support\Mudawala::url(), false)
            ->assertSee('rel="icon"', false)
            ->assertSee('name="csrf-token"', false)
            ->assertDontSee('LexPro', false);
    }

    public function test_page_shows_the_office_name_not_a_hardcoded_brand(): void
    {
        Setting::set('office_name', 'مكتب البيان للمحاماة');

        $this->get('/login')
            ->assertOk()
            ->assertSee('مكتب البيان للمحاماة', false)
            // هوية المنتج تبقى ظاهرة إلى جانب اسم المكتب
            ->assertSee('مُداوَلة', false);
    }

    public function test_office_logo_appears_on_login_when_uploaded(): void
    {
        $this->get('/login')->assertOk()->assertDontSee(route('office.logo'), false);

        Storage::disk('local')->put('office/logo.png', 'x');
        Setting::set('office_logo_path', 'office/logo.png');
        Setting::set('office_logo_updated_at', '123');

        $this->get('/login')->assertOk()->assertSee(route('office.logo'), false);
    }

    public function test_theme_and_language_controls_are_present(): void
    {
        // بالعربية يعرض التذييل رابط التحويل إلى الإنجليزية، والعكس
        $this->withSession(['locale' => 'ar'])->get('/login')
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            // السمة المحفوظة تُطبَّق قبل الرسم (نفس مفتاح 'theme' المستخدم داخل النظام)
            ->assertSee("localStorage.getItem('theme')", false)
            ->assertSee(route('language.switch', 'en'), false);

        $this->withSession(['locale' => 'en'])->get('/login')
            ->assertOk()
            ->assertSee(route('language.switch', 'ar'), false);
    }

    public function test_page_switches_direction_with_the_locale(): void
    {
        $this->withSession(['locale' => 'ar'])->get('/login')
            ->assertOk()->assertSee('dir="rtl"', false);

        $this->withSession(['locale' => 'en'])->get('/login')
            ->assertOk()->assertSee('dir="ltr"', false);
    }

    public function test_form_keeps_csrf_password_toggle_and_remember_controls(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('data-eye-btn', false)
            ->assertSee('name="remember"', false)
            ->assertSee('autocomplete="current-password"', false);
    }

    public function test_remember_me_actually_issues_a_remember_cookie(): void
    {
        $user = User::factory()->create([
            'email' => 'remember@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'remember@example.com',
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // الخانة كانت شكلية: لم تكن تُمرَّر إلى attempt() فلا تُصدر كوكي البقاء
        $response->assertCookie(auth()->guard()->getRecallerName());
    }

    public function test_without_remember_the_token_is_left_untouched(): void
    {
        $user = User::factory()->create([
            'email' => 'plain@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        $before = $user->remember_token;

        $this->post('/login', ['email' => 'plain@example.com', 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertSame($before, $user->fresh()->remember_token);
    }

    public function test_disabled_account_gets_a_visible_reason(): void
    {
        User::factory()->create([
            'email' => 'off@example.com',
            'password' => 'password',
            'is_active' => false,
        ]);

        $this->post('/login', ['email' => 'off@example.com', 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHas('login_error');

        $this->assertGuest();

        // الرسالة تظهر فعلاً على الصفحة وليست مبتلعة
        $this->followingRedirects()
            ->post('/login', ['email' => 'off@example.com', 'password' => 'password'])
            ->assertSee('تم تعطيل حسابك', false);
    }

    public function test_wrong_password_is_reported_and_never_authenticates(): void
    {
        User::factory()->create([
            'email' => 'who@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->followingRedirects()
            ->post('/login', ['email' => 'who@example.com', 'password' => 'nope'])
            ->assertOk()
            ->assertSee('البريد الإلكتروني أو كلمة المرور غير صحيحة', false);

        $this->assertGuest();
    }

    public function test_session_is_regenerated_on_login(): void
    {
        User::factory()->create([
            'email' => 'sess@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => 'sess@example.com', 'password' => 'password']);

        $this->assertNotSame($before, session()->getId(), 'يجب تدوير معرّف الجلسة بعد الدخول (Session Fixation)');
    }

    /** مفتاحُ القفل: البريدُ والعنوانُ معاً — انظر الاختبار الذي يليه. */
    private function lockKey(string $email, string $ip = '127.0.0.1'): string
    {
        return 'login_lock_' . md5($email . '|' . $ip);
    }

    public function test_five_failed_attempts_lock_the_account(): void
    {
        User::factory()->create([
            'email' => 'lock@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'lock@example.com', 'password' => 'bad']);
        }

        $this->assertTrue(Cache::has($this->lockKey('lock@example.com')));
        $this->assertGuest();
    }

    /**
     * ═══ والقفلُ على المُحاوِل لا على صاحب الحساب ═══
     *
     * كان المفتاحُ بريدَ الحساب وحدَه، فخمسُ محاولاتٍ خاطئةٍ من أيّ
     * زائرٍ تُغلق حسابَ مدير المكتب ربعَ ساعة — تُعاد كلَّ ربع ساعةٍ
     * فيبقى خارج نظامه، وتُغرَق قناةُ التنبيهات وسجلُّ التدقيق معه.
     */
    public function test_an_attacker_locks_only_themselves_out(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        // المهاجمُ من عنوانه
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'victim@example.com', 'password' => 'bad']);
        }

        $this->assertTrue(Cache::has($this->lockKey('victim@example.com', '203.0.113.7')), 'المهاجمُ لم يُقفل على نفسه');
        $this->assertFalse(Cache::has($this->lockKey('victim@example.com')), 'قُفل الحسابُ على صاحبه');

        // وصاحبُ الحساب يدخل من جهازه كأنّ شيئاً لم يكن
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        $this->post('/login', ['email' => 'victim@example.com', 'password' => 'password']);
        $this->assertAuthenticated();
    }

    public function test_a_locked_account_is_refused_even_with_the_right_password(): void
    {
        User::factory()->create([
            'email' => 'locked@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Cache::put($this->lockKey('locked@example.com'), true, now()->addMinutes(15));

        $this->followingRedirects()
            ->post('/login', ['email' => 'locked@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertSee('تم قفل الحساب مؤقتاً', false);

        $this->assertGuest();
    }

    public function test_rate_limited_guest_lands_back_on_login_not_the_dashboard(): void
    {
        // حدّ المحاولات (throttle:5,1) كان يُترجَم إلى تحويل نحو لوحة التحكم
        // فيُطرد الزائر بلا أي رسالة — الآن يعود لصفحة الدخول ويقرأ السبب
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/login', ['email' => 'flood@example.com', 'password' => 'bad']);
        }

        $response->assertRedirect('/login')->assertSessionHas('login_error');
        $this->assertGuest();
    }

    public function test_forgot_password_falls_back_to_a_hint_instead_of_a_dead_link(): void
    {
        $this->withSession(['locale' => 'ar'])->get('/login')
            ->assertOk()
            ->assertSee('data-forgot-hint', false)
            ->assertSee('استعادة كلمة المرور تتم عبر مدير المكتب', false);
    }

    public function test_login_page_is_not_indexed(): void
    {
        $this->get('/login')->assertOk()->assertSee('noindex', false);
    }
}
