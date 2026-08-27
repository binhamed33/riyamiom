<?php

namespace Tests\Feature;

use App\Models\HrLeave;
use App\Models\HrLeaveType;
use App\Models\HrSalary;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use App\Support\Payroll;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الإجازات وأثرها في الراتب — TEST 9 إلى 12.
 *
 * المثال الذي تختبره الحسابات هو مثال المواصفة نفسه: راتب ٩٠٠،
 * شهر ٣٠ يوماً، ثلاثة أيام بلا أجر ⇒ ٩٠ ريالاً. ثم نتحقّق أن الرقم
 * لا يصل إلى صاحب الإجازة بأي طريق.
 */
class LeaveDeductionTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function employee(): User
    {
        return User::factory()->create(['role' => 'staff', 'is_active' => true]);
    }

    private function leave(User $employee, string $typeCode, string $from, string $to): HrLeave
    {
        $type = HrLeaveType::where('code', $typeCode)->firstOrFail();

        return HrLeave::create([
            'employee_id' => $employee->id,
            'type' => $typeCode,
            'leave_type_id' => $type->id,
            'start_date' => $from,
            'end_date' => $to,
            'status' => 'pending',
        ]);
    }

    /** الأنواع الستّة زُرعت بالهجرة، و«بلا أجر» وحدها تخصم. */
    public function test_seeded_types_have_sane_defaults(): void
    {
        $this->assertSame(6, HrLeaveType::count());
        $this->assertTrue(HrLeaveType::where('code', 'unpaid')->firstOrFail()->affects_salary);
        $this->assertFalse(HrLeaveType::where('code', 'annual')->firstOrFail()->affects_salary);
        $this->assertFalse(HrLeaveType::where('code', 'sick')->firstOrFail()->affects_salary);
    }

    /** TEST 9 — طلب الموظف يصل للمدير. */
    public function test_employee_request_reaches_manager(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();
        $type = HrLeaveType::where('code', 'annual')->firstOrFail();

        $this->actingAs($employee)->post(route('hr.leaves.store'), [
            'leave_type_id' => $type->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'reason' => 'ظرف عائلي',
        ])->assertRedirect();

        $leave = HrLeave::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('pending', $leave->status);
        $this->assertSame(3, $leave->days);

        $this->assertSame(
            1,
            Notification::where('user_id', $manager->id)->where('notifiable_id', $leave->id)->count(),
            'الطلب لم يصل المدير'
        );
    }

    /** TEST 10 — الرفض لا يُنتج خصماً. */
    public function test_rejected_leave_produces_no_deduction(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();
        HrSalary::create(['employee_id' => $employee->id, 'basic_salary' => 900]);

        $leave = $this->leave($employee, 'unpaid', '2026-09-01', '2026-09-03');

        $this->actingAs($manager)->post(route('hr.leaves.reject', $leave))->assertRedirect();

        $leave->refresh();
        $this->assertSame('rejected', $leave->status);
        $this->assertNull($leave->deduction_amount);

        $slip = Payroll::payslip($employee, '2026-09');
        $this->assertSame(0, $slip['unpaid_days']);
        $this->assertEquals(0.0, $slip['leave_deduction']);
        $this->assertEquals(900.0, $slip['net']);
    }

    /** TEST 11 — اعتماد إجازة بلا أجر: ٩٠٠ ÷ ٣٠ × ٣ = ٩٠. */
    public function test_approved_unpaid_leave_deducts_exactly(): void
    {
        Setting::set('hr_month_days_mode', 'fixed30', 'hr');

        $manager = $this->manager();
        $employee = $this->employee();
        HrSalary::create(['employee_id' => $employee->id, 'basic_salary' => 900]);

        $leave = $this->leave($employee, 'unpaid', '2026-09-01', '2026-09-03');

        $this->actingAs($manager)->post(route('hr.leaves.approve', $leave))->assertRedirect();

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertEquals(90.0, (float) $leave->deduction_amount);

        $slip = Payroll::payslip($employee, '2026-09');
        $this->assertSame(3, $slip['unpaid_days']);
        $this->assertEquals(30.0, $slip['daily_rate']);
        $this->assertEquals(90.0, $slip['leave_deduction']);
        $this->assertEquals(810.0, $slip['net']);
    }

    /** الإجازة المدفوعة لا تخصم ولو اعتُمدت. */
    public function test_paid_leave_never_deducts(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();
        HrSalary::create(['employee_id' => $employee->id, 'basic_salary' => 900]);

        $leave = $this->leave($employee, 'annual', '2026-09-01', '2026-09-05');
        $this->actingAs($manager)->post(route('hr.leaves.approve', $leave));

        $slip = Payroll::payslip($employee, '2026-09');
        $this->assertSame(0, $slip['unpaid_days']);
        $this->assertEquals(900.0, $slip['net']);
    }

    /** المكتب يجعل المرضية خاصمة — فتخصم، بلا لمس كود. */
    public function test_office_can_make_a_type_deduct(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();
        HrSalary::create(['employee_id' => $employee->id, 'basic_salary' => 600]);

        $sick = HrLeaveType::where('code', 'sick')->firstOrFail();

        $this->actingAs($manager)->put(route('leave-types.update', $sick), [
            'name' => 'إجازة مرضية',
            'affects_salary' => 1,
            'is_active' => 1,
        ])->assertRedirect();

        $leave = $this->leave($employee, 'sick', '2026-09-10', '2026-09-11');
        $this->actingAs($manager)->post(route('hr.leaves.approve', $leave));

        $slip = Payroll::payslip($employee, '2026-09');
        $this->assertSame(2, $slip['unpaid_days']);
        $this->assertEquals(40.0, $slip['leave_deduction']);   // 600/30 × 2
    }

    /** طريقة الحساب إعداد: أيام الشهر الفعلية تغيّر قيمة اليوم. */
    public function test_actual_days_mode_changes_daily_rate(): void
    {
        $employee = $this->employee();
        HrSalary::create(['employee_id' => $employee->id, 'basic_salary' => 900]);

        Setting::set('hr_month_days_mode', 'fixed30', 'hr');
        $this->assertEquals(30.0, Payroll::dailyRate(900, '2026-02'));

        Setting::set('hr_month_days_mode', 'actual', 'hr');
        $this->assertEquals(28, Payroll::daysInPeriod('2026-02'));
        $this->assertEqualsWithDelta(32.143, Payroll::dailyRate(900, '2026-02'), 0.01);
    }

    /** إجازة تعبر شهرين تُقسَّم على شهريها لا تُحسب مرّتين. */
    public function test_leave_spanning_two_months_splits(): void
    {
        Setting::set('hr_month_days_mode', 'fixed30', 'hr');

        $manager = $this->manager();
        $employee = $this->employee();
        HrSalary::create(['employee_id' => $employee->id, 'basic_salary' => 900]);

        $leave = $this->leave($employee, 'unpaid', '2026-08-30', '2026-09-02');
        $this->actingAs($manager)->post(route('hr.leaves.approve', $leave));

        $aug = Payroll::payslip($employee, '2026-08');
        $sep = Payroll::payslip($employee, '2026-09');

        $this->assertSame(2, $aug['unpaid_days']);      // ٣٠ و٣١ أغسطس
        $this->assertSame(2, $sep['unpaid_days']);      // ١ و٢ سبتمبر
        $this->assertSame(4, $aug['unpaid_days'] + $sep['unpaid_days']);
    }

    /** TEST 12 — تفاصيل الخصم للمدير وحده. */
    public function test_deduction_details_go_only_to_managers(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();
        $colleague = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        HrSalary::create(['employee_id' => $employee->id, 'basic_salary' => 900]);

        $leave = $this->leave($employee, 'unpaid', '2026-09-01', '2026-09-03');
        $this->actingAs($manager)->post(route('hr.leaves.approve', $leave));

        $managerNotes = Notification::where('user_id', $manager->id)->get();
        $this->assertTrue(
            $managerNotes->contains(fn ($n) => str_contains((string) $n->message, '90')),
            'المدير لم يصله المبلغ'
        );

        // صاحب الإجازة: خبر الاعتماد بلا رقم
        foreach (Notification::where('user_id', $employee->id)->get() as $note) {
            $this->assertStringNotContainsString('90.00', (string) $note->message);
            $this->assertStringNotContainsString('الخصم', (string) $note->message);
            $this->assertNull($note->params['amount'] ?? null);
        }

        // الزميل لا يصله شيء عن هذه الإجازة أصلاً
        $this->assertSame(
            0,
            Notification::where('user_id', $colleague->id)->where('notifiable_id', $leave->id)->count()
        );
    }

    /** مديرٌ يعتمد إجازة نفسه لا يُشعِر نفسه برقم راتبه. */
    public function test_manager_approving_own_leave_gets_no_self_notification(): void
    {
        $manager = $this->manager();
        HrSalary::create(['employee_id' => $manager->id, 'basic_salary' => 900]);

        $leave = $this->leave($manager, 'unpaid', '2026-09-01', '2026-09-03');
        $this->actingAs($manager)->post(route('hr.leaves.approve', $leave));

        foreach (Notification::where('user_id', $manager->id)->get() as $note) {
            $this->assertStringNotContainsString('الخصم المتوقّع', (string) $note->message);
        }
    }

    /** إجازة بلا راتب مُسجَّل: صفر خصم لا خطأ قسمة. */
    public function test_leave_without_salary_record_is_zero(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();

        $leave = $this->leave($employee, 'unpaid', '2026-09-01', '2026-09-03');
        $this->actingAs($manager)->post(route('hr.leaves.approve', $leave))->assertRedirect();

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertNull($leave->deduction_amount);
    }
}
