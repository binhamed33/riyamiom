<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CaseActivity;
use App\Models\CaseAiMessage;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Support\ClientMessage;
use App\Models\Notification;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use App\Services\DocumentSmartService;
use App\Services\GeminiService;

use App\Traits\AuditLoggable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

use Illuminate\View\View;

class CaseController extends Controller
{
    use AuditLoggable;

    public function index(Request $request): View
    {
        $sortMap = [
            'number' => 'cases.office_case_number',
            'created' => 'cases.created_at',
            'priority' => 'priority',
            'status' => 'cases.status',
            'type' => 'cases.case_type',
            'court' => 'cases.court',
            'opponent' => 'cases.opponent',
            'client' => 'clients.name',
            'lawyer' => 'users.name',
        ];

        $sort = $request->get('sort', 'number');
        if (!array_key_exists($sort, $sortMap)) {
            $sort = 'number';
        }
        $dir = strtolower($request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = LegalCase::with(['client', 'lawyer'])
            ->leftJoin('clients', 'cases.client_id', '=', 'clients.id')
            ->leftJoin('users', 'cases.lawyer_id', '=', 'users.id')
            ->select('cases.*');

        if ($request->filled('status')) {
            $query->where('cases.status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('cases.priority', $request->priority);
        }

        if ($request->filled('lawyer_id')) {
            $query->where('cases.lawyer_id', $request->lawyer_id);
        }

        if ($request->filled('court')) {
            $query->where('cases.court', $request->court);
        }

        if ($request->filled('case_type')) {
            $query->where('cases.case_type', $request->case_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('cases.created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('cases.created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('cases.case_number', 'like', "%{$search}%")
                    ->orWhere('cases.office_case_number', 'like', "%{$search}%")
                    ->orWhere('cases.opponent_phone', 'like', "%{$search}%")
                    ->orWhere('cases.title', 'like', "%{$search}%");
            });
        }

        if ($sort === 'priority') {
            $query->orderByRaw("CASE cases.priority WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 WHEN 'urgent' THEN 4 ELSE 0 END {$dir}")
                ->orderBy('cases.id', $dir);
        } elseif ($sort === 'number') {
            $query->orderBy(DB::raw('CAST(cases.office_case_number AS UNSIGNED)'), $dir);
        } else {
            $query->orderBy($sortMap[$sort], $dir)->orderBy('cases.id', $dir);
        }

        $cases = $query->paginate(15)->withQueryString();
        $users = User::where('is_active', true)->orderBy('name')->get();
        $filterCourts = LegalCase::whereNotNull('court')->where('court', '!=', '')->distinct()->orderBy('court')->pluck('court');
        $filterTypes = LegalCase::whereNotNull('case_type')->where('case_type', '!=', '')->distinct()->orderBy('case_type')->pluck('case_type');

        return view('cases.index', compact('cases', 'users', 'sort', 'dir', 'filterCourts', 'filterTypes'));
    }

    public function create(): View
    {
        $clients = Client::orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get();

        return view('cases.create', compact('clients', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'case_number'         => 'required|string|unique:cases,case_number',
            'case_type'           => 'nullable|string|max:255',
            'title'               => 'nullable|string|max:255',
            'description'         => 'required|string',
            'type'                => 'nullable|string|max:255',
            'court'               => 'required|string|max:255',
            'opponent'            => 'required|string',
            'opponent_phone'      => 'nullable|string|max:255',
            'opponent_address'    => 'nullable|string',
            'opponent_lawyer'     => 'nullable|string|max:255',
            'opponent_civil_number' => 'nullable|string|max:255',
            'status'              => 'required|in:active,pending,overdue,closed,won,lost,adjudicated,fees_pending',
            'priority'            => 'required|in:low,medium,high,urgent',
            'client_id'           => 'required|exists:clients,id',
            'lawyer_id'           => 'nullable|exists:users,id',
            'doc_file'            => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:20480',
            'doc_title'           => 'nullable|string|max:255',
            'doc_access_level'    => 'nullable|in:all,team,private',
            'task_title'          => 'nullable|string|max:255',
            'task_description'    => 'nullable|string|max:2000',
            'task_due_date'       => 'nullable|date',
            'task_priority'       => 'nullable|in:low,medium,high,urgent',
            'task_assigned_to'    => 'nullable|exists:users,id',
            'note_title'          => 'nullable|string|max:255',
            'note_content'        => 'nullable|string|max:2000',
            'opened_at'           => 'nullable|date',
            'template_id'         => 'nullable|exists:case_templates,id',
        ]);

        if (empty($validated['title'])) {
            $validated['title'] = $validated['case_number'];
        }

        if (empty($validated['opened_at'])) {
            $validated['opened_at'] = now()->toDateString();
        }

        if (empty($validated['type']) && !empty($validated['case_type'])) {
            $validated['type'] = $validated['case_type'];
        } elseif (empty($validated['type'])) {
            $validated['type'] = 'مدني';
        }

        $maxOffice = LegalCase::max(DB::raw('office_case_number + 0'));
        $validated['office_case_number'] = (string) ((int) ($maxOffice ?? 0) + 1);

        if (!in_array(auth()->user()->role, ['developer', 'admin']) && empty($validated['lawyer_id'])) {
            $validated['lawyer_id'] = auth()->id();
        }

        $validated['created_by'] = auth()->id();

        $sessionErrors = [];
        $sessionsData = $request->input('sessions', []);
        if (is_array($sessionsData)) {
            foreach ($sessionsData as $i => $s) {
                if (empty($s['date'])) {
                    $sessionErrors[] = "الجلسة " . ($i + 1) . ": التاريخ مطلوب";
                } elseif (!strtotime($s['date'])) {
                    $sessionErrors[] = "الجلسة " . ($i + 1) . ": تاريخ غير صالح";
                }
            }
        }
        if (!empty($sessionErrors)) {
            return redirect()->back()->withInput()->with('error', implode('<br>', $sessionErrors));
        }

        $storedDocPath = null;

        DB::beginTransaction();
        try {
            $legalCase = LegalCase::create(collect($validated)->except('template_id')->all());

            // القالب الذكي: يجهّز القضية تلقائياً (مهام + قائمة تحقق + مجلدات + تذكيرات)
            if (!empty($validated['template_id'])) {
                \App\Models\CaseTemplate::where('is_active', true)
                    ->find($validated['template_id'])
                    ?->applyTo($legalCase, auth()->id());
            }

            // مشغّل الأتمتة اللحظي — آمن: لا يكسر إنشاء القضية أبداً
            \App\Services\Automation\AutomationEngine::fire('case_created', $legalCase);

            if (is_array($sessionsData)) {
                foreach ($sessionsData as $sessionData) {
                    if (empty($sessionData['date'])) continue;
                    Session::create([
                        'case_id'  => $legalCase->id,
                        'date'     => $sessionData['date'],
                        'location' => $sessionData['location'] ?? '',
                        'status'   => $sessionData['status'] ?? 'upcoming',
                        'notes'    => $sessionData['notes'] ?? '',
                        'report'   => $sessionData['report'] ?? '',
                    ]);
                }
            }

            // Optional document upload
            if ($request->hasFile('doc_file')) {
                $file = $request->file('doc_file');
                $extension = strtolower($file->getClientOriginalExtension());
                $storedDocPath = $file->store('documents', 'private');

                $inferred = DocumentSmartService::inferFromFilename($file->getClientOriginalName());

                $document = Document::create([
                    'case_id'      => $legalCase->id,
                    'uploaded_by'  => auth()->id(),
                    'title'        => $request->filled('doc_title') ? $request->input('doc_title') : $file->getClientOriginalName(),
                    'doc_type'     => $inferred['type'],
                    'doc_date'     => $inferred['date'],
                    'file_path'    => $storedDocPath,
                    'file_type'    => $extension,
                    'file_size'    => $file->getSize(),
                    'access_level' => $request->input('doc_access_level', 'all'),
                ]);

                $this->logAudit(
                    AuditLog::ACTION_CREATE,
                    Document::class,
                    $document->id,
                    null,
                    $document->toArray()
                );
            }

            // Optional task
            if ($request->filled('task_title')) {
                $task = Task::create([
                    'title'        => $request->input('task_title'),
                    'description'  => $request->input('task_description'),
                    'case_id'      => $legalCase->id,
                    'assigned_to'  => $request->filled('task_assigned_to') ? $request->input('task_assigned_to') : auth()->id(),
                    'created_by'   => auth()->id(),
                    'status'       => 'pending',
                    'priority'     => $request->input('task_priority', 'medium'),
                    'due_date'     => $request->filled('task_due_date') ? $request->input('task_due_date') : null,
                ]);

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
            }

            // Optional note
            if ($request->filled('note_title')) {
                $activity = CaseActivity::create([
                    'case_id'     => $legalCase->id,
                    'user_id'     => auth()->id(),
                    'type'        => CaseActivity::TYPE_NOTE,
                    'title'       => $request->input('note_title'),
                    'content'     => $request->input('note_content'),
                    'occurred_at' => now(),
                ]);

                $this->logAudit(
                    AuditLog::ACTION_CREATE,
                    CaseActivity::class,
                    $activity->id,
                    null,
                    ['case_id' => $legalCase->id, 'type' => $activity->type, 'title' => $activity->title]
                );
            }

            DB::commit();

            $this->logAudit(
                AuditLog::ACTION_CREATE,
                LegalCase::class,
                $legalCase->id,
                null,
                $legalCase->toArray()
            );

            return $this->redirectAfterStore($legalCase);
        } catch (\Exception $e) {
            DB::rollBack();
            if ($storedDocPath && Storage::disk('private')->exists($storedDocPath)) {
                Storage::disk('private')->delete($storedDocPath);
            }
            return redirect()->back()->withInput()->with('error', 'حدث خطأ أثناء حفظ القضية والجلسات: ' . $e->getMessage());
        }
    }

    private function redirectAfterStore(LegalCase $legalCase): RedirectResponse
    {
        try {
            $notify = $this->notifyClientPortal($legalCase);

            if (!empty($notify['sent'])) {
                $channelsText = count($notify['sent']) > 1
                    ? 'البريد الإلكتروني وواتساب'
                    : ($notify['sent'][0] === 'email' ? 'البريد الإلكتروني' : 'واتساب');
                $notice = 'تم إرسال رسالة المتابعة للموكل تلقائياً عبر ' . $channelsText;
                if (!empty($notify['failures'])) {
                    $notice .= ' — ' . implode(' | ', $notify['failures']);
                }
                return redirect()->route('cases.show', $legalCase)
                    ->with('success', 'case_created')
                    ->with('portal_notice', $notice)
                    ->with('print_url', route('cases.show', $legalCase) . '?print=1');
            }

            if (!empty($notify['failures'])) {
                return redirect()->route('cases.show', $legalCase)
                    ->with('success', 'case_created')
                    ->with('portal_notice', 'لم يتم إرسال رسالة المتابعة تلقائياً: ' . implode(' | ', $notify['failures']))
                    ->with('print_url', route('cases.show', $legalCase) . '?print=1');
            }
        } catch (\Throwable $e) {
            Log::error('Auto portal notify failed for case ' . $legalCase->id . ': ' . $e->getMessage());
        }

        return redirect()->route('cases.show', $legalCase)
            ->with('success', 'case_created')
            ->with('print_url', route('cases.show', $legalCase) . '?print=1');
    }

    public function show(LegalCase $case): View
    {
        $this->authorizeCaseAccess($case);

        $case->load(['client', 'lawyer', 'sessions', 'tasks.assignee', 'documents.uploader', 'documents.folder', 'aiMessages', 'checklistItems.doneBy', 'folders', 'reminders']);

        $events = collect();

        try {
            $case->load('activities.user');
            $case->activities->each(function ($a) use ($events) {
            $typeLabel = [
                'note' => 'ملاحظة',
                'call' => 'اتصال',
                'appointment' => 'موعد',
                'document' => 'مستند',
                'task' => 'مهمة',
                'session' => 'جلسة',
                'payment' => 'دفعة',
                'other' => 'إجراء',
            ][$a->type] ?? $a->type;
            $events->push([
                'kind' => 'activity',
                'label' => $a->title,
                'sub' => $typeLabel . ($a->user ? ' • ' . $a->user->name : ''),
                'date' => $a->occurred_at,
                'key' => 'a' . $a->id,
            ]);
        });

        $case->sessions->each(function ($s) use ($events) {
            $events->push([
                'kind' => 'session',
                'label' => 'جلسة — ' . ($s->location ?? '-'),
                'sub' => $s->status . ($s->notes ? ' • ' . $s->notes : ''),
                'date' => $s->date,
                'key' => 's' . $s->id,
            ]);
        });

        $case->tasks->each(function ($t) use ($events) {
            $events->push([
                'kind' => 'task',
                'label' => ($t->status === 'completed' ? '✓ ' : '') . $t->title,
                'sub' => $t->status . ($t->due_date ? ' • ' . $t->due_date->format('Y/m/d') : ''),
                'date' => $t->created_at,
                'key' => 't' . $t->id,
            ]);
        });

        $case->documents->each(function ($d) use ($events) {
            $events->push([
                'kind' => 'document',
                'label' => $d->title,
                'sub' => $d->file_type,
                'date' => $d->created_at,
                'key' => 'd' . $d->id,
            ]);
        });

        // سجل التدقيق الحقيقي: من فعل ماذا، والقيمة القديمة → الجديدة
        $case->auditLogs()->with('user')->latest('id')->limit(100)->get()
            ->each(function ($log) use ($events) {
                $entry = $this->describeAuditForTimeline($log);
                if ($entry) {
                    $events->push($entry + ['date' => $log->created_at, 'key' => 'g' . $log->id]);
                }
            });

        $timeline = $events->sortByDesc('date')->values();
        } catch (\Throwable $e) {
            logger()->warning('Timeline degraded (case ' . $case->id . '): ' . $e->getMessage());
            $timeline = collect();
        }

        $sessionsData = $case->sessions->map(fn($s) => [
            'id' => $s->id,
            'case_id' => $s->case_id,
            'date' => $s->date?->format('Y-m-d\TH:i'),
            'location' => $s->location,
            'status' => $s->status,
            'notes' => $s->notes,
            'report' => $s->report,
        ])->values();

        $aiMessagesData = $case->aiMessages->sortBy('created_at')->take(-40)->map(fn($m) => [
            'id' => $m->id,
            'role' => $m->role,
            'content' => $m->content,
            'created_at' => $m->created_at?->format('Y/m/d H:i'),
        ])->values();

        return view('cases.show', compact('case', 'sessionsData', 'aiMessagesData', 'timeline'));
    }

    /**
     * تحويل سجل تدقيق إلى بند خط زمني مقروء: من فعل ماذا، القديم → الجديد.
     * يعيد null للسجلات غير المفيدة للعرض (تفادياً للضجيج).
     */
    private function describeAuditForTimeline(\App\Models\AuditLog $log): ?array
    {
        $who = $log->user?->name ?? 'النظام';

        $modelLabel = match ($log->model_type) {
            LegalCase::class => 'القضية',
            Session::class => 'الجلسة',
            \App\Models\Task::class => 'المهمة',
            \App\Models\Document::class => 'المستند',
            \App\Models\CaseActivity::class => null, // النشاطات تظهر بنفسها في الخط الزمني
            default => null,
        };
        if ($modelLabel === null) {
            return null;
        }

        $fieldLabels = [
            'status' => 'الحالة', 'priority' => 'الأولوية', 'court' => 'المحكمة',
            'case_type' => 'النوع', 'title' => 'العنوان', 'lawyer_id' => 'المحامي',
            'date' => 'الموعد', 'location' => 'المكان', 'due_date' => 'الاستحقاق',
            'assigned_to' => 'المسؤول', 'notes' => 'الملاحظات', 'report' => 'القرار',
        ];
        $statusLabels = [
            'active' => 'نشطة', 'pending' => 'قيد المتابعة', 'overdue' => 'متأخرة',
            'closed' => 'مغلقة', 'won' => 'مكسوبة', 'lost' => 'مخسورة',
            'adjudicated' => 'مفصولة', 'fees_pending' => 'أتعاب معلقة',
            'upcoming' => 'قادمة', 'completed' => 'منجزة', 'postponed' => 'مؤجلة',
            'cancelled' => 'ملغاة', 'in_progress' => 'قيد الإنجاز',
        ];
        $fmt = fn ($v) => $statusLabels[$v] ?? (is_scalar($v) ? mb_substr((string) $v, 0, 40) : '—');

        if ($log->action === \App\Models\AuditLog::ACTION_DELETE) {
            $name = $log->old_values['title'] ?? '';

            return [
                'kind' => 'audit',
                'label' => '🗑️ ' . $who . ' حذف ' . $modelLabel . ($name ? ' «' . mb_substr($name, 0, 50) . '»' : ''),
                'sub' => 'حذف مسجّل في سجل التدقيق',
            ];
        }

        if ($log->action === \App\Models\AuditLog::ACTION_UPDATE) {
            $changes = [];
            foreach ((array) $log->new_values as $field => $new) {
                $old = $log->old_values[$field] ?? null;
                if (!isset($fieldLabels[$field]) || $old == $new) {
                    continue;
                }
                $changes[] = $fieldLabels[$field] . ': ' . $fmt($old) . ' ← ' . $fmt($new);
                if (count($changes) >= 3) {
                    break;
                }
            }
            if (!$changes) {
                return null;
            }

            return [
                'kind' => 'audit',
                'label' => '✏️ ' . $who . ' عدّل ' . $modelLabel,
                'sub' => implode(' • ', $changes),
            ];
        }

        // الإنشاء يظهر أصلاً كبنود (جلسة/مهمة/مستند) — نعرض من أنشأه للقضية فقط
        if ($log->action === \App\Models\AuditLog::ACTION_CREATE && $log->model_type === LegalCase::class) {
            return [
                'kind' => 'audit',
                'label' => '⚖️ ' . $who . ' أنشأ القضية',
                'sub' => 'فُتح الملف',
            ];
        }

        return null;
    }

    /** تبديل حالة بند قائمة التحقق (من القالب الذكي) — ضمن صلاحية الوصول للقضية. */
    public function toggleChecklistItem(LegalCase $case, \App\Models\CaseChecklistItem $item): RedirectResponse
    {
        $this->authorizeCaseAccess($case);
        abort_unless($item->case_id === $case->id, 404);

        $item->update($item->is_done
            ? ['is_done' => false, 'done_by' => null, 'done_at' => null]
            : ['is_done' => true, 'done_by' => auth()->id(), 'done_at' => now()]);

        return back()->with('success', $item->is_done ? 'أُنجز البند ✓' : 'أُعيد البند لغير منجز.');
    }

    public function edit(LegalCase $case): View
    {
        $this->authorizeCaseAccess($case);

        $case->load('sessions');
        $clients = Client::orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get();

        return view('cases.edit', compact('case', 'clients', 'users'));
    }

    public function update(Request $request, LegalCase $case): RedirectResponse
    {
        $this->authorizeCaseAccess($case);

        $validated = $request->validate([
            'case_number'         => 'required|string|unique:cases,case_number,' . $case->id,
            'case_type'           => 'nullable|string|max:255',
            'title'               => 'nullable|string|max:255',
            'description'         => 'required|string',
            'type'                => 'nullable|string|max:255',
            'court'               => 'required|string|max:255',
            'opponent'            => 'required|string',
            'opponent_phone'      => 'nullable|string|max:255',
            'opponent_address'    => 'nullable|string',
            'opponent_lawyer'     => 'nullable|string|max:255',
            'opponent_civil_number' => 'nullable|string|max:255',
            'status'              => 'required|in:active,pending,overdue,closed,won,lost,adjudicated,fees_pending',
            'priority'            => 'required|in:low,medium,high,urgent',
            'client_id'           => 'required|exists:clients,id',
            'lawyer_id'           => 'nullable|exists:users,id',
        ]);

        if (empty($validated['title'])) {
            $validated['title'] = $case->title;
        }

        if (empty($validated['type']) && !empty($validated['case_type'])) {
            $validated['type'] = $validated['case_type'];
        } elseif (empty($validated['type'])) {
            $validated['type'] = $case->type ?: 'مدني';
        }

        $sessionErrors = [];
        $sessionsData = $request->input('sessions', []);
        if (is_array($sessionsData)) {
            foreach ($sessionsData as $i => $s) {
                if (!empty($s['delete'])) continue;
                if (empty($s['date'])) {
                    $sessionErrors[] = "الجلسة " . ($i + 1) . ": التاريخ مطلوب";
                } elseif (!strtotime($s['date'])) {
                    $sessionErrors[] = "الجلسة " . ($i + 1) . ": تاريخ غير صالح";
                }
            }
        }
        if (!empty($sessionErrors)) {
            return redirect()->back()->withInput()->with('error', implode('<br>', $sessionErrors));
        }

        DB::beginTransaction();
        try {
            $oldValues = $case->toArray();
            $case->update($validated);

            if (isset($validated['status']) && $oldValues['status'] !== $validated['status'] && $case->lawyer_id) {
                \App\Support\Notify::send(
                    userId: $case->lawyer_id,
                    titleKey: 'app.notif_case_status_title',
                    messageKey: 'app.notif_case_status_body',
                    params: [
                        'case' => $case->title,
                        'from' => __('app.status_' . $oldValues['status']),
                        'to' => __('app.status_' . $validated['status']),
                    ],
                    notifiableType: LegalCase::class,
                    notifiableId: $case->id,
                );
            }

            // Process sessions
            $processedIds = [];
            if (is_array($sessionsData)) {
                foreach ($sessionsData as $sessionData) {
                    if (!empty($sessionData['delete'])) {
                        Session::where('id', $sessionData['id'])->delete();
                        continue;
                    }
                    if (empty($sessionData['date'])) continue;

                    $sessionFields = [
                        'case_id'  => $case->id,
                        'date'     => $sessionData['date'],
                        'location' => $sessionData['location'] ?? '',
                        'status'   => $sessionData['status'] ?? 'upcoming',
                        'notes'    => $sessionData['notes'] ?? '',
                        'report'   => $sessionData['report'] ?? '',
                    ];

                    if (!empty($sessionData['id'])) {
                        Session::where('id', $sessionData['id'])->update($sessionFields);
                        $processedIds[] = $sessionData['id'];
                    } else {
                        $newSession = Session::create($sessionFields);
                        $processedIds[] = $newSession->id;
                    }
                }
            }

            DB::commit();

            $this->logAudit(
                AuditLog::ACTION_UPDATE,
                LegalCase::class,
                $case->id,
                $oldValues,
                $case->fresh()->toArray()
            );

            \App\Services\ClientNotifier::notifyCaseUpdate($case->fresh());

            return redirect()->route('cases.show', $case)
                ->with('success', 'Case updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'حدث خطأ أثناء تحديث القضية والجلسات: ' . $e->getMessage());
        }
    }

    public function destroy(LegalCase $case): RedirectResponse
    {
        $this->authorizeCaseAccess($case);

        $user = auth()->user();
        abort_unless($case->created_by === $user->id || in_array($user->role, ['developer', 'admin']), 403);

        $oldValues = $case->toArray();
        $caseNumber = $case->case_number;
        $title = $case->title;
        $case->delete();

        $this->logAudit(
            AuditLog::ACTION_DELETE,
            LegalCase::class,
            $case->id,
            $oldValues,
            null
        );

        return redirect()->route('cases.index')
            ->with('success', 'Case deleted successfully.');
    }

    public function summarize(LegalCase $case): JsonResponse
    {
        $this->authorizeCaseAccess($case);
        $case->load(['client', 'lawyer', 'sessions', 'tasks', 'documents']);

        return response()->json([
            'id'           => $case->id,
            'case_number'  => $case->case_number,
            'title'        => $case->title,
            'status'       => $case->status,
            'priority'     => $case->priority,
            'client'       => $case->client?->name,
            'lawyer'       => $case->lawyer?->name,
            'opened_at'    => $case->opened_at?->format('Y-m-d'),
            'next_date'    => $case->next_date?->format('Y-m-d'),
            'sessions_count' => $case->sessions->count(),
            'sessions'     => $case->sessions->count(),
            'tasks'        => $case->tasks->count(),
            'documents'    => $case->documents->count(),
        ]);
    }

    public function autoDetectOverdue(): RedirectResponse
    {
        $updated = 0;
        $startOfToday = now()->startOfDay();

        // قضية متأخّرة بأحد أمرين، لا بواحد فقط:
        //   • جلسة قادمة مرّ موعدها ولم يُحدَّث حالها.
        //   • تاريخ الجلسة القادمة المسجَّل في القضية نفسها قد مضى.
        // كان الكشف يقرأ الأول وحده، فقضية لها تاريخ قادم فات ولا جلسة
        // مسجَّلة تبقى «نشطة» إلى أن ينتبه أحد.
        //
        // «اليوم» ليس تأخّراً: الجلسة التي موعدها اليوم لم يفُت موعدها.
        LegalCase::where('status', 'active')
            ->with(['sessions' => fn ($q) => $q->where('status', 'upcoming')->orderByDesc('date')])
            ->chunk(100, function ($cases) use (&$updated, $startOfToday) {
                foreach ($cases as $case) {
                    $latestSession = $case->sessions->first();

                    $sessionPassed = $latestSession
                        && $latestSession->date
                        && \Illuminate\Support\Carbon::parse($latestSession->date)->lt($startOfToday);

                    $nextDatePassed = $case->next_date
                        && \Illuminate\Support\Carbon::parse($case->next_date)->lt($startOfToday);

                    if ($sessionPassed || $nextDatePassed) {
                        $case->update(['status' => 'overdue']);
                        $updated++;
                    }
                }
            });

        // «كشف المتأخرة» يعرض المتأخرة وحدها.
        //
        // كان الزر يعيد إلى القائمة كما هي، فيقف المستخدم أمام كل
        // القضايا بحثاً عمّا كُشف للتوّ. الكشف والعرض عملٌ واحد في
        // ذهن من يضغط الزر، فليكونا واحداً في النظام.
        $total = LegalCase::where('status', 'overdue')->count();

        return redirect()
            ->route('cases.index', ['status' => 'overdue'])
            ->with('success', match (true) {
                $updated > 0 => __('app.overdue_marked', ['count' => $updated, 'total' => $total]),
                $total > 0   => __('app.overdue_existing', ['total' => $total]),
                default      => __('app.overdue_none'),
            });
    }

    public function trashed(): View
    {
        $query = LegalCase::onlyTrashed()->with(['client', 'lawyer']);

        $cases = $query->latest('deleted_at')->paginate(15);
        return view('cases.trashed', compact('cases'));
    }

    public function monthly(Request $request): View
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $cases = LegalCase::with(['client', 'lawyer', 'sessions' => fn($q) => $q->orderBy('date', 'desc')])
            ->whereYear('opened_at', $year)
            ->whereMonth('opened_at', $month)
            ->latest('opened_at')
            ->get();

        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'إبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        $monthName = $months[(int)$month] ?? '';
        $years = range(now()->year - 5, now()->year + 1);

        $summary = [
            'total' => $cases->count(),
            'active' => $cases->where('status', 'active')->count(),
            'closed' => $cases->whereIn('status', ['closed', 'won', 'lost'])->count(),
            'pending' => $cases->where('status', 'pending')->count(),
        ];

        $casesJson = $cases->map(fn($c) => [
            'id' => $c->id,
            'case_number' => $c->case_number,
            'title' => $c->title,
            'client_name' => $c->client?->name,
            'client_url' => $c->client ? route('clients.show', $c->client) : null,
            'court' => $c->court,
            'status' => $c->status,
            'last_session_date' => $c->sessions->first()?->date?->format('Y-m-d'),
            'show_url' => route('cases.show', $c),
        ])->values();

        $summaryJson = $summary;

        return view('cases.monthly', compact('cases', 'month', 'year', 'monthName', 'years', 'months', 'summary', 'casesJson', 'summaryJson'));
    }

    public function monthlyData(Request $request): JsonResponse
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $cases = LegalCase::with(['client', 'lawyer', 'sessions' => fn($q) => $q->orderBy('date', 'desc')])
            ->whereYear('opened_at', $year)
            ->whereMonth('opened_at', $month)
            ->latest('opened_at')
            ->get();

        $summary = [
            'total' => $cases->count(),
            'active' => $cases->where('status', 'active')->count(),
            'closed' => $cases->whereIn('status', ['closed', 'won', 'lost'])->count(),
            'pending' => $cases->where('status', 'pending')->count(),
        ];

        $casesData = $cases->map(fn($c) => [
            'id' => $c->id,
            'case_number' => $c->case_number,
            'title' => $c->title,
            'client_name' => $c->client?->name,
            'client_url' => $c->client ? route('clients.show', $c->client) : null,
            'court' => $c->court,
            'status' => $c->status,
            'last_session_date' => $c->sessions->first()?->date?->format('Y-m-d'),
            'show_url' => route('cases.show', $c),
        ])->values();

        return response()->json(['cases' => $casesData, 'summary' => $summary]);
    }

    public function restore(int $id): RedirectResponse
    {
        $case = LegalCase::onlyTrashed()->findOrFail($id);

        $case->restore();
        return redirect()->route('cases.index')->with('success', 'تم استرجاع القضية بنجاح');
    }

    public function analyze(LegalCase $case): JsonResponse
    {
        @set_time_limit(180);

        $service = new GeminiService();

        if (!$service->isConfigured()) {
            return response()->json([
                'error' => \App\Support\AiSettings::notConfiguredMessage(),
            ], 400);
        }

        $case->load(['client', 'lawyer', 'sessions', 'tasks']);

        $prompt = <<<PROMPT
أنت محامٍ خبير وأستاذ قانون في سلطنة عمان، متخصص في تطبيق القوانين العمانية السارية:
- قانون المعاملات المدنية العماني
- قانون الإجراءات المدنية والتجارية العماني
- قانون الإثبات في المعاملات المدنية والتجارية العماني
- قانون العمل العماني
- قانون الشركات التجارية العماني
- قانون التجارة العماني
- قانون الجزاء العماني وقانون الإجراءات الجزائية العماني
- قانون المرافعات الشرعية العماني وأحكام الأحوال الشخصية
- قوانين التنفيذ المدني والجزائي العماني
- نظام المحاماة العماني وقرارات المهن القانونية
- أحكام المحكمة العليا العمانية ومبادئها المستقرة

قم بتحليل القضية التالية بشكل احترافي وعميق باللغة العربية:

**بيانات القضية:**
- رقم القضية: {$case->case_number}
- نوع القضية: {$case->case_type} ({$case->type})
- عنوان القضية: {$case->title}
- المحكمة: {$case->court}
- الحالة: {$case->status}
- الأولوية: {$case->priority}
- وصف القضية: {$case->description}
- الخصم: {$case->opponent}
- محامي الخصم: {$case->opponent_lawyer}
- الموكل: {$case->client?->name}
- المحامي المسؤول: {$case->lawyer?->name}

**الجلسات:**
{$this->sessionsText($case)}

**المهام المنجزة/المعلقة:**
{$this->tasksText($case)}

قم بتقديم تحليل منظم بالأقسام التالية (استخدم عناوين واضحة):
1. **تقييم القضية**: تحليل قوة الدعوى وفرص نجاحها وفقاً للقانون العماني، مع ذكر الأساس القانوني والمبادئ المستند إليها.
2. **المواد والمراجع القانونية**: اذكر النصوص القانونية العمانية ذات الصلة بنوع القضية (قانون المعاملات المدنية، قانون الإثبات، قانون الإجراءات المدنية، قانون العمل، قانون الجزاء، قانون الشركات... حسب طبيعة القضية)، مع شرح دلالتها على هذه القضية.
3. **نقاط ضعف الخصم**: تحليل نقاط الضعف في موقف الخصم والدفوع المحتملة ضده.
4. **توقع النتيجة**: توقع محتمل لنتيجة القضية بناءً على المعطيات والاجتهاد القضائي العماني.
5. **خطة العمل**: خطوات إجرائية مقترحة للمحامي (مذكرات، مستندات، شهود، خبرة، وساطة...) مرتبة حسب الأولوية.
6. **المخاطر**: مخاطر محتملة وكيفية التعامل معها.

تعليمات الأسلوب والعمق:
- تصرف وكأنك أشطر محامٍ وأخبر خبير قانوني في العالم، وكأنك تقدم مرافعة مكتوبة لمحكمة عليا.
- كن موسعاً وشاملاً: لا تختصر، ولا تنتقل بين الأقسام بسرعة، وافتح كل قسم بشرح متعمق وتفصيل دقيق واستدلال قانوني قوي.
- اذكر احتمالات متعددة مع تحليل لكل احتمال، وقدم نصائح استراتيجية عملية.
- استخدم أمثلة واقعية وأنماط من الاجتهاد القضائي.
- لا تختلق نصوص مواد قانونية غير موجودة؛ إذا لم تكن متأكداً من رقم المادة، اذكر المبدأ القانوني والمرجع العام دون رقم مادة محدد، ونبّه أن يتم التحقق من النص الرسمي.
- كن واقعياً ومحايداً ولا تبالغ في التوقعات.
- إذا كانت المعلومات غير كافية في بعض النقاط، اذكر ذلك بصراحة واقترح ما يجب استكماله.
- التزم بالقوانين السارية في سلطنة عمان فقط.
- لا تختتم قبل إتمام جميع الأقسام الستة بشكل كامل.
PROMPT;

        $analysis = $service->analyze($prompt);

        if (!$analysis) {
            return response()->json([
                'error' => $service->getLastError() ?: 'تعذر الحصول على التحليل من خدمة Gemini، حاول مرة أخرى لاحقاً',
            ], 502);
        }

        $case->ai_analysis = $analysis;
        $case->save();

        return response()->json([
            'analysis' => $analysis,
        ]);
    }

    public function aiChat(Request $request, LegalCase $case): JsonResponse
    {
        $this->authorizeCaseAccess($case);

        @set_time_limit(180);

        $request->validate([
            'message' => 'required|string|max:4000',
        ]);

        $service = new GeminiService();

        if (!$service->isConfigured()) {
            return response()->json([
                'error' => \App\Support\AiSettings::notConfiguredMessage(),
            ], 400);
        }

        $userMessage = trim($request->input('message'));

        try {
            $case->aiMessages()->create([
                'role' => 'user',
                'content' => $userMessage,
            ]);

            $case->load(['client', 'lawyer', 'sessions', 'tasks']);

        $history = $case->aiMessages()
            ->orderBy('created_at', 'asc')
            ->take(40)
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->toArray();

        $systemPrompt = <<<SYSTEM
أنت مساعد قانوني ذكي مدمج في نظام إدارة مكتب محاماة عماني. أنت محامٍ خبير في القوانين السارية في سلطنة عمان (قانون المعاملات المدنية، قانون الإجراءات المدنية والتجارية، قانون الإثبات، قانون العمل، قانون الشركات التجارية، قانون التجارة، قانون الجزاء، قانون الإجراءات الجزائية، قانون المرافعات الشرعية، قوانين التنفيذ، نظام المحاماة، وأحكام المحكمة العليا العمانية).

بيانات القضية الحالية:
- رقم القضية: {$case->case_number}
- نوع القضية: {$case->case_type} ({$case->type})
- عنوان القضية: {$case->title}
- المحكمة: {$case->court}
- الحالة: {$case->status}
- وصف القضية: {$case->description}
- الخصم: {$case->opponent}
- الموكل: {$case->client?->name}
- المحامي المسؤول: {$case->lawyer?->name}

الجلسات السابقة:
{$this->sessionsText($case)}

المهام:
{$this->tasksText($case)}

قواعد الرد:
- أجب باللغة العربية الفصحى دائماً.
- استند في إجاباتك إلى القانون العماني فقط، واذكر القانون أو المبدأ ذي الصلة.
- لا تختلق نصوص مواد قانونية؛ إذا لم تكن متأكداً من رقم المادة، اشرح المبدأ القانوني والمرجع العام ونبّه للتحقق من النص الرسمي.
- أجب بإجابات عملية ومركزة ومختصرة قدر الإمكان (دون إسهاب غير ضروري).
- استخدم عناوين أو نقاط عند الحاجة لتسهيل القراءة.
- يمكنك الرد على أسئلة عامة عن القانون العماني أيضاً.
- تذكّر سياق القضية الحالية عند الإجابة، وأشر إليه عند الاقتضاء.
- إذا سُئلت عن شيء خارج القانون أو خطر، اعتذر بلطف.
SYSTEM;

        $reply = $service->chat($history, $systemPrompt);

            if (!$reply) {
                return response()->json([
                    'error' => $service->getLastError() ?: 'تعذر الحصول على رد من الذكاء الاصطناعي، حاول مرة أخرى لاحقاً',
                ], 502);
            }

            $case->aiMessages()->create([
                'role' => 'assistant',
                'content' => $reply,
            ]);

            return response()->json([
                'reply' => $reply,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AI chat failed for case ' . $case->id . ': ' . $e->getMessage());
            return response()->json([
                'error' => 'خطأ من خدمة الذكاء الاصطناعي: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function sendPortalMessage(LegalCase $case): JsonResponse
    {
        $this->authorizeCaseAccess($case);

        $result = $this->notifyClientPortal($case);
        $sentChannels = $result['sent'];
        $failures = $result['failures'];

        if (empty($sentChannels)) {
            return response()->json([
                'error' => 'تعذر الإرسال التلقائي: ' . implode(' | ', $failures),
                'fallback_wa_link' => $result['fallback_wa_link'],
            ], 400);
        }

        $channelsText = count($sentChannels) > 1
            ? 'البريد الإلكتروني وواتساب'
            : ($sentChannels[0] === 'email' ? 'البريد الإلكتروني' : 'واتساب');

        return response()->json([
            'success' => true,
            'channels' => $sentChannels,
            'message' => 'تم إرسال رسالة المتابعة للموكل عبر ' . $channelsText,
            'failures' => $failures,
        ]);
    }

    private function notifyClientPortal(LegalCase $case): array
    {
        $case->loadMissing('client');
        $client = $case->client;

        if (!$client) {
            return ['sent' => [], 'failures' => ['لا يوجد موكل مرتبط بهذه القضية'], 'fallback_wa_link' => null];
        }

        $message = ClientMessage::portalInvite($case);
        $sentChannels = [];
        $failures = [];

        // Email - automatic
        if ($client->email) {
            if (config('mail.default', 'log') !== 'log') {
                try {
                    Mail::raw($message, function ($m) use ($client, $case) {
                        $m->from(ClientMessage::fromAddress(), ClientMessage::officeName());
                        $m->to($client->email)
                            ->subject(ClientMessage::inviteSubject($case));
                    });
                    $sentChannels[] = 'email';
                } catch (\Throwable $e) {
                    Log::error('Portal invite email failed: ' . $e->getMessage());
                    $failures[] = 'الإيميل: ' . $e->getMessage();
                }
            } else {
                $failures[] = 'الإيميل غير مفعل في إعدادات الخادم';
            }
        }

        // WhatsApp - Meta Cloud API (preferred) or Green API fallback
        $waUrl = config('services.whatsapp.url', '');
        $waToken = config('services.whatsapp.token', '');
        $metaToken = config('services.whatsapp.meta_token', '');
        $metaPhoneId = config('services.whatsapp.meta_phone_id', '');
        $waTemplate = config('services.whatsapp.template', '');

        $phoneDigits = preg_replace('/[^0-9+]/', '', (string) $client->phone);
        $phoneDigits = ltrim($phoneDigits, '+');

        $infobipConfigured = config('services.infobip.base_url')
            && config('services.infobip.api_key')
            && config('services.infobip.sender')
            && config('services.infobip.template');

        if ($client->phone && $metaToken && $metaPhoneId && $waTemplate) {
            try {
                $response = Http::withToken($metaToken)
                    ->timeout(30)
                    ->post("https://graph.facebook.com/v21.0/{$metaPhoneId}/messages", [
                        'messaging_product' => 'whatsapp',
                        'to' => $phoneDigits,
                        'type' => 'template',
                        'template' => [
                            'name' => $waTemplate,
                            'language' => ['code' => 'ar'],
                            'components' => [
                                ['type' => 'body', 'parameters' => [
                                    ['type' => 'text', 'text' => $case->client?->name ?: 'الموكل'],
                                    ['type' => 'text', 'text' => $case->case_number ?: '—'],
                                ]],
                            ],
                        ],
                    ]);
                if ($response->successful()) {
                    $sentChannels[] = 'whatsapp';
                } else {
                    Log::error('Portal invite whatsapp (meta) failed: status=' . $response->status() . ' body=' . $response->body());
                    $err = $response->json('error');
                    if (is_array($err)) {
                        $code = $err['code'] ?? ($err['subcode'] ?? null);
                        $msg = $err['message'] ?? '';
                        $failures[] = 'الواتساب: ' . ($code !== null ? "[$code] " : '') . $msg;
                    } else {
                        $failures[] = 'الواتساب: رمز الحالة ' . $response->status();
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Portal invite whatsapp (meta) failed: ' . $e->getMessage());
                $failures[] = 'الواتساب: ' . $e->getMessage();
            }

            if (!in_array('whatsapp', $sentChannels) && $infobipConfigured) {
                if ($this->sendInfobipMessage($phoneDigits, $case->client?->name ?: 'الموكل', $case->case_number ?: '—')) {
                    $sentChannels[] = 'whatsapp';
                } else {
                    $failures[] = 'الواتساب (Infobip): فشل الإرسال';
                }
            }
        } elseif ($client->phone && $infobipConfigured) {
            if ($this->sendInfobipMessage($phoneDigits, $case->client?->name ?: 'الموكل', $case->case_number ?: '—')) {
                $sentChannels[] = 'whatsapp';
            } else {
                $failures[] = 'الواتساب (Infobip): فشل الإرسال';
            }
        } elseif ($client->phone && $waUrl && $waToken) {
            try {
                $phone = preg_replace('/^\+/', '', $client->phone);
                $phone = str_contains($phone, '@') ? $phone : $phone . '@c.us';
                $response = Http::timeout(30)
                    ->post(rtrim($waUrl, '/') . '/sendMessage/' . $waToken, [
                        'chatId' => $phone,
                        'message' => $message,
                    ]);
                if ($response->successful()) {
                    $sentChannels[] = 'whatsapp';
                } else {
                    $failures[] = 'الواتساب: رمز الحالة ' . $response->status();
                }
            } catch (\Throwable $e) {
                Log::error('Portal invite whatsapp failed: ' . $e->getMessage());
                $failures[] = 'الواتساب: ' . $e->getMessage();
            }
        }

        $fallbackWaLink = null;
        if ($client->phone && !in_array('whatsapp', $sentChannels)) {
            $fallbackWaLink = 'https://wa.me/' . ltrim($client->phone, '+') . '?text=' . urlencode($message);
        }

        return ['sent' => $sentChannels, 'failures' => $failures, 'fallback_wa_link' => $fallbackWaLink];
    }

    protected function sendInfobipMessage(string $to, string $name, string $caseNumber): bool
    {
        $baseUrl = rtrim((string) config('services.infobip.base_url'), '/');
        $apiKey = (string) config('services.infobip.api_key');
        $sender = (string) config('services.infobip.sender');
        $template = (string) config('services.infobip.template');
        $language = (string) config('services.infobip.language');

        $vars = array_filter(array_map('trim', explode(',', (string) config('services.infobip.template_vars'))));
        $values = ['name' => $name, 'case' => $caseNumber];
        $placeholders = [];
        foreach ($vars as $var) {
            $placeholders[] = $values[$var] ?? '';
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'App ' . $apiKey,
                'Accept' => 'application/json',
            ])->timeout(30)->post("{$baseUrl}/whatsapp/1/message/template", [
                'messages' => [[
                    'from' => $sender,
                    'to' => $to,
                    'messageId' => (string) \Illuminate\Support\Str::uuid(),
                    'content' => [
                        'templateName' => $template,
                        'templateData' => [
                            'body' => ['placeholders' => $placeholders],
                        ],
                        'language' => $language,
                    ],
                ]],
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('Portal invite whatsapp (infobip) failed: status=' . $response->status() . ' body=' . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error('Portal invite whatsapp (infobip) failed: ' . $e->getMessage());
            return false;
        }
    }

    /** النصّ من مصدر واحد يحمل اسم كل مكتب ورابطه. */
    protected function portalInviteMessage(?LegalCase $case = null): string
    {
        return ClientMessage::portalInvite($case);
    }

    private function sessionsText(LegalCase $case): string
    {
        return $case->sessions->map(function ($s) {
            return "- {$s->date?->format('Y-m-d')} ({$s->status}): {$s->notes} {$s->report}";
        })->join("\n") ?: '- لا توجد جلسات مسجلة';
    }

    private function tasksText(LegalCase $case): string
    {
        return $case->tasks->map(function ($t) {
            return "- {$t->title} (حالة: {$t->status})";
        })->join("\n") ?: '- لا توجد مهام مسجلة';
    }

    private function authorizeCaseAccess(LegalCase $case): void
    {
        // All team members can access any case
    }

}
