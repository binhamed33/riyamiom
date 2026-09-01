<?php

namespace Tests\Feature;

use App\Models\HrAttendance;
use App\Models\Setting;
use App\Models\User;
use App\Support\AttendanceGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * سقفُ المناوبة: الانصرافُ بزرّه، وثماني ساعاتٍ حدٌّ لا يُتجاوز.
 *
 * ═══ القاعدتان معاً ═══
 *
 * ١) لا يُكتب انصرافٌ من غياب النشاط. المحامي يكتب ويقابل موكّليه
 *    بعيداً عن الشاشة، وآخرُ نقرةٍ له ليست لحظةَ انصرافه. وهذا باقٍ.
 *
 * ٢) لكنّ السجلَّ لا يبقى مفتوحاً إلى الأبد. من أغلق المتصفّح ومضى
 *    كان سجلُّه يُعرض «لم يُسجَّل» ويظهر صاحبُه «حاضراً» أياماً. فبعد
 *    ثماني ساعاتٍ من الحضور يُقفل على «حضورٌ + ثماني» — حدٌّ معلومٌ
 *    مقدَّماً، لا تخمينٌ من نقرة.
 *
 * والوسمُ `auto_capped` يفصل بينهما في الكشف: وقتٌ بلغ السقف، لا
 * وقتٌ ضغطه صاحبُه.
 */
class AttendanceShiftCapTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
    }

    private function openRecord(User $user, int $hoursAgo): HrAttendance
    {
        return HrAttendance::create([
            'user_id' => $user->id,
            'work_date' => now()->subHours($hoursAgo)->toDateString(),
            'check_in_at' => now()->subHours($hoursAgo),
            'status' => 'present',
            'source' => 'auto_login',
        ]);
    }

    // ══════════ السقف ══════════

    /** تسعُ ساعاتٍ بلا انصراف: يُقفل على الثامنة لا على الآن. */
    public function test_a_record_past_the_cap_is_closed_at_check_in_plus_eight(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, 9);

        $this->assertSame(1, AttendanceGuard::closeOvertimeRecords());

        $record->refresh();

        $this->assertNotNull($record->check_out_at);
        $this->assertSame(480, (int) $record->minutes, 'الدقائقُ ليست ثماني ساعات');
        $this->assertSame('auto_capped', $record->source);
        $this->assertSame('completed', $record->status);

        // ولا يُكتب «الآن»: الوقتُ محسوبٌ من الحضور
        $this->assertTrue(
            $record->check_out_at->equalTo($record->check_in_at->copy()->addHours(8)),
            'وقتُ الانصراف ليس حضوراً + ثماني ساعات',
        );
    }

    /** وثلاثُ ساعاتٍ لا تُمسّ: الموظّفُ في دوامه. */
    public function test_a_record_within_the_cap_is_untouched(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, 3);

        $this->assertSame(0, AttendanceGuard::closeOvertimeRecords());
        $this->assertNull($record->refresh()->check_out_at);
    }

    /** ومن ضغط الانصراف بيده لا يُعاد إقفاله. */
    public function test_a_closed_record_is_never_reclosed(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, 12);

        $record->update([
            'check_out_at' => $record->check_in_at->copy()->addHours(2),
            'minutes' => 120,
            'status' => 'completed',
            'source' => 'manual',
        ]);

        $this->assertSame(0, AttendanceGuard::closeOvertimeRecords());

        $record->refresh();
        $this->assertSame(120, (int) $record->minutes);
        $this->assertSame('manual', $record->source);
    }

    /**
     * والسقفُ لا يحتاج إذنَ الإقفال الليليّ.
     *
     * ذاك يخترع وقتاً من آخر نقرة فعُطِّل بطلب المكاتب، وهذا حدٌّ
     * معلومٌ مقدَّماً — فربطُهما بمفتاحٍ واحد يُبقي السجلّات مفتوحة
     * إلى الأبد في كل مكتبٍ رفض الأوّل.
     */
    public function test_the_cap_does_not_depend_on_the_nightly_option(): void
    {
        Setting::set('hr_auto_close', '0');
        $user = $this->staff();
        $this->openRecord($user, 10);

        $this->assertFalse(AttendanceGuard::autoCloseEnabled());
        $this->assertSame(1, AttendanceGuard::closeOvertimeRecords());
    }

    /** والسجلُّ المفتوح منذ يومين يُقفل هو أيضاً. */
    public function test_a_record_left_open_since_days_is_closed(): void
    {
        $user = $this->staff();
        $record = $this->openRecord($user, 50);

        AttendanceGuard::closeOvertimeRecords();

        $this->assertSame(480, (int) $record->refresh()->minutes);
    }

    // ══════════ ضبط السقف ══════════

    /** المكتبُ يبدّل السقف، والقيمُ المستحيلة تُحبَس. */
    public function test_the_cap_is_configurable_and_bounded(): void
    {
        $this->assertSame(8, AttendanceGuard::capHours());

        Setting::set('hr_shift_cap_hours', '10');
        $this->assertSame(10, AttendanceGuard::capHours());

        // صفرٌ يُقفل كلَّ سجلٍّ لحظةَ فتحه، وثمانٍ وأربعون لا تُقفل شيئاً
        Setting::set('hr_shift_cap_hours', '0');
        $this->assertSame(1, AttendanceGuard::capHours());

        Setting::set('hr_shift_cap_hours', '48');
        $this->assertSame(24, AttendanceGuard::capHours());
    }

    /** والأمرُ المجدوَل يمسح السقفَ ولو كان الإقفال الليليّ معطَّلاً. */
    public function test_the_scheduled_command_sweeps_the_cap(): void
    {
        Setting::set('hr_auto_close', '0');
        $user = $this->staff();
        $record = $this->openRecord($user, 9);

        $this->artisan('hr:close-attendance --cap')->assertSuccessful();

        $this->assertSame('auto_capped', $record->refresh()->source);
    }

    // ══════════ الخمول ══════════

    /**
     * مهلةُ الخمول ساعةٌ لا عشرُ دقائق.
     *
     * «المحامي يقرأ ملفّاً أو يقابل موكّلاً والشاشةُ مفتوحة» — فيعود
     * فيجد نفسَه على وشك الخروج. ورقمٌ في ملفّ عرضٍ يُبدَّل بلا انتباه،
     * فيُحرَس هنا.
     */
    public function test_the_idle_window_is_one_hour_and_stays_under_the_session_lifetime(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertMatchesRegularExpression(
            '/var TIMEOUT = 60 \* 60 \* 1000;/',
            $layout,
            'مهلةُ الخمول ليست ساعة',
        );

        // ومهلةُ الخمول دون عمر الجلسة: لو تجاوزته لسقط المستخدم في
        // فراغٍ بين المهلتين — جلسةٌ ميتةٌ ونافذةٌ لم تظهر بعد
        $this->assertLessThan(
            (int) config('session.lifetime'),
            60 + 1,
            'مهلةُ الخمول بلغت عمرَ الجلسة',
        );
    }
}
