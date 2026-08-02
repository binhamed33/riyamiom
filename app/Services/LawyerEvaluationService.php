<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

class LawyerEvaluationService
{
    public const PERIODS = ['all', 'month', 'last_month'];

    private const ROLES = ['admin', 'lawyer', 'staff'];

    public function evaluate(string $period = 'all'): array
    {
        [$from, $to] = $this->periodRange($period);

        $users = User::whereIn('role', self::ROLES)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                'id'    => $user->id,
                'name'  => $user->name,
                'role'  => $user->role,
                'metrics' => $this->metricsFor($user, $from, $to),
            ];
        }

        usort($rows, fn ($a, $b) => $b['metrics']['score'] <=> $a['metrics']['score']);

        foreach ($rows as $i => $row) {
            $rows[$i]['rank'] = $i + 1;
        }

        return $rows;
    }

    public function periodRange(string $period): array
    {
        return match ($period) {
            'month'      => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            default      => [null, null],
        };
    }

    private function metricsFor(User $user, ?Carbon $from, ?Carbon $to): array
    {
        $casesQuery = LegalCase::where('lawyer_id', $user->id);
        $this->applyWindow($casesQuery, 'created_at', $from, $to);
        $cases = $casesQuery->get(['id', 'status']);

        $totalCases = $cases->count();
        $closedCases = $cases->whereIn('status', ['closed', 'won', 'lost'])->count();
        $openCases = $totalCases - $closedCases;

        $sessionsQuery = Session::whereHas('case', fn ($q) => $q->where('lawyer_id', $user->id));
        $this->applyWindow($sessionsQuery, 'created_at', $from, $to);
        $sessionRows = $sessionsQuery->get(['id', 'report']);
        $sessionCount = $sessionRows->count();
        $sessionReports = $sessionRows->whereNotNull('report')->where('report', '!=', '')->count();

        $tasksQuery = Task::where('assigned_to', $user->id);
        $this->applyWindow($tasksQuery, 'created_at', $from, $to);
        $totalTasks = $tasksQuery->count();

        $completedQuery = Task::where('assigned_to', $user->id)->where('status', 'completed');
        $this->applyWindow($completedQuery, 'completed_at', $from, $to);
        $completedRows = $completedQuery->get(['id', 'due_date', 'completed_at']);
        $completedCount = $completedRows->count();
        $onTimeCount = $completedRows
            ->filter(fn ($t) => $t->due_date && $t->completed_at && Carbon::createFromTimestamp($t->completed_at)->lte($t->due_date->copy()->endOfDay()))
            ->count();

        $documentsQuery = Document::where('uploaded_by', $user->id);
        $this->applyWindow($documentsQuery, 'created_at', $from, $to);
        $documentCount = $documentsQuery->count();

        $auditQuery = AuditLog::where('user_id', $user->id)->whereIn('action', ['create', 'update']);
        $this->applyWindow($auditQuery, 'created_at', $from, $to);
        $auditCount = $auditQuery->count();

        $lastLogin = AuditLog::where('user_id', $user->id)->where('action', 'login')->max('created_at');

        $casePts = min($closedCases * 3, 24) + min($openCases * 1, 16);
        $activityPts = min($sessionCount, 12)
            + min($completedCount, 10)
            + min($documentCount, 8)
            + min($auditCount * 0.5, 5);
        $qualityPts = ($totalTasks > 0 ? ($completedCount / $totalTasks) * 10 : 0)
            + ($completedCount > 0 ? ($onTimeCount / $completedCount) * 10 : 0)
            + ($totalCases > 0 ? ($closedCases / $totalCases) * 5 : 0);

        $score = round(min(100, $casePts + $activityPts + $qualityPts), 1);

        return [
            'cases_total'     => $totalCases,
            'cases_closed'    => $closedCases,
            'cases_open'      => $openCases,
            'sessions'        => $sessionCount,
            'session_reports' => $sessionReports,
            'tasks_total'     => $totalTasks,
            'tasks_completed' => $completedCount,
            'tasks_on_time'   => $onTimeCount,
            'documents'       => $documentCount,
            'audit_actions'   => $auditCount,
            'last_login'      => $lastLogin,
            'score'           => $score,
            'grade'           => $this->grade($score),
        ];
    }

    private function applyWindow($query, string $column, ?Carbon $from, ?Carbon $to): void
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }
        if ($to) {
            $query->where($column, '<=', $to);
        }
    }

    private function grade(float $score): string
    {
        if ($score >= 85) {
            return 'excellent';
        }
        if ($score >= 70) {
            return 'very_good';
        }
        if ($score >= 50) {
            return 'good';
        }

        return 'needs_improvement';
    }
}
