<?php

namespace Tests\Feature;

use App\Models\HrAttendance;
use App\Models\HrLeave;
use App\Models\HrLeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * لوحة الحضور — TEST 13/14 وما حولهما.
 *
 * والفصل الذي يهمّ: الموظف يفتح الصفحة نفسها فلا يجد فيها سجلّ
 * زميله. اختُبر بأن نُدخل سجلّ زميلٍ باسمٍ مميّز ونبحث عنه في جسم
 * الردّ — لا بأن نصدّق أن الاستعلام مُرشّح.
 */
class AttendanceBoardTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true, 'name' => 'مدير المكتب']);
    }

    private function record(User $user, ?string $out = null): HrAttendance
    {
        return HrAttendance::create([
            'user_id' => $user->id,
            'work_date' => HrAttendance::today(),
            'check_in_at' => now()->subHours(3),
            'check_out_at' => $out ? now()->subHour() : null,
            'minutes' => $out ? 120 : null,
            'status' => $out ? 'completed' : 'present',
            'source' => 'manual',
        ]);
    }

    public function test_manager_sees_counts_and_board(): void
    {
        $manager = $this->manager();
        $present = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'حاضرٌ اليوم']);
        $left = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'name' => 'منصرفٌ اليوم']);
        $absent = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'غائبٌ اليوم']);

        $this->record($present);
        $this->record($left, 'out');

        $response = $this->actingAs($manager)->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('حاضرٌ اليوم');
        $response->assertSee('منصرفٌ اليوم');
        $response->assertSee('غائبٌ اليوم');   // يظهر في لوحة الحالة بحالة «غائب»
        $response->assertSee('حالة الفريق اليوم');
    }

    /** الموظف لا يرى إلا نفسه — لا في الجدول ولا في اللوحة. */
    public function test_employee_sees_only_own_records(): void
    {
        $me = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'أنا الموظف']);
        $other = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'زميلٌ سرّي']);

        $this->record($me);
        $this->record($other);

        $response = $this->actingAs($me)->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('أنا الموظف');
        $response->assertDontSee('زميلٌ سرّي');
        $response->assertDontSee('حالة الفريق اليوم');
    }

    /** الترشيح بالموظف يعمل للمدير. */
    public function test_manager_can_filter_by_employee(): void
    {
        $manager = $this->manager();
        $a = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'موظف ألف']);
        $b = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'موظف باء']);

        $this->record($a);
        $this->record($b);

        $response = $this->actingAs($manager)
            ->get(route('attendance.index', ['employee_id' => $a->id]));

        $response->assertOk();

        // العدّ النصّي لا يصلح هنا: الاسم يظهر في قائمة الترشيح وفي
        // لوحة الحالة أيضاً. المقصود ما رشّحه الاستعلام — فنفحصه هو.
        $rows = $response->viewData('records');

        $this->assertCount(1, $rows);
        $this->assertSame($a->id, $rows->first()->user_id);
    }

    /** الترشيح بالحالة: «حاضر» لا يُظهر من انصرف. */
    public function test_status_filter_narrows_rows(): void
    {
        $manager = $this->manager();
        $present = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'ما زال هنا']);
        $left = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'غادر مبكراً']);

        $this->record($present);
        $this->record($left, 'out');

        $response = $this->actingAs($manager)
            ->get(route('attendance.index', ['status' => 'present']));

        $response->assertOk();

        $rows = $response->viewData('records');

        $this->assertCount(1, $rows);
        $this->assertSame($present->id, $rows->first()->user_id);
        $this->assertNull($rows->first()->check_out_at);
    }

    /** المدى الشهري يشمل سجلاً من أول الشهر. */
    public function test_month_range_includes_earlier_days(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'سجلٌّ قديم']);

        HrAttendance::create([
            'user_id' => $employee->id,
            'work_date' => now('Asia/Muscat')->startOfMonth()->toDateString(),
            'check_in_at' => now('Asia/Muscat')->startOfMonth()->setTime(8, 0),
            'check_out_at' => now('Asia/Muscat')->startOfMonth()->setTime(16, 0),
            'minutes' => 480,
            'status' => 'completed',
            'source' => 'manual',
        ]);

        $day = $this->actingAs($manager)->get(route('attendance.index', ['range' => 'day']));
        $month = $this->actingAs($manager)->get(route('attendance.index', ['range' => 'month']));

        $month->assertOk();
        $this->assertCount(1, $month->viewData('records'));

        // مدى «اليوم» لا يلتقطه إلا إن كان اليوم أولَ الشهر
        $isFirstOfMonth = now('Asia/Muscat')->day === 1;
        $this->assertCount($isFirstOfMonth ? 1 : 0, $day->viewData('records'));
    }

    /** من في إجازة معتمدة يُعدّ «في إجازة» لا «غائباً». */
    public function test_employee_on_approved_leave_is_not_counted_absent(): void
    {
        $manager = $this->manager();
        $onLeave = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'في إجازته']);

        HrLeave::create([
            'employee_id' => $onLeave->id,
            'type' => 'annual',
            'leave_type_id' => HrLeaveType::where('code', 'annual')->firstOrFail()->id,
            'start_date' => now('Asia/Muscat')->subDay()->toDateString(),
            'end_date' => now('Asia/Muscat')->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        $response = $this->actingAs($manager)->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('إجازة');
        // الغائبون: المدير وحده (لا سجلّ له ولا إجازة)
        $response->assertSeeInOrder(['غائبون', '1']);
    }
}
