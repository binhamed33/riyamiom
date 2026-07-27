<?php

namespace App\Http\Controllers;

use App\Models\HrBonus;
use App\Models\HrLeave;
use App\Models\HrPenalty;
use App\Models\HrPerformance;
use App\Models\User;
use App\Models\LegalCase;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'employees');
        $employees = User::whereIn('role', ['admin', 'lawyer', 'staff'])->get();

        $performances = HrPerformance::with(['employee', 'reviewer'])->latest()->paginate(20);
        $bonuses = HrBonus::with(['employee', 'giver'])->latest()->paginate(20);
        $penalties = HrPenalty::with(['employee', 'giver'])->latest()->paginate(20);
        $leaves = HrLeave::with(['employee', 'approver'])->latest()->paginate(20);

        $stats = [
            'total_employees' => $employees->count(),
            'avg_rating' => HrPerformance::avg('rating'),
            'total_bonuses' => HrBonus::sum('amount'),
            'total_penalties' => HrPenalty::sum('amount'),
            'pending_leaves' => HrLeave::where('status', 'pending')->count(),
        ];

        // Chart data: employee performance vs cases
        $chartData = [];
        foreach ($employees as $emp) {
            $casesCount = LegalCase::where('lawyer_id', $emp->id)->count();
            $tasksCount = Task::where('assigned_to', $emp->id)->count();
            $tasksDone = Task::where('assigned_to', $emp->id)->where('status', 'completed')->count();
            $avgRating = HrPerformance::where('employee_id', $emp->id)->avg('rating');
            $bonusTotal = HrBonus::where('employee_id', $emp->id)->sum('amount');
            $chartData[] = [
                'name' => $emp->name,
                'cases' => $casesCount,
                'tasks' => $tasksCount,
                'tasks_done' => $tasksDone,
                'rating' => round($avgRating ?? 0, 1),
                'bonuses' => round($bonusTotal, 2),
            ];
        }

        return view('hr.index', compact('tab', 'employees', 'performances', 'bonuses', 'penalties', 'leaves', 'stats', 'chartData'));
    }

    public function storePerformance(Request $request)
    {
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
        $data = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'type' => 'required|in:annual,sick,emergency,maternity,unpaid,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);
        $data['status'] = 'pending';
        HrLeave::create($data);
        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'تم تقديم طلب الإجازة');
    }

    public function approveLeave(HrLeave $leave)
    {
        $leave->update(['status' => 'approved', 'approved_by' => auth()->id()]);
        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'تم الموافقة على الإجازة');
    }

    public function rejectLeave(HrLeave $leave)
    {
        $leave->update(['status' => 'rejected', 'approved_by' => auth()->id()]);
        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'تم رفض الإجازة');
    }

    public function destroyPerformance(HrPerformance $performance)
    {
        $performance->delete();
        return redirect()->route('hr.index', ['tab' => 'performance'])->with('success', 'تم حذف التقييم');
    }

    public function destroyBonus(HrBonus $bonus)
    {
        $bonus->delete();
        return redirect()->route('hr.index', ['tab' => 'bonuses'])->with('success', 'تم حذف المكافأة');
    }

    public function destroyPenalty(HrPenalty $penalty)
    {
        $penalty->delete();
        return redirect()->route('hr.index', ['tab' => 'penalties'])->with('success', 'تم حذف الجزاء');
    }

    public function destroyLeave(HrLeave $leave)
    {
        $leave->delete();
        return redirect()->route('hr.index', ['tab' => 'leaves'])->with('success', 'تم حذف الإجازة');
    }
}
