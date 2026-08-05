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
            'status'              => 'required|in:active,pending,overdue,closed,won,lost,adjudicated,fees_pending',
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
            $validated['type'] = 'Ù…Ø¯Ù†ÙŠ';
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
                    $sessionErrors[] = "Ø§Ù„Ø¬Ù„Ø³Ø© " . ($i + 1) . ": Ø§Ù„ØªØ§Ø±ÙŠØ® Ù…Ø·Ù„ÙˆØ¨";
                } elseif (!strtotime($s['date'])) {
                    $sessionErrors[] = "Ø§Ù„Ø¬Ù„Ø³Ø© " . ($i + 1) . ": ØªØ§Ø±ÙŠØ® ØºÙŠØ± ØµØ§Ù„Ø­";
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
            return redirect()->back()->withInput()->with('error', 'Ø­Ø¯Ø« Ø®Ø·Ø£ Ø£Ø«Ù†Ø§Ø¡ Ø­ÙØ¸ Ø§Ù„Ù‚Ø¶ÙŠØ© ÙˆØ§Ù„Ø¬Ù„Ø³Ø§Øª: ' . $e->getMessage());
        }
    }

    private function redirectAfterStore(LegalCase $legalCase): RedirectResponse
    {
        try {
            $notify = $this->notifyClientPortal($legalCase);

            if (!empty($notify['sent'])) {
                $channelsText = count($notify['sent']) > 1
                    ? 'Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ ÙˆÙˆØ§ØªØ³Ø§Ø¨'
                    : ($notify['sent'][0] === 'email' ? 'Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ' : 'ÙˆØ§ØªØ³Ø§Ø¨');
                $notice = 'ØªÙ… Ø¥Ø±Ø³Ø§Ù„ Ø±Ø³Ø§Ù„Ø© Ø§Ù„Ù…ØªØ§Ø¨Ø¹Ø© Ù„Ù„Ù…ÙˆÙƒÙ„ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹ Ø¹Ø¨Ø± ' . $channelsText;
                if (!empty($notify['failures'])) {
                    $notice .= ' â€” ' . implode(' | ', $notify['failures']);
                }
                return redirect()->route('cases.show', $legalCase)
                    ->with('success', 'case_created')
                    ->with('portal_notice', $notice)
                    ->with('print_url', route('cases.show', $legalCase) . '?print=1');
            }

            if (!empty($notify['failures'])) {
                return redirect()->route('cases.show', $legalCase)
                    ->with('success', 'case_created')
                    ->with('portal_notice', 'Ù„Ù… ÙŠØªÙ… Ø¥Ø±Ø³Ø§Ù„ Ø±Ø³Ø§Ù„Ø© Ø§Ù„Ù…ØªØ§Ø¨Ø¹Ø© ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹: ' . implode(' | ', $notify['failures']))
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
            $validated['type'] = $case->type ?: 'Ù…Ø¯Ù†ÙŠ';
        }

        $sessionErrors = [];
        $sessionsData = $request->input('sessions', []);
        if (is_array($sessionsData)) {
            foreach ($sessionsData as $i => $s) {
                if (!empty($s['delete'])) continue;
                if (empty($s['date'])) {
                    $sessionErrors[] = "Ø§Ù„Ø¬Ù„Ø³Ø© " . ($i + 1) . ": Ø§Ù„ØªØ§Ø±ÙŠØ® Ù…Ø·Ù„ÙˆØ¨";
                } elseif (!strtotime($s['date'])) {
                    $sessionErrors[] = "Ø§Ù„Ø¬Ù„Ø³Ø© " . ($i + 1) . ": ØªØ§Ø±ÙŠØ® ØºÙŠØ± ØµØ§Ù„Ø­";
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
                    'title'           => 'ØªÙ… ØªØºÙŠÙŠØ± Ø­Ø§Ù„Ø© Ø§Ù„Ù‚Ø¶ÙŠØ©',
                    'message'         => "ØªÙ… ØªØºÙŠÙŠØ± Ø­Ø§Ù„Ø© Ù‚Ø¶ÙŠØ© '{$case->title}' Ù…Ù† {$oldValues['status']} Ø¥Ù„Ù‰ {$validated['status']}",
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
            return redirect()->back()->withInput()->with('error', 'Ø­Ø¯Ø« Ø®Ø·Ø£ Ø£Ø«Ù†Ø§Ø¡ ØªØ­Ø¯ÙŠØ« Ø§Ù„Ù‚Ø¶ÙŠØ© ÙˆØ§Ù„Ø¬Ù„Ø³Ø§Øª: ' . $e->getMessage());
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
            1 => 'ÙŠÙ†Ø§ÙŠØ±', 2 => 'ÙØ¨Ø±Ø§ÙŠØ±', 3 => 'Ù…Ø§Ø±Ø³', 4 => 'Ø¥Ø¨Ø±ÙŠÙ„',
            5 => 'Ù…Ø§ÙŠÙˆ', 6 => 'ÙŠÙˆÙ†ÙŠÙˆ', 7 => 'ÙŠÙˆÙ„ÙŠÙˆ', 8 => 'Ø£ØºØ³Ø·Ø³',
            9 => 'Ø³Ø¨ØªÙ…Ø¨Ø±', 10 => 'Ø£ÙƒØªÙˆØ¨Ø±', 11 => 'Ù†ÙˆÙÙ…Ø¨Ø±', 12 => 'Ø¯ÙŠØ³Ù…Ø¨Ø±',
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
        return redirect()->route('cases.index')->with('success', 'ØªÙ… Ø§Ø³ØªØ±Ø¬Ø§Ø¹ Ø§Ù„Ù‚Ø¶ÙŠØ© Ø¨Ù†Ø¬Ø§Ø­');
    }

    public function analyze(LegalCase $case): JsonResponse
    {
        @set_time_limit(180);

        $service = new GeminiService();

        if (!$service->isConfigured()) {
            return response()->json([
                'error' => 'Ù„Ù… ÙŠØªÙ… Ø¥Ø¹Ø¯Ø§Ø¯ Ù…ÙØªØ§Ø­ Gemini ÙÙŠ Ù…Ù„Ù Ø§Ù„Ø¥Ø¹Ø¯Ø§Ø¯Ø§ØªØŒ ÙŠØ±Ø¬Ù‰ Ø§Ù„ØªÙˆØ§ØµÙ„ Ù…Ø¹ Ø§Ù„Ù…Ø·ÙˆØ±',
            ], 400);
        }

        $case->load(['client', 'lawyer', 'sessions', 'tasks']);

        $prompt = <<<PROMPT
Ø£Ù†Øª Ù…Ø­Ø§Ù…Ù Ø®Ø¨ÙŠØ± ÙˆØ£Ø³ØªØ§Ø° Ù‚Ø§Ù†ÙˆÙ† ÙÙŠ Ø³Ù„Ø·Ù†Ø© Ø¹Ù…Ø§Ù†ØŒ Ù…ØªØ®ØµØµ ÙÙŠ ØªØ·Ø¨ÙŠÙ‚ Ø§Ù„Ù‚ÙˆØ§Ù†ÙŠÙ† Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠØ© Ø§Ù„Ø³Ø§Ø±ÙŠØ©:
- Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ù…Ø¯Ù†ÙŠØ© Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ
- Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª Ø§Ù„Ù…Ø¯Ù†ÙŠØ© ÙˆØ§Ù„ØªØ¬Ø§Ø±ÙŠØ© Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ
- Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¥Ø«Ø¨Ø§Øª ÙÙŠ Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ù…Ø¯Ù†ÙŠØ© ÙˆØ§Ù„ØªØ¬Ø§Ø±ÙŠØ© Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ
- Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¹Ù…Ù„ Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ
- Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø´Ø±ÙƒØ§Øª Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ© Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ
- Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„ØªØ¬Ø§Ø±Ø© Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ
- Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¬Ø²Ø§Ø¡ Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ ÙˆÙ‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª Ø§Ù„Ø¬Ø²Ø§Ø¦ÙŠØ© Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ
- Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ù…Ø±Ø§ÙØ¹Ø§Øª Ø§Ù„Ø´Ø±Ø¹ÙŠØ© Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ ÙˆØ£Ø­ÙƒØ§Ù… Ø§Ù„Ø£Ø­ÙˆØ§Ù„ Ø§Ù„Ø´Ø®ØµÙŠØ©
- Ù‚ÙˆØ§Ù†ÙŠÙ† Ø§Ù„ØªÙ†ÙÙŠØ° Ø§Ù„Ù…Ø¯Ù†ÙŠ ÙˆØ§Ù„Ø¬Ø²Ø§Ø¦ÙŠ Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ
- Ù†Ø¸Ø§Ù… Ø§Ù„Ù…Ø­Ø§Ù…Ø§Ø© Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ ÙˆÙ‚Ø±Ø§Ø±Ø§Øª Ø§Ù„Ù…Ù‡Ù† Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ©
- Ø£Ø­ÙƒØ§Ù… Ø§Ù„Ù…Ø­ÙƒÙ…Ø© Ø§Ù„Ø¹Ù„ÙŠØ§ Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠØ© ÙˆÙ…Ø¨Ø§Ø¯Ø¦Ù‡Ø§ Ø§Ù„Ù…Ø³ØªÙ‚Ø±Ø©

Ù‚Ù… Ø¨ØªØ­Ù„ÙŠÙ„ Ø§Ù„Ù‚Ø¶ÙŠØ© Ø§Ù„ØªØ§Ù„ÙŠØ© Ø¨Ø´ÙƒÙ„ Ø§Ø­ØªØ±Ø§ÙÙŠ ÙˆØ¹Ù…ÙŠÙ‚ Ø¨Ø§Ù„Ù„ØºØ© Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©:

**Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù‚Ø¶ÙŠØ©:**
- Ø±Ù‚Ù… Ø§Ù„Ù‚Ø¶ÙŠØ©: {$case->case_number}
- Ù†ÙˆØ¹ Ø§Ù„Ù‚Ø¶ÙŠØ©: {$case->case_type} ({$case->type})
- Ø¹Ù†ÙˆØ§Ù† Ø§Ù„Ù‚Ø¶ÙŠØ©: {$case->title}
- Ø§Ù„Ù…Ø­ÙƒÙ…Ø©: {$case->court}
- Ø§Ù„Ø­Ø§Ù„Ø©: {$case->status}
- Ø§Ù„Ø£ÙˆÙ„ÙˆÙŠØ©: {$case->priority}
- ÙˆØµÙ Ø§Ù„Ù‚Ø¶ÙŠØ©: {$case->description}
- Ø§Ù„Ø®ØµÙ…: {$case->opponent}
- Ù…Ø­Ø§Ù…ÙŠ Ø§Ù„Ø®ØµÙ…: {$case->opponent_lawyer}
- Ø§Ù„Ù…ÙˆÙƒÙ„: {$case->client?->name}
- Ø§Ù„Ù…Ø­Ø§Ù…ÙŠ Ø§Ù„Ù…Ø³Ø¤ÙˆÙ„: {$case->lawyer?->name}

**Ø§Ù„Ø¬Ù„Ø³Ø§Øª:**
{$this->sessionsText($case)}

**Ø§Ù„Ù…Ù‡Ø§Ù… Ø§Ù„Ù…Ù†Ø¬Ø²Ø©/Ø§Ù„Ù…Ø¹Ù„Ù‚Ø©:**
{$this->tasksText($case)}

Ù‚Ù… Ø¨ØªÙ‚Ø¯ÙŠÙ… ØªØ­Ù„ÙŠÙ„ Ù…Ù†Ø¸Ù… Ø¨Ø§Ù„Ø£Ù‚Ø³Ø§Ù… Ø§Ù„ØªØ§Ù„ÙŠØ© (Ø§Ø³ØªØ®Ø¯Ù… Ø¹Ù†Ø§ÙˆÙŠÙ† ÙˆØ§Ø¶Ø­Ø©):
1. **ØªÙ‚ÙŠÙŠÙ… Ø§Ù„Ù‚Ø¶ÙŠØ©**: ØªØ­Ù„ÙŠÙ„ Ù‚ÙˆØ© Ø§Ù„Ø¯Ø¹ÙˆÙ‰ ÙˆÙØ±Øµ Ù†Ø¬Ø§Ø­Ù‡Ø§ ÙˆÙÙ‚Ø§Ù‹ Ù„Ù„Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠØŒ Ù…Ø¹ Ø°ÙƒØ± Ø§Ù„Ø£Ø³Ø§Ø³ Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠ ÙˆØ§Ù„Ù…Ø¨Ø§Ø¯Ø¦ Ø§Ù„Ù…Ø³ØªÙ†Ø¯ Ø¥Ù„ÙŠÙ‡Ø§.
2. **Ø§Ù„Ù…ÙˆØ§Ø¯ ÙˆØ§Ù„Ù…Ø±Ø§Ø¬Ø¹ Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ©**: Ø§Ø°ÙƒØ± Ø§Ù„Ù†ØµÙˆØµ Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ© Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠØ© Ø°Ø§Øª Ø§Ù„ØµÙ„Ø© Ø¨Ù†ÙˆØ¹ Ø§Ù„Ù‚Ø¶ÙŠØ© (Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ù…Ø¯Ù†ÙŠØ©ØŒ Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¥Ø«Ø¨Ø§ØªØŒ Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª Ø§Ù„Ù…Ø¯Ù†ÙŠØ©ØŒ Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¹Ù…Ù„ØŒ Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¬Ø²Ø§Ø¡ØŒ Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø´Ø±ÙƒØ§Øª... Ø­Ø³Ø¨ Ø·Ø¨ÙŠØ¹Ø© Ø§Ù„Ù‚Ø¶ÙŠØ©)ØŒ Ù…Ø¹ Ø´Ø±Ø­ Ø¯Ù„Ø§Ù„ØªÙ‡Ø§ Ø¹Ù„Ù‰ Ù‡Ø°Ù‡ Ø§Ù„Ù‚Ø¶ÙŠØ©.
3. **Ù†Ù‚Ø§Ø· Ø¶Ø¹Ù Ø§Ù„Ø®ØµÙ…**: ØªØ­Ù„ÙŠÙ„ Ù†Ù‚Ø§Ø· Ø§Ù„Ø¶Ø¹Ù ÙÙŠ Ù…ÙˆÙ‚Ù Ø§Ù„Ø®ØµÙ… ÙˆØ§Ù„Ø¯ÙÙˆØ¹ Ø§Ù„Ù…Ø­ØªÙ…Ù„Ø© Ø¶Ø¯Ù‡.
4. **ØªÙˆÙ‚Ø¹ Ø§Ù„Ù†ØªÙŠØ¬Ø©**: ØªÙˆÙ‚Ø¹ Ù…Ø­ØªÙ…Ù„ Ù„Ù†ØªÙŠØ¬Ø© Ø§Ù„Ù‚Ø¶ÙŠØ© Ø¨Ù†Ø§Ø¡Ù‹ Ø¹Ù„Ù‰ Ø§Ù„Ù…Ø¹Ø·ÙŠØ§Øª ÙˆØ§Ù„Ø§Ø¬ØªÙ‡Ø§Ø¯ Ø§Ù„Ù‚Ø¶Ø§Ø¦ÙŠ Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ.
5. **Ø®Ø·Ø© Ø§Ù„Ø¹Ù…Ù„**: Ø®Ø·ÙˆØ§Øª Ø¥Ø¬Ø±Ø§Ø¦ÙŠØ© Ù…Ù‚ØªØ±Ø­Ø© Ù„Ù„Ù…Ø­Ø§Ù…ÙŠ (Ù…Ø°ÙƒØ±Ø§ØªØŒ Ù…Ø³ØªÙ†Ø¯Ø§ØªØŒ Ø´Ù‡ÙˆØ¯ØŒ Ø®Ø¨Ø±Ø©ØŒ ÙˆØ³Ø§Ø·Ø©...) Ù…Ø±ØªØ¨Ø© Ø­Ø³Ø¨ Ø§Ù„Ø£ÙˆÙ„ÙˆÙŠØ©.
6. **Ø§Ù„Ù…Ø®Ø§Ø·Ø±**: Ù…Ø®Ø§Ø·Ø± Ù…Ø­ØªÙ…Ù„Ø© ÙˆÙƒÙŠÙÙŠØ© Ø§Ù„ØªØ¹Ø§Ù…Ù„ Ù…Ø¹Ù‡Ø§.

ØªØ¹Ù„ÙŠÙ…Ø§Øª Ø§Ù„Ø£Ø³Ù„ÙˆØ¨ ÙˆØ§Ù„Ø¹Ù…Ù‚:
- ØªØµØ±Ù ÙˆÙƒØ£Ù†Ùƒ Ø£Ø´Ø·Ø± Ù…Ø­Ø§Ù…Ù ÙˆØ£Ø®Ø¨Ø± Ø®Ø¨ÙŠØ± Ù‚Ø§Ù†ÙˆÙ†ÙŠ ÙÙŠ Ø§Ù„Ø¹Ø§Ù„Ù…ØŒ ÙˆÙƒØ£Ù†Ùƒ ØªÙ‚Ø¯Ù… Ù…Ø±Ø§ÙØ¹Ø© Ù…ÙƒØªÙˆØ¨Ø© Ù„Ù…Ø­ÙƒÙ…Ø© Ø¹Ù„ÙŠØ§.
- ÙƒÙ† Ù…ÙˆØ³Ø¹Ø§Ù‹ ÙˆØ´Ø§Ù…Ù„Ø§Ù‹: Ù„Ø§ ØªØ®ØªØµØ±ØŒ ÙˆÙ„Ø§ ØªÙ†ØªÙ‚Ù„ Ø¨ÙŠÙ† Ø§Ù„Ø£Ù‚Ø³Ø§Ù… Ø¨Ø³Ø±Ø¹Ø©ØŒ ÙˆØ§ÙØªØ­ ÙƒÙ„ Ù‚Ø³Ù… Ø¨Ø´Ø±Ø­ Ù…ØªØ¹Ù…Ù‚ ÙˆØªÙØµÙŠÙ„ Ø¯Ù‚ÙŠÙ‚ ÙˆØ§Ø³ØªØ¯Ù„Ø§Ù„ Ù‚Ø§Ù†ÙˆÙ†ÙŠ Ù‚ÙˆÙŠ.
- Ø§Ø°ÙƒØ± Ø§Ø­ØªÙ…Ø§Ù„Ø§Øª Ù…ØªØ¹Ø¯Ø¯Ø© Ù…Ø¹ ØªØ­Ù„ÙŠÙ„ Ù„ÙƒÙ„ Ø§Ø­ØªÙ…Ø§Ù„ØŒ ÙˆÙ‚Ø¯Ù… Ù†ØµØ§Ø¦Ø­ Ø§Ø³ØªØ±Ø§ØªÙŠØ¬ÙŠØ© Ø¹Ù…Ù„ÙŠØ©.
- Ø§Ø³ØªØ®Ø¯Ù… Ø£Ù…Ø«Ù„Ø© ÙˆØ§Ù‚Ø¹ÙŠØ© ÙˆØ£Ù†Ù…Ø§Ø· Ù…Ù† Ø§Ù„Ø§Ø¬ØªÙ‡Ø§Ø¯ Ø§Ù„Ù‚Ø¶Ø§Ø¦ÙŠ.
- Ù„Ø§ ØªØ®ØªÙ„Ù‚ Ù†ØµÙˆØµ Ù…ÙˆØ§Ø¯ Ù‚Ø§Ù†ÙˆÙ†ÙŠØ© ØºÙŠØ± Ù…ÙˆØ¬ÙˆØ¯Ø©Ø› Ø¥Ø°Ø§ Ù„Ù… ØªÙƒÙ† Ù…ØªØ£ÙƒØ¯Ø§Ù‹ Ù…Ù† Ø±Ù‚Ù… Ø§Ù„Ù…Ø§Ø¯Ø©ØŒ Ø§Ø°ÙƒØ± Ø§Ù„Ù…Ø¨Ø¯Ø£ Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠ ÙˆØ§Ù„Ù…Ø±Ø¬Ø¹ Ø§Ù„Ø¹Ø§Ù… Ø¯ÙˆÙ† Ø±Ù‚Ù… Ù…Ø§Ø¯Ø© Ù…Ø­Ø¯Ø¯ØŒ ÙˆÙ†Ø¨Ù‘Ù‡ Ø£Ù† ÙŠØªÙ… Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù„Ù†Øµ Ø§Ù„Ø±Ø³Ù…ÙŠ.
- ÙƒÙ† ÙˆØ§Ù‚Ø¹ÙŠØ§Ù‹ ÙˆÙ…Ø­Ø§ÙŠØ¯Ø§Ù‹ ÙˆÙ„Ø§ ØªØ¨Ø§Ù„Øº ÙÙŠ Ø§Ù„ØªÙˆÙ‚Ø¹Ø§Øª.
- Ø¥Ø°Ø§ ÙƒØ§Ù†Øª Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª ØºÙŠØ± ÙƒØ§ÙÙŠØ© ÙÙŠ Ø¨Ø¹Ø¶ Ø§Ù„Ù†Ù‚Ø§Ø·ØŒ Ø§Ø°ÙƒØ± Ø°Ù„Ùƒ Ø¨ØµØ±Ø§Ø­Ø© ÙˆØ§Ù‚ØªØ±Ø­ Ù…Ø§ ÙŠØ¬Ø¨ Ø§Ø³ØªÙƒÙ…Ø§Ù„Ù‡.
- Ø§Ù„ØªØ²Ù… Ø¨Ø§Ù„Ù‚ÙˆØ§Ù†ÙŠÙ† Ø§Ù„Ø³Ø§Ø±ÙŠØ© ÙÙŠ Ø³Ù„Ø·Ù†Ø© Ø¹Ù…Ø§Ù† ÙÙ‚Ø·.
- Ù„Ø§ ØªØ®ØªØªÙ… Ù‚Ø¨Ù„ Ø¥ØªÙ…Ø§Ù… Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø£Ù‚Ø³Ø§Ù… Ø§Ù„Ø³ØªØ© Ø¨Ø´ÙƒÙ„ ÙƒØ§Ù…Ù„.
PROMPT;

        $analysis = $service->analyze($prompt);

        if (!$analysis) {
            return response()->json([
                'error' => 'ØªØ¹Ø°Ø± Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø§Ù„ØªØ­Ù„ÙŠÙ„ØŒ Ø­Ø§ÙˆÙ„ Ù…Ø±Ø© Ø£Ø®Ø±Ù‰ Ù„Ø§Ø­Ù‚Ø§Ù‹',
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
                'error' => 'Ù„Ù… ÙŠØªÙ… Ø¥Ø¹Ø¯Ø§Ø¯ Ù…ÙØªØ§Ø­ Gemini ÙÙŠ Ù…Ù„Ù Ø§Ù„Ø¥Ø¹Ø¯Ø§Ø¯Ø§ØªØŒ ÙŠØ±Ø¬Ù‰ Ø§Ù„ØªÙˆØ§ØµÙ„ Ù…Ø¹ Ø§Ù„Ù…Ø·ÙˆØ±',
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
Ø£Ù†Øª Ù…Ø³Ø§Ø¹Ø¯ Ù‚Ø§Ù†ÙˆÙ†ÙŠ Ø°ÙƒÙŠ Ù…Ø¯Ù…Ø¬ ÙÙŠ Ù†Ø¸Ø§Ù… Ø¥Ø¯Ø§Ø±Ø© Ù…ÙƒØªØ¨ Ù…Ø­Ø§Ù…Ø§Ø© Ø¹Ù…Ø§Ù†ÙŠ. Ø£Ù†Øª Ù…Ø­Ø§Ù…Ù Ø®Ø¨ÙŠØ± ÙÙŠ Ø§Ù„Ù‚ÙˆØ§Ù†ÙŠÙ† Ø§Ù„Ø³Ø§Ø±ÙŠØ© ÙÙŠ Ø³Ù„Ø·Ù†Ø© Ø¹Ù…Ø§Ù† (Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ù…Ø¯Ù†ÙŠØ©ØŒ Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª Ø§Ù„Ù…Ø¯Ù†ÙŠØ© ÙˆØ§Ù„ØªØ¬Ø§Ø±ÙŠØ©ØŒ Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¥Ø«Ø¨Ø§ØªØŒ Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¹Ù…Ù„ØŒ Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø´Ø±ÙƒØ§Øª Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ©ØŒ Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„ØªØ¬Ø§Ø±Ø©ØŒ Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¬Ø²Ø§Ø¡ØŒ Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª Ø§Ù„Ø¬Ø²Ø§Ø¦ÙŠØ©ØŒ Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ù…Ø±Ø§ÙØ¹Ø§Øª Ø§Ù„Ø´Ø±Ø¹ÙŠØ©ØŒ Ù‚ÙˆØ§Ù†ÙŠÙ† Ø§Ù„ØªÙ†ÙÙŠØ°ØŒ Ù†Ø¸Ø§Ù… Ø§Ù„Ù…Ø­Ø§Ù…Ø§Ø©ØŒ ÙˆØ£Ø­ÙƒØ§Ù… Ø§Ù„Ù…Ø­ÙƒÙ…Ø© Ø§Ù„Ø¹Ù„ÙŠØ§ Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠØ©).

Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù‚Ø¶ÙŠØ© Ø§Ù„Ø­Ø§Ù„ÙŠØ©:
- Ø±Ù‚Ù… Ø§Ù„Ù‚Ø¶ÙŠØ©: {$case->case_number}
- Ù†ÙˆØ¹ Ø§Ù„Ù‚Ø¶ÙŠØ©: {$case->case_type} ({$case->type})
- Ø¹Ù†ÙˆØ§Ù† Ø§Ù„Ù‚Ø¶ÙŠØ©: {$case->title}
- Ø§Ù„Ù…Ø­ÙƒÙ…Ø©: {$case->court}
- Ø§Ù„Ø­Ø§Ù„Ø©: {$case->status}
- ÙˆØµÙ Ø§Ù„Ù‚Ø¶ÙŠØ©: {$case->description}
- Ø§Ù„Ø®ØµÙ…: {$case->opponent}
- Ø§Ù„Ù…ÙˆÙƒÙ„: {$case->client?->name}
- Ø§Ù„Ù…Ø­Ø§Ù…ÙŠ Ø§Ù„Ù…Ø³Ø¤ÙˆÙ„: {$case->lawyer?->name}

Ø§Ù„Ø¬Ù„Ø³Ø§Øª Ø§Ù„Ø³Ø§Ø¨Ù‚Ø©:
{$this->sessionsText($case)}

Ø§Ù„Ù…Ù‡Ø§Ù…:
{$this->tasksText($case)}

Ù‚ÙˆØ§Ø¹Ø¯ Ø§Ù„Ø±Ø¯:
- Ø£Ø¬Ø¨ Ø¨Ø§Ù„Ù„ØºØ© Ø§Ù„Ø¹Ø±Ø¨ÙŠØ© Ø§Ù„ÙØµØ­Ù‰ Ø¯Ø§Ø¦Ù…Ø§Ù‹.
- Ø§Ø³ØªÙ†Ø¯ ÙÙŠ Ø¥Ø¬Ø§Ø¨Ø§ØªÙƒ Ø¥Ù„Ù‰ Ø§Ù„Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ ÙÙ‚Ø·ØŒ ÙˆØ§Ø°ÙƒØ± Ø§Ù„Ù‚Ø§Ù†ÙˆÙ† Ø£Ùˆ Ø§Ù„Ù…Ø¨Ø¯Ø£ Ø°ÙŠ Ø§Ù„ØµÙ„Ø©.
- Ù„Ø§ ØªØ®ØªÙ„Ù‚ Ù†ØµÙˆØµ Ù…ÙˆØ§Ø¯ Ù‚Ø§Ù†ÙˆÙ†ÙŠØ©Ø› Ø¥Ø°Ø§ Ù„Ù… ØªÙƒÙ† Ù…ØªØ£ÙƒØ¯Ø§Ù‹ Ù…Ù† Ø±Ù‚Ù… Ø§Ù„Ù…Ø§Ø¯Ø©ØŒ Ø§Ø´Ø±Ø­ Ø§Ù„Ù…Ø¨Ø¯Ø£ Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠ ÙˆØ§Ù„Ù…Ø±Ø¬Ø¹ Ø§Ù„Ø¹Ø§Ù… ÙˆÙ†Ø¨Ù‘Ù‡ Ù„Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù„Ù†Øµ Ø§Ù„Ø±Ø³Ù…ÙŠ.
- Ø£Ø¬Ø¨ Ø¨Ø¥Ø¬Ø§Ø¨Ø§Øª Ø¹Ù…Ù„ÙŠØ© ÙˆÙ…Ø±ÙƒØ²Ø© ÙˆÙ…Ø®ØªØµØ±Ø© Ù‚Ø¯Ø± Ø§Ù„Ø¥Ù…ÙƒØ§Ù† (Ø¯ÙˆÙ† Ø¥Ø³Ù‡Ø§Ø¨ ØºÙŠØ± Ø¶Ø±ÙˆØ±ÙŠ).
- Ø§Ø³ØªØ®Ø¯Ù… Ø¹Ù†Ø§ÙˆÙŠÙ† Ø£Ùˆ Ù†Ù‚Ø§Ø· Ø¹Ù†Ø¯ Ø§Ù„Ø­Ø§Ø¬Ø© Ù„ØªØ³Ù‡ÙŠÙ„ Ø§Ù„Ù‚Ø±Ø§Ø¡Ø©.
- ÙŠÙ…ÙƒÙ†Ùƒ Ø§Ù„Ø±Ø¯ Ø¹Ù„Ù‰ Ø£Ø³Ø¦Ù„Ø© Ø¹Ø§Ù…Ø© Ø¹Ù† Ø§Ù„Ù‚Ø§Ù†ÙˆÙ† Ø§Ù„Ø¹Ù…Ø§Ù†ÙŠ Ø£ÙŠØ¶Ø§Ù‹.
- ØªØ°ÙƒÙ‘Ø± Ø³ÙŠØ§Ù‚ Ø§Ù„Ù‚Ø¶ÙŠØ© Ø§Ù„Ø­Ø§Ù„ÙŠØ© Ø¹Ù†Ø¯ Ø§Ù„Ø¥Ø¬Ø§Ø¨Ø©ØŒ ÙˆØ£Ø´Ø± Ø¥Ù„ÙŠÙ‡ Ø¹Ù†Ø¯ Ø§Ù„Ø§Ù‚ØªØ¶Ø§Ø¡.
- Ø¥Ø°Ø§ Ø³ÙØ¦Ù„Øª Ø¹Ù† Ø´ÙŠØ¡ Ø®Ø§Ø±Ø¬ Ø§Ù„Ù‚Ø§Ù†ÙˆÙ† Ø£Ùˆ Ø®Ø·Ø±ØŒ Ø§Ø¹ØªØ°Ø± Ø¨Ù„Ø·Ù.
SYSTEM;

        $reply = $service->chat($history, $systemPrompt);

            if (!$reply) {
                return response()->json([
                    'error' => 'ØªØ¹Ø°Ø± Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø±Ø¯ Ù…Ù† Ø§Ù„Ø°ÙƒØ§Ø¡ Ø§Ù„Ø§ØµØ·Ù†Ø§Ø¹ÙŠØŒ Ø­Ø§ÙˆÙ„ Ù…Ø±Ø© Ø£Ø®Ø±Ù‰ Ù„Ø§Ø­Ù‚Ø§Ù‹',
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
                'error' => 'Ø®Ø·Ø£ Ù…Ù† Ø®Ø¯Ù…Ø© Ø§Ù„Ø°ÙƒØ§Ø¡ Ø§Ù„Ø§ØµØ·Ù†Ø§Ø¹ÙŠ: ' . $e->getMessage(),
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
                'error' => 'ØªØ¹Ø°Ø± Ø§Ù„Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„ØªÙ„Ù‚Ø§Ø¦ÙŠ: ' . implode(' | ', $failures),
                'fallback_wa_link' => $result['fallback_wa_link'],
            ], 400);
        }

        $channelsText = count($sentChannels) > 1
            ? 'Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ ÙˆÙˆØ§ØªØ³Ø§Ø¨'
            : ($sentChannels[0] === 'email' ? 'Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ' : 'ÙˆØ§ØªØ³Ø§Ø¨');

        return response()->json([
            'success' => true,
            'channels' => $sentChannels,
            'message' => 'ØªÙ… Ø¥Ø±Ø³Ø§Ù„ Ø±Ø³Ø§Ù„Ø© Ø§Ù„Ù…ØªØ§Ø¨Ø¹Ø© Ù„Ù„Ù…ÙˆÙƒÙ„ Ø¹Ø¨Ø± ' . $channelsText,
            'failures' => $failures,
        ]);
    }

    private function notifyClientPortal(LegalCase $case): array
    {
        $case->loadMissing('client');
        $client = $case->client;

        if (!$client) {
            return ['sent' => [], 'failures' => ['Ù„Ø§ ÙŠÙˆØ¬Ø¯ Ù…ÙˆÙƒÙ„ Ù…Ø±ØªØ¨Ø· Ø¨Ù‡Ø°Ù‡ Ø§Ù„Ù‚Ø¶ÙŠØ©'], 'fallback_wa_link' => null];
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
                            ->subject('Ù…ØªØ§Ø¨Ø¹Ø© Ù‚Ø¶ÙŠØªÙƒ Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠØ§Ù‹ - Ø´Ø±ÙƒØ© Ø­Ù…Ø¯ Ø§Ù„Ø±ÙŠØ§Ù…ÙŠ Ù„Ù„Ù…Ø­Ø§Ù…Ø§Ø© (Ù‚Ø¶ÙŠØ© ' . $case->case_number . ')');
                    });
                    $sentChannels[] = 'email';
                } catch (\Throwable $e) {
                    Log::error('Portal invite email failed: ' . $e->getMessage());
                    $failures[] = 'Ø§Ù„Ø¥ÙŠÙ…ÙŠÙ„: ' . $e->getMessage();
                }
            } else {
                $failures[] = 'Ø§Ù„Ø¥ÙŠÙ…ÙŠÙ„ ØºÙŠØ± Ù…ÙØ¹Ù„ ÙÙŠ Ø¥Ø¹Ø¯Ø§Ø¯Ø§Øª Ø§Ù„Ø®Ø§Ø¯Ù…';
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
                                    ['type' => 'text', 'text' => $case->case_number ?: 'â€”'],
                                    ['type' => 'text', 'text' => 'https://office.riyami.om/client-access'],
                                ]],
                            ],
                        ],
                    ]);
                if ($response->successful()) {
                    $sentChannels[] = 'whatsapp';
                } else {
                    $failures[] = 'Ø§Ù„ÙˆØ§ØªØ³Ø§Ø¨: ' . ($response->json('error.message') ?? 'Ø±Ù…Ø² Ø§Ù„Ø­Ø§Ù„Ø© ' . $response->status());
                }
            } catch (\Throwable $e) {
                Log::error('Portal invite whatsapp (meta) failed: ' . $e->getMessage());
                $failures[] = 'Ø§Ù„ÙˆØ§ØªØ³Ø§Ø¨: ' . $e->getMessage();
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
                    $failures[] = 'Ø§Ù„ÙˆØ§ØªØ³Ø§Ø¨: Ø±Ù…Ø² Ø§Ù„Ø­Ø§Ù„Ø© ' . $response->status();
                }
            } catch (\Throwable $e) {
                Log::error('Portal invite whatsapp failed: ' . $e->getMessage());
                $failures[] = 'Ø§Ù„ÙˆØ§ØªØ³Ø§Ø¨: ' . $e->getMessage();
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
ÙŠØ³Ø± **Ø´Ø±ÙƒØ© Ø­Ù…Ø¯ Ø§Ù„Ø±ÙŠØ§Ù…ÙŠ Ù„Ù„Ù…Ø­Ø§Ù…Ø§Ø© (Ø´Ø±ÙƒØ© Ù…Ø¯Ù†ÙŠØ© Ù„Ù„Ù…Ø­Ø§Ù…Ø§Ø©)** Ø£Ù† ØªØ¶Ø¹ Ø¨ÙŠÙ† Ø£ÙŠØ¯ÙŠÙƒÙ… Ø®Ø¯Ù…Ø© **Ù…ØªØ§Ø¨Ø¹Ø© Ø§Ù„Ù‚Ø¶Ø§ÙŠØ§ Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠØ§Ù‹**ØŒ ÙˆØ°Ù„Ùƒ Ø­Ø±ØµØ§Ù‹ Ù…Ù†Ø§ Ø¹Ù„Ù‰ ØªØ¹Ø²ÙŠØ² Ø¬ÙˆØ¯Ø© Ø§Ù„Ø®Ø¯Ù…Ø§Øª Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ©ØŒ ÙˆØªÙˆÙÙŠØ± ØªØ¬Ø±Ø¨Ø© Ø£ÙƒØ«Ø± Ø³Ù‡ÙˆÙ„Ø© ÙˆØ´ÙØ§ÙÙŠØ© Ù„Ù…ÙˆÙƒÙ„ÙŠÙ†Ø§ Ø§Ù„ÙƒØ±Ø§Ù….

ÙŠÙ…ÙƒÙ†ÙƒÙ… Ø§Ù„Ø§Ø·Ù„Ø§Ø¹ Ø¹Ù„Ù‰ Ø¢Ø®Ø± Ù…Ø³ØªØ¬Ø¯Ø§Øª Ø§Ù„Ù‚Ø¶ÙŠØ©ØŒ ÙˆÙ…ØªØ§Ø¨Ø¹Ø© ØªÙØ§ØµÙŠÙ„Ù‡Ø§ Ø¨ÙƒÙ„ ÙŠØ³Ø±ØŒ Ù…Ù† Ø®Ù„Ø§Ù„ Ø§Ù„Ø¯Ø®ÙˆÙ„ Ø¥Ù„Ù‰ Ø§Ù„Ø±Ø§Ø¨Ø· Ø§Ù„ØªØ§Ù„ÙŠ:

https://office.riyami.om/client-access

Ø¨Ø¹Ø¯ ÙØªØ­ Ø§Ù„Ø±Ø§Ø¨Ø·ØŒ ÙŠÙØ±Ø¬Ù‰ Ø¥Ø¯Ø®Ø§Ù„ **Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ** Ø£Ùˆ **Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ** Ø§Ù„Ù…Ø³Ø¬Ù„ Ù„Ø¯Ù‰ Ø§Ù„Ù…ÙƒØªØ¨ØŒ Ù„ØªØ¸Ù‡Ø± Ù„ÙƒÙ… Ø¬Ù…ÙŠØ¹ ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ù‚Ø¶ÙŠØ© ÙˆØ§Ù„Ù…Ø³ØªØ¬Ø¯Ø§Øª Ø§Ù„Ù…ØªØ¹Ù„Ù‚Ø© Ø¨Ù‡Ø§ Ø¨Ø´ÙƒÙ„ Ù…Ø¨Ø§Ø´Ø±.

ÙˆÙÙŠ Ø­Ø§Ù„ ÙˆØ§Ø¬Ù‡ØªÙƒÙ… Ø£ÙŠ ØµØ¹ÙˆØ¨Ø© ÙÙŠ Ø§Ù„Ø¯Ø®ÙˆÙ„ Ø£Ùˆ ÙƒØ§Ù†Øª Ù„Ø¯ÙŠÙƒÙ… Ø£ÙŠ Ø§Ø³ØªÙØ³Ø§Ø±Ø§ØªØŒ ÙØ¥Ù† ÙØ±ÙŠÙ‚Ù†Ø§ Ø¹Ù„Ù‰ Ø£ØªÙ… Ø§Ù„Ø§Ø³ØªØ¹Ø¯Ø§Ø¯ Ù„Ø®Ø¯Ù…ØªÙƒÙ… ÙˆØ§Ù„Ø¥Ø¬Ø§Ø¨Ø© Ø¹Ù† Ø¬Ù…ÙŠØ¹ Ø§Ø³ØªÙØ³Ø§Ø±Ø§ØªÙƒÙ….

**Ø´Ø±ÙƒØ© Ø­Ù…Ø¯ Ø§Ù„Ø±ÙŠØ§Ù…ÙŠ Ù„Ù„Ù…Ø­Ø§Ù…Ø§Ø© (Ø´Ø±ÙƒØ© Ù…Ø¯Ù†ÙŠØ© Ù„Ù„Ù…Ø­Ø§Ù…Ø§Ø©)**
Ù†Ø¹ØªØ² Ø¨Ø«Ù‚ØªÙƒÙ…ØŒ ÙˆÙ†Ø³Ø¹Ù‰ Ø¯Ø§Ø¦Ù…Ø§Ù‹ Ø¥Ù„Ù‰ ØªÙ‚Ø¯ÙŠÙ… Ø®Ø¯Ù…Ø§Øª Ù‚Ø§Ù†ÙˆÙ†ÙŠØ© Ø§Ø­ØªØ±Ø§ÙÙŠØ© Ø¨Ø£Ø¹Ù„Ù‰ Ù…Ø¹Ø§ÙŠÙŠØ± Ø§Ù„Ø¬ÙˆØ¯Ø©.
TXT;
    }

    private function sessionsText(LegalCase $case): string
    {
        return $case->sessions->map(function ($s) {
            return "- {$s->date?->format('Y-m-d')} ({$s->status}): {$s->notes} {$s->report}";
        })->join("\n") ?: '- Ù„Ø§ ØªÙˆØ¬Ø¯ Ø¬Ù„Ø³Ø§Øª Ù…Ø³Ø¬Ù„Ø©';
    }

    private function tasksText(LegalCase $case): string
    {
        return $case->tasks->map(function ($t) {
            return "- {$t->title} (Ø­Ø§Ù„Ø©: {$t->status})";
        })->join("\n") ?: '- Ù„Ø§ ØªÙˆØ¬Ø¯ Ù…Ù‡Ø§Ù… Ù…Ø³Ø¬Ù„Ø©';
    }

    private function authorizeCaseAccess(LegalCase $case): void
    {
        // All team members can access any case
    }

}
