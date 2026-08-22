<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * سبب رفض الدخول يقوله الخادم صراحةً.
 *
 * كانت الصفحة تخمّنه بالبحث عن عبارة في نصّ الصفحة العائدة — ونصّ
 * الصفحة يشمل نصّ السكربت، والسكربت يحمل العبارة في سطر الفحص. فكان
 * أول خطأ في كلمة المرور يظهر «تم تعليق تسجيل الدخول».
 */
class LoginErrorReasonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function user(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'staff@office.test',
            'password' => bcrypt('CorrectHorse#9'),
            'role' => 'lawyer',
            'is_active' => true,
        ], $attrs));
    }

    /**
     * حدّ المسار (٥ طلبات في الدقيقة) طبقة مستقلّة عن قفل الحساب.
     * نمسح كل مفتاح في الذاكرة المؤقتة عدا قفل الحساب، لنختبر القفل
     * نفسه لا الحدّ.
     */
    private function flushRouteThrottle(): void
    {
        $store = Cache::getStore();

        foreach (array_keys((fn () => $this->storage)->call($store)) as $key) {
            if (!str_starts_with($key, 'login_lock_')) {
                Cache::forget($key);
            }
        }
    }

    private function attempt(string $password, string $email = 'staff@office.test')
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('login'), ['email' => $email, 'password' => $password]);
    }

    public function test_a_wrong_password_says_so_and_never_says_locked(): void
    {
        $this->user();

        $response = $this->attempt('WrongPass#1');

        $response->assertStatus(401)->assertJson(['code' => 'invalid_credentials']);
        $this->assertStringNotContainsString('تعليق', $response->json('title'));
        $this->assertStringNotContainsString('قفل', $response->json('title'));
    }

    public function test_the_first_four_tries_all_say_wrong_password(): void
    {
        $this->user();

        foreach (range(1, 4) as $i) {
            $this->attempt('WrongPass#' . $i)
                ->assertStatus(401)
                ->assertJson(['code' => 'invalid_credentials']);
        }
    }

    public function test_the_fifth_try_locks_and_says_so(): void
    {
        $this->user();

        foreach (range(1, 4) as $i) {
            $this->attempt('WrongPass#' . $i);
        }

        $this->attempt('WrongPass#5')
            ->assertStatus(401)
            ->assertJson(['code' => 'locked']);
    }

    public function test_once_locked_even_the_right_password_is_refused_as_locked(): void
    {
        $this->user();

        foreach (range(1, 5) as $i) {
            $this->attempt('WrongPass#' . $i);
        }

        $this->flushRouteThrottle();

        $this->attempt('CorrectHorse#9')
            ->assertStatus(401)
            ->assertJson(['code' => 'locked']);
    }

    public function test_the_last_tries_warn_how_many_remain(): void
    {
        $this->user();

        foreach (range(1, 2) as $i) {
            $this->attempt('WrongPass#' . $i);
        }

        // المحاولة الثالثة: بقيت اثنتان
        $this->assertStringContainsString('محاولتان', $this->attempt('WrongPass#3')->json('detail'));
        // الرابعة: بقيت واحدة
        $this->assertStringContainsString('محاولة', $this->attempt('WrongPass#4')->json('detail'));
    }

    public function test_a_disabled_account_says_disabled_not_locked(): void
    {
        $this->user(['is_active' => false]);

        $this->attempt('CorrectHorse#9')
            ->assertStatus(401)
            ->assertJson(['code' => 'disabled']);
    }

    public function test_a_correct_password_still_gets_in(): void
    {
        $user = $this->user();

        $this->post(route('login'), [
            'email' => 'staff@office.test',
            'password' => 'CorrectHorse#9',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_browser_without_javascript_still_gets_the_old_flash_message(): void
    {
        $this->user();

        $this->post(route('login'), [
            'email' => 'staff@office.test',
            'password' => 'WrongPass#1',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('login_error', 'البريد الإلكتروني أو كلمة المرور غير صحيحة.');
    }

    public function test_a_successful_login_clears_the_counter(): void
    {
        $this->user();

        foreach (range(1, 3) as $i) {
            $this->attempt('WrongPass#' . $i);
        }

        $this->post(route('login'), [
            'email' => 'staff@office.test',
            'password' => 'CorrectHorse#9',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('logout'));

        // العدّاد صُفِّر: خطأ جديد يقول «كلمة مرور» لا «تعليق»
        $this->attempt('WrongPass#9')->assertJson(['code' => 'invalid_credentials']);
    }
}
