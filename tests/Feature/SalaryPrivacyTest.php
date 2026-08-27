<?php

namespace Tests\Feature;

use App\Models\HrPayrollAdjustment;
use App\Models\HrSalary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * خصوصية الرواتب — TEST 5 إلى 8.
 *
 * المبدأ الذي تحرسه هذه الاختبارات: الراتب لا يُرى إلا من إدارة
 * المكتب. لا الموظف يرى راتب زميله، ولا يرى راتبه هو. وليس المنع
 * إخفاءَ عنصرٍ في صفحة: نطلب المسار نفسه بحساب موظف وننتظر 403،
 * ثم نتأكّد أن الرقم لم يظهر في جسم أي ردّ يصله.
 */
class SalaryPrivacyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * «مرفوض» في هذا التطبيق وجهان لعملةٍ واحدة:
     * طلبُ متصفحٍ يُردّ بتحويلةٍ إلى اللوحة برسالة، وطلبُ API يُردّ
     * بـ403 صريح. نتحقّق من الاثنين معاً — ومن أنّ الرقم لم يُسرَّب
     * في أيٍّ منهما — فلا يكفي أن يُمنع العرض ويُسلَّم الرقم للـJSON.
     */
    private function assertDenied(string $url, string $method = 'get', array $payload = [], ?string $secret = null): void
    {
        $browser = $this->{$method}($url, $payload);
        $browser->assertRedirect(route('dashboard'));
        $browser->assertSessionHas('error');

        $api = $this->{$method . 'Json'}($url, $payload);
        $api->assertStatus(403);

        if ($secret !== null) {
            $this->assertStringNotContainsString($secret, $browser->getContent());
            $this->assertStringNotContainsString($secret, $api->getContent());
            $this->assertStringNotContainsString(number_format((float) $secret, 2), $api->getContent());
        }
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function employee(string $role = 'staff'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function giveSalary(User $employee, float $basic = 900): HrSalary
    {
        return HrSalary::create([
            'employee_id' => $employee->id,
            'basic_salary' => $basic,
            'allowances' => 0,
        ]);
    }

    /** TEST 5 — الموظف يحاول الوصول إلى راتبه: ممنوع. */
    public function test_employee_cannot_open_salaries_page(): void
    {
        $employee = $this->employee();
        $this->giveSalary($employee);

        $this->actingAs($employee);
        // المسار صار يحوّل إلى تبويب الرواتب — والتفويض يُفحص قبل التحويل
        $this->assertDenied(route('salaries.index'));
        // وتبويب الرواتب نفسه لا يعرض رقماً لمن لا يملكه
        $tab = $this->get(route('hr.index', ['tab' => 'salaries']));
        $tab->assertOk();
        $tab->assertDontSee('الرواتب</a>', false);
    }

    /** TEST 6 — الوصول المباشر لمسار الراتب: ممنوع. */
    public function test_employee_cannot_open_own_payslip_route(): void
    {
        $employee = $this->employee();
        $this->giveSalary($employee, 1234.56);

        $this->actingAs($employee);
        $this->assertDenied(route('salaries.show', $employee), 'get', [], '1234.56');
    }

    /** TEST 7 — الموظف يحاول رؤية راتب زميله: ممنوع. */
    public function test_employee_cannot_open_colleague_payslip(): void
    {
        $employee = $this->employee();
        $colleague = $this->employee('lawyer');
        $this->giveSalary($colleague, 777.77);

        $this->actingAs($employee);
        $this->assertDenied(route('salaries.show', $colleague), 'get', [], '777.77');
    }

    /** المحامي كذلك — رتبةٌ أعلى لا تعني اطّلاعاً على الرواتب. */
    public function test_lawyer_cannot_open_salaries(): void
    {
        $lawyer = $this->employee('lawyer');

        $this->actingAs($lawyer);
        $this->assertDenied(route('salaries.index'));
        $this->assertDenied(route('salaries.store'), 'post', [
            'employee_id' => $lawyer->id,
            'basic_salary' => 5000,
        ]);
    }

    /** الموظف لا يستطيع الكتابة كذلك — لا يمنح نفسه راتباً. */
    public function test_employee_cannot_write_salary(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee);
        $this->assertDenied(route('salaries.store'), 'post', [
            'employee_id' => $employee->id,
            'basic_salary' => 9999,
        ]);

        $this->assertSame(0, HrSalary::count());
    }

    /** ولا يحذف بنداً ولا يضيفه. */
    public function test_employee_cannot_touch_adjustments(): void
    {
        $employee = $this->employee();
        $manager = $this->manager();

        $adjustment = HrPayrollAdjustment::create([
            'employee_id' => $employee->id,
            'period' => '2026-08',
            'kind' => 'deduction',
            'amount' => 50,
            'reason' => 'اختبار',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($employee);

        $this->assertDenied(route('salaries.adjustments.store'), 'post', [
            'employee_id' => $employee->id,
            'period' => '2026-08',
            'kind' => 'allowance',
            'amount' => 500,
            'reason' => 'مكافأة لنفسي',
        ]);

        $this->assertDenied(route('salaries.adjustments.destroy', $adjustment), 'delete');

        $this->assertSame(1, HrPayrollAdjustment::count());
    }

    /** الراتب لا يظهر في أي صفحة يفتحها الموظف. */
    public function test_salary_figure_absent_from_employee_pages(): void
    {
        $employee = $this->employee();
        $this->giveSalary($employee, 4321.99);

        foreach ([route('dashboard'), route('hr.index'), route('hr.index', ['tab' => 'attendance_log'])] as $url) {
            $response = $this->actingAs($employee)->get($url);

            if ($response->status() !== 200) {
                continue;                       // صفحة محجوبة لسببٍ آخر
            }

            $body = $response->getContent();
            $this->assertStringNotContainsString('4321.99', $body, "الرقم ظهر في {$url}");
            $this->assertStringNotContainsString('4,321.99', $body, "الرقم ظهر في {$url}");
        }
    }

    /** TEST 8 — المدير يضيف راتباً: يُحفظ. */
    public function test_manager_can_store_salary(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();

        $this->actingAs($manager)->post(route('salaries.store'), [
            'employee_id' => $employee->id,
            'basic_salary' => 900,
            'allowances' => 50,
        ])->assertRedirect();

        $salary = HrSalary::where('employee_id', $employee->id)->firstOrFail();

        $this->assertEquals(900, (float) $salary->basic_salary);
        $this->assertEquals(50, (float) $salary->allowances);
        $this->assertSame($manager->id, $salary->updated_by);
    }

    /** والمدير يرى الصفحة والكشف. */
    public function test_manager_can_view_salaries(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();
        $this->giveSalary($employee, 900);

        $this->actingAs($manager)->get(route('salaries.index'))
            ->assertRedirect(route('hr.index', ['tab' => 'salaries']));
        $this->actingAs($manager)->get(route('hr.index', ['tab' => 'salaries']))->assertOk();
        $this->actingAs($manager)->get(route('salaries.show', $employee))->assertOk();
    }

    /** موظفٌ مُنح صلاحية salaries.manage صراحةً يمرّ — والمنع ليس بالدور وحده. */
    public function test_explicit_permission_grants_access(): void
    {
        $employee = $this->employee();
        $employee->givePermission('salaries.manage');

        $this->actingAs($employee)->get(route('salaries.index'))
            ->assertRedirect(route('hr.index', ['tab' => 'salaries']));
        $this->actingAs($employee)->get(route('hr.index', ['tab' => 'salaries']))->assertOk();
    }

    /** تعديل الراتب يترك أثراً في سجل الحركات. */
    public function test_salary_change_is_audited(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();

        $this->actingAs($manager)->post(route('salaries.store'), [
            'employee_id' => $employee->id,
            'basic_salary' => 900,
        ]);

        $this->actingAs($manager)->post(route('salaries.store'), [
            'employee_id' => $employee->id,
            'basic_salary' => 1000,
        ]);

        $logs = \App\Models\AuditLog::where('model_type', HrSalary::class)->get();

        $this->assertGreaterThanOrEqual(2, $logs->count(), 'التعديل لم يُسجَّل');
        $update = $logs->firstWhere('action', 'update');
        $this->assertNotNull($update);
        $this->assertSame($manager->id, $update->user_id);
        $this->assertEquals(900, $update->old_values['basic_salary']);
        $this->assertEquals(1000, $update->new_values['basic_salary']);
    }
}
