<?php

namespace Tests\Feature;

use App\Models\HrAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * دورة الحضور من الدخول إلى الانصراف — TEST 1 إلى 4 في المواصفة.
 *
 * كلّها عبر HTTP لا بنداءٍ مباشر على الدوالّ: ما يهمّ هو ما يقع حين
 * يسجّل موظفٌ حقيقي دخوله، لا ما تفعله دالّةٌ تُنادى في فراغ.
 */
class AttendanceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $attrs = []): User
    {
        return User::factory()->create($attrs + [
            'role' => 'staff',
            'is_active' => true,
            'password' => Hash::make('secret-pass-123'),
        ]);
    }

    private function login(User $user): \Illuminate\Testing\TestResponse
    {
        return $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-pass-123',
        ]);
    }

    /** TEST 1 — دخولٌ أول يُنشئ حضور اليوم. */
    public function test_login_creates_todays_attendance(): void
    {
        $user = $this->staff();

        $this->assertSame(0, HrAttendance::count());

        $this->login($user)->assertRedirect();

        $record = HrAttendance::where('user_id', $user->id)->first();

        $this->assertNotNull($record, 'الدخول لم يُنشئ سجلّ حضور');
        $this->assertSame(HrAttendance::today(), $record->work_date->toDateString());
        $this->assertNotNull($record->check_in_at);
        $this->assertNull($record->check_out_at);
        $this->assertSame('present', $record->status);
        $this->assertSame('auto_login', $record->source);
    }

    /** TEST 2 — الدخول ثانيةً في اليوم نفسه لا يُنشئ سجلاً آخر. */
    public function test_second_login_same_day_creates_no_new_record(): void
    {
        $user = $this->staff();

        $this->login($user);
        $first = HrAttendance::where('user_id', $user->id)->firstOrFail();

        $this->post('/logout');
        $this->login($user);

        $this->assertSame(1, HrAttendance::where('user_id', $user->id)->count());
        $this->assertSame($first->id, HrAttendance::where('user_id', $user->id)->firstOrFail()->id);
        // ووقت الحضور الأصلي لم يُستبدل بوقت الدخول الثاني
        $this->assertEquals(
            $first->check_in_at->timestamp,
            HrAttendance::find($first->id)->check_in_at->timestamp
        );
    }

    /** TEST 3 — «استمرار الحضور» يُبقي السجلّ مفتوحاً. */
    public function test_keep_present_leaves_record_open(): void
    {
        $user = $this->staff();
        $this->login($user);

        $this->actingAs($user)
            ->post(route('attendance.keep'))
            ->assertRedirect();

        $record = HrAttendance::where('user_id', $user->id)->firstOrFail();

        $this->assertNull($record->check_out_at, 'الاستمرار أغلق السجلّ');
        $this->assertSame('present', $record->status);
        $this->assertSame(1, HrAttendance::count());
    }

    /** TEST 4 — تسجيل الخروج يحفظ وقت الانصراف والمدة. */
    public function test_logout_records_check_out(): void
    {
        $user = $this->staff();
        $this->login($user);

        $record = HrAttendance::where('user_id', $user->id)->firstOrFail();
        // نُرجع الحضور ساعتين للوراء ليكون للمدة معنى
        $record->update(['check_in_at' => now()->subHours(2)]);

        $this->post('/logout')->assertRedirect();

        $record->refresh();

        $this->assertNotNull($record->check_out_at, 'الخروج لم يسجّل انصرافاً');
        $this->assertSame('completed', $record->status);
        $this->assertGreaterThanOrEqual(119, $record->minutes);
        $this->assertLessThanOrEqual(121, $record->minutes);
    }

    /** الانصراف اليدوي من الزرّ يعمل كما يعمل الخروج. */
    public function test_manual_checkout_button_records_time(): void
    {
        $user = $this->staff();
        $this->login($user);

        $this->actingAs($user)->post(route('hr.attendance.checkout'))->assertRedirect();

        $record = HrAttendance::where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($record->check_out_at);
        $this->assertSame('completed', $record->status);
    }

    /** الموكّل ليس موظفاً: دخولُه لا يفتح له سجلّ حضور. */
    public function test_client_login_creates_no_attendance(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'is_active' => true,
            'password' => Hash::make('secret-pass-123'),
        ]);

        $this->login($client);

        $this->assertSame(0, HrAttendance::where('user_id', $client->id)->count());
    }

    /** الحضور المعطَّل من الإعدادات لا يُسجَّل — والدخول يمضي. */
    public function test_disabled_auto_checkin_still_lets_user_in(): void
    {
        \App\Models\Setting::set('hr_auto_checkin', '0', 'hr');

        $user = $this->staff();
        $this->login($user)->assertRedirect();

        $this->assertTrue(auth()->check(), 'تعطيل الحضور منع الدخول');
        $this->assertSame(0, HrAttendance::count());
    }

    /** انصرافٌ بلا حضور لا يخترع سجلاً. */
    public function test_checkout_without_check_in_creates_nothing(): void
    {
        \App\Models\Setting::set('hr_auto_checkin', '0', 'hr');
        $user = $this->staff();

        $this->actingAs($user)->post(route('hr.attendance.checkout'));

        $this->assertSame(0, HrAttendance::count());
    }
}
