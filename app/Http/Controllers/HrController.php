<?php

namespace App\Http\Controllers;

use App\Models\HrAttendance;
use App\Models\HrBonus;
use App\Models\HrLeave;
use App\Models\HrLeaveType;
use App\Models\HrPenalty;
use App\Models\HrPerformance;
use App\Models\Notification;
use App\Models\User;
use App\Models\LegalCase;
use App\Models\Task;
use App\Support\AttendanceGuard;
use App\Support\Payroll;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrController extends Controller
{
    protected function isAdmin(): bool
    {
        // كانت تعدّ المحامي والموظّف «إدارة» — فيوافق الموظف على إجازة
        // نفسه ويمنح نفسه مكافأة ويرى سجلات زملائه كلّها. الإدارة هنا
        // هي إدارة المكتب لا كلّ من ليس موكّلاً.
        return in_array(auth()->user()->role, ['developer', 'admin']);
    }

    public function index(Request $request): View
    {
        $user = auth()->user();
        $isAdmin = $this->isAdmin();
        $tab = $request->get('tab', $isAdmin ? 'employees' : 'attendance');

        if ($isAdmin) {
            $employees = User::whereIn('role', ['admin', 'lawyer', 'staff'])->get();
        } else {
            $employees = User::where('id', $user->id)->get();
        }

        $performances = HrPerformance::with(['employee', 'reviewer'])
            ->when(!$isAdmin, fn($q) => $q->where('employee_id', $user->id))
            ->latest()->paginate(20);

        $bonuses = HrBonus::with(['employee', 'giver'])
            ->when(!$isAdmin, fn($q) => $q->where('employee_id', $user->id))
            ->latest()->paginate(20);

        $penalties = HrPenalty::with(['employee', 'giver'])
            ->when(!$isAdmin, fn($q) => $q->where('employee_id', $user->id))
            ->latest()->paginate(20);

        $leaves = HrLeave::with(['employee', 'approver'])
            ->when(!$isAdmin, fn($q) => $q->where('employee_id', $user->id))
            ->latest()->paginate(20);

        $stats = [
            'total_employees' => User::whereIn('role', ['admin', 'lawyer', 'staff'])->count(),
            'avg_rating' => HrPerformance::when(!$isAdmin, fn($q) => $q->where('employee_id', $user->id))->avg('rating'),
            'total_bonuses' => HrBonus::when(!$isAdmin, fn($q) => $q->where('employee_id', $user->id))->sum('amount'),
            'total_penalties' => HrPenalty::when(!$isAdmin, fn($q) => $q->where('employee_id', $user->id))->sum('amount'),
            'pending_leaves' => HrLeave::where('status', 'pending')->when(!$isAdmin, fn($q) => $q->where('employee_id', $user->id))->count(),
        ];

        $chartData = [];
        $ratingDistribution = ['excellent' => 0, 'good' => 0, 'poor' => 0];

        if ($isAdmin) {
            // كانت أربعة استعلامات لكل موظّف داخل حلقة — مكتبٌ بعشرين
            // موظفاً يفتح صفحته بثمانين استعلاماً. أربعة استعلامات
            // مجمَّعة تكفي مهما كثر الفريق.
            $ids = $employees->pluck('id');

            $ratings = HrPerformance::whereIn('employee_id', $ids)
                ->groupBy('employee_id')
                ->selectRaw('employee_id, AVG(rating) as avg_rating')
                ->pluck('avg_rating', 'employee_id');

            $caseCounts = LegalCase::whereIn('lawyer_id', $ids)
                ->groupBy('lawyer_id')
                ->selectRaw('lawyer_id, COUNT(*) as total')
                ->pluck('total', 'lawyer_id');

            $taskCounts = Task::whereIn('assigned_to', $ids)
                ->groupBy('assigned_to')
                ->selectRaw('assigned_to, COUNT(*) as total')
                ->pluck('total', 'assigned_to');

            $doneCounts = Task::whereIn('assigned_to', $ids)
                ->where('status', 'completed')
                ->groupBy('assigned_to')
                ->selectRaw('assigned_to, COUNT(*) as total')
                ->pluck('total', 'assigned_to');

            foreach ($employees as $emp) {
                $avgRating = $ratings[$emp->id] ?? null;

                $chartData[] = [
                    'name' => $emp->name,
                    'rating' => round((float) ($avgRating ?? 0), 1),
                    'cases' => (int) ($caseCounts[$emp->id] ?? 0),
                    'tasks' => (int) ($taskCounts[$emp->id] ?? 0),
                    'tasks_done' => (int) ($doneCounts[$emp->id] ?? 0),
                ];

                if ($avgRating >= 4) {
                    $ratingDistribution['excellent']++;
                } elseif ($avgRating >= 3) {
                    $ratingDistribution['good']++;
                } else {
                    $ratingDistribution['poor']++;
                }
            }
        }

        // الحضور: سجلّ اليوم لصاحب الشاشة، وشهرُه؛ وللإدارة حضور الفريق اليوم
        $attendanceToday = HrAttendance::todayFor($user->id);
        $attendanceMonth = HrAttendance::where('user_id', $user->id)
            ->whereDate('work_date', '>=', now('Asia/Muscat')->startOfMonth()->toDateString())
            ->orderByDesc('work_date')->get();
        $teamAttendance = $isAdmin
            ? HrAttendance::with('user')->whereDate('work_date', HrAttendance::today())->orderBy('check_in_at')->get()
            : collect();

        // تبويبا سجلّ الحضور والرواتب: بياناتهما تُحسب عند الحاجة
        // فقط — صفحةُ التقييمات لا تدفع ثمن استعلامات كشفٍ لا تعرضه.
        $extra = $this->tabData($request, $tab, $isAdmin, $employees);

        return view('hr.index', array_merge(
            compact('tab', 'employees', 'performances', 'bonuses', 'penalties', 'leaves', 'stats', 'chartData', 'ratingDistribution', 'attendanceToday', 'attendanceMonth', 'teamAttendance'),
            $extra
        ));
    }

    /** هل يُدير الرواتب؟ — الشرط نفسه الذي يحرس المسار والمتحكّم. */
    private function canManageSalaries(): bool
    {
        $u = auth()->user();

        return $u && ($u->isDeveloper() || $u->role === 'admin' || $u->hasPermission('salaries.manage'));
    }

    /**
     * بيانات التبويبين المنقولين.
     *
     * الرواتب لا تُحسب إلا لمن يملك رؤيتها: حسابُها ثم إخفاؤها في
     * القالب يعني أنّ الأرقام مرّت في الذاكرة وربما في سجلّ استعلامات.
     */
    private function tabData(Request $request, string $tab, bool $isAdmin, $employees): array
    {
        $canManageSalaries = $this->canManageSalaries();
        $out = ['canManageSalaries' => $canManageSalaries];

        if ($tab === 'attendance_log') {
            $user = auth()->user();
            $manager = $isAdmin || $user->hasPermission('attendance.manage');

            $range = in_array($request->get('range'), ['day', 'week', 'month'], true)
                ? $request->get('range') : 'day';

            try {
                $date = $request->filled('date')
                    ? CarbonImmutable::parse($request->get('date'))
                    : CarbonImmutable::now('Asia/Muscat');
            } catch (\Throwable) {
                $date = CarbonImmutable::now('Asia/Muscat');
            }

            [$from, $to] = match ($range) {
                'week' => [$date->startOfWeek(CarbonImmutable::SATURDAY), $date->endOfWeek(CarbonImmutable::FRIDAY)],
                'month' => [$date->startOfMonth(), $date->endOfMonth()],
                default => [$date, $date],
            };

            // whereDate لا whereBetween: العمود يُحفظ سلسلةً كاملة
            $q = HrAttendance::with('user')
                ->whereDate('work_date', '>=', $from->toDateString())
                ->whereDate('work_date', '<=', $to->toDateString());

            if (! $manager) {
                $q->where('user_id', $user->id);
            } elseif ($request->filled('employee_id')) {
                $q->where('user_id', (int) $request->get('employee_id'));
            }

            if ($request->get('status') === 'present') {
                $q->whereNull('check_out_at');
            } elseif ($request->get('status') === 'completed') {
                $q->whereNotNull('check_out_at');
            }

            $out += [
                'isManagerAtt' => $manager,
                'attRange' => $range,
                'attDate' => $date,
                'attRecords' => $q->orderByDesc('work_date')->orderBy('check_in_at')
                    ->paginate(50)->withQueryString(),
                'attStats' => $manager ? $this->attendanceStats($employees) : null,
                'attBoard' => $manager ? $this->attendanceBoard($employees) : collect(),
            ];
        }

        if ($tab === 'salaries' && $canManageSalaries) {
            $period = (string) $request->get('period', '');
            $period = preg_match('/^\d{4}-\d{2}$/', $period) ? $period : Payroll::currentPeriod();

            $payslips = $employees->map(fn (User $e) => Payroll::payslip($e, $period));

            $out += [
                'payPeriod' => $period,
                'payslips' => $payslips,
                'payTotals' => [
                    'gross' => round($payslips->sum('gross'), 2),
                    'deductions' => round($payslips->sum('deductions'), 2),
                    'net' => round($payslips->sum('net'), 2),
                    'without_salary' => $payslips->where('has_salary', false)->count(),
                ],
                'monthDaysMode' => Payroll::monthDaysMode(),
            ];
        }

        return $out;
    }

    /** عدّادات اليوم — استعلامان لا استعلامٌ لكل موظف. */
    private function attendanceStats($employees): array
    {
        $today = HrAttendance::today();
        $records = HrAttendance::whereDate('work_date', $today)->get()->keyBy('user_id');

        $onLeave = HrLeave::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->pluck('employee_id')->unique();

        return [
            'present' => $records->whereNull('check_out_at')->count(),
            'completed' => $records->whereNotNull('check_out_at')->count(),
            'on_leave' => $employees->filter(fn ($e) => $onLeave->contains($e->id))->count(),
            'absent' => $employees->filter(
                fn ($e) => ! $records->has($e->id) && ! $onLeave->contains($e->id)
            )->count(),
        ];
    }

    private function attendanceBoard($employees)
    {
        $today = HrAttendance::today();
        $records = HrAttendance::whereDate('work_date', $today)->get()->keyBy('user_id');

        $onLeave = HrLeave::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->pluck('employee_id')->unique();

        return $employees->map(function ($e) use ($records, $onLeave) {
            $rec = $records->get($e->id);

            return [
                'employee' => $e,
                'status' => ($onLeave->contains($e->id) && ! $rec) ? 'on_leave' : AttendanceGuard::statusOf($rec),
                'record' => $rec,
            ];
        });
    }

    /**
     * تسجيل الحضور اليدوي.
     *
     * الزرّ والدخول التلقائي يمرّان من الباب نفسه: كتابة الحالة في
     * موضعين جعلت الزرّ يترك status على «حاضر» بعد الانصراف —
     * أمسكه اختبار، ومصدرٌ واحد يمنع عودته.
     */
    public function checkIn()
    {
        $user = auth()->user();

        try {
            $existing = HrAttendance::todayFor($user->id);

            if (! $existing) {
                HrAttendance::create([
                    'user_id' => $user->id,
                    'work_date' => HrAttendance::today(),
                    'check_in_at' => now(),
                    'status' => 'present',
                    'source' => 'manual',
                ]);
            }
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // سجّل حضوره من جهاز آخر في نفس اللحظة — الموجود يكفي
        }

        return redirect()->route('hr.index', ['tab' => 'attendance'])
            ->with('success', 'سُجّل حضورك. يوماً موفقاً.');
    }

    public function checkOut()
    {
        $user = auth()->user();

        // نفس الدالّة التي يستعملها الخروج من النظام: وقتٌ واحد،
        // ومدّةٌ واحدة، وحالةٌ واحدة — أياً كان الزرّ الذي ضُغط.
        $record = \App\Support\AttendanceGuard::checkOutOnLogout($user);

        if (! $record && ! HrAttendance::todayFor($user->id) && ! HrAttendance::openFor($user->id)) {
            return redirect()->route('hr.index', ['tab' => 'attendance'])
                ->withErrors(['attendance' => 'لم تسجّل حضوراً اليوم بعد.']);
        }

        return redirect()->route('hr.index', ['tab' => 'attendance'])
            ->with('success', 'سُجّل انصرافك.');
    }

    public function storePerformance(Request $request)
    {
        abort_unless($this->isAdmin(), 403);
        $data = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'review_date' => 'required|date',
            'rating' => 'required|integer|min:1|max:5',
            'notes' => 'nullable|string',
        ]);
        $data['reviewer_id'] = auth()->id();
        HrPerformance::create($data);
        return redirect()->route('hr.index', ['tab' => 'performance'])->with('success', 'تم إضافة التقييم');
    }

    public function storeBonus(Request $request)
    {
        abort_unless($this->isAdmin(), 403);
        $data = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string',
            'date' => 'required|date',
        ]);
        $data['given_by'] = auth()->id();
        HrBonus::create($data);
        return redirect()->route('hr.index', ['tab' => 'bonuses'])->with('success', 'تم إضافة المكافأة');
    }

    public function storePenalty(Request $request)
    {
        abort_unless($this->isAdmin(), 403);
        $data = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'amount' => 'nullable|numeric|min:0',
            'reason' => 'required|string',
            'date' => 'required|date',
        ]);
        $data['given_by'] = auth()->id();
        HrPenalty::create($data);
        return redirect()->route('hr.index', ['tab' => 'penalties'])->with('success', 'تم إضافة الجزاء');
    }

    public function storeLeave(Request $request)
    {
        $user = auth()->user();
        $canAssign = in_array($user->role, ['developer', 'admin']);

        $data = $request->validate([
            'employee_id' => $canAssign ? 'required|exists:users,id' : 'nullable',
            'leave_type_id' => 'nullable|exists:hr_leave_types,id',
            'type' => 'nullable|in:annual,sick,emergency,maternity,unpaid,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        $data['employee_id'] = $canAssign ? $data['employee_id'] : $user->id;
        $data['status'] = 'pending';

        // النموذج الجديد يرسل leave_type_id، والقديم يرسل type. نقبل
        // الاثنين ونملأ الناقص من الموجود، فلا تنكسر صفحةٌ لم تُحدَّث
        // بعدُ ولا يبقى صفٌّ بلا نوع.
        $type = null;

        if (! empty($data['leave_type_id'])) {
            $type = HrLeaveType::find($data['leave_type_id']);
            $data['type'] = $type?->code && in_array($type->code, ['annual','sick','emergency','maternity','unpaid','other'], true)
                ? $type->code
                : 'other';
        } elseif (! empty($data['type'])) {
            $type = HrLeaveType::where('code', $data['type'])->first();
            $data['leave_type_id'] = $type?->id;
        } else {
            return back()->withErrors(['leave_type_id' => 'اختر نوع الإجازة.'])->withInput();
        }

        $data['days'] = (int) \Carbon\CarbonImmutable::parse($data['start_date'])
            ->diffInDays(\Carbon\CarbonImmutable::parse($data['end_date'])) + 1;

        $leave = HrLeave::create($data);

        // Reload to get employee relationship
        $leave->load('employee');

        // Notify all admins
        $admins = User::whereIn('role', ['developer', 'admin'])->get();
        foreach ($admins as $admin) {
            \App\Support\Notify::send(
                userId: $admin->id,
                titleKey: 'app.notif_leave_new_title',
                messageKey: 'app.notif_leave_new_body',
                params: ['employee' => $leave->employee->name ?? __('app.employee'), 'type' => $leave->typeName()],
                type: Notification::TYPE_INFO,
                notifiableType: 'App\\Models\\HrLeave',
                notifiableId: $leave->id,
            );
        }

        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'تم تقديم طلب الإجازة');
    }

    public function approveLeave(HrLeave $leave)
    {
        abort_unless($this->isAdmin(), 403);

        $leave->loadMissing('leaveType', 'employee');

        // الخصم يُحسب عند الاعتماد ويُخزَّن: تعديل الراتب بعد شهرين
        // لا يجوز أن يُغيّر خصم إجازةٍ اعتُمدت على راتب ذلك الحين.
        $deduction = Payroll::deductionForLeave($leave);

        $leave->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'days' => $leave->days ?: $deduction['days'],
            'deduction_amount' => $deduction['amount'] > 0 ? $deduction['amount'] : null,
        ]);

        // للموظف: خبر الاعتماد وحده — بلا رقم ولا خصم
        \App\Support\Notify::send(
            userId: $leave->employee_id,
            titleKey: 'app.notif_leave_approved_title',
            messageKey: 'app.notif_leave_approved_body',
            params: ['type' => $leave->typeName()],
            type: Notification::TYPE_SUCCESS,
            notifiableType: 'App\\Models\\HrLeave',
            notifiableId: $leave->id,
        );

        // للمدير وحده: الأيام والمبلغ. لا تُرسَل لمحامٍ ولا لموظف،
        // ولا لصاحب الإجازة نفسه ولو كان مديراً لغيره.
        if ($deduction['amount'] > 0) {
            $managers = User::whereIn('role', ['developer', 'admin'])
                ->where('id', '!=', $leave->employee_id)
                ->get();

            foreach ($managers as $manager) {
                \App\Support\Notify::send(
                    userId: $manager->id,
                    titleKey: 'app.notif_leave_deduction_title',
                    messageKey: 'app.notif_leave_deduction_body',
                    params: [
                        'employee' => $leave->employee->name ?? '—',
                        'days' => $deduction['days'],
                        'amount' => number_format($deduction['amount'], 2),
                    ],
                    type: Notification::TYPE_WARNING,
                    notifiableType: 'App\\Models\\HrLeave',
                    notifiableId: $leave->id,
                );
            }
        }

        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'تم الموافقة على الإجازة');
    }

    public function rejectLeave(HrLeave $leave)
    {
        abort_unless($this->isAdmin(), 403);
        // الرفض يمحو أي خصمٍ محسوب: طلبٌ مرفوض لا يكلّف صاحبه ريالاً
        $leave->update(['status' => 'rejected', 'approved_by' => auth()->id(), 'deduction_amount' => null]);

        \App\Support\Notify::send(
            userId: $leave->employee_id,
            titleKey: 'app.notif_leave_rejected_title',
            messageKey: 'app.notif_leave_rejected_body',
            params: ['type' => $leave->typeName()],
            type: Notification::TYPE_WARNING,
            notifiableType: 'App\\Models\\HrLeave',
            notifiableId: $leave->id,
        );

        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'تم رفض الإجازة');
    }

    public function destroyPerformance(HrPerformance $performance)
    {
        abort_unless($this->isAdmin(), 403);
        $performance->delete();
        return redirect()->route('hr.index', ['tab' => 'performance'])->with('success', 'تم حذف التقييم');
    }

    public function destroyBonus(HrBonus $bonus)
    {
        abort_unless($this->isAdmin(), 403);
        $bonus->delete();
        return redirect()->route('hr.index', ['tab' => 'bonuses'])->with('success', 'تم حذف المكافأة');
    }

    public function destroyPenalty(HrPenalty $penalty)
    {
        abort_unless($this->isAdmin(), 403);
        $penalty->delete();
        return redirect()->route('hr.index', ['tab' => 'penalties'])->with('success', 'تم حذف الجزاء');
    }

    public function destroyLeave(HrLeave $leave)
    {
        abort_unless($this->isAdmin(), 403);
        $leave->delete();
        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'تم حذف الإجازة');
    }
}
