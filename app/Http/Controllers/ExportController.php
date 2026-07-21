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

        return view('reports.index', compact('counts'));
    }

    public function cases()
    {
        $count = LegalCase::count();
        $this->logAudit(AuditLog::ACTION_CREATE, null, null, null, ['action' => 'export', 'type' => 'cases', 'count' => $count]);
        return Excel::download(new CasesExport, 'cases_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function sessions()
    {
        $count = Session::count();
        $this->logAudit(AuditLog::ACTION_CREATE, null, null, null, ['action' => 'export', 'type' => 'sessions', 'count' => $count]);
        return Excel::download(new SessionsExport, 'sessions_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function tasks()
    {
        $count = Task::count();
        $this->logAudit(AuditLog::ACTION_CREATE, null, null, null, ['action' => 'export', 'type' => 'tasks', 'count' => $count]);
        return Excel::download(new TasksExport, 'tasks_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function clients()
    {
        $count = Client::count();
        $this->logAudit(AuditLog::ACTION_CREATE, null, null, null, ['action' => 'export', 'type' => 'clients', 'count' => $count]);
        return Excel::download(new ClientsExport, 'clients_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function all()
    {
        $total = LegalCase::count() + Session::count() + Task::count() + Client::count();
        $this->logAudit(AuditLog::ACTION_CREATE, null, null, null, ['action' => 'export', 'type' => 'all']);
        return Excel::download(new AllExport, 'law_office_export_' . now()->format('Y-m-d') . '.xlsx');
    }
}
