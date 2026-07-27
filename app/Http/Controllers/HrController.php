<?php

namespace App\Http\Controllers;

use App\Models\HrBonus;
use App\Models\HrPenalty;
use App\Models\HrPerformance;
use App\Models\User;
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

        $stats = [
            'total_employees' => $employees->count(),
            'avg_rating' => HrPerformance::avg('rating'),
            'total_bonuses' => HrBonus::sum('amount'),
            'total_penalties' => HrPenalty::sum('amount'),
        ];

        return view('hr.index', compact('tab', 'employees', 'performances', 'bonuses', 'penalties', 'stats'));
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
}
