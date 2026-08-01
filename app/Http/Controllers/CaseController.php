<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Notification;
use App\Models\Session;
use App\Models\User;

use App\Traits\AuditLoggable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\View\View;

class CaseController extends Controller
{
    use AuditLoggable;

    public function index(Request $request): View
    {
        $query = LegalCase::with(['client', 'lawyer']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('lawyer_id')) {
            $query->where('lawyer_id', $request->lawyer_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'like', "%{$search}%")
                    ->orWhere('office_case_number', 'like', "%{$search}%")
                    ->orWhere('opponent_phone', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        $cases = $query->orderBy(DB::raw('CAST(office_case_number AS UNSIGNED)'))->paginate(15)->withQueryString();
        $users = User::where('is_active', true)->orderBy('name')->get();

        return view('cases.index', compact('cases', 'users'));
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
            'case_type'           => 'nullable|in:مدني,تجاري,عمالي,أحوال شخصية,جزائي,تنفيذ مدني,تنفيذ جزائي,قضاء مستعجل,أوامر على العرائض,إفلاس وإعادة هيكلة,إيجارات,مرور,أحداث,اداري,استثمار,استشكال,تظلمات',
            'title'               => 'required|string|max:255',
            'description'         => 'required|string',
            'type'                => 'nullable|string|max:255',
            'court'               => 'required|string|max:255',
            'opponent'            => 'required|string',
            'opponent_phone'      => 'nullable|string|max:255',
            'opponent_address'    => 'nullable|string',
            'opponent_lawyer'     => 'nullable|string|max:255',
            'opponent_civil_number' => 'nullable|string|max:255',
            'status'              => 'required|in:active,pending,overdue,closed,won,lost',
            'priority'            => 'required|in:low,medium,high,urgent',
            'client_id'           => 'required|exists:clients,id',
            'lawyer_id'           => 'nullable|exists:users,id',
        ]);

        if (empty($validated['type']) && !empty($validated['case_type'])) {
            $validated['type'] = $validated['case_type'];
        } elseif (empty($validated['type'])) {
            $validated['type'] = 'مدني';
        }

        $maxOffice = LegalCase::max(DB::raw('office_case_number + 0'));
        $validated['office_case_number'] = (string) ((int) ($maxOffice ?? 0) + 1);

        if (auth()->user()->isLawyer() && empty($validated['lawyer_id'])) {
            $validated['lawyer_id'] = auth()->id();
        }

        $sessionErrors = [];
        $sessionsData = $request->input('sessions', []);
        if (is_array($sessionsData)) {
            foreach ($sessionsData as $i => $s) {
                if (!empty($s['date']) && !strtotime($s['date'])) {
                    $sessionErrors[] = "الجلسة " . ($i + 1) . ": تاريخ غير صالح";
                }
            }
        }
        if (!empty($sessionErrors)) {
            return redirect()->back()->withInput()->with('error', implode('<br>', $sessionErrors));
        }

        DB::beginTransaction();
        try {
            $legalCase = LegalCase::create($validated);

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

            DB::commit();

            $this->logAudit(
                AuditLog::ACTION_CREATE,
                LegalCase::class,
                $legalCase->id,
                null,
                $legalCase->toArray()
            );

            return redirect()->route('cases.show', $legalCase)
                ->with('success', 'case_created')
                ->with('print_url', route('cases.show', $legalCase) . '?print=1');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'حدث خطأ أثناء حفظ القضية والجلسات: ' . $e->getMessage());
        }
    }

    public function show(LegalCase $case): View
    {
        $this->authorizeCaseAccess($case);

        $case->load(['client', 'lawyer', 'sessions', 'tasks.assignee', 'documents.uploader']);

        $sessionsData = $case->sessions->map(fn($s) => [
            'id' => $s->id,
            'case_id' => $s->case_id,
            'date' => $s->date?->format('Y-m-d H:i:s'),
            'location' => $s->location,
            'status' => $s->status,
            'notes' => $s->notes,
            'report' => $s->report,
        ])->values();

        return view('cases.show', compact('case', 'sessionsData'));
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
            'case_type'           => 'nullable|in:مدني,تجاري,عمالي,أحوال شخصية,جزائي,تنفيذ مدني,تنفيذ جزائي,قضاء مستعجل,أوامر على العرائض,إفلاس وإعادة هيكلة,إيجارات,مرور,أحداث,اداري,استثمار,استشكال,تظلمات',
            'title'               => 'required|string|max:255',
            'description'         => 'required|string',
            'type'                => 'nullable|string|max:255',
            'court'               => 'required|string|max:255',
            'opponent'            => 'required|string',
            'opponent_phone'      => 'nullable|string|max:255',
            'opponent_address'    => 'nullable|string',
            'opponent_lawyer'     => 'nullable|string|max:255',
            'opponent_civil_number' => 'nullable|string|max:255',
            'status'              => 'required|in:active,pending,overdue,closed,won,lost',
            'priority'            => 'required|in:low,medium,high,urgent',
            'client_id'           => 'required|exists:clients,id',
            'lawyer_id'           => 'nullable|exists:users,id',
        ]);

        if (empty($validated['type']) && !empty($validated['case_type'])) {
            $validated['type'] = $validated['case_type'];
        } elseif (empty($validated['type'])) {
            $validated['type'] = $case->type ?: 'مدني';
        }

        $sessionErrors = [];
        $sessionsData = $request->input('sessions', []);
        if (is_array($sessionsData)) {
            foreach ($sessionsData as $i => $s) {
                if (!empty($s['date']) && !strtotime($s['date'])) {
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
                Notification::create([
                    'user_id'         => $case->lawyer_id,
                    'title'           => 'تم تغيير حالة القضية',
                    'message'         => "تم تغيير حالة قضية '{$case->title}' من {$oldValues['status']} إلى {$validated['status']}",
                    'type'            => Notification::TYPE_INFO,
                    'is_read'         => false,
                    'notifiable_type' => LegalCase::class,
                    'notifiable_id'   => $case->id,
                ]);
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
            'sessions_count' => $case->sessions->count(),
            'sessions'     => $case->sessions->count(),
            'tasks'        => $case->tasks->count(),
            'documents'    => $case->documents->count(),
        ]);
    }

    public function autoDetectOverdue(): RedirectResponse
    {
        $updated = 0;
        LegalCase::where('status', 'active')->chunk(100, function ($cases) use (&$updated) {
            foreach ($cases as $case) {
                $latestSession = $case->sessions()->where('status', 'upcoming')->orderBy('date', 'desc')->first();
                if ($latestSession && $latestSession->date < now()) {
                    $case->update(['status' => 'overdue']);
                    $updated++;
                }
            }
        });

        return redirect()->back()
            ->with('success', "{$updated} cases marked as overdue.");
    }

    public function trashed(): View
    {
        $query = LegalCase::onlyTrashed()->with(['client', 'lawyer']);

        $cases = $query->latest('deleted_at')->paginate(15);
        return view('cases.trashed', compact('cases'));
    }

    public function monthly(Request $request): View
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

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
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

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
        $service = new \App\Services\GeminiService();

        if (!$service->isConfigured()) {
            return response()->json([
                'error' => 'لم يتم إعداد مفتاح Gemini في ملف الإعدادات، يرجى التواصل مع المطور',
            ], 400);
        }

        $case->load(['client', 'lawyer', 'sessions', 'tasks']);

        $sessionsText = $case->sessions->map(function ($s) {
            return "- {$s->date?->format('Y-m-d')} ({$s->status}): {$s->notes} {$s->report}";
        })->join("\n");

        $tasksText = $case->tasks->map(function ($t) {
            return "- {$t->title} (حالة: {$t->status})";
        })->join("\n");

        $prompt = <<<PROMPT
