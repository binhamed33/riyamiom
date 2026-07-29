<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LegalCase;
use App\Models\Notification;
use App\Models\Session;
use App\Models\User;
use App\Traits\AuditLoggable;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourtSessionController extends Controller
{
    use AuditLoggable;
    public function index(Request $request): View
    {
        $query = Session::with(['case.client', 'case.lawyer']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('case_id')) {
            $query->where('case_id', $request->case_id);
        }

        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        $sessions = $query->orderBy('date', 'desc')->paginate(15)->withQueryString();

        return view('sessions.index', compact('sessions'));
    }

    public function create(): View
    {
        $cases = LegalCase::orderBy('title')->get();

        return view('sessions.create', compact('cases'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'case_id'  => 'required|exists:cases,id',
            'date'     => 'required|date',
            'location' => 'required|string|max:255',
            'status'   => 'required|in:upcoming,completed,postponed,cancelled',
            'notes'    => 'nullable|string',
            'report'   => 'nullable|string',
        ]);

        $case = LegalCase::findOrFail($validated['case_id']);

        $session = Session::create($validated);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            Session::class,
            $session->id,
            null,
            $session->toArray()
        );

        $case = LegalCase::find($validated['case_id']);
        if ($case && $case->lawyer_id) {
            Notification::create([
                'user_id'         => $case->lawyer_id,
                'title'           => 'New Court Session Scheduled',
                'message'         => "A court session has been scheduled for case '{$case->title}' on {$session->date}.",
                'type'            => Notification::TYPE_INFO,
                'is_read'         => false,
                'notifiable_type' => Session::class,
                'notifiable_id'   => $session->id,
            ]);
        }

        return redirect()->route('sessions.show', $session)
            ->with('success', 'Court session created successfully.');
    }

    public function show(Session $session): View
    {
        $this->authorizeSessionAccess($session);

        $session->load('case.client', 'case.lawyer');

        return view('sessions.show', compact('session'));
    }

    public function edit(Session $session): View
    {
        $this->authorizeSessionAccess($session);

        $cases = LegalCase::orderBy('title')->get();

        return view('sessions.edit', compact('session', 'cases'));
    }

    public function update(Request $request, Session $session): RedirectResponse
    {
        $this->authorizeSessionAccess($session);

        $validated = $request->validate([
            'case_id'  => 'required|exists:cases,id',
            'date'     => 'required|date',
            'location' => 'required|string|max:255',
            'status'   => 'required|in:upcoming,completed,postponed,cancelled',
            'notes'    => 'nullable|string',
            'report'   => 'nullable|string',
        ]);

        $oldValues = $session->toArray();
        $session->update($validated);

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Session::class,
            $session->id,
            $oldValues,
            $session->toArray()
        );

        return redirect()->route('sessions.show', $session)
            ->with('success', 'Court session updated successfully.');
    }

    public function destroy(Session $session): RedirectResponse
    {
        $this->authorizeSessionAccess($session);

        $oldValues = $session->toArray();
        $session->delete();

        $this->logAudit(
            AuditLog::ACTION_DELETE,
            Session::class,
            $session->id,
            $oldValues,
            null
        );

        return redirect()->route('sessions.index')
            ->with('success', 'Court session deleted successfully.');
    }

    private function authorizeSessionAccess(Session $session): void
    {
        // All team members can access any session
    }

    public function today(): View
    {
        $query = Session::with(['case.client', 'case.lawyer'])
            ->whereDate('date', Carbon::today())
            ->where('status', 'upcoming')
            ->orderBy('date');

        $sessions = $query->paginate(15);

        return view('sessions.index', compact('sessions'));
    }

}
