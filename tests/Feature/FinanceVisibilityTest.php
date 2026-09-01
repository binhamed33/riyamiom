<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\FinanceFee;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Support\ClientEvents;
use App\Support\ClientPortal;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * «أضفتُ فاتورةً وما وصل الموكّلَ إشعار» — والفاتورةُ وُلدت خفيّة.
 *
 * ═══ ما وقع ═══
 *
 * ‏client_visible افتراضُها صفرٌ في القاعدة والمتحكّم معاً، ونموذجُ
 * الفاتورة في القسم المالي **بلا خانةٍ أصلاً**. فكلُّ فاتورةٍ تُنشأ
 * من هناك خفيّةٌ حتماً: لا تظهر في بوابة الموكّل، ولا يُقيَّد لها
 * إشعارٌ، ولا يُخطئ شيءٌ في أيّ سجلّ. والمكتبُ يفتّش في الربط
 * والطابور — والعلّةُ خانةٌ غائبة.
 *
 * ═══ القرار ═══
 *
 * الخانةُ في النماذج مؤشَّرةٌ افتراضاً: الفاتورةُ تُكتب ليُطالَب بها،
 * وإخفاؤها استثناءٌ يُقصد. وافتراضُ **الكود** يبقى صفراً: استيرادُ
 * بياناتٍ قديمةٍ برمجيّاً يجب ألّا يمطر الموكّلين برسائل فواتيرَ
 * مضت — من لا يمرّ بالنموذج لا يُراسِل أحداً إلا بقصدٍ صريح.
 */
class FinanceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;
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
            'name' => 'موكّلُ الفواتير',
            'phone' => '91234567',
            'national_id' => '99887766',
            'type' => 'individual',
        ]);

        $this->case = LegalCase::create([
            'case_number' => '2026/950', 'title' => 'قضية', 'type' => 'civil',
            'description' => 'وصف', 'court' => 'الابتدائية', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium', 'client_id' => $this->client->id,
        ]);
    }

    /** الخانةُ مؤشَّرةٌ افتراضاً في نموذجَي القسم المالي. */
    public function test_the_finance_forms_show_the_visibility_box_ticked(): void
    {
        $html = $this->actingAs($this->admin)->get(route('finance.index'))->assertOk()->getContent();

        preg_match_all('/<input[^>]*name="client_visible"[^>]*value="1"[^>]*>/', $html, $m);

        $this->assertNotEmpty($m[0], 'لا خانةَ إظهارٍ في نماذج القسم المالي');

        // الإنشاءُ مؤشَّرٌ افتراضاً — والتعديلُ مربوطٌ بقيمة فاتورته
        $checkedByDefault = array_filter($m[0], fn ($tag) => str_contains($tag, 'checked'));
        $this->assertNotEmpty($checkedByDefault, 'خانةُ الإنشاء غيرُ مؤشَّرةٍ — فتُولد كلُّ فاتورةٍ خفيّة');
    }

    /** وخانةُ رسم صفحة القضية مؤشَّرةٌ هي أيضاً. */
    public function test_the_case_page_fee_box_is_ticked(): void
    {
        $html = $this->actingAs($this->admin)->get(route('cases.show', $this->case))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="client_visible"[^>]*value="1"[^>]*checked[^>]*>|<input[^>]*checked[^>]*name="client_visible"[^>]*>/',
            $html,
        );
    }

    /** ═══ الرحلةُ التي اشتُكي منها: فاتورةٌ من الشاشة ⇐ إشعار ═══ */
    public function test_an_invoice_created_from_the_form_notifies_the_client(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)->post(route('finance.invoices.store'), [
            'invoice_number' => 'INV-2026-01',
            'client_id' => $this->client->id,
            'case_id' => $this->case->id,
            'client_visible' => '1',
            'amount' => 150,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ])->assertRedirect();

        $notification = ClientNotification::where('type', ClientEvents::INVOICE_NEW)->first();

        $this->assertNotNull($notification, 'أُنشئت فاتورةٌ مرئيّةٌ ولم يُقيَّد إشعار');
        $this->assertSame($this->client->id, $notification->client_id);
        $this->assertStringNotContainsString('150', (string) $notification->title, 'المبلغُ في نصّ الإشعار');
    }

    /** ومن أطفأ الخانةَ قصداً يُحترم قصدُه: لا بوابةَ ولا إشعار. */
    public function test_an_explicitly_hidden_invoice_stays_silent(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)->post(route('finance.invoices.store'), [
            'invoice_number' => 'INV-2026-02',
            'client_id' => $this->client->id,
            'case_id' => $this->case->id,
            'client_visible' => '0',
            'amount' => 90,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertSame(0, ClientNotification::where('type', ClientEvents::INVOICE_NEW)->count());
    }

    /** وتعديلُ رسمٍ من نموذجٍ بلا خانةٍ لا يخفيه — عطلُ المسح لا يعود. */
    public function test_editing_a_fee_without_the_box_does_not_hide_it(): void
    {
        $fee = FinanceFee::create([
            'case_id' => $this->case->id,
            'fee_type' => 'أتعاب',
            'amount' => 100,
            'status' => 'unpaid',
            'client_visible' => true,
            'date' => now()->toDateString(),
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->put(route('finance.fees.update', $fee), [
            'case_id' => $this->case->id,
            'fee_type' => 'أتعاب معدّلة',
            'amount' => 120,
            'status' => 'unpaid',
            'date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertTrue((bool) $fee->refresh()->client_visible,
            'تعديلٌ بلا خانةٍ أخفى الرسمَ عن الموكّل');
    }
}
