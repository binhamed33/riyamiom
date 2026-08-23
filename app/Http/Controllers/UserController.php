<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Traits\AuditLoggable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Rules\StrongPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    use AuditLoggable;
    public function index(Request $request): View
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        // حدّ الباقة يُفرَض هنا في الخادم — إخفاء زرّ ليس منعاً.
        // ولا يُحذف شيء عند التجاوز: المنع على الإضافة وحدها.
        if (\App\Support\PlanLimits::reached('users')) {
            return redirect()->back()->withInput()
                ->with('limit_reached', 'users')
                ->withErrors(['limit' => \App\Support\PlanLimits::message('users')]);
        }

        $validated = $request->validate([
            'name'       => \App\Support\PersonName::rule(),
            'email'      => 'required|email|unique:users,email',
            'password'   => ['required', 'string', 'confirmed', \App\Support\PasswordPolicy::rules()],
            'role'       => 'required|in:developer,admin,lawyer,staff,client',
            'phone'      => \App\Support\GulfPhone::rule(),
            'is_active'  => 'boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ], \App\Support\PasswordPolicy::messages());

        if ($validated['role'] === 'developer' && !auth()->user()->isDeveloper()) {
            return redirect()->back()->withInput()->withErrors([
                'role' => 'فقط المطور يمكنه إضافة مستخدم بدور مطور.',
            ]);
        }

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $request->boolean('is_active', true);

        try {
            $user = \App\Support\PlanLimits::guard('users', fn () => User::create($validated));
        } catch (\App\Support\LimitReached $e) {
            return redirect()->back()->withInput()
                ->with('limit_reached', $e->resource)
                ->withErrors(['limit' => \App\Support\PlanLimits::message($e->resource)]);
        }

        if ($request->has('_permissions')) {
            $user->syncPermissions($request->permissions ?? []);
        }

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            User::class,
            $user->id,
            null,
            $user->toArray()
        );

        return redirect()->route('users.show', $user)
            ->with('success', 'User created successfully.');
    }

    public function show(User $user): View
    {
        $user->loadCount(['cases', 'tasks', 'documents']);

        return view('users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->isDeveloper() && !auth()->user()->isDeveloper()) {
            return redirect()->back()->withErrors([
                'error' => 'لا يمكنك تعديل مطور إلا إذا كنت مطورًا.',
            ]);
        }

        $validated = $request->validate([
            'name'       => \App\Support\PersonName::rule(),
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'password'   => ['nullable', 'string', 'confirmed', \App\Support\PasswordPolicy::rules()],
            'role'       => 'required|in:developer,admin,lawyer,staff,client',
            'phone'      => \App\Support\GulfPhone::rule(),
            'is_active'  => 'boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ], \App\Support\PasswordPolicy::messages());

        if ($validated['role'] === 'developer' && !auth()->user()->isDeveloper()) {
            return redirect()->back()->withInput()->withErrors([
                'role' => 'فقط المطور يمكنه منح دور مطور.',
            ]);
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        $oldValues = $user->toArray();
        $user->update($validated);

        if ($request->has('_permissions')) {
            $user->syncPermissions($request->permissions ?? []);
        }

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            User::class,
            $user->id,
            $oldValues,
            $user->toArray()
        );

        return redirect()->route('users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->isDeveloper() && !auth()->user()->isDeveloper()) {
            return redirect()->route('users.index')
                ->withErrors(['error' => 'لا يمكنك حذف مطور إلا إذا كنت مطورًا.']);
        }

        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->withErrors(['error' => 'You cannot delete your own account.']);
        }

        if ($user->isAdmin()) {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return redirect()->route('users.index')
                    ->withErrors(['error' => 'Cannot delete the last admin user.']);
            }
        }

        if ($user->isDeveloper()) {
            $devCount = User::where('role', 'developer')->count();
            if ($devCount <= 1) {
                return redirect()->route('users.index')
                    ->withErrors(['error' => 'Cannot delete the last developer.']);
            }
        }

        $oldValues = $user->toArray();
        $user->delete();

        $this->logAudit(
            AuditLog::ACTION_DELETE,
            User::class,
            $user->id,
            $oldValues,
            null
        );

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }
}
