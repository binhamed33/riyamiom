@props([
    // key => label — والمفاتيح تُطابق sortMap في المتحكم حصراً
    'options' => [],
    'default' => 'created',
    'defaultDir' => 'desc',
])

@php
    $sort = request('sort', $default);
    $dir = request('dir', $defaultDir);
@endphp

<div class="flex items-center gap-1.5 flex-wrap" role="group" aria-label="{{ __('app.sort_by') }}">
    <span class="text-[11px] font-bold text-gray-400 whitespace-nowrap">{{ __('app.sort_by') }}:</span>
    @foreach($options as $key => $label)
        @php
            $active = $sort === $key;
            // الضغط على النشط يقلب الاتجاه؛ وعلى غيره يبدأ نازلاً
            $nextDir = $active ? ($dir === 'desc' ? 'asc' : 'desc') : 'desc';
        @endphp
        <a href="{{ request()->fullUrlWithQuery(['sort' => $key, 'dir' => $nextDir, 'page' => null]) }}"
           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold border transition {{ $active ? 'bg-gold/12 text-gold-dark border-gold/25' : 'bg-white text-gray-400 border-gray-200 hover:text-gray-600 hover:border-gray-300' }}"
           @if($active) aria-pressed="true" @endif>
            {{ $label }}
            @if($active)
                <svg class="w-3 h-3 transition-transform {{ $dir === 'asc' ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            @endif
        </a>
    @endforeach
</div>
