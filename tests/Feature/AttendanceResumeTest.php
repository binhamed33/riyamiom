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

    private function seen(User $user, \Carbon\CarbonInterface $at): void
    {
        DB::table('sessions')->insert([
            'id' => 'sess-' . uniqid(), 'user_id' => $user->id,
            'ip_address' => '127.0.0.1', 'user_agent' => 't', 'payload' => '',
            'last_activity' => $at->timestamp,
        ]);
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

    /** وما بلغ السقفَ ثمّ استُؤنف وأُقفل بالزرّ لم يعد موسوماً بالسقف. */
    public function test_a_capped_day_resumed_and_closed_by_hand_drops_the_inferred_mark(): void
    {
        $user = $this->staff();
        $record = $this->closedDay($user, inHoursAgo: 9, outHoursAgo: 1, source: 'auto_capped');

        AttendanceGuard::resume($user);
        AttendanceGuard::checkOut($user);

        $this->assertSame('manual', $record->fresh()->source);
        $this->assertNull($record->fresh()->inferredLabel());
    }

    // ══════════ الاستئناف عند الدخول ══════════

    /** دخولٌ عصراً بعد انصرافِ الظهر: يُستأنف وحده ويقولها. */
    public function test_login_after_a_short_break_resumes_the_day(): void
    {
        $user = $this->staff();
        $record = $this->closedDay($user, inHoursAgo: 8, outHoursAgo: 3);

        $this->login($user)->assertRedirect();

        $record->refresh();
        $this->assertNull($record->check_out_at, 'الدخولُ بعد الاستراحة لم يستأنف اليوم');
        $this->assertSame(300, (int) $record->minutes);
        $this->assertSame(2, (int) $record->intervals);
        $this->assertTrue(session('attendance_flash')['resumed'] ?? false, 'الإشعارُ لا يقول إنّ الدوام استُؤنف');
    }

    /** ودخولٌ ليلاً بعد يومٍ انقضى لا يفتح فترةً تبلغ السقفَ صباحاً. */
    public function test_login_long_after_the_checkout_does_not_resume(): void
    {
        $user = $this->staff();
        $record = $this->closedDay($user, inHoursAgo: 14, outHoursAgo: 7);

        $this->login($user)->assertRedirect();

        $record->refresh();
        $this->assertNotNull($record->check_out_at, 'انصرافُ يومٍ انقضى فُتح بدخولٍ ليليّ');
        $this->assertSame(1, (int) $record->intervals);
        $this->assertFalse(session('attendance_flash')['resumed'] ?? false);
    }

    /** ومن عاد إلى «يومك مكتمل» يجد زرَّ الاستئناف في لوحته وتبويب الحضور. */
    public function test_a_completed_day_offers_the_resume_button(): void
    {
        $user = $this->staff();
        $this->closedDay($user, inHoursAgo: 14, outHoursAgo: 7);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('يومك مكتمل')
            ->assertSee(route('hr.attendance.resume'))
            ->assertSee('استئناف الدوام');

        $this->actingAs($user)->get(route('hr.index', ['tab' => 'attendance']))
            ->assertOk()
            ->assertSee('استئناف الدوام');
    }

    // ══════════ السقفُ والفترات ══════════

    /** السقفُ يُقاس من الاستئناف لا من الحضور الأوّل. */
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

        // وحين تبلغ الفترةُ المفتوحة السقفَ تُقفل على «استئنافٌ + السقف» مضافاً إلى المحفوظ
        $record->update(['resumed_at' => now()->subHours(9)]);
        $this->assertSame(1, AttendanceGuard::closeOvertimeRecords());

        $record->refresh();
        $this->assertSame(300 + 480, (int) $record->minutes);
        $this->assertTrue($record->check_out_at->equalTo(now()->subHours(9)->addHours(8)->startOfSecond())
            || abs($record->check_out_at->diffInSeconds(now()->subHour())) <= 2,
            'وقتُ الانصراف ليس استئنافاً + ثماني ساعات');
        $this->assertSame('auto_capped', $record->source);
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
        $this->assertSame('auto_capped', $record->source);
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
            'minutes' => 540, 'intervals' => 2, 'status' => 'completed', 'source' => 'auto_capped',
        ]);

        $this->actingAs($user)->get(route('hr.index', ['tab' => 'attendance']))
            ->assertOk()
            ->assertSee('فترتان')
            ->assertSee('بلغ السقف');
    }
}
