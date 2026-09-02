<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * انتهاءُ صلاحية الصفحة ليس بابَه شاشةٌ سوداء.
 *
 * صفحةٌ تُترك مفتوحةً ساعاتٍ ثمّ يُضغط زرُّها، فيكون رمزُ الحماية قد
 * انتهى مع الجلسة. والردُّ الافتراضيّ «419 PAGE EXPIRED» بالإنجليزية:
 * بلا سببٍ ولا رجوعٍ ولا نموذجٍ يُعاد ملؤه — فيظنّ المحامي أنّ النظام
 * سقط ويتصل بالدعم.
 */
class ExpiredPageTest extends TestCase
{
    use RefreshDatabase;

    private function probe(string $path): void
    {
        Route::middleware('web')->post($path, function () {
            throw new TokenMismatchException();
        });
    }

    /** زائرٌ غير مسجّل يعود إلى الدخول برسالةٍ يقرؤها هناك. */
    public function test_a_guest_returns_to_login_with_a_reason(): void
    {
        $this->probe('/__expiry-guest');

        $this->post('/__expiry-guest', ['email' => 'a@b.om', 'password' => 'secret'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('login_error');

        $this->assertStringContainsString('انتهت صلاحية الصفحة', (string) session('login_error'));
    }

    /** ومستخدمٌ مسجَّل يعود إلى صفحته نفسِها ونموذجُه محفوظ. */
    public function test_a_signed_in_user_returns_to_the_same_page_with_input(): void
    {
        $this->probe('/__expiry-user');

        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($user)
            ->post('/__expiry-user', ['title' => 'مذكرة نصفَ مكتوبة', 'password' => 'secret'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('مذكرة نصفَ مكتوبة', session('_old_input.title'), 'ضاع ما كُتب فيُكتب من جديد');
        $this->assertArrayNotHasKey('password', (array) session('_old_input'), 'كلمةُ المرور حُفظت في الجلسة');
    }

    /** و404 لا تُبتلع في طريق 419 — كلٌّ إلى معالجه. */
    public function test_other_http_errors_are_untouched(): void
    {
        $this->get('/__no-such-page-here')->assertNotFound();
    }
}
