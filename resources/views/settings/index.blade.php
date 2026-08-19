@extends('layouts.app')

@section('title', __('app.page_settings'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold-dark">{{ __('app.settings') }}</h1>
    </div>

    @php
        $subService = app(\App\Services\SubscriptionService::class);
        $isDev = auth()->user()->isDeveloper();
        $subInfo = $isDev ? null : $subService->info();
        $subKey = $subInfo['key'] ?? null;
        $subPct = 0;
        if ($subInfo && $subInfo['start_at'] && $subInfo['end_at']) {
            $totalSecs = max(1, (int) $subInfo['start_at']->diffInSeconds($subInfo['end_at']));
            $elapsedSecs = max(0, min($totalSecs, (int) $subInfo['start_at']->diffInSeconds(now())));
            $subPct = (int) round($elapsedSecs / $totalSecs * 100);
        }
    @endphp

    @if($isDev)
        <a href="{{ route('developer.subscription.config') }}" class="block bg-white rounded-xl border border-gray-200 p-5 hover:border-gold/50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-gold-light to-gold-dark flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div class="flex-1">
                    <h2 class="text-base font-bold text-gray-800">إعدادات الاشتراك</h2>
                    <p class="text-xs text-gray-500 mt-0.5">إدارة اشتراك هذه النسخة من النظام — متاح للمطور فقط</p>
                </div>
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </div>
        </a>
    @elseif($subInfo)
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-gold-light to-gold-dark flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div class="flex-1">
                    <h2 class="text-base font-bold text-gray-800">اشتراك النظام</h2>
                    <p class="text-xs text-gray-500 mt-0.5">يُدار من قبل المطور</p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ \App\Services\SubscriptionService::colorClasses($subInfo['color']) }}">{{ $subInfo['label'] }}</span>
            </div>

            @if($subInfo['start_at'] && $subInfo['end_at'])
                <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                    <div class="bg-gray-50 rounded-lg px-4 py-3">
                        <p class="text-xs text-gray-400 mb-1">تاريخ البدء</p>
                        <p class="font-semibold text-gray-800" dir="ltr">{{ $subInfo['start_at']->format('d/m/Y') }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg px-4 py-3">
                        <p class="text-xs text-gray-400 mb-1">تاريخ الانتهاء</p>
                        <p class="font-semibold text-gray-800" dir="ltr">{{ $subInfo['end_at']->format('d/m/Y') }}</p>
                    </div>
                </div>

                @if(in_array($subKey, ['active', 'expiring_soon']))
                    <div>
                        <div class="flex justify-between text-xs text-gray-500 mb-1.5">
                            <span>مدة الاشتراك</span>
                            <span class="font-semibold {{ $subKey === 'expiring_soon' ? 'text-amber-600' : 'text-emerald-600' }}">
                                {{ $subPct }}% — متبقي {{ $subInfo['remaining_days'] }} يوم
                            </span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full {{ $subKey === 'expiring_soon' ? 'bg-amber-500' : 'bg-emerald-500' }}" style="width: {{ $subPct }}%"></div>
                        </div>
                    </div>
                @endif
            @else
                <div class="bg-gray-50 rounded-lg px-4 py-3 text-sm text-gray-500">
                    لا يوجد اشتراك مفعّل لهذا النظام حاليًا. يرجى التواصل مع المطور لتفعيل الوصول الكامل.
                </div>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
            <h2 class="text-lg font-semibold text-gold-dark border-b border-gray-200 pb-3">{{ __('app.office_info') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="office_name" class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.office_name') }}</label>
                    <input
                        type="text"
                        name="office_name"
                        id="office_name"
                        value="{{ old('office_name', $settings['office_name'] ?? '') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
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
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
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
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
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
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    >
                    @error('office_address')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
            <h2 class="text-lg font-semibold text-gold-dark border-b border-gray-200 pb-3">{{ __('app.notification_settings') }}</h2>
            <div class="space-y-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="email_notifications"
                        value="1"
                        {{ old('email_notifications', $settings['email_notifications'] ?? true) ? 'checked' : '' }}
                        class="w-4 h-4 text-gold-dark bg-white border-gray-300 rounded focus:ring-gold-dark"
                    >
                    <span class="text-gray-700">{{ __('app.email_notifications') }}</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="task_reminders"
                        value="1"
                        {{ old('task_reminders', $settings['task_reminders'] ?? true) ? 'checked' : '' }}
                        class="w-4 h-4 text-gold-dark bg-white border-gray-300 rounded focus:ring-gold-dark"
                    >
                    <span class="text-gray-700">{{ __('app.task_reminders') }}</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="deadline_alerts"
                        value="1"
                        {{ old('deadline_alerts', $settings['deadline_alerts'] ?? true) ? 'checked' : '' }}
                        class="w-4 h-4 text-gold-dark bg-white border-gray-300 rounded focus:ring-gold-dark"
                    >
                    <span class="text-gray-700">{{ __('app.deadline_alerts') }}</span>
                </label>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
            <h2 class="text-lg font-semibold text-gold-dark border-b border-gray-200 pb-3">{{ __('app.system_settings') }}</h2>
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
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
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
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
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
