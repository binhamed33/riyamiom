<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = auth()->user();

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'email'             => 'required|email|unique:users,email,' . $user->id,
            'phone'             => 'nullable|string|max:255',
            'current_password'  => 'required_with:password|current_password',
            'password'          => ['nullable', 'string', 'confirmed', \App\Support\PasswordPolicy::rules()],
        ], \App\Support\PasswordPolicy::messages());

        $oldValues = $user->toArray();

        $data = [
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => AuditLog::ACTION_UPDATE,
            'model_type' => User::class,
            'model_id'   => $user->id,
            'old_values' => $oldValues,
            'new_values' => $user->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('profile.edit')
            ->with('success', 'Profile updated successfully.');
    }
}
