@props(['action', 'count' => 0, 'clearUrl' => null, 'hidden' => []])

<div x-data="{ open: {{ $count > 0 ? 'true' : 'false' }} }" class="bg-white rounded-xl border border-gold/15 overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-4 py-1.5">
        <button type="button" @click="open = !open"
                class="flex-1 flex items-center gap-2.5 py-2 text-sm font-bold text-gold-dark hover:opacity-80 transition"
                :aria-expanded="open ? 'true' : 'false'">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/>
            </svg>
            {{ __('app.filter') }}
            @if($count > 0)
                <span class="min-w-[20px] h-5 px-1.5 inline-flex items-center justify-center rounded-full bg-gold text-gray-900 text-[11px] font-bold">{{ $count }}</span>
            @endif
            <svg class="w-4 h-4 flex-shrink-0 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        @if($count > 0 && $clearUrl)
            <a href="{{ $clearUrl }}" class="text-xs font-medium text-gray-500 hover:text-red-600 transition whitespace-nowrap">
                ✕ {{ __('app.clear_filters') }}
            </a>
        @endif
    </div>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-1"
         class="border-t border-gray-100">
        <form method="GET" action="{{ $action }}" class="p-4">
            @foreach($hidden as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            {{ $slot }}
        </form>
    </div>
</div>
