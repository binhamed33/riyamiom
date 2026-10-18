<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Client;
use App\Models\FinanceInvoice;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Services\Automation\AutomationEngine;
use App\Support\ArabicCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * مركزُ الانتباه ولوحةُ التحكّم والمهام: ما يُعدّ متأخّراً، وبكم يوم، ولمن.
 *
 * ═══ ما وقع ═══
 *
 * - «متأخرة -6 يوم»: ‏diffInDays(due, false) سالبٌ في Carbon 3، فلا يصير
 *   شيءٌ «عاجلاً»، وإشارةُ الفواتير مقلوبةٌ معه.
 * - مهمّةُ اليوم متأخّرةٌ منذ منتصف الليل: due_date «Y-m-d 00:00:00» يُقارَن بـnow().
 * - المحامي يقرأ في «النشاط الأخير» دخولَ المدير وأفعالَ زملائه.
 * - «أُضيف قبل منذ يوم»، و«التقويم / مركز الانتباه»، و«نفّذت 4 إجراءً».
 */
class AttentionDashboardFixesTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-10-17 15:00', 'Asia/Muscat'));

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'name' => 'المدير', 'locale' => 'ar']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function task(string $title, ?string $due, array $extra = []): Task
    {
        return Task::create(array_merge([
            'title' => $title,
            'assigned_to' => $this->admin->id,
            'created_by' => $this->admin->id,
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => $due,
        ], $extra));
    }

    private function caseFor(User $lawyer, string $number): LegalCase
    {
        $client = Client::create(['name' => 'موكّل ' . $number, 'type' => 'individual', 'national_id' => (string) random_int(1000000, 9999999), 'phone' => '96891234567']);

        return LegalCase::create([
            'case_number' => $number, 'title' => 'قضية ' . $number, 'description' => 'وصف', 'type' => 'مدني',
            'court' => 'المحكمة', 'opponent' => 'خصم', 'status' => 'active', 'priority' => 'medium',
            'client_id' => $client->id, 'lawyer_id' => $lawyer->id, 'created_by' => $this->admin->id, 'opened_at' => now(),
        ]);
    }

    private function invoice(string $number, ?string $due, string $status = 'unpaid'): FinanceInvoice
    {
        $client = Client::firstOrCreate(['name' => 'موكّل الفواتير'], ['type' => 'individual', 'national_id' => '1234567', 'phone' => '96890000000']);

        return FinanceInvoice::create([
            'invoice_number' => $number, 'client_id' => $client->id, 'amount' => 100, 'paid_amount' => 0,
            'status' => $status, 'issue_date' => now()->subMonth()->toDateString(), 'due_date' => $due,
            'user_id' => $this->admin->id,
        ]);
    }

    private function attentionItems(?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->get(route('attention.index'))->assertOk()->viewData('items');
    }

    // ───────────────────────────────────────── أيّامُ التأخّر

    public function test_overdue_days_are_positive_and_three_days_late_is_critical(): void
    {
        $this->task('متأخرة ستة', now()->subDays(6)->toDateString());
        $this->task('متأخرة يوماً', now()->subDay()->toDateString());

        $items = $this->attentionItems();
        $six = $items->firstWhere('title', 'مهمة متأخرة — متأخرة ستة');
        $one = $items->firstWhere('title', 'مهمة متأخرة — متأخرة يوماً');

        $this->assertSame('متأخرة 6 أيام', $six['description'], 'كانت «متأخرة -6 يوم»');
        $this->assertSame('critical', $six['severity'], 'ستّةُ أيّامٍ تأخيراً عاجلةٌ لا تنبيه');
        $this->assertSame('متأخرة يوماً واحداً', $one['description']);
        $this->assertSame('warning', $one['severity']);

        // وشارةُ العدد في اللوحة: كانت «2 تنبيه»
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('font-bold">تنبيهان</span>', $html);
    }

    public function test_arabic_count_has_the_accusative_forms_for_durations(): void
    {
        $this->assertSame('يوماً واحداً', ArabicCount::daysAccusative(1));
        $this->assertSame('يومين', ArabicCount::daysAccusative(2));
        $this->assertSame('5 أيام', ArabicCount::daysAccusative(5));
        $this->assertSame('150 يوماً', ArabicCount::daysAccusative(150));
    }

    // ───────────────────────────────────────── مهمّةُ اليوم ليست متأخّرة

    public function test_a_task_due_today_is_not_overdue_on_any_screen(): void
    {
        // بعد منتصف الليل بثانية: كانت مهمّةُ اليوم «متأخرة 0 يوم» من هنا
        Carbon::setTestNow(Carbon::parse('2026-10-17 00:00:01', 'Asia/Muscat'));

        $today = $this->task('مهمة اليوم', now()->toDateString());
        $late = $this->task('مهمة الأمس', now()->subDay()->toDateString());

        $this->assertFalse($today->fresh()->isOverdue());
        $this->assertTrue($late->fresh()->isOverdue());
        $this->assertSame(1, $late->fresh()->daysOverdue());

        $this->assertNull($this->attentionItems()->firstWhere('title', 'مهمة متأخرة — مهمة اليوم'));

        $dash = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
        $this->assertSame(1, $dash->viewData('overdueTasks'), 'KPI «المتأخر» يعدّ مهمّةَ الأمس وحدَها');
        $this->assertSame(['مهمة الأمس'], $dash->viewData('overdueTasksList')->pluck('title')->all());
        $briefTitles = $dash->viewData('brief')->pluck('title');
        $this->assertContains('مهمة مستحقة اليوم — مهمة اليوم', $briefTitles);
        $this->assertNotContains('مهمة متأخرة — مهمة اليوم', $briefTitles);

        $badge = '<span class="text-red-700 text-xs font-semibold mr-2">';
        $this->assertStringNotContainsString($badge, $this->get(route('tasks.show', $today))->assertOk()->getContent());
        $this->assertStringContainsString($badge, $this->get(route('tasks.show', $late))->assertOk()->getContent());

        $this->assertSame(['مهمة الأمس'], $this->get(route('tasks.index', ['due' => 'overdue']))->viewData('tasks')->pluck('title')->all());
    }

    // ───────────────────────────────────────── الفواتير

    public function test_invoice_alerts_flag_the_late_one_not_the_upcoming_one_and_skip_cancelled(): void
    {
        $this->invoice('ف/0162', now()->addDays(2)->toDateString());
        $this->invoice('ف/0140', now()->subDays(5)->toDateString(), 'partial');
        $this->invoice('ف/0099', now()->subDays(10)->toDateString(), 'cancelled');

        $items = $this->attentionItems();
        $soon = $items->firstWhere('title', 'فاتورة غير مدفوعة — ف/0162');
        $late = $items->firstWhere('title', 'فاتورة غير مدفوعة — ف/0140');

        $this->assertSame('info', $soon['severity'], 'تستحقّ بعد يومين: معلومةٌ لا تنبيه');
        $this->assertStringNotContainsString('متأخرة', $soon['description']);
        $this->assertSame('warning', $late['severity']);
        $this->assertStringContainsString('(متأخرة 5 أيام)', $late['description']);
        $this->assertNull($items->firstWhere('title', 'فاتورة غير مدفوعة — ف/0099'), 'الملغاةُ ليست غيرَ مدفوعة');
    }

    public function test_the_brief_skips_cancelled_invoices_keeps_today_due_and_puts_undated_last(): void
    {
        $this->invoice('ف/بلا', null);
        $this->invoice('ف/قريبة', now()->addDays(2)->toDateString());
        $this->invoice('ف/اليوم', now()->toDateString());
        $this->invoice('ف/متأخرة', now()->subDays(5)->toDateString());
        $this->invoice('ف/ملغاة', now()->subDays(3)->toDateString(), 'cancelled');

        $titles = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->viewData('brief')->where('icon', 'invoice')->pluck('title')->values()->all();

        $this->assertSame([
            'فاتورة متأخرة — ف/متأخرة',
            'فاتورة مستحقة — ف/اليوم',
            'فاتورة مستحقة — ف/قريبة',
            'فاتورة مستحقة — ف/بلا',
        ], $titles);
    }

    // ───────────────────────────────────────── الموكّل الجديد

    public function test_new_client_alert_does_not_double_the_preposition(): void
    {
        $client = Client::create(['name' => 'نوال', 'type' => 'individual', 'national_id' => '7654321', 'phone' => '96891111111']);
        $client->forceFill(['created_at' => now()->subDay()])->saveQuietly();

        $item = $this->attentionItems()->firstWhere('title', 'موكل جديد بدون قضية — نوال');

        $this->assertStringStartsWith('أُضيف منذ', $item['description']);
        $this->assertStringNotContainsString('قبل منذ', $item['description']);
    }

    // ───────────────────────────────────────── خصوصيّةُ النشاط الأخير

    public function test_a_lawyer_sees_only_her_own_audit_rows_and_rows_about_her_cases(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 10:00', 'Asia/Muscat'));
        $maryam = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'name' => 'مريم', 'locale' => 'ar']);
        $other = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'name' => 'بدر']);
        $hers = $this->caseFor($maryam, 'M-1');
        $his = $this->caseFor($other, 'B-1');

        Carbon::setTestNow(Carbon::parse('2026-10-17 11:00', 'Asia/Muscat'));
        $log = fn (User $by, string $action, ?LegalCase $case, string $title) => AuditLog::create([
            'user_id' => $by->id, 'action' => $action, 'model_type' => LegalCase::class, 'model_id' => $case?->id,
            'case_id' => $case?->id, 'new_values' => ['title' => $title],
        ]);
        AuditLog::create(['user_id' => $this->admin->id, 'action' => 'login', 'model_type' => User::class, 'model_id' => $this->admin->id]);
        $log($other, 'update', $his, 'سطر بدر');
        $log($maryam, 'update', null, 'سطر مريم');
        $log($this->admin, 'update', $hers, 'سطر قضية مريم');

        $logs = $this->actingAs($maryam)->get(route('dashboard'))->assertOk()
            ->viewData('recentActivity')->where('type', 'log');

        $titles = $logs->pluck('title')->implode(' | ');
        $this->assertStringContainsString('«سطر مريم»', $titles);
        $this->assertStringContainsString('«سطر قضية مريم»', $titles);
        $this->assertStringNotContainsString('«سطر بدر»', $titles, 'ما فعله زميلٌ في قضيّته ليس لها');
        $this->assertStringNotContainsString(__('app.audit_login'), $titles, 'دخولُ المدير ليس لها');
        $this->assertCount(2, $logs);

        // ومن يرى سجلَّ التدقيق يرى السطورَ كلَّها
        $adminLogs = $this->actingAs($this->admin)->get(route('dashboard'))->viewData('recentActivity')->where('type', 'log');
        $this->assertCount(4, $adminLogs);
    }

    // ───────────────────────────────────────── العناوين والجمل

    public function test_attention_breadcrumb_names_the_dashboard(): void
    {
        $html = $this->actingAs($this->admin)->get(route('attention.index'))->assertOk()->getContent();

        $link = '<a href="' . route('dashboard') . '" class="text-gray-400 hover:text-gold-dark transition">';
        $this->assertStringContainsString($link . 'لوحة التحكم</a>', $html);
        $this->assertStringNotContainsString($link . 'التقويم</a>', $html);
    }

    public function test_automation_strip_agrees_the_number_with_its_noun(): void
    {
        for ($i = 0; $i < 4; $i++) {
            AutomationRun::create(['trigger' => 'case_stale', 'status' => 'success']);
        }

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('نفّذت <b>4 إجراءات</b> تلقائياً اليوم', $html);
        $this->assertStringNotContainsString('إجراءً تلقائياً', $html);
    }

    public function test_automation_dry_run_toast_agrees_the_number_with_its_noun(): void
    {
        $rule = Automation::create(['name' => 'تذكير', 'trigger' => 'case_stale', 'conditions' => [], 'actions' => [['type' => 'notify']], 'is_active' => true, 'created_by' => $this->admin->id]);
        $this->mock(AutomationEngine::class, fn ($m) => $m->shouldReceive('dryRun')->andReturn(5));

        $this->actingAs($this->admin)->from(route('automations.index'))->post(route('automations.test', $rule))
            ->assertSessionHas('success', 'اختبار «تذكير»: يطابق القاعدةَ الآن 5 عناصر (لم يُنفَّذ شيء).');
    }

    public function test_upcoming_session_row_shows_the_time_instead_of_a_dangling_dash(): void
    {
        $case = $this->caseFor($this->admin, 'S-1');
        Session::create(['case_id' => $case->id, 'date' => now()->addDay()->setTime(10, 0), 'location' => 'القاعة 4', 'status' => 'upcoming']);

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        // ‏bdi بلا dir: تحت dir="ltr" كانت «10:00 ص» تُقرأ «ص 10:00»
        $this->assertStringContainsString('القاعة 4 — <bdi>10:00 ص</bdi>', $html);
        $this->assertDoesNotMatchRegularExpression('/القاعة 4 —\s*<\/p>/u', $html);
    }

    public function test_pending_tasks_list_puts_undated_tasks_last(): void
    {
        $this->task('بلا موعد', null);
        $this->task('بعد ثلاثة أيام', now()->addDays(3)->toDateString());

        $list = $this->actingAs($this->admin)->get(route('dashboard'))->viewData('pendingTasksList');

        $this->assertSame(['بعد ثلاثة أيام', 'بلا موعد'], $list->pluck('title')->all());
    }

    // ───────────────────────────────────────── مهامي

    public function test_my_tasks_names_itself_and_counts_its_own_completed_tasks(): void
    {
        $maryam = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'locale' => 'ar']);
        $this->task('مفتوحة لمريم', now()->addDay()->toDateString(), ['assigned_to' => $maryam->id]);
        $this->task('منجزة لمريم', null, ['assigned_to' => $maryam->id, 'status' => 'completed']);
        $this->task('منجزة للمدير 1', null, ['status' => 'completed']);
        $this->task('منجزة للمدير 2', null, ['status' => 'completed']);

        $res = $this->actingAs($maryam)->get(route('tasks.my'))->assertOk();

        $this->assertSame(['مفتوحة لمريم'], $res->viewData('tasks')->pluck('title')->all(), 'المنجزةُ خلف زرّها');
        $this->assertSame(1, $res->viewData('doneCount'), 'كان الزرُّ «المنجزة (0)» أبداً');
        $html = $res->getContent();
        $this->assertStringContainsString('<h2 class="text-2xl font-bold text-gray-900">مهامي</h2>', $html);
        $this->assertStringContainsString('href="' . route('tasks.my', ['due' => 'overdue']) . '"', $html, 'التبويباتُ تبقى في «مهامي»');

        $done = $this->get(route('tasks.my', ['done' => 1]))->assertOk();
        $this->assertSame(['منجزة لمريم'], $done->viewData('tasks')->pluck('title')->all());
    }

    // ───────────────────────────────────────── جدولُ المهام والجرس والرسم

    public function test_desktop_tasks_table_marks_an_overdue_due_date(): void
    {
        $this->task('متأخرة', now()->subDay()->toDateString());
        $this->task('غداً', now()->addDay()->toDateString());

        $html = $this->actingAs($this->admin)->get(route('tasks.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<td class="px-6 py-4 text-red-700 font-semibold">\s*2026-10-16\s*<span[^>]*>متأخرة عن موعدها<\/span>/u', $html);
        $this->assertMatchesRegularExpression('/<td class="px-6 py-4 text-gray-600">\s*2026-10-18\s*<\/td>/u', $html);
    }

    public function test_the_bell_dropdown_is_opaque_and_chart_digits_follow_the_page(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\.dropdown-dark \{\s*background: #FFFFFF;/', $html);
        $this->assertStringNotContainsString('rgba(255, 255, 255, 0.98)', $html);
        $this->assertStringContainsString("window.Chart.defaults.locale = 'en-US';", $html);
        $this->assertStringContainsString('labels: Object.keys(lawyerData).map(wrapName)', $html);
    }
}
