<?php

namespace Tests\Feature;

use App\Jobs\SendClientNotification;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\FinanceFee;
use App\Models\FinanceInvoice;
use App\Models\LegalCase;
use App\Models\Session as CourtSession;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\ClientPortal\PortalOtp;
use App\Services\WhatsApp\SendingGuard;
use App\Support\ClientEvents;
use App\Support\ClientPortal;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * «لما تصير إضافة رسوم وإلى آخره ما توصل له رسالة بالواتس».
 *
 * ثلاثةُ أسبابٍ وُجدت وراء الشكوى، ولكلٍّ اختبارُه هنا:
 *  ‏١) الرسمُ لم يكن له حدثٌ أصلاً، والدفعةُ لها مفتاحٌ بلا مُطلِق.
 *  ‏٢) جلساتُ نموذج القضية كانت تُكتب باستعلامٍ لا بالنموذج، فلا يرى
 *     المراقبُ تأجيلَها.
 *  ‏٣) رسالةٌ محجوزةٌ لا يُفرج عنها إلا الكنسُ كلَّ خمس دقائق، وما ضاعت
 *     مهمّتُه يبقى «معلّقاً» أبداً، ورموزُ البوابة تأكل سقفَ الإحماء.
 */
class ClientNotificationGapsTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;
    private User $admin;
    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-for-testing-0123456789'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');
        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
        ClientEvents::setMasterEnabled(true);

        $this->client = Client::create([
            'name' => 'أحمد بن سالم البوسعيدي', 'phone' => '91234567',
            'national_id' => '12345678', 'type' => 'individual',
        ]);

        Queue::fake();
        $this->case = LegalCase::create([
            'case_number' => '2026/123', 'title' => 'قضية', 'type' => 'civil',
            'description' => 'وصف', 'court' => 'الابتدائية', 'opponent' => 'شركة الخصم',
            'status' => 'active', 'priority' => 'medium', 'client_id' => $this->client->id,
        ]);
        ClientNotification::query()->delete();
    }

    private function ofType(string $type)
    {
        return ClientNotification::where('type', $type)->get();
    }

    // ── ١) الرسوم والدفعات ───────────────────────────────────────

    /** رسمٌ يراه الموكّل يُبلَّغ به — من النموذج نفسِه الذي يستعمله المكتب. */
    public function test_a_client_visible_fee_messages_the_client(): void
    {
        $this->actingAs($this->admin)->post(route('finance.fees.store'), [
            'case_id' => $this->case->id, 'fee_type' => 'رسوم قيد', 'amount' => '25',
            'status' => 'unpaid', 'client_visible' => '1', 'date' => now()->toDateString(),
        ])->assertRedirect();

        $sent = $this->ofType(ClientEvents::FEE_NEW);
        $this->assertCount(1, $sent);
        $this->assertSame($this->client->id, $sent[0]->client_id);
        $this->assertSame('billing', $sent[0]->target);
        // لا مبلغَ في الرسالة: واتساب قناةُ تنبيهٍ لا كشفُ حساب
        $this->assertStringNotContainsString('25', $sent[0]->title . $sent[0]->body);
        Queue::assertPushed(SendClientNotification::class);
    }

    /** والرسمُ الداخلي لا يُقيَّد له شيء، حتى يعلّمه المكتب مرئيّاً — فيُبلَّغ مرّةً واحدة. */
    public function test_an_internal_fee_is_silent_until_it_is_made_visible(): void
    {
        $fee = FinanceFee::create([
            'case_id' => $this->case->id, 'fee_type' => 'رسوم', 'amount' => 10,
            'status' => 'unpaid', 'client_visible' => false, 'date' => now()->toDateString(),
        ]);
        $this->assertCount(0, $this->ofType(ClientEvents::FEE_NEW));

        $fee->update(['client_visible' => true]);
        $fee->update(['amount' => 12]);
        $fee->update(['client_visible' => false]);
        $fee->update(['client_visible' => true]);

        $this->assertCount(1, $this->ofType(ClientEvents::FEE_NEW), 'الحدثُ الواحد إشعارٌ واحد');
    }

    public function test_a_disabled_fee_event_records_nothing(): void
    {
        ClientEvents::setEnabled(ClientEvents::FEE_NEW, false);

        FinanceFee::create([
            'case_id' => $this->case->id, 'fee_type' => 'رسوم', 'amount' => 10,
            'status' => 'unpaid', 'client_visible' => true, 'date' => now()->toDateString(),
        ]);

        $this->assertCount(0, $this->ofType(ClientEvents::FEE_NEW));
    }

    /** دفعةٌ على فاتورةٍ مرئيّة: كلُّ زيادةٍ في المدفوع إشعار، والحفظُ بلا دفعةٍ لا شيء. */
    public function test_each_payment_on_a_visible_invoice_is_one_message(): void
    {
        ClientEvents::setEnabled(ClientEvents::PAYMENT_NEW, true);

        $invoice = FinanceInvoice::create([
            'invoice_number' => 'INV-1', 'client_id' => $this->client->id, 'case_id' => $this->case->id,
            'amount' => 100, 'paid_amount' => 0, 'status' => 'unpaid', 'client_visible' => true,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addMonth()->toDateString(),
        ]);

        $invoice->update(['paid_amount' => 40]);
        $invoice->update(['description' => 'تعديلٌ لا دفعة']);
        $invoice->update(['paid_amount' => 100]);
        $invoice->update(['paid_amount' => 60]); // تصحيحٌ بالنقص ليس دفعة
        $this->assertCount(2, $this->ofType(ClientEvents::PAYMENT_NEW));

        // دفعةُ ٤٠ حقيقيّةٌ تعيد المجموعَ إلى ١٠٠ — لا تُبتلع بمطابقة الأولى
        $invoice->update(['paid_amount' => 100]);
        $this->assertCount(3, $this->ofType(ClientEvents::PAYMENT_NEW));
    }

    /** تصحيحُ مبلغِ فاتورةٍ مدفوعة يعيد حسابَ المدفوع — وليس دفعة. */
    public function test_correcting_the_amount_of_a_paid_invoice_is_not_a_payment(): void
    {
        ClientEvents::setEnabled(ClientEvents::PAYMENT_NEW, true);

        $invoice = FinanceInvoice::create([
            'invoice_number' => 'INV-3', 'client_id' => $this->client->id, 'case_id' => $this->case->id,
            'amount' => 100, 'paid_amount' => 100, 'status' => 'paid', 'client_visible' => true,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addMonth()->toDateString(),
        ]);

        // كما يحفظه نموذجُ التعديل: المدفوعُ = المبلغ ما دامت «مدفوعة»
        $invoice->update(['amount' => 110, 'paid_amount' => 110, 'status' => 'paid']);

        $this->assertCount(0, $this->ofType(ClientEvents::PAYMENT_NEW));

        // والانتقالُ إلى «مدفوعة» دفعةٌ حقّاً
        $other = FinanceInvoice::create([
            'invoice_number' => 'INV-4', 'client_id' => $this->client->id, 'case_id' => $this->case->id,
            'amount' => 100, 'paid_amount' => 0, 'status' => 'unpaid', 'client_visible' => true,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addMonth()->toDateString(),
        ]);
        $other->update(['amount' => 120, 'paid_amount' => 120, 'status' => 'paid']);

        $this->assertCount(1, $this->ofType(ClientEvents::PAYMENT_NEW));
    }

    public function test_a_payment_on_an_internal_invoice_is_silent(): void
    {
        ClientEvents::setEnabled(ClientEvents::PAYMENT_NEW, true);

        $invoice = FinanceInvoice::create([
            'invoice_number' => 'INV-2', 'client_id' => $this->client->id, 'case_id' => $this->case->id,
            'amount' => 100, 'paid_amount' => 0, 'status' => 'unpaid', 'client_visible' => false,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addMonth()->toDateString(),
        ]);
        $invoice->update(['paid_amount' => 50]);

        $this->assertCount(0, $this->ofType(ClientEvents::PAYMENT_NEW));
    }

    // ── ٢) جلساتُ نموذج القضية ──────────────────────────────────

    private function caseForm(array $sessions, ?LegalCase $case = null): \Illuminate\Testing\TestResponse
    {
        $case ??= $this->case;

        return $this->actingAs($this->admin)->put(route('cases.update', $case), [
            'case_number' => $case->case_number, 'description' => 'وصف', 'court' => 'الابتدائية',
            'opponent' => 'شركة الخصم', 'status' => 'active', 'priority' => 'medium',
            'client_id' => $case->client_id, 'sessions' => $sessions,
        ]);
    }

    /** تأجيلُ جلسةٍ من نموذج القضية يصل الموكّلَ كما يصله من صفحة الجلسة. */
    public function test_moving_a_session_from_the_case_form_tells_the_client(): void
    {
        $session = CourtSession::create([
            'case_id' => $this->case->id, 'date' => now()->addDays(3)->setTime(9, 0),
            'location' => 'الابتدائية', 'status' => 'upcoming',
        ]);
        ClientNotification::query()->delete();

        $this->caseForm([[
            'id' => $session->id, 'date' => now()->addDays(10)->setTime(9, 0)->format('Y-m-d H:i'),
            'location' => 'الابتدائية', 'status' => 'upcoming',
        ]])->assertRedirect(route('cases.show', $this->case));

        $this->assertCount(1, $this->ofType(ClientEvents::SESSION_MOVED));
    }

    /** ومعرّفُ جلسةِ قضيّةٍ أخرى في النموذج لا يمسّها. */
    public function test_the_case_form_cannot_write_to_another_cases_session(): void
    {
        $other = LegalCase::create([
            'case_number' => '2026/999', 'title' => 'أخرى', 'type' => 'civil',
            'description' => 'وصف', 'court' => 'الاستئناف', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium', 'client_id' => $this->client->id,
        ]);
        $foreign = CourtSession::create([
            'case_id' => $other->id, 'date' => now()->addDays(3)->setTime(9, 0),
            'location' => 'الاستئناف', 'status' => 'upcoming',
        ]);
        $before = $foreign->fresh()->date->toDateTimeString();

        $this->caseForm([['id' => $foreign->id, 'date' => now()->addDays(20)->format('Y-m-d H:i'), 'status' => 'upcoming']]);
        $this->caseForm([['id' => $foreign->id, 'delete' => '1']]);

        $foreign->refresh();
        $this->assertTrue($foreign->exists);
        $this->assertSame($other->id, $foreign->case_id);
        $this->assertSame($before, $foreign->date->toDateTimeString());
    }

    // ── أرقامٌ في حقلٍ واحد ──────────────────────────────────────

    public function test_a_field_holding_two_numbers_yields_each_one(): void
    {
        $this->assertSame(['96891234567', '96899887766'], WhatsAppContact::waIdsOf('91234567 / 99887766'));
        $this->assertSame(['96891234567', '96899887766'], WhatsAppContact::waIdsOf('91234567، 99887766'));
        $this->assertSame(['96891234567', '96899887766'], WhatsAppContact::waIdsOf('91234567 99887766'));
        $this->assertSame(['96891234567'], WhatsAppContact::waIdsOf('+968 9123 4567'));
        $this->assertSame([], WhatsAppContact::waIdsOf(null));

        // رقمٌ واحدٌ بشرطةٍ بين نصفيه ليس رقمين
        $this->assertSame(['96891234567'], WhatsAppContact::waIdsOf('9123 - 4567'));
        $this->assertSame(['96891234567', '96899887766'], WhatsAppContact::waIdsOf('91234567 - 99887766'));
        // أرقامٌ مقسّمةٌ بمسافات، وأرقامٌ هنديّة، والأوّلُ يبقى أوّلاً
        $this->assertSame(['96891234567', '96899887766'], WhatsAppContact::waIdsOf('+968 9123 4567 +968 9988 7766'));
        $this->assertSame(['96891234567', '96899887766'], WhatsAppContact::waIdsOf('+968 9123 4567 99887766'));
        $this->assertSame(['96891234567', '96899887766'], WhatsAppContact::waIdsOf('٩١٢٣٤٥٦٧ ٩٩٨٨٧٧٦٦'));
        $this->assertSame('96891234567', WhatsAppContact::primaryWaId('+968 9123 4567 ext 12345'));
        $this->assertSame(['96891234567', '96899887766'], WhatsAppContact::waIdsOf('9123 4567 9988 7766'));
        // مفتاحُ دولةٍ أخرى لا يُقطع عند ثماني خانات
        $this->assertSame(['966512345678', '96891234567'], WhatsAppContact::waIdsOf('+966 51 234 5678 +968 9123 4567'));
        // ملتبس: لا يُوصل نصفُ رقمٍ بنصف آخر — يُترك غيرَ صالحٍ فيُتخطّى
        $this->assertFalse(WhatsAppContact::isSendable(WhatsAppContact::primaryWaId('9123 - 4567 9988 - 7766')));
        $this->assertFalse(WhatsAppContact::isSendable(WhatsAppContact::primaryWaId('9123 - 4567 (ابني 9988 7766)')));

        // الأوّلُ القابلُ للإرسال لا السلسلةُ كلُّها رقماً واحداً من ستّة عشر خانة
        $this->assertSame('96891234567', WhatsAppContact::primaryWaId('91234567 / 99887766'));
        $this->assertFalse(WhatsAppContact::isSendable('9689123456799887766'));
    }

    /** وإشعارُ موكّلٍ في هاتفه رقمان يذهب إلى الأوّل لا إلى سلسلتهما. */
    public function test_a_notification_goes_to_the_first_of_two_numbers(): void
    {
        $this->client->update(['phone' => '91234567 / 99887766']);
        $note = ClientNotification::create([
            'client_id' => $this->client->id, 'case_id' => $this->case->id,
            'type' => ClientEvents::CASE_UPDATE, 'title' => 'تحديث', 'event_key' => 'x',
            'channel_state' => ClientNotification::PENDING,
        ]);

        app()->call([new SendClientNotification($note->id), 'handle']);

        $contact = WhatsAppContact::where('client_id', $this->client->id)->first()
            ?? WhatsAppContact::first();
        $this->assertNotNull($contact, 'لم تُقيَّد رسالةٌ أصلاً: ' . $note->fresh()->channel_reason);
        $this->assertSame('96891234567', $contact->wa_id);
    }

    // ── ٣) الحجزُ والضائع وسقفُ الإحماء ──────────────────────────

    private function outMessage(array $attrs = []): WhatsAppMessage
    {
        $contact = WhatsAppContact::firstOrCreate(['wa_id' => '96891234567'], ['client_id' => $this->client->id]);
        $thread = WhatsAppConversation::firstOrCreate(['contact_id' => $contact->id], [
            'status' => WhatsAppConversation::STATUS_OPEN, 'unread_count' => 0,
        ]);

        return WhatsAppMessage::create($attrs + [
            'conversation_id' => $thread->id, 'direction' => WhatsAppMessage::OUT,
            'type' => 'text', 'body' => 'تنبيه', 'status' => WhatsAppMessage::STATUS_QUEUED,
        ]);
    }

    /** ما حان موعدُه يُفرج عنه كلَّ دقيقة، لا بعد خمسٍ — وما لم يحن يبقى. */
    public function test_release_held_dispatches_only_what_is_due(): void
    {
        $due = $this->outMessage();
        $due->forceFill(['hold_until' => now()->subMinute()])->save();
        $later = $this->outMessage();
        $later->forceFill(['hold_until' => now()->addHour()])->save();

        $this->artisan('whatsapp:release-held')->assertSuccessful();

        Queue::assertPushed(SendWhatsAppMessage::class, 1);
        Queue::assertPushed(SendWhatsAppMessage::class, fn ($job) => $this->jobMessageId($job) === $due->id);
    }

    /** إشعارٌ ورسالةٌ ضاعت مهمّتاهما يُعاد دفعُهما — وصفُّ رمز البوابة لا يُرسَل أبداً. */
    public function test_the_sweep_redispatches_what_was_lost_but_never_an_otp_ledger_row(): void
    {
        $lostNote = ClientNotification::create([
            'client_id' => $this->client->id, 'type' => ClientEvents::CASE_UPDATE,
            'title' => 'تحديث', 'event_key' => 'lost', 'channel_state' => ClientNotification::PENDING,
        ]);
        ClientNotification::whereKey($lostNote->id)->update(['created_at' => now()->subMinutes(30), 'updated_at' => now()->subMinutes(30)]);

        // مهمّتُه حيّةٌ تُعاد كلَّ خمس دقائق ولمسته قبل دقيقتين — لا تُدفع له نسخة
        $liveNote = ClientNotification::create([
            'client_id' => $this->client->id, 'type' => ClientEvents::CASE_UPDATE,
            'title' => 'تحديث', 'event_key' => 'live', 'channel_state' => ClientNotification::PENDING,
        ]);
        ClientNotification::whereKey($liveNote->id)->update(['created_at' => now()->subMinutes(30), 'updated_at' => now()->subMinutes(2)]);

        $freshNote = ClientNotification::create([
            'client_id' => $this->client->id, 'type' => ClientEvents::CASE_UPDATE,
            'title' => 'تحديث', 'event_key' => 'fresh', 'channel_state' => ClientNotification::PENDING,
        ]);

        $lostMsg = $this->outMessage();
        $this->ageMessage($lostMsg, 30);
        $ledger = $this->outMessage(['body' => PortalOtp::LEDGER_BODY]);
        $this->ageMessage($ledger, 30);

        // مهمّةٌ في مهلة إعادة المحاولة لمست صفَّها قبل دقائق — ليست ضائعة
        $retrying = $this->outMessage();
        $this->ageMessage($retrying, 30, touchedMinutesAgo: 5);

        // وما تجاوز ستَّ ساعات خبرٌ فات — لا يُرسَل متأخّراً
        $stale = $this->outMessage();
        $this->ageMessage($stale, 7 * 60);
        $staleNote = ClientNotification::create([
            'client_id' => $this->client->id, 'type' => ClientEvents::CASE_UPDATE,
            'title' => 'تحديث', 'event_key' => 'stale', 'channel_state' => ClientNotification::PENDING,
        ]);
        ClientNotification::whereKey($staleNote->id)->update(['created_at' => now()->subHours(7), 'updated_at' => now()->subHours(7)]);

        $this->artisan('whatsapp:sweep')->assertSuccessful();

        Queue::assertPushed(SendClientNotification::class, fn ($job) => $this->jobNoteId($job) === $lostNote->id);
        Queue::assertNotPushed(SendClientNotification::class, fn ($job) => $this->jobNoteId($job) === $freshNote->id);
        Queue::assertPushed(SendWhatsAppMessage::class, fn ($job) => $this->jobMessageId($job) === $lostMsg->id);
        Queue::assertNotPushed(SendWhatsAppMessage::class, fn ($job) => $this->jobMessageId($job) === $ledger->id);
        Queue::assertNotPushed(SendWhatsAppMessage::class, fn ($job) => $this->jobMessageId($job) === $retrying->id);
        Queue::assertNotPushed(SendWhatsAppMessage::class, fn ($job) => $this->jobMessageId($job) === $stale->id);
        Queue::assertNotPushed(SendClientNotification::class, fn ($job) => $this->jobNoteId($job) === $staleNote->id);
        Queue::assertNotPushed(SendClientNotification::class, fn ($job) => $this->jobNoteId($job) === $liveNote->id);
    }

    private function ageMessage(WhatsAppMessage $message, int $minutes, ?int $touchedMinutesAgo = null): void
    {
        WhatsAppMessage::whereKey($message->id)->update([
            'created_at' => now()->subMinutes($minutes),
            'updated_at' => now()->subMinutes($touchedMinutesAgo ?? $minutes),
        ]);
    }

    /** موكّلٌ كتب «إيقاف» بعد أن حُجزت رسالتُه لا تصله حين يُفرج عنها. */
    public function test_an_opt_out_after_queueing_stops_the_send(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        $this->travelTo(now()->setTime(12, 0));

        $message = $this->outMessage();
        $message->conversation->contact->forceFill(['opted_out_at' => now()])->save();

        app()->call([new SendWhatsAppMessage($message->id), 'handle']);

        \Illuminate\Support\Facades\Http::assertNothingSent();
        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $message->fresh()->status);
        // قرارُ الموكّل ليس عطلاً في الخدمة
        $this->assertEmpty(Setting::get(WhatsAppSettings::KEY_LAST_ERROR));
    }

    /** إعادةُ مهمّةِ الإشعار بعد أن قُيّدت رسالتُها لا تُنشئ رسالةً ثانية. */
    public function test_a_retried_notification_job_queues_one_message(): void
    {
        $note = ClientNotification::create([
            'client_id' => $this->client->id, 'case_id' => $this->case->id,
            'type' => ClientEvents::CASE_UPDATE, 'title' => 'تحديث', 'event_key' => 'retry',
            'channel_state' => ClientNotification::PENDING,
        ]);

        app()->call([new SendClientNotification($note->id), 'handle']);
        app()->call([new SendClientNotification($note->id), 'handle']);

        $this->assertSame(1, WhatsAppMessage::where('direction', WhatsAppMessage::OUT)->count());
        $this->assertNotNull($note->fresh()->notified_at);
    }

    /** رموزُ البوابة لا تُحسب من سقف الإشعارات. */
    public function test_portal_codes_do_not_use_up_the_daily_budget(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $before = SendingGuard::remainingToday();

        foreach (range(1, 3) as $i) {
            $this->outMessage(['body' => PortalOtp::LEDGER_BODY, 'status' => WhatsAppMessage::STATUS_SENT])
                ->forceFill(['sent_at' => now()->subMinutes($i)])->save();
        }
        $this->assertSame($before, SendingGuard::remainingToday());

        $this->outMessage(['status' => WhatsAppMessage::STATUS_SENT])->forceFill(['sent_at' => now()])->save();
        $this->assertSame(max(0, $before - 1), SendingGuard::remainingToday());
    }

    private function jobMessageId(SendWhatsAppMessage $job): int
    {
        return $job->messageId;
    }

    private function jobNoteId(SendClientNotification $job): int
    {
        return $job->notificationId;
    }
}
