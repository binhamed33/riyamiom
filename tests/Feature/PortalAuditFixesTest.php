<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\FinanceInvoice;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClientPortal\ClientCaseGateway;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * عيوبُ بوابة الموكّل التي رصدها تدقيقُ البروفة.
 *
 * ‏A8  جلسةٌ مضت ولم يُغلقها المكتب تُعرض «الجلسة القادمة … اليوم».
 * ‏A9  رابطُ واتساب «#documents» يهبط على رأس الصفحة والقسمُ مطويّ.
 * ‏A10 «متبقٍّ 5 يوماً» و«2 قضايا».
 * ‏A11 «أُضيف 27-09-2026 · KB 75».
 * ‏A12 مجاميعُ المحاسبة بلا تنسيق (cd-facts لا تعريفَ له).
 * ‏A13 الرئيسةُ تفيض أفقيّاً على الهاتف.
 * ‏A18 فاتورةٌ ملغاةٌ مشارَكةٌ تُحسب في «المتبقّي عليكم».
 */
class PortalAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;
    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('client-portal:lookup:127.0.0.1');
        $this->travelTo(Carbon::parse('2026-10-18 10:00:00', 'Asia/Muscat'));

        // البوابةُ بلغة المكتب — العربيّة (config/app.php)
        config(['app.locale' => 'ar']);

        foreach ([ClientPortal::KEY_ENABLED, ClientPortal::KEY_SHOW_SESSIONS, ClientPortal::KEY_SHOW_DOCUMENTS, ClientPortal::KEY_SHOW_TIMELINE, ClientPortal::KEY_SHOW_ACCOUNTING] as $key) {
            Setting::set($key, '1', 'client_portal');
        }

        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $this->client = Client::factory()->create(['name' => 'سالم بن حمد', 'national_id' => '4444444444', 'phone' => '+968 90000444']);
        $this->case = LegalCase::factory()->create([
            'client_id' => $this->client->id, 'lawyer_id' => $lawyer->id,
            'title' => 'مطالبة عمّالية بمستحقّات نهاية الخدمة لدى شركة المقاولات الكبرى', 'status' => 'active',
        ]);
    }

    private function signIn(): void
    {
        $this->post(route('client.access.lookup'), ['national_id' => '4444444444']);
        $this->post(route('client.access.verify'), ['digits' => '444']);
        $this->assertSame($this->client->id, session('client_access_id'));
    }

    // ══════════ A8 ══════════

    public function test_a_past_session_left_upcoming_is_not_the_next_session(): void
    {
        // نسيه المكتبُ «قادمة» قبل أربعة أيام
        Session::create(['case_id' => $this->case->id, 'date' => now()->subDays(4)->setTime(9, 0), 'status' => 'upcoming', 'location' => 'القاعة القديمة']);

        $this->assertCount(0, ClientCaseGateway::for($this->client)->upcomingSessions());

        $this->signIn();
        $html = $this->get(route('client.portal.home'))->assertOk()->getContent();
        $this->assertStringNotContainsString('القاعة القديمة', $html);
        $this->assertStringContainsString(__('portal.empty.sessions'), $html, 'لا جلسةَ قادمة — فيُقال ذلك لا «اليوم»');
    }

    // ══════════ A8 · A10 ══════════

    public function test_the_next_session_counts_days_in_arabic(): void
    {
        Session::create(['case_id' => $this->case->id, 'date' => now()->subDays(4)->setTime(9, 0), 'status' => 'upcoming', 'location' => 'القاعة القديمة']);
        Session::create(['case_id' => $this->case->id, 'date' => now()->addDays(5)->setTime(9, 0), 'status' => 'upcoming', 'location' => 'القاعة 3']);

        $this->signIn();
        $html = $this->get(route('client.portal.home'))->assertOk()->getContent();

        $this->assertStringContainsString('القاعة 3', $html);
        $this->assertStringContainsString('متبقٍّ 5 أيام', $html);
        $this->assertStringNotContainsString('يوماً', $html);
    }

    public function test_the_cases_count_agrees_with_its_noun(): void
    {
        $this->signIn();
        $this->get(route('client.portal.cases'))->assertOk()->assertSee('قضية واحدة')->assertDontSee('1 قضية');

        LegalCase::factory()->create(['client_id' => $this->client->id, 'title' => 'قضية ثانية']);
        $this->get(route('client.portal.cases'))->assertSee('قضيتان')->assertDontSee('2 قضايا');
    }

    // ══════════ A9 · A11 · A12 ══════════

    public function test_the_case_page_has_the_link_anchors_and_readable_document_meta(): void
    {
        $doc = Document::create([
            'case_id' => $this->case->id, 'title' => 'صحيفة الدعوى', 'file_path' => 'documents/claim.pdf',
            'file_type' => 'pdf', 'file_size' => 75 * 1024, 'access_level' => 'all', 'client_visible' => true,
        ]);
        $doc->forceFill(['created_at' => Carbon::parse('2026-09-27 11:00', 'Asia/Muscat')])->save();
        FinanceInvoice::create(['invoice_number' => 'INV-1', 'client_id' => $this->client->id, 'case_id' => $this->case->id, 'amount' => 100, 'paid_amount' => 40, 'status' => 'partial', 'client_visible' => true, 'issue_date' => '2026-10-01']);

        $this->signIn();
        $html = $this->get(route('client.portal.case', $this->case->id))->assertOk()->getContent();

        foreach (['documents', 'sessions', 'timeline', 'case'] as $target) {
            $this->assertStringContainsString('id="' . $target . '"', $html, "رابطُ «#{$target}» لا يجد قسمه");
        }
        $this->assertStringContainsString('openFromHash', $html, 'لا شيءَ يفتح القسمَ المطويّ عند الوصول بالرابط');

        $this->assertStringContainsString('أُضيف 27 سبتمبر 2026', $html);
        $this->assertStringContainsString('<bdi>75 ك.ب</bdi>', $html);
        $this->assertDoesNotMatchRegularExpression('/\d\s*KB/', $html);

        $this->assertStringNotContainsString('cd-facts', $html);
        $this->assertStringContainsString('<dl class="cd-dl"', $html);
    }

    // ══════════ A13 ══════════

    public function test_the_recent_list_cannot_overflow_on_a_phone(): void
    {
        $this->signIn();
        $html = $this->get(route('client.portal.home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\.hm-list \{[^}]*grid-template-columns: minmax\(0, 1fr\)/', $html);
        $this->assertMatchesRegularExpression('/\.hm-row \{[^}]*min-width: 0/', $html);
    }

    // ══════════ A18 ══════════

    public function test_a_shared_cancelled_invoice_is_not_owed(): void
    {
        FinanceInvoice::create(['invoice_number' => 'INV-OK', 'client_id' => $this->client->id, 'case_id' => $this->case->id, 'amount' => 200, 'paid_amount' => 50, 'status' => 'partial', 'client_visible' => true, 'issue_date' => '2026-10-01']);
        FinanceInvoice::create(['invoice_number' => 'INV-VOID', 'client_id' => $this->client->id, 'case_id' => $this->case->id, 'amount' => 900, 'paid_amount' => 0, 'status' => 'cancelled', 'client_visible' => true, 'issue_date' => '2026-10-02']);

        $acc = ClientCaseGateway::for($this->client)->accountingFor($this->case);
        $this->assertEqualsWithDelta(200.0, $acc['total'], 0.001);
        $this->assertEqualsWithDelta(150.0, $acc['due'], 0.001);

        $dues = ClientCaseGateway::for($this->client)->duesSummary();
        $this->assertEqualsWithDelta(150.0, $dues['due'], 0.001, 'الملغاةُ دخلت «المتبقّي عليكم»');
        $void = $dues['items']->firstWhere('label', 'INV-VOID');
        $this->assertSame(0.0, $void['remaining']);
        $this->assertTrue($void['cancelled']);

        $this->signIn();
        $html = $this->get(route('client.portal.home'))->assertOk()->getContent();
        $this->assertStringContainsString('150.00', $html);
        $this->assertStringNotContainsString('900.00 <small>', $html);
        $this->assertStringContainsString(__('portal.accounting.cancelled_badge'), $html);
    }
}
