<?php

namespace App\Http\Controllers;

use App\Models\HrAttendance;
use App\Models\HrBonus;
use App\Models\HrLeave;
use App\Models\HrLeaveType;
use App\Models\HrPenalty;
use App\Models\HrPerformance;
use App\Models\Notification;
use App\Models\HrSalary;
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

        // الحضور: سجلّ اليوم لصاحب الشاشة — أو سجلُّ أمسِ المفتوح لمن يعمل
        // بعد منتصف الليل — وشهرُه؛ وللإدارة حضور الفريق اليوم
        $attendanceToday = HrAttendance::todayFor($user->id) ?? AttendanceGuard::openRecord($user);
        $attendanceMonth = HrAttendance::where('user_id', $user->id)
            ->whereDate('work_date', '>=', now('Asia/Muscat')->startOfMonth()->toDateString())
            ->orderByDesc('work_date')->get();
        // ومعه ما بقي مفتوحاً من أيّامٍ سبقت: كان فرعُ «بلا انصراف» في الجدول
        // لا يُعرض أبداً لأنّ الاستعلامَ يومُ اليوم وحده
        $teamAttendance = $isAdmin
            ? HrAttendance::with('user')
                ->where(fn ($q) => $q->whereDate('work_date', HrAttendance::today())
                    ->orWhere(fn ($o) => $o->whereNull('check_out_at')->whereDate('work_date', '<', HrAttendance::today())))
                ->orderBy('check_in_at')->get()
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

            // من مُنح «إدارة حضور الفريق» كان يرى جدولَ الجميع وعدّاداتِ نفسِه
            // وقائمةَ موظّفين فيها اسمُه وحدَه — فتبدو الصلاحيّةُ معطوبة
            if ($manager && ! $isAdmin) {
                $employees = User::whereIn('role', ['admin', 'lawyer', 'staff'])->orderBy('name')->get();
            }

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
            } elseif ($request->get('status') === 'unclosed') {
                // منسيٌّ مفتوحاً من يومٍ سبق — يحتاج تصحيحاً
                $q->whereNull('check_out_at')->whereDate('work_date', '<', HrAttendance::today());
            } elseif ($request->get('status') === 'inferred') {
                // ما كتبه النظامُ لا صاحبُه: للمراجعة مع الموظّف
                $q->where(fn ($w) => $w->whereIn('closed_by', [HrAttendance::CLOSED_BY_CAP, HrAttendance::CLOSED_BY_SEEN])
                    ->orWhere(fn ($l) => $l->whereNull('closed_by')->whereIn('source', ['auto_capped', 'auto_closed'])));
            }

            // مجموعُ المدى لكلّ موظّف — من استعلامٍ مجمَّع لا من صفحةٍ مقطوعة:
            // «كم ساعة عمل فلان في سبتمبر؟» كان جمعَ اثنين وعشرين صفّاً بالعين
            $totals = (clone $q)->reorder()->get(['user_id', 'minutes', 'inferred_minutes', 'closed_by', 'source', 'check_out_at'])
                ->groupBy('user_id')
                ->map(fn ($rows) => [
                    'minutes' => (int) $rows->sum('minutes'),
                    'days' => $rows->count(),
                    'inferred' => $rows->filter(fn ($r) => $r->hasInferredTime())->count(),
                    'open' => $rows->whereNull('check_out_at')->count(),
                ]);

            $out += [
                'attTotals' => $totals,
                'attEmployees' => $employees,
                'attQuery' => (clone $q)->orderByDesc('work_date')->orderBy('check_in_at'),
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
                // ═══ التعديلُ لا يبدأ من صفر ═══
                //
                // كان نموذجُ «تحديد راتب» يفتح فارغاً دائماً، فمن أراد تعديلَ
                // راتبٍ قائم رأى صفراً في الأساسيّ وظنّ أنّ التعديل غيرُ ممكن
                // — وهو ممكن، لكنّ الشاشةَ لا تقوله. فالرواتبُ القائمةُ تُرسل
                // مع القائمة، ويُملأ النموذجُ بها عند اختيار الموظّف.
                'salaries' => HrSalary::whereIn('employee_id', $employees->pluck('id'))->get()->keyBy('employee_id'),
                // ورابطُ «تعديل» من الجدول ومن الكشف يفتح النموذجَ على صاحبه
                'salaryEmployee' => (int) $request->get('employee', 0),
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
    /**
     * كشفُ الحضور للمدى المصفّى — ملفَّ CSV للمحاسب.
     *
     * الاستعلامُ نفسُه الذي يعرضه التبويب، بلا تقطيع صفحات. والخليّةُ التي
     * تبدأ بـ= أو + أو - أو @ تُسبق بفاصلةٍ عليا: اسمٌ أو سببٌ يُكتب هكذا
     * يُنفَّذ معادلةً في إكسل عند فتح الملفّ.
     */
    public function exportAttendance(Request $request)
    {
        $user = auth()->user();
        abort_unless($this->isAdmin() || $user->hasPermission('attendance.manage'), 403);

        $employees = User::whereIn('role', ['admin', 'lawyer', 'staff'])->get();
        $data = $this->tabData($request->merge(['tab' => 'attendance_log']), 'attendance_log', $this->isAdmin(), $employees);
        $rows = $data['attQuery']->with('user')->get();

        // ومعها التبويبُ والرجوع: إكسل يقصّ الفراغَ الأوّل ثمّ ينفّذ ما بعده
        $safe = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v;
        $tz = 'Asia/Muscat';

        $csv = fopen('php://temp', 'r+');
        fwrite($csv, "\xEF\xBB\xBF");
        fputcsv($csv, ['الموظف', 'التاريخ', 'الحضور', 'الانصراف', 'الدقائق', 'المدة', 'الفترات', 'الكاتب', 'ملاحظة']);
        foreach ($rows as $r) {
            fputcsv($csv, [
                $safe($r->user->name ?? '—'),
                $r->work_date->toDateString(),
                $r->check_in_at->timezone($tz)->format('H:i'),
                $r->checkOutDisplay() ?? '',
                $r->minutes === null ? '' : (int) $r->minutes,
                $r->minutes === null ? '' : \App\Support\Duration::human((int) $r->minutes),
                (int) ($r->intervals ?: 1),
                // الكاتبُ صراحةً لكلّ قيمة: «غير موثَّق» لما سبق التوثيق، لا «بالزرّ» افتراضاً
                match ($r->closed_by ?? ($r->check_out_at ? HrAttendance::CLOSED_BY_LEGACY : null)) {
                    null => 'مفتوح',
                    HrAttendance::CLOSED_BY_BUTTON => $r->inferredLabel() ?? 'بالزرّ',
                    HrAttendance::CLOSED_BY_LEGACY => 'غير موثَّق',
                    default => $r->inferredLabel() ?? 'غير موثَّق',
                },
                $safe($r->note),
            ]);
        }
        rewind($csv);
        $body = stream_get_contents($csv);
        fclose($csv);

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="attendance-' . $data['attDate']->toDateString() . '-' . $data['attRange'] . '.csv"',
        ]);
    }

    /** يومٌ يضيفه الإداري لموظّفٍ لم يدخل النظام — انظر AttendanceGuard::addDay. */
    public function addDay(Request $request)
    {
        $user = auth()->user();
        abort_unless($this->isAdmin() || $user->hasPermission('attendance.manage'), 403);

        $data = $request->validate([
            // موظّفٌ نشِطٌ له حضور — لا موكّلٌ ولا حسابٌ معطَّل
            'employee_id' => ['required', \Illuminate\Validation\Rule::exists('users', 'id')->whereIn('role', ['admin', 'lawyer', 'staff'])->where('is_active', true)],
            // صيغةٌ ثابتة: «date» تقبل ISO بوقتٍ ومنطقة فيسقط التركيبُ بعدها بخطأ خادم
            'work_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'check_in' => 'required|date_format:H:i',
            'check_out' => 'required|date_format:H:i',
            'next_day' => 'nullable|boolean',
            'reason' => 'required|string|max:120',
        ], [], ['employee_id' => 'الموظف', 'work_date' => 'اليوم', 'check_in' => 'الحضور', 'check_out' => 'الانصراف', 'reason' => 'السبب']);

        $employee = User::findOrFail($data['employee_id']);
        abort_if(! $this->isAdmin() && $employee->id === $user->id, 403, 'لا يضيف الموظّفُ يوماً لنفسه.');

        $in = \Carbon\Carbon::parse($data['work_date'] . ' ' . $data['check_in'], 'Asia/Muscat');
        $out = \Carbon\Carbon::parse($data['work_date'] . ' ' . $data['check_out'], 'Asia/Muscat');
        if ($request->boolean('next_day')) {
            $out->addDay();
        }

        if ($out->lessThanOrEqualTo($in)) {
            return back()->withInput()->withErrors(['add_check_out' => 'الانصراف يجب أن يكون بعد الحضور.']);
        }

        if ($out->greaterThan(now())) {
            return back()->withInput()->withErrors(['add_check_out' => 'لا يُكتب انصرافٌ لم يقع بعد.']);
        }

        $record = AttendanceGuard::addDay($employee, $user, $in, $out, $data['reason']);

        if (! $record) {
            return back()->withInput()->withErrors(['add_check_out' => 'لهذا الموظّف سجلٌّ في هذا اليوم أصلاً — صحّحه من الجدول.']);
        }

        return back()->with('success', 'أُضيف يومُ ' . $employee->name . ': ' . $in->format('h:i A') . ' → ' . $out->format('h:i A') . '.');
    }

    /** سجلّاتُ اليوم، ومعها المفتوحُ منذ أقلَّ من يوم: من حضر ليلاً وما زال يعمل حاضرٌ لا غائب. */
    private function todaysRecords()
    {
        $today = HrAttendance::today();

        return HrAttendance::where(fn ($q) => $q->whereDate('work_date', $today)
                ->orWhere(fn ($o) => $o->whereNull('check_out_at')->where('check_in_at', '>=', now()->subDay())))
            ->orderBy('work_date')->get()->keyBy('user_id');
    }

    private function attendanceStats($employees): array
    {
        $today = HrAttendance::today();
        $records = $this->todaysRecords();
        // حسابٌ معطَّل ليس غائباً: كان المحامي المستقيل يُعدّ غائباً كلَّ يوم
        $employees = $employees->where('is_active', true);

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
        $records = $this->todaysRecords();
        $employees = $employees->where('is_active', true);

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

        // المطوّر والموكّل لا حضورَ لهما: كان الزرُّ يُنشئ سجلاً ثمّ يُقفله السقف
        abort_unless(AttendanceGuard::tracks($user), 403);

        try {
            // سجلُّ أمسِ المفتوح (ليلةُ عمل) هو سجلُّ هذا الحضور — لا سجلٌّ ثانٍ
            $existing = HrAttendance::todayFor($user->id) ?? AttendanceGuard::openWithinDay($user->id);

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

        // دالّةٌ واحدة لكلّ زرّ انصراف (لوحة القيادة، تبويب الحضور،
        // إشعار الحضور): وقتٌ واحد، ومدّةٌ واحدة، وحالةٌ واحدة.
        $record = \App\Support\AttendanceGuard::checkOut($user);

        if (! $record) {
            $today = HrAttendance::todayFor($user->id);

            // تبويبان: ضغط الانصرافَ في أحدهما ثمّ في الآخر «للتأكيد» — فكان
            // يُقال له «سُجّل انصرافك» والوقتُ المحفوظ هو الأوّل، ويظنّ
            // الثاني هو المسجَّل ويعترض على الكشف بعدها
            $message = $today?->check_out_at
                ? 'سجلُّ اليوم مقفَلٌ منذ ' . $today->check_out_at->timezone('Asia/Muscat')->format('h:i A') . ' — «استئناف الدوام» يفتحه إن كنت ما زلت في دوامك.'
                : 'لم تسجّل حضوراً اليوم بعد.';

            return redirect()->route('hr.index', ['tab' => 'attendance'])
                ->withErrors(['attendance' => $message]);
        }

        // ولا يُسأل «أما زلت في دوامك؟» في الشاشة نفسِها التي أكّدت انصرافَه
        request()->session()->put('attendance_resume_dismissed', HrAttendance::today());

        return redirect()->route('hr.index', ['tab' => 'attendance'])
            ->with('success', 'سُجّل انصرافك.');
    }

    /**
     * تصحيحُ سجلّ حضورٍ بيد الإداري — بوقتين وسببٍ مكتوب.
     *
     * محامٍ وجد انصرافَه ١:٣٦ وهو خرج ٣:٥٧ ولم يكن في النظام ما يصحّحه
     * سوى قاعدة البيانات. هنا يصحّح الإداري ويبقى الأثر: القديمُ والجديدُ
     * والسببُ ومَن صحّح — في الملاحظة وفي سجلّ التدقيق.
     */
    public function correct(Request $request, HrAttendance $record)
    {
        $user = auth()->user();
        abort_unless($this->isAdmin() || $user->hasPermission('attendance.manage'), 403);
        // من مُنح إدارةَ الحضور لا يصحّح سجلَّ نفسِه — مديرُ المكتب وحدَه، وأثرُه مدوَّن
        abort_if(! $this->isAdmin() && $record->user_id === $user->id, 403, 'لا يصحّح الموظّفُ سجلَّ نفسه.');

        $data = $request->validate([
            'check_in' => 'required|date_format:H:i',
            'check_out' => 'required|date_format:H:i',
            'next_day' => 'nullable|boolean',
            'reason' => 'required|string|max:120',
        ], [], ['check_in' => 'الحضور', 'check_out' => 'الانصراف', 'reason' => 'السبب']);

        // الوقتان على يوم السجلّ بتوقيت المكتب، والانصرافُ في اليوم التالي إن
        // قيل صراحةً (ليلةُ عمل) — لا تخميناً من انصرافٍ يسبق الحضور
        $day = $record->work_date->toDateString();
        $in = \Carbon\Carbon::parse($day . ' ' . $data['check_in'], 'Asia/Muscat');
        $out = \Carbon\Carbon::parse($day . ' ' . $data['check_out'], 'Asia/Muscat');
        if ($request->boolean('next_day')) {
            $out->addDay();
        }

        if ($out->lessThanOrEqualTo($in)) {
            return back()->withInput(['record_id' => $record->id] + $data)
                ->withErrors(['check_out' => 'الانصراف يجب أن يكون بعد الحضور.']);
        }

        // انصرافٌ لم يقع بعد لا يُكتب — ولو بيد الإداري
        if ($out->greaterThan(now())) {
            return back()->withInput(['record_id' => $record->id] + $data)
                ->withErrors(['check_out' => 'لا يُكتب انصرافٌ لم يقع بعد.']);
        }

        \App\Support\AttendanceGuard::correct($record, $user, $in, $out, $data['reason']);

        return back()->with('success', 'صُحّح السجلّ: ' . $in->format('h:i A') . ' → ' . $out->format('h:i A') . ' (' . \App\Support\Duration::human($record->fresh()->minutes) . ').');
    }

    /**
     * استئنافُ يومٍ أُقفل — لمن عاد من استراحة الظهر فوجد «يومك مكتمل».
     *
     * السجلُّ نفسُه يُفتح وتبقى دقائقُه، وتبدأ فترةٌ جديدة من الآن؛
     * والانصرافُ التالي يضيف لا يستبدل. والزرُّ لا يخترع سجلاً: من لا
     * انصرافَ له اليوم لا شيءَ عنده يُستأنف.
     */
    public function resume()
    {
        $record = \App\Support\AttendanceGuard::resume(auth()->user());

        if (! $record) {
            $today = HrAttendance::todayFor(auth()->id());
            $message = match (true) {
                $today?->closed_by === HrAttendance::CLOSED_BY_MANAGER => 'هذا اليوم صحّحه الإداري — راجعه إن كان فيه خطأ.',
                $today?->check_out_at !== null => 'انصرافُك مضى عليه أكثرُ من ' . \App\Support\AttendanceGuard::RESUME_WINDOW_HOURS . ' ساعات — يومٌ انقضى؛ يومُ الغد يُفتح بحضوره.',
                default => 'لا انصرافَ مسجَّلاً اليوم يُستأنف بعده.',
            };

            return redirect()->route('hr.index', ['tab' => 'attendance'])->withErrors(['attendance' => $message]);
        }

        return redirect()->route('hr.index', ['tab' => 'attendance'])
            ->with('success', 'استُؤنف دوامك — ما سبق محفوظ، والدقائق تُحسب من الآن.');
    }

    /** «لستُ في دوامي»: إلغاءُ حضورٍ تلقائيٍّ حديث — انظر AttendanceGuard::cancellable. */
    public function cancel()
    {
        $cancelled = AttendanceGuard::cancelAutoCheckIn(auth()->user());

        return back()->with($cancelled ? 'success' : 'error', $cancelled
            ? 'أُلغي تسجيلُ الحضور — لم يُحسب لك شيء.'
            : 'لا يُلغى إلا حضورٌ تلقائيٌّ لم تمضِ عليه نصفُ ساعة.');
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
