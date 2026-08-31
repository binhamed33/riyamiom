<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\SetupDoctor;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * معالجُ الربط — يقول أين توقّف المكتب بالضبط.
 *
 * ═══ ما يحرسه ═══
 *
 * ١) خطوةٌ واحدةٌ «تالية» لا خمس: ما لا يصحّ إلا بعد سابقته يُعرض
 *    منتظِراً لا متعثّراً، وإلا عاد التشتّتُ الذي وُضع المعالج لرفعه.
 *
 * ٢) «سجّلتُ العنوان ولم أصل إلى شيء» أشهرُ تعثّر: يُسجَّل العنوان
 *    ويُنسى زرُّ Manage تحته. فيُسأل Meta عن الاشتراكات، ويُفرَّق بين
 *    «لا اشتراكَ البتّة» و«اشتراكٌ ناقص» — علاجُهما مختلف.
 *
 * ٣) والقوالبُ لا تمنع الجاهزية: الاستقبالُ والردّ داخل النافذة
 *    يعملان بلا قالب، وعدُّها شرطاً يُوهم المكتبَ أنّه لم يُنهِ شيئاً.
 *
 * ٤) ولا يخرج من الفحص رمزٌ ولا سرّ — الاستجابةُ تُقرأ في المتصفّح.
 */
class WhatsAppSetupWizardTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'EAAG-permanent-token-value-for-testing-0123456789';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function credentials(): void
    {
        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString(self::TOKEN), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_APP_SECRET, Crypt::encryptString('app-secret-0123456789'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_WABA_ID, '999888777', 'whatsapp');
    }

    private function doctor(bool $probe = false): array
    {
        return app(SetupDoctor::class)->report($probe);
    }

    /** @param array<int, array<string, mixed>> $steps */
    private function step(array $report, string $key): array
    {
        foreach ($report['steps'] as $step) {
            if ($step['key'] === $key) {
                return $step;
            }
        }

        $this->fail('لا خطوة اسمها ' . $key);
    }

    // ── الترتيب ──────────────────────────────────────────────────

    public function test_an_office_that_entered_nothing_is_pointed_at_the_first_step_only(): void
    {
        $report = $this->doctor();

        $this->assertSame('credentials', $report['next']);
        $this->assertSame(SetupDoctor::NEXT, $this->step($report, 'credentials')['state']);

        // وما بعدها ينتظر لا يتعثّر
        $this->assertSame(SetupDoctor::WAITING, $this->step($report, 'webhook')['state']);
        $this->assertSame(SetupDoctor::WAITING, $this->step($report, 'first_message')['state']);
        $this->assertFalse($report['ready']);
    }

    public function test_the_first_step_names_exactly_what_is_missing(): void
    {
        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString(self::TOKEN), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');

        $reason = $this->step($this->doctor(), 'credentials')['reason'];

        $this->assertStringContainsString('معرّف حساب الأعمال', $reason);
        $this->assertStringContainsString('سرّ التطبيق', $reason);
        $this->assertStringNotContainsString('معرّف الرقم', $reason);
    }

    // ── الويبهوك ─────────────────────────────────────────────────

    /** لم يصل إشعارٌ قطّ ⇐ العنوان لم يُسجَّل عند Meta. */
    public function test_a_webhook_that_never_fired_is_reported_as_unregistered(): void
    {
        $this->credentials();
        WhatsAppSettings::rememberIdentity('96812345678', 'مكتب المحاماة');

        $report = $this->doctor();

        $this->assertSame(SetupDoctor::NEXT, $this->step($report, 'webhook')['state']);
        $this->assertSame('webhook', $report['next']);
    }

    public function test_a_webhook_that_fired_once_is_done(): void
    {
        $this->credentials();
        WhatsAppSettings::rememberIdentity('96812345678', 'مكتب المحاماة');
        WhatsAppSettings::touchWebhook();

        $this->assertSame(SetupDoctor::DONE, $this->step($this->doctor(), 'webhook')['state']);
    }

    // ── الاشتراك في الحقول ───────────────────────────────────────

    /** أشهرُ تعثّر: العنوان مسجَّل والحقول لا. */
    public function test_a_registered_webhook_with_no_subscribed_fields_says_so_plainly(): void
    {
        $this->credentials();
        WhatsAppSettings::touchWebhook();

        Http::fake([
            '*/111222333*' => Http::response(['display_phone_number' => '96812345678', 'verified_name' => 'مكتب'], 200),
            '*/subscribed_apps*' => Http::response(['data' => []], 200),
        ]);

        $fields = $this->step($this->doctor(probe: true), 'fields');

        $this->assertSame(SetupDoctor::NEXT, $fields['state']);
        $this->assertStringContainsString('غيرُ مشترِكٍ في أيّ حقل', $fields['reason']);
        $this->assertStringContainsString('Manage', (string) $fields['action']);
    }

    /** واشتراكٌ ناقص شكوى أخرى — والعلاج مختلف. */
    public function test_a_partial_subscription_names_the_missing_field(): void
    {
        $this->credentials();
        WhatsAppSettings::touchWebhook();

        Http::fake([
            '*/111222333*' => Http::response(['display_phone_number' => '96812345678', 'verified_name' => 'مكتب'], 200),
            '*/subscribed_apps*' => Http::response([
                'data' => [['subscribed_fields' => ['message_template_status_update']]],
            ], 200),
        ]);

        $fields = $this->step($this->doctor(probe: true), 'fields');

        $this->assertSame(SetupDoctor::NEXT, $fields['state']);
        $this->assertStringContainsString('ينقص: messages', $fields['reason']);
    }

    public function test_a_full_subscription_passes(): void
    {
        $this->credentials();
        WhatsAppSettings::touchWebhook();

        Http::fake([
            '*/111222333*' => Http::response(['display_phone_number' => '96812345678', 'verified_name' => 'مكتب'], 200),
            '*/subscribed_apps*' => Http::response([
                'data' => [['subscribed_fields' => ['messages', 'message_template_status_update']]],
            ], 200),
        ]);

        $this->assertSame(SetupDoctor::DONE, $this->step($this->doctor(probe: true), 'fields')['state']);
    }

    /** وتعذّرُ السؤال ليس «لا اشتراك»: يُقال إنّه تعذّر. */
    public function test_a_failed_probe_is_not_reported_as_an_empty_subscription(): void
    {
        $this->credentials();
        WhatsAppSettings::touchWebhook();

        Http::fake([
            '*/111222333*' => Http::response(['display_phone_number' => '96812345678'], 200),
            '*/subscribed_apps*' => Http::response(['error' => ['message' => 'bad token', 'code' => 190]], 401),
        ]);

        $fields = $this->step($this->doctor(probe: true), 'fields');

        $this->assertSame(SetupDoctor::NEXT, $fields['state']);
        $this->assertStringNotContainsString('غيرُ مشترِكٍ في أيّ حقل', $fields['reason']);
    }

    // ── الجاهزية ────────────────────────────────────────────────

    /** القوالبُ اختياريّة — لا تمنع «جاهز». */
    public function test_an_office_with_no_approved_template_is_still_ready_to_receive(): void
    {
        $this->credentials();
        WhatsAppSettings::touchWebhook();

        $contact = WhatsAppContact::create(['wa_id' => '96891234567']);
        $conversation = WhatsAppConversation::create([
            'contact_id' => $contact->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 1,
            'last_inbound_at' => now(),
        ]);
        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'wamid' => 'wamid.FIRST',
            'direction' => WhatsAppMessage::IN,
            'type' => 'text',
            'body' => 'مرحبا',
            'status' => WhatsAppMessage::STATUS_DELIVERED,
        ]);

        Http::fake([
            '*/111222333*' => Http::response(['display_phone_number' => '96812345678', 'verified_name' => 'مكتب'], 200),
            '*/subscribed_apps*' => Http::response([
                'data' => [['subscribed_fields' => ['messages']]],
            ], 200),
        ]);

        $report = $this->doctor(probe: true);

        $this->assertTrue($report['ready'], 'قالبٌ غير معتمَد منع الجاهزية — والاستقبال يعمل بدونه');
        $this->assertSame(SetupDoctor::NEXT, $this->step($report, 'templates')['state']);
        $this->assertFalse($this->step($report, 'templates')['required']);
    }

    public function test_an_approved_template_completes_the_optional_step(): void
    {
        WhatsAppTemplate::create([
            'name' => 'session_reminder', 'language' => 'ar', 'status' => 'APPROVED', 'body' => 'تذكير {{1}}',
        ]);

        $this->assertSame(SetupDoctor::DONE, $this->step($this->doctor(), 'templates')['state']);
    }

    // ── المسار ───────────────────────────────────────────────────

    public function test_the_checkup_endpoint_returns_the_steps_and_no_secret(): void
    {
        $this->credentials();
        WhatsAppSettings::touchWebhook();

        Http::fake([
            '*/111222333*' => Http::response(['display_phone_number' => '96812345678', 'verified_name' => 'مكتب'], 200),
            '*/subscribed_apps*' => Http::response(['data' => [['subscribed_fields' => ['messages']]]], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('settings.whatsapp.checkup'))
            ->assertOk()
            ->assertJsonStructure(['steps' => [['key', 'title', 'state', 'reason']], 'ready', 'next']);

        $body = $response->getContent();

        $this->assertStringNotContainsString(self::TOKEN, $body);
        $this->assertStringNotContainsString('app-secret-0123456789', $body);
        $this->assertStringNotContainsString('EAAG', $body);
    }

    public function test_a_lawyer_cannot_run_the_checkup(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->actingAs($lawyer)
            ->post(route('settings.whatsapp.checkup'))
            ->assertRedirect(route('dashboard'));
    }

    /** ولا يُسأل Meta عند مجرّد فتح الصفحة. */
    public function test_opening_the_settings_page_never_calls_meta(): void
    {
        $this->credentials();
        Http::fake();

        $this->actingAs($this->admin)->get(route('settings.index'))->assertOk();

        Http::assertNothingSent();
    }
}
