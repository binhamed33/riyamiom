@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="max-w-3xl">
    <div class="flex items-center justify-between mb-6 gap-3">
        <h1 class="text-xl font-bold text-gold-dark">{{ $title }}</h1>
        <a href="{{ $backUrl }}" class="text-sm font-semibold text-gray-500 hover:text-gray-800 transition">← رجوع</a>
    </div>

    @if ($revisions->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400">
            لا نسخ سابقة بعد — تُحفظ لقطة تلقائياً قبل كل تعديل.
        </div>
    @else
        <p class="text-xs text-gray-400 mb-4">قبل كل استعادة تُحفظ الحالة الحالية كنسخة جديدة — فالاستعادة نفسها قابلة للتراجع.</p>
        <div class="space-y-3">
            @foreach ($revisions as $rev)
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="px-2.5 py-1 rounded-lg bg-gold/10 text-gold-dark text-xs font-bold">v{{ $rev->version }}</span>
                        <span class="text-sm font-semibold text-gray-700">{{ $rev->payload['name'] ?? '—' }}</span>
                        <span class="text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($rev->created_at)->diffForHumans() }}</span>
                        <div class="ms-auto">
                            <form method="POST" action="{{ $restoreRoute($rev->version) }}"
                                  data-confirm="استعادة النسخة v{{ $rev->version }}؟ الحالة الحالية ستُحفظ كنسخة جديدة قبل الاستعادة.">
                                @csrf
                                <button class="text-xs font-bold text-gold-dark border border-gold/30 rounded-lg px-3 py-1.5 hover:bg-gold/5 transition md-touch">استعادة</button>
                            </form>
                        </div>
                    </div>
                    <details class="mt-3">
                        <summary class="text-xs text-gray-400 cursor-pointer">تفاصيل هذه النسخة</summary>
                        <pre class="mt-2 text-[11px] leading-relaxed bg-gray-50 border border-gray-100 rounded-lg p-3 overflow-x-auto" dir="ltr">{{ json_encode($rev->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
