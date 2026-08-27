<?php

namespace Tests\Feature;

use App\Models\HrLeaveType;
use App\Models\HrSalary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الصفحات تُرسَم فعلاً.
 *
 * اختبارُ منطقٍ يمرّ وصفحةٌ تنهار عند فتحها = ميزةٌ لا وجود لها.
 * هنا نفتح كل صفحةٍ جديدة ونطلب 200 ومحتوىً نعرفه.
 */
class HrPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_salaries_index_renders_with_data(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $employee = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'سالم الموظف']);
        HrSalary::create(['employee_id' => $employee->id, 'basic_salary' => 900, 'allowances' => 50]);

        $r = $this->actingAs($manager)->get(route('hr.index', ['tab' => 'salaries']));

        $r->assertOk();
        $r->assertSee('سالم الموظف');
        $r->assertSee('الصافي');
        $r->assertSee('طريقة حساب اليوم');
    }

    public function test_payslip_page_renders(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $employee = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        HrSalary::create(['employee_id' => $employee->id, 'basic_salary' => 900]);

        $r = $this->actingAs($manager)->get(route('salaries.show', $employee));

        $r->assertOk();
        $r->assertSee('كشف راتب');
        $r->assertSee('قيمة اليوم');
    }

    /** موظفٌ بلا راتب: الصفحة تُفتح وتقول ذلك صراحةً. */
    public function test_payslip_without_salary_explains_itself(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $employee = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $r = $this->actingAs($manager)->get(route('salaries.show', $employee));

        $r->assertOk();
        $r->assertSee('لم يُسجَّل راتب لهذا الموظف بعد');
    }

    public function test_attendance_page_renders_for_both_roles(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $employee = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($manager)->get(route('hr.index', ['tab' => 'attendance_log']))->assertOk();
        $this->actingAs($employee)->get(route('hr.index', ['tab' => 'attendance_log']))->assertOk();
    }

    public function test_hr_page_shows_leave_types_panel_to_manager_only(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $employee = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $m = $this->actingAs($manager)->get(route('hr.index', ['tab' => 'leaves']));
        $m->assertOk();
        $m->assertSee('أنواع الإجازات وأثرها في الراتب');
        $m->assertSee('يخصم من الراتب');

        $e = $this->actingAs($employee)->get(route('hr.index', ['tab' => 'leaves']));
        $e->assertOk();
        $e->assertDontSee('أنواع الإجازات وأثرها في الراتب');
    }

    /** نموذج الإجازة يعرض الأنواع من الجدول — بما فيها نوعٌ أضافه المكتب. */
    public function test_leave_form_lists_custom_type(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        HrLeaveType::create([
            'code' => 'hajj', 'name' => 'إجازة حج', 'affects_salary' => false, 'is_active' => true, 'sort' => 9,
        ]);

        $r = $this->actingAs($manager)->get(route('hr.index', ['tab' => 'leaves']));

        $r->assertOk();
        $r->assertSee('إجازة حج');
    }

    /** النوع المعطَّل يختفي من الاختيار ولا يُحذف. */
    public function test_disabled_type_hidden_from_form(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $type = HrLeaveType::where('code', 'emergency')->firstOrFail();
        $this->actingAs($manager)->put(route('leave-types.update', $type), [
            'name' => 'إجازة طارئة', 'is_active' => 0,
        ]);

        $this->assertSame(6, HrLeaveType::count(), 'التعطيل حذف النوع');
        $this->assertFalse(HrLeaveType::selectable()->contains('code', 'emergency'));
    }
}
