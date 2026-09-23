<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * أرقامُ اللوحة تقول الحقيقة، ولا يناقض أحدُها الآخر.
 *
 * ═══ عطلان كانا فيها ═══
 *
 * ١) رقما المستندات متبادلان: بطاقةُ «العملاء» تعرض جديدَ الشهر
 *    وبطاقةُ «جديد هذا الشهر» تعرض الإجمالي. فمن قرأ «٧١ مستند» تحت
 *    العملاء ظنّه رصيدَ المكتب، ومن قرأ «٧٣» تحت جديدِ الشهر ظنّ أنّ
 *    ثلاثةً وسبعين رُفعت هذا الشهر.
 *
 * ٢) أعدادُ القضايا محفوظةٌ خمسَ دقائق وجاراتُها حيّة: من أضاف قضيةً
 *    رأى «جديد هذا الشهر ١٩» و«الإجمالي ١٨» — وجديدُ الشهر لا يكون
 *    أكبرَ من الإجمالي أبداً.
 */
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function makeCase(string $number = 'م/2026/1', string $status = 'active'): LegalCase
    {
        $client = Client::create([
            'name' => 'موكّل ' . $number,
            'type' => 'individual',
            'national_id' => (string) random_int(1000000, 9999999),
            'phone' => '96891234567',
        ]);

        return LegalCase::create([
            'case_number' => $number,
            'title' => 'قضية ' . $number,
            'description' => 'وصف',
            'type' => 'مدني',
            'court' => 'المحكمة',
            'opponent' => 'خصم',
            'status' => $status,
            'priority' => 'medium',
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
            'opened_at' => now(),
        ]);
    }

    private function stats(): array
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

        return $response->original->getData();
    }

    /** الإجماليُّ والجديدُ يُقرآن من اللحظة نفسِها. */
    public function test_the_totals_never_lag_behind_the_monthly_counts(): void
    {
        $this->makeCase('م/2026/1');
        $first = $this->stats();

        $this->assertSame(1, $first['totalCases']);
        $this->assertSame(1, $first['newCasesThisMonth']);

        // قضيةٌ ثانيةٌ تُضاف فوراً — لا انتظارَ خمسِ دقائق
        $this->makeCase('م/2026/2');
        $second = $this->stats();

        $this->assertSame(2, $second['totalCases'], 'الإجماليُّ تخلّف عن الواقع — كاشٌ يناقض جارَه');
        $this->assertSame(2, $second['newCasesThisMonth']);

        // القاعدةُ التي لا تُخرَق: جديدُ الشهر جزءٌ من الإجمالي
        $this->assertLessThanOrEqual($second['totalCases'], $second['newCasesThisMonth']);
    }

    /** ونسبةُ الفوز تُحسب من المحسوم وحده، لا من كلّ القضايا. */
    public function test_the_win_rate_counts_decided_cases_only(): void
    {
        $this->makeCase('م/1', 'won');
        $this->makeCase('م/2', 'won');
        $this->makeCase('م/3', 'lost');
        $this->makeCase('م/4', 'active');

        $stats = $this->stats();

        $this->assertSame(2, $stats['wonCases']);
        $this->assertSame(1, $stats['lostCases']);
        $this->assertSame(66.7, $stats['winRate'], 'حُسبت النسبةُ من كلّ القضايا لا من المحسوم');
    }

    /** ونسبةُ إنجاز المهام من كلّ المهام. */
    public function test_the_task_rate_is_completed_over_total(): void
    {
        $case = $this->makeCase();

        foreach (['completed', 'completed', 'pending', 'in_progress'] as $i => $status) {
            Task::create([
                'title' => 'مهمة ' . $i,
                'case_id' => $case->id,
                'assigned_to' => $this->admin->id,
                'created_by' => $this->admin->id,
                'status' => $status,
                'priority' => 'medium',
                'due_date' => now()->addDays(5),
                'completed_at' => $status === 'completed' ? now() : null,
            ]);
        }

        $stats = $this->stats();

        $this->assertSame(4, $stats['totalTasks']);
        $this->assertSame(2, $stats['completedTasks']);
        $this->assertSame(50.0, $stats['tasksCompletionRate']);
    }

    /**
     * والمستنداتُ في موضعها: الإجماليُّ تحت العملاء، والجديدُ تحت
     * «جديد هذا الشهر» — لا متبادلَين.
     */
    public function test_the_document_numbers_are_not_swapped(): void
    {
        $case = $this->makeCase();

        // مستندان قديمان وواحدٌ هذا الشهر
        foreach ([now()->subMonths(3), now()->subMonths(2), now()] as $i => $when) {
            $doc = Document::create([
                'case_id' => $case->id,
                'uploaded_by' => $this->admin->id,
                'title' => 'مستند ' . $i,
                'file_path' => 'documents/' . $i . '.pdf',
                'file_type' => 'pdf',
                'file_size' => 100,
                'access_level' => Document::ACCESS_ALL,
            ]);
            $doc->forceFill(['created_at' => $when])->save();
        }

        $stats = $this->stats();

        $this->assertSame(3, $stats['totalDocuments']);
        $this->assertSame(1, $stats['newDocumentsThisMonth']);

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->getContent();

        // بطاقةُ العملاء تحمل الإجماليَّ (٣)، وبطاقةُ الشهر الجديدَ (١)
        $this->assertStringContainsString('3 ' . __('app.document'), $html,
            'بطاقةُ العملاء لا تعرض إجماليَّ المستندات');
        $this->assertStringContainsString('1 ' . __('app.documents_new'), $html,
            'بطاقةُ «جديد هذا الشهر» لا تعرض جديدَ المستندات');
    }

    /**
     * ═══ صفرٌ لا يُلوَّن ═══
     *
     * «مهام مكتملة هذا الأسبوع: صفر» وشريطُها ممتلئٌ بلونٍ ثلثَ عرضه —
     * لأنّ عرضَه كان نسبةَ الإنجاز الكلّيّة (كلُّ ما أُنجز منذ البداية على
     * كلّ المهام)، وهي رقمُ بطاقةٍ أخرى في الشاشة نفسِها. فيقرأ المكتبُ
     * صفراً ويرى إنجازاً.
     */
    public function test_a_zero_week_leaves_the_bar_empty(): void
    {
        $case = $this->makeCase();

        // ستُّ مهامّ أُنجزت قبل شهر، وواحدةٌ مستحقّةٌ هذا الأسبوع لم تُنجَز
        foreach (range(1, 6) as $i) {
            $this->task($case, 'completed', now()->subMonth(), now()->subMonth());
        }
        $this->task($case, 'pending', now()->addDay());

        $stats = $this->stats();

        $this->assertSame(0, $stats['completedThisWeek']);
        $this->assertSame(0.0, $stats['weekCompletionRate'], 'شريطُ الأسبوع مُلوَّنٌ وما أُنجز فيه صفر');
        $this->assertGreaterThan(0, $stats['tasksCompletionRate'], 'النسبةُ الكلّيّة ليست هي المقصودة هنا');

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('bg-purple-500 h-2 rounded-full transition-all" style="width: ' . $stats['tasksCompletionRate'], $html,
            'الشريطُ ما زال يرسم النسبةَ الكلّيّة');
    }

    /** والشريطُ يقيس عملَ الأسبوع: ما أُنجز فيه على ما استُحقّ فيه. */
    public function test_the_week_bar_measures_the_week(): void
    {
        $case = $this->makeCase();

        $this->task($case, 'completed', now()->subMonth(), now()->subDay());   // أُنجزت هذا الأسبوع
        $this->task($case, 'completed', now()->subMonth(), now()->subMonths(2)); // أُنجزت قبل شهرين
        $this->task($case, 'pending', now()->addDay());                        // مستحقّة هذا الأسبوع
        $this->task($case, 'pending', now()->addMonth());                      // مستحقّة بعد الأسبوع — خارج المقام

        $stats = $this->stats();

        $this->assertSame(1, $stats['completedThisWeek']);
        $this->assertSame(2, $stats['weekTaskTarget'], 'المقامُ ليس عملَ الأسبوع');
        $this->assertSame(50.0, $stats['weekCompletionRate']);
    }

    /** وحدُّ الأسبوع سبتٌ ثابتٌ لا يتبدّل بتبدّل لغة الواجهة. */
    public function test_the_week_starts_on_saturday_whatever_the_interface_language(): void
    {
        $case = $this->makeCase();

        // مهمّةٌ أُنجزت سبتَ هذا الأسبوع: تُعدّ في العربيّة والإنجليزيّة سواء
        $saturday = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->addHours(9);
        $this->task($case, 'completed', now()->subMonth(), $saturday);

        foreach (['ar', 'en'] as $locale) {
            $this->app->setLocale($locale);
            $this->assertSame(1, $this->stats()['completedThisWeek'], 'تبدّل معنى «هذا الأسبوع» بتبدّل اللغة: ' . $locale);
        }
    }

    private function task(LegalCase $case, string $status, $dueDate = null, $completedAt = null): Task
    {
        return Task::create([
            'title' => 'مهمة ' . uniqid(),
            'case_id' => $case->id,
            'assigned_to' => $this->admin->id,
            'created_by' => $this->admin->id,
            'status' => $status,
            'priority' => 'medium',
            'due_date' => $dueDate,
            'completed_at' => $status === 'completed' ? ($completedAt ?? now()) : null,
        ]);
    }
}
