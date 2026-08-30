@props([
    'title',
    'value',
    'accent' => 'gold',
    // نسبة التغير الشهري — null تعني «لا شهر سابق يُقارن به» فلا تُعرض شارة
    'delta' => null,
    'sub' => null,
])

@php
    // الرقم البطل بحبر النص لا بلون السلسلة — واللون هوية للأيقونة والحافة
    $tones = [
        'gold' => ['border-gold/15 hover:border-gold/25', 'bg-gold/12 text-gold-dark'],
        'green' => ['border-green-200 hover:border-green-300', 'bg-green-100 text-green-700'],
        'blue' => ['border-blue-200 hover:border-blue-300', 'bg-blue-100 text-blue-700'],
        'purple' => ['border-purple-200 hover:border-purple-300', 'bg-purple-100 text-purple-700'],
        'red' => ['border-red-200 hover:border-red-300', 'bg-red-100 text-red-700'],
    ][$accent] ?? ['border-gold/15', 'bg-gold/12 text-gold-dark'];
@endphp

<div class="bg-white rounded-xl border {{ $tones[0] }} p-4 transition-colors flex flex-col gap-2">
    <div class="flex items-center justify-between gap-2">
        <p class="text-gray-400 text-[11px] font-bold">{{ $title }}</p>
        <div class="w-8 h-8 rounded-lg {{ $tones[1] }} flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">{{ $slot }}</svg>
        </div>
    </div>

    <div class="flex items-baseline gap-2 flex-wrap">
        <p class="text-2xl font-bold text-gray-900 leading-none" style="font-variant-numeric: tabular-nums">{{ $value }}</p>
        @if($delta !== null)
            <span class="inline-flex items-center gap-0.5 text-[11px] font-bold px-1.5 py-0.5 rounded-full {{ $delta >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}" dir="ltr">
                @if($delta >= 0)
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>+{{ $delta }}%
                @else
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>{{ $delta }}%
                @endif
            </span>
        @endif
    </div>

    @if($sub)
        <p class="text-[11px] text-gray-400 leading-snug">{{ $sub }}</p>
    @endif
</div>
