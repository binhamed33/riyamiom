<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\InboxService;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * الإرسال والربط — وحدودُهما.
 *
 * ═══ ما تحرسه هذه الاختبارات ═══
 *
 * ١) نافذة Meta: خارج أربعٍ وعشرين ساعة لا يمرّ إلا قالبٌ معتمَد. ولولا
 *    المنعُ عندنا لظهرت الرسالة في الخيط ثمّ رفضتها Meta بعد ثوانٍ —
 *    فيظنّ المحامي أنّه أجاب موكّله وهو لم يفعل.
 *
 * ٢) العزلُ داخل المكتب: محادثةُ موكّلٍ لا تُربط بقضيّة موكّلٍ آخر،
 *    ومستندٌ لا يُحفظ في ملفٍّ ليس لصاحبه. وهذا أخطر ما في الشاشة:
 *    الخلطُ هنا يضع ورقةَ خصمٍ في ملفّ خصمِه.
 *
 * ٣) الملاحظةُ الداخلية لا تُرسَل أبداً — مهما دُفعت إلى الطابور.
 */
class WhatsAppMessagingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;
    private LegalCase $case;
    private WhatsAppConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-value-for-testing'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');

        // صندوقُ الوارد مخفيٌّ افتراضاً — وهذه الاختباراتُ تفحصه
        // نفسَه، فتُشغّله صراحةً كما يشغّله المكتب الذي يريده.
        Setting::set(WhatsAppSettings::KEY_INBOX_VISIBLE, '1', 'whatsapp');

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->client = Client::create([
            'name' => 'سالم البوسعيدي', 'phone' => '91234567', 'type' => 'individual',
        ]);
        $this->case = LegalCase::create([
            'case_number' => '2026/1', 'title' => 'قضية سالم', 'type' => 'civil',
            'description' => 'وصف', 'court' => 'المحكمة الابتدائية', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium', 'client_id' => $this->client->id,
        ]);

        $contact = WhatsAppContact::create(['wa_id' => '96891234567', 'client_id' => $this->client->id]);
        $this->conversation = WhatsAppConversation::create([
            'contact_id' => $contact->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
            'last_inbound_at' => now(),
        ]);
    }

    // ── نافذة الأربع والعشرين ساعة ───────────────────────────────

    public function test_a_free_text_reply_inside_the_window_is_queued(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post(route('whatsapp.send', $this->conversation), ['body' => 'وصلتنا رسالتكم'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(SendWhatsAppMessage::class);
        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => WhatsAppMessage::OUT,
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'body' => 'وصلتنا رسالتكم',
        ]);
    }

    /** خارج النافذة: يُمنع الردّ الحرّ قبل أن يُكتب في الخيط. */
    public function test_a_free_text_reply_outside_the_window_is_refused_before_it_is_written(): void
    {
        Queue::fake();
        $this->conversation->forceFill(['last_inbound_at' => now()->subHours(30)])->save();

        $this->actingAs($this->admin)
            ->post(route('whatsapp.send', $this->conversation), ['body' => 'متأخر'])
            ->assertRedirect()
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
        $this->assertSame(0, WhatsAppMessage::count(), 'كُتبت رسالةٌ سترفضها Meta');
    }

    /** وخارجها يمرّ القالبُ المعتمَد وحده. */
    public function test_outside_the_window_only_an_approved_template_passes(): void
    {
        Queue::fake();
        $this->conversation->forceFill(['last_inbound_at' => now()->subHours(30)])->save();

        WhatsAppTemplate::create([
            'name' => 'pending_one', 'language' => 'ar', 'status' => 'PENDING', 'body' => 'مرحبا {{1}}',
        ]);
        WhatsAppTemplate::create([
            'name' => 'approved_one', 'language' => 'ar', 'status' => 'APPROVED', 'body' => 'تذكير بجلسة {{1}}',
        ]);

        // قالبٌ قيد المراجعة يُرفض — إرسالُه يفشل عند Meta بلا تفسير
        $this->actingAs($this->admin)
            ->post(route('whatsapp.send', $this->conversation), ['template' => 'pending_one', 'params' => ['س']])
            ->assertSessionHas('error');
        $this->assertSame(0, WhatsAppMessage::count());

        $this->actingAs($this->admin)
            ->post(route('whatsapp.send', $this->conversation), ['template' => 'approved_one', 'params' => ['2026/1']])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('whatsapp_messages', [
            'type' => 'template', 'template_name' => 'approved_one',
        ]);
    }

    /** وعددُ القيم يجب أن يطابق ما اعتُمد — وإلا رفضته Meta. */
    public function test_a_template_with_the_wrong_number_of_values_is_refused(): void
    {
        WhatsAppTemplate::create([
            'name' => 'two_vars', 'language' => 'ar', 'status' => 'APPROVED', 'body' => 'قضية {{1}} بتاريخ {{2}}',
        ]);

        $this->actingAs($this->admin)
            ->post(route('whatsapp.send', $this->conversation), ['template' => 'two_vars', 'params' => ['2026/1']])
            ->assertSessionHas('error');

        $this->assertSame(0, WhatsAppMessage::count());
    }

    // ── من طلب الإيقاف ───────────────────────────────────────────

    /** رفضُ المراسلة يُحترم — ولو كان المرسِل مدير المكتب. */
    public function test_a_contact_who_opted_out_is_never_messaged(): void
    {
        $this->conversation->contact->forceFill(['opted_out_at' => now()])->save();

        $this->actingAs($this->admin)
            ->post(route('whatsapp.send', $this->conversation), ['body' => 'رسالة'])
            ->assertSessionHas('error');

        $this->assertSame(0, WhatsAppMessage::count());
    }

    /** وكلمةُ «إيقاف» من العميل تُسجَّل فوراً عند استيعاب رسالته. */
    public function test_the_word_stop_from_the_client_records_an_opt_out(): void
    {
        $inbox = app(InboxService::class);

        $inbox->ingestIncoming([
            'from' => '96891234567', 'id' => 'wamid.STOP', 'timestamp' => (string) now()->timestamp,
            'type' => 'text', 'text' => ['body' => 'إيقاف'],
        ]);

        $this->assertNotNull($this->conversation->contact->fresh()->opted_out_at);
    }

    // ── الملاحظة الداخلية ────────────────────────────────────────

    /**
     * الملاحظة الداخلية لا تُرسَل — ولو دُفعت إلى الطابور قسراً.
     *
     * الحارسُ في المهمّة نفسها لا في المتحكّم وحده: كتابةُ ملاحظةٍ عن
     * موكّل ثمّ إرسالُها إليه بالخطأ ضررٌ لا يُصلَح باعتذار.
     */
    public function test_an_internal_note_is_never_sent_even_if_pushed_to_the_queue(): void
    {
        Http::fake();

        $note = app(InboxService::class)
            ->addInternalNote($this->conversation, 'الموكّل متأخر في السداد', $this->admin);

        (new SendWhatsAppMessage($note->id))->handle();

        Http::assertNothingSent();
        $this->assertTrue($note->fresh()->is_internal);
    }

    public function test_a_note_is_stored_without_reaching_meta(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post(route('whatsapp.note', $this->conversation), ['body' => 'ملاحظة للفريق'])
            ->assertSessionHas('success');

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('whatsapp_messages', ['is_internal' => true, 'body' => 'ملاحظة للفريق']);
    }

    // ── العزل داخل المكتب ────────────────────────────────────────

    /**
     * محادثةُ موكّلٍ لا تُربط بقضيّة موكّلٍ آخر.
     *
     * لو رُبطت لعُرضت قضيّةُ رجلٍ في سياق محادثة رجلٍ آخر، ولحُفظ ما
     * يرسله في ملفٍّ ليس له — وهو في مكتب محاماة تسريبُ خصمٍ لخصمِه.
     */
    public function test_a_conversation_cannot_be_linked_to_another_clients_case(): void
    {
        $other = Client::create(['name' => 'خصم', 'phone' => '99887766', 'type' => 'individual']);
        $otherCase = LegalCase::create([
            'case_number' => '2026/2', 'title' => 'قضية الخصم', 'type' => 'civil',
            'description' => 'وصف', 'court' => 'محكمة', 'opponent' => 'سالم',
            'status' => 'active', 'priority' => 'medium', 'client_id' => $other->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('whatsapp.link-case', $this->conversation), ['case_id' => $otherCase->id])
            ->assertSessionHas('error');

        $this->assertNull($this->conversation->fresh()->case_id, 'رُبطت محادثةٌ بقضيّة موكّلٍ آخر');

        // وقضيّةُ صاحبها تُقبل
        $this->actingAs($this->admin)
            ->post(route('whatsapp.link-case', $this->conversation), ['case_id' => $this->case->id])
            ->assertSessionHas('success');

        $this->assertSame($this->case->id, $this->conversation->fresh()->case_id);
    }

    /** ومحادثةٌ بلا موكّلٍ مرتبط لا تُربط بقضيّةٍ أصلاً. */
    public function test_an_unlinked_conversation_cannot_be_attached_to_any_case(): void
    {
        $this->conversation->contact->forceFill(['client_id' => null])->save();

        $this->actingAs($this->admin)
            ->post(route('whatsapp.link-case', $this->conversation), ['case_id' => $this->case->id])
            ->assertSessionHas('error');

        $this->assertNull($this->conversation->fresh()->case_id);
    }

    /** وفكُّ ربط الموكّل يفكّ ربط القضية معه. */
    public function test_unlinking_the_client_also_detaches_the_case(): void
    {
        $this->conversation->forceFill(['case_id' => $this->case->id])->save();

        $this->actingAs($this->admin)
            ->post(route('whatsapp.link-client', $this->conversation), ['client_id' => null])
            ->assertSessionHas('success');

        $this->assertNull($this->conversation->fresh()->case_id, 'بقيت القضية على محادثةٍ بلا صاحب');
    }

    /** ومستندٌ وارد لا يُحفظ في ملفّ قضيّةٍ ليست لصاحب المحادثة. */
    public function test_media_cannot_be_saved_into_another_clients_case_file(): void
    {
        $other = Client::create(['name' => 'خصم آخر', 'phone' => '95554444', 'type' => 'individual']);
        $otherCase = LegalCase::create([
            'case_number' => '2026/3', 'title' => 'قضية أخرى', 'type' => 'civil',
            'description' => 'وصف', 'court' => 'محكمة', 'opponent' => 'فلان',
            'status' => 'active', 'priority' => 'medium', 'client_id' => $other->id,
        ]);

        $message = WhatsAppMessage::create([
            'conversation_id' => $this->conversation->id,
            'wamid' => 'wamid.MEDIA1',
            'direction' => WhatsAppMessage::IN,
            'type' => 'document',
            'media_id' => 'media-123',
            'media_mime' => 'application/pdf',
            'media_name' => 'عقد.pdf',
            'status' => WhatsAppMessage::STATUS_DELIVERED,
        ]);

        $this->actingAs($this->admin)
            ->post(route('whatsapp.save-document', $message), ['case_id' => $otherCase->id])
            ->assertSessionHas('error');

        $this->assertSame(0, Document::count(), 'حُفظ مستندٌ في ملفّ موكّلٍ آخر');
        $this->assertNull($message->fresh()->document_id);
    }

    // ── مطابقة الموكّل بالرقم ────────────────────────────────────

    /**
     * الرقمُ الوارد يُطابق موكّله ولو اختلفت صيغةُ كتابته.
     *
     * الموكّل يُسجَّل رقمُه «91234567» ويصل من واتساب «96891234567» —
     * وهما واحد. والهاتفُ مشفَّرٌ فلا يُطابَق بـLIKE، فالبصمةُ هي الطريق.
     */
    public function test_an_incoming_number_finds_its_client_across_formats(): void
    {
        $this->assertNotNull(Client::findByPhone('96891234567'));
        $this->assertSame($this->client->id, Client::findByPhone('+968 9123 4567')->id);
        $this->assertSame($this->client->id, Client::findByPhone('091234567')->id);
        $this->assertNull(Client::findByPhone('96899999999'));
    }

    /** والتباسٌ بين موكّلين لا يُربط تلقائياً — يُترك لإنسان. */
    public function test_an_ambiguous_number_is_not_auto_linked(): void
    {
        Client::create(['name' => 'شبيه', 'phone' => '96891234567', 'type' => 'individual']);

        $this->assertNull(Client::findByPhone('91234567'), 'رُبط رقمٌ يطابق موكّلَين بأحدهما');
    }

    /** والبصمةُ تتجدّد مع تعديل الرقم — لا تبقى على القديم. */
    public function test_the_phone_hash_follows_an_edited_number(): void
    {
        $this->client->update(['phone' => '99001122']);

        $this->assertNull(Client::findByPhone('91234567'));
        $this->assertSame($this->client->id, Client::findByPhone('96899001122')->id);
    }
}
