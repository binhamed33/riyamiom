@props(['type' => 'info', 'message' => ''])

@php
    $s = [
        'success' => ['bg' => 'rgba(34, 197, 94, 0.08)', 'border' => 'rgba(34, 197, 94, 0.15)', 'text' => 'text-emerald-400', 'icon' => 'text-emerald-400', 'btn' => 'text-emerald-400/50 hover:text-emerald-400'],
        'error' => ['bg' => 'rgba(239, 68, 68, 0.08)', 'border' => 'rgba(239, 68, 68, 0.15)', 'text' => 'text-red-400', 'icon' => 'text-red-400', 'btn' => 'text-red-400/50 hover:text-red-400'],
        'warning' => ['bg' => 'rgba(245, 158, 11, 0.08)', 'border' => 'rgba(245, 158, 11, 0.15)', 'text' => 'text-amber-400', 'icon' => 'text-amber-400', 'btn' => 'text-amber-400/50 hover:text-amber-400'],
        'info' => ['bg' => 'rgba(59, 130, 246, 0.08)', 'border' => 'rgba(59, 130, 246, 0.15)', 'text' => 'text-blue-400', 'icon' => 'text-blue-400', 'btn' => 'text-blue-400/50 hover:text-blue-400'],
    ][$type] ?? ['bg' => 'rgba(59, 130, 246, 0.08)', 'border' => 'rgba(59, 130, 246, 0.15)', 'text' => 'text-blue-400', 'icon' => 'text-blue-400', 'btn' => 'text-blue-400/50 hover:text-blue-400'];
@endphp

<div
    x-data="{ show: true }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 -translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0 -translate-y-2"
    class="mb-6"
>
    <div class="rounded-xl p-4" style="background: {{ $s['bg'] }}; border: 1px solid {{ $s['border'] }}; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0 mt-0.5">
                <svg class="w-5 h-5 {{ $s['icon'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="@if($type === 'success') M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z @elseif($type === 'error') M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z @elseif($type === 'warning') M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z @else M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z @endif"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm {{ $s['text'] }} font-medium">{{ $message }}</p>
                @if(session('print_url'))
                    <button type="button" data-print-url="{{ session('print_url') }}" class="mt-2 inline-flex items-center gap-1.5 text-xs {{ $s['text'] }} bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        طباعة القضية
                    </button>
                    @php session()->forget('print_url') @endphp
                @endif
            </div>
            <button
                @click="show = false"
                class="flex-shrink-0 {{ $s['btn'] }} transition"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
</div>
