<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LegalCase;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use App\Traits\AuditLoggable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    use AuditLoggable;
    public function index(Request $request): View
    {
        $query = Task::with(['assignee', 'creator', 'case']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $tasks = $query->latest()->paginate(15)->withQueryString();
        $users = User::where('role', '!=', 'client')->orderBy('name')->get();

        return view('tasks.index', compact('tasks', 'users'));
    }

    public function create(): View
    {
        $cases = LegalCase::with('client')->orderBy('office_case_number')->get();
        $users = User::where('role', '!=', 'client')->orderBy('name')->get();

        return view('tasks.create', compact('cases', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'case_id'     => 'nullable|exists:cases,id',
            'assigned_to' => 'required|exists:users,id',
            'status'      => 'required|in:pending,in_progress,completed',
            'priority'    => 'required|in:low,medium,high,urgent',
            'due_date'    => 'nullable|date',
        ]);

        $validated['created_by'] = auth()->id();

        $task = Task::create($validated);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            Task::class,
            $task->id,
            null,
            $task->toArray()
        );

        if ($task->assigned_to && $task->assigned_to !== auth()->id()) {
            Notification::create([
                'user_id'         => $task->assigned_to,
                'title'           => 'New Task Assigned',
                'message'         => "You have been assigned a new task: '{$task->title}'.",
                'type'            => Notification::TYPE_INFO,
                'is_read'         => false,
                'notifiable_type' => Task::class,
                'notifiable_id'   => $task->id,
            ]);
        }

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Task created successfully.');
    }

    public function show(Task $task): View
    {
        $this->authorizeTaskAccess($task);
        $task->load(['assignee', 'creator', 'case']);

        return view('tasks.show', compact('task'));
    }

    public function edit(Task $task): View
    {
        $this->authorizeTaskAccess($task);
        $cases = LegalCase::with('client')->orderBy('office_case_number')->get();
        $users = User::where('role', '!=', 'client')->orderBy('name')->get();

        return view('tasks.edit', compact('task', 'cases', 'users'));
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskAccess($task);

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'case_id'     => 'nullable|exists:cases,id',
            'assigned_to' => 'required|exists:users,id',
            'status'      => 'required|in:pending,in_progress,completed',
            'priority'    => 'required|in:low,medium,high,urgent',
            'due_date'    => 'nullable|date',
        ]);

        $oldValues = $task->toArray();

        if ($validated['status'] === 'completed' && $task->status !== 'completed') {
            $validated['completed_at'] = now();
        } elseif ($validated['status'] !== 'completed') {
            $validated['completed_at'] = null;
        }

        $task->update($validated);

        if ($task->status === 'completed' && $oldValues['status'] !== 'completed' && $task->created_by && $task->created_by !== auth()->id()) {
            Notification::create([
                'user_id'         => $task->created_by,
                'title'           => 'تم إكمال المهمة',
                'message'         => "تم إكمال المهمة '{$task->title}'",
                'type'            => Notification::TYPE_SUCCESS,
                'is_read'         => false,
                'notifiable_type' => Task::class,
                'notifiable_id'   => $task->id,
            ]);
        }

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Task::class,
            $task->id,
            $oldValues,
            $task->toArray()
        );

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Task updated successfully.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorizeTaskAccess($task);
        $oldValues = $task->toArray();
        $task->delete();

        $this->logAudit(
            AuditLog::ACTION_DELETE,
            Task::class,
            $task->id,
            $oldValues,
            null
        );

        return redirect()->route('tasks.index')
            ->with('success', 'Task deleted successfully.');
    }

    public function myTasks(): View
    {
        $tasks = Task::with(['case', 'creator'])
            ->where('assigned_to', auth()->id())
            ->latest()
            ->paginate(15);

        $users = User::where('role', '!=', 'client')->orderBy('name')->get();

        return view('tasks.index', compact('tasks', 'users'));
    }

    private function authorizeTaskAccess(Task $task): void
    {
        // All team members can access any task
    }

}
