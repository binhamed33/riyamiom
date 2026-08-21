<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Traits\AuditLoggable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    use AuditLoggable;
    public function index(Request $request): View
    {
        $query = Client::withCount('cases');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $clients = $query->latest()->paginate(15)->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        return view('clients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'type'          => 'required|in:individual,company',
            'phone'         => 'nullable|string|max:255',
            'email'         => 'nullable|email|max:255',
            'address'       => 'nullable|string',
            'national_id'   => 'nullable|string|max:255',
            'company_name'  => 'nullable|string|max:255',
            'user_id'       => 'nullable|exists:users,id',
        ]);

        $client = Client::create($validated);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            Client::class,
            $client->id,
            null,
            $client->toArray()
        );

        return redirect()->route('clients.show', $client)
            ->with('success', 'Client created successfully.');
    }

    public function storeAjax(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'phone'       => 'nullable|string|max:255',
            'email'       => 'nullable|email|max:255',
            'national_id' => 'nullable|string|max:255',
            'address'     => 'nullable|string|max:500',
        ]);

        $validated['type'] = 'individual';
        $validated['phone'] = $validated['phone'] ?? '';
        $validated['email'] = $validated['email'] ?? '';

        $client = Client::create($validated);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            Client::class,
            $client->id,
            null,
            $client->toArray()
        );

        return response()->json([
            'id'   => $client->id,
            'name' => $client->name,
        ]);
    }

    public function show(Client $client): View
    {
        $this->authorizeClientAccess($client);
        $client->load('cases.lawyer');

        return view('clients.show', compact('client'));
    }

    public function edit(Client $client): View
    {
        $this->authorizeClientAccess($client);
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeClientAccess($client);
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'type'          => 'required|in:individual,company',
            'phone'         => 'nullable|string|max:255',
            'email'         => 'nullable|email|max:255',
            'address'       => 'nullable|string',
            'national_id'   => 'nullable|string|max:255',
            'company_name'  => 'nullable|string|max:255',
            'user_id'       => 'nullable|exists:users,id',
        ]);

        $oldValues = $client->toArray();
        $client->update($validated);

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            Client::class,
            $client->id,
            $oldValues,
            $client->toArray()
        );

        return redirect()->route('clients.show', $client)
            ->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorizeClientAccess($client);
        $oldValues = $client->toArray();
        $client->delete();

        $this->logAudit(
            AuditLog::ACTION_DELETE,
            Client::class,
            $client->id,
            $oldValues,
            null
        );

        return redirect()->route('clients.index')
            ->with('success', 'Client deleted successfully.');
    }

    public function trashed(): View
    {
        $query = Client::onlyTrashed()->withCount('cases');

        $clients = $query->latest('deleted_at')->paginate(15);
        return view('clients.trashed', compact('clients'));
    }

    public function restore(int $id): RedirectResponse
    {
        $client = Client::onlyTrashed()->findOrFail($id);

        $client->restore();
        return redirect()->route('clients.index')->with('success', 'تم استرجاع العميل بنجاح');
    }

    private function authorizeClientAccess(Client $client): void
    {
        // All team members can access any client
    }
}
