<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FinanceFee;
use App\Models\FinanceInvoice;
use App\Models\FinanceTransaction;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * القسمُ الماليّ كما ظهر في لقطات الإعلان.
 *
 * - تذييلُ كلِّ قائمةٍ مرقّمة «Showing 1 to 20 of 38 results».
 * - فاتورةٌ ملغاةٌ بمبلغها كاملاً أحمرَ تحت «المتبقي».
 * - نوافذُ التعديل تفتح حقولَ التاريخ فارغةً فيُرفض الحفظ.
 * - نموذجُ الفاتورة بلا قضية، فلا تبلغ «المحاسبة» في صفحة قضيتها.
 * - صفحةُ الطباعة «طباعة / Print» بخطٍّ احتياطيّ وبلا موكّلٍ ولا قضيةٍ ولا هاتف.
 * - «تاريخ الإضافة» ينقلب في RTL، والمتحكّمُ يحسب رسوماً لا تُعرض.
 * - Escape لا يغلق النافذة إلا والتركيزُ داخلها.
 */
class FinanceFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $lawyer;
    private Client $client;
    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        Carbon::setTestNow(Carbon::parse('2026-10-18 09:00', 'Asia/Muscat'));
        app()->setLocale('ar');

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'locale' => 'ar']);
        $this->lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'locale' => 'ar']);
        $this->client = Client::factory()->create([
            'name' => 'شركة الأفلاج', 'national_id' => '4444444444', 'phone' => '+968 9000 2308', 'email' => 'aflaj@example.com',
        ]);
        $this->case = LegalCase::factory()->create([
            'client_id' => $this->client->id, 'lawyer_id' => $this->lawyer->id,
            'title' => 'تظلّم من قرار استبعاد', 'office_case_number' => 353, 'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function invoice(array $attrs = []): FinanceInvoice
    {
        return FinanceInvoice::create(array_merge([
            'invoice_number' => 'ف/2026/' . random_int(1000, 9999), 'client_id' => $this->client->id, 'case_id' => $this->case->id,
            'amount' => 2835, 'paid_amount' => 0, 'status' => 'unpaid', 'client_visible' => false,
            'issue_date' => '2026-10-18', 'due_date' => '2026-11-01', 'user_id' => $this->admin->id,
        ], $attrs));
    }

    private function transaction(array $attrs = []): FinanceTransaction
    {
        return FinanceTransaction::create(array_merge([
            'type' => 'income', 'category' => 'أتعاب محاماة', 'amount' => 100, 'date' => '2026-10-18', 'user_id' => $this->admin->id,
        ], $attrs));
    }

    /** ‏403 يُعرض هنا تحويلاً إلى اللوحة برسالة — كما في FinanceIsolationTest. */
    private function assertRefused(\Illuminate\Testing\TestResponse $response): void
    {
        $this->assertContains($response->getStatusCode(), [302, 403]);
        if ($response->getStatusCode() === 302) {
            $this->assertStringContainsString('dashboard', (string) $response->headers->get('Location'));
        }
        $this->assertNull($response->headers->get('Content-Disposition'));
    }

    /** ما يضعه زرُّ التعديل في نافذته: ‎$dispatch('set-x', {…})‎ داخل سمةٍ مُهرَّبة. */
    private function modalPayload(string $html, string $event): array
    {
        $this->assertMatchesRegularExpression('/\$dispatch\(\'' . $event . '\', ([^"]+)\)"/', $html);
        preg_match('/\$dispatch\(\'' . $event . '\', ([^"]+)\)"/', $html, $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true);
    }

    /** ‏<input type=date> يمحو كلَّ قيمةٍ ليست Y-m-d — هذا ما كان يُفرغ الحقل. */
    private function dateInput(mixed $value): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    // ── ترقيمُ الصفحات ─────────────────────────────────────────────

    public function test_the_pagination_summary_is_arabic(): void
    {
        foreach (range(1, 21) as $i) {
            $this->transaction(['amount' => $i]);
        }

        $html = $this->actingAs($this->admin)->get(route('finance.index'))->assertOk()->getContent();

        $this->assertStringContainsString('عرض', $html);
        $this->assertMatchesRegularExpression('/من أصل\s*<span class="font-medium">21<\/span>\s*نتيجة/u', $html);
        $this->assertStringNotContainsString('Showing', $html);
        $this->assertDoesNotMatchRegularExpression('/<\/span>\s*results/', $html);
        $this->assertSame('التالي', __('pagination.next'));
        $this->assertSame('السابق', __('pagination.previous'));
    }

    // ── الفاتورة الملغاة ───────────────────────────────────────────

    public function test_a_cancelled_invoice_has_nothing_remaining(): void
    {
        $cancelled = $this->invoice(['status' => 'cancelled', 'invoice_number' => 'ف/2026/0173']);

        $this->assertSame(0.0, $cancelled->remaining_amount);

        $html = $this->actingAs($this->admin)->get(route('finance.index', ['tab' => 'invoices']))->assertOk()->getContent();
        $this->assertStringContainsString('<td class="px-4 py-3 font-bold text-gray-400">ملغاة</td>', $html);
        $this->assertStringNotContainsString('text-red-700">2,835.00', $html);

        $show = $this->actingAs($this->admin)->get(route('finance.invoices.show', $cancelled))->assertOk()->getContent();
        $this->assertStringContainsString('<dd class="font-medium text-gray-400">ملغاة</dd>', $show);
    }

    public function test_the_portal_does_not_call_a_cancelled_invoice_paid(): void
    {
        RateLimiter::clear('client-portal:lookup:127.0.0.1');
        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
        Setting::set(ClientPortal::KEY_SHOW_ACCOUNTING, '1', 'client_portal');
        $this->invoice(['status' => 'cancelled', 'client_visible' => true]);

        $this->post(route('client.access.lookup'), ['national_id' => '4444444444']);
        $this->post(route('client.access.verify'), ['digits' => '308']);
        $this->assertSame($this->client->id, session('client_access_id'));

        $html = $this->get(route('client.portal.case', $this->case->id))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/p-badge mute">\s*' . preg_quote(__('portal.accounting.cancelled_badge'), '/') . '/u', $html);
    }

    // ── نوافذُ التعديل ─────────────────────────────────────────────

    public function test_the_invoice_edit_modal_opens_with_its_dates_and_saves(): void
    {
        $invoice = $this->invoice();
        $html = $this->actingAs($this->admin)->get(route('finance.index', ['tab' => 'invoices']))->assertOk()->getContent();
        $inv = $this->modalPayload($html, 'set-inv');

        // كان «2026-10-17T20:00:00.000000Z»: لحظةُ منتصف ليل مسقط بتوقيت UTC
        $this->assertSame('2026-10-18', $inv['issue_date']);
        $this->assertSame('2026-11-01', $inv['due_date']);

        // ما ترسله النافذة: كلُّ حقلٍ مربوطٍ بـx-model كما يملؤه المتصفّح
        $this->actingAs($this->admin)->put(route('finance.invoices.update', $invoice), [
            'invoice_number' => $inv['invoice_number'],
            'client_id' => (string) ($inv['client_id'] ?? ''),
            'case_id' => (string) ($inv['case_id'] ?? ''),
            'amount' => $inv['amount'],
            'status' => $inv['status'],
            'issue_date' => $this->dateInput($inv['issue_date']),
            'due_date' => $this->dateInput($inv['due_date']),
            'description' => 'عُدّلت من النافذة',
            'client_visible' => $inv['client_visible'] ? '1' : '0',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $invoice->refresh();
        $this->assertSame('عُدّلت من النافذة', $invoice->description);
        $this->assertSame('2026-10-18', $invoice->issue_date->format('Y-m-d'));
        $this->assertSame($this->case->id, $invoice->case_id);
        $this->assertSame($this->client->id, $invoice->client_id);
    }

    public function test_the_transaction_and_fee_edit_modals_carry_their_dates(): void
    {
        $tx = $this->transaction();
        $fee = FinanceFee::create([
            'case_id' => $this->case->id, 'fee_type' => 'رسوم نسخة رسمية', 'amount' => 10, 'status' => 'paid',
            'client_visible' => false, 'date' => '2026-10-18', 'user_id' => $this->admin->id,
        ]);

        $txData = $this->modalPayload($this->actingAs($this->admin)->get(route('finance.index'))->getContent(), 'set-tx');
        $this->assertSame('2026-10-18', $txData['date']);
        $this->actingAs($this->admin)->put(route('finance.transactions.update', $tx), [
            'type' => $txData['type'], 'category' => $txData['category'], 'amount' => $txData['amount'],
            'date' => $this->dateInput($txData['date']), 'payment_method' => '', 'reference' => '', 'description' => 'معدّلة',
        ])->assertSessionHasNoErrors();
        $this->assertSame('معدّلة', $tx->fresh()->description);

        $feeHtml = $this->actingAs($this->admin)->get(route('finance.index', ['tab' => 'fees']))->getContent();
        $feeData = $this->modalPayload($feeHtml, 'set-fee');
        $this->assertSame('2026-10-18', $feeData['date']);
        // القضيةُ مربوطةٌ بـx-model وتُزامَن مع TomSelect — لا x-bind:selected يعرض «اختر»
        $this->assertStringContainsString('x-model="fee.case_id"', $feeHtml);
        $this->assertStringNotContainsString('x-bind:selected="fee.case_id', $feeHtml);
        $this->actingAs($this->admin)->put(route('finance.fees.update', $fee), [
            'case_id' => (string) $feeData['case_id'], 'fee_type' => $feeData['fee_type'], 'amount' => $feeData['amount'],
            'status' => $feeData['status'], 'date' => $this->dateInput($feeData['date']), 'description' => 'معدّل',
        ])->assertSessionHasNoErrors();
        $this->assertSame('معدّل', $fee->fresh()->description);
    }

    public function test_an_edit_without_a_trashed_client_keeps_its_link(): void
    {
        $invoice = $this->invoice();
        $this->client->delete();

        // الموكّلُ في سلّة المحذوفات ليس في قائمة النافذة، فيصل حقلُه فارغاً
        $this->actingAs($this->admin)->put(route('finance.invoices.update', $invoice), [
            'invoice_number' => $invoice->invoice_number, 'client_id' => '', 'case_id' => (string) $this->case->id,
            'amount' => '2835', 'status' => 'unpaid', 'issue_date' => '2026-10-18', 'client_visible' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->client->id, $invoice->fresh()->client_id);
    }

    public function test_escape_closes_every_finance_modal_from_anywhere(): void
    {
        $html = $this->actingAs($this->admin)->get(route('finance.index'))->assertOk()->getContent();

        preg_match_all('/<div id="(tx|txEdit|inv|invEdit|fee|feeEdit)Modal"[^\n]*/', $html, $modals);
        $this->assertCount(6, $modals[0]);
        foreach ($modals[0] as $tag) {
            $this->assertStringContainsString('@keydown.escape.window="open = false"', $tag);
        }
    }

    // ── ربطُ الفاتورة بقضية ───────────────────────────────────────

    public function test_the_add_invoice_form_links_a_case_and_reaches_its_accounting(): void
    {
        $html = $this->actingAs($this->lawyer)->get(route('finance.index', ['tab' => 'invoices']))->assertOk()->getContent();
        $start = strpos($html, 'id="invModal"');
        $form = substr($html, $start, strpos($html, 'id="feeModal"') - $start);
        $this->assertStringContainsString('name="case_id"', $form, 'نموذجُ الفاتورة بلا حقل قضية');
        $this->assertStringContainsString('<option value="">' . __('app.finance_select') . '</option>', $form);

        $this->actingAs($this->lawyer)->post(route('finance.invoices.store'), [
            'invoice_number' => 'ف/2026/0500', 'client_id' => '', 'case_id' => (string) $this->case->id,
            'amount' => '300', 'status' => 'unpaid', 'issue_date' => '2026-10-18', 'client_visible' => '0',
        ])->assertSessionHasNoErrors();

        $invoice = FinanceInvoice::where('invoice_number', 'ف/2026/0500')->firstOrFail();
        $this->assertSame($this->case->id, $invoice->case_id);
        // موكّلُ القضية موكّلُ فاتورتها حين يُترك الحقل
        $this->assertSame($this->client->id, $invoice->client_id);

        $this->actingAs($this->admin)->get(route('cases.show', $this->case))->assertOk()->assertSee('ف/2026/0500');
    }

    public function test_a_lawyer_cannot_hang_an_invoice_on_someone_elses_case(): void
    {
        $other = LegalCase::factory()->create(['client_id' => $this->client->id, 'lawyer_id' => $this->admin->id]);

        $response = $this->actingAs($this->lawyer)->post(route('finance.invoices.store'), [
            'invoice_number' => 'ف/2026/0501', 'case_id' => (string) $other->id,
            'amount' => '300', 'status' => 'unpaid', 'issue_date' => '2026-10-18',
        ]);
        $this->assertRefused($response);

        $this->assertDatabaseMissing('finance_invoices', ['invoice_number' => 'ف/2026/0501']);
    }

    // ── الطباعة والعرض ─────────────────────────────────────────────

    public function test_the_print_page_is_arabic_with_the_app_fonts_and_contacts(): void
    {
        Setting::set('office_phone', '+968 9000 1000');
        Setting::set('office_email', 'office@example.com');
        Setting::set('office_address', 'مسقط – الخوير');
        $invoice = $this->invoice(['description' => 'أتعاب المرحلة الأولى']);

        $html = $this->actingAs($this->admin)->get(route('finance.invoices.print', $invoice))->assertOk()->getContent();

        $this->assertStringNotContainsString('Print', $html);
        $this->assertStringNotContainsString('Description', $html);
        $this->assertStringNotContainsString('DejaVu', $html);
        $this->assertStringContainsString('fonts.googleapis.com/css2?family=Cairo', $html);
        $this->assertStringContainsString("font-family: 'Tajawal'", $html);
        $this->assertStringContainsString('<bdi dir="ltr">+968 9000 1000</bdi>', $html);
        $this->assertStringContainsString('<bdi dir="ltr">office@example.com</bdi>', $html);
        $this->assertStringContainsString('مسقط – الخوير', $html);
        // بياناتُ الموكّل وقضيّتُه
        $this->assertStringContainsString(__('app.finance_client_details'), $html);
        $this->assertStringContainsString('<bdi dir="ltr">+968 9000 2308</bdi>', $html);
        $this->assertStringContainsString('<bdi dir="ltr">#353</bdi> — تظلّم من قرار استبعاد', $html);
        // زرُّ الطباعة يعمل تحت سياسة المحتوى
        $this->assertMatchesRegularExpression('/<script nonce="[^"]+">\s*document\.querySelectorAll\(\'\[data-window-print\]\'\)/', $html);
    }

    public function test_the_added_at_timestamp_keeps_its_order_in_rtl(): void
    {
        $invoice = $this->invoice();
        $stamp = $invoice->created_at->format('Y-m-d H:i');

        foreach ([route('finance.invoices.show', $invoice), route('finance.invoices.print', $invoice)] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('<bdi dir="ltr">' . $stamp . '</bdi>', $html, $url);
        }
    }

    public function test_the_fee_tab_survives_a_trashed_case(): void
    {
        FinanceFee::create([
            'case_id' => $this->case->id, 'fee_type' => 'رسم قضيةٍ محذوفة', 'amount' => 10, 'status' => 'unpaid',
            'client_visible' => false, 'date' => '2026-10-18', 'user_id' => $this->admin->id,
        ]);
        $this->case->delete();

        $this->actingAs($this->admin)->get(route('finance.index', ['tab' => 'fees']))
            ->assertOk()
            ->assertSee('رسم قضيةٍ محذوفة')
            ->assertSee($this->case->case_number);
    }

    // ── الرسوم البيانية ───────────────────────────────────────────

    public function test_the_finance_page_draws_its_charts(): void
    {
        $this->transaction(['amount' => 1500, 'date' => '2026-10-11']);
        $this->transaction(['amount' => 500, 'date' => '2026-09-02', 'category' => 'استشارات']);
        $this->transaction(['type' => 'expense', 'amount' => 300, 'date' => '2026-10-12', 'category' => 'رسوم']);
        $this->transaction(['amount' => 999, 'date' => '2025-09-30']); // خارج الأشهر الاثني عشر

        $response = $this->actingAs($this->admin)->get(route('finance.index'))->assertOk();
        $trend = $response->viewData('financeTrend');

        $this->assertCount(12, $trend);
        $this->assertSame('نوفمبر 2025', $trend[0]['title']);
        $this->assertSame('أكتوبر', $trend[11]['label']);
        $this->assertEquals(1500, $trend[11]['income']);
        $this->assertEquals(300, $trend[11]['expense']);
        $this->assertEquals(500, $trend[10]['income']);
        $this->assertSame(2499.0, $response->viewData('incomeCategories')['أتعاب محاماة']);

        $response->assertSee('id="financeTrendChart"', false)->assertSee('id="financeCategoryChart"', false);
        $response->assertSee(__('app.finance_trend_title'));
    }

    // ── التقارير ──────────────────────────────────────────────────

    public function test_the_reports_page_has_a_finance_card_and_an_arabic_hint(): void
    {
        $this->transaction();

        $html = $this->actingAs($this->admin)->get(route('reports.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('File Upload', $html);
        $this->assertStringContainsString(route('export.finance'), $html);
        $this->actingAs($this->admin)->get(route('export.finance'))->assertOk()->assertDownload('finance_2026-10-18.xlsx');

        // الماليّةُ لمن يراها وحده
        $this->actingAs($this->lawyer)->get(route('reports.index'))->assertOk()->assertDontSee(route('export.finance'));
        $this->assertRefused($this->actingAs($this->lawyer)->get(route('export.finance')));
    }
}
