<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\EvolutionPayload;
use App\Services\WhatsApp\EvolutionProvider;
use App\Services\WhatsApp\InboxService;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * جسرُ واتساب ويب — الاقتران بمسح رمز.
 *
 * ═══ ما تحرسه ═══
 *
 * ١) بابُ الجسر بلا توقيعٍ معمّى، فالسرُّ في المسار. ومن لا يعرفه
 *    يُردّ بـ٤٠٣ — وإلا حقن أيُّ أحدٍ رسالةً في خيط موكّل يقرؤها
 *    المحامي على أنّها منه.
 *
 * ٢) وصدى ما نرسله نحن (fromMe) والمجموعاتُ لا تُستوعَب واردةً: الأولى
 *    تُظهر رسالة المكتب كأنّ الموكّل كتبها، والثانية تملأ الصندوق بما
 *    لا يخصّ المكتب.
 *
 * ٣) والنافذةُ والقوالبُ قاعدتا Meta لا قاعدتا الرسالة: على الجسر
 *    يُردّ حرّاً في أيّ وقت، ولا يُطفأ تذكيرُ الجلسات لغياب قالب.
 *
 * ٤) والحمولةُ تُترجَم إلى شكل Meta ثمّ تمرّ بنفس صندوق الوارد — لا
 *    مسارَ استيعابٍ ثانٍ يفترق عن الأوّل بعد شهر.
 */
class WhatsAppEvolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.default' => 'evolution',
            'whatsapp.evolution.base_url' => 'https://bridge.test',
            'whatsapp.evolution.api_key' => 'bridge-key-0123456789',
        ]);

        // الصندوقُ مخفيٌّ افتراضاً — ويُشغَّل هنا لأنّ اختبارَ الردّ
        // الحرّ يمرّ من مساره
        \App\Models\Setting::set(WhatsAppSettings::KEY_INBOX_VISIBLE, '1', 'whatsapp');
    }

    private function hook(array $payload, ?string $secret = null)
    {
        return $this->postJson(
            '/webhooks/evolution/' . ($secret ?? WhatsAppSettings::evolutionSecret()),
            $payload,
        );
    }

    private function inbound(string $id = 'EVO1', string $text = 'السلام عليكم', array $over = []): array
    {
        return array_replace_recursive([
            'event' => 'messages.upsert',
            'data' => [
                'key' => ['id' => $id, 'remoteJid' => '96891234567@s.whatsapp.net', 'fromMe' => false],
                'pushName' => 'سالم',
                'messageTimestamp' => (string) now()->timestamp,
                'message' => ['conversation' => $text],
            ],
        ], $over);
    }

    // ── البـاب ───────────────────────────────────────────────────

    public function test_a_wrong_secret_is_refused(): void
    {
        $this->hook($this->inbound(), str_repeat('z', 40))->assertStatus(403);

        $this->assertSame(0, WhatsAppWebhookEvent::count());
    }

    public function test_the_right_secret_is_accepted_and_recorded(): void
    {
        Queue::fake();

        $this->hook($this->inbound())->assertOk();

        $this->assertDatabaseHas('whatsapp_webhook_events', ['event_key' => 'msg:EVO1']);
    }

    /** السرُّ يُولَّد مرّةً ويبقى — ولا يُعاد بناؤه مع كل نداء. */
    public function test_the_secret_is_stable_and_long(): void
    {
        $first = WhatsAppSettings::evolutionSecret();

        $this->assertSame($first, WhatsAppSettings::evolutionSecret());
        $this->assertGreaterThanOrEqual(32, mb_strlen($first));
    }

    // ── ما لا يُستوعَب ───────────────────────────────────────────

    /** صدى ما أرسلناه نحن ليس رسالةً واردة. */
    public function test_an_echo_of_our_own_message_is_ignored(): void
    {
        $this->hook($this->inbound('EVO2', 'شكراً', [
            'data' => ['key' => ['fromMe' => true]],
        ]))->assertOk();

        $this->assertSame(0, WhatsAppWebhookEvent::count());
    }

    /** والمجموعاتُ ليست محادثاتِ موكّلين. */
    public function test_a_group_message_is_ignored(): void
    {
        $this->hook($this->inbound('EVO3', 'إعلان', [
            'data' => ['key' => ['remoteJid' => '120363000000000000@g.us']],
        ]))->assertOk();

        $this->assertSame(0, WhatsAppWebhookEvent::count());
    }

    // ── الترجمة والاستيعاب ──────────────────────────────────────

    public function test_a_bridge_message_becomes_a_conversation_through_the_same_inbox(): void
    {
        $events = EvolutionPayload::events('messages.upsert', $this->inbound('EVO4', 'أريد موعداً')['data']);

        $this->assertCount(1, $events);

        $message = app(InboxService::class)->ingestIncoming(
            $events[0]['data']['message'],
            $events[0]['data']['contacts'],
        );

        $this->assertNotNull($message);
        $this->assertSame('أريد موعداً', $message->body);
        $this->assertSame('96891234567', WhatsAppContact::firstOrFail()->wa_id);
        $this->assertSame('سالم', WhatsAppContact::firstOrFail()->profile_name);
    }

    /** وحالاتُ Baileys تُترجَم إلى مفردات Meta. */
    public function test_delivery_states_are_translated(): void
    {
        $map = ['SERVER_ACK' => 'sent', 'DELIVERY_ACK' => 'delivered', 'READ' => 'read', 'ERROR' => 'failed'];

        foreach ($map as $raw => $expected) {
            $events = EvolutionPayload::events('messages.update', [
                'key' => ['id' => 'W' . $raw], 'status' => $raw,
            ]);

            $this->assertSame($expected, $events[0]['data']['status']['status'], $raw . ' لم تُترجَم');
        }
    }

    // ── حالةُ الاتصال ───────────────────────────────────────────

    public function test_a_connection_event_marks_the_office_connected(): void
    {
        $this->hook(['event' => 'connection.update', 'data' => ['state' => 'open']])->assertOk();

        $this->assertSame('open', WhatsAppSettings::evolutionState());
        $this->assertTrue(WhatsAppSettings::isConnected());
    }

    public function test_an_office_with_no_pairing_is_not_connected(): void
    {
        $this->assertFalse(WhatsAppSettings::isConnected());
    }

    // ── قواعد Meta لا تُطبَّق هنا ───────────────────────────────

    /** لا نافذةَ أربعٍ وعشرين ساعة على الجسر. */
    public function test_the_service_window_does_not_apply_to_the_bridge(): void
    {
        $contact = WhatsAppContact::create(['wa_id' => '96891234567']);
        $conversation = WhatsAppConversation::create([
            'contact_id' => $contact->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
            'last_inbound_at' => now()->subDays(9),
        ]);

        $this->assertTrue($conversation->windowOpen(), 'مُنع الردّ الحرّ بقاعدةٍ لا تخصّ هذا المزوّد');
        $this->assertFalse($conversation->windowApplies());
    }

    /** ويرسل المحامي نصّاً حرّاً بعد أيام. */
    public function test_a_reply_after_days_is_queued_on_the_bridge(): void
    {
        Queue::fake();
        WhatsAppSettings::setEvolutionState('open');

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $contact = WhatsAppContact::create(['wa_id' => '96891234567']);
        $conversation = WhatsAppConversation::create([
            'contact_id' => $contact->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
            'last_inbound_at' => now()->subDays(5),
        ]);

        $this->actingAs($admin)
            ->post(route('whatsapp.send', $conversation), ['body' => 'تفضّل بالحضور غداً'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('whatsapp_messages', ['body' => 'تفضّل بالحضور غداً']);
    }

    // ── القوالب تُصاغ نصّاً ─────────────────────────────────────

    public function test_a_template_is_rendered_as_plain_text(): void
    {
        \App\Models\WhatsAppTemplate::create([
            'name' => 'session_reminder', 'language' => 'ar', 'status' => 'APPROVED',
            'body' => 'تذكير {{1}} بقضية {{2}} جلسة {{3}}',
        ]);

        $text = EvolutionProvider::renderTemplate('session_reminder', 'ar', ['سالم', '2026/9', 'الأحد']);

        $this->assertSame('تذكير سالم بقضية 2026/9 جلسة الأحد', $text);
    }

    /** ومتغيّرٌ بلا قيمة يُحذف — لا يصل الموكّلَ «{{3}}». */
    public function test_an_unfilled_variable_never_reaches_the_client(): void
    {
        \App\Models\WhatsAppTemplate::create([
            'name' => 'short', 'language' => 'ar', 'status' => 'APPROVED',
            'body' => 'مرحباً {{1}} — {{2}} — {{3}}',
        ]);

        $text = EvolutionProvider::renderTemplate('short', 'ar', ['سالم']);

        $this->assertStringNotContainsString('{{', $text);
        $this->assertStringContainsString('سالم', $text);
    }

    /** وبلا قالبٍ مخزَّن تُبنى الرسالةُ من القيم — لا صمت. */
    public function test_a_missing_template_still_produces_a_message(): void
    {
        $text = EvolutionProvider::renderTemplate('__none__', 'ar', ['سالم', 'جلسة الأحد']);

        $this->assertStringContainsString('سالم', $text);
        $this->assertStringContainsString('جلسة الأحد', $text);
    }

    // ── الإرسال ─────────────────────────────────────────────────

    public function test_sending_uses_the_instance_and_strips_the_plus(): void
    {
        Http::fake(['*' => Http::response(['key' => ['id' => 'BAE123']], 200)]);

        $result = (new EvolutionProvider())->sendText('+968 9123 4567', 'أهلاً');

        $this->assertTrue($result->ok);
        $this->assertSame('BAE123', $result->wamid);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/message/sendText/')
                && $request['number'] === '96891234567';
        });
    }

    /** مفتاحٌ مرفوض خطأٌ دائم — إعادةُ المحاولة لا تُصلحه. */
    public function test_a_rejected_key_is_permanent(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $result = (new EvolutionProvider())->sendText('96891234567', 'أهلاً');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->retryable);
    }

    /** وانقطاعُ الشبكة عابر — تُعاد ولا تُسقَط رسالةُ موكّل. */
    public function test_a_network_drop_is_retryable(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 7');
        });

        $result = (new EvolutionProvider())->sendText('96891234567', 'أهلاً');

        $this->assertFalse($result->ok);
        $this->assertTrue($result->retryable);
    }

    // ── الاقتران ────────────────────────────────────────────────

    public function test_pairing_returns_a_qr_and_sets_the_webhook_with_the_secret(): void
    {
        Http::fake([
            '*/instance/connectionState/*' => Http::response(['instance' => ['state' => 'close']], 200),
            '*/instance/create' => Http::response(['instance' => []], 201),
            '*/webhook/set/*' => Http::response(['webhook' => ['enabled' => true]], 200),
            '*/instance/connect/*' => Http::response(['base64' => 'iVBORw0KGgo='], 200),
        ]);

        $result = (new EvolutionProvider())->pair();

        $this->assertSame('connecting', $result['state']);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $result['qr']);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $url = $body['webhook']['url'] ?? $body['url'] ?? '';

            return str_contains($request->url(), '/webhook/set/')
                && str_contains((string) $url, WhatsAppSettings::evolutionSecret());
        });
    }

    /** وموصولٌ أصلاً: لا يُعرض رمزٌ لمن لا يحتاجه. */
    public function test_pairing_an_already_open_instance_returns_no_qr(): void
    {
        Http::fake(['*/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200)]);

        $result = (new EvolutionProvider())->pair();

        $this->assertSame('open', $result['state']);
        $this->assertNull($result['qr']);
    }

    /** اسمُ النسخة يُشتقّ من النطاق — لا يتصادم مكتبان على خادمٍ واحد. */
    public function test_the_instance_name_comes_from_the_office_domain(): void
    {
        config(['app.url' => 'https://office.riyami.om']);

        $this->assertSame('office-riyami-om', WhatsAppSettings::evolutionInstance());
    }

    // ── التشخيص ─────────────────────────────────────────────────

    /**
     * التشخيصُ يسمّي المزوّدَ العامل ولا يعرض حقولَ غيره.
     *
     * كان يقول «Meta تردّ» على الجسر، ويعرض معرّفَ الحساب وبصمةَ
     * الرمز فارغتين بشرطات — فيرى المشغّل حقولاً خاويةً ويظنّ الربطَ
     * ناقصاً وهو تامّ، ويبحث عن قيمٍ لا وجود لها في هذا الطريق.
     *
     * والوسمُ محايدٌ عمداً («ربط مباشر بالرقم»): مخرَجُ التشخيص
     * يُصوَّر ويُلصَق، ولا يُقال فيه ما لا يعني قارئَه.
     */
    public function test_the_doctor_speaks_about_the_bridge_not_meta(): void
    {
        WhatsAppSettings::setEvolutionState('open');

        Http::fake([
            '*/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200),
            '*/instance/fetchInstances*' => Http::response([
                ['instance' => ['owner' => '96871730036@s.whatsapp.net', 'profileName' => 'المكتب']],
            ], 200),
        ]);

        $this->artisan('whatsapp:doctor --probe')
            ->expectsOutputToContain('ربط مباشر بالرقم')
            ->expectsOutputToContain('نسخة المكتب')
            ->doesntExpectOutputToContain('بصمة الرمز')
            ->doesntExpectOutputToContain('معرّف حساب الأعمال')
            ->assertSuccessful();
    }

    // ── الصلاحية ────────────────────────────────────────────────

    public function test_a_lawyer_cannot_pair(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->actingAs($lawyer)
            ->post(route('settings.whatsapp.pair'))
            ->assertRedirect(route('dashboard'));
    }
}