أنت خبير قانوني محامٍ في سلطنة عمان، متخصص في تحليل القضايا القانونية. قم بتحليل القضية التالية بشكل احترافي وشامل باللغة العربية:

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
{$sessionsText}

**المهام المنجزة/المعلقة:**
{$tasksText}

قم بتقديم تحليل منظم بالأقسام التالية (استخدم العناوين):
1. **تقييم القضية**: تحليل قوة الدعوى وفرص نجاحها
2. **نقاط ضعف الخصم**: تحليل نقاط الضعف في موقف الخصم
3. **توقع النتيجة**: توقع محتمل لنتيجة القضية بناءً على المعطيات
4. **خطة العمل**: خطوات مقترحة للمحامي للمضي قدماً
5. **المخاطر**: مخاطر محتملة وكيفية التعامل معها

كن واقعياً ومحايداً ولا تبالغ في التوقعات. إذا كانت المعلومات غير كافية في بعض النقاط، اذكر ذلك بصراحة.
PROMPT;

        $analysis = $service->analyze($prompt);

        if (!$analysis) {
            return response()->json([
                'error' => 'تعذر الحصول على التحليل، حاول مرة أخرى لاحقاً',
            ], 500);
        }

        $case->ai_analysis = $analysis;
        $case->save();

        return response()->json([
            'analysis' => $analysis,
        ]);
    }

    private function authorizeCaseAccess(LegalCase $case): void
    {
        // All team members can access any case
    }

}
