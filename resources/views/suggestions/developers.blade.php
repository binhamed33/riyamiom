@extends('layouts.app')

@section('title', 'اقتراحات الموظفين')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-amber-600">اقتراحات الموظفين</h1>
        <p class="text-sm text-gray-500 mt-1">ردّ على الاقتراحات وحدّد حالتها — يصل تنبيه فوري لصاحب الاقتراح.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-center gap-3 mb-6">
        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-green-700 bg-green-100 px-3 py-1.5 rounded-full">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            تم التنفيذ
        </span>
        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-amber-700 bg-amber-100 px-3 py-1.5 rounded-full">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            قيد الدراسة أو التنفيذ
        </span>
    </div>

    @forelse($suggestions as $suggestion)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center flex-shrink-0 text-white text-sm font-bold">
                    {{ mb_substr($suggestion->user->name, 0, 1) }}
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-900">{{ $suggestion->user->name }}</p>
                    <p class="text-[11px] text-gray-400">{{ $suggestion->user->role }} • {{ $suggestion->created_at->diffForHumans() }}</p>
                </div>
            </div>

            <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap bg-gray-50 border border-gray-100 rounded-xl p-3">{{ $suggestion->content }}</p>

            <div class="flex items-center gap-2 mt-3">
                <form method="POST" action="{{ route('suggestions.status', $suggestion) }}" class="inline">
                    @csrf
                    <input type="hidden" name="status" value="implemented">
                    <button type="submit" class="inline-flex items-center gap-1.5 text-[11px] font-bold px-3 py-1.5 rounded-full border transition {{ $suggestion->status === 'implemented' ? 'bg-green-600 text-white border-green-600' : 'bg-white text-green-700 border-green-300 hover:bg-green-50' }}">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        تم التنفيذ
                    </button>
                </form>
                <form method="POST" action="{{ route('suggestions.status', $suggestion) }}" class="inline">
                    @csrf
                    <input type="hidden" name="status" value="pending">
                    <button type="submit" class="inline-flex items-center gap-1.5 text-[11px] font-bold px-3 py-1.5 rounded-full border transition {{ $suggestion->status === 'pending' ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-amber-700 border-amber-300 hover:bg-amber-50' }}">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        قيد الدراسة أو التنفيذ
                    </button>
                </form>
            </div>

            <form method="POST" action="{{ route('suggestions.reply', $suggestion) }}" class="mt-4">
                @csrf
                <textarea name="reply" rows="2" placeholder="اكتب ردّك لصاحب الاقتراح..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-amber-300 focus:bg-amber-50 transition resize-y">{{ old('reply', $suggestion->developer_reply) }}</textarea>
                <div class="flex items-center justify-between mt-2">
                    <p class="text-[11px] text-gray-400">
                        @if($suggestion->replied_at)
                            آخر رد: {{ $suggestion->replied_at->diffForHumans() }}
                        @else
                            لم يُرد بعد
                        @endif
                    </p>
                    <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2 rounded-lg text-xs transition flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                        إرسال الرد
                    </button>
                </div>
            </form>
        </div>
    @empty
        <div class="bg-gray-50 rounded-xl border border-gray-100 p-8 text-center">
            <p class="text-gray-400 text-sm">لا توجد اقتراحات بعد</p>
        </div>
    @endforelse

    <div class="mt-4">
        {{ $suggestions->links() }}
    </div>
</div>
@endsection
