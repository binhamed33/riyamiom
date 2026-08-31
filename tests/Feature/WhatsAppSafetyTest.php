<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\SendingGuard;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * ما يخفض احتمالَ حظر رقم المكتب.
 *
 * ═══ ما يُحظَر لأجله رقمٌ فعلاً ═══
 *
 * البلاغُ هو الوقود: دفعةٌ في دقيقة، وإرسالٌ إلى من لا علاقة له
 * بالمكتب، ورسائلُ في الثالثة فجراً. فتُختبر الحدودُ الأربعة:
 * الموكّلون وحدهم، والمهلة، والسقوف، والصمت الليلي.
 *
 * ولا شيءَ هنا يَعِد بعدم الحظر — الضمانُ الوحيد الواجهةُ الرسمية.
 */
class WhatsAppSafetyTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppConversation $conversation;
    private WhatsAppContact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-for-testing-0123456789'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');

        $client = Client::create([
            'name' => 'سالم', 'phone' => '91234567', 'type' => 'individual',
        ]);

        $this->contact = WhatsAppContact::create(['wa_id' => '96891234567', 'client_id' => $client->id]);
        $this->conversation = WhatsAppConversation::create([
            'contact_id' => $this->contact->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
            'last_inbound_at' => now(),
        ]);

        // منتصفُ النهار: خارج الصمت الليلي في كلّ الاختبارات إلا ما
        // يقصده
        $this->travelTo(now()->setTime(12, 0));
    }

    private function queued(string $body = 'مرحبا'): WhatsAppMessage
    {
        return WhatsAppMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => $body,
            'status' => WhatsAppMessage::STATUS_QUEUED,
        ]);
    }

    // ── الموكّلون وحدهم ─────────────────────────────────────────

    /** رقمٌ ليس في السجلّ ولم يراسل المكتب لا يُراسَل. */
    public function test_a_stranger_who_never_wrote_is_never_messaged(): void
    {
        Http::fake();

        $stranger = WhatsAppContact::create(['wa_id' => '96899999999']);
        $thread = WhatsAppConversation::create([
            'contact_id' => $stranger->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
        ]);

        $message = WhatsAppMessage::create([
            'conversation_id' => $thread->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => 'عرض',
            'status' => WhatsAppMessage::STATUS_QUEUED,
        ]);

        (new SendWhatsAppMessage($message->id))->handle();

        Http::assertNothingSent();
        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $message->fresh()->status);
    }

    /** ومن راسل المكتب بنفسه يُردّ عليه — ليس اقتحاماً. */
    public function test_someone_who_wrote_first_can_be_answered(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.OK']]], 200)]);

        $stranger = WhatsAppContact::create(['wa_id' => '96899999999']);
        $thread = WhatsAppConversation::create([
            'contact_id' => $stranger->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 1,
            'last_inbound_at' => now(),
        ]);

        $message = WhatsAppMessage::create([
            'conversation_id' => $thread->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => 'أهلاً',
            'status' => WhatsAppMessage::STATUS_QUEUED,
        ]);

        (new SendWhatsAppMessage($message->id))->handle();

        $this->assertSame(WhatsAppMessage::STATUS_SENT, $message->fresh()->status);
    }

    // ── المهلة والسقوف ──────────────────────────────────────────

    /** رسالتان متتاليتان: الثانية تنتظر. */
    public function test_two_messages_back_to_back_are_paced_apart(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.A']]], 200)]);

        $first = $this->queued('الأولى');
        (new SendWhatsAppMessage($first->id))->handle();
        $this->assertSame(WhatsAppMessage::STATUS_SENT, $first->fresh()->status);

        $second = $this->queued('الثانية');
        (new SendWhatsAppMessage($second->id))->handle();

        // لم تُرسَل — أُعيد جدولتُها
        $this->assertSame(WhatsAppMessage::STATUS_QUEUED, $second->fresh()->status);
        Queue::assertPushed(SendWhatsAppMessage::class);
    }

    /** والسقفُ اليومي يوقف ما بعده. */
    public function test_the_daily_cap_holds_the_rest(): void
    {
        Queue::fake();
        Setting::set(SendingGuard::KEY_PER_DAY, '2', 'whatsapp');
        Setting::set(SendingGuard::KEY_MIN_GAP, '3', 'whatsapp');

        // رسالتان أُرسلتا اليوم
        foreach (['أ', 'ب'] as $body) {
            WhatsAppMessage::create([
                'conversation_id' => $this->conversation->id,
                'direction' => WhatsAppMessage::OUT, 'type' => 'text', 'body' => $body,
                'status' => WhatsAppMessage::STATUS_SENT, 'sent_at' => now()->subHours(2),
            ]);
        }

        $this->assertSame(0, SendingGuard::remainingToday());

        $third = $this->queued('ج');
        (new SendWhatsAppMessage($third->id))->handle();

        $this->assertSame(WhatsAppMessage::STATUS_QUEUED, $third->fresh()->status);
    }

    // ── الصمت الليلي ────────────────────────────────────────────

    /** الثالثةُ فجراً: تنتظر الصباح ولا تُلغى. */
    public function test_a_message_at_three_in_the_morning_waits_for_daylight(): void
    {
        Queue::fake();
        $this->travelTo(now()->setTime(3, 0));

        $message = $this->queued();
        (new SendWhatsAppMessage($message->id))->handle();

        $this->assertSame(WhatsAppMessage::STATUS_QUEUED, $message->fresh()->status, 'أُلغيت رسالةٌ بدل أن تنتظر');
        Queue::assertPushed(SendWhatsAppMessage::class);
    }

    public function test_midday_is_not_quiet(): void
    {
        $this->travelTo(now()->setTime(12, 0));

        $this->assertNull(SendingGuard::delayFor($this->queued()));
    }

    /** والصمتُ يُطفأ بمساواة الساعتين. */
    public function test_quiet_hours_can_be_switched_off(): void
    {
        $this->travelTo(now()->setTime(3, 0));
        Setting::set(SendingGuard::KEY_QUIET_FROM, '0', 'whatsapp');
        Setting::set(SendingGuard::KEY_QUIET_TO, '0', 'whatsapp');

        $this->assertNull(SendingGuard::delayFor($this->queued()));
    }

    // ── التدرّج بعد الاقتران ────────────────────────────────────

    /** رقمٌ اقترن اليوم لا يرسل بسقفه الكامل. */
    public function test_a_freshly_paired_number_starts_low(): void
    {
        Setting::set(SendingGuard::KEY_PER_DAY, '70', 'whatsapp');

        $this->assertSame(70, SendingGuard::perDay(), 'بلا اقترانٍ مسجَّل يعمل بالسقف الكامل');

        Setting::set(SendingGuard::KEY_PAIRED_AT, now()->toIso8601String(), 'whatsapp');

        $this->assertLessThan(70, SendingGuard::perDay());
        $this->assertGreaterThan(0, SendingGuard::perDay());
    }

    /** وبعد أسبوعٍ يبلغ سقفَه. */
    public function test_after_a_week_the_full_cap_applies(): void
    {
        Setting::set(SendingGuard::KEY_PER_DAY, '70', 'whatsapp');
        Setting::set(SendingGuard::KEY_PAIRED_AT, now()->subDays(10)->toIso8601String(), 'whatsapp');

        $this->assertSame(70, SendingGuard::perDay());
    }

    // ── إطفاء الحاكم ────────────────────────────────────────────

    public function test_the_guard_can_be_switched_off_entirely(): void
    {
        $this->travelTo(now()->setTime(3, 0));
        Setting::set(SendingGuard::KEY_ENABLED, '0', 'whatsapp');

        $this->assertNull(SendingGuard::delayFor($this->queued()));
    }

    // ── صندوق الوارد ────────────────────────────────────────────

    /** مخفيٌّ افتراضاً — والمسارُ نفسُه يُرفض لا الرابطُ وحده. */
    public function test_the_inbox_is_hidden_and_its_routes_are_refused_by_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->assertFalse(WhatsAppSettings::inboxVisible());

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('whatsapp.index'));

        $this->actingAs($admin)->get(route('whatsapp.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->post(route('whatsapp.send', $this->conversation), ['body' => 'يدوي'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_showing_the_inbox_opens_it_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Setting::set(WhatsAppSettings::KEY_INBOX_VISIBLE, '1', 'whatsapp');

        $this->actingAs($admin)->get(route('whatsapp.index'))->assertOk();
        $this->actingAs($admin)->get(route('dashboard'))->assertSee(route('whatsapp.index'));
    }

    /** والمطوّرُ يمرّ ولو كان مخفيّاً — هو من يشخّص العطل. */
    public function test_a_developer_still_reaches_the_inbox(): void
    {
        $dev = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $this->actingAs($dev)->get(route('whatsapp.index'))->assertOk();
    }

    /** والإشعاراتُ الآلية تعمل والصندوقُ مخفيّ. */
    public function test_automatic_notifications_work_while_the_inbox_is_hidden(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.OK']]], 200)]);

        $this->assertFalse(WhatsAppSettings::inboxVisible());

        $message = $this->queued('إشعارٌ آلي');
        (new SendWhatsAppMessage($message->id))->handle();

        $this->assertSame(WhatsAppMessage::STATUS_SENT, $message->fresh()->status);
    }
}
