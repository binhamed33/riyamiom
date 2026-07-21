<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Notification;
use App\Models\User;

use App\Traits\AuditLoggable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaseController extends Controller
{
    use AuditLoggable;

    public function index(Request $request): View
    {
        $query = LegalCase::with(['client', 'lawyer']);

        if (auth()->user()->isLawyer()) {
            $query->where('lawyer_id', auth()->id());
        }

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
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        $cases = $query->latest()->paginate(15)->withQueryString();
        $lawyers = User::where('role', 'lawyer')->get();

        return view('cases.index', compact('cases', 'lawyers'));
    }

    public function create(): View
    {
        $clients = Client::orderBy('name')->get();
        $lawyers = User::where('role', 'lawyer')->orderBy('name')->get();

        return view('cases.create', compact('clients', 'lawyers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'case_number' => 'required|string|unique:cases,case_number',
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'type'        => 'required|string|max:255',
            'court'       => 'required|string|max:255',
            'opponent'    => 'required|string|max:255',
            'status'      => 'required|in:active,pending,overdue,closed,won,lost',
            'priority'    => 'required|in:low,medium,high,urgent',
            'opened_at'   => 'required|date',
            'next_date'   => 'nullable|date|after_or_equal:opened_at',
            'client_id'   => 'required|exists:clients,id',
            'lawyer_id'   => 'nullable|exists:users,id',
        ]);

        $legalCase = LegalCase::create($validated);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            LegalCase::class,
            $legalCase->id,
            null,
            $legalCase->toArray()
        );

        return redirect()->route('cases.show', $legalCase)
            ->with('success', 'Case created successfully.');
    }

    public function show(LegalCase $case): View
    {
        $this->authorizeCaseAccess($case);

        $case->load(['client', 'lawyer', 'sessions', 'tasks.assignee', 'documents.uploader']);

        return view('cases.show', compact('case'));
    }

    public function edit(LegalCase $case): View
    {
        $this->authorizeCaseAccess($case);

        $clients = Client::orderBy('name')->get();
        $lawyers = User::where('role', 'lawyer')->orderBy('name')->get();

        return view('cases.edit', compact('case', 'clients', 'lawyers'));
    }

    public function update(Request $request, LegalCase $case): RedirectResponse
    {
        $this->authorizeCaseAccess($case);

        $validated = $request->validate([
            'case_number' => 'required|string|unique:cases,case_number,' . $case->id,
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'type'        => 'required|string|max:255',
            'court'       => 'required|string|max:255',
            'opponent'    => 'required|string|max:255',
            'status'      => 'required|in:active,pending,overdue,closed,won,lost',
            'priority'    => 'required|in:low,medium,high,urgent',
            'opened_at'   => 'required|date',
            'next_date'   => 'nullable|date|after_or_equal:opened_at',
            'client_id'   => 'required|exists:clients,id',
            'lawyer_id'   => 'nullable|exists:users,id',
        ]);

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

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            LegalCase::class,
            $case->id,
            $oldValues,
            $case->toArray()
        );

        return redirect()->route('cases.show', $case)
            ->with('success', 'Case updated successfully.');
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
            'next_date'    => $case->next_date?->format('Y-m-d'),
            'sessions'     => $case->sessions->count(),
            'tasks'        => $case->tasks->count(),
            'documents'    => $case->documents->count(),
        ]);
    }

    public function autoDetectOverdue(): RedirectResponse
    {
        if (auth()->user()->isLawyer()) {
            abort(403);
        }

        $updated = LegalCase::where('status', 'active')
            ->where('next_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        return redirect()->back()
            ->with('success', "{$updated} cases marked as overdue.");
    }

    public function trashed(): View
    {
        $query = LegalCase::onlyTrashed()->with(['client', 'lawyer']);

        if (auth()->user()->isLawyer()) {
            $query->where('lawyer_id', auth()->id());
        }

        $cases = $query->latest('deleted_at')->paginate(15);
        return view('cases.trashed', compact('cases'));
    }

    public function restore(int $id): RedirectResponse
    {
        $case = LegalCase::onlyTrashed()->findOrFail($id);

        $user = auth()->user();
        if ($user->isLawyer() && $case->lawyer_id !== $user->id) {
            abort(403);
        }

        $case->restore();
        return redirect()->route('cases.index')->with('success', 'تم استرجاع القضية بنجاح');
    }

    private function authorizeCaseAccess(LegalCase $case): void
    {
        $user = auth()->user();
        if ($user->isLawyer() && $case->lawyer_id !== $user->id) {
            abort(403);
        }
    }

}
