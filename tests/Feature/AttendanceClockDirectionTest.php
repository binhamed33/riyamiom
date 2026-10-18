<?php

namespace Tests\Feature;

use App\Models\HrAttendance;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * وقتُ الحضور يُقرأ «07:58 ص» لا «07:58 AM» ولا «ص 07:58».
 *
 * ═══ ما رصدته البروفة ═══
 *
 * ١) إشعارُ ما بعد الدخول يكتب «تم تسجيل حضورك اليوم الساعة 01:22 AM»:
 *    المستمعُ والوسيطُ يخزّنان الوقتَ بـformat('h:i A')، ورسائلُ الموارد
 *    البشريّة كذلك.
 * ٢) ما تُرجم منها إلى «ص/م» بقي ملفوفاً بـ<span dir="ltr">، فصار شريطُ
 *    كلِّ صفحة «تم تسجيل حضورك الساعة ص 07:58.» — الحرفُ العربيّ قبل الرقم.
 */
class AttendanceClockDirectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        Carbon::setTestNow(Carbon::parse('2026-10-18 07:58:00', 'Asia/Muscat'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** لا «ص 07:58» ولا «07:58 AM» في أيّ موضعٍ من الصفحة. */
    private function assertArabicClock(string $html, string $clock): void
    {
        $this->assertStringContainsString($clock, $html);
        $this->assertStringNotContainsString('dir="ltr">' . $clock, $html, 'الوقتُ العربيّ ملفوفٌ بـdir="ltr" فيُقرأ «ص 07:58»');
        $this->assertDoesNotMatchRegularExpression('/\d{2}:\d{2} (AM|PM)/', $html);
    }

    public function test_the_login_toast_says_the_time_in_arabic_and_in_reading_order(): void
    {
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'locale' => 'ar', 'password' => Hash::make('secret-pass-123')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-123'])->assertRedirect();

        $at = session('attendance_flash')['at'] ?? null;
        $this->assertSame('07:58 ص', $at, 'الوقتُ المخزَّن للإشعار ما زال AM/PM');

        $html = $this->get(route('cases.index'))->assertOk()->getContent();
        $this->assertStringContainsString('تم تسجيل حضورك اليوم الساعة <bdi>07:58 ص</bdi>', $html);
        $this->assertArabicClock($html, '07:58 ص');
    }

    public function test_the_still_present_banner_does_not_flip_the_meridiem(): void
    {
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'locale' => 'ar']);
        HrAttendance::create(['user_id' => $user->id, 'work_date' => HrAttendance::today(), 'check_in_at' => now()->subMinutes(3), 'status' => 'present', 'source' => 'auto_login']);

        $html = $this->actingAs($user)->get(route('cases.index'))->assertOk()->getContent();

        $this->assertStringContainsString('أنت مسجّل حضور حاليًا', $html);
        $this->assertArabicClock($html, '07:55 ص');
    }

    public function test_the_hr_messages_name_the_times_in_arabic(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 18:00:00', 'Asia/Muscat'));
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'locale' => 'ar']);
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'locale' => 'ar', 'name' => 'سالم']);

        $this->actingAs($admin)->post(route('hr.attendance.add'), [
            'employee_id' => $lawyer->id, 'work_date' => '2026-10-18',
            'check_in' => '08:00', 'check_out' => '16:00', 'reason' => 'نسي الدخول',
        ])->assertSessionHas('success', 'أُضيف يومُ سالم: 08:00 ص ← 04:00 م.');

        $record = HrAttendance::where('user_id', $lawyer->id)->firstOrFail();
        $this->actingAs($admin)->post(route('hr.attendance.correct', $record), [
            'check_in' => '08:30', 'check_out' => '16:30', 'reason' => 'تصحيح',
        ])->assertSessionHas('success', fn ($m) => str_starts_with($m, 'صُحّح السجلّ: 08:30 ص ← 04:30 م'));
    }

    public function test_a_second_checkout_names_the_saved_time_in_arabic(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 16:00:00', 'Asia/Muscat'));
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'locale' => 'ar']);
        HrAttendance::create([
            'user_id' => $user->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->subHours(7), 'check_out_at' => now()->subHour(), 'minutes' => 360,
            'status' => 'completed', 'source' => 'auto_login', 'closed_by' => HrAttendance::CLOSED_BY_BUTTON,
        ]);

        $this->actingAs($user)->post(route('hr.attendance.checkout'))
            ->assertSessionHasErrors(['attendance' => 'سجلُّ اليوم مقفَلٌ منذ 03:00 م — «استئناف الدوام» يفتحه إن كنت ما زلت في دوامك.']);
    }
}
