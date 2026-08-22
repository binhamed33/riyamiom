@props(['url' => null, 'title', 'subtitle' => null, 'badges' => null, 'meta' => null, 'actions' => null])

{{-- بطاقة سجل للهاتف: بديل صف الجدول حتى لا يُسحب الجدول أفقياً --}}
<div class="relative px-4 py-3.5 border-b border-gray-100 last:border-0 active:bg-gray-50 transition-colors">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            @if($url)
                {{-- الرابط يمتدّ على البطاقة كلها: هدف لمس كامل بدل سطر من 22 بكسل --}}
                <a href="{{ $url }}" class="md-stretch block font-bold text-gray-800 hover:text-gold-dark transition leading-snug">{{ $title }}</a>
            @else
                <span class="block font-bold text-gray-800 leading-snug">{{ $title }}</span>
            @endif
            @if($subtitle)
                <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $subtitle }}</p>
            @endif
        </div>
        @if($actions)
            <div class="relative z-10 flex items-center gap-1 shrink-0">{{ $actions }}</div>
        @endif
    </div>

    @if($badges)
        <div class="flex flex-wrap items-center gap-1.5 mt-2.5">{{ $badges }}</div>
    @endif

    @if($meta)
        <dl class="grid grid-cols-2 gap-x-3 gap-y-1.5 mt-3 text-xs">{{ $meta }}</dl>
    @endif
</div>
