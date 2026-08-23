<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\Mudawala;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الصفحتان اللتان تُفتحان حين لا يعمل شيء.
 *
 * صفحة انتهاء الاشتراك كانت تبني شكلها كلّه على Tailwind من شبكة
 * توزيع خارجية — وهي بالذات الصفحة التي تُفتح حين لا يعمل شيء. فإن
 * تأخّر ذلك المصدر أو حجبه مزوّد، انهارت إلى نصٍّ متراكم.
 */
class SystemDownPagesTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
    }

    private function expire(): void
    {
        Setting::set('subscription_status', SubscriptionService::STATUS_ACTIVE);
        Setting::set('subscription_end_at', now()->subDays(3)->toDateTimeString());
    }

    public function test_the_expiry_page_needs_nothing_from_outside_to_look_right()
    {
        $this->expire();

        $html = $this->actingAs($this->user())->get('/subscription-expired')->getContent();

        // لا سكربت خارجي ولا صفحة أنماط خارجية سوى الخطوط
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
        $this->assertStringNotContainsString('unpkg.com', $html);

        // والشكل كلّه في الصفحة نفسها
        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('.card {', $html);
    }

    public function test_the_expiry_page_reassures_about_the_data_first()
    {
        $this->expire();

        $this->actingAs($this->user())
            ->get('/subscription-expired')
            ->assertStatus(200)
            ->assertSee('محفوظة كما هي', false);
    }

    public function test_the_expiry_page_says_when_it_expired()
    {
        $this->expire();

        $this->actingAs($this->user())
            ->get('/subscription-expired')
            ->assertSee(now()->subDays(3)->format('Y-m-d'), false);
    }

    public function test_the_expiry_page_offers_email_only()
    {
        $this->expire();
        Setting::set('office_phone', '91234567');

        $html = $this->actingAs($this->user())->get('/subscription-expired')->getContent();

        $this->assertStringContainsString('mailto:', $html);
        // القناة المعتمدة البريد وحده — زرّ الواتساب أُزيل
        $this->assertStringNotContainsString('wa.me', $html);
        $this->assertStringNotContainsString('واتساب', $html);
    }

    public function test_a_suspended_subscription_says_suspended_not_expired()
    {
        Setting::set('subscription_status', SubscriptionService::STATUS_SUSPENDED);
        Setting::set('subscription_end_at', now()->addYear()->toDateTimeString());

        $this->actingAs($this->user())
            ->get('/subscription-expired')
            ->assertSee('متوقف', false);
    }

    public function test_both_down_pages_point_at_one_mudawala_address()
    {
        $this->expire();
        $url = Mudawala::url();

        $this->assertStringContainsString('mudawala.riyami.om', $url);

        $this->actingAs($this->user())
            ->get('/subscription-expired')
            ->assertSee($url, false);
    }

    public function test_no_view_still_hardcodes_the_old_address()
    {
        $hits = [];

        foreach (glob(resource_path('views') . '/{,*/,*/*/,*/*/*/}*.blade.php', GLOB_BRACE) as $file) {
            if (str_contains(file_get_contents($file), 'dev.riyami.om')) {
                $hits[] = str_replace(resource_path('views') . '/', '', $file);
            }
        }

        $this->assertSame([], $hits, 'قوالب لا تزال تكتب النطاق بيدها: ' . implode(', ', $hits));
    }
}
