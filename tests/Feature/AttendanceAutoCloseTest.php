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

    /** آخر نشاطٍ للموظّف في جدول جلسات لارافيل. */
    private function seeUserAt(User $user, string $time): void
    {
        DB::table('sessions')->insert([
            'id' => 'sess-' . $user->id . '-' . uniqid(),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->setTimeFromTimeString($time)->timestamp,
        ]);
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
     * ومن لا أثرَ لجلسته يُقفل سجلُّه على حضوره بصفر دقيقة.
     *
     * رقمٌ ظاهرُ الخطأ يُراجَع، خيرٌ من رقمٍ مخترَعٍ يُصدَّق ويدخل الرواتب.
     */
    public function test_a_record_with_no_session_trace_closes_at_check_in(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, '09:00');

        AttendanceGuard::closeStaleRecords();

        $fresh = $record->fresh();
        $this->assertSame('09:00', $fresh->check_out_at->format('H:i'));
        $this->assertSame(0, $fresh->minutes);
    }

    /** والانصراف لا يسبق الحضور مهما قال جدول الجلسات. */
    public function test_checkout_never_precedes_check_in(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, '13:00');
        $this->seeUserAt($user, '07:00');

        AttendanceGuard::closeStaleRecords();

        $fresh = $record->fresh();
        $this->assertTrue(
            $fresh->check_out_at->greaterThanOrEqualTo($fresh->check_in_at),
            'الانصراف قبل الحضور',
        );
        $this->assertGreaterThanOrEqual(0, $fresh->minutes);
    }

    /** والسجلّ يُوسم فيعرف المكتب أن الوقت مستنتَجٌ لا مسجَّل. */
    public function test_an_auto_closed_record_is_marked_as_such(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user);
        $this->seeUserAt($user, '16:00');

        AttendanceGuard::closeStaleRecords();

        $this->assertSame('auto_closed', $record->fresh()->source);
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

    /** والأمر المجدول يُشغّل المنطق نفسه. */
    public function test_the_scheduled_command_closes_records(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user);
        $this->seeUserAt($user, '16:00');

        $this->artisan('hr:close-attendance')->assertSuccessful();

        $this->assertNotNull($record->fresh()->check_out_at);
    }
}
