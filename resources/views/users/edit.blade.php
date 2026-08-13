@extends('layouts.app')

@section('title', __('app.page_edit_user'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold-dark">{{ __('app.page_edit_user') }}</h1>
        <a href="{{ route('users.index') }}" class="text-gray-500 hover:text-gray-700 transition">{{ __('app.back_to_list') }}</a>
    </div>

    <form method="POST" action="{{ route('users.update', $user) }}" class="bg-white rounded-xl border border-gray-200 p-8 space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="name" class="block text-sm font-medium text-gold-dark mb-2">{{ __('app.user_full_name') }} <span class="text-red-700">*</span></label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name', $user->name) }}"
                    class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    required
                >
                @error('name')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gold-dark mb-2">{{ __('app.user_email') }} <span class="text-red-700">*</span></label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email', $user->email) }}"
                    class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    required
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="bg-white rounded-lg p-4 border border-gray-100">
            <p class="text-gray-400 text-sm mb-3">{{ __('app.password_leave_empty') }}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="password" class="block text-sm font-medium text-gold-dark mb-2">{{ __('app.new_password') }}</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    >
                    @error('password')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gold-dark mb-2">{{ __('app.confirm_password') }}</label>
                    <input
                        type="password"
                        name="password_confirmation"
                        id="password_confirmation"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    >
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="role" class="block text-sm font-medium text-gold-dark mb-2">{{ __('app.user_role') }} <span class="text-red-700">*</span></label>
                <select
                    name="role"
                    id="role"
                    class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    required
                >
                    <option value="developer" {{ old('role', $user->role) === 'developer' ? 'selected' : '' }}>{{ __('app.developer') }}</option>
                    <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>{{ __('app.admin') }}</option>
                    <option value="lawyer" {{ old('role', $user->role) === 'lawyer' ? 'selected' : '' }}>{{ __('app.lawyer') }}</option>
                    <option value="staff" {{ old('role', $user->role) === 'staff' ? 'selected' : '' }}>{{ __('app.staff') }}</option>
                    <option value="client" {{ old('role', $user->role) === 'client' ? 'selected' : '' }}>{{ __('app.client_role') }}</option>
                </select>
                @error('role')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium text-gold-dark mb-2">{{ __('app.user_phone') }}</label>
                <input
                    type="text"
                    name="phone"
                    id="phone"
                    value="{{ old('phone', $user->phone) }}"
                    class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    dir="ltr"
                >
                @error('phone')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label class="flex items-center gap-3 cursor-pointer">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    {{ old('is_active', $user->is_active) ? 'checked' : '' }}
                    class="w-4 h-4 text-gold-dark bg-white border-gray-300 rounded focus:ring-gold-dark"
                >
                <span class="text-gray-700">{{ __('app.active_account') }}</span>
            </label>
            @error('is_active')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        @include('users._permissions')

        <div class="flex items-center gap-4 pt-4 border-t border-gray-200">
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.save_changes') }}</button>
            <a href="{{ route('users.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">{{ __('app.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
