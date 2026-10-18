<?php

namespace Tests\Feature;

use App\Http\Controllers\FeasibilityController;
use App\Models\Client;
use App\Models\HrAttendance;
use App\Models\HrLeave;
use App\Models\HrLeaveType;
use App\Models\HrPerformance;
use App\Models\LegalCase;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Support\AttendanceGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * الموارد البشريّة ودراسة الجدوى تقولان ما تقيسانه، بلغة القارئ.
 *
 * ═══ ما ظهر على الشاشة ═══
 *
 * - أشهرُ «اتجاهات القضايا الشهرية» May 2026 … Oct 2026.
 * - «كُسبت 6 قضية من 8 قضية» و«7 القضايا · 36 المهام · 196 يوم».
 * - المحاسبةُ بلا قضيّةٍ ولا مهمّة «يحتاج تحسين 0%»، و«معدّل النجاح 34%»
 *   بجوار «نسبة فوز المكتب 75%».
 * - «إجازات pending»، والدورُ «lawyer»، ونوعُ الإجازة «emergency».
 * - الانصرافُ «04:15 PM» بجوار حضورٍ «08:15 ص»، و«أقلّ من دقيقة في 1 يوم».
 * - من لم يُقيَّم قطّ «ضعيف»، و«طلب إجازة إجازة طارئة».
 * - «نسبة الكفاءة» في صفحة المحامي 0% دائماً.
 * - أثرُ الحضور «أُقفل بالسقف 04:15 PM» في جملةٍ عربيّة.
 */
class HrFeasibilityFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        app()->setLocale('ar');
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'name' => 'هلال المدير', 'locale' => 'ar']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(string $name, string $role = 'lawyer'): User
    {
        return User::factory()->create(['name' => $name, 'role' => $role, 'is_active' => true, 'locale' => 'ar']);
    }

    private function casesFor(User $u, int $count, string $status): void
    {
        $client = Client::create([
            'name' => 'موكّل', 'type' => 'individual',
            'national_id' => (string) random_int(1000000, 9999999), 'phone' => '96890000000',
        ]);

        for ($i = 0; $i < $count; $i++) {
            $n = ++$this->seq;
            LegalCase::create([
                'case_number' => 'ق/' . $n, 'office_case_number' => (string) $n, 'title' => 'قضية',
                'description' => 'و', 'type' => 'مدني', 'court' => 'محكمة', 'opponent' => 'خصم',
                'status' => $status, 'priority' => 'medium',
                'client_id' => $client->id, 'lawyer_id' => $u->id,
                'created_by' => $u->id, 'opened_at' => now()->subMonths(2),
            ]);
        }
    }

    private function taskFor(User $u, string $status = 'completed'): void
    {
        Task::create([
            'title' => 'مهمّة ' . (++$this->seq), 'status' => $status, 'priority' => 'medium',
            'assigned_to' => $u->id, 'created_by' => $u->id,
            'due_date' => now()->addDays(3), 'completed_at' => $status === 'completed' ? now() : null,
        ]);
    }

    // ══════════ دراسة الجدوى ══════════

    /** format('M Y') كان يكتب «May 2026 … Oct 2026» تحت الرسم وفي جدول أرقامه. */
    public function test_the_monthly_trend_is_labelled_with_arabic_months(): void
    {
        Carbon::setTestNow('2026-10-17 09:00:00');
        $lawyer = $this->member('محامٍ');
        $this->casesFor($lawyer, 1, 'active');

        $res = $this->actingAs($this->admin)->get(route('feasibility.index'))->assertOk();

        $labels = collect($res->viewData('monthlyTrend'))->pluck('label')->all();
        $this->assertSame(['مايو 2026', 'يونيو 2026', 'يوليو 2026', 'أغسطس 2026', 'سبتمبر 2026', 'أكتوبر 2026'], $labels);
        $res->assertDontSee('Oct 2026');
    }

    /** العددُ ومعدودُه: «كُسبت 6 قضية من 8 قضية» و«7 القضايا · 196 يوم». */
    public function test_counts_agree_with_their_nouns(): void
    {
        $lawyer = $this->member('المحامي المجتهد');
        $this->casesFor($lawyer, 6, 'won');
        $this->casesFor($lawyer, 2, 'lost');
        $other = $this->member('المحامي الثاني');
        $this->casesFor($other, 1, 'active');

        $res = $this->actingAs($this->admin)->get(route('feasibility.index'))->assertOk();

        $res->assertSee('من القضايا المحسومة كُسبت 6 من 8');
        $res->assertDontSee('6 قضية');
        // المجتهد ثمانِ قضايا ولا مهامّ، والثاني قضيّةٌ واحدة
        $res->assertSee('8 قضايا · لا مهام');
        $res->assertSee('قضية واحدة · لا مهام');
        $res->assertDontSee('8 ' . __('app.cases'));
    }

    /** المحاسبةُ بلا قضيّةٍ ولا مهمّة كانت «يحتاج تحسين 0%» — وهي لم تُقَس. */
    public function test_a_member_without_data_is_never_the_least_performer(): void
    {
        $strong = $this->member('الأقوى');
        $this->casesFor($strong, 3, 'won');
        $weak = $this->member('الأضعف');
        $this->casesFor($weak, 1, 'lost');
        $accountant = $this->member('فاطمة المحاسبة', 'staff');

        $res = $this->actingAs($this->admin)->get(route('feasibility.index'))->assertOk();

        $least = $res->viewData('leastPerformer');
        $this->assertTrue($least['has_data']);
        $this->assertSame($weak->id, $least['user']->id);
        $this->assertNotSame($accountant->id, $least['user']->id);

        // وفي لوحة الترتيب يُكتب «لا توجد بيانات» لا شريطاً أحمرَ «0%»
        $row = collect($res->viewData('efficiencyData'))->firstWhere('user.id', $accountant->id);
        $this->assertFalse($row['has_data']);
        $res->assertSee(__('app.no_data'));
    }

    /** «معدّل النجاح» كان يَعُدّ من لا قضيّةَ محسومةً له ٠٪ — ٢٥٪ بجوار فوزٍ ٧٥٪. */
    public function test_office_averages_skip_members_without_a_denominator(): void
    {
        $a = $this->member('صاحب المحسومات');
        $this->casesFor($a, 3, 'won');
        $this->casesFor($a, 1, 'lost');
        $b = $this->member('بلا محسوم');
        $this->casesFor($b, 2, 'active');

        $res = $this->actingAs($this->admin)->get(route('feasibility.index'))->assertOk();

        $this->assertEquals(75.0, $res->viewData('avgSuccess'));
        $this->assertEquals(75.0, $res->viewData('officeWinRate'));
    }

    // ══════════ الموارد البشريّة ══════════

    /** «إجازات pending» والدورُ «lawyer / staff» في بطاقةٍ وجدولٍ عربيّين. */
    public function test_hr_cards_and_roles_are_arabic(): void
    {
        $this->member('منى', 'staff');
        $this->member('سالم');

        $res = $this->actingAs($this->admin)->get(route('hr.index', ['tab' => 'employees']))->assertOk();

        $res->assertSee('إجازات قيد الانتظار');
        $res->assertDontSee('إجازات pending');
        $res->assertSee('border-gold/25">' . __('app.lawyer') . '</span>', false);
        $res->assertSee('border-gold/25">' . __('app.staff') . '</span>', false);
        $res->assertDontSee('border-gold/25">lawyer</span>', false);
        $res->assertDontSee('border-gold/25">admin</span>', false);
    }

    /** من لا تقييمَ له كان «ضعيف»: متوسّطُه null يسقط في الفئة الأخيرة. */
    public function test_an_unreviewed_employee_is_not_counted_as_poor(): void
    {
        $good = $this->member('الممتاز');
        $poor = $this->member('الضعيف', 'staff');
        HrPerformance::create(['employee_id' => $good->id, 'reviewer_id' => $this->admin->id, 'rating' => 5, 'review_date' => now()->toDateString()]);
        HrPerformance::create(['employee_id' => $poor->id, 'reviewer_id' => $this->admin->id, 'rating' => 2, 'review_date' => now()->toDateString()]);

        $res = $this->actingAs($this->admin)->get(route('hr.index', ['tab' => 'employees']))->assertOk();

        // المديرُ نفسُه بلا تقييم
        $this->assertSame(['excellent' => 1, 'good' => 0, 'poor' => 1, 'unrated' => 1], $res->viewData('ratingDistribution'));
        // ونقاطُ المفتاح تأخذ ألوانَ قطع الرسم لا green/yellow/red-400
        $res->assertSee('data-rating-dot="warn"', false);
        $res->assertDontSee('rounded-full bg-yellow-400', false);
    }

    /** النوعُ اسمُه لا رمزُه — والقديمُ بلا نوعٍ مربوط يُترجَم لا يبقى «sick». */
    public function test_the_leave_type_badge_shows_the_name(): void
    {
        $emp = $this->member('منى', 'staff');
        $emergency = HrLeaveType::where('code', 'emergency')->firstOrFail();
        HrLeave::create(['employee_id' => $emp->id, 'type' => 'emergency', 'leave_type_id' => $emergency->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'status' => 'pending', 'days' => 2]);
        HrLeave::create(['employee_id' => $emp->id, 'type' => 'sick', 'leave_type_id' => null,
            'start_date' => '2026-09-01', 'end_date' => '2026-09-01', 'status' => 'approved', 'days' => 1]);

        $res = $this->actingAs($this->admin)->get(route('hr.index', ['tab' => 'leaves']))->assertOk();

        $res->assertSee('border-gray-200">إجازة طارئة</span>', false);
        $res->assertSee('border-gray-200">إجازة مرضية</span>', false);
        $res->assertDontSee('border-gray-200">emergency</span>', false);
        $res->assertDontSee('border-gray-200">sick</span>', false);
    }

    /** «قدّم منى طلب إجازة إجازة طارئة»: اسمُ النوع يبدأ بـ«إجازة» أصلاً. */
    public function test_the_leave_notification_does_not_repeat_the_word(): void
    {
        $emp = $this->member('منى بنت سالم', 'staff');
        $emergency = HrLeaveType::where('code', 'emergency')->firstOrFail();

        $this->actingAs($emp)->post(route('hr.leaves.store'), [
            'leave_type_id' => $emergency->id, 'start_date' => '2026-10-20', 'end_date' => '2026-10-20',
        ])->assertRedirect();

        $n = Notification::where('user_id', $this->admin->id)->where('message_key', 'app.notif_leave_new_body')->firstOrFail();
        $this->assertSame('قدّم منى بنت سالم طلب إجازة طارئة', $n->localizedMessage('ar'));

        // والإجازةُ القديمة بلا نوعٍ مربوط تُسمّى بالاسم نفسِه
        $legacy = new HrLeave(['type' => 'emergency']);
        $this->assertSame('إجازة طارئة', $legacy->typeName());

        // وإشعارُ الخصم: «3 يوم» خطأُ تمييز
        $this->assertSame('الموظف منى · عدد الأيام 3 · الخصم المتوقّع 10.00 ر.ع',
            __('app.notif_leave_deduction_body', ['employee' => 'منى', 'days' => 3, 'amount' => '10.00'], 'ar'));
    }

    /** الانصرافُ «04:15 PM» بجوار حضورٍ «08:15 ص» في الصفّ نفسِه. */
    public function test_the_check_out_column_is_arabic_like_the_check_in(): void
    {
        Carbon::setTestNow('2026-10-17 14:00:00'); // ‏18:00 بمسقط
        $emp = $this->member('منى', 'staff');
        HrAttendance::create([
            'user_id' => $emp->id, 'work_date' => '2026-10-17',
            'check_in_at' => Carbon::parse('2026-10-17 08:15', 'Asia/Muscat'),
            'check_out_at' => Carbon::parse('2026-10-17 16:15', 'Asia/Muscat'),
            'minutes' => 480, 'status' => 'completed', 'closed_by' => HrAttendance::CLOSED_BY_BUTTON,
        ]);

        $res = $this->actingAs($this->admin)->get(route('hr.index', ['tab' => 'attendance_log']))->assertOk();

        $res->assertSee('08:15 ص');
        $res->assertSee('04:15 م');
        $res->assertDontSee('04:15 PM');
    }

    /** يومٌ مفتوحٌ وحدَه كان «أقلّ من دقيقة في 1 يوم · 1 مفتوح». */
    public function test_range_totals_do_not_count_an_open_day_as_zero_minutes(): void
    {
        Carbon::setTestNow('2026-10-17 06:00:00'); // ‏10:00 بمسقط
        $present = $this->member('الحاضرة', 'staff');
        HrAttendance::create([
            'user_id' => $present->id, 'work_date' => '2026-10-17',
            'check_in_at' => Carbon::parse('2026-10-17 08:00', 'Asia/Muscat'),
            'status' => 'present',
        ]);
        $done = $this->member('المنصرف', 'staff');
        foreach (['2026-10-12', '2026-10-13'] as $day) {
            HrAttendance::create([
                'user_id' => $done->id, 'work_date' => $day,
                'check_in_at' => Carbon::parse($day . ' 08:00', 'Asia/Muscat'),
                'check_out_at' => Carbon::parse($day . ' 16:00', 'Asia/Muscat'),
                'minutes' => 480, 'status' => 'completed', 'closed_by' => HrAttendance::CLOSED_BY_BUTTON,
            ]);
        }

        $res = $this->actingAs($this->admin)->get(route('hr.index', ['tab' => 'attendance_log', 'range' => 'month']))->assertOk();

        $this->assertNull($res->viewData('attTotals')[$present->id]['minutes']);
        $res->assertDontSee('أقلّ من دقيقة');
        $res->assertSee('— يومٌ مفتوح');
        $res->assertSee('16 س في يومين');
        $res->assertDontSee('في 2 يوم');
    }

    /** صندوقُ الموظّف بعرضٍ ثابت — كان يضيق عند فقد التركيز فيُفلت ضغطةَ «تصفية». */
    public function test_the_employee_filter_has_a_stable_width(): void
    {
        $res = $this->actingAs($this->admin)->get(route('hr.index', ['tab' => 'attendance_log']))->assertOk();

        // وسطرٌ واحد: حقلُ البحث كان يلتفّ تحت الاسم بعد الاختيار ثمّ يُخفى عند فقد
        // التركيز، فيقصر الصفُّ ويقفز الزرّ بين الضغط والإفلات (أُعيد إنتاجه في المتصفّح)
        $res->assertSee('<div class="w-full sm:w-64 [&_.ts-control]:flex-nowrap [&_.ts-control>.item]:truncate">', false);
        $res->assertSee('name="employee_id" class="ts w-full', false);
    }

    // ══════════ صفحة المستخدم ══════════

    /** ‏$user->efficiency لم يكن عموداً ولا مُحوِّلاً — فكانت كلُّ صفحة محامٍ «0%». */
    public function test_the_lawyer_profile_shows_the_feasibility_efficiency(): void
    {
        $lawyer = $this->member('المحامي');
        $this->casesFor($lawyer, 3, 'won');
        $this->taskFor($lawyer);
        $idle = $this->member('محامٍ بلا عمل');

        $expected = FeasibilityController::teamEfficiency()->firstWhere('user.id', $lawyer->id)['overall'];
        $this->assertGreaterThan(0, $expected);

        $this->actingAs($this->admin)->get(route('users.show', $lawyer))->assertOk()
            ->assertSee($expected . '%');
        $this->assertSame((float) $expected, $lawyer->fresh()->efficiency);

        // ومن لا بيانات له «—» لا «0%»
        $this->assertNull($idle->fresh()->efficiency);
        $this->actingAs($this->admin)->get(route('users.show', $idle))->assertOk()
            ->assertDontSee('>0%<', false);
    }

    // ══════════ أثرُ الحضور ══════════

    /**
     * «صحّحه … 2026-10-17: كان 07:31 AM → 01:36 PM» — لاتينيّةٌ في جملةٍ عربيّة
     * تُقرأ مقلوبةً تحت RTL، وتاريخٌ بالشَّرطة ينقلب، وسهمٌ يشير إلى القديم.
     */
    public function test_attendance_notes_use_arabic_times_and_an_rtl_arrow(): void
    {
        Carbon::setTestNow('2026-10-17 14:00:00');
        app()->setLocale('en'); // الأثرُ عربيٌّ أيّاً كانت لغةُ من كتبه
        $emp = $this->member('منى', 'staff');
        $record = HrAttendance::create([
            'user_id' => $emp->id, 'work_date' => '2026-10-17',
            'check_in_at' => Carbon::parse('2026-10-17 07:31', 'Asia/Muscat'),
            'check_out_at' => Carbon::parse('2026-10-17 13:36', 'Asia/Muscat'),
            'minutes' => 365, 'status' => 'completed', 'closed_by' => HrAttendance::CLOSED_BY_CAP,
        ]);

        AttendanceGuard::correct($record, $this->admin,
            Carbon::parse('2026-10-17 07:31', 'Asia/Muscat'), Carbon::parse('2026-10-17 15:57', 'Asia/Muscat'), 'خرج متأخّراً');

        $note = (string) $record->fresh()->note;
        $this->assertStringContainsString('صحّحه هلال المدير 2026/10/17: كان 07:31 ص ← 01:36 م، صار 07:31 ص ← 03:57 م', $note);
        $this->assertStringNotContainsString('PM', $note);
        $this->assertStringNotContainsString('→', $note);

        $added = AttendanceGuard::addDay($emp, $this->admin,
            Carbon::parse('2026-10-15 08:00', 'Asia/Muscat'), Carbon::parse('2026-10-15 16:00', 'Asia/Muscat'), 'يوم محكمة');
        $this->assertStringContainsString('08:00 ص ← 04:00 م', (string) $added->note);
    }
}
