<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Services\AttentionService;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();
        $lastMonth = $now->copy()->subMonth();
        $user = auth()->user();

        $caseBase = LegalCase::query();
        $clientBase = Client::query();
        $taskBase = Task::query();
        $sessionBase = Session::query();
        $documentBase = Document::query();

        // === Case Statistics (cached 5 min) ===
        $caseStats = Cache::remember('dashboard_case_stats', 300, fn () => [
            'total' => (clone $caseBase)->count(),
            'active' => (clone $caseBase)->where('status', 'active')->count(),
            'overdue' => (clone $caseBase)->where('status', 'overdue')->count(),
            'closed' => (clone $caseBase)->where('status', 'closed')->count(),
            'won' => (clone $caseBase)->where('status', 'won')->count(),
            'lost' => (clone $caseBase)->where('status', 'lost')->count(),
            'pending' => (clone $caseBase)->where('status', 'pending')->count(),
        ]);
        $totalCases = $caseStats['total'];
        $activeCases = $caseStats['active'];
        $overdueCases = $caseStats['overdue'];
        $closedCases = $caseStats['closed'];
        $wonCases = $caseStats['won'];
        $lostCases = $caseStats['lost'];
        $pendingCases = $caseStats['pending'];

        // New cases this month vs last month
        $newCasesThisMonth = (clone $caseBase)->where('created_at', '>=', $startOfMonth)->count();
        $newCasesLastMonth = (clone $caseBase)->where('created_at', '>=', $lastMonth)
            ->where('created_at', '<', $startOfMonth)->count();

        // Win rate
        $decidedCases = $wonCases + $lostCases;
        $winRate = $decidedCases > 0 ? round(($wonCases / $decidedCases) * 100, 1) : 0;

        // === Session Statistics ===
        $todaySessions = (clone $sessionBase)->whereDate('date', $now->toDateString())
            ->where('status', 'upcoming')
            ->with('case')
            ->get();

        $upcomingSessions = (clone $sessionBase)->with('case')
            ->where('date', '>=', $now)
            ->where('date', '<=', $now->copy()->addDays(7))
            ->where('status', 'upcoming')
            ->orderBy('date')
            ->get();

        $totalSessions = (clone $sessionBase)->count();

        // === Task Statistics ===
        $totalTasks = (clone $taskBase)->count();
        $pendingTasks = (clone $taskBase)->where('status', 'pending')->count();
        $inProgressTasks = (clone $taskBase)->where('status', 'in_progress')->count();
        $completedTasks = (clone $taskBase)->where('status', 'completed')->count();
        $overdueTasks = (clone $taskBase)->where('status', '!=', 'completed')
            ->where('due_date', '<', $now)
            ->count();

        $tasksCompletionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        // Tasks completed this week
        $completedThisWeek = (clone $taskBase)->where('status', 'completed')
            ->where('completed_at', '>=', $startOfWeek)
            ->count();

        // === Client & Document Statistics ===
        $totalClients = (clone $clientBase)->count();
        $totalDocuments = (clone $documentBase)->count();

        // New clients this month
        $newClientsThisMonth = (clone $clientBase)->where('created_at', '>=', $startOfMonth)->count();

        // === User Statistics ===
        $totalLawyers = User::where('role', 'lawyer')->count();
        $activeLawyers = User::where('role', 'lawyer')->where('is_active', true)->count();

        // === Charts Data (cached 5 min) ===
        $cacheSuffix = '';
        $casesByStatus = Cache::remember('dashboard_cases_by_status' . $cacheSuffix, 300, fn () =>
            (clone $caseBase)->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
        );

        $casesByPriority = Cache::remember('dashboard_cases_by_priority' . $cacheSuffix, 300, fn () =>
            (clone $caseBase)->selectRaw('priority, count(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority')
        );

        // Monthly cases trend (last 6 months)
        $monthlyTrend = Cache::remember('dashboard_monthly_trend' . $cacheSuffix, 300, function () use ($now, $caseBase) {
            $trend = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = $now->copy()->subMonths($i);
                $monthStart = $month->copy()->startOfMonth();
                $monthEnd = $month->copy()->endOfMonth();
                $trend[] = [
                    'label' => $month->format('M'),
                    'new' => (clone $caseBase)->whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                    'closed' => (clone $caseBase)->where('status', 'closed')
                        ->where('updated_at', '>=', $monthStart)
                        ->where('updated_at', '<=', $monthEnd)
                        ->count(),
                ];
            }
            return $trend;
        });

        // Cases by lawyer
        $casesByLawyer = Cache::remember('dashboard_cases_by_lawyer' . $cacheSuffix, 300, fn () =>
            (clone $caseBase)->join('users', 'cases.lawyer_id', '=', 'users.id')
                ->selectRaw('users.name, count(*) as count')
                ->groupBy('users.name')
                ->pluck('count', 'name')
        );

        // === Recent Activity (combined feed) ===
        $activityItems = collect();

        // Recent cases
        (clone $caseBase)->latest()->limit(3)->get()->each(function ($item) use ($activityItems) {
            $activityItems->push([
                'type' => 'case',
                'icon' => 'case',
                'title' => $item->title,
                'subtitle' => $item->case_number . ' — ' . ($item->status ?? ''),
                'time' => $item->created_at,
                'url' => route('cases.show', $item),
            ]);
        });

        // Recent tasks
        (clone $taskBase)->with('assignee')->latest()->limit(3)->get()->each(function ($item) use ($activityItems) {
            $activityItems->push([
                'type' => 'task',
                'icon' => 'task',
                'title' => $item->title,
                'subtitle' => ($item->assignee?->name ?? '') . ' — ' . ($item->status ?? ''),
                'time' => $item->created_at,
                'url' => route('tasks.show', $item),
            ]);
        });

        // Recent clients
        (clone $clientBase)->latest()->limit(3)->get()->each(function ($item) use ($activityItems) {
            $activityItems->push([
                'type' => 'client',
                'icon' => 'client',
                'title' => $item->name,
                'subtitle' => $item->phone ?? '',
                'time' => $item->created_at,
                'url' => route('clients.show', $item),
            ]);
        });

        // Recent documents
        (clone $documentBase)->with('case')->latest()->limit(3)->get()->each(function ($item) use ($activityItems) {
            $activityItems->push([
                'type' => 'document',
                'icon' => 'document',
                'title' => $item->title,
                'subtitle' => $item->case?->title ?? '',
                'time' => $item->created_at,
                'url' => route('documents.index'),
            ]);
        });

        // Audit log entries
        AuditLog::with('user')->latest()->limit(5)->get()->each(function ($item) use ($activityItems) {
            $activityItems->push([
                'type' => 'log',
                'icon' => 'log',
                'title' => $item->description ?? '',
                'subtitle' => $item->user?->name ?? '',
                'time' => $item->created_at,
                'url' => null,
            ]);
        });

        // Sort by time, take top 10
        $recentActivity = $activityItems->sortByDesc('time')->take(10)->values();

        // === Overdue Tasks List ===
        $overdueTasksList = (clone $taskBase)->with(['assignee', 'case'])
            ->where('status', '!=', 'completed')
            ->where('due_date', '<', $now)
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        // === Pending Tasks List ===
        $pendingTasksList = (clone $taskBase)->with('assignee', 'case')
            ->where('status', '!=', 'completed')
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        // === Top performing lawyer (cached 5 min) ===
        $topLawyer = Cache::remember('dashboard_top_lawyer', 300, fn () =>
            LegalCase::where('status', 'won')
                ->selectRaw('lawyer_id, count(*) as wins')
                ->groupBy('lawyer_id')
                ->orderByDesc('wins')
                ->first()
        );

        // === Attention Center (smart alerts) ===
        $attentionItems = app(AttentionService::class)->items(5);

        // === Today's Brief (ما يحتاج انتباهك اليوم) ===
        $brief = collect();
        if (!$user->isClient()) {
            $hour = (int) $now->format('H');
            $greeting = $hour < 12 ? 'صباح الخير' : ($hour < 18 ? 'مساء الخير' : 'مساء الخير');

            $todaySessions = (clone $sessionBase)->with('case')
                ->whereDate('date', $now->toDateString())
                ->where('status', 'upcoming')
                ->orderBy('date')
                ->get();
            $todaySessions->each(function ($s) use ($brief) {
                $case = $s->case;
                $brief->push([
                    'sev' => 1,
                    'title' => 'جلسة الآن/اليوم — ' . ($case->case_number ?? 'قضية') . ($s->location ? ' (' . $s->location . ')' : ''),
                    'sub' => $s->date->format('H:i') . ($case?->client?->name ? ' — ' . $case->client->name : ''),
                    'url' => route('sessions.show', $s),
                    'icon' => 'gavel',
                ]);
            });

            $dueTasks = (clone $taskBase)->with('case')
                ->where('status', '!=', 'completed')
                ->whereDate('due_date', $now->toDateString())
                ->limit(8)
                ->get();
            $dueTasks->each(function ($t) use ($brief, $now) {
                $brief->push([
                    'sev' => 2,
                    'title' => ($t->due_date < $now ? 'مهمة متأخرة — ' : 'مهمة مستحقة اليوم — ') . $t->title,
                    'sub' => $t->case?->case_number ? '#' . $t->case->case_number : '—',
                    'url' => route('tasks.show', $t),
                    'icon' => 'task',
                ]);
            });

            $upcomingAppointments = \App\Models\CaseActivity::with('case')
                ->where('type', 'appointment')
                ->where('occurred_at', '>=', $now)
                ->where('occurred_at', '<=', $now->copy()->addDays(7))
                ->orderBy('occurred_at')
                ->limit(5)
                ->get();
            $upcomingAppointments->each(function ($a) use ($brief) {
                $brief->push([
                    'sev' => 3,
                    'title' => 'موعد — ' . $a->title,
                    'sub' => $a->occurred_at->format('Y/m/d H:i') . ($a->case ? ' — ' . $a->case->case_number : ''),
                    'url' => $a->case_id ? route('cases.show', $a->case_id) : route('dashboard'),
                    'icon' => 'calendar',
                ]);
            });

            $dueInvoices = \App\Models\FinanceInvoice::with('client')
                ->where('status', '!=', 'paid')
                ->where(function ($q) use ($now) {
                    $q->whereNull('due_date')->orWhere('due_date', '<=', $now->copy()->addDays(7));
                })
                ->orderBy('due_date')
                ->limit(5)
                ->get();
            $dueInvoices->each(function ($i) use ($brief) {
                $overdue = $i->due_date && $i->due_date->isPast();
                $brief->push([
                    'sev' => $overdue ? 2 : 3,
                    'title' => ($overdue ? 'فاتورة متأخرة — ' : 'فاتورة مستحقة — ') . $i->invoice_number,
                    'sub' => ($i->client->name ?? '') . ' — باقي ' . number_format((float) ($i->amount - $i->paid_amount), 2),
                    'url' => route('finance.invoices.show', $i),
                    'icon' => 'invoice',
                ]);
            });

            $todayCalls = \App\Models\CaseActivity::where('type', 'call')
                ->whereDate('occurred_at', $now->toDateString())
                ->count();
            if ($todayCalls > 0) {
                $brief->push([
                    'sev' => 4,
                    'title' => $todayCalls . ' اتصال مسجل اليوم',
                    'sub' => 'توثيق تلقائي ضمن الخط الزمني للقضايا',
                    'url' => route('attention.index'),
                    'icon' => 'call',
                ]);
            }
        } else {
            $greeting = 'مرحباً؛';
        }

        return view('dashboard', compact(
            'totalCases',
            'activeCases',
            'overdueCases',
            'closedCases',
            'wonCases',
            'lostCases',
            'pendingCases',
            'newCasesThisMonth',
            'newCasesLastMonth',
            'winRate',
            'todaySessions',
            'upcomingSessions',
            'totalSessions',
            'totalTasks',
            'pendingTasks',
            'inProgressTasks',
            'completedTasks',
            'overdueTasks',
            'tasksCompletionRate',
            'completedThisWeek',
            'totalClients',
            'newClientsThisMonth',
            'totalDocuments',
            'totalLawyers',
            'activeLawyers',
            'casesByStatus',
            'casesByPriority',
            'monthlyTrend',
            'casesByLawyer',
            'recentActivity',
            'overdueTasksList',
            'pendingTasksList',
            'topLawyer',
            'attentionItems',
            'brief',
            'greeting'
        ));
    }
}
