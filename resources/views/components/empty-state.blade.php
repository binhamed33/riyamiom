@props([
    'title',
    'hint' => null,
    'icon' => 'inbox',
    'actionUrl' => null,
    'actionLabel' => null,
    'filtered' => false,
    'clearUrl' => null,
    'compact' => false,
])

@php
    // حالة «لا نتائج للفلاتر» غير حالة «لا بيانات بعد» — والرسالة تختلف
    $paths = [
        'cases' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
        'clients' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        'sessions' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'tasks' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        'documents' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'search' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
        'inbox' => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
    ];
    $path = $paths[$filtered ? 'search' : $icon] ?? $paths['inbox'];
@endphp

<div class="text-center {{ $compact ? 'py-10 px-5' : 'py-16 px-6' }}">
    <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-gold/10 border border-gold/20 flex items-center justify-center">
        <svg class="w-7 h-7 text-gold-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/>
        </svg>
    </div>

    <p class="text-base font-bold text-gray-800">
        {{ $filtered ? __('app.empty_filtered_title') : $title }}
    </p>

    @if($filtered || $hint)
        <p class="text-sm text-gray-500 mt-1.5 max-w-sm mx-auto leading-relaxed">
            {{ $filtered ? __('app.empty_filtered_hint') : $hint }}
        </p>
    @endif

    <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
        @if($filtered && $clearUrl)
            <a href="{{ $clearUrl }}"
               class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-colors">
                {{ __('app.clear_filters') }}
            </a>
        @endif

        @if($actionUrl && $actionLabel)
            <a href="{{ $actionUrl }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-sm transition-colors
                      {{ $filtered && $clearUrl ? 'border border-gray-300 text-gray-600 hover:border-gold hover:text-gold-dark' : 'bg-primary hover:bg-primary-dark text-white' }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                {{ $actionLabel }}
            </a>
        @endif
    </div>
</div>
