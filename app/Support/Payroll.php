<?php

namespace App\Support;

use App\Models\HrLeave;
use App\Models\HrPayrollAdjustment;
use App\Models\HrSalary;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * حساب الراتب لفترة.
 *
 * كل رقمٍ هنا مشتقٌّ من مصدرٍ واحد: الراتب من hr_salaries، والبدل
 * والخصم المؤقّت من hr_payroll_adjustments، وخصم الإجازة من إجازةٍ
 * معتمدة نوعُها يخصم. لا رقم يُخزَّن مرّتين فيختلفان.
 *
 * وطريقة القسمة إعدادُ مكتب لا ثابتٌ في الكود: مكتبٌ يعتمد الشهر
 * ثلاثين يوماً دائماً، وآخر يعتمد أيام الشهر الفعلية — والفرق بينهما
 * في فبراير يبلغ عشر قيمة اليوم.
 */
class Payroll
{
    public const MODE_FIXED30 = 'fixed30';
    public const MODE_ACTUAL = 'actual';

    /** إعداد المكتب لطريقة قسمة الراتب على الأيام. */
    public static function monthDaysMode(): string
    {
        $mode = (string) Setting::get('hr_month_days_mode', self::MODE_FIXED30);

        return in_array($mode, [self::MODE_FIXED30, self::MODE_ACTUAL], true)
            ? $mode
            : self::MODE_FIXED30;
    }

    /** عدد أيام الفترة التي يُقسم عليها الراتب. */
    public static function daysInPeriod(string $period): int
    {
        if (self::monthDaysMode() === self::MODE_FIXED30) {
            return 30;
        }

        return self::periodStart($period)->daysInMonth;
    }

    /** قيمة اليوم الواحد — أساس كل خصم بالأيام. */
    public static function dailyRate(float $basicSalary, string $period): float
    {
        $days = self::daysInPeriod($period);

        return $days > 0 ? round($basicSalary / $days, 3) : 0.0;
    }

    /**
     * عدد أيام الإجازة داخل الفترة وحدها.
     *
     * إجازةٌ تمتدّ من ٢٨ أغسطس إلى ٣ سبتمبر تُخصم أربعة أيام من أغسطس
     * وثلاثة من سبتمبر — لا سبعةً من الشهر الذي بدأت فيه. القصّ على
     * حدود الشهر هو ما يجعل كشفَي الشهرين يجمعان الإجازة مرّة واحدة.
     */
    public static function leaveDaysInPeriod(HrLeave $leave, string $period): int
    {
        $start = self::periodStart($period);
        $end = $start->endOfMonth();

        $from = CarbonImmutable::parse($leave->start_date)->startOfDay();
        $to = CarbonImmutable::parse($leave->end_date)->startOfDay();

        if ($to->lt($from)) {
            return 0;                       // تاريخٌ مقلوب لا يصنع خصماً
        }

        $from = $from->lt($start) ? $start : $from;
        $to = $to->gt($end) ? $end : $to;

        if ($to->lt($from)) {
            return 0;                       // الإجازة كلها خارج الفترة
        }

        return (int) $from->diffInDays($to) + 1;   // اليومان طرفان محسوبان
    }

    /** أيام الإجازة الخاصمة المعتمدة لموظف في فترة. */
    public static function unpaidLeaveDays(int $employeeId, string $period): int
    {
        $start = self::periodStart($period);
        $end = $start->endOfMonth();

        $leaves = HrLeave::with('leaveType')
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();

        $days = 0;

        foreach ($leaves as $leave) {
            if (! self::deducts($leave)) {
                continue;
            }
            $days += self::leaveDaysInPeriod($leave, $period);
        }

        return $days;
    }

    /**
     * هل تخصم هذه الإجازة؟
     *
     * الحكم من نوعها المُعدّ. وإجازةٌ قديمة بلا نوع مربوط تُقرأ من
     * عمود type القديم — فلا يسقط حكمها لأن الترقية أضافت عموداً.
     */
    public static function deducts(HrLeave $leave): bool
    {
        if ($leave->leaveType) {
            return (bool) $leave->leaveType->affects_salary;
        }

        return $leave->type === 'unpaid';
    }

