<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\WhatsApp\SetupDoctor;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * إتمامُ الربط نيابةً عن المكتب.
 *
 * ═══ ما تحرسه ═══
 *
 * ١) الخطواتُ الأربع التي كان المكتب يتعثّر فيها تُنفَّذ بنداءات: يُستنتج
 *    الحساب والرقم من الرمز، ويُسجَّل العنوان، ويُشترَك في الحساب.
 *
 * ٢) ولا يتوقّف عند أوّل إخفاق: ما تمّ يُروى وما لم يتمّ يُروى، فلا
 *    يُعيد المكتب كلَّ شيءٍ من أوّله بسبب خطوةٍ واحدة.
 *
 * ٣) ورمزُ التطبيق (`{id}|{secret}`) أقوى من رمز الوصول: يتصرّف في
 *    التطبيق نفسه. فلا يخرج في استجابةٍ ولا يُخزَّن.
 *
 * ٤) وحسابان في الرمز لا يُختار أحدُهما صامتاً: ربطُ المكتب بحسابٍ
 *    ليس حسابه أسوأ من أن يُسأل.
 */
class WhatsAppAutowireTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'EAAG-permanent-token-value-for-testing-0123456789';
    private const SECRET = 'app-secret-value-0123456789';
    private const APP_ID = '1234567890';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString(self::TOKEN), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_APP_SECRET, Crypt::encryptString(self::SECRET), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_APP_ID, self::APP_ID, 'whatsapp');
    }

    /** @param array<string, mixed> $overrides */
    private function fakeMeta(array $overrides = []): void
    {
        Http::fake(array_merge([
            '*/debug_token*' => Http::response([
                'data' => [
                    'granular_scopes' => [
                        ['scope' => 'whatsapp_business_management', 'target_ids' => ['999888777']],
                    ],
                ],
            ], 200),
            '*/999888777/phone_numbers*' => Http::response([
                'data' => [['id' => '111222333', 'display_phone_number' => '+968 1234 5678']],
            ], 200),
            '*/1234567890/subscriptions*' => Http::response(['success' => true], 200),
            '*/999888777/subscribed_apps*' => Http::response(['success' => true], 200),
            '*/111222333*' => Http::response([
                'display_phone_number' => '+968 1234 5678', 'verified_name' => 'مكتب المحاماة',
            ], 200),
            '*' => Http::response(['data' => []], 200),
        ], $overrides));
    }

    private function press(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->postJson(route('settings.whatsapp.autowire'));
    }

    // ── الطريق السعيد ────────────────────────────────────────────

    public function test_it_discovers_registers_and_subscribes_in_one_press(): void
    {
        $this->fakeMeta();

        $response = $this->press()->assertOk();

        $this->assertTrue($response->json('ok'), 'أخفقت خطوةٌ: ' . json_encode($response->json('failed'), JSON_UNESCAPED_UNICODE));

        // ما استُنتج حُفظ
        $this->assertSame('999888777', WhatsAppSettings::wabaId());
        $this->assertSame('111222333', WhatsAppSettings::phoneNumberId());

        // والعنوانُ سُجّل بعنواننا ورمزِ تحقّقنا نحن لا بما يكتبه إنسان
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/1234567890/subscriptions')
                && $request['callback_url'] === url('/webhooks/whatsapp')
                && $request['verify_token'] === WhatsAppSettings::verifyToken()
                && str_contains((string) $request['fields'], 'messages');
        });

        // والاشتراكُ عُقد على الحساب — لا يكفي تسجيل العنوان
        Http::assertSent(fn ($request) => str_contains($request->url(), '/999888777/subscribed_apps'));
    }

    /** رمزُ التطبيق يُبنى من المعرّف والسرّ — ولا يخرج في استجابة. */
    public function test_the_app_token_never_leaves_in_a_response(): void
    {
        $this->fakeMeta();

        $body = $this->press()->getContent();

        $this->assertStringNotContainsString(self::SECRET, $body);
        $this->assertStringNotContainsString(self::APP_ID . '|', $body);
        $this->assertStringNotContainsString(self::TOKEN, $body);
    }

    // ── الإخفاقات ───────────────────────────────────────────────

    /** إخفاقُ خطوةٍ لا يمحو ما تمّ قبلها. */
    public function test_a_failed_subscription_still_reports_what_succeeded(): void
    {
        $this->fakeMeta([
            '*/999888777/subscribed_apps*' => Http::response([
                'error' => ['message' => 'not permitted', 'code' => 200],
            ], 403),
        ]);

        $response = $this->press()->assertOk();

        $this->assertFalse($response->json('ok'));
        $this->assertNotEmpty($response->json('done'), 'أُخفيت الخطوات التي نجحت');
        $this->assertNotEmpty($response->json('failed'));
    }

    /** حسابان في الرمز: يُسأل المكتب ولا يُختار عنه. */
    public function test_two_accounts_are_not_silently_guessed(): void
    {
        $this->fakeMeta([
            '*/debug_token*' => Http::response([
                'data' => [
                    'granular_scopes' => [
                        ['scope' => 'whatsapp_business_management', 'target_ids' => ['111', '222']],
                    ],
                ],
            ], 200),
        ]);

        $this->press()->assertOk();

        $this->assertNull(WhatsAppSettings::wabaId(), 'رُبط المكتب بحسابٍ اختاره النظام عنه');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'subscribed_apps'));
    }

    /** ورمزٌ لا يخوّل حساباً: يُقال ذلك، ولا يُسجَّل ويبهوكٌ لحسابٍ مجهول. */
    public function test_a_token_with_no_account_says_so(): void
    {
        $this->fakeMeta([
            '*/debug_token*' => Http::response(['data' => ['granular_scopes' => []]], 200),
        ]);

        $response = $this->press()->assertOk();

        $this->assertFalse($response->json('ok'));
        $this->assertStringContainsString(
            'System User',
            implode(' ', (array) $response->json('failed')),
        );
    }

    // ── الصلاحية ────────────────────────────────────────────────

    public function test_a_lawyer_cannot_autowire(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->actingAs($lawyer)
            ->post(route('settings.whatsapp.autowire'))
            ->assertRedirect(route('dashboard'));
    }

    /** والزرُّ لا يُعرض أصلاً قبل أن تكتمل القيم الثلاث. */
    public function test_the_button_is_hidden_until_the_three_values_exist(): void
    {
        Setting::set(WhatsAppSettings::KEY_APP_ID, '', 'whatsapp');

        $this->actingAs($this->admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertDontSee('data-wa-autowire="1"', false);

        Setting::set(WhatsAppSettings::KEY_APP_ID, self::APP_ID, 'whatsapp');

        $this->actingAs($this->admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('data-wa-autowire="1"', false);
    }
}
