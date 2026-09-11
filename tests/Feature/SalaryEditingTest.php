<?php

namespace Tests\Feature;

use App\Models\HrSalary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تعديلُ راتبٍ قائم — من الجدول ومن الكشف، لا من صفر.
 *
 * ═══ ما وقع على شاشة المالك ═══
 *
 * «في تعديل الرواتب والكشف ما يمديني أعدّل راتبه». والتعديلُ كان ممكناً
 * في الخادم (‎updateOrCreate‎) لكنّ الشاشةَ لا تقوله: نموذجُ «تحديد راتب»
 * يفتح فارغاً دائماً، فمن اختار موظّفاً له راتبٌ رأى صفراً في الأساسيّ
 * وظنّ أنّ التعديل غيرُ ممكن. وكشفُ الراتب يعرض الرقمَ ولا يتيح تغييرَه.
 *
 * ═══ وما يحرسه هذا ═══
 *
 * ١) الخياراتُ تحمل الراتبَ القائم فيُملأ النموذجُ عند الاختيار.
 * ٢) ورابطُ «تعديل» من الجدول يفتح النموذجَ على صاحبه مسبَقَ الاختيار.
 * ٣) وكشفُ الراتب فيه نموذجُ تعديلٍ مملوءٌ يحفظ بالمسار نفسِه.
 */
class SalaryEditingTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function employeeWithSalary(float $basic = 900, float $allowances = 50): User
    {
        $e = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'عبدالله بن علي الكندي']);

        HrSalary::create([
            'employee_id' => $e->id, 'basic_salary' => $basic, 'allowances' => $allowances,
            'note' => 'راتبٌ قديم', 'updated_by' => $this->manager()->id,
        ]);

        return $e;
    }

    /** الخيارُ يحمل الراتبَ القائم — وهو ما يملأ النموذج. */
    public function test_the_employee_option_carries_the_existing_salary(): void
    {
        $e = $this->employeeWithSalary(900, 50);

        $html = $this->actingAs($this->manager())->get('/hr?tab=salaries')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<option value="' . $e->id . '"[^>]*data-basic="900\.00"[^>]*data-allowances="50\.00"/', $html,
            'الخيارُ بلا راتبه القائم — النموذجُ سيفتح على صفر');
        $this->assertStringContainsString('data-salary-form', $html);
    }

    /** ورابطُ «تعديل» من الجدول يفتح النموذجَ على صاحبه. */
    public function test_the_edit_link_preselects_the_employee(): void
    {
        $e = $this->employeeWithSalary();

        $html = $this->actingAs($this->manager())->get('/hr?tab=salaries')->assertOk()->getContent();
        $this->assertStringContainsString('employee=' . $e->id, $html, 'لا رابطَ تعديلٍ في الجدول');

        $html = $this->actingAs($this->manager())->get('/hr?tab=salaries&employee=' . $e->id)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="' . $e->id . '"[^>]*selected/', $html,
            'الرابطُ لا يسبق اختيارَ الموظّف');
    }

    /** وكشفُ الراتب فيه نموذجُ تعديلٍ مملوءٌ بالقائم. */
    public function test_the_payslip_page_carries_a_prefilled_edit_form(): void
    {
        $e = $this->employeeWithSalary(900, 50);

        $html = $this->actingAs($this->manager())->get('/salaries/' . $e->id)->assertOk()->getContent();

        $this->assertStringContainsString('تعديل الراتب', $html);
        $this->assertStringContainsString('name="employee_id" value="' . $e->id . '"', $html);
        $this->assertStringContainsString('value="900.00"', $html, 'الأساسيُّ القائم غيرُ مملوء');
        $this->assertStringContainsString('value="50.00"', $html, 'البدلاتُ القائمة غيرُ مملوءة');
    }

    /** والحفظُ من الكشف يُحدّث لا يُنشئ ثانياً. */
    public function test_saving_from_the_payslip_updates_the_existing_salary(): void
    {
        $e = $this->employeeWithSalary(900, 50);

        $this->actingAs($this->manager())->post('/salaries', [
            'employee_id' => $e->id, 'basic_salary' => 1200, 'allowances' => 75, 'note' => 'زيادة',
        ])->assertRedirect();

        $this->assertSame(1, HrSalary::where('employee_id', $e->id)->count(), 'أُنشئ راتبٌ ثانٍ بدل التعديل');
        $this->assertSame(1200.0, (float) HrSalary::where('employee_id', $e->id)->value('basic_salary'));
    }

    /** وموظّفٌ بلا راتبٍ يفتح الكشفُ له نموذجَ تسجيلٍ لا تعديل. */
    public function test_an_employee_without_a_salary_gets_a_register_form(): void
    {
        $e = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $html = $this->actingAs($this->manager())->get('/salaries/' . $e->id)->assertOk()->getContent();

        $this->assertStringContainsString('تسجيل الراتب', $html);
        $this->assertStringNotContainsString('تعديل الراتب', $html);
    }
}
