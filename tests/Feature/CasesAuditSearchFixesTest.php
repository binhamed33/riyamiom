<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\AuditLog;
use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\FinanceInvoice;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Services\Automation\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * القضيةُ وسجلُّ العمليات والبحثُ يقولون كلماتِهم لا مفاتيحَهم.
 *
 * ═══ ما ظهر ═══
 *
 * - شارةُ الأولوية في تبويب «المهام» تقول high وurgent.
 * - الخطُّ الزمنيّ يقول «الأولوية: high ← medium».
 * - سجلُّ العمليات: «LegalCase» في عمود النموذج و«status: overdue» في التفاصيل.
 * - لوحُ الأوامر: «• active» تحت القضية، و«• paid» تحت فاتورةٍ لا يحقّ
 *   للموظّف رؤيتُها أصلاً، ورابطُها ‎/finance‎ لا الفاتورة.
 * - «أُنشئ جلسة» و«أُنشئ CaseActivity».
 * - صناديقُ TomSelect فارغةٌ بدل «جميع المستخدمين».
 * - تبويبُ الجلسات: عمودُ المكان عنوانُه «النوع».
 * - «2026-06-21 09:30» ينقلب في سياقٍ RTL، و«تحضير جلسة 2026-10-19» يصير «19-10-2026».
 */
class CasesAuditSearchFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        app()->setLocale('ar');
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'name' => 'هلال', 'locale' => 'ar']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function client(string $name = 'نوال بنت حمد'): Client
    {
        return Client::create(['name' => $name, 'type' => 'individual', 'national_id' => (string) random_int(1000000, 9999999), 'phone' => '+968 9000 1220']);
    }

    private function caseFor(Client $client, array $overrides = []): LegalCase
    {
        return LegalCase::create($overrides + [
            'case_number' => '2026/' . random_int(100, 999), 'title' => 'مطالبة مالية', 'description' => 'وصف',
            'type' => 'تجاري', 'court' => 'المحكمة الابتدائية بمسقط', 'opponent' => 'خصم', 'opponent_phone' => '+968 9000 2412',
            'status' => 'active', 'priority' => 'low', 'client_id' => $client->id, 'created_by' => $this->admin->id,
        ]);
    }

    // ───────────────────────────────────────── صفحة القضية

    public function test_the_case_tasks_tab_names_the_priority(): void
    {
        $case = $this->caseFor($this->client());
        Task::factory()->create(['case_id' => $case->id, 'title' => 'إعداد المذكرة', 'priority' => 'urgent', 'status' => 'pending', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);

        $html = $this->actingAs($this->admin)->get(route('cases.show', $case))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/>\s*عاجلة\s*<\/span>/u', $html);
        $this->assertDoesNotMatchRegularExpression('/>\s*urgent\s*<\/span>/u', $html);
    }

    public function test_the_case_timeline_names_a_priority_change(): void
    {
        $case = $this->caseFor($this->client());
        AuditLog::create([
            'user_id' => $this->admin->id, 'action' => 'update', 'model_type' => LegalCase::class, 'model_id' => $case->id,
            'case_id' => $case->id, 'old_values' => ['priority' => 'medium'], 'new_values' => ['priority' => 'high'],
        ]);

        $html = $this->actingAs($this->admin)->get(route('cases.show', $case))->assertOk()->getContent();

        $this->assertStringContainsString('الأولوية: متوسطة ← عالية', $html);
        $this->assertStringNotContainsString('medium ← high', $html);
    }

    public function test_the_case_sessions_tab_labels_the_location_column_and_isolates_the_date(): void
    {
        $case = $this->caseFor($this->client());
        Session::create(['case_id' => $case->id, 'date' => now()->addDays(5), 'status' => 'upcoming', 'location' => 'القاعة 3']);

        $html = $this->actingAs($this->admin)->get(route('cases.show', $case))->assertOk()->getContent();

        $date = preg_quote(__('app.table_date'), '/');
        $this->assertMatchesRegularExpression('/>\s*' . $date . '\s*<\/th>\s*<th[^>]*>\s*الموقع\s*<\/th>/u', $html);
        $this->assertDoesNotMatchRegularExpression('/>\s*' . $date . '\s*<\/th>\s*<th[^>]*>\s*النوع\s*<\/th>/u', $html);
        $this->assertStringContainsString('<bdi dir="ltr" x-text="sessionDateLabel(s.date)"></bdi>', $html);
    }

    public function test_session_pages_isolate_the_date_time(): void
    {
        $case = $this->caseFor($this->client());
        $session = Session::create(['case_id' => $case->id, 'date' => Carbon::parse('2026-06-21 09:30'), 'status' => 'upcoming', 'location' => 'القاعة 3']);

        $this->actingAs($this->admin)->get(route('sessions.index', ['date_from' => '2026-06-01']))
            ->assertOk()->assertSee('<bdi dir="ltr">2026-06-21 09:30</bdi>', false);
        $this->actingAs($this->admin)->get(route('sessions.show', $session))
            ->assertOk()->assertSee('<bdi dir="ltr">2026-06-21 09:30</bdi>', false);
    }

    // ───────────────────────────────────────── سجلّ العمليات

    public function test_the_audit_log_names_the_model_and_its_fields(): void
    {
        $case = $this->caseFor($this->client());
        AuditLog::create([
            'user_id' => $this->admin->id, 'action' => 'update', 'model_type' => LegalCase::class, 'model_id' => $case->id,
            'case_id' => $case->id, 'new_values' => ['title' => 'مطالبة مالية', 'case_id' => $case->id, 'status' => 'overdue', 'priority' => 'urgent'],
        ]);

        $html = $this->actingAs($this->admin)->get(route('audit-log.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<td[^>]*>\s*قضية\s*<\/td>/u', $html);
        $this->assertStringNotContainsString('>LegalCase<', $html);
        $this->assertStringContainsString('العنوان:</span> <bdi>مطالبة مالية</bdi>', $html);
        $this->assertStringContainsString('الحالة:</span> <bdi>متأخرة</bdi>', $html);
        $this->assertStringContainsString('الأولوية:</span> <bdi>عاجلة</bdi>', $html);
        $this->assertStringNotContainsString('case_id:', $html);
        $this->assertStringNotContainsString('overdue', $html);
    }

    public function test_tom_select_keeps_the_empty_option_as_its_placeholder(): void
    {
        $html = $this->actingAs($this->admin)->get(route('audit-log.index'))->assertOk()->getContent();

        // نصٌّ فارغٌ صريحٌ كان يغلب نصَّ <option value=""> فيبقى الصندوقُ فارغاً
        $this->assertStringNotContainsString("placeholder: el.getAttribute('placeholder') || ''", $html);
        $this->assertStringContainsString('<option value="">' . __('app.all_users') . '</option>', $html);
    }

    public function test_audit_sentences_agree_in_gender_with_their_noun(): void
    {
        $case = $this->caseFor($this->client());
        $session = Session::create(['case_id' => $case->id, 'date' => now()->addDay(), 'status' => 'upcoming', 'location' => 'القاعة 3']);
        $make = fn (string $action, string $type, array $values) => AuditLog::create(['user_id' => $this->admin->id, 'action' => $action, 'model_type' => $type, 'model_id' => 1, 'new_values' => $values, 'old_values' => $values]);

        $this->assertSame('أُنشئت جلسة', AuditLog::create(['action' => 'create', 'model_type' => Session::class, 'model_id' => $session->id])->describe());
        $this->assertSame('أُنشئت مهمّة «إعداد المذكرة»', $make('create', Task::class, ['title' => 'إعداد المذكرة'])->describe());
        $this->assertSame('حُذفت قضية «مطالبة مالية»', $make('delete', LegalCase::class, ['title' => 'مطالبة مالية'])->describe());
        $this->assertSame('عُدِّلت فاتورة «INV-1»', $make('update', FinanceInvoice::class, ['case_number' => 'INV-1'])->describe());
        $this->assertSame('أُنشئ موكّل «سعيد»', $make('create', Client::class, ['name' => 'سعيد'])->describe());
        $this->assertSame('أُنشئ نشاط «مكالمة الموكّل»', $make('create', CaseActivity::class, ['title' => 'مكالمة الموكّل'])->describe());
    }

    // ───────────────────────────────────────── لوح الأوامر

    public function test_the_command_palette_names_case_status_and_priority(): void
    {
        $this->caseFor($this->client(), ['title' => 'مطالبة إيجار', 'status' => 'active', 'priority' => 'high']);

        $search = $this->actingAs($this->admin)->getJson('/command?q=' . urlencode('إيجار'))->assertOk()->json('groups.case.0.sub');
        $this->assertSame('المحكمة الابتدائية بمسقط • نشطة', $search);

        $recent = $this->actingAs($this->admin)->getJson('/command')->assertOk()->json('groups.recent-case.0.sub');
        $this->assertSame('نوال بنت حمد • عالية', $recent);
    }

    public function test_command_palette_invoices_follow_finance_visibility_and_link_to_the_invoice(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true, 'locale' => 'ar']);
        $client = $this->client();
        $invoice = fn (string $number, string $description, User $owner, string $status) => FinanceInvoice::create([
            'invoice_number' => $number, 'client_id' => $client->id, 'amount' => 945, 'paid_amount' => 945,
            'status' => $status, 'issue_date' => now()->toDateString(), 'due_date' => now()->addMonth()->toDateString(),
            'description' => $description, 'user_id' => $owner->id,
        ]);
        $own = $invoice('INV-ZZ-1', 'أتعاب', $staff, 'paid');
        // لغيره: واحدةٌ تطابق برقمها وأخرى بوصفها — «orWhere» بلا قوسٍ كان يفتح الثانية
        $invoice('INV-ZZ-2', 'أتعاب', $this->admin, 'partial');
        $invoice('INV-XX-3', 'سداد ZZ', $this->admin, 'unpaid');

        $staffSees = collect($this->actingAs($staff)->getJson('/command?q=ZZ')->assertOk()->json('groups.invoice'));
        $this->assertCount(1, $staffSees);
        $this->assertSame(route('finance.invoices.show', $own), $staffSees[0]['url']);
        $this->assertSame('945.00 • مدفوعة', $staffSees[0]['sub']);

        $adminSees = collect($this->actingAs($this->admin)->getJson('/command?q=ZZ')->assertOk()->json('groups.invoice'));
        $this->assertCount(3, $adminSees);
        $this->assertNotContains('945.00 • partial', $adminSees->pluck('sub'));
    }

    // ───────────────────────────────────────── الأتمتة

    public function test_automation_dates_stay_whole_inside_arabic_text(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 09:00'));
        Setting::set('automation_enabled', '1');
        Automation::create([
            'name' => 'تحضير الجلسات', 'trigger' => 'session_approaching',
            'conditions' => [['field' => 'days_until_session', 'operator' => 'lte', 'value' => 3]],
            'actions' => [['type' => 'create_task', 'title' => 'تحضير جلسة {date} — {case}', 'priority' => 'high', 'assign' => 'case_lawyer', 'due_in_days' => 1]],
            'is_active' => true,
        ]);
        $case = LegalCase::factory()->create();
        Session::factory()->create(['case_id' => $case->id, 'date' => Carbon::parse('2026-10-19 09:30'), 'status' => 'upcoming']);

        (new AutomationEngine())->runScheduled();

        $title = Task::where('case_id', $case->id)->value('title');
        $this->assertStringStartsWith('تحضير جلسة 2026/10/19 — ', $title);
        $this->assertStringNotContainsString('2026-10-19', $title);
    }
}
