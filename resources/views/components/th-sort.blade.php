{{--
    ترويسةُ عمودٍ تُرتَّب بالنقر على نصّها.

    ═══ لماذا النصُّ نفسُه ═══

    شريطُ «ترتيب حسب» فوق الجدول يعمل، لكنّ أحداً لا يبحث عنه: من
    أراد ترتيباً ضغط رأسَ العمود — هكذا تعمل كلُّ الجداول التي رآها.
    فالترويسةُ رابطٌ يشمل النصَّ والسهمَ معاً.

    والسهمُ يقول الحال: ممتلئٌ صاعدٌ أو نازلٌ على العمود النشط، وباهتٌ
    مزدوجٌ على غيره — فيُعرف أنّه يُنقر قبل أن يُنقر.
--}}
@props([
    'key',                       // مفتاحُ الترتيب كما في sortMap بالمتحكّم
    'label',
    'sort' => null,              // النشطُ الآن
    'dir' => 'desc',
    'align' => 'right',
    'thClass' => null,           // بديلٌ كاملٌ لأصناف الترويسة حين يخالف الجدولُ المقاسَ العام
])

@php
    $current = $sort ?? request('sort');
    $isActive = $current === $key;
    // النقرُ على النشط يقلب الاتجاه، وعلى غيره يبدأ صاعداً
    $nextDir = ($isActive && $dir === 'asc') ? 'desc' : 'asc';
@endphp

<th {{ $attributes->merge(['class' => $thClass ?: 'px-6 py-3 text-' . $align . ' font-semibold']) }}>
    <a href="{{ request()->fullUrlWithQuery(['sort' => $key, 'dir' => $nextDir, 'page' => null]) }}"
       data-live-link
       class="inline-flex items-center gap-1 whitespace-nowrap transition-colors text-gold-dark {{ $isActive ? 'bg-gold/12 rounded-lg px-2 py-1' : 'opacity-90 hover:opacity-100' }}"
       @if($isActive) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
        {{ $label }}
        @if($isActive)
            <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                @if($dir === 'asc')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                @else
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                @endif
            </svg>
        @else
            <svg class="w-3 h-3 flex-shrink-0 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4M8 15l4 4 4-4"/>
            </svg>
        @endif
    </a>
</th>
