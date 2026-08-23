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

        // كل عضو يرى مهام المكتب، و«مهامي» تُعيد العرض الشخصي لمن أراده
        if ($request->boolean('mine')) {
            $query->where('assigned_to', auth()->id());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('due')) {
            $today = now()->startOfDay();
            match ($request->due) {
                'overdue' => $query->whereNotNull('due_date')
                    ->where('due_date', '<', $today)
                    ->where('status', '!=', 'completed'),
                'today' => $query->whereDate('due_date', $today),
                'week' => $query->whereBetween('due_date', [$today, $today->copy()->addDays(7)->endOfDay()]),
                'upcoming' => $query->whereNotNull('due_date')
                    ->where('due_date', '>=', $today)
                    ->where('status', '!=', 'completed'),
                default => null,
            };
        }

        // القضية: كانت الفلترة عليها ناقصة رغم أن المهمة مرتبطة بقضية
        if ($request->filled('case_id')) {
            $query->where('case_id', $request->case_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        $tasks = $query->latest()->paginate(15)->withQueryString();
        $users = User::where('role', '!=', 'client')->orderBy('name')->get();

        $filterCases = \App\Models\LegalCase::orderByDesc('id')
            ->limit(300)
            ->get(['id', 'office_case_number', 'title']);

        return view('tasks.index', compact('tasks', 'users', 'filterCases'));
    }

    public function create(Request $request): View
    {
        $cases = LegalCase::with('client')->orderBy('office_case_number')->get();
        $users = User::where('role', '!=', 'client')->orderBy('name')->get();
        $selectedCaseId = (int) $request->query('case_id', 0);

        return view('tasks.create', compact('cases', 'users', 'selectedCaseId'));
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
            \App\Support\Notify::send(
                userId: $task->assigned_to,
                titleKey: 'app.notif_task_assigned_title',
                messageKey: 'app.notif_task_assigned_body',
                params: ['task' => $task->title],
                type: Notification::TYPE_INFO,
                notifiableType: Task::class,
                notifiableId: $task->id,
            );
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

        $this->notifyIfJustCompleted($task, $oldValues['status']);

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

    /**
     * تغيير الحالة وحدها.
     *
     * كان الزرّ يمرّ عبر update فيرسل معه عنوان المهمة ووصفها ومَن
     * أُسندت إليه — كما كانت الصفحة ساعة فُتحت. فإن عدّلها زميلٌ في
     * تلك الأثناء، مسح الضغطُ تعديلَه وأعاد القديم بلا إنذار.
     *
     * هنا لا يُكتب غير الحالة ووقت الإتمام.
     */
    public function changeStatus(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskAccess($task);

        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,completed',
        ]);

        if ($validated['status'] === $task->status) {
            return redirect()->route('tasks.show', $task);
        }

        $oldValues = $task->toArray();
        $wasStatus = $task->status;

        $task->status = $validated['status'];
        $task->completed_at = $validated['status'] === 'completed' ? now() : null;
        $task->save();

        $this->notifyIfJustCompleted($task, $wasStatus);

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Task::class,
            $task->id,
            $oldValues,
            $task->toArray()
        );

        return redirect()->route('tasks.show', $task)
            ->with('success', __('app.task_status_changed', [
                'status' => __('app.status_' . $validated['status']),
            ]));
    }

    /** مَن أنشأ المهمة يُخبَر باكتمالها — مرةً واحدة، ولا يُخبِر نفسه. */
    private function notifyIfJustCompleted(Task $task, ?string $wasStatus): void
    {
        if ($task->status !== 'completed' || $wasStatus === 'completed') {
            return;
        }

        if (! $task->created_by || $task->created_by === auth()->id()) {
            return;
        }

        \App\Support\Notify::send(
            userId: $task->created_by,
            titleKey: 'app.notif_task_done_title',
            messageKey: 'app.notif_task_done_body',
            params: ['task' => $task->title],
            type: Notification::TYPE_SUCCESS,
            notifiableType: Task::class,
            notifiableId: $task->id,
        );
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

        // نفس القالب يُستخدم هنا، فيحتاج نفس قوائم الفلترة
        $filterCases = \App\Models\LegalCase::orderByDesc('id')
            ->limit(300)
            ->get(['id', 'office_case_number', 'title']);

        return view('tasks.index', compact('tasks', 'users', 'filterCases'));
    }

    private function authorizeTaskAccess(Task $task): void
    {
        // All team members can access any task
    }

}
