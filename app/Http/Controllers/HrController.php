<?php

namespace App\Http\Controllers;

use App\Models\HrBonus;
use App\Models\HrLeave;
use App\Models\HrPenalty;
use App\Models\HrPerformance;
use App\Models\Notification;
use App\Models\User;
use App\Models\LegalCase;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrController extends Controller
{
    protected function isAdmin(): bool
    {
        return in_array(auth()->user()->role, ['developer', 'admin', 'lawyer', 'staff']);
    }

    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'employees');
        $user = auth()->user();
        $isAdmin = $this->isAdmin();

        if ($isAdmin) {
            $employees = User::whereIn('role', ['admin', 'lawyer', 'staff'])->get();
        } else {
            $employees = auth()->user()->isGuest() ? collect() : User::where('id', $user->id)->get();
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
            'total_employees' => auth()->user()->isGuest() ? 0 : User::whereIn('role', ['admin', 'lawyer', 'staff'])->count(),
            'avg_rating' => HrPerformance::when(!$isAdmin, fn($q) => $q->where('employee_id', $user->id))->avg('rating'),
            'total_bonuses' => HrBonus::when(!$isAdmin, fn($q) => $q->where('employee_id', $user->id))->sum('amount'),
            'total_penalties' => HrPenalty::when(!$isAdmin, fn($q) => $q->where('employee_id', $user->id))->sum('amount'),
            'pending_leaves' => HrLeave::where('status', 'pending')->when(!$isAdmin, fn($q) => $q->where('employee_id', $user->id))->count(),
        ];

        $chartData = [];
        $ratingDistribution = ['excellent' => 0, 'good' => 0, 'poor' => 0];
        if ($isAdmin) {
            foreach ($employees as $emp) {
                $avgRating = HrPerformance::where('employee_id', $emp->id)->avg('rating');
                $chartData[] = [
                    'name' => $emp->name,
                    'rating' => round($avgRating ?? 0, 1),
                    'cases' => LegalCase::where('lawyer_id', $emp->id)->count(),
                    'tasks' => Task::where('assigned_to', $emp->id)->count(),
                    'tasks_done' => Task::where('assigned_to', $emp->id)->where('status', 'completed')->count(),
                ];
                if ($avgRating >= 4) $ratingDistribution['excellent']++;
                elseif ($avgRating >= 3) $ratingDistribution['good']++;
                else $ratingDistribution['poor']++;
            }
        }

        return view('hr.index', compact('tab', 'employees', 'performances', 'bonuses', 'penalties', 'leaves', 'stats', 'chartData', 'ratingDistribution'));
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
            'type' => 'required|in:annual,sick,emergency,maternity,unpaid,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        $data['employee_id'] = $canAssign ? $data['employee_id'] : $user->id;
        $data['status'] = 'pending';
        $leave = HrLeave::create($data);

        // Reload to get employee relationship
        $leave->load('employee');

        // Notify all admins
        $admins = User::whereIn('role', ['developer', 'admin'])->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => 'طلب إجازة جديد',
                'message' => 'قدم ' . ($leave->employee->name ?? 'موظف') . ' طلب إجازة ' . __('hr_leave_type_' . $leave->type),
                'type' => Notification::TYPE_INFO,
                'notifiable_type' => 'App\\Models\\HrLeave',
                'notifiable_id' => $leave->id,
            ]);
        }

        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'تم تقديم طلب الإجازة');
    }

    public function approveLeave(HrLeave $leave)
    {
        abort_unless($this->isAdmin(), 403);
        $leave->update(['status' => 'approved', 'approved_by' => auth()->id()]);

        Notification::create([
            'user_id' => $leave->employee_id,
            'title' => 'تم الموافقة على الإجازة',
            'message' => 'تمت الموافقة على طلب إجازتك (' . __('hr_leave_type_' . $leave->type) . ')',
            'type' => Notification::TYPE_SUCCESS,
            'notifiable_type' => 'App\\Models\\HrLeave',
            'notifiable_id' => $leave->id,
        ]);

        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'تم الموافقة على الإجازة');
    }

    public function rejectLeave(HrLeave $leave)
    {
        abort_unless($this->isAdmin(), 403);
        $leave->update(['status' => 'rejected', 'approved_by' => auth()->id()]);

        Notification::create([
            'user_id' => $leave->employee_id,
            'title' => 'تم رفض الإجازة',
            'message' => 'تم رفض طلب إجازتك (' . __('hr_leave_type_' . $leave->type) . ')',
            'type' => Notification::TYPE_WARNING,
            'notifiable_type' => 'App\\Models\\HrLeave',
            'notifiable_id' => $leave->id,
        ]);

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
