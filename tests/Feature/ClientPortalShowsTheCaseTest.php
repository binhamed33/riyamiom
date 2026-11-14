<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\Document;
use App\Models\FinanceFee;
use App\Models\FinanceInvoice;
use App\Models\LegalCase;
use App\Models\Session as CourtSession;
use App\Models\Setting;
use App\Models\User;
use App\Support\ClientEvents;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * «الموكّل لا يرى في البوابة الرسوم والمبالغ والمستندات ولا الجلسات وايش
 * صار فيها — كأنّ وجودها عدمها».
 *
 * ثلاثةُ أسبابٍ اجتمعت: قسما المستندات والحسابات مطفآن افتراضاً، والبنودُ
 * تُرفع مخفيّةً، والجلسةُ لا مكانَ فيها لما يُقال للموكّل («القرار»
 * و«الملاحظات» داخليّان). وما يُتاح الآن لا يوسّع الحاجز: الداخليُّ والخاصُّ
 * يبقيان خارج البوابة.
 */
class ClientPortalShowsTheCaseTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;
    private User $admin;
    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');
        ClientEvents::setMasterEnabled(true);

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->client = Client::create(['name' => 'سالم الحارثي', 'phone' => '91234567', 'type' => 'individual']);
        $this->case = LegalCase::create([
            'case_number' => '2026/77', 'title' => 'قضية', 'type' => 'civil',
            'description' => 'وصف', 'court' => 'الابتدائية', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium', 'client_id' => $this->client->id,
        ]);
    }

    private function doc(string $title, bool $visible, string $access = Document::ACCESS_ALL): Document
    {
        return Document::create([
            'case_id' => $this->case->id, 'uploaded_by' => $this->admin->id, 'title' => $title,
            'file_path' => 'documents/' . md5($title) . '.pdf', 'file_type' => 'pdf', 'file_size' => 10,
            'access_level' => $access, 'client_visible' => $visible,
        ]);
    }

    private function fee(string $type, bool $visible): FinanceFee
    {
        return FinanceFee::create([
            'case_id' => $this->case->id, 'fee_type' => $type, 'amount' => 30,
            'status' => 'unpaid', 'client_visible' => $visible, 'date' => now()->toDateString(),
        ]);
    }

    private function invoice(string $number, bool $visible): FinanceInvoice
    {
        return FinanceInvoice::create([
            'invoice_number' => $number, 'client_id' => $this->client->id, 'case_id' => $this->case->id,
            'amount' => 200, 'paid_amount' => 50, 'status' => 'partial', 'client_visible' => $visible,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addMonth()->toDateString(),
        ]);
    }

    // ── ما يراه الموكّل ─────────────────────────────────────────

    /** مكتبٌ لم يلمس إعدادات البوابة: ما عُلّم مرئيّاً يظهر، والداخليُّ لا. */
    public function test_a_fresh_office_shows_what_was_shared_and_hides_the_rest(): void
    {
        $this->doc('عقد الإيجار', true);
        $this->doc('مذكرة داخلية', false);
        $this->doc('تقرير خاص', true, Document::ACCESS_PRIVATE);
        $this->fee('رسوم قيد الدعوى', true);
        $this->fee('أتعاب قيد المراجعة', false);
        $this->invoice('INV-SHOWN', true);
        $this->invoice('INV-HIDDEN', false);
        CourtSession::create([
            'case_id' => $this->case->id, 'date' => now()->subDay()->setTime(9, 0), 'location' => 'الابتدائية',
            'status' => 'postponed', 'report' => 'خطّتنا: طلبُ ندب خبير',
            'client_summary' => 'حضرنا وقدّمنا المذكرة، وأُجّلت للردّ.',
        ]);

        $page = $this->portalLogin($this->client)->get(route('client.portal.case', $this->case))->assertOk();

        $page->assertSee('عقد الإيجار')
            ->assertSee('رسوم قيد الدعوى')
            ->assertSee('INV-SHOWN')
            ->assertSee('حضرنا وقدّمنا المذكرة، وأُجّلت للردّ.')
            ->assertSee(__('portal.sessions.summary'));

        $page->assertDontSee('مذكرة داخلية')
            ->assertDontSee('تقرير خاص')
            ->assertDontSee('أتعاب قيد المراجعة')
            ->assertDontSee('INV-HIDDEN')
            ->assertDontSee('طلبُ ندب خبير');
    }

    /** ومكتبٌ أطفأ القسمَ بيده يبقى على قراره. */
    public function test_an_office_that_turned_a_section_off_keeps_it_off(): void
    {
        Setting::set(ClientPortal::KEY_SHOW_ACCOUNTING, '0', 'client_portal');
        $this->fee('رسوم قيد الدعوى', true);

        $this->portalLogin($this->client)->get(route('client.portal.case', $this->case))
            ->assertOk()->assertDontSee('رسوم قيد الدعوى');
    }

    // ── ما جرى في الجلسة ────────────────────────────────────────

    private function courtSession(): CourtSession
    {
        return CourtSession::create([
            'case_id' => $this->case->id, 'date' => now()->subDay()->setTime(9, 0),
            'location' => 'الابتدائية', 'status' => 'completed',
        ]);
    }

    private function saveSession(CourtSession $s, array $extra)
    {
        return $this->actingAs($this->admin)->putJson(route('sessions.update', $s), $extra + [
            'case_id' => $s->case_id, 'date' => $s->date->format('Y-m-d H:i'),
            'location' => $s->location, 'status' => $s->status, 'notes' => '', 'report' => 'داخلي',
        ]);
    }

    /** يُبلَّغ الموكّلُ مرّةً حين يُكتب له — وتصحيحُ كلمةٍ لا يُرسل ثانيةً. */
    public function test_writing_for_the_client_notifies_once(): void
    {
        $s = $this->courtSession();
        ClientNotification::query()->delete();

        $this->saveSession($s, ['client_summary' => 'انعقدت وحُجزت للحكم.'])->assertOk()->assertJson(['success' => true]);
        $this->saveSession($s->fresh(), ['client_summary' => 'انعقدت الجلسة وحُجزت للحكم.'])->assertOk();

        $sent = ClientNotification::where('type', ClientEvents::SESSION_RESULT)->get();
        $this->assertCount(1, $sent);
        // النصُّ يُقرأ في البوابة لا في إشعار الشاشة المقفلة
        $this->assertStringNotContainsString('للحكم', $sent[0]->title . $sent[0]->body);
        $this->assertSame('انعقدت الجلسة وحُجزت للحكم.', $s->fresh()->client_summary);
    }

    /** نافذةُ «القرار» القديمة (بلا الخانة) لا تمحو ما كُتب للموكّل. */
    public function test_a_form_without_the_field_does_not_erase_it(): void
    {
        $s = $this->courtSession();
        $s->update(['client_summary' => 'حُجزت للحكم.']);

        $this->saveSession($s->fresh(), [])->assertOk();
        $this->assertSame('حُجزت للحكم.', $s->fresh()->client_summary);

        // ونموذجُ تعديل القضية كذلك: الصفُّ بلا الخانة لا يمسّها، وبها يكتبها
        $form = fn (array $row) => $this->actingAs($this->admin)->put(route('cases.update', $this->case), [
            'case_number' => $this->case->case_number, 'description' => 'وصف', 'court' => 'الابتدائية',
            'opponent' => 'خصم', 'status' => 'active', 'priority' => 'medium', 'client_id' => $this->client->id,
            'sessions' => [$row + ['id' => $s->id, 'date' => $s->date->format('Y-m-d H:i'), 'status' => 'completed']],
        ])->assertRedirect(route('cases.show', $this->case));

        $form([]);
        $this->assertSame('حُجزت للحكم.', $s->fresh()->client_summary);

        $form(['client_summary' => 'صدر الحكم لصالحكم.']);
        $this->assertSame('صدر الحكم لصالحكم.', $s->fresh()->client_summary);
    }

    // ── «إظهار الكل للموكّل» ────────────────────────────────────

    /** ضغطةٌ واحدة تُتيح المخفيَّ كلَّه عدا الخاصّ — وإشعارٌ واحدٌ لا سيلُ رسائل. */
    public function test_share_all_shows_everything_but_private_with_one_notice(): void
    {
        $hiddenDoc = $this->doc('مذكرة', false);
        $private = $this->doc('تقرير خاص', false, Document::ACCESS_PRIVATE);
        $fee = $this->fee('رسوم', false);
        $invoice = $this->invoice('INV-1', false);
        ClientNotification::query()->delete();

        $this->actingAs($this->admin)->get(route('cases.show', $this->case))
            ->assertOk()->assertSee('data-share-all', false)->assertSee(__('app.portal_share_all'));

        $this->actingAs($this->admin)->post(route('cases.share-with-client', $this->case))
            ->assertRedirect(route('cases.show', $this->case))
            ->assertSessionHas('success', __('app.portal_share_all_done', ['count' => 3]));

        $this->assertTrue($hiddenDoc->fresh()->client_visible);
        $this->assertFalse($private->fresh()->client_visible);
        $this->assertTrue($fee->fresh()->client_visible);
        $this->assertTrue($invoice->fresh()->client_visible);

        $this->assertSame(1, ClientNotification::count());
        $this->assertSame(ClientEvents::CASE_UPDATE, ClientNotification::first()->type);

        // ولا شيء بقي: الزرُّ يختفي، وضغطةٌ ثانيةٌ لا تُرسل شيئاً
        $this->actingAs($this->admin)->get(route('cases.show', $this->case))->assertDontSee('data-share-all', false);
        $this->actingAs($this->admin)->post(route('cases.share-with-client', $this->case))
            ->assertSessionHas('success', __('app.portal_share_all_none'));
        $this->assertSame(1, ClientNotification::count());
    }

    /** المالُ لمدير المكتب ومحامي القضية: الموظّفُ لا يرى الزرَّ ولا يصل إليه. */
    public function test_staff_cannot_share_the_case_finances(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $fee = $this->fee('رسوم', false);

        $this->actingAs($staff)->get(route('cases.show', $this->case))->assertOk()->assertDontSee('data-share-all', false);
        // ‏403 يُحوَّل إلى اللوحة برسالةٍ تقول السبب (bootstrap/app.php) — والمهمُّ أنّ شيئاً لم يُتَح
        $this->actingAs($staff)->post(route('cases.share-with-client', $this->case))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertFalse($fee->fresh()->client_visible);
    }

    /** مستندُ الرفع السريع مرئيٌّ افتراضاً لمن يملك الإتاحة — ومخفيٌّ لمن لا يملكها. */
    public function test_quick_upload_defaults_to_visible_only_for_those_who_may_share(): void
    {
        $this->actingAs($this->admin)->get(route('cases.show', $this->case))
            ->assertSee("docForm: { title: '', file: null, client_visible: true }", false);

        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $this->actingAs($staff)->get(route('cases.show', $this->case))
            ->assertSee("docForm: { title: '', file: null, client_visible: false }", false);
    }

    // ── ما أكّدته المراجعة ──────────────────────────────────────

    /** المحامي يُتيح ما أنشأه هو فقط — رسمٌ داخليٌّ سجّله المديرُ يبقى مخفيّاً. */
    public function test_the_case_lawyer_shares_only_the_finance_rows_they_created(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $this->case->update(['lawyer_id' => $lawyer->id]);

        $adminFee = $this->fee('أتعاب قيد المراجعة', false);
        $adminFee->forceFill(['user_id' => $this->admin->id])->save();
        $ownFee = $this->fee('رسوم قيد', false);
        $ownFee->forceFill(['user_id' => $lawyer->id])->save();

        $this->actingAs($lawyer)->post(route('cases.share-with-client', $this->case))
            ->assertSessionHas('success', __('app.portal_share_all_done', ['count' => 1]));

        $this->assertTrue($ownFee->fresh()->client_visible);
        $this->assertFalse($adminFee->fresh()->client_visible);
    }

    /** فاتورةُ موكّلٍ آخر على القضية لا تُتاح لموكّلها، ولا تظهر له ولو عُلّمت. */
    public function test_another_clients_invoice_on_the_case_is_never_shown(): void
    {
        $other = Client::create(['name' => 'كفيل', 'phone' => '99887766', 'type' => 'individual']);
        $theirs = $this->invoice('INV-GUARANTOR', false);
        $theirs->forceFill(['client_id' => $other->id])->save();

        $this->actingAs($this->admin)->post(route('cases.share-with-client', $this->case));
        $this->assertFalse($theirs->fresh()->client_visible);

        $theirs->forceFill(['client_visible' => true])->save();
        $this->portalLogin($this->client)->get(route('client.portal.case', $this->case))
            ->assertOk()->assertDontSee('INV-GUARANTOR');
    }

    /** قسمٌ أطفأه المكتب لا يُعدّ على الزرّ ولا يُتاح به. */
    public function test_share_all_skips_a_section_the_office_turned_off(): void
    {
        Setting::set(ClientPortal::KEY_SHOW_ACCOUNTING, '0', 'client_portal');
        $fee = $this->fee('رسوم', false);
        $doc = $this->doc('مذكرة', false);

        $this->actingAs($this->admin)->post(route('cases.share-with-client', $this->case))
            ->assertSessionHas('success', __('app.portal_share_all_done', ['count' => 1]));

        $this->assertTrue($doc->fresh()->client_visible);
        $this->assertFalse($fee->fresh()->client_visible);
    }

    /** إشعارُ «أُتيح لكم» لكلّ قضية — لا لأوّل موكّلٍ في المكتب يومَها. */
    public function test_share_all_notifies_each_case_on_the_same_day(): void
    {
        $second = LegalCase::create([
            'case_number' => '2026/78', 'title' => 'ثانية', 'type' => 'civil', 'description' => 'وصف',
            'court' => 'الابتدائية', 'opponent' => 'خصم', 'status' => 'active', 'priority' => 'medium',
            'client_id' => Client::create(['name' => 'موكّل ثانٍ', 'phone' => '92223333', 'type' => 'individual'])->id,
        ]);
        $this->fee('رسوم', false);
        FinanceFee::create(['case_id' => $second->id, 'fee_type' => 'رسوم', 'amount' => 5, 'status' => 'unpaid', 'client_visible' => false, 'date' => now()->toDateString()]);
        ClientNotification::query()->delete();

        $this->actingAs($this->admin)->post(route('cases.share-with-client', $this->case));
        $this->actingAs($this->admin)->post(route('cases.share-with-client', $second));

        $this->assertSame(2, ClientNotification::where('type', ClientEvents::CASE_UPDATE)->count());
    }

    /** جلسةٌ تُقيَّد ومعها ما جرى، أو تأجيلٌ كُتب معه ما جرى: رسالةٌ واحدة. */
    public function test_one_save_is_one_message(): void
    {
        ClientNotification::query()->delete();
        $s = CourtSession::create([
            'case_id' => $this->case->id, 'date' => now()->subDay()->setTime(9, 0), 'location' => 'الابتدائية',
            'status' => 'completed', 'client_summary' => 'انعقدت وحُجزت للحكم.',
        ]);
        $this->assertSame([ClientEvents::SESSION_RESULT], ClientNotification::pluck('type')->all());

        ClientNotification::query()->delete();
        $s->update(['date' => now()->addWeek()->setTime(9, 0), 'status' => 'postponed', 'client_summary' => 'أُجّلت للمرافعة.']);
        $this->assertSame([ClientEvents::SESSION_RESULT], ClientNotification::pluck('type')->all());

        // ومكتبٌ أطفأ «ما جرى» يبقى يصله خبرُ الموعد
        ClientNotification::query()->delete();
        ClientEvents::setEnabled(ClientEvents::SESSION_RESULT, false);
        $s->update(['date' => now()->addWeeks(2)->setTime(9, 0), 'client_summary' => 'أُجّلت ثانيةً.']);
        $this->assertSame([ClientEvents::SESSION_MOVED], ClientNotification::pluck('type')->all());
    }

    /** جلسةُ الإضافة السريعة بلا مكان: نافذةُ «القرار» تحفظ ما يُكتب للموكّل. */
    public function test_the_decision_modal_saves_for_a_session_without_a_location(): void
    {
        $s = CourtSession::create(['case_id' => $this->case->id, 'date' => now()->subDay()->setTime(9, 0), 'location' => '', 'status' => 'completed']);

        $this->saveSession($s, ['location' => '', 'client_summary' => 'انعقدت.'])->assertOk();

        $this->assertSame('انعقدت.', $s->fresh()->client_summary);
        $this->assertSame('', (string) $s->fresh()->location);
    }

    /** البوابةُ القديمة (/my/sessions) تعرض ما كُتب للموكّل لا القرارَ الداخلي. */
    public function test_the_legacy_portal_never_shows_the_internal_decision(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $this->client->forceFill(['user_id' => $user->id])->save();
        CourtSession::create([
            'case_id' => $this->case->id, 'date' => now()->subDay()->setTime(9, 0), 'location' => 'الابتدائية',
            'status' => 'completed', 'report' => 'خطّتنا السرّية', 'notes' => 'ملاحظة داخلية', 'client_summary' => 'انعقدت وحُجزت للحكم.',
        ]);

        $this->actingAs($user)->get(route('client.sessions'))->assertOk()
            ->assertSee('انعقدت وحُجزت للحكم.')->assertDontSee('خطّتنا السرّية')->assertDontSee('ملاحظة داخلية');
    }
}
