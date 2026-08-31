@extends('client-portal.layout')
@section('title', 'الإشعارات')

@push('styles')
<style>
    /* مصمَّمةٌ للهاتف أوّلاً: الموكّل يفتحها من رابطٍ في واتساب،
       فالأزرار كبيرة والنصُّ يُقرأ بلا تكبير. */
    .n-list { display: flex; flex-direction: column; gap: .6rem; }
    .n-item {
        display: flex; gap: .85rem; align-items: flex-start;
        padding: 1rem 1.1rem; min-height: 64px;
        text-align: start;
    }
    .n-mark {
        flex-shrink: 0; width: 9px; height: 9px; border-radius: 999px;
        margin-block-start: .42rem; background: var(--line-2);
    }
    .n-item.is-new .n-mark { background: var(--gold); box-shadow: 0 0 0 3px var(--gold-soft); }
    .n-body { flex: 1; min-width: 0; }
    .n-title { font-weight: 700; font-size: .92rem; line-height: 1.55; margin: 0; }
    .n-sub { font-size: .78rem; color: var(--fg-2); margin: .2rem 0 0; line-height: 1.6; }
    .n-foot {
        display: flex; align-items: center; justify-content: space-between;
        gap: .7rem; margin-block-start: .55rem;
    }
    .n-when { font-size: .68rem; color: var(--fg-3); }
    .n-kind {
        font-size: .64rem; font-weight: 700; color: var(--gold);
        background: var(--gold-soft); border-radius: 6px; padding: .18rem .5rem;
    }
    .n-go { font-size: .78rem; font-weight: 700; color: var(--gold); white-space: nowrap; }
    .n-empty { padding: 3rem 1.2rem; text-align: center; }
    .n-empty svg { width: 42px; height: 42px; color: var(--fg-3); margin-block-end: .8rem; }
</style>
@endpush

@section('content')
<div class="p-in" style="margin-bottom:1.2rem">
    <h1 class="p-h1">الإشعارات</h1>
    <p class="p-lede">ما جدَّ في ملفّاتكم لدى المكتب.</p>
</div>

@if ($items->count())
    <div class="n-list">
        @foreach ($items as $item)
            @php
                // الوجهةُ تُبنى الآن بالنطاق الحالي — لا رابطَ مخزَّن
                // يموت مع تغيّر النطاق (وقد تغيّر فعلاً).
                $href = $item->case_id
                    ? route('client.portal.case', $item->case_id)
                    : route('client.portal.home');
            @endphp
            <a href="{{ $href }}" class="p-card n-item @unless($item->isRead()) is-new @endunless">
                <span class="n-mark" aria-hidden="true"></span>
                <span class="n-body">
                    <span class="n-kind">{{ $item->typeLabel() }}</span>
                    <p class="n-title" style="margin-top:.4rem">{{ $item->title }}</p>
                    @if ($item->body)
                        <p class="n-sub">{{ $item->body }}</p>
                    @endif
                    <span class="n-foot">
                        <span class="n-when">{{ $item->created_at?->diffForHumans() }}</span>
                        <span class="n-go">عرض التفاصيل ←</span>
                    </span>
                </span>
            </a>
        @endforeach
    </div>
@else
    <div class="p-card n-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0m6 0H9"/>
        </svg>
        <p class="n-title">لا إشعارات بعد</p>
        <p class="n-sub">حين يجدّ شيءٌ في قضاياكم سيظهر هنا، ويصلكم تنبيهٌ على واتساب.</p>
    </div>
@endif
@endsection
