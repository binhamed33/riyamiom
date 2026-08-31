<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * بابُ واتساب لا يُفتح إلا لمن يُثبت أنّه Meta.
 *
 * ═══ الخطر الذي تحرسه هذه الاختبارات ═══
 *
 * عنوانُ الويبهوك عامٌّ بالضرورة: تناديه Meta من خوادمها بلا جلسةٍ ولا
 * رمز CSRF. فلو قُبل ما يصله بلا تحقّق لاستطاع أيُّ من يعرف العنوان أن
 * يحقن رسائل في محادثات المكتب — رسائلَ تبدو من موكّل، يبني عليها
 * محامٍ تصرّفاً.
 *
 * والحارسُ الثاني: عدمُ التكرار. تُعيد Meta الإشعار إن تأخّر الردّ،
 * وتُعيده بلا سبب أحياناً — فبلا دفترٍ يُقيَّد فيه الحدث تظهر رسالةُ
 * الموكّل مرّتين ويُنفَّذ الردّ الآلي مرّتين وتُخصم رسالتان.
 */
class WhatsAppWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-app-secret-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(WhatsAppSettings::KEY_APP_SECRET, Crypt::encryptString(self::SECRET), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-test-token'), 'whatsapp');
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '96812345678', 'phone_number_id' => '111222333'],
                        'contacts' => [['profile' => ['name' => 'سالم'], 'wa_id' => '96891234567']],
                        'messages' => [[
                            'from' => '96891234567',
                            'id' => 'wamid.TEST1',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'السلام عليكم'],
                        ]],
                    ],
                ]],
            ]],
        ], $overrides);
    }

    private function hook(array $payload, ?string $secret = self::SECRET)
    {
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type' => 'application/json'];

        if ($secret !== null) {
            $headers['X-Hub-Signature-256'] = 'sha256=' . hash_hmac('sha256', $raw, $secret);
        }

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], $this->transformHeaders($headers), $raw);
    }

    private function transformHeaders(array $headers): array
    {
        $server = [];

        foreach ($headers as $key => $value) {
            $server['HTTP_' . str_replace('-', '_', strtoupper($key))] = $value;
        }

        return $server;
    }

    // ── التوقيع ──────────────────────────────────────────────────

    public function test_a_correctly_signed_payload_is_accepted(): void
    {
        $this->hook($this->payload())->assertOk();

        $this->assertDatabaseHas('whatsapp_webhook_events', ['event_key' => 'msg:wamid.TEST1']);
    }

    /** توقيعٌ بسرٍّ آخر — أي مُنتحِل — يُرفض ولا يُخزَّن منه شيء. */
    public function test_a_payload_signed_with_the_wrong_secret_is_refused(): void
    {
        $this->hook($this->payload(), 'attacker-secret')->assertForbidden();

        $this->assertSame(0, WhatsAppWebhookEvent::count(), 'خُزّن حدثٌ من حمولةٍ غير موثوقة');
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_a_payload_with_no_signature_at_all_is_refused(): void
    {
        $this->hook($this->payload(), null)->assertForbidden();

        $this->assertSame(0, WhatsAppWebhookEvent::count());
    }

    /**
     * وبلا سرِّ تطبيقٍ مضبوط: يُرفض كلُّ شيء.
     *
     * القبولُ حين لا يمكن التحقّق هو أسوأ الخيارين: مكتبٌ لم يُكمل
     * إعداده يصير بابُه مفتوحاً لمن يعرف نطاقه.
     */
    public function test_an_office_with_no_app_secret_refuses_everything(): void
    {
        Setting::set(WhatsAppSettings::KEY_APP_SECRET, '', 'whatsapp');

        $this->hook($this->payload())->assertForbidden();
        $this->assertSame(0, WhatsAppWebhookEvent::count());
    }

    /** حمولةٌ صحيحةُ التوقيع لكن لرقمٍ ليس رقمَ هذا المكتب تُطرح. */
    public function test_a_payload_for_a_foreign_phone_number_id_is_dropped(): void
    {
        $payload = $this->payload();
        $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = '999888777';

        $this->hook($payload)->assertOk();

        $this->assertSame(0, WhatsAppWebhookEvent::count(), 'قُبل حدثٌ لرقم مكتبٍ آخر');
    }

    // ── مصافحة التحقّق ───────────────────────────────────────────

    public function test_the_verification_handshake_echoes_the_challenge_for_the_right_token(): void
    {
        $token = WhatsAppSettings::verifyToken();

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=' . urlencode($token) . '&hub_challenge=abc123')
            ->assertOk()
            ->assertSee('abc123');
    }

    public function test_the_verification_handshake_refuses_a_wrong_token(): void
    {
        WhatsAppSettings::verifyToken();

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=guessed&hub_challenge=abc123')
            ->assertForbidden()
            ->assertDontSee('abc123');
    }

    /** ورمزُ التحقّق يُولَّد ولا يُكتبه إنسان — ولا يتغيّر بعد توليده. */
    public function test_the_verify_token_is_generated_once_and_is_long(): void
    {
        $first = WhatsAppSettings::verifyToken();
        $second = WhatsAppSettings::verifyToken();

        $this->assertSame($first, $second, 'تغيّر رمز التحقّق — يكسر الربط عند Meta بلا رسالة');
        $this->assertGreaterThanOrEqual(32, strlen($first));
    }

    // ── عدم التكرار ──────────────────────────────────────────────

    public function test_the_same_notification_twice_creates_one_event_only(): void
    {
        $this->hook($this->payload())->assertOk();
        $this->hook($this->payload())->assertOk();

        $this->assertSame(1, WhatsAppWebhookEvent::where('event_key', 'msg:wamid.TEST1')->count(),
            'إعادةُ Meta أنشأت حدثاً ثانياً');
    }

    /** وحتى لو عولج الحدث مرّتين: الرسالة لا تُكتب مرّتين. */
    public function test_processing_the_same_message_twice_creates_one_message(): void
    {
        $inbox = app(\App\Services\WhatsApp\InboxService::class);
        $message = ['from' => '96891234567', 'id' => 'wamid.X1', 'timestamp' => (string) now()->timestamp,
                    'type' => 'text', 'text' => ['body' => 'مرحبا']];

        $this->assertNotNull($inbox->ingestIncoming($message));
        $this->assertNull($inbox->ingestIncoming($message), 'استُوعبت نفس الرسالة مرّتين');

        $this->assertSame(1, WhatsAppMessage::where('wamid', 'wamid.X1')->count());
    }

    /** وحالاتُ التسليم الثلاث أحداثٌ مشروعة لا تكرار. */
    public function test_the_three_delivery_states_are_three_distinct_events(): void
    {
        foreach (['sent', 'delivered', 'read'] as $state) {
            $payload = [
                'object' => 'whatsapp_business_account',
                'entry' => [['id' => 'WABA1', 'changes' => [['field' => 'messages', 'value' => [
                    'metadata' => ['phone_number_id' => '111222333'],
                    'statuses' => [['id' => 'wamid.OUT1', 'status' => $state, 'timestamp' => (string) now()->timestamp,
                                    'recipient_id' => '96891234567']],
                ]]]]],
            ];

            $this->hook($payload)->assertOk();
        }

        $this->assertSame(3, WhatsAppWebhookEvent::where('kind', 'status')->count());
    }

    // ── الاستقبال لا يعالج ───────────────────────────────────────

    /**
     * الاستقبال يقيّد ويحيل ولا يعالج.
     *
     * المعالجةُ داخل الطلب تتجاوز مهلة Meta فتُعيد الإرسال، ثمّ تُعيده،
     * فيتضاعف كلُّ شيء تحت ضغطٍ هو من صنعنا.
     */
    public function test_receiving_only_queues_and_never_processes_inline(): void
    {
        Queue::fake();

        $this->hook($this->payload())->assertOk();

        Queue::assertPushed(\App\Jobs\ProcessWhatsAppWebhook::class);
        $this->assertSame(0, WhatsAppMessage::count(), 'عولجت الرسالة داخل الطلب');
    }

    /** والمعالجةُ في الطابور هي التي تُنشئ المحادثة فعلاً. */
    public function test_the_queued_job_creates_the_conversation_and_message(): void
    {
        $this->hook($this->payload())->assertOk();

        $event = WhatsAppWebhookEvent::firstOrFail();
        (new \App\Jobs\ProcessWhatsAppWebhook($event->id))->handle(app(\App\Services\WhatsApp\InboxService::class));

        $this->assertSame(1, WhatsAppMessage::count());
        $this->assertDatabaseHas('whatsapp_contacts', ['wa_id' => '96891234567', 'profile_name' => 'سالم']);
        $this->assertNotNull($event->fresh()->processed_at);
    }

    /** حمولةٌ لا تخصّنا تُقبل بصمت — ولا تُعاد إلى الأبد. */
    public function test_an_unrelated_object_is_acknowledged_not_stored(): void
    {
        $this->hook(['object' => 'page', 'entry' => []])->assertOk();

        $this->assertSame(0, WhatsAppWebhookEvent::count());
    }

    // ── الخصوصية ─────────────────────────────────────────────────

    /** لا يُعاد أيُّ سرٍّ في استجابة الويبهوك مهما كان الطلب. */
    public function test_no_secret_ever_appears_in_a_webhook_response(): void
    {
        $ok = $this->hook($this->payload());
        $bad = $this->hook($this->payload(), 'wrong');
        $challenge = $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=x&hub_challenge=y');

        foreach ([$ok, $bad, $challenge] as $response) {
            $body = $response->getContent();
            $this->assertStringNotContainsString(self::SECRET, $body);
            $this->assertStringNotContainsString('EAA-test-token', $body);
        }
    }
}
