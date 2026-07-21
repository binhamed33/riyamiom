<?php

namespace App\Http\Controllers;

use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FeasibilityController extends Controller
{
    public function index(): View
    {
        $userIds = User::where('role', 'lawyer')->pluck('id');

        $stats = [];

        if ($userIds->isNotEmpty()) {
            // Cases grouped by lawyer_id and status
            $casesGrouped = LegalCase::whereIn('lawyer_id', $userIds)
                ->selectRaw('lawyer_id, status, count(*) as count')
                ->groupBy('lawyer_id', 'status')
                ->get()
                ->groupBy('lawyer_id');

            // Tasks grouped by assigned_to and status
            $tasksGrouped = Task::whereIn('assigned_to', $userIds)
                ->selectRaw('assigned_to, status, count(*) as count')
                ->groupBy('assigned_to', 'status')
                ->get()
                ->groupBy('assigned_to');

            // Task completed on time (completed_at <= due_date)
            $tasksCompliance = Task::whereIn('assigned_to', $userIds)
                ->where('status', 'completed')
                ->whereNotNull('due_date')
                ->whereNotNull('completed_at')
                ->selectRaw('assigned_to, count(*) as total, SUM(CASE WHEN completed_at <= due_date THEN 1 ELSE 0 END) as on_time')
                ->groupBy('assigned_to')
                ->get()
                ->keyBy('assigned_to');

            // First task date per lawyer
            $firstTaskDates = Task::whereIn('assigned_to', $userIds)
                ->selectRaw('assigned_to, MIN(created_at) as first_date')
                ->groupBy('assigned_to')
                ->get()
                ->keyBy('assigned_to');

            // Sessions via case relationship
            $sessionsByLawyer = Session::join('cases', 'court_sessions.case_id', '=', 'cases.id')
                ->whereIn('cases.lawyer_id', $userIds)
                ->selectRaw('cases.lawyer_id, count(*) as count')
                ->groupBy('cases.lawyer_id')
                ->get()
                ->keyBy('lawyer_id');

            // Total tasks (with and without due_date) per lawyer
            $tasksTotalWithDue = Task::whereIn('assigned_to', $userIds)
                ->selectRaw('assigned_to, count(*) as total, SUM(CASE WHEN due_date IS NOT NULL THEN 1 ELSE 0 END) as with_due')
                ->groupBy('assigned_to')
                ->get()
                ->keyBy('assigned_to');

            // Overdue tasks per lawyer
            $overdueTasksGrouped = Task::whereIn('assigned_to', $userIds)
                ->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->where('due_date', '<', now())
                ->selectRaw('assigned_to, count(*) as count')
                ->groupBy('assigned_to')
                ->get()
                ->keyBy('assigned_to');

            foreach ($userIds as $userId) {
                $c = $casesGrouped[$userId] ?? collect();
                $t = $tasksGrouped[$userId] ?? collect();
                $tc = $tasksCompliance[$userId] ?? null;
                $ft = $firstTaskDates[$userId] ?? null;
                $sl = $sessionsByLawyer[$userId] ?? null;
                $tt = $tasksTotalWithDue[$userId] ?? null;
                $ot = $overdueTasksGrouped[$userId] ?? null;

                $totalCases = (int) $c->sum('count');
                $wonCases = (int) $c->where('status', 'won')->first()?->count ?? 0;
                $lostCases = (int) $c->where('status', 'lost')->first()?->count ?? 0;
                $activeCases = (int) $c->where('status', 'active')->first()?->count ?? 0;
                $pendingCases = (int) $c->where('status', 'pending')->first()?->count ?? 0;
                $closedCases = (int) $c->where('status', 'closed')->first()?->count ?? 0;

                $totalTasks = (int) $t->sum('count');
                $completedTasks = (int) $t->where('status', 'completed')->first()?->count ?? 0;
                $pendingTasks = (int) $t->where('status', 'pending')->first()?->count ?? 0;
                $inProgressTasks = (int) $t->where('status', 'in_progress')->first()?->count ?? 0;

                $tasksWithDue = (int) ($tt->with_due ?? 0);
                $completedOnTime = (int) ($tc->on_time ?? 0);
                $overdueTasks = (int) ($ot->count ?? 0);
                $firstTaskDate = $ft->first_date ?? null;
                $totalSessions = (int) ($sl->count ?? 0);

                $stats[$userId] = compact(
                    'totalCases', 'wonCases', 'lostCases', 'activeCases', 'pendingCases', 'closedCases',
                    'totalTasks', 'completedTasks', 'pendingTasks', 'inProgressTasks',
                    'tasksWithDue', 'completedOnTime', 'overdueTasks', 'firstTaskDate', 'totalSessions'
                );
            }
        }

        $users = User::whereIn('id', $userIds)->get();

        $efficiencyData = $users->map(function (User $user) use ($stats) {
            $s = $stats[$user->id] ?? [];

            $totalDecided = ($s['wonCases'] ?? 0) + ($s['lostCases'] ?? 0);
            $successRate = $totalDecided > 0 ? round(($s['wonCases'] / $totalDecided) * 100, 1) : 0.0;

            $totalTasks = $s['totalTasks'] ?? 0;
            $completedTasks = $s['completedTasks'] ?? 0;
            $taskCompletion = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0.0;

            $tasksWithDue = $s['tasksWithDue'] ?? 0;
            $completedOnTime = $s['completedOnTime'] ?? 0;
            $deadlineCompliance = $tasksWithDue > 0 ? round(($completedOnTime / $tasksWithDue) * 100, 1) : 0.0;

            $firstTaskDate = $s['firstTaskDate'] ?? null;
            $activeDays = $firstTaskDate ? max(\Carbon\Carbon::parse($firstTaskDate)->diffInDays(now()), 1) : 1;
            $productivity = round(($completedTasks / $activeDays) * 100, 2);

            $overall = ($successRate * 0.35)
                + ($taskCompletion * 0.25)
                + ($deadlineCompliance * 0.25)
                + ($productivity * 0.15);

            return [
                'user' => $user,
                'total_cases' => $s['totalCases'] ?? 0,
                'won_cases' => $s['wonCases'] ?? 0,
                'lost_cases' => $s['lostCases'] ?? 0,
                'active_cases' => $s['activeCases'] ?? 0,
                'pending_cases' => $s['pendingCases'] ?? 0,
                'closed_cases' => $s['closedCases'] ?? 0,
                'total_tasks' => $s['totalTasks'] ?? 0,
                'completed_tasks' => $s['completedTasks'] ?? 0,
                'pending_tasks' => $s['pendingTasks'] ?? 0,
                'in_progress_tasks' => $s['inProgressTasks'] ?? 0,
                'overdue_tasks' => $s['overdueTasks'] ?? 0,
                'total_sessions' => $s['totalSessions'] ?? 0,
                'success_rate' => $successRate,
                'task_completion' => $taskCompletion,
                'deadline_compliance' => $deadlineCompliance,
                'productivity' => $productivity,
                'overall' => round($overall, 1),
                'active_days' => $activeDays,
            ];
        })->sortByDesc('overall')->values();

        $topPerformer = $efficiencyData->first();
        $leastPerformer = $efficiencyData->last();

        // Office-wide aggregate stats (cached 5 min)
        $officeStats = Cache::remember('feasibility_office_stats', 300, fn () => [
            'totalCasesAll' => LegalCase::count(),
            'totalTasksAll' => Task::count(),
            'completedTasksAll' => Task::where('status', 'completed')->count(),
            'wonCasesAll' => LegalCase::where('status', 'won')->count(),
            'lostCasesAll' => LegalCase::where('status', 'lost')->count(),
        ]);

        $totalCasesAll = $officeStats['totalCasesAll'];
        $totalTasksAll = $officeStats['totalTasksAll'];
        $completedTasksAll = $officeStats['completedTasksAll'];
        $wonCasesAll = $officeStats['wonCasesAll'];
        $lostCasesAll = $officeStats['lostCasesAll'];
        $totalDecidedAll = $wonCasesAll + $lostCasesAll;
        $officeWinRate = $totalDecidedAll > 0 ? round(($wonCasesAll / $totalDecidedAll) * 100, 1) : 0;
        $officeTaskRate = $totalTasksAll > 0 ? round(($completedTasksAll / $totalTasksAll) * 100, 1) : 0;

        $totalLawyers = $users->count();
        $avgOverall = $efficiencyData->count() > 0 ? round($efficiencyData->avg('overall'), 1) : 0;
        $avgSuccess = $efficiencyData->count() > 0 ? round($efficiencyData->avg('success_rate'), 1) : 0;
        $avgTaskComp = $efficiencyData->count() > 0 ? round($efficiencyData->avg('task_completion'), 1) : 0;
        $avgDeadline = $efficiencyData->count() > 0 ? round($efficiencyData->avg('deadline_compliance'), 1) : 0;

        // Charts data (cached 5 min)
        $casesByType = Cache::remember('feasibility_cases_by_type', 300, fn () =>
            LegalCase::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray()
        );

        $monthlyTrend = Cache::remember('feasibility_monthly_trend', 300, function () {
            $trend = collect();
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $start = $date->copy()->startOfMonth();
                $end = $date->copy()->endOfMonth();
                $trend->push([
                    'label' => $date->format('M Y'),
                    'new' => LegalCase::whereBetween('created_at', [$start, $end])->count(),
                    'won' => LegalCase::where('status', 'won')->whereBetween('updated_at', [$start, $end])->count(),
                    'lost' => LegalCase::where('status', 'lost')->whereBetween('updated_at', [$start, $end])->count(),
                ]);
            }
            return $trend;
        });

        $casesByLawyer = Cache::remember('feasibility_cases_by_lawyer', 300, fn () =>
            LegalCase::select('lawyer_id', DB::raw('count(*) as count'))
                ->groupBy('lawyer_id')
                ->pluck('count', 'lawyer_id')
                ->toArray()
        );

        $tasksByPriority = Cache::remember('feasibility_tasks_by_priority', 300, fn () =>
            Task::select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray()
        );

        return view('feasibility.index', compact(
            'efficiencyData', 'topPerformer', 'leastPerformer',
            'totalCasesAll', 'totalTasksAll', 'totalLawyers',
            'officeWinRate', 'officeTaskRate',
            'avgOverall', 'avgSuccess', 'avgTaskComp', 'avgDeadline',
            'casesByType', 'monthlyTrend', 'casesByLawyer', 'tasksByPriority'
        ));
    }
}
