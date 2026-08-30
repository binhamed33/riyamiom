<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FinanceFee;
use App\Models\FinanceInvoice;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * §13: محاسبة القضية امتداداً للقسم المالي لا نظاماً موازياً —
 * والمال لا يبلغ الموكّل إلا ببابين: مفتاح المكتب، وعلامة على البند.
 */
class CaseAccountingTest extends TestCase
{
    use RefreshDatabase;

    private User $lawyer;
    private Client $client;
    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('client-portal:lookup:127.0.0.1');

        $this->lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $this->client = Client::factory()->create([
            'name' => 'موكّل المحاسبة', 'national_id' => '3333333333', 'phone' => '+968 90000333',
        ]);
        $this->case = LegalCase::factory()->create([
            'client_id' => $this->client->id,
            'lawyer_id' => $this->lawyer->id,
            'title' => 'قضية المحاسبة',
            'status' => 'active',
        ]);
    }

    private function signInAsClient(): void
    {
        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
        $this->post(route('client.access.lookup'), ['national_id' => '3333333333']);
        $this->post(route('client.access.verify'), ['digits' => '333']);
        $this->assertSame($this->client->id, session('client_access_id'));
    }

    private function fee(array $attrs = []): FinanceFee
    {
        return FinanceFee::create(array_merge([
            'case_id' => $this->case->id,
            'fee_type' => 'رسوم رفع دعوى',
            'amount' => 120.500,
            'status' => 'unpaid',
            'client_visible' => false,
            'date' => now()->toDateString(),
            'user_id' => $this->lawyer->id,
        ], $attrs));
    }

    // ── من صفحة القضية ────────────────────────────────────────────

    public function test_a_lawyer_adds_a_fee_from_the_case_and_returns_to_it(): void
    {
        $this->actingAs($this->lawyer)
            ->post(route('finance.fees.store'), [
                'case_id' => $this->case->id,
                'fee_type' => 'أتعاب مرافعة',
                'amount' => 300,
                'status' => 'unpaid',
                'date' => now()->toDateString(),
                'client_visible' => 1,
                'return_to_case' => 1,
            ])
            ->assertRedirect(route('cases.show', $this->case));

        $fee = FinanceFee::firstOrFail();
        $this->assertSame($this->case->id, $fee->case_id);
        $this->assertTrue($fee->client_visible, 'العلامة تُحفظ كما اختارها المحامي');
    }

    public function test_the_same_fee_appears_in_the_office_finance_section(): void
    {
        $this->fee(['fee_type' => 'رسم مشترك']);

        // بندٌ واحد لا نسختان: صفحة القضية والقسم المالي يقرآن الجدول نفسه
        $this->actingAs($this->lawyer)->get(route('finance.index', ['tab' => 'fees']))
            ->assertOk()->assertSee('رسم مشترك');

        $this->actingAs($this->lawyer)->get(route('cases.show', $this->case))
            ->assertOk()->assertSee('رسم مشترك');
    }

    public function test_the_case_page_totals_what_is_due(): void
    {
        $this->fee(['amount' => 100, 'status' => 'paid']);
        $this->fee(['amount' => 60, 'status' => 'unpaid']);

        FinanceInvoice::create([
            'invoice_number' => 'INV-ACC-1',
            'client_id' => $this->client->id,
            'case_id' => $this->case->id,
            'amount' => 200,
            'paid_amount' => 50,
            'status' => 'partial',
            'issue_date' => now()->toDateString(),
            'user_id' => $this->lawyer->id,
        ]);

        $response = $this->actingAs($this->lawyer)->get(route('cases.show', $this->case))->assertOk();

        // المتبقي = (160 - 100) + (200 - 50) = 210
        $response->assertSee('210.00');
        $response->assertSee(__('app.outstanding'));
    }

    public function test_a_fee_never_reaches_the_client_without_both_gates(): void
    {
        $this->fee(['fee_type' => 'رسم مخفي', 'client_visible' => false]);
        $visible = $this->fee(['fee_type' => 'رسم معلن', 'client_visible' => true]);

        // الباب الأول مغلق: مفتاح المكتب مطفأ — فلا شيء يظهر ولو عُلّم البند
        Setting::set(ClientPortal::KEY_SHOW_ACCOUNTING, '0', 'client_portal');
        $this->signInAsClient();
        $this->get(route('client.portal.case', $this->case->id))
            ->assertOk()
            ->assertDontSee('رسم معلن')
            ->assertDontSee('رسم مخفي');

        // الباب الأول مفتوح: البند المعلَّم وحده يظهر
        Setting::set(ClientPortal::KEY_SHOW_ACCOUNTING, '1', 'client_portal');
        $this->get(route('client.portal.case', $this->case->id))
            ->assertOk()
            ->assertSee('رسم معلن')
            ->assertDontSee('رسم مخفي');

        $this->assertTrue($visible->client_visible);
    }

    public function test_the_client_sees_totals_of_visible_items_only(): void
    {
        Setting::set(ClientPortal::KEY_SHOW_ACCOUNTING, '1', 'client_portal');

        $this->fee(['amount' => 500, 'status' => 'unpaid', 'client_visible' => false]);
        $this->fee(['amount' => 100, 'status' => 'paid', 'client_visible' => true]);
        $this->fee(['amount' => 40, 'status' => 'unpaid', 'client_visible' => true]);

        $this->signInAsClient();
        $response = $this->get(route('client.portal.case', $this->case->id))->assertOk();

        // 140 لا 640: المخفي لا يُحتسب في مجموع يراه الموكّل
        $response->assertSee('140.00');
        $response->assertDontSee('640.00');
    }

    public function test_a_client_cannot_reach_another_clients_case_accounting(): void
    {
        Setting::set(ClientPortal::KEY_SHOW_ACCOUNTING, '1', 'client_portal');

        $stranger = Client::factory()->create(['name' => 'موكّل آخر', 'national_id' => '4444444444', 'phone' => '+968 90000444']);
        $strangerCase = LegalCase::factory()->create(['client_id' => $stranger->id, 'lawyer_id' => $this->lawyer->id]);
        FinanceFee::create([
            'case_id' => $strangerCase->id, 'fee_type' => 'رسم الغير', 'amount' => 90,
            'status' => 'unpaid', 'client_visible' => true, 'date' => now()->toDateString(),
        ]);

        $this->signInAsClient();
        $this->get(route('client.portal.case', $strangerCase->id))->assertNotFound();
    }

    public function test_a_staff_member_without_finance_rights_sees_no_money_on_the_case(): void
    {
        $this->fee(['fee_type' => 'رسم داخلي']);
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($staff)->get(route('cases.show', $this->case))
            ->assertOk()
            ->assertDontSee('رسم داخلي')
            ->assertDontSee(__('app.case_accounting'));
    }
}
