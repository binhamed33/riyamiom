@extends('layouts.app')

@section('title', 'صندوق الاقتراحات')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gold-dark">صندوق الاقتراحات</h1>
        <p class="text-sm text-gray-500 mt-1">شاركنا بفكرتك أو ملاحظتك — تصل مباشرة إلى المطوّرين ويتم مراجعتها.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-8">
        <form method="POST" action="{{ route('suggestions.store') }}" x-data="{ len: 0, content: '' }">
            @csrf
            <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">عنوان مختصر <span class="text-gray-400 font-normal">(اختياري)</span></label>
            <input type="text" id="title" name="title" maxlength="160" value="{{ old('title') }}"
                   class="w-full mb-4 bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-gold/25 focus:bg-gold/10 transition @error('title') border-red-300 @enderror"
                   placeholder="مثال: تنبيه قبل موعد الجلسة بيوم">
            @error('title')<p class="text-xs text-red-600 font-medium -mt-3 mb-3">{{ $message }}</p>@enderror

            <label for="content" class="block text-sm font-semibold text-gray-700 mb-2">اقتراحك <span class="text-red-500">*</span></label>
            <textarea
                id="content" name="content" rows="5" required minlength="20" maxlength="2000"
                x-model="content"
                @input="len = content.length"
                class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-gold/25 focus:bg-gold/10 transition resize-y @error('content') border-red-300 @enderror"
                placeholder="صف اقتراحك بوصف واضح... ماذا تقترح ولماذا؟"></textarea>
            <div class="flex items-center justify-between mt-2">
                <div>
                    @error('content')
                        <p class="text-xs text-red-600 font-medium">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-gray-400" x-show="len > 0 && len < 20">يجب وصف الاقتراح بوصف جيد — 20 حرفاً على الأقل</p>
                </div>
                <p class="text-xs text-gray-400" x-text="'عدد الأحرف: ' + len"></p>
            </div>
            <button type="submit" class="mt-4 bg-gradient-to-l from-gold-dark to-gold hover:from-gold-light hover:to-gold text-white font-bold px-6 py-2.5 rounded-xl transition-all shadow-lg shadow-gold/30 hover:shadow-gold-light/30 text-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                إرسال الاقتراح
            </button>

            <p class="mt-3 text-[11px] text-gray-400 leading-relaxed">
                يُرسَل مع اقتراحك: اسمك ودورك وبريدك واسم مكتبك والصفحة التي أرسلت منها ونوع جهازك —
                لمساعدة المطوّرين على فهم السياق. لا تُرسَل أي بيانات قضايا أو موكّلين.
            </p>
        </form>
    </div>

    <div>
        <h2 class="text-base font-bold text-gray-700 mb-3">اقتراحاتي السابقة</h2>
        @forelse($suggestions as $suggestion)
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-3">
                <div class="flex items-center gap-2 mb-2">
                    {{-- الحالة كما قرّرها فريق التطوير، لا كما تُخمَّن محلياً --}}
                    @php $state = $suggestion->statusDisplay(); @endphp
                    @if($state['tone'] === 'done')
                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-green-700 bg-green-100 px-2 py-0.5 rounded-full">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            {{ $state['label'] }}
                        </span>
                    @elseif($state['tone'] === 'planned')
                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-700 bg-blue-100 px-2 py-0.5 rounded-full">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg>
                            {{ $state['label'] }}
                        </span>
                    @elseif($state['tone'] === 'declined')
                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-gray-600 bg-gray-100 px-2 py-0.5 rounded-full">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            {{ $state['label'] }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-gold-dark bg-gold/12 px-2 py-0.5 rounded-full">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $state['label'] }}
                        </span>
                    @endif

                    {{-- حالة الوصول: الموظف يستحقّ أن يعرف أن اقتراحه لم يضع --}}
                    @if ($suggestion->delivery_state === 'sent')
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-gray-500" title="وصل فريق التطوير">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            وصل فريق التطوير
                        </span>
                    @elseif (in_array($suggestion->delivery_state, ['pending', 'failed'], true))
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-gray-400" title="محفوظ وسيُرسَل تلقائياً">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            محفوظ — قيد الإرسال
                        </span>
                    @endif
                </div>

                @if ($suggestion->title)
                    <p class="text-sm font-bold text-gray-900 mb-1">{{ $suggestion->title }}</p>
                @endif
                <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap">{{ $suggestion->content }}</p>
                @if($suggestion->developer_reply)
                    <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-[11px] font-bold text-green-700">ردّ المطوّر:</p>
                        <p class="text-sm text-gray-800 whitespace-pre-wrap mt-1">{{ $suggestion->developer_reply }}</p>
                        <p class="text-[11px] text-gray-400 mt-1">{{ $suggestion->replied_at?->diffForHumans() }}</p>
                    </div>
                @endif
                <div class="flex items-center justify-between gap-3 mt-2">
                    <p class="text-[11px] text-gray-400">{{ $suggestion->created_at->diffForHumans() }}</p>

                    {{-- الحذف لصاحبه ومدير المكتب — والشرط نفسه مفروض في الخادم --}}
                    @if ($suggestion->deletableBy(auth()->user()))
                        <form method="POST" action="{{ route('suggestions.destroy', $suggestion) }}"
                              data-confirm="{{ __('app.suggestion_delete_confirm') }}">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="text-[11px] font-semibold text-gray-400 hover:text-red-600 transition px-2 py-1 -m-1">
                                {{ __('app.suggestion_delete') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-gray-50 rounded-xl border border-gray-100 p-8 text-center">
                <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                <p class="text-gray-400 text-sm">لم ترسل أي اقتراح بعد — كن أول من يشارك</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
