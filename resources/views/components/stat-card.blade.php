@props(['title' => '', 'value' => '', 'icon' => '', 'color' => 'gold'])

@php
    $colors = [
        'gold' => [
            'bg' => 'bg-white/5',
            'icon_bg' => 'bg-gradient-to-br from-gold to-gold-dark',
            'icon_text' => 'text-navy',
            'border' => 'border-gold/20',
            'shadow' => 'shadow-gold/10',
            'badge' => 'bg-[#C9A55A]/10 text-[#C9A55A]',
        ],
        'navy' => [
            'bg' => 'bg-white/5',
            'icon_bg' => 'bg-gradient-to-br from-navy to-navy-light',
            'icon_text' => 'text-white',
            'border' => 'border-navy/10',
            'shadow' => 'shadow-navy/10',
            'badge' => 'bg-white/10 text-white/60',
        ],
        'emerald' => [
            'bg' => 'bg-white/5',
            'icon_bg' => 'bg-gradient-to-br from-emerald-500 to-emerald-600',
            'icon_text' => 'text-white',
            'border' => 'border-emerald-500/20',
            'shadow' => 'shadow-emerald-500/10',
            'badge' => 'bg-emerald-500/15 text-emerald-400',
        ],
        'red' => [
            'bg' => 'bg-white/5',
            'icon_bg' => 'bg-gradient-to-br from-red-500 to-red-600',
            'icon_text' => 'text-white',
            'border' => 'border-red-500/20',
            'shadow' => 'shadow-red-500/10',
            'badge' => 'bg-red-500/15 text-red-400',
        ],
        'purple' => [
            'bg' => 'bg-white/5',
            'icon_bg' => 'bg-gradient-to-br from-purple-500 to-purple-600',
            'icon_text' => 'text-white',
            'border' => 'border-purple-500/20',
            'shadow' => 'shadow-purple-500/10',
            'badge' => 'bg-purple-500/15 text-purple-400',
        ],
        'blue' => [
            'bg' => 'bg-white/5',
            'icon_bg' => 'bg-gradient-to-br from-blue-500 to-blue-600',
            'icon_text' => 'text-white',
            'border' => 'border-blue-500/20',
            'shadow' => 'shadow-blue-500/10',
            'badge' => 'bg-blue-500/15 text-blue-400',
        ],
    ];

    $c = $colors[$color] ?? $colors['gold'];
@endphp

<div class="group bg-navy rounded-xl border border-white/10 p-5 hover:shadow-lg {{ $c['shadow'] }} transition-all duration-300 hover:-translate-y-0.5">
    <div class="flex items-start justify-between">
        <div class="flex-1 min-w-0">
            <p class="text-sm text-white/50 font-medium mb-1">{{ $title }}</p>
            <p class="text-2xl font-heading font-bold text-white">{{ $value }}</p>
        </div>
        <div class="w-12 h-12 rounded-xl {{ $c['icon_bg'] }} flex items-center justify-center flex-shrink-0 shadow-lg group-hover:scale-110 transition-transform duration-300">
            <span class="{{ $c['icon_text'] }}">{{ $icon }}</span>
        </div>
    </div>

    @if(isset($slot) && $slot->isNotEmpty())
        <div class="mt-3 pt-3 border-t border-white/10">
            {{ $slot }}
        </div>
    @endif
</div>
