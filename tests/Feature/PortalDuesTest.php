<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FinanceInvoice;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClientPortal\PortalLinks;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ما على الموكّل — في قضاياه كلِّها لا في واحدة.
 *
 * ═══ ما تحرسه ═══
 *
 * ١) سؤالُ الموكّل الأوّل «كم عليّ؟» له جوابٌ في الصفحة الأولى، لا
 *    مجموعٌ يجمعه بنفسه من ثلاث قضايا.
 *
 * ٢) وما لم يعلّمه المكتبُ «مرئياً للموكّل» لا يدخل الحساب أصلاً —
 *    لا في المجموع ولا في التفصيل. فرسمٌ داخليٌّ قيد المراجعة لا يظهر
 *    رقماً في شاشة الموكّل قبل أن يقرّره المكتب.
 *
 * ٣) ولا يرى مالَ غيره: المجموعُ محصورٌ بقضاياه هو.
 *
 * ٤) و‏#billing وجهةُ رابط إشعار الفاتورة — لا بدّ أن تكون موجودة،
 *    وإلا فتح الموكّلُ رابطاً يقوده إلى لا شيء.
 */
class PortalDuesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
        Setting::set(ClientPortal::KEY_SHOW_ACCOUNTING, '1', 'client_portal');

        $this->client = Client::create([
            'name' => 'أحمد', 'phone' => '91234567', 'national_id' => '12345678', 'type' => 'individual',
        ]);
    }

    private function caseFor(Client $client, string $number): LegalCase
    {
        return LegalCase::create([
            'case_number' => $number, 'title' => 'قضية ' . $number, 'type' => 'civil',
            'description' => 'وصف', 'court' => 'الابتدائية', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium', 'client_id' => $client->id,
        ]);
    }

    private function invoice(LegalCase $case, float $amount, float $paid, bool $visible = true): FinanceInvoice
    {
        return FinanceInvoice::create([
            'invoice_number' => 'INV-' . $case->id . '-' . (int) $amount,
            'client_id' => $case->client_id,
            'case_id' => $case->id,
            'amount' => $amount,
            'paid_amount' => $paid,
            'status' => $paid >= $amount ? 'paid' : 'unpaid',
            'client_visible' => $visible,
            'issue_date' => now()->toDateString(),
            'user_id' => $this->admin->id,
        ]);
    }

    private function enter(Client $client): void
    {
        $link = PortalLinks::for($client, 'home');
        preg_match('#/p/([A-Za-z0-9]+)#', $link, $m);
        $this->get('/p/' . $m[1]);
    }

    // ══════════ المجموع ══════════

    /** المتبقّي عبر قضيّتين يُجمع ويُعرض في الصفحة الأولى. */
    public function test_the_client_sees_what_is_owed_across_all_his_cases(): void
    {
        $a = $this->caseFor($this->client, '2026/1');
        $b = $this->caseFor($this->client, '2026/2');

        $this->invoice($a, 300, 100);   // متبقٍّ ٢٠٠
        $this->invoice($b, 500, 0);     // متبقٍّ ٥٠٠

        $this->enter($this->client);

        $this->get(route('client.portal.home'))
            ->assertOk()
            ->assertSee('المستحقّات المالية')
            ->assertSee('المتبقّي عليكم')
            ->assertSee('700.00')   // المتبقّي
            ->assertSee('800.00')   // الإجمالي
            ->assertSee('100.00');  // المسدَّد
    }

    /** وما لم يُعلَّم «مرئياً للموكّل» لا يدخل الحساب. */
    public function test_an_internal_invoice_never_enters_the_total(): void
    {
        $case = $this->caseFor($this->client, '2026/3');

        $this->invoice($case, 250, 0, visible: true);
        $this->invoice($case, 9000, 0, visible: false);

        $this->enter($this->client);

        $this->get(route('client.portal.home'))
            ->assertOk()
            ->assertSee('250.00')
            ->assertDontSee('9,000.00')
            ->assertDontSee('9250.00');
    }

    /** ولا يرى مالَ موكّلٍ آخر. */
    public function test_a_client_never_sees_another_clients_dues(): void
    {
        $other = Client::create([
            'name' => 'غيره', 'phone' => '99887766', 'national_id' => '87654321', 'type' => 'individual',
        ]);

        $mine = $this->caseFor($this->client, '2026/4');
        $theirs = $this->caseFor($other, '2026/5');

        $this->invoice($mine, 100, 0);
        $this->invoice($theirs, 7777, 0);

        $this->enter($this->client);

        $this->get(route('client.portal.home'))
            ->assertOk()
            ->assertSee('100.00')
            ->assertDontSee('7,777.00')
            ->assertDontSee('2026/5');
    }

    /** وبلا مستحقّاتٍ لا يُعرض القسمُ أصلاً — لا صفرٌ يقلق. */
    public function test_a_client_with_nothing_owed_sees_no_dues_section(): void
    {
        $this->caseFor($this->client, '2026/6');
        $this->enter($this->client);

        $this->get(route('client.portal.home'))
            ->assertOk()
            ->assertDontSee('المستحقّات المالية');
    }

    /** والمكتبُ إن أطفأ المحاسبة لم يظهر شيء. */
    public function test_the_office_can_switch_the_money_off_entirely(): void
    {
        Setting::set(ClientPortal::KEY_SHOW_ACCOUNTING, '0', 'client_portal');

        $case = $this->caseFor($this->client, '2026/7');
        $this->invoice($case, 400, 0);

        $this->enter($this->client);

        $this->get(route('client.portal.home'))
            ->assertOk()
            ->assertDontSee('المستحقّات المالية')
            ->assertDontSee('400.00');
    }

    // ══════════ رابط إشعار الفاتورة ══════════

    /** وجهةُ `billing` تقود إلى قسمٍ موجودٍ فعلاً. */
    public function test_the_invoice_notification_link_lands_on_a_section_that_exists(): void
    {
        $case = $this->caseFor($this->client, '2026/8');
        $this->invoice($case, 600, 0);

        $link = PortalLinks::for($this->client, 'billing', $case->id);
        preg_match('#/p/([A-Za-z0-9]+)#', $link, $m);

        $this->get('/p/' . $m[1])
            ->assertRedirect(route('client.portal.home') . '#billing');

        $this->get(route('client.portal.home'))
            ->assertOk()
            ->assertSee('id="billing"', false);
    }
}
