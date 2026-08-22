<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * سياسة CSP في نظام المكتب صارمة: script-src بلا 'unsafe-inline'.
 * أي <script> بلا nonce يحجبه المتصفّح بصمت — لا رسالة خطأ ولا أثر،
 * فتبدو الصفحة سليمة وهي معطّلة. هذه الاختبارات تمنع تكرار ذلك.
 */
class ClientPortalCspTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('client-portal:lookup:127.0.0.1');
        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
    }

    private function scriptsCarryNonce(string $html): bool
    {
        // نتجاهل application/ld+json وما شابهه — ليست شيفرة تُنفَّذ
        preg_match_all('/<script(?![^>]*type="application\/)([^>]*)>/i', $html, $matches);

        foreach ($matches[1] as $attributes) {
            if (!str_contains($attributes, 'nonce=')) {
                return false;
            }
        }

        return true;
    }

    public function test_the_login_page_scripts_all_carry_a_nonce(): void
    {
        $html = $this->get(route('client.access'))->assertOk()->getContent();

        $this->assertTrue(
            $this->scriptsCarryNonce($html),
            'صفحة الدخول تحوي <script> بلا nonce — ستُحجب بصمت.'
        );
    }

    public function test_the_portal_pages_scripts_all_carry_a_nonce(): void
    {
        $client = Client::factory()->create(['national_id' => '5555555555', 'phone' => '+968 99887766']);
        LegalCase::factory()->create(['client_id' => $client->id]);

        $this->post(route('client.access.lookup'), ['national_id' => '5555555555']);
        $this->post(route('client.access.verify'), ['digits' => '766']);

        foreach ([
            route('client.portal.home'),
            route('client.portal.cases'),
            route('client.portal.account'),
        ] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertTrue(
                $this->scriptsCarryNonce($html),
                "الصفحة {$url} تحوي <script> بلا nonce."
            );
        }
    }

    public function test_the_case_page_scripts_carry_a_nonce(): void
    {
        $client = Client::factory()->create(['national_id' => '6666666666', 'phone' => '+968 91112233']);
        $case = LegalCase::factory()->create(['client_id' => $client->id]);

        $this->post(route('client.access.lookup'), ['national_id' => '6666666666']);
        $this->post(route('client.access.verify'), ['digits' => '233']);

        $html = $this->get(route('client.portal.case', $case->id))->assertOk()->getContent();

        $this->assertTrue($this->scriptsCarryNonce($html), 'صفحة القضية تحوي <script> بلا nonce.');
        $this->assertStringContainsString('data-acc', $html, 'أزرار طيّ الأقسام مفقودة.');
    }

    /** المعالجات المضمَّنة (onclick ونحوها) لا تعمل تحت هذه السياسة إطلاقاً */
    public function test_the_portal_uses_no_inline_event_handlers(): void
    {
        $html = $this->get(route('client.access'))->assertOk()->getContent();

        foreach (['onclick=', 'onsubmit=', 'onchange=', 'oninput=', 'onload='] as $handler) {
            $this->assertStringNotContainsString(
                $handler,
                $html,
                "معالج مضمَّن {$handler} لا يعمل مع سياسة CSP هنا."
            );
        }
    }
}
