<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\HrAttendance;
use App\Models\User;
use App\Support\AttendanceGuard;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * مَن كتب وقتَ الانصراف، ومن أين جاء «آخرُ نشاط»، وكيف يصحّح الإداري.
 *
 * ═══ الشكوى الثانية ═══
 *
 * محامٍ في مكتبٍ حقيقيّ: «النظام طلّعني من الدوام ١:٣٦ وأنا طالع ٣:٥٧،
 * وأمسِ ١:٣١ وأنا طالع ٣:٥٨ — أرجو تعديل وقت خروجي». ولا أحد يستطيع
 * أن يقول من الصفّ ما الذي كتب ١:٣٦، ولا يملك الإداري زرّاً يصحّحه.
 *
 * ═══ ما يُحرَس هنا ═══
 *
 * ١) أثرُ الموظّف يبقى بعد الخروج ويأتي من فعلِ إنسانٍ لا من نبضة آلة.
 * ٢) السقفُ يقرأ هذا الأثرَ ويحبسه في يوم السجلّ.
 * ٣) كلُّ انصرافٍ يحمل كاتبَه، ويُدوَّن في سجلّ التدقيق.
 * ٤) الإداري يصحّح بوقتين وسبب، ويبقى القديمُ مكتوباً.
 * ٥) دخولُ «تذكّرني» يسجّل الحضور كدخول النموذج.
 */
class AttendanceProvenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // ساعةٌ مجمَّدة: الحزمةُ كانت خضراءَ بعد السادسة مساءً فقط — أوقاتُ «16:00» المزروعة
        // تقع في المستقبل صباحاً أو داخل نافذة الخمول عصراً
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-16 21:00:00', 'Asia/Muscat'));
    }

    private function staff(string $role = 'lawyer'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function open(User $user, int $hoursAgo, ?string $workDate = null): HrAttendance
    {
        return HrAttendance::create([
            'user_id' => $user->id,
            'work_date' => $workDate ?? HrAttendance::today(),
            'check_in_at' => now()->subHours($hoursAgo),
            'status' => 'present',
            'source' => 'auto_login',
        ]);
    }

    private function seenAt(User $user, \Carbon\CarbonInterface $at): void
    {
        DB::table('users')->where('id', $user->id)->update(['last_seen_at' => $at]);
    }

    // ══════════ الأثر ══════════

    /** صفحةٌ يفتحها إنسانٌ تختم الأثر؛ ونبضةُ المزامنة والإبقاءُ على الجلسة لا. */
    public function test_human_requests_stamp_last_seen_and_machine_polls_do_not(): void
    {
        $user = $this->staff();
        $this->assertNull(DB::table('users')->where('id', $user->id)->value('last_seen_at'));

        $this->actingAs($user)->get(route('sync'));
        $this->actingAs($user)->post(route('session.keepalive'));
        $this->assertNull(DB::table('users')->where('id', $user->id)->value('last_seen_at'),
            'نبضةُ الآلة خُتمت نشاطاً بشريّاً');

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->assertNotNull(DB::table('users')->where('id', $user->id)->value('last_seen_at'));
    }

    /** والخروجُ يختم اللحظةَ قبل موت الجلسة — ولا يكتب انصرافاً إلّا إن طُلب. */
    public function test_logout_stamps_the_moment_and_checks_out_only_on_request(): void
    {
        $user = $this->staff();
        $record = $this->open($user, 3);

        $this->actingAs($user)->post(route('logout'));
        $this->assertNotNull(DB::table('users')->where('id', $user->id)->value('last_seen_at'), 'الخروج لم يختم الأثر');
        $this->assertNull($record->fresh()->check_out_at);

        $this->actingAs($user)->post(route('logout'), ['checkout' => '1']);
        $fresh = $record->fresh();
        $this->assertNotNull($fresh->check_out_at, '«تسجيل الانصراف والخروج» لم يسجّل الانصراف');
        $this->assertSame(HrAttendance::CLOSED_BY_BUTTON, $fresh->closed_by);
    }

    /** ونافذةُ الخروج تسأل من سجلُّه مفتوح، ولا تسأل من لا سجلَّ له. */
    public function test_the_logout_dialog_appears_only_with_an_open_record(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertDontSee('data-logout-ask', false);

        $this->open($user, 2);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('data-logout-ask', false)
            ->assertSee('تسجيل الانصراف والخروج')
            ->assertSee('خروجٌ فقط');
    }

    // ══════════ السقفُ والأثر ══════════

    /** السقفُ يقرأ users.last_seen_at لا جدولَ الجلسات الذي يموت بالخروج. */
    public function test_the_cap_uses_the_durable_last_seen(): void
    {
        $user = $this->staff();
        $record = $this->open($user, 11);
        // خرج من النظام قبل ساعتين (أثرٌ دائم) — وجدولُ الجلسات فارغ
        $this->seenAt($user, now()->subHours(2));

        $this->assertSame(1, AttendanceGuard::closeOvertimeRecords());

        $fresh = $record->fresh();
        $this->assertLessThanOrEqual(2, abs($fresh->check_out_at->diffInSeconds(now()->subHours(2))),
            'أُقفل بالسقف رغم أثرٍ بعده');
        $this->assertSame(HrAttendance::CLOSED_BY_SEEN, $fresh->closed_by);
        $this->assertEqualsWithDelta(540, (int) $fresh->minutes, 1);
    }

    /** ونشاطُ اليوم لا يمدّ سجلَّ الاثنين المنسيّ سبعةً وأربعين ساعة. */
    public function test_activity_on_another_day_does_not_extend_an_old_record(): void
    {
        $user = $this->staff();
        $old = $this->open($user, 50, now()->subHours(50)->toDateString());
        $this->seenAt($user, now()->subMinutes(90));

        $this->assertSame(1, AttendanceGuard::closeOvertimeRecords());

        $fresh = $old->fresh();
        $this->assertSame(480, (int) $fresh->minutes, 'نشاطُ اليوم مدّ سجلَّ ما قبل يومين');
        $this->assertSame(HrAttendance::CLOSED_BY_CAP, $fresh->closed_by);
    }

    /** وإقفالٌ آليٌّ لا يتجاوز حدَّ اليوم (السادسة صباحَ الغد). */
    public function test_an_inferred_close_is_bounded_to_the_day(): void
    {
        $user = $this->staff();
        // حضورٌ قبل ٢٦ ساعة على يومِ أمس؛ حدُّ اليوم مضى — يُقفل عليه لا على السقف الذي يقع بعده
        $record = HrAttendance::create([
            'user_id' => $user->id,
            'work_date' => now()->subDay()->toDateString(),
            'check_in_at' => now()->subDay()->setTime(23, 30),
            'status' => 'present',
            'source' => 'auto_login',
        ]);
        $this->assertSame(1, AttendanceGuard::closeOvertimeRecords());

        // الساعةُ مجمَّدة على التاسعة مساءً: السقفُ (07:30) مضى، والحدُّ (06:00) قبله
        $fresh = $record->fresh();
        $this->assertTrue($fresh->check_out_at->equalTo(now()->setTime(AttendanceGuard::DAY_END_HOUR, 0)), 'الإقفالُ الآليّ تجاوز حدَّ اليوم');
        $this->assertSame(390, (int) $fresh->minutes);
        $this->assertSame(HrAttendance::CLOSED_BY_CAP, $fresh->closed_by);
    }

    /** كلُّ إقفالٍ يُدوَّن في سجلّ التدقيق بقديمه وجديده. */
    public function test_every_close_leaves_an_audit_row(): void
    {
        $user = $this->staff();
        $this->open($user, 2);

        AttendanceGuard::checkOut($user);

        $row = AuditLog::where('action', 'attendance_close')->where('model_type', HrAttendance::class)->first();
        $this->assertNotNull($row, 'الإقفالُ بلا سطر تدقيق');
        $this->assertSame(HrAttendance::CLOSED_BY_BUTTON, $row->new_values['closed_by']);
        $this->assertSame($user->id, $row->old_values['employee_id']);
    }

    // ══════════ التصحيح ══════════

    /** الإداري يصحّح بوقتين وسبب: الدقائقُ تُعاد، والوسمُ «مصحَّح»، والقديمُ يبقى مكتوباً. */
    public function test_a_manager_corrects_a_record_and_the_trail_survives(): void
    {
        $admin = $this->staff('admin');
        $lawyer = $this->staff();
        $record = HrAttendance::create([
            'user_id' => $lawyer->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->setTime(7, 31), 'check_out_at' => now()->setTime(13, 36),
            'minutes' => 365, 'status' => 'completed', 'source' => 'auto_login', 'closed_by' => HrAttendance::CLOSED_BY_CAP,
        ]);

        $this->actingAs($admin)->post(route('hr.attendance.correct', $record), [
            'check_in' => '07:31', 'check_out' => '15:57', 'reason' => 'خرج ٣:٥٧ بشهادته والسجلُّ أُقفل بالسقف',
        ])->assertRedirect()->assertSessionHas('success');

        $fresh = $record->fresh();
        $this->assertSame('15:57', $fresh->check_out_at->timezone('Asia/Muscat')->format('H:i'));
        $this->assertSame(506, (int) $fresh->minutes);
        $this->assertSame(HrAttendance::CLOSED_BY_MANAGER, $fresh->closed_by);
        $this->assertSame('مصحَّح', $fresh->inferredLabel());
        $this->assertStringContainsString('01:36 PM', (string) $fresh->note, 'الوقتُ القديم ضاع من الأثر');
        $this->assertStringContainsString('03:57 PM', (string) $fresh->note);
        $this->assertStringContainsString($admin->name, (string) $fresh->note);

        $audit = AuditLog::where('action', 'attendance_correct')->where('model_id', $record->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame(365, (int) $audit->old_values['minutes']);
        $this->assertSame($admin->id, (int) $audit->new_values['by']);
    }

    /** وموظّفٌ لا يصحّح سجلَّ أحد — ولا سجلَّ نفسِه. */
    public function test_staff_cannot_correct_records(): void
    {
        $lawyer = $this->staff();
        $record = HrAttendance::create([
            'user_id' => $lawyer->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->setTime(8, 0), 'check_out_at' => now()->setTime(12, 0),
            'minutes' => 240, 'status' => 'completed', 'source' => 'auto_login', 'closed_by' => HrAttendance::CLOSED_BY_BUTTON,
        ]);

        // الرفضُ في الواجهة تحويلٌ إلى اللوحة برسالة (معالجُ الاستثناءات)، وفي JSON رمزُ 403
        $this->actingAs($lawyer)->postJson(route('hr.attendance.correct', $record), [
            'check_in' => '08:00', 'check_out' => '18:00', 'reason' => 'x',
        ])->assertForbidden();

        $this->assertSame(240, (int) $record->fresh()->minutes);
    }

    /** وانصرافٌ لا يتأخّر عن الحضور يُردّ بسبب، وبلا سببٍ لا تصحيح. */
    public function test_correction_validation(): void
    {
        $admin = $this->staff('admin');
        $record = HrAttendance::create([
            'user_id' => $admin->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->setTime(8, 0), 'status' => 'present', 'source' => 'auto_login',
        ]);

        $this->actingAs($admin)->from(route('hr.index', ['tab' => 'attendance_log']))
            ->post(route('hr.attendance.correct', $record), ['check_in' => '09:00', 'check_out' => '08:30', 'reason' => 'خطأ'])
            ->assertSessionHasErrors('check_out');

        $this->actingAs($admin)->post(route('hr.attendance.correct', $record), ['check_in' => '08:00', 'check_out' => '16:00'])
            ->assertSessionHasErrors('reason');

        $this->assertNull($record->fresh()->check_out_at);
    }

    /** والكشفُ يعرض زرَّ التصحيح للإداري وحده، والوسمَ لكلّ من يراه. */
    public function test_the_log_offers_correction_to_managers_only(): void
    {
        $admin = $this->staff('admin');
        $lawyer = $this->staff();
        HrAttendance::create([
            'user_id' => $lawyer->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->setTime(8, 0), 'check_out_at' => now()->setTime(16, 0),
            'minutes' => 480, 'status' => 'completed', 'source' => 'auto_login', 'closed_by' => HrAttendance::CLOSED_BY_MANAGER,
        ]);

        $this->actingAs($admin)->get(route('hr.index', ['tab' => 'attendance_log']))->assertOk()
            ->assertSee('data-attendance-fix', false)->assertSee('مصحَّح');

        $this->actingAs($lawyer)->get(route('hr.index', ['tab' => 'attendance_log']))->assertOk()
            ->assertDontSee('data-attendance-fix', false)->assertSee('مصحَّح');
    }

    // ══════════ الدخول ══════════

    /** دخولُ «تذكّرني» يطلق حدثَ Login بلا نموذج — ويُسجَّل الحضور مثله. */
    public function test_a_remembered_login_records_attendance(): void
    {
        $user = $this->staff();

        event(new Login('web', $user, true));

        $record = HrAttendance::todayFor($user->id);
        $this->assertNotNull($record, 'دخولُ «تذكّرني» بلا سجلّ حضور');
        $this->assertSame('auto_login', $record->source);
    }

    /** وانصرافٌ ثانٍ على يومٍ مقفَل يقول الحقيقة لا «سُجّل انصرافك». */
    public function test_a_second_checkout_on_a_closed_day_is_not_reported_as_success(): void
    {
        $user = $this->staff();
        $this->open($user, 2);

        $this->actingAs($user)->post(route('hr.attendance.checkout'))->assertSessionHas('success');
        $this->actingAs($user)->post(route('hr.attendance.checkout'))->assertSessionHasErrors('attendance');
    }

    /** وانصرافٌ بعد منتصف الليل يُقال صراحةً «(+1)». */
    public function test_a_checkout_after_midnight_is_labelled(): void
    {
        $user = $this->staff();
        $record = HrAttendance::create([
            'user_id' => $user->id, 'work_date' => now()->subDay()->toDateString(),
            'check_in_at' => now()->subDay()->setTime(22, 0), 'check_out_at' => now()->setTime(0, 30),
            'minutes' => 150, 'status' => 'completed', 'source' => 'auto_login', 'closed_by' => HrAttendance::CLOSED_BY_BUTTON,
        ]);

        $this->assertSame('00:30 (+1)', $record->checkOutDisplay());
    }

    /** وأمرُ القراءة يطبع الكاتبَ لكلّ انصراف بلا أن يكتب شيئاً. */
    public function test_the_read_only_command_shows_who_closed_each_day(): void
    {
        $user = $this->staff();
        $record = HrAttendance::create([
            'user_id' => $user->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->setTime(7, 31), 'check_out_at' => now()->setTime(13, 36),
            'minutes' => 365, 'status' => 'completed', 'source' => 'auto_login', 'closed_by' => HrAttendance::CLOSED_BY_CAP,
        ]);

        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('hr:attendance-log', ['--employee' => (string) $user->id, '--days' => 7]));
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringContainsString('13:36', $output);
        $this->assertStringContainsString('cap', $output);
        $this->assertSame('13:36', $record->fresh()->check_out_at->format('H:i'), 'أمرُ القراءة كتب شيئاً');
    }

    // ══════════ ما كتبه النظامُ يبقى محسوباً ══════════

    /** فترةٌ بلغت السقفَ ثمّ استئنافٌ وإقفالٌ بالزرّ: الثماني المستنتَجةُ تبقى معلَّمةً في المجموع. */
    public function test_inferred_minutes_survive_a_resume_and_a_button_close(): void
    {
        $user = $this->staff();
        $record = $this->open($user, 10);

        $this->assertSame(1, AttendanceGuard::closeOvertimeRecords());
        $this->assertSame(480, (int) $record->fresh()->inferred_minutes);

        AttendanceGuard::resume($user);
        $record->fresh()->update(['resumed_at' => now()->subMinutes(90)]);
        AttendanceGuard::checkOut($user);

        $fresh = $record->fresh();
        $this->assertSame(HrAttendance::CLOSED_BY_BUTTON, $fresh->closed_by);
        $this->assertSame(480, (int) $fresh->inferred_minutes, 'دقائقُ السقف ضاعت من العلامة');
        $this->assertEqualsWithDelta(570, (int) $fresh->minutes, 1);
        $this->assertSame('فترةٌ بالسقف', $fresh->inferredLabel());
        $this->assertTrue($fresh->hasInferredTime());

        // والتصحيحُ يمحو العلامة: وقتان مسجَّلان بيد الإداري
        AttendanceGuard::correct($fresh, $this->staff('admin'), now()->setTime(8, 0), now()->setTime(16, 0), 'بشهادته');
        $this->assertSame(0, (int) $fresh->fresh()->inferred_minutes);
    }

    /** والإقفالُ الليليّ لا يُقفل على من ما زال يكتب قربَ منتصف الليل. */
    public function test_the_nightly_close_skips_someone_still_active(): void
    {
        \App\Models\Setting::set('hr_auto_close', '1');
        $user = $this->staff();
        $record = $this->open($user, 5);
        $this->seenAt($user, now()->subMinutes(5));

        $this->assertSame(0, AttendanceGuard::closeStaleRecords());
        $this->assertNull($record->fresh()->check_out_at);
    }

    // ══════════ الإداري يضيف يوماً ══════════

    /** يومُ محكمةٍ بلا دخول: الإداري يضيفه بوقتين وسبب، ولا يُضاف يومٌ له سجلٌّ أصلاً. */
    public function test_a_manager_adds_a_missing_day_once(): void
    {
        $admin = $this->staff('admin');
        $lawyer = $this->staff();
        $day = now('Asia/Muscat')->subDay()->toDateString();

        $this->actingAs($admin)->post(route('hr.attendance.add'), [
            'employee_id' => $lawyer->id, 'work_date' => $day, 'check_in' => '08:30', 'check_out' => '14:30', 'reason' => 'يومُ محكمة',
        ])->assertRedirect()->assertSessionHas('success');

        $record = HrAttendance::where('user_id', $lawyer->id)->whereDate('work_date', $day)->first();
        $this->assertNotNull($record);
        $this->assertSame(360, (int) $record->minutes);
        $this->assertSame(HrAttendance::CLOSED_BY_MANAGER, $record->closed_by);
        $this->assertStringContainsString('يومُ محكمة', (string) $record->note);
        $this->assertNotNull(AuditLog::where('action', 'attendance_add')->where('model_id', $record->id)->first());

        $this->actingAs($admin)->post(route('hr.attendance.add'), [
            'employee_id' => $lawyer->id, 'work_date' => $day, 'check_in' => '09:00', 'check_out' => '12:00', 'reason' => 'تكرار',
        ])->assertSessionHasErrors('add_check_out');
        $this->assertSame(1, HrAttendance::where('user_id', $lawyer->id)->count());

        $this->actingAs($lawyer)->postJson(route('hr.attendance.add'), [
            'employee_id' => $lawyer->id, 'work_date' => $day, 'check_in' => '09:00', 'check_out' => '12:00', 'reason' => 'x',
        ])->assertForbidden();
    }

    // ══════════ الكشفُ ملفّاً ══════════

    /** التنزيل للإداري وحده، بترميزٍ يفهمه إكسل، وبلا خليّةٍ تُنفَّذ معادلةً. */
    public function test_the_csv_export_is_for_managers_and_neutralises_formulas(): void
    {
        $admin = $this->staff('admin');
        $lawyer = $this->staff();
        $lawyer->update(['name' => '=HYPERLINK("http://x")']);
        HrAttendance::create([
            'user_id' => $lawyer->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->setTime(8, 0), 'check_out_at' => now()->setTime(16, 0),
            'minutes' => 480, 'status' => 'completed', 'source' => 'auto_login', 'closed_by' => HrAttendance::CLOSED_BY_CAP,
        ]);

        $res = $this->actingAs($admin)->get(route('hr.attendance.export', ['range' => 'day']))->assertOk();
        $body = $res->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'بلا BOM يقرأ إكسل العربيّةَ رموزاً');
        $this->assertStringContainsString("'=HYPERLINK", $body, 'اسمٌ يبدأ بـ= يُنفَّذ معادلةً');
        $this->assertStringContainsString('بلغ السقف', $body);
        $this->assertStringContainsString('attachment', (string) $res->headers->get('Content-Disposition'));

        $this->actingAs($lawyer)->getJson(route('hr.attendance.export', ['range' => 'day']))->assertForbidden();
    }

    /** وحذفُ حسابٍ له حضورٌ يُردّ — التعطيلُ يحفظ الكشف. */
    public function test_deleting_a_user_with_attendance_is_refused(): void
    {
        $admin = $this->staff('admin');
        $lawyer = $this->staff();
        $this->open($lawyer, 2);

        $this->actingAs($admin)->delete(route('users.destroy', $lawyer))->assertRedirect();

        $this->assertNotNull(User::find($lawyer->id), 'حُذف الموظّف وحضورُه معه');
        $this->assertSame(1, HrAttendance::where('user_id', $lawyer->id)->count());
    }

    // ══════════ ما ليس نشاطاً بشريّاً ══════════

    /** خروجُ الخمول (المؤقّت) لا يختم أثراً، وقراءةُ XHR (إعادةُ جلب الصفحة) لا تختم. */
    public function test_idle_logout_and_xhr_reads_are_not_human_activity(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->get(route('dashboard'), ['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertNull(DB::table('users')->where('id', $user->id)->value('last_seen_at'), 'إعادةُ جلب الصفحة خُتمت نشاطاً');

        $this->actingAs($user)->get(route('chat.unread'));
        $this->assertNull(DB::table('users')->where('id', $user->id)->value('last_seen_at'), 'نبضةُ المحادثة خُتمت نشاطاً');

        $this->actingAs($user)->post(route('logout'), ['idle' => '1']);
        $this->assertNull(DB::table('users')->where('id', $user->id)->value('last_seen_at'), 'خروجُ الخمول خُتم أثراً بشريّاً');
    }

    /** ودخولُ «تذكّرني» الذي أطلقته نبضةُ آلة يُؤجَّل إلى أوّل طلبٍ بشريّ. */
    public function test_a_login_fired_by_a_machine_request_is_deferred(): void
    {
        $user = $this->staff();

        // نبضةُ مزامنةٍ تُطلق حدثَ الدخول (كعكةُ تذكّرني بعد انتهاء الجلسة)
        $this->actingAs($user)->get(route('sync'));
        $req = \Illuminate\Http\Request::create(route('sync'), 'GET');
        $req->setRouteResolver(fn () => app('router')->getRoutes()->match($req));
        app()->instance('request', $req);
        event(new Login('web', $user, true));

        $this->assertNull(HrAttendance::todayFor($user->id), 'نبضةُ آلةٍ فتحت يومَ حضور');
        $this->assertTrue((bool) session('attendance_login_pending'));

        // أوّلُ صفحةٍ يفتحها إنسان تسجّل الحضورَ المؤجَّل
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->assertNotNull(HrAttendance::todayFor($user->id), 'الحضورُ المؤجَّل لم يُسجَّل');
        $this->assertNull(session('attendance_login_pending'));
    }

    // ══════════ حدودُ التصحيح ══════════

    /** من مُنح إدارةَ الحضور لا يصحّح سجلَّ نفسِه ولا يضيف لنفسه يوماً. */
    public function test_a_delegated_manager_cannot_touch_their_own_record(): void
    {
        $delegate = $this->staff();
        $delegate->givePermission('attendance.manage');
        $own = HrAttendance::create([
            'user_id' => $delegate->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->setTime(8, 0), 'check_out_at' => now()->setTime(12, 0),
            'minutes' => 240, 'status' => 'completed', 'source' => 'auto_login', 'closed_by' => HrAttendance::CLOSED_BY_CAP,
        ]);

        $this->actingAs($delegate)->postJson(route('hr.attendance.correct', $own), [
            'check_in' => '08:00', 'check_out' => '17:00', 'reason' => 'x',
        ])->assertForbidden();
        $this->assertSame(240, (int) $own->fresh()->minutes);

        $this->actingAs($delegate)->postJson(route('hr.attendance.add'), [
            'employee_id' => $delegate->id, 'work_date' => now('Asia/Muscat')->subDay()->toDateString(),
            'check_in' => '08:00', 'check_out' => '16:00', 'reason' => 'x',
        ])->assertForbidden();

        // لكنّه يصحّح لزميله
        $other = $this->staff();
        $row = HrAttendance::create([
            'user_id' => $other->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->setTime(8, 0), 'check_out_at' => now()->setTime(12, 0),
            'minutes' => 240, 'status' => 'completed', 'source' => 'auto_login', 'closed_by' => HrAttendance::CLOSED_BY_CAP,
        ]);
        $this->actingAs($delegate)->post(route('hr.attendance.correct', $row), [
            'check_in' => '08:00', 'check_out' => '15:00', 'reason' => 'بشهادته',
        ])->assertSessionHas('success');
        $this->assertSame(420, (int) $row->fresh()->minutes);
    }

    /** وانصرافٌ لم يقع بعد لا يُكتب — ولا بيد الإداري. */
    public function test_a_future_departure_is_refused(): void
    {
        $admin = $this->staff('admin');
        $lawyer = $this->staff();
        $record = HrAttendance::create([
            'user_id' => $lawyer->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->setTime(8, 0), 'status' => 'present', 'source' => 'auto_login',
        ]);

        // الساعةُ التاسعة مساءً — الحاديةَ عشرةَ لم تقع بعد
        $this->actingAs($admin)->post(route('hr.attendance.correct', $record), [
            'check_in' => '08:00', 'check_out' => '23:00', 'reason' => 'x',
        ])->assertSessionHasErrors('check_out');
        $this->assertNull($record->fresh()->check_out_at);

        $this->actingAs($admin)->post(route('hr.attendance.add'), [
            'employee_id' => $lawyer->id, 'work_date' => now('Asia/Muscat')->toDateString(),
            'check_in' => '20:00', 'check_out' => '23:30', 'reason' => 'x',
        ])->assertSessionHasErrors('add_check_out');
    }

    /** ليلةُ عمل: انصرافٌ في اليوم التالي يُصحَّح بعلامته لا يُردّ «قبل الحضور». */
    public function test_a_next_day_departure_can_be_corrected(): void
    {
        $admin = $this->staff('admin');
        $lawyer = $this->staff();
        $record = HrAttendance::create([
            'user_id' => $lawyer->id, 'work_date' => now()->subDay()->toDateString(),
            'check_in_at' => now()->subDay()->setTime(22, 0), 'check_out_at' => now()->setTime(1, 30),
            'minutes' => 210, 'status' => 'completed', 'source' => 'auto_login', 'closed_by' => HrAttendance::CLOSED_BY_CAP,
        ]);

        $this->actingAs($admin)->post(route('hr.attendance.correct', $record), [
            'check_in' => '22:00', 'check_out' => '00:15', 'next_day' => '1', 'reason' => 'خرج بعد منتصف الليل بربع ساعة',
        ])->assertSessionHas('success');

        $fresh = $record->fresh();
        $this->assertSame('00:15 (+1)', $fresh->checkOutDisplay());
        $this->assertSame(135, (int) $fresh->minutes);
    }

    /** ومن صحّح الإداري يومَه لا يفتحه فوق التصحيح. */
    public function test_a_manager_corrected_day_is_not_resumable(): void
    {
        $lawyer = $this->staff();
        $record = HrAttendance::create([
            'user_id' => $lawyer->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->setTime(8, 0), 'check_out_at' => now()->subHour(),
            'minutes' => 720, 'status' => 'completed', 'source' => 'auto_login', 'closed_by' => HrAttendance::CLOSED_BY_MANAGER,
        ]);

        $this->assertFalse(AttendanceGuard::resumable($record));
        $this->actingAs($lawyer)->post(route('hr.attendance.resume'))->assertSessionHasErrors('attendance');
        $this->assertNotNull($record->fresh()->check_out_at);
        $this->actingAs($lawyer)->get(route('dashboard'))->assertOk()->assertDontSee('data-attendance-resume', false);
    }

    /** وبعد ضغط الانصراف لا يُسأل «أما زلت في دوامك؟» في الشاشة نفسِها. */
    public function test_the_resume_banner_does_not_nag_right_after_the_button(): void
    {
        $user = $this->staff();
        $this->open($user, 3);

        $this->actingAs($user)->post(route('hr.attendance.checkout'))->assertRedirect();
        $this->actingAs($user)->get(route('cases.index'))->assertOk()->assertDontSee('data-attendance-resume', false);
    }

    // ══════════ الكشفُ والوحدة ══════════

    /** مرشّحا «بلا انصراف» و«مستنتَج» — ومعهما الصفوفُ القديمة الموسومة بالمصدر وحده. */
    public function test_the_unclosed_and_inferred_filters(): void
    {
        // الأسماءُ تظهر في قائمة التصفية أيّاً كان المرشّح — فالتمييزُ بأوقاتٍ لا بأسماء
        $admin = $this->staff('admin');
        $a = $this->staff(); $b = $this->staff(); $c = $this->staff();
        $day = now()->subDay();
        HrAttendance::create(['user_id' => $a->id, 'work_date' => $day->toDateString(), 'check_in_at' => $day->copy()->setTime(8, 1), 'status' => 'present', 'source' => 'auto_login']);
        HrAttendance::create(['user_id' => $b->id, 'work_date' => $day->toDateString(), 'check_in_at' => $day->copy()->setTime(8, 2), 'check_out_at' => $day->copy()->setTime(16, 0), 'minutes' => 480, 'status' => 'completed', 'source' => 'auto_capped', 'closed_by' => null]);
        HrAttendance::create(['user_id' => $c->id, 'work_date' => $day->toDateString(), 'check_in_at' => $day->copy()->setTime(8, 3), 'check_out_at' => $day->copy()->setTime(15, 0), 'minutes' => 420, 'status' => 'completed', 'source' => 'auto_login', 'closed_by' => HrAttendance::CLOSED_BY_BUTTON]);

        $this->actingAs($admin)->get(route('hr.index', ['tab' => 'attendance_log', 'range' => 'week', 'status' => 'unclosed']))
            ->assertOk()->assertSee('08:01 AM')->assertDontSee('08:02 AM')->assertDontSee('08:03 AM');

        // والقديمُ الموسومُ بالمصدر وحده (قبل عمود الكاتب) يبقى في «مستنتَج»
        $this->actingAs($admin)->get(route('hr.index', ['tab' => 'attendance_log', 'range' => 'week', 'status' => 'inferred']))
            ->assertOk()->assertSee('08:02 AM')->assertDontSee('08:03 AM')->assertDontSee('08:01 AM');
    }

    /** ووحدةُ الموارد البشريّة المعطَّلة تعطّل الحضورَ التلقائيّ وإشعاراتِه وفحصَه. */
    public function test_a_disabled_hr_feature_disables_attendance(): void
    {
        \App\Models\Setting::set('feature_hr', '1');
        $user = $this->staff();

        $this->assertFalse(AttendanceGuard::autoEnabled());
        event(new Login('web', $user, false));
        $this->assertNull(HrAttendance::todayFor($user->id), 'وحدةٌ معطَّلة سجّلت حضوراً لا يراه أحد');

        \Illuminate\Support\Facades\Artisan::call('office:health');
        $this->assertStringNotContainsString('مسحةُ السقف', \Illuminate\Support\Facades\Artisan::output());
    }

    /** وهجرةُ التوثيق تُترجم المصادرَ القديمة بثوابت النموذج — لا بنصوصٍ لا يعرفها. */
    public function test_the_provenance_backfill_uses_known_values(): void
    {
        $user = $this->staff();
        $nightly = HrAttendance::create(['user_id' => $user->id, 'work_date' => now()->subDays(3)->toDateString(), 'check_in_at' => now()->subDays(3)->setTime(8, 0), 'check_out_at' => now()->subDays(3)->setTime(16, 0), 'minutes' => 480, 'status' => 'completed', 'source' => 'auto_closed']);
        $legacy = HrAttendance::create(['user_id' => $user->id, 'work_date' => now()->subDays(2)->toDateString(), 'check_in_at' => now()->subDays(2)->setTime(8, 0), 'check_out_at' => now()->subDays(2)->setTime(13, 36), 'minutes' => 336, 'status' => 'completed', 'source' => 'auto_login']);
        DB::table('hr_attendance')->whereIn('id', [$nightly->id, $legacy->id])->update(['closed_by' => null]);
        // خروجٌ في الثانية نفسِها التي كُتب فيها انصرافُ 13:36 — بصمةُ زرّ الخروج القديم.
        // ‏created_at لا يُملأ بالإسناد الجماعيّ، فيُكتب مباشرةً على الجدول.
        $log = AuditLog::create(['user_id' => $user->id, 'action' => 'logout']);
        DB::table('audit_logs')->where('id', $log->id)
            ->update(['created_at' => $legacy->check_out_at->copy()->addSeconds(1)]);

        $migration = require database_path('migrations/2026_09_16_000200_add_closed_by_to_hr_attendance.php');
        $migration->up();

        $this->assertSame(HrAttendance::CLOSED_BY_SEEN, $nightly->fresh()->closed_by);
        $this->assertTrue($nightly->fresh()->closedByInference());
        $this->assertSame(HrAttendance::CLOSED_BY_LOGOUT, $legacy->fresh()->closed_by);
        $this->assertSame('زرّ الخروج (قديم)', $legacy->fresh()->inferredLabel());
    }

    // ══════════ دخولٌ عابر لا دوام ══════════

    /**
     * «لستُ في دوامي»: يُلغى حضورٌ تلقائيٌّ حديثٌ فحسب — ولا يُحذف عملٌ وقع.
     *
     * محامٍ دخل من بيته ليلاً ليقرأ ملفّاً كان يُفتح له يومٌ يبلغ السقفَ
     * صباحاً بثماني ساعاتٍ لم يعملها.
     */
    public function test_a_passing_login_can_be_cancelled_but_real_work_cannot(): void
    {
        $user = $this->staff();
        event(new Login('web', $user, false));
        $record = HrAttendance::todayFor($user->id);
        $this->assertNotNull($record);

        // الإشعارُ يعرضه، والإلغاءُ يحذفه ويُدوَّن
        $this->actingAs($user)->get(route('cases.index'))->assertOk()->assertSee('لستُ في دوامي');
        $this->actingAs($user)->post(route('hr.attendance.cancel'))->assertRedirect()->assertSessionHas('success');
        $this->assertNull(HrAttendance::todayFor($user->id), 'لم يُلغَ الحضورُ العابر');
        $this->assertNotNull(AuditLog::where('action', 'attendance_cancel')->first());

        // وحضورٌ مضى عليه أكثرُ من نصف ساعة عملٌ لا يُمحى
        $old = HrAttendance::create([
            'user_id' => $user->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->subHours(3), 'status' => 'present', 'source' => 'auto_login',
        ]);
        $this->assertFalse(AttendanceGuard::cancellable($old));
        $this->actingAs($user)->post(route('hr.attendance.cancel'))->assertSessionHas('error');
        $this->assertNotNull($old->fresh(), 'حُذف حضورٌ مضى عليه ثلاثُ ساعات');

        // ولا يُمحى ما ضغط صاحبُه حضورَه بيده
        $old->update(['check_in_at' => now()->subMinutes(5), 'source' => 'manual']);
        $this->assertFalse(AttendanceGuard::cancellable($old->fresh()));
    }
}
