@extends('layouts.app')

@section('title', __('app.page_settings'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-amber-600">{{ __('app.settings') }}</h1>
    </div>

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
            <h2 class="text-lg font-semibold text-amber-600 border-b border-gray-200 pb-3">{{ __('app.office_info') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="office_name" class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.office_name') }}</label>
                    <input
                        type="text"
                        name="office_name"
                        id="office_name"
                        value="{{ old('office_name', $settings['office_name'] ?? '') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                    >
                    @error('office_name')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="office_email" class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.office_email') }}</label>
                    <input
                        type="email"
                        name="office_email"
                        id="office_email"
                        value="{{ old('office_email', $settings['office_email'] ?? '') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                    >
                    @error('office_email')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="office_phone" class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.office_phone') }}</label>
                    <input
                        type="text"
                        name="office_phone"
                        id="office_phone"
                        value="{{ old('office_phone', $settings['office_phone'] ?? '') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                        dir="ltr"
                    >
                    @error('office_phone')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="office_address" class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.office_address') }}</label>
                    <input
                        type="text"
                        name="office_address"
                        id="office_address"
                        value="{{ old('office_address', $settings['office_address'] ?? '') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                    >
                    @error('office_address')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
            <h2 class="text-lg font-semibold text-amber-600 border-b border-gray-200 pb-3">{{ __('app.notification_settings') }}</h2>
            <div class="space-y-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="email_notifications"
                        value="1"
                        {{ old('email_notifications', $settings['email_notifications'] ?? true) ? 'checked' : '' }}
                        class="w-4 h-4 text-amber-600 bg-white border-gray-300 rounded focus:ring-amber-500"
                    >
                    <span class="text-gray-700">{{ __('app.email_notifications') }}</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="task_reminders"
                        value="1"
                        {{ old('task_reminders', $settings['task_reminders'] ?? true) ? 'checked' : '' }}
                        class="w-4 h-4 text-amber-600 bg-white border-gray-300 rounded focus:ring-amber-500"
                    >
                    <span class="text-gray-700">{{ __('app.task_reminders') }}</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="deadline_alerts"
                        value="1"
                        {{ old('deadline_alerts', $settings['deadline_alerts'] ?? true) ? 'checked' : '' }}
                        class="w-4 h-4 text-amber-600 bg-white border-gray-300 rounded focus:ring-amber-500"
                    >
                    <span class="text-gray-700">{{ __('app.deadline_alerts') }}</span>
                </label>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
            <h2 class="text-lg font-semibold text-amber-600 border-b border-gray-200 pb-3">{{ __('app.system_settings') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.timezone') }}</label>
                    <input type="text" value="{{ __('app.oman_muscat') }}" disabled
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-500 rounded-lg px-4 py-2.5 cursor-not-allowed">
                    <p class="mt-1 text-xs text-gray-400">{{ __('app.oman_only') }}</p>
                </div>
                <div>
                    <label for="date_format" class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.date_format') }}</label>
                    <select
                        name="date_format"
                        id="date_format"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                    >
                        <option value="Y-m-d" {{ old('date_format', $settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : '' }}>2026-07-14</option>
                        <option value="d/m/Y" {{ old('date_format', $settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' }}>14/07/2026</option>
                        <option value="d-m-Y" {{ old('date_format', $settings['date_format'] ?? '') === 'd-m-Y' ? 'selected' : '' }}>14-07-2026</option>
                    </select>
                    @error('date_format')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="items_per_page" class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.items_per_page') }}</label>
                    <input
                        type="number"
                        name="items_per_page"
                        id="items_per_page"
                        value="{{ old('items_per_page', $settings['items_per_page'] ?? 15) }}"
                        min="5"
                        max="100"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                    >
                    @error('items_per_page')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.save_settings') }}</button>
        </div>
    </form>
</div>
@endsection
