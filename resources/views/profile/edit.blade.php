@extends('layouts.app')

@section('title', __('app.page_profile'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold-dark">{{ __('app.profile') }}</h1>
    </div>

    <form method="POST" action="{{ route('profile.update') }}" class="bg-white rounded-xl border border-gray-200 p-8 space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="name" class="block text-sm font-medium text-gold-dark mb-2">{{ __('app.user_full_name') }} *</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name', auth()->user()->name) }}"
                    class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    required
                >
                @error('name')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gold-dark mb-2">{{ __('app.email') }} *</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email', auth()->user()->email) }}"
                    class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    required
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="phone" class="block text-sm font-medium text-gold-dark mb-2">{{ __('app.phone') }}</label>
            <input
                type="text"
                name="phone"
                id="phone"
                value="{{ old('phone', auth()->user()->phone) }}"
                class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                dir="ltr"
            >
            @error('phone')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div class="border-t border-gray-200 pt-6">
            <h2 class="text-lg font-semibold text-gold-dark mb-4">{{ __('app.change_password') }}</h2>
            <div class="bg-white rounded-lg p-4 border border-gray-100 mb-4">
                <p class="text-gray-400 text-sm">{{ __('app.password_leave_empty_text') }}</p>
                <p class="text-gold-dark/60 text-xs mt-1">{{ __('app.password_requirements') }}</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-600 mb-2">{{ __('app.current_password') }}</label>
                    <input
                        type="password"
                        name="current_password"
                        id="current_password"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    >
                    @error('current_password')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-600 mb-2">{{ __('app.new_password') }}</label>
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
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-600 mb-2">{{ __('app.confirm_password') }}</label>
                    <input
                        type="password"
                        name="password_confirmation"
                        id="password_confirmation"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    >
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 pt-4 border-t border-gray-200">
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.save_changes') }}</button>
        </div>
    </form>
</div>
@endsection