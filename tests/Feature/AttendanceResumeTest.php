<?php

namespace Tests\Feature;

use App\Models\HrAttendance;
use App\Models\User;
use App\Support\AttendanceGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * الدوامُ يُستأنف بعد انصرافٍ مسجَّل — والسقفُ لا يُقفل على من يعمل.
 *
 * ═══ الشكوى كما وردت ═══
 *
 * «تسجيل الخروج ليس فقط يخرجنا من النظام، بل يسوّي مغادرة، وبالتالي
 * ساعاتُ العمل تكون أقلّ على الرغم من أنّنا في فترة الدوام؛ يكتب
 * "لقد اكتمل يومك" ويسجّل خروجاً — وجدتُ مسجَّلاً لي وقتَ الانصراف ١:٢٩».
 *
 * المكاتبُ تعمل فترتين: صباحيّةً إلى الواحدة والنصف ومسائيّةً من الرابعة
 * والنصف. فمن أُقفل يومُه ظهراً — بزرّ الخروج، أو بزرّ الانصراف، أو
 * بالسقف — كان يعود عصراً إلى «يومك مكتمل ✓» ولا شيءَ يفتحه.
 *
 * ═══ ما يُحرَس هنا ═══
 *
 * ١) الاستئناف يفتح سجلَّ اليوم نفسَه ويحفظ دقائقَه، والانصرافُ التالي
 *    يضيف ولا يحسب الاستراحةَ دواماً.
 * ٢) الدخولُ بعد انصرافٍ قريب يستأنف وحده؛ وبعد يومٍ انقضى لا يفتح فترةً
 *    تبلغ السقفَ وتضيف ثماني ساعاتٍ لم تُعمل.
 * ٣) السقفُ يُقاس من بداية الفترة المفتوحة، ولا يُقفل على من رآه الخادم
 *    في آخر ساعة، ولا يقصّ وقتاً رآه بعد السقف.
 */
class AttendanceResumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // ساعةٌ مجمَّدة: الحزمةُ كانت خضراءَ بعد السادسة مساءً فقط — أوقاتُ «16:00» المزروعة
        // تقع في المستقبل صباحاً أو داخل نافذة الخمول عصراً
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-16 21:00:00', 'Asia/Muscat'));
    }

    private function staff(): User
    {
        return User::factory()->create([
            'role' => 'lawyer',
            'is_active' => true,
            'password' => Hash::make('secret-pass-123'),
        ]);
    }

    /** يومٌ مقفَل: حضورٌ قبل ساعاتٍ وانصرافٌ قبل $outHoursAgo ساعة. */
    private function closedDay(User $user, int $inHoursAgo, int $outHoursAgo, ?string $source = 'auto_login'): HrAttendance
    {
        return HrAttendance::create([
            'user_id' => $user->id,
            'work_date' => HrAttendance::today(),
            'check_in_at' => now()->subHours($inHoursAgo),
            'check_out_at' => now()->subHours($outHoursAgo),
            'minutes' => ($inHoursAgo - $outHoursAgo) * 60,
            'status' => 'completed',
            'source' => $source,
        ]);
    }

    /** آخرُ نشاطٍ بشريّ — عمودٌ دائم لا صفُّ جلسةٍ يمحوه الخروج. */
    private function seen(User $user, \Carbon\CarbonInterface $at): void
    {
        DB::table('users')->where('id', $user->id)->update(['last_seen_at' => $at]);
    }

    private function login(User $user): \Illuminate\Testing\TestResponse
    {
        return $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-123']);
    }

    // ══════════ الاستئناف بالزرّ ══════════

    /** انصرافٌ ظهراً ثمّ استئنافٌ عصراً: الفترتان تُجمعان والاستراحةُ لا تُحسب. */
    public function test_resume_reopens_today_and_the_next_checkout_adds_to_what_was_banked(): void
    {
        $user = $this->staff();
        $record = $this->closedDay($user, inHoursAgo: 8, outHoursAgo: 3); // ٥ ساعاتٍ صباحاً، استراحةُ ٣

        $this->actingAs($user)->post(route('hr.attendance.resume'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $record->refresh();
        $this->assertNull($record->check_out_at, 'الاستئناف لم يفتح اليوم');
        $this->assertSame('present', $record->status);
        $this->assertNotNull($record->resumed_at);
        $this->assertSame(300, (int) $record->minutes, 'دقائقُ الصباح ضاعت بالاستئناف');
        $this->assertSame(2, (int) $record->intervals);
        $this->assertSame(1, HrAttendance::count(), 'الاستئناف أنشأ سجلاً ثانياً — القيدُ الفريد يومٌ لكلّ موظّف');
        $this->assertStringContainsString('استُؤنف', (string) $record->note);

        // فترةٌ مسائيّة ساعتان ثمّ الزرّ
        $record->update(['resumed_at' => now()->subHours(2)]);
        $this->actingAs($user)->post(route('hr.attendance.checkout'))->assertRedirect();

        $record->refresh();
        $this->assertSame('completed', $record->status);
        $this->assertNull($record->resumed_at);
        $this->assertEqualsWithDelta(420, (int) $record->minutes, 1, 'المدّةُ ليست ٥ + ٢ ساعات — الاستراحةُ حُسبت أو الصباحُ ضاع');
        $this->assertTrue($record->check_out_at->greaterThan(now()->subMinute()));
    }

    /** لا انصرافَ اليوم: لا شيءَ يُستأنف ولا سجلَّ يُخترع. */
    public function test_resume_with_nothing_to_resume_creates_nothing(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->post(route('hr.attendance.resume'))
            ->assertRedirect()
            ->assertSessionHasErrors('attendance');

        $this->assertSame(0, HrAttendance::count());
    }

    /** ويومٌ مفتوحٌ أصلاً لا يُستأنف — فلا تُضاعَف فتراتُه. */
    public function test_an_open_day_is_not_resumed_again(): void
    {
        $user = $this->staff();
        $record = HrAttendance::create([
            'user_id' => $user->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->subHours(2), 'status' => 'present', 'source' => 'auto_login',
        ]);

        $this->actingAs($user)->post(route('hr.attendance.resume'))->assertSessionHasErrors('attendance');

        $this->assertSame(1, (int) $record->fresh()->intervals);
        $this->assertNull($record->fresh()->resumed_at);
    }

    /** وما بلغ السقفَ ثمّ استُؤنف وأُقفل بالزرّ يُوسم بالزرّ — والأثرُ يحفظ أنّ ما قبله سقف. */
    public function test_a_capped_day_resumed_and_closed_by_hand_is_marked_by_the_button(): void
    {
        $user = $this->staff();
        $record = $this->closedDay($user, inHoursAgo: 9, outHoursAgo: 1);
        $record->update(['closed_by' => HrAttendance::CLOSED_BY_CAP]);

        AttendanceGuard::resume($user);
        AttendanceGuard::checkOut($user);

        $fresh = $record->fresh();
        $this->assertSame(HrAttendance::CLOSED_BY_BUTTON, $fresh->closed_by);
        $this->assertNull($fresh->inferredLabel());
        $this->assertStringContainsString('بعد إقفالٍ بالسقف', (string) $fresh->note);
    }

    // ══════════ الاستئناف عند الدخول ══════════

    /** دخولٌ عصراً بعد إقفالٍ آليٍّ قريب (سقف): يُستأنف وحده ويقولها. */
    public function test_login_after_a_short_break_resumes_the_day(): void
    {
        $user = $this->staff();
        $record = $this->closedDay($user, inHoursAgo: 8, outHoursAgo: 3);
        $record->update(['closed_by' => HrAttendance::CLOSED_BY_CAP]);

        $this->login($user)->assertRedirect();

        $record->refresh();
        $this->assertNull($record->check_out_at, 'الدخولُ بعد الاستراحة لم يستأنف اليوم');
        $this->assertSame(300, (int) $record->minutes);
        $this->assertSame(2, (int) $record->intervals);
        $this->assertTrue(session('attendance_flash')['resumed'] ?? false, 'الإشعارُ لا يقول إنّ الدوام استُؤنف');
    }

    /**
     * وانصرافٌ بالزرّ لا يُفتح خلسةً بدخولٍ لاحق — يُعرض الاستئنافُ ويقرّر هو.
     *
     * محامٍ انصرف الواحدةَ والنصف بيده ودخل من هاتفه الثامنةَ ليقرأ حكماً
     * دقيقتين كان يجد يومَه مفتوحاً ثمّ مقفَلاً بالسقف بساعاتٍ لم يعملها.
     */
    public function test_login_after_a_button_checkout_offers_resume_instead_of_resuming(): void
    {
        $user = $this->staff();
        $record = $this->closedDay($user, inHoursAgo: 8, outHoursAgo: 3);
        $record->update(['closed_by' => HrAttendance::CLOSED_BY_BUTTON]);

        $this->login($user)->assertRedirect();

        $this->assertNotNull($record->fresh()->check_out_at, 'انصرافٌ بالزرّ فُتح خلسةً بالدخول');
        $this->assertSame(1, (int) $record->fresh()->intervals);

        $page = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $page->assertSee('data-attendance-resume', false)->assertSee('استئناف الدوام');

        // «انتهى يومي» يُغلق السؤالَ لليوم ولا يمسّ السجلّ
        $this->actingAs($user)->post(route('attendance.keep'), ['dismiss' => 'resume'])->assertRedirect();
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('data-attendance-resume', false);
        $this->assertNotNull($record->fresh()->check_out_at);
    }

    /** ودخولٌ ليلاً بعد يومٍ انقضى لا يفتح فترةً تبلغ السقفَ صباحاً. */
    public function test_login_long_after_the_checkout_does_not_resume(): void
    {
        $user = $this->staff();
        $record = $this->closedDay($user, inHoursAgo: 14, outHoursAgo: 7);
        $record->update(['closed_by' => HrAttendance::CLOSED_BY_CAP]);

        $this->login($user)->assertRedirect();

        $record->refresh();
        $this->assertNotNull($record->check_out_at, 'انصرافُ يومٍ انقضى فُتح بدخولٍ ليليّ');
        $this->assertSame(1, (int) $record->intervals);
        $this->assertFalse(session('attendance_flash')['resumed'] ?? false);
    }

    /** ومن عاد إلى «يومك مكتمل» بانصرافٍ قريب يجد زرَّ الاستئناف — وبانصرافٍ انقضى يومُه لا يجده. */
    public function test_a_completed_day_offers_the_resume_button(): void
    {
        $user = $this->staff();
        $record = $this->closedDay($user, inHoursAgo: 8, outHoursAgo: 3);
        $record->update(['closed_by' => HrAttendance::CLOSED_BY_BUTTON]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('يومك مكتمل')
            ->assertSee(route('hr.attendance.resume'))
            ->assertSee('استئناف الدوام');

        $this->actingAs($user)->get(route('hr.index', ['tab' => 'attendance']))
            ->assertOk()
            ->assertSee('استئناف الدوام');

        // انصرافٌ قبل سبع ساعات: يومٌ انقضى — لا زرَّ، والخادمُ يردّ الطلبَ المباشر
        $record->update(['check_out_at' => now()->subHours(7)]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee(route('hr.attendance.resume'));
        $this->actingAs($user)->post(route('hr.attendance.resume'))->assertSessionHasErrors('attendance');
        $this->assertNotNull($record->fresh()->check_out_at);
    }

    // ══════════ السقفُ والفترات ══════════

    /** السقفُ سقفُ اليوم: يُقاس من الاستئناف مع ما حُفظ قبله، لا من الحضور الأوّل ولا لكلّ فترةٍ وحدَها. */
    public function test_the_cap_measures_the_open_interval_not_the_first_check_in(): void
    {
        $user = $this->staff();
        $record = HrAttendance::create([
            'user_id' => $user->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->subHours(12),       // حضورٌ أوّل قبل ١٢ ساعة
            'resumed_at' => now()->subHours(2),         // واستئنافٌ قبل ساعتين
            'minutes' => 300, 'intervals' => 2,
            'status' => 'present', 'source' => 'auto_login',
        ]);

        $this->assertSame(0, AttendanceGuard::closeOvertimeRecords(), 'المستأنَفُ قبل ساعتين أُقفل بالسقف من حضوره الأوّل');
        $this->assertNull($record->fresh()->check_out_at);

        // وحين يبلغ اليومُ كلُّه السقفَ (٣٠٠ محفوظة + ١٨٠ من الاستئناف) يُقفل
        // على ذلك الحدّ: ثماني ساعاتٍ لليوم لا ثمانٍ لكلّ فترة
        $record->update(['resumed_at' => now()->subHours(9)]);
        $this->assertSame(1, AttendanceGuard::closeOvertimeRecords());

        $record->refresh();
        $this->assertSame(480, (int) $record->minutes, 'فترتان بسقفٍ لكلٍّ — اليومُ تجاوز سقفَه');
        $this->assertLessThanOrEqual(2, abs($record->check_out_at->diffInSeconds(now()->subHours(9)->addHours(3))),
            'وقتُ الانصراف ليس استئنافاً + ما بقي من السقف');
        $this->assertSame(HrAttendance::CLOSED_BY_CAP, $record->closed_by);
    }

    /** من رآه الخادمُ في آخر ساعة لا يُقفل عليه وهو على شاشته. */
    public function test_the_cap_skips_someone_still_active(): void
    {
        $user = $this->staff();
        $record = HrAttendance::create([
            'user_id' => $user->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->subHours(9), 'status' => 'present', 'source' => 'auto_login',
        ]);
        $this->seen($user, now()->subMinutes(10));

        $this->assertSame(0, AttendanceGuard::closeOvertimeRecords(), 'أُقفل على موظّفٍ رآه الخادم قبل عشر دقائق');
        $this->assertNull($record->fresh()->check_out_at);
    }

    /** ومن جاوز السقفَ وهو يعمل ثمّ غاب يُقفل على آخر ما رُئي — لا على السقف. */
    public function test_the_cap_keeps_time_seen_after_the_cap(): void
    {
        $user = $this->staff();
        $record = HrAttendance::create([
            'user_id' => $user->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->subHours(11), 'status' => 'present', 'source' => 'auto_login',
        ]);
        // رُئي قبل ساعتين (أي بعد السقف بساعة) ثمّ غاب
        $this->seen($user, now()->subHours(2));

        $this->assertSame(1, AttendanceGuard::closeOvertimeRecords());

        $record->refresh();
        $this->assertEqualsWithDelta(540, (int) $record->minutes, 1, 'قُصّت ساعةٌ رآها الخادم بعد السقف');
        $this->assertSame(HrAttendance::CLOSED_BY_SEEN, $record->closed_by, 'وقتٌ من آخر نشاطٍ وُسم سقفاً');
        $this->assertSame('آخر نشاط', $record->inferredLabel());
    }

    /** وما رُئي قبل السقف لا يُستنتج منه انصراف: يبقى السقفُ حدّاً لا نقرةً. */
    public function test_activity_before_the_cap_never_lowers_the_cap(): void
    {
        $user = $this->staff();
        $record = HrAttendance::create([
            'user_id' => $user->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->subHours(10), 'status' => 'present', 'source' => 'auto_login',
        ]);
        $this->seen($user, now()->subHours(6)); // آخرُ نقرةٍ بعد أربع ساعاتٍ من الحضور

        AttendanceGuard::closeOvertimeRecords();

        $this->assertSame(480, (int) $record->fresh()->minutes, 'آخرُ نقرةٍ صارت انصرافاً — الشكوى الأولى عائدة');
    }

    /** الكشفُ يقول «فترتان» و«بلغ السقف» بدل أرقامٍ تبدو مضغوطةً بيد صاحبها. */
    public function test_the_log_labels_resumed_and_capped_records(): void
    {
        $user = $this->staff();
        HrAttendance::create([
            'user_id' => $user->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->subHours(12), 'check_out_at' => now()->subHour(),
            'minutes' => 540, 'intervals' => 2, 'status' => 'completed', 'source' => 'auto_login',
            'closed_by' => HrAttendance::CLOSED_BY_CAP,
        ]);

        $this->actingAs($user)->get(route('hr.index', ['tab' => 'attendance']))
            ->assertOk()
            ->assertSee('فترتان')
            ->assertSee('بلغ السقف');
    }
}
