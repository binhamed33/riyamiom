<?php

namespace Tests\Feature;

use App\Models\HrAttendance;
use App\Models\HrBonus;
use App\Models\HrLeave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * بوابة الموظّف (§18-22): الحضور، والإجازات، وحدود الصلاحيات.
 *
 * أخطر ما وُجد هنا: HrController كان يعدّ المحامي والموظّف «إدارة»،
 * فيوافق الموظّف على إجازة نفسه ويمنح نفسه مكافأة. القرارات إدارية
 * في الخادم الآن، والاختبارات تمنع عودتها.
 */
class EmployeePortalTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'staff', 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    // ── الحضور ───────────────────────────────────────────────────

    public function test_an_employee_checks_in_then_out_and_the_day_is_complete(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post('/hr/attendance/check-in')->assertRedirect();

        $record = HrAttendance::todayFor($staff->id);
        $this->assertNotNull($record);
        $this->assertNull($record->check_out_at);

        $this->actingAs($staff)->post('/hr/attendance/check-out')->assertRedirect();

        $record->refresh();
        $this->assertNotNull($record->check_out_at);
        $this->assertIsInt($record->minutes);
    }

    public function test_double_check_in_makes_one_record_only(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post('/hr/attendance/check-in');
        $this->actingAs($staff)->post('/hr/attendance/check-in');

        $this->assertSame(1, HrAttendance::where('user_id', $staff->id)->count());
    }

    public function test_check_out_without_check_in_is_told_the_truth(): void
    {
        $this->actingAs($this->staff())
            ->post('/hr/attendance/check-out')
            ->assertSessionHasErrors('attendance');

        $this->assertSame(0, HrAttendance::count());
    }

    public function test_a_client_cannot_reach_attendance(): void
    {
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $this->actingAs($client)->post('/hr/attendance/check-in')->assertRedirect();
        $this->assertSame(0, HrAttendance::count(), 'الموكّل ليس موظفاً ولا يسجّل حضوراً');
    }

    // ── الصلاحيات: القرارات إدارية لا لكل موظف ───────────────────

    public function test_a_staff_member_cannot_approve_their_own_leave(): void
    {
        $staff = $this->staff();
        $leave = HrLeave::create([
            'employee_id' => $staff->id,
            'type' => 'annual',
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
            'status' => 'pending',
        ]);

        // ‏403 يتحول في هذا التطبيق عمداً إلى إعادة توجيه برسالة —
        // فالخاصية المفحوصة هي الحالة: الطلب بقي معلّقاً ولم يعتمده أحد
        $this->actingAs($staff)->post("/hr/leaves/{$leave->id}/approve")
            ->assertRedirect(route('dashboard'));

        $this->assertSame('pending', $leave->fresh()->status);
        $this->assertNull($leave->fresh()->approved_by);
    }

    public function test_a_staff_member_cannot_award_themselves_a_bonus(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post('/hr/bonuses', [
            'employee_id' => $staff->id,
            'amount' => 500,
            'reason' => 'اجتهاد',
            'date' => now()->toDateString(),
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(0, HrBonus::count());
    }

    public function test_the_admin_still_approves_leaves(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();
        $leave = HrLeave::create([
            'employee_id' => $staff->id,
            'type' => 'annual',
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post("/hr/leaves/{$leave->id}/approve")->assertRedirect();

        $fresh = $leave->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame($admin->id, $fresh->approved_by);
    }

    // ── العرض: كلٌّ يرى ما له ────────────────────────────────────

    public function test_the_hr_page_opens_on_attendance_for_a_staff_member(): void
    {
        $response = $this->actingAs($this->staff())->get('/hr');

        $response->assertOk();
        $response->assertSee('تسجيل الحضور');
        $response->assertDontSee('حضور الفريق اليوم', false);
    }

    public function test_the_admin_sees_the_team_attendance(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();
        $this->actingAs($staff)->post('/hr/attendance/check-in');

        $response = $this->actingAs($admin)->get('/hr?tab=attendance');

        $response->assertOk();
        $response->assertSee('حضور الفريق اليوم', false);
        $response->assertSee($staff->name);
    }

    public function test_the_dashboard_offers_the_check_in_button(): void
    {
        $this->actingAs($this->staff())->get('/dashboard')
            ->assertOk()
            ->assertSee('تسجيل الحضور', false);
    }
}