    /**
     * كشف راتب فترة — أرقامٌ كاملة للمدير.
     *
     * تُعاد دائماً حتى لو لم يُسجَّل للموظف راتب: صفرٌ صريح أوضح من
     * صفحةٍ فارغة لا يُعرف أسببها غياب الراتب أم عطلٌ في الصفحة.
     */
    public static function payslip(User $employee, string $period): array
    {
        $salary = HrSalary::where('employee_id', $employee->id)->first();

        $basic = (float) ($salary->basic_salary ?? 0);
        $baseAllowances = (float) ($salary->allowances ?? 0);

        $adjustments = HrPayrollAdjustment::where('employee_id', $employee->id)
            ->where('period', $period)
            ->orderBy('id')
            ->get();

        $periodAllowances = (float) $adjustments->where('kind', 'allowance')->sum('amount');
        $otherDeductions = (float) $adjustments->where('kind', 'deduction')->sum('amount');

        $days = self::daysInPeriod($period);
        $daily = self::dailyRate($basic, $period);
        $unpaidDays = self::unpaidLeaveDays($employee->id, $period);
        $leaveDeduction = round($daily * $unpaidDays, 2);

        $gross = round($basic + $baseAllowances + $periodAllowances, 2);
        $deductions = round($leaveDeduction + $otherDeductions, 2);

        return [
            'employee' => $employee,
            'period' => $period,
            'has_salary' => $salary !== null,
            'basic' => round($basic, 2),
            'allowances' => round($baseAllowances + $periodAllowances, 2),
            'base_allowances' => round($baseAllowances, 2),
            'period_allowances' => round($periodAllowances, 2),
            'unpaid_days' => $unpaidDays,
            'month_days' => $days,
            'daily_rate' => $daily,
            'leave_deduction' => $leaveDeduction,
            'other_deductions' => round($otherDeductions, 2),
            'deductions' => $deductions,
            'gross' => $gross,
            // الصافي لا ينزل تحت الصفر: خصمٌ يفوق الراتب خطأُ إدخال،
            // وراتبٌ سالب في الكشف يقرأه المدير على أنه مديونية.
            'net' => round(max(0, $gross - $deductions), 2),
            'adjustments' => $adjustments,
        ];
    }

    /** الخصم المتوقّع من إجازة بعينها — لإشعار المدير عند الاعتماد. */
    public static function deductionForLeave(HrLeave $leave): array
    {
        $period = CarbonImmutable::parse($leave->start_date)->format('Y-m');

        if (! self::deducts($leave)) {
            return ['days' => 0, 'amount' => 0.0, 'period' => $period];
        }

        $salary = HrSalary::where('employee_id', $leave->employee_id)->first();
        $basic = (float) ($salary->basic_salary ?? 0);

        // الإجازة قد تعبر شهرين: نجمع خصم كل شهرٍ بقيمة يومه
        $days = 0;
        $amount = 0.0;
        $cursor = CarbonImmutable::parse($leave->start_date)->startOfMonth();
        $last = CarbonImmutable::parse($leave->end_date)->startOfMonth();

        while ($cursor->lte($last)) {
            $p = $cursor->format('Y-m');
            $d = self::leaveDaysInPeriod($leave, $p);
            $days += $d;
            $amount += $d * self::dailyRate($basic, $p);
            $cursor = $cursor->addMonth();
        }

        return ['days' => $days, 'amount' => round($amount, 2), 'period' => $period];
    }

    private static function periodStart(string $period): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $period . '-01')->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::now('Asia/Muscat')->startOfMonth();
        }
    }

    /** الفترة الافتراضية: الشهر الجاري بتوقيت مسقط. */
    public static function currentPeriod(): string
    {
        return CarbonImmutable::now('Asia/Muscat')->format('Y-m');
    }
}
