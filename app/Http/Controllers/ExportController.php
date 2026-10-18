<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Models\Client;
use App\Traits\AuditLoggable;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CasesExport;
use App\Exports\SessionsExport;
use App\Exports\TasksExport;
use App\Exports\ClientsExport;
use App\Exports\AllExport;
use App\Exports\FinanceExport;
use App\Models\FinanceFee;
use App\Models\FinanceInvoice;
use App\Models\FinanceTransaction;
use App\Models\Setting;

class ExportController extends Controller
{
    use AuditLoggable;

    public function index()
    {
        $counts = [
            'cases' => LegalCase::count(),
            'sessions' => Session::count(),
            'tasks' => Task::count(),
            'clients' => Client::count(),
        ];

        // بطاقةُ الماليّة لمن يرى ماليّةَ المكتب وحده، وحين لا تكون الوحدةُ معطّلة
        $canExportFinance = in_array(auth()->user()->role, ['developer', 'admin'], true)
            && (Setting::get('feature_finance', '0') !== '1' || auth()->user()->role === 'developer');
        if ($canExportFinance) {
            $counts['finance'] = FinanceTransaction::count() + FinanceInvoice::count() + FinanceFee::count();
        }

        return view('reports.index', compact('counts', 'canExportFinance'));
    }

    public function finance()
    {
        $this->logAudit(AuditLog::ACTION_CREATE, null, null, null, ['action' => 'export', 'type' => 'finance']);
        return Excel::download(new FinanceExport(), 'finance_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function cases()
    {
        $user = auth()->user();
        $this->logAudit(AuditLog::ACTION_CREATE, null, null, null, ['action' => 'export', 'type' => 'cases']);
        return Excel::download(new CasesExport($user), 'cases_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function sessions()
    {
        $user = auth()->user();
        $this->logAudit(AuditLog::ACTION_CREATE, null, null, null, ['action' => 'export', 'type' => 'sessions']);
        return Excel::download(new SessionsExport($user), 'sessions_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function tasks()
    {
        $user = auth()->user();
        $this->logAudit(AuditLog::ACTION_CREATE, null, null, null, ['action' => 'export', 'type' => 'tasks']);
        return Excel::download(new TasksExport($user), 'tasks_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function clients()
    {
        $user = auth()->user();
        $this->logAudit(AuditLog::ACTION_CREATE, null, null, null, ['action' => 'export', 'type' => 'clients']);
        return Excel::download(new ClientsExport($user), 'clients_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function all()
    {
        $user = auth()->user();
        $this->logAudit(AuditLog::ACTION_CREATE, null, null, null, ['action' => 'export', 'type' => 'all']);
        return Excel::download(new AllExport($user), 'law_office_export_' . now()->format('Y-m-d') . '.xlsx');
    }
}
