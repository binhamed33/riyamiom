<?php

namespace App\Http\Controllers;

use App\Models\HrAttendance;
use App\Models\HrLeave;
use App\Models\User;
use App\Support\AttendanceGuard;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * لوحة الحضور.
 *
 * الموظف يرى سجلّه وحده؛ والمدير يرى الفريق. الفصل في الاستعلام
 * لا في العرض: صفحةٌ تُحمّل سجلات الجميع ثم تُخفي بعضها بـCSS
 * تُسلّمها كاملةً لمن يفتح «مصدر الصفحة».
 */
class AttendanceController extends Controller
{
    private function isManager(): bool
    {
        $u = auth()->user();

        return $u && ($u->isDeveloper() || $u->role === 'admin' || $u->hasPermission('attendance.manage'));
    }

    public function index(Request $request): View
    {
        $user = auth()->user();
        $manager = $this->isManager();

        $range = in_array($request->get('range'), ['day', 'week', 'month'], true)
            ? $request->get('range') : 'day';

        $date = $this->parseDate($request->get('date'));
        [$from, $to] = $this->window($date, $range);

        // whereDate لا whereBetween: العمود يُحفظ سلسلةً كاملة
        // ('2026-08-27 00:00:00')، فمقارنتها بـ'2026-08-27' نصّياً
        // تُخرجها من الحدّ الأعلى ويعود الجدول فارغاً — أمسكه اختبار.
        $query = HrAttendance::with('user')
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString());

        if (! $manager) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('user_id', (int) $request->get('employee_id'));
        }

        if ($request->filled('status') && in_array($request->get('status'), ['present', 'completed'], true)) {
            $request->get('status') === 'present'
                ? $query->whereNull('check_out_at')
                : $query->whereNotNull('check_out_at');
        }

        $records = $query->orderByDesc('work_date')->orderBy('check_in_at')->paginate(50)->withQueryString();

        $employees = $manager
            ? User::whereIn('role', ['admin', 'lawyer', 'staff'])->orderBy('name')->get()
            : collect([$user]);

        return view('attendance.index', [
            'records' => $records,
            'employees' => $employees,
            'isManager' => $manager,
            'range' => $range,
            'date' => $date,
            'from' => $from,
            'to' => $to,
            'stats' => $manager ? $this->todayStats($employees) : null,
            'board' => $manager ? $this->board($employees) : collect(),
            'myOpen' => AttendanceGuard::openRecord($user),
            'filters' => $request->only(['employee_id', 'status']),
        ]);
    }

    /**
     * «استمرار الحضور» — لا يُنشئ شيئاً ولا يُغلق شيئاً.
     *
     * يضع علامةً في الجلسة فقط، فلا يُسأل الموظف مرة أخرى اليوم.
     * السجلّ يبقى مفتوحاً كما هو — وهذا هو المقصود.
     */
    public function keepPresent(Request $request)
    {
        $request->session()->put('attendance_prompt_dismissed', HrAttendance::today());

        return back()->with('success', 'واصلْ عملك — حضورك ما يزال مسجّلاً.');
    }

    private function todayStats($employees): array
    {
        $today = HrAttendance::today();
        $records = HrAttendance::whereDate('work_date', $today)->get()->keyBy('user_id');

        $present = $records->whereNull('check_out_at')->count();
        $completed = $records->whereNotNull('check_out_at')->count();

        $onLeave = HrLeave::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->pluck('employee_id')->unique();

        $absent = $employees->filter(
            fn ($e) => ! $records->has($e->id) && ! $onLeave->contains($e->id)
        )->count();

        return [
            'present' => $present,
            'completed' => $completed,
            'absent' => $absent,
            'on_leave' => $employees->filter(fn ($e) => $onLeave->contains($e->id))->count(),
            'total' => $employees->count(),
        ];
    }

    /** حالة كل موظف الآن — استعلامان لا استعلامٌ لكل موظف. */
    private function board($employees)
    {
        $today = HrAttendance::today();
        $records = HrAttendance::whereDate('work_date', $today)->get()->keyBy('user_id');

        $onLeave = HrLeave::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->pluck('employee_id')->unique();

        return $employees->map(function ($e) use ($records, $onLeave) {
            $rec = $records->get($e->id);
            $status = $onLeave->contains($e->id) && ! $rec
                ? 'on_leave'
                : AttendanceGuard::statusOf($rec);

            return [
                'employee' => $e,
                'status' => $status,
                'record' => $rec,
            ];
        });
    }

    private function parseDate(?string $raw): CarbonImmutable
    {
        try {
            return $raw ? CarbonImmutable::parse($raw) : CarbonImmutable::now('Asia/Muscat');
        } catch (\Throwable) {
            return CarbonImmutable::now('Asia/Muscat');
        }
    }

    private function window(CarbonImmutable $date, string $range): array
    {
        return match ($range) {
            'week' => [$date->startOfWeek(CarbonImmutable::SATURDAY), $date->endOfWeek(CarbonImmutable::FRIDAY)],
            'month' => [$date->startOfMonth(), $date->endOfMonth()],
            default => [$date, $date],
        };
    }
}
