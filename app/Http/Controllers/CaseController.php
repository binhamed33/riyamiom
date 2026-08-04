<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CaseAiMessage;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Notification;
use App\Models\Session;
use App\Models\User;
use App\Services\GeminiService;

use App\Traits\AuditLoggable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
            'status'              => 'required|in:active,pending,overdue,closed,won,lost',
            'priority'            => 'required|in:low,medium,high,urgent',
            'client_id'           => 'required|exists:clients,id',
            'lawyer_id'           => 'nullable|exists:users,id',
        ]);

        if (empty($validated['title'])) {
            $validated['title'] = $validated['case_number'];
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

            return $this->redirectAfterStore($legalCase);
        } catch (\Exception $e) {
            DB::rollBack();
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

        $case->load(['client', 'lawyer', 'sessions', 'tasks.assignee', 'documents.uploader', 'aiMessages']);

        $sessionsData = $case->sessions->map(fn($s) => [
            'id' => $s->id,
            'case_id' => $s->case_id,
            'date' => $s->date?->format('Y-m-d'),
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

        return view('cases.show', compact('case', 'sessionsData', 'aiMessagesData'));
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
            'status'              => 'required|in:active,pending,overdue,closed,won,lost',
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
        @set_time_limit(180);

        $service = new GeminiService();

        if (!$service->isConfigured()) {
            return response()->json([
                'error' => 'لم يتم إعداد مفتاح Gemini في ملف الإعدادات، يرجى التواصل مع المطور',
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
                'error' => 'تعذر الحصول على التحليل، حاول مرة أخرى لاحقاً',
            ], 500);
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
                'error' => 'لم يتم إعداد مفتاح Gemini في ملف الإعدادات، يرجى التواصل مع المطور',
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
                    'error' => 'تعذر الحصول على رد من الذكاء الاصطناعي، حاول مرة أخرى لاحقاً',
                ], 500);
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

        $message = $this->portalInviteMessage();
        $sentChannels = [];
        $failures = [];

        // Email - automatic
        if ($client->email) {
            if (config('mail.default', 'log') !== 'log') {
                try {
                    Mail::raw($message, function ($m) use ($client, $case) {
                        $m->from(
                            \App\Models\Setting::get('office_email', config('mail.from.address', 'hello@example.com')),
                            \App\Models\Setting::get('office_name', config('mail.from.name', 'LexPro'))
                        );
                        $m->to($client->email)
                            ->subject('متابعة قضيتك إلكترونياً - شركة حمد الريامي للمحاماة (قضية ' . $case->case_number . ')');
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

        $phoneDigits = preg_replace('/^\+/', '', $client->phone);

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
                                    ['type' => 'text', 'text' => $case->case_number ?: '—'],
                                    ['type' => 'text', 'text' => 'https://office.riyami.om/client-access'],
                                ]],
                            ],
                        ],
                    ]);
                if ($response->successful()) {
                    $sentChannels[] = 'whatsapp';
                } else {
                    $failures[] = 'الواتساب: ' . ($response->json('error.message') ?? 'رمز الحالة ' . $response->status());
                }
            } catch (\Throwable $e) {
                Log::error('Portal invite whatsapp (meta) failed: ' . $e->getMessage());
                $failures[] = 'الواتساب: ' . $e->getMessage();
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

    protected function portalInviteMessage(): string
    {
        return <<<TXT
يسر **شركة حمد الريامي للمحاماة (شركة مدنية للمحاماة)** أن تضع بين أيديكم خدمة **متابعة القضايا إلكترونياً**، وذلك حرصاً منا على تعزيز جودة الخدمات القانونية، وتوفير تجربة أكثر سهولة وشفافية لموكلينا الكرام.

يمكنكم الاطلاع على آخر مستجدات القضية، ومتابعة تفاصيلها بكل يسر، من خلال الدخول إلى الرابط التالي:

https://office.riyami.om/client-access

بعد فتح الرابط، يُرجى إدخال **رقم الهاتف** أو **البريد الإلكتروني** المسجل لدى المكتب، لتظهر لكم جميع تفاصيل القضية والمستجدات المتعلقة بها بشكل مباشر.

وفي حال واجهتكم أي صعوبة في الدخول أو كانت لديكم أي استفسارات، فإن فريقنا على أتم الاستعداد لخدمتكم والإجابة عن جميع استفساراتكم.

**شركة حمد الريامي للمحاماة (شركة مدنية للمحاماة)**
نعتز بثقتكم، ونسعى دائماً إلى تقديم خدمات قانونية احترافية بأعلى معايير الجودة.
TXT;
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
