<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Session as CourtSession;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Jobs\SendWhatsAppMessage;
use Illuminate\Support\Facades\Http;
use App\Services\ClientPortal\PortalOtp;
use App\Services\WhatsApp\ConversationEraser;
use App\Services\WhatsApp\SendingGuard;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * «أريد إضافة خيار مسح محادثات الواتساب من الموقع».
 *
 * لمدير المكتب وحده: محادثةٌ، أو محدَّدة، أو الكلُّ بكلمةٍ تُكتب. النصُّ
 * والمرفقُ يذهبان نهائياً. ويبقى ما يحرس الرقمَ والموكّلَ: عدّادُ الإرسال
 * (حذفُه يفتح الباب لتجاوز السقف فيُحظر الرقم)، وأثرُ تذكير الجلسة (حذفُه
 * يعني تذكيراً ثانياً)، ورفضُ المراسلة في جهة الاتصال.
 */
class WhatsAppEraseConversationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-for-tests'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_INBOX_VISIBLE, '1', 'whatsapp');

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->travelTo(now()->setTime(12, 0));
    }

    private function thread(string $waId, string $name = 'مستفسر'): WhatsAppConversation
    {
        $contact = WhatsAppContact::create(['wa_id' => $waId, 'profile_name' => $name]);

        return WhatsAppConversation::create([
            'contact_id' => $contact->id, 'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 2, 'last_inbound_at' => now()->subHour(), 'last_message_at' => now(),
        ]);
    }

    private function msg(WhatsAppConversation $c, array $attrs): WhatsAppMessage
    {
        return WhatsAppMessage::create($attrs + [
            'conversation_id' => $c->id, 'direction' => WhatsAppMessage::IN,
            'type' => 'text', 'status' => WhatsAppMessage::STATUS_DELIVERED, 'sent_at' => now(),
        ]);
    }

    private function user(string $role, array $permissions = []): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        foreach ($permissions as $permission) {
            $user->permissions()->create(['permission' => $permission]);
        }

        return $user;
    }

    // ── ما يُمسح وما يبقى ───────────────────────────────────────

    public function test_erasing_removes_every_word_and_attachment_but_keeps_the_guards(): void
    {
        $c = $this->thread('96891234567');
        $session = CourtSession::create(['case_id' => null, 'date' => now()->addDays(2), 'location' => 'م', 'status' => 'upcoming']);

        $this->msg($c, ['body' => 'عندي سؤال عن قضية الطلاق']);
        $this->msg($c, ['type' => 'document', 'media_id' => 'MEDIA-1', 'media_name' => 'تقرير طبي.pdf']);
        $old = $this->msg($c, ['direction' => WhatsAppMessage::OUT, 'body' => 'ردٌّ قديم', 'status' => WhatsAppMessage::STATUS_READ, 'sent_at' => now()->subMonth()]);
        $today = $this->msg($c, ['direction' => WhatsAppMessage::OUT, 'body' => 'ردُّ اليوم', 'status' => WhatsAppMessage::STATUS_SENT, 'sent_at' => now()->subMinutes(5)]);
        $reminder = $this->msg($c, ['direction' => WhatsAppMessage::OUT, 'body' => 'تذكير بجلستكم', 'status' => WhatsAppMessage::STATUS_SENT,
            'sent_at' => now()->subDays(20), 'session_id' => $session->id, 'template_name' => 'session_reminder']);
        $queued = $this->msg($c, ['direction' => WhatsAppMessage::OUT, 'body' => 'لم يخرج بعد', 'status' => WhatsAppMessage::STATUS_QUEUED, 'sent_at' => null]);

        $remainingBefore = SendingGuard::remainingToday();

        $this->actingAs($this->admin)->post(route('whatsapp.erase', $c))
            ->assertRedirect(route('whatsapp.index'))->assertSessionHas('success');

        // لا نصَّ ولا مرفقَ في أيّ صفٍّ باقٍ
        $left = WhatsAppMessage::where('conversation_id', $c->id)->get();
        $this->assertSame(collect([$today->id, $reminder->id, $queued->id])->sort()->values()->all(), $left->pluck('id')->sort()->values()->all());
        foreach ($left as $row) {
            $this->assertNull($row->body);
            $this->assertNull($row->media_id);
            $this->assertSame(WhatsAppMessage::TYPE_CLEARED, $row->type);
        }
        $this->assertNull(WhatsAppMessage::find($old->id));
        // ما في الطابور يُلغى أثراً («فاشلة») لا يُحذف: مهمّتُه تجده فلا ترسل
        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $queued->fresh()->status);

        // الحرّاس: سقفُ اليوم لم يُصفَّر، وأثرُ التذكير باقٍ بقالبه وجلسته
        $this->assertSame($remainingBefore, SendingGuard::remainingToday());
        $this->assertSame('session_reminder', $reminder->fresh()->template_name);
        $this->assertSame($session->id, $reminder->fresh()->session_id);

        // جهةُ الاتصال باقية (وفيها الرفضُ إن وُجد)، والمحادثةُ مخفيّة
        $this->assertNotNull($c->contact->fresh());
        $this->assertSame(WhatsAppConversation::STATUS_CLEARED, $c->fresh()->status);
        $this->assertSame(0, $c->fresh()->unread_count);
    }

    public function test_an_erased_conversation_leaves_the_inbox_and_comes_back_empty_on_a_new_message(): void
    {
        $c = $this->thread('96891234567', 'سالم المسح');
        $this->msg($c, ['body' => 'نصٌّ قبل المسح']);

        app(ConversationEraser::class)->erase($c);

        $this->actingAs($this->admin)->get(route('whatsapp.index'))->assertOk()->assertDontSee('سالم المسح');

        // رسالةٌ صادرةٌ آليّة (إشعار) تعيدها — بلا ما مُسح
        $this->msg($c, ['direction' => WhatsAppMessage::OUT, 'body' => 'إشعارٌ جديد', 'status' => WhatsAppMessage::STATUS_QUEUED, 'sent_at' => null]);
        $this->assertSame(WhatsAppConversation::STATUS_OPEN, $c->fresh()->status);

        $this->actingAs($this->admin)->get(route('whatsapp.index'))->assertOk()->assertSee('سالم المسح');
        $this->actingAs($this->admin)->get(route('whatsapp.show', $c))->assertOk()
            ->assertSee('إشعارٌ جديد')->assertDontSee('نصٌّ قبل المسح');
    }

    public function test_an_opt_out_survives_erasing(): void
    {
        $c = $this->thread('96891234567');
        $c->contact->forceFill(['opted_out_at' => now()])->save();

        app(ConversationEraser::class)->erase($c);

        $this->assertFalse($c->contact->fresh()->acceptsNotifications());
    }

    public function test_the_portal_code_trace_keeps_its_placeholder_so_it_stays_out_of_the_budget(): void
    {
        $c = $this->thread('96891234567');
        $before = SendingGuard::remainingToday();
        $this->msg($c, ['direction' => WhatsAppMessage::OUT, 'body' => PortalOtp::LEDGER_BODY, 'status' => WhatsAppMessage::STATUS_SENT, 'sent_at' => now()]);

        app(ConversationEraser::class)->erase($c);

        $this->assertSame(PortalOtp::LEDGER_BODY, WhatsAppMessage::where('conversation_id', $c->id)->value('body'));
        $this->assertSame($before, SendingGuard::remainingToday());
    }

    // ── المحدَّد والكلّ ─────────────────────────────────────────

    public function test_erasing_the_selected_conversations_only(): void
    {
        $a = $this->thread('96891111111');
        $b = $this->thread('96892222222');
        $keep = $this->thread('96893333333');
        foreach ([$a, $b, $keep] as $c) {
            $this->msg($c, ['body' => 'نص']);
        }

        $this->actingAs($this->admin)->post(route('whatsapp.erase-many'), ['ids' => [$a->id, $b->id]])
            ->assertRedirect(route('whatsapp.index'))
            ->assertSessionHas('success', __('app.wa_erase_done', ['count' => 2]));

        $this->assertSame(WhatsAppConversation::STATUS_CLEARED, $a->fresh()->status);
        $this->assertSame(WhatsAppConversation::STATUS_CLEARED, $b->fresh()->status);
        $this->assertSame(WhatsAppConversation::STATUS_OPEN, $keep->fresh()->status);
        $this->assertSame(1, WhatsAppMessage::where('conversation_id', $keep->id)->count());
    }

    public function test_erasing_everything_needs_the_typed_word(): void
    {
        $a = $this->thread('96891111111');
        $b = $this->thread('96892222222');

        $this->actingAs($this->admin)->from(route('whatsapp.index'))
            ->post(route('whatsapp.erase-all'), ['confirm' => 'نعم'])
            ->assertRedirect(route('whatsapp.index'))->assertSessionHas('error');
        $this->assertSame(WhatsAppConversation::STATUS_OPEN, $a->fresh()->status);

        $this->actingAs($this->admin)->post(route('whatsapp.erase-all'), ['confirm' => ' ' . ConversationEraser::CONFIRM_WORD . ' '])
            ->assertSessionHas('success', __('app.wa_erase_done', ['count' => 2]));
        $this->assertSame(0, WhatsAppConversation::listed()->count());

        // في السجلّ من مسح وكم — بلا شيءٍ من المحتوى
        $log = AuditLog::where('model_type', WhatsAppConversation::class)->latest('id')->first();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(AuditLog::ACTION_DELETE, $log->action);
    }

    // ── من يملك المسح ───────────────────────────────────────────

    public function test_only_office_admins_can_erase(): void
    {
        $c = $this->thread('96891234567');
        $this->msg($c, ['body' => 'نص']);
        $manager = $this->user('staff', ['whatsapp.view', 'whatsapp.send', 'whatsapp.manage']);

        // الزرُّ لا يُرسم لمن لا يملكه
        $this->actingAs($manager)->get(route('whatsapp.show', $c))->assertOk()->assertDontSee('data-wa-erase', false);
        $this->actingAs($manager)->get(route('whatsapp.index'))->assertOk()->assertDontSee('data-wa-erase-bar', false);

        foreach ([
            fn () => $this->post(route('whatsapp.erase', $c)),
            fn () => $this->post(route('whatsapp.erase-many'), ['ids' => [$c->id]]),
            fn () => $this->post(route('whatsapp.erase-all'), ['confirm' => ConversationEraser::CONFIRM_WORD]),
        ] as $attempt) {
            $this->actingAs($manager);
            $attempt()->assertRedirect(route('dashboard'))->assertSessionHas('error');
        }

        $this->assertSame(WhatsAppConversation::STATUS_OPEN, $c->fresh()->status);
        $this->assertSame(1, WhatsAppMessage::where('conversation_id', $c->id)->count());

        // والمديرُ يرى الأزرار
        $this->actingAs($this->admin)->get(route('whatsapp.show', $c))->assertSee('data-wa-erase', false);
        $this->actingAs($this->admin)->get(route('whatsapp.index'))->assertSee('data-wa-erase-bar', false);
    }

    // ── ما أكّدته المراجعة ──────────────────────────────────────

    /** رسالةٌ حُمّلت للإرسال ثمّ مُسحت محادثتُها: لا تخرج. */
    public function test_a_message_erased_after_its_job_loaded_it_is_not_sent(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.X']]], 200)]);
        $c = $this->thread('96891234567');
        $queued = $this->msg($c, ['direction' => WhatsAppMessage::OUT, 'body' => 'إشعار', 'status' => WhatsAppMessage::STATUS_QUEUED, 'sent_at' => null]);

        app(ConversationEraser::class)->erase($c);
        app()->call([new SendWhatsAppMessage($queued->id), 'handle']);

        Http::assertNothingSent();
        $this->assertNull($queued->fresh()->body);
    }

    /** المسحُ يمحو السجلّ لا القرار: المحوَّلةُ إلى محامٍ تبقى محوَّلةً ومُسندة. */
    public function test_erasing_keeps_the_handoff_and_the_assignee(): void
    {
        $c = $this->thread('96891234567');
        $c->forceFill(['handoff_at' => now()->subHour(), 'assigned_to' => $this->admin->id])->save();

        app(ConversationEraser::class)->erase($c);

        $this->assertNotNull($c->fresh()->handoff_at);
        $this->assertSame($this->admin->id, $c->fresh()->assigned_to);
        $this->assertFalse($c->fresh()->aiMayReply());
    }

    /** نسخةٌ قديمةٌ في الذاكرة لا تُبقي «غير مقروء» وصل بعد تحميلها. */
    public function test_a_stale_model_still_resets_unread(): void
    {
        $c = $this->thread('96891234567');
        $stale = WhatsAppConversation::find($c->id);
        $stale->forceFill(['unread_count' => 0])->syncOriginal();
        WhatsAppConversation::whereKey($c->id)->update(['unread_count' => 5]);

        app(ConversationEraser::class)->erase($stale);

        $this->assertSame(0, $c->fresh()->unread_count);
    }

    /** نصُّ الوارد في سجلّ الاستقبال يُفرَّغ — ويبقى مفتاحُه فلا تُحييه إعادةُ Meta. */
    public function test_the_inbound_copy_in_the_webhook_log_is_emptied(): void
    {
        $c = $this->thread('96891234567');
        $this->msg($c, ['wamid' => 'wamid.IN1', 'body' => 'سرٌّ في الرسالة']);
        WhatsAppWebhookEvent::create(['event_key' => 'msg:wamid.IN1', 'kind' => 'message', 'payload' => ['text' => 'سرٌّ في الرسالة']]);

        app(ConversationEraser::class)->erase($c);

        $event = WhatsAppWebhookEvent::where('event_key', 'msg:wamid.IN1')->first();
        $this->assertNotNull($event, 'صفُّ الحدث يبقى ليمنع التكرار');
        $this->assertStringNotContainsString('سرٌّ', json_encode($event->payload, JSON_UNESCAPED_UNICODE));
        $this->assertNotNull($event->processed_at);
    }

    /** تبويبٌ قديمٌ على محادثةٍ ممسوحة: لا تُعرض، و«إغلاق» منها لا يعيدها. */
    public function test_a_stale_tab_cannot_bring_an_erased_conversation_back(): void
    {
        $c = $this->thread('96891234567');
        app(ConversationEraser::class)->erase($c);

        $this->actingAs($this->admin)->get(route('whatsapp.show', $c))
            ->assertRedirect(route('whatsapp.index'))->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('whatsapp.close', $c))->assertRedirect(route('whatsapp.index'));

        $this->assertSame(WhatsAppConversation::STATUS_CLEARED, $c->fresh()->status);
    }

    /** خطوةُ «أوّل رسالةٍ واردة» في معالج الإعداد تبقى منجَزة بعد مسح الكلّ. */
    public function test_the_setup_checklist_remembers_the_first_message_after_erasing(): void
    {
        $c = $this->thread('96891234567');
        $this->msg($c, ['body' => 'مرحبا']);

        $this->actingAs($this->admin)->post(route('whatsapp.erase-all'), ['confirm' => ConversationEraser::CONFIRM_WORD]);

        $step = collect(app(\App\Services\WhatsApp\SetupDoctor::class)->report()['steps'] ?? [])->firstWhere('key', 'first_message');
        $this->assertNotNull($step);
        $this->assertSame(\App\Services\WhatsApp\SetupDoctor::DONE, $step['state']);
    }
}
