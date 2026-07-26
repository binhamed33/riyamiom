@extends('layouts.app')

@section('title', __('app.page_add_user'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold">{{ __('app.page_add_user') }}</h1>
        <a href="{{ route('users.index') }}" class="text-ivory/50 hover:text-ivory transition">{{ __('app.back_to_list') }}</a>
    </div>

    <form method="POST" action="{{ route('users.store') }}" class="bg-navy-light rounded-xl border border-ivory/10 p-8 space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="name" class="block text-sm font-medium text-gold mb-2">{{ __('app.user_full_name') }} <span class="text-red-400">*</span></label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name') }}"
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                    required
                >
                @error('name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gold mb-2">{{ __('app.user_email') }} <span class="text-red-400">*</span></label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email') }}"
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                    required
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="password" class="block text-sm font-medium text-gold mb-2">{{ __('app.password') }} <span class="text-red-400">*</span></label>
                <input
                    type="password"
                    name="password"
                    id="password"
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                    required
                >
                @error('password')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gold mb-2">{{ __('app.confirm_password') }} <span class="text-red-400">*</span></label>
                <input
                    type="password"
                    name="password_confirmation"
                    id="password_confirmation"
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                    required
                >
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="role" class="block text-sm font-medium text-gold mb-2">{{ __('app.user_role') }} <span class="text-red-400">*</span></label>
                <select
                    name="role"
                    id="role"
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                    required
                >
                    <option value="">{{ __('app.choose_role') }}</option>
                    <option value="developer" {{ old('role') === 'developer' ? 'selected' : '' }}>{{ __('app.developer') }}</option>
                    <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>{{ __('app.admin') }}</option>
                    <option value="lawyer" {{ old('role') === 'lawyer' ? 'selected' : '' }}>{{ __('app.lawyer') }}</option>
                    <option value="staff" {{ old('role') === 'staff' ? 'selected' : '' }}>{{ __('app.staff') }}</option>
                    <option value="client" {{ old('role') === 'client' ? 'selected' : '' }}>{{ __('app.client_role') }}</option>
                </select>
                @error('role')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium text-gold mb-2">{{ __('app.user_phone') }}</label>
                <input
                    type="text"
                    name="phone"
                    id="phone"
                    value="{{ old('phone') }}"
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                    dir="ltr"
                >
                @error('phone')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label class="flex items-center gap-3 cursor-pointer">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    {{ old('is_active', 1) ? 'checked' : '' }}
                    class="w-4 h-4 text-gold bg-navy border-ivory/30 rounded focus:ring-gold"
                >
                <span class="text-ivory">{{ __('app.active_account') }}</span>
            </label>
            @error('is_active')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        @include('users._permissions')

        <div class="flex items-center gap-4 pt-4 border-t border-ivory/10">
            <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.add_user') }}</button>
            <a href="{{ route('users.index') }}" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">{{ __('app.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
