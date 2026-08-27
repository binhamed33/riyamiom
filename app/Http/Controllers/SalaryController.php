<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\HrPayrollAdjustment;
use App\Models\HrSalary;
use App\Models\User;
use App\Support\Payroll;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الرواتب — للمدير وحده.
 *
 * الحارس هنا مكرّرٌ عمداً: المسار محميٌّ بوسيط الأدوار، وكل دالّة
 * تفحص التفويض ثانيةً. لأن مساراً يُضاف يوماً بلا الوسيط الصحيح
 * يفتح الجدول كلّه، وطبقةٌ ثانية تُبقيه مغلقاً.
 *
 * والقاعدة التي تحكم كل شيء هنا: صاحب الراتب نفسه لا يراه. لا
 * استثناء لـ«رأيه هو»، فذلك المنفذ هو الذي يجعل الجدول قابلاً
 * للاستنتاج صفّاً صفّاً.
 */
class SalaryController extends Controller
{
    private function authorizeManager(): void
    {
        $user = auth()->user();

        abort_unless(
            $user && (
                $user->isDeveloper()
                || $user->role === 'admin'
                || $user->hasPermission('salaries.manage')
            ),
            403,
            'الرواتب متاحة لإدارة المكتب فقط.'
        );
    }

    /** الأدوار التي لها راتب — الموكّل ليس موظفاً. */
    private function employees()
    {
        return User::whereIn('role', ['admin', 'lawyer', 'staff'])
            ->orderBy('name')
            ->get();
    }

    /** الصفحة صارت تبويباً — والمسار يحوّل بعد التحقّق من التفويض. */
    public function index(Request $request)
    {
        $this->authorizeManager();

        return redirect()->route('hr.index', array_merge(
            ['tab' => 'salaries'],
            $request->only(['period'])
        ));
    }

    public function legacyIndex(Request $request): View
    {
        $this->authorizeManager();

        $period = $this->period($request);
        $employees = $this->employees();

        $salaries = HrSalary::with('employee')->get()->keyBy('employee_id');

        $payslips = $employees->map(fn (User $e) => Payroll::payslip($e, $period));

        $totals = [
            'gross' => round($payslips->sum('gross'), 2),
            'deductions' => round($payslips->sum('deductions'), 2),
            'net' => round($payslips->sum('net'), 2),
            'without_salary' => $payslips->where('has_salary', false)->count(),
        ];

        return view('salaries.index', [
            'employees' => $employees,
            'salaries' => $salaries,
            'payslips' => $payslips,
            'period' => $period,
            'totals' => $totals,
            'monthDaysMode' => Payroll::monthDaysMode(),
        ]);
    }

    /** كشف موظف واحد. */
    public function show(Request $request, User $employee): View
    {
        $this->authorizeManager();

        abort_unless(
            in_array($employee->role, ['admin', 'lawyer', 'staff'], true),
            404
        );

        $period = $this->period($request);

        return view('salaries.show', [
            'employee' => $employee,
            'payslip' => Payroll::payslip($employee, $period),
            'salary' => HrSalary::where('employee_id', $employee->id)->first(),
            'period' => $period,
        ]);
    }

    /** حفظ راتب موظف — إنشاءً أو تعديلاً. */
    public function store(Request $request)
    {
        $this->authorizeManager();

        $data = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'basic_salary' => 'required|numeric|min:0|max:9999999',
            'allowances' => 'nullable|numeric|min:0|max:9999999',
            'effective_from' => 'nullable|date',
            'note' => 'nullable|string|max:255',
        ]);

        $employee = User::findOrFail($data['employee_id']);

        abort_unless(
            in_array($employee->role, ['admin', 'lawyer', 'staff'], true),
            422
        );

        $existing = HrSalary::where('employee_id', $employee->id)->first();
        $before = $existing ? [
            'basic_salary' => (float) $existing->basic_salary,
            'allowances' => (float) $existing->allowances,
        ] : null;

        $salary = HrSalary::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'basic_salary' => $data['basic_salary'],
                'allowances' => $data['allowances'] ?? 0,
                'effective_from' => $data['effective_from'] ?? null,
                'note' => $data['note'] ?? null,
                'updated_by' => auth()->id(),
            ]
        );

        $this->audit(
            $before ? AuditLog::ACTION_UPDATE : AuditLog::ACTION_CREATE,
            $salary,
            $before,
            ['basic_salary' => (float) $salary->basic_salary, 'allowances' => (float) $salary->allowances],
            $employee
        );

        return redirect()->route('salaries.index')
            ->with('success', 'حُفظ راتب ' . $employee->name . '.');
    }

    /** بدل أو خصم لفترة. */
    public function storeAdjustment(Request $request)
    {
        $this->authorizeManager();

        $data = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'period' => 'required|date_format:Y-m',
            'kind' => 'required|in:allowance,deduction',
            'amount' => 'required|numeric|min:0.01|max:9999999',
            'reason' => 'required|string|max:255',
        ]);

        $adjustment = HrPayrollAdjustment::create($data + ['created_by' => auth()->id()]);

        $this->audit(
            AuditLog::ACTION_CREATE,
            $adjustment,
            null,
            ['kind' => $data['kind'], 'amount' => (float) $data['amount'], 'period' => $data['period']],
            User::find($data['employee_id'])
        );

        return back()->with('success', 'أُضيف البند.');
    }

    public function destroyAdjustment(HrPayrollAdjustment $adjustment)
    {
        $this->authorizeManager();

        $snapshot = ['kind' => $adjustment->kind, 'amount' => (float) $adjustment->amount, 'period' => $adjustment->period];
        $employee = $adjustment->employee;

        $adjustment->delete();

        $this->audit(AuditLog::ACTION_DELETE, $adjustment, $snapshot, null, $employee);

        return back()->with('success', 'حُذف البند.');
    }

    /** إعداد طريقة قسمة الشهر. */
    public function updateSettings(Request $request)
    {
        $this->authorizeManager();

        $data = $request->validate([
            'hr_month_days_mode' => 'required|in:fixed30,actual',
        ]);

        \App\Models\Setting::set('hr_month_days_mode', $data['hr_month_days_mode'], 'hr');

        return back()->with('success', 'حُفظت طريقة الحساب.');
    }

    private function period(Request $request): string
    {
        $period = (string) $request->get('period', '');

        return preg_match('/^\d{4}-\d{2}$/', $period) ? $period : Payroll::currentPeriod();
    }

    /**
     * أثرٌ لكل تغيير مالي.
     *
     * مغلَّفٌ لأن مكتباً بلا جدول audit_logs كان يُسقط العملية كلها —
     * وفقدان تعديل راتب أسوأ من فقدان سطر في السجلّ.
     */
    private function audit(string $action, $model, ?array $old, ?array $new, ?User $employee): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'old_values' => $old,
                'new_values' => $new ? $new + ['employee' => $employee?->name] : ['employee' => $employee?->name],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable) {
            // السجلّ يفشل بصمت — العملية المالية لا تسقط معه
        }
    }
}
