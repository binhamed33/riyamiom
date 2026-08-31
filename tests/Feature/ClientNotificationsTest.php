<?php

namespace Tests\Feature;

use App\Jobs\SendClientNotification;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\ClientPortalLink;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Session as CourtSession;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\ClientPortal\PortalLinks;
use App\Support\ClientEvents;
use App\Support\ClientPortal;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * إشعارُ الموكّل: حدثٌ ⇐ إشعار ⇐ طابور ⇐ واتساب ⇐ رابطٌ آمن ⇐ بوابة.
 *
 * ═══ ما تحرسه ═══
 *
 * ١) واتساب قناةُ تنبيهٍ لا مخزنُ بيانات: لا وقائعَ ولا خصمَ ولا مبلغَ
 *    في الرسالة. تصل إلى هاتفٍ قد يقرؤه غيرُ صاحبه، وتبقى في إشعار
 *    الشاشة المقفلة وفي نسخِه الاحتياطية.
 *
 * ٢) وما عُلّم داخلياً لا يخرج البتّة — لا يُقيَّد له إشعارٌ أصلاً،
 *    فلا يمكن أن يُرسَل بخطأٍ لاحق.
 *
 * ٣) والرابطُ لا يوسّع صلاحيةَ أحد: يفتح جلسةَ صاحبه، وتغييرُ رقمٍ في
 *    العنوان بعده يُعطي ٤٠٤ كما لو لم يوجد.
 *
 * ٤) وله ثلاثةُ حدود: مدّةٌ، واستعمالٌ واحد، وإبطال. ورسالةُ واتساب
 *    تبقى في الهاتف سنين.
 *
 * ٥) والحدثُ الواحد إشعارٌ واحد مهما تكرّر إطلاقُه.
 */
class ClientNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // مكتبٌ مربوطٌ بواتساب — وإلا سُجّل الإشعارُ ولم يُرسَل
        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-for-testing-0123456789'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');
        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');

        // المفتاحُ الرئيسي مطفأٌ افتراضاً — يُشغَّل هنا صراحةً كما
        // يشغّله المكتب بيده
        ClientEvents::setMasterEnabled(true);

        $this->client = Client::create([
            'name' => 'أحمد بن سالم البوسعيدي',
            'phone' => '91234567',
            'national_id' => '12345678',
            'type' => 'individual',
        ]);
    }

    private function makeCase(?Client $for = null, string $number = '2026/123'): LegalCase
    {
        return LegalCase::create([
            'case_number' => $number, 'title' => 'قضية', 'type' => 'civil',
            'description' => 'وصف', 'court' => 'الابتدائية', 'opponent' => 'شركة الخصم للتجارة',
            'status' => 'active', 'priority' => 'medium',
            'client_id' => ($for ?? $this->client)->id,
        ]);
    }

    private function outgoing(): ?WhatsAppMessage
    {
        return WhatsAppMessage::where('direction', WhatsAppMessage::OUT)->latest('id')->first();
    }

    // ══════════ السيناريو كاملاً ══════════

    /** ١ ← ١٢ من سيناريو الاختبار المطلوب. */
    public function test_the_whole_journey_from_a_new_case_to_the_portal(): void
    {
        Queue::fake();

        // ٤) تُفتح القضية ⇐ ٥) حدث ⇐ ٦) إشعار
        $case = $this->makeCase();

        $notification = ClientNotification::firstOrFail();
        $this->assertSame(ClientEvents::CASE_CREATED, $notification->type);
        $this->assertSame($this->client->id, $notification->client_id);
        $this->assertSame($case->id, $notification->case_id);

        // ٧) مهمّةٌ في الطابور
        Queue::assertPushed(SendClientNotification::class);

        // ٨) تُنفَّذ فتُكتب رسالةٌ وتُدفع للإرسال
        (new SendClientNotification($notification->id))->handle(app(\App\Services\WhatsApp\InboxService::class));

        $message = $this->outgoing();
        $this->assertNotNull($message, 'لم تُكتب رسالةٌ للموكّل');
        Queue::assertPushed(SendWhatsAppMessage::class);

        // الرسالةُ تنبيهٌ ورابط — لا تفاصيل
        $this->assertStringContainsString('أحمد', $message->body);
        $this->assertStringContainsString('2026/123', $message->body);
        $this->assertStringNotContainsString('شركة الخصم', $message->body);
        $this->assertStringNotContainsString('الابتدائية', $message->body);

        // ٩) يضغط الرابط ⇐ ١٠) تُفتح البوابة
        preg_match('#/p/([A-Za-z0-9]+)#', (string) $message->body, $m);
        $this->assertNotEmpty($m, 'لا رابط في الرسالة');

        $this->get('/p/' . $m[1])->assertRedirect(route('client.portal.case', $case->id) . '#case');

        // ١١) يرى قضيّته
        $this->get(route('client.portal.case', $case->id))->assertOk()->assertSee('2026/123');

        // والإشعارُ في مركز الإشعارات
        $this->get(route('client.portal.notifications'))->assertOk()->assertSee('قضيةٌ جديدة', false);

        $this->assertSame(ClientNotification::QUEUED, $notification->fresh()->channel_state);
    }

    // ══════════ العزل و IDOR ══════════

    /** الموكّل «أ» لا يبلغ قضيّة «ب» ولا مستنده ولا إشعاره. */
    public function test_a_client_cannot_reach_another_clients_case_document_or_notification(): void
    {
        Queue::fake();

        $other = Client::create([
            'name' => 'خصم', 'phone' => '99887766', 'national_id' => '87654321', 'type' => 'individual',
        ]);

        $mine = $this->makeCase($this->client, '2026/1');
        $theirs = $this->makeCase($other, '2026/2');

        $theirDoc = Document::create([
            'case_id' => $theirs->id, 'uploaded_by' => $this->admin->id,
            'title' => 'مذكّرة', 'doc_type' => 'other', 'file_path' => 'x/y.pdf',
            'file_type' => 'pdf', 'file_size' => 10, 'client_visible' => true,
        ]);

        // يدخل الموكّل «أ» برابطه
        $link = PortalLinks::for($this->client, 'case', $mine->id);
        preg_match('#/p/([A-Za-z0-9]+)#', $link, $m);
        $this->get('/p/' . $m[1])->assertRedirect();

        // ثمّ يجرّب معرّفات غيره
        $this->get(route('client.portal.case', $theirs->id))->assertNotFound();
        $this->get(route('client.portal.document', $theirDoc->id))->assertNotFound();

        // ومركزُ إشعاراته لا يحمل إشعارَ غيره
        $this->get(route('client.portal.notifications'))
            ->assertOk()
            ->assertDontSee('2026/2');
    }

    /** ورابطُ موكّلٍ لا يفتح جلسةَ غيره. */
    public function test_a_link_opens_only_its_own_owners_session(): void
    {
        $other = Client::create([
            'name' => 'آخر', 'phone' => '99887766', 'national_id' => '87654321', 'type' => 'individual',
        ]);
        $theirCase = $this->makeCase($other, '2026/9');

        $link = PortalLinks::for($this->client, 'home');
        preg_match('#/p/([A-Za-z0-9]+)#', $link, $m);
        $this->get('/p/' . $m[1]);

        $this->get(route('client.portal.case', $theirCase->id))->assertNotFound();
    }

    // ══════════ الرابط ══════════

    public function test_a_used_link_never_works_twice(): void
    {
        $link = PortalLinks::for($this->client, 'home');
        preg_match('#/p/([A-Za-z0-9]+)#', $link, $m);

        $this->get('/p/' . $m[1])->assertRedirect(route('client.portal.home'));
        $this->post(route('client.access.logout'));

        $this->get('/p/' . $m[1])->assertRedirect(route('client.access'));
        $this->get(route('client.portal.home'))->assertRedirect(route('client.access'));
    }

    public function test_an_expired_link_is_refused(): void
    {
        $link = PortalLinks::for($this->client, 'home');
        preg_match('#/p/([A-Za-z0-9]+)#', $link, $m);

        ClientPortalLink::query()->update(['expires_at' => now()->subMinute()]);

        $this->get('/p/' . $m[1])->assertRedirect(route('client.access'));
        $this->get(route('client.portal.home'))->assertRedirect(route('client.access'));
    }

    public function test_a_revoked_link_is_refused(): void
    {
        $link = PortalLinks::for($this->client, 'home');
        preg_match('#/p/([A-Za-z0-9]+)#', $link, $m);

        PortalLinks::revokeAllFor($this->client);

        $this->get('/p/' . $m[1])->assertRedirect(route('client.access'));
    }

    public function test_an_invented_token_is_refused(): void
    {
        $this->get('/p/' . str_repeat('a', 64))->assertRedirect(route('client.access'));
    }

    /** الرمزُ لا يُخزَّن صريحاً — بصمتُه وحدها. */
    public function test_only_the_hash_is_stored(): void
    {
        $link = PortalLinks::for($this->client, 'home');
        preg_match('#/p/([A-Za-z0-9]+)#', $link, $m);

        $this->assertDatabaseMissing('client_portal_links', ['token_hash' => $m[1]]);
        $this->assertDatabaseHas('client_portal_links', ['token_hash' => PortalLinks::hash($m[1])]);
    }

    // ══════════ ما لا يخرج ══════════

    /** مستندٌ داخليّ لا يُقيَّد له إشعارٌ أصلاً. */
    public function test_an_internal_document_never_becomes_a_notification(): void
    {
        Queue::fake();
        ClientEvents::setEnabled(ClientEvents::DOCUMENT_NEW, true);

        $case = $this->makeCase();
        ClientNotification::query()->delete();

        Document::create([
            'case_id' => $case->id, 'uploaded_by' => $this->admin->id,
            'title' => 'مذكّرة داخلية', 'doc_type' => 'other', 'file_path' => 'x/y.pdf',
            'file_type' => 'pdf', 'file_size' => 10, 'client_visible' => false,
        ]);

        $this->assertSame(0, ClientNotification::count(), 'خرج إشعارٌ عن مستندٍ داخلي');
    }

    /** ونوعٌ لم يشغّله المكتب لا يُقيَّد ولا يُرسَل. */
    public function test_a_disabled_event_records_nothing(): void
    {
        Queue::fake();
        ClientEvents::setEnabled(ClientEvents::SESSION_NEW, false);

        $case = $this->makeCase();
        ClientNotification::query()->delete();

        CourtSession::create([
            'case_id' => $case->id, 'date' => now()->addDays(3),
            'location' => 'الابتدائية', 'status' => 'upcoming',
        ]);

        $this->assertSame(0, ClientNotification::count());
    }

    /** «قضية جديدة» وحدها مفعَّلةٌ افتراضاً. */
    public function test_nothing_fires_until_the_office_turns_the_master_switch_on(): void
    {
        Queue::fake();
        ClientEvents::setMasterEnabled(false);

        $this->makeCase();

        $this->assertSame(0, ClientNotification::count(), 'أُشعر موكّلٌ قبل أن يشغّل المكتب شيئاً');
        Queue::assertNotPushed(SendClientNotification::class);
    }

    /**
     * سيرُ القضية يُؤشَّر افتراضاً، والثلاثةُ الباقية تُترك للمكتب.
     *
     * فتحُ القضية وحالتُها وتحديثُها والجلسةُ وتأجيلُها وتذكيرُها
     * والفاتورة: هذه ما يسأل عنه الموكّل المكتبَ هاتفياً كلَّ أسبوع،
     * وإخبارُه بها هو الغرضُ من المنظومة أصلاً.
     *
     * أمّا المستندُ (قد يُرفع عشرةٌ في يومٍ واحد فتغرق رسائلُه)
     * والدفعةُ (خبرٌ ماليٌّ يُساء فهمه بلا سياق) والإشعارُ العام —
     * فبيد المكتب.
     */
    public function test_the_case_lifecycle_events_are_on_and_the_rest_are_left_to_the_office(): void
    {
        $on = [
            ClientEvents::CASE_CREATED, ClientEvents::CASE_STATUS, ClientEvents::CASE_UPDATE,
            ClientEvents::SESSION_NEW, ClientEvents::SESSION_MOVED, ClientEvents::SESSION_REMINDER,
            ClientEvents::INVOICE_NEW,
        ];

        $off = [ClientEvents::DOCUMENT_NEW, ClientEvents::PAYMENT_NEW, ClientEvents::ANNOUNCEMENT];

        foreach ($on as $type) {
            $this->assertTrue(ClientEvents::enabled($type), $type . ' مطفأٌ وكان ينبغي أن يعمل');
        }

        foreach ($off as $type) {
            $this->assertFalse(ClientEvents::enabled($type), $type . ' مفعَّلٌ وكان ينبغي أن يُترك للمكتب');
        }

        // نوعٌ يُضاف ولا يُقرَّر له افتراضٌ يمرّ صامتاً — فيُعدّ العدد
        $this->assertSame(
            count($on) + count($off),
            count(ClientEvents::types()),
            'أُضيف نوعٌ ولم يُقرَّر له افتراض',
        );
    }

    // ══════════ التكرار ══════════

    public function test_the_same_event_never_notifies_twice(): void
    {
        Queue::fake();

        $case = $this->makeCase();
        $this->assertSame(1, ClientNotification::count());

        // إطلاقٌ ثانٍ لنفس الحدث
        app(\App\Observers\ClientNotifyObserver::class)->caseCreated($case);
        app(\App\Observers\ClientNotifyObserver::class)->caseCreated($case);

        $this->assertSame(1, ClientNotification::count());
        Queue::assertPushed(SendClientNotification::class, 1);
    }

    /** والمهمّةُ المُعادة لا تكتب رسالةً ثانية. */
    public function test_a_replayed_job_does_not_send_again(): void
    {
        Queue::fake();
        $this->makeCase();

        $notification = ClientNotification::firstOrFail();
        $inbox = app(\App\Services\WhatsApp\InboxService::class);

        (new SendClientNotification($notification->id))->handle($inbox);
        (new SendClientNotification($notification->id))->handle($inbox);

        $this->assertSame(1, WhatsAppMessage::where('direction', WhatsAppMessage::OUT)->count());
    }

    // ══════════ الرفض ══════════

    /** من طلب إيقاف المراسلة يُقيَّد إشعارُه ولا يُراسَل. */
    public function test_an_opted_out_client_is_recorded_but_not_messaged(): void
    {
        Queue::fake();

        WhatsAppContact::create([
            'wa_id' => '96891234567',
            'client_id' => $this->client->id,
            'opted_out_at' => now(),
        ]);

        $this->makeCase();
        $notification = ClientNotification::firstOrFail();

        (new SendClientNotification($notification->id))->handle(app(\App\Services\WhatsApp\InboxService::class));

        $this->assertSame(0, WhatsAppMessage::where('direction', WhatsAppMessage::OUT)->count());
        $this->assertSame(ClientNotification::SKIPPED, $notification->fresh()->channel_state);

        // ويبقى الإشعارُ في بوابته — الرفضُ للمراسلة لا للعلم
        $this->assertSame(1, ClientNotification::count());
    }

    /** ومكتبٌ بلا واتساب: الإشعار في البوابة ولا رسالة. */
    public function test_an_office_without_whatsapp_still_records_the_notification(): void
    {
        Queue::fake();
        Setting::set(WhatsAppSettings::KEY_TOKEN, '', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '', 'whatsapp');

        $this->makeCase();
        $notification = ClientNotification::firstOrFail();

        (new SendClientNotification($notification->id))->handle(app(\App\Services\WhatsApp\InboxService::class));

        $this->assertSame(0, WhatsAppMessage::count());
        $this->assertSame(ClientNotification::SKIPPED, $notification->fresh()->channel_state);
    }

    // ══════════ الصلاحيات ══════════

    public function test_a_guest_reaches_no_portal_page(): void
    {
        $this->get(route('client.portal.notifications'))->assertRedirect(route('client.access'));
    }
}
