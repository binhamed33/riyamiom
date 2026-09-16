<?php

namespace Tests\Feature;

use App\Models\HrAttendance;
use App\Models\User;
use App\Support\AttendanceGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * الانصراف يُسجَّل ولو لم يضغط الموظّف «تسجيل خروج».
 *
 * ═══ العطل الذي وُضع له ═══
 *
 * الحضور يُسجَّل تلقائياً عند الدخول، والانصراف لا يُسجَّل إلا بضغط زرٍّ.
 * والموظّف يُغلق المتصفّح ويمضي — وهو الغالب — فيبقى `check_out_at` فارغاً
 * أبداً: لا انصرافَ ولا دقائقَ محسوبة، وسِجلُّ الشهر أعمدةٌ خالية بينما
 * الموظّف داومَ كلّ يوم.
 *
 * ولا شيء في هذا يُخطئ: الحضور صحيح، والصفحة تُعرض، والحزمة خضراء. لا
 * يظهر إلا حين يفتح المديرُ سجلَّ الشهر فيجده فارغاً من الانصراف.
 */
class AttendanceAutoCloseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // ساعةٌ مجمَّدة: الحزمةُ كانت خضراءَ بعد السادسة مساءً فقط — أوقاتُ «16:00» المزروعة
        // تقع في المستقبل صباحاً أو داخل نافذة الخمول عصراً
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-16 21:00:00', 'Asia/Muscat'));
        // هذه الحزمة تختبر سلوك الإقفال «حين يُفعَّل». وهو معطَّل
        // افتراضاً بناءً على اقتراح محامٍ: وقتُ آخر نقرةٍ ليس وقتَ
        // انصراف، والانصراف بزرّه وحده — انظر AttendanceOnlyByButtonTest.
        \App\Models\Setting::set('hr_auto_close', '1');
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => 'staff', 'is_active' => true]);
    }

    /** حضورٌ مفتوح بلا انصراف — كما يتركه من أغلق المتصفّح. */
    private function openRecord(User $user, string $checkIn = '08:00'): HrAttendance
    {
        return HrAttendance::create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'check_in_at' => now()->setTimeFromTimeString($checkIn),
            'status' => 'present',
            'source' => 'login',
        ]);
    }

    /**
     * آخر نشاطٍ بشريٍّ معروف للموظّف.
     *
     * عمودٌ دائم لا صفُّ جلسة: ذاك يُحذف بالخروج ويُكنَس بعد ساعتين وتجدّده
     * نبضةُ المزامنة بلا إنسان — فكان يُقرأ حضوراً لمن غادر.
     */
    private function seeUserAt(User $user, string $time): void
    {
        DB::table('users')->where('id', $user->id)
            ->update(['last_seen_at' => now()->setTimeFromTimeString($time)]);
    }

    public function test_an_open_record_is_closed_instead_of_staying_empty_forever(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user);
        $this->seeUserAt($user, '16:00');

        $closed = AttendanceGuard::closeStaleRecords();

        $this->assertSame(1, $closed);
        $this->assertNotNull($record->fresh()->check_out_at, 'بقي السجلّ بلا انصراف');
        $this->assertSame('completed', $record->fresh()->status);
    }

    /**
     * ووقتُ الانصراف هو آخر نشاطٍ حقيقيّ، لا ساعةُ تشغيل الأمر.
     *
     * الأمر يعمل قرابة منتصف الليل. فلو كُتب وقتُه لصار كلُّ موظّفٍ في
     * المكتب منصرفاً الحادية عشرة والنصف مساءً — رقمٌ يدخل كشف الرواتب.
     */
    public function test_the_checkout_time_is_the_last_real_activity(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, '08:00');
        $this->seeUserAt($user, '15:30');

        AttendanceGuard::closeStaleRecords();

        $out = $record->fresh()->check_out_at;
        $this->assertSame('15:30', $out->format('H:i'), 'كُتب وقتٌ غير آخر نشاط');
        $this->assertSame(450, $record->fresh()->minutes, 'الدقائق لا تطابق الفارق');
    }

    /** وأحدثُ جلسةٍ هي المعتبرة حين تعدّدت أجهزتُه. */
    public function test_the_latest_session_wins_across_devices(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, '08:00');
        $this->seeUserAt($user, '11:00');
        $this->seeUserAt($user, '17:15');

        AttendanceGuard::closeStaleRecords();

        $this->assertSame('17:15', $record->fresh()->check_out_at->format('H:i'));
    }

    /**
     * ومن لا أثرَ له يُترك مفتوحاً — لا يُقفل على حضوره بصفر دقيقة.
     *
     * صفُّ الجلسة يُكنَس بعد ساعتين، فكان كلُّ من نسي الزرَّ ولم يُرَ
     * بعد الثامنة يُقفل بصفر دقيقة: يومُ عملٍ كامل يُقرأ «0 د» في كشف
     * الشهر ويُصدَّق. «بلا انصراف» صدقٌ يُصحَّحه الإداري، وسقفُ اليوم
     * يقفله في مسحته إن لم يُصحَّح.
     */
    public function test_a_record_with_no_trace_is_left_open_for_the_manager(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, '09:00');

        $this->assertSame(0, AttendanceGuard::closeStaleRecords());
        $this->assertNull($record->fresh()->check_out_at, 'أُقفل بلا أثرٍ بصفر دقيقة');
    }

    /** ونشاطٌ قبل الحضور ليس أثراً لهذا اليوم — فيبقى مفتوحاً لا مقفَلاً بصفر. */
    public function test_activity_before_check_in_is_no_trace(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, '13:00');
        $this->seeUserAt($user, '07:00');

        $this->assertSame(0, AttendanceGuard::closeStaleRecords());
        $this->assertNull($record->fresh()->check_out_at);
    }

    /**
     * ونشاطُ يومٍ آخر لا يُقفل به سجلٌّ قديم.
     *
     * الإداري يشغّل `--date` ليومٍ فات والموظّفُ داخلٌ الآن: كان آخرُ
     * نشاطٍ (الآن) يُكتب انصرافاً لذلك اليوم فتخرج آلافُ الدقائق.
     */
    public function test_another_days_activity_never_closes_an_old_record(): void
    {
        $user = $this->staff();
        $old = HrAttendance::create([
            'user_id' => $user->id,
            'work_date' => now()->subDays(3)->toDateString(),
            'check_in_at' => now()->subDays(3)->setTime(8, 0),
            'status' => 'present',
            'source' => 'login',
        ]);
        DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()]);

        $this->assertSame(0, AttendanceGuard::closeStaleRecords(now()->subDays(3), force: true));
        $this->assertNull($old->fresh()->check_out_at, 'نشاطُ اليوم أُقفل به سجلُّ ما قبل ثلاثة أيام');
    }

    /** والسجلّ يُوسم بكاتبه فيعرف المكتب أن الوقت مستنتَجٌ لا مسجَّل. */
    public function test_an_auto_closed_record_is_marked_as_such(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user);
        $this->seeUserAt($user, '16:00');

        AttendanceGuard::closeStaleRecords();

        $this->assertSame(HrAttendance::CLOSED_BY_SEEN, $record->fresh()->closed_by);
        $this->assertSame('آخر نشاط', $record->fresh()->inferredLabel());
        $this->assertStringContainsString('أُقفل بآخر نشاط', (string) $record->fresh()->note);
    }

    /** وسجلٌّ أُقفل بالفعل لا يُمسّ. */
    public function test_a_closed_record_is_left_alone(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, '08:00');
        $record->update([
            'check_out_at' => now()->setTimeFromTimeString('14:00'),
            'minutes' => 360,
            'status' => 'completed',
            'source' => 'logout',
        ]);
        $this->seeUserAt($user, '20:00');

        $this->assertSame(0, AttendanceGuard::closeStaleRecords());
        $this->assertSame('14:00', $record->fresh()->check_out_at->format('H:i'));
        $this->assertSame('logout', $record->fresh()->source);
    }

    /** والأمر المجدول يُشغّل المنطق نفسه — الإقفالُ الليليّ لا السقف (الحضورُ الثالثة، فالسقفُ لم يُبلغ). */
    public function test_the_scheduled_command_closes_records(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, '15:00');
        $this->seeUserAt($user, '16:00');

        $this->artisan('hr:close-attendance')->assertSuccessful();

        $fresh = $record->fresh();
        $this->assertNotNull($fresh->check_out_at);
        $this->assertSame(HrAttendance::CLOSED_BY_SEEN, $fresh->closed_by, 'أقفله السقفُ لا الإقفالُ الليليّ');
        $this->assertSame('16:00', $fresh->check_out_at->format('H:i'));
    }
}
