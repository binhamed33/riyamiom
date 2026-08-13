@props(['title' => '', 'value' => '', 'icon' => '', 'color' => 'gold'])

@php
    $colors = [
        'gold' => [
            'bg' => 'bg-gold/10',
            'icon_bg' => 'bg-gradient-to-br from-gold to-gold-dark',
            'icon_text' => 'text-white',
            'border' => 'border-gold/15',
            'shadow' => 'shadow-gold/20',
            'badge' => 'bg-gold/12 text-gold-dark',
        ],
        'navy' => [
            'bg' => 'bg-gray-100',
            'icon_bg' => 'bg-gradient-to-br from-gold to-gold-dark',
            'icon_text' => 'text-white',
            'border' => 'border-gray-200',
            'shadow' => 'shadow-gray-200/20',
            'badge' => 'bg-gray-100 text-gray-600',
        ],
        'emerald' => [
            'bg' => 'bg-emerald-100',
            'icon_bg' => 'bg-gradient-to-br from-emerald-500 to-emerald-600',
            'icon_text' => 'text-white',
            'border' => 'border-emerald-200',
            'shadow' => 'shadow-emerald-200/20',
            'badge' => 'bg-emerald-100 text-emerald-700',
        ],
        'red' => [
            'bg' => 'bg-red-100',
            'icon_bg' => 'bg-gradient-to-br from-red-500 to-red-600',
            'icon_text' => 'text-white',
            'border' => 'border-red-200',
            'shadow' => 'shadow-red-200/20',
            'badge' => 'bg-red-100 text-red-700',
        ],
        'purple' => [
            'bg' => 'bg-purple-100',
            'icon_bg' => 'bg-gradient-to-br from-purple-500 to-purple-600',
            'icon_text' => 'text-white',
            'border' => 'border-purple-200',
            'shadow' => 'shadow-purple-200/20',
            'badge' => 'bg-purple-100 text-purple-700',
        ],
        'blue' => [
            'bg' => 'bg-blue-100',
            'icon_bg' => 'bg-gradient-to-br from-blue-500 to-blue-600',
            'icon_text' => 'text-white',
            'border' => 'border-blue-200',
            'shadow' => 'shadow-blue-200/20',
            'badge' => 'bg-blue-100 text-blue-700',
        ],
    ];

    $c = $colors[$color] ?? $colors['gold'];
@endphp

<div class="group bg-white rounded-xl border border-gray-200 p-5 hover:shadow-lg {{ $c['shadow'] }} transition-all duration-300 hover:-translate-y-1">
    <div class="flex items-start justify-between">
        <div class="flex-1 min-w-0">
            <p class="text-sm text-gray-500 font-medium mb-1">{{ $title }}</p>
            <p class="text-2xl font-heading font-bold text-gray-900">{{ $value }}</p>
        </div>
        <div class="w-12 h-12 rounded-xl {{ $c['icon_bg'] }} flex items-center justify-center flex-shrink-0 shadow-lg group-hover:scale-110 transition-transform duration-300">
            <span class="{{ $c['icon_text'] }}">{{ $icon }}</span>
        </div>
    </div>

    @if(isset($slot) && $slot->isNotEmpty())
        <div class="mt-3 pt-3 border-t border-gray-200">
            {{ $slot }}
        </div>
    @endif
</div>
