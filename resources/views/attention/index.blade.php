@extends('layouts.app')

@section('title', app()->getLocale() === 'ar' ? 'مركز الانتباه' : 'Attention Center')

@section('breadcrumb')
    <div class="flex items-center gap-2 text-sm">
        <a href="{{ route('dashboard') }}" class="text-gray-400 hover:text-amber-700 transition">{{ __('app.calendar') }}</a>
        <span class="text-gray-300">/</span>
        <span class="font-medium text-gray-700">{{ app()->getLocale() === 'ar' ? 'مركز الانتباه' : 'Attention Center' }}</span>
    </div>
@endsection

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-heading font-bold text-gray-900">{{ app()->getLocale() === 'ar' ? 'مركز الانتباه' : 'Attention Center' }}</h1>
            <p class="text-sm text-gray-400 mt-1">{{ app()->getLocale() === 'ar' ? 'تنبيهات ذكية تحتاج إجراء منك - يتم تحديثها تلقائياً' : 'Smart alerts requiring your action - refreshed automatically' }}</p>
        </div>
        <button type="button" x-on:click="window.location.reload()" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-amber-200 bg-white text-sm font-medium text-amber-700 hover:bg-amber-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            {{ app()->getLocale() === 'ar' ? 'تحديث' : 'Refresh' }}
        </button>
    </div>

    @php
        $severityStyles = [
            'critical' => ['border' => 'border-red-200', 'bg' => 'bg-red-50', 'dot' => 'bg-red-500', 'text' => 'text-red-700', 'label' => 'عاجل'],
            'warning'  => ['border' => 'border-orange-200', 'bg' => 'bg-orange-50', 'dot' => 'bg-orange-500', 'text' => 'text-orange-700', 'label' => 'تنبيه'],
            'info'     => ['border' => 'border-amber-200', 'bg' => 'bg-amber-50', 'dot' => 'bg-amber-500', 'text' => 'text-amber-700', 'label' => 'معلومة'],
        ];
        $iconPaths = [
            'gavel' => 'M9.253 6.242 3.75 11.745a3 3 0 0 0 0 4.238l3.757 3.758a3 3 0 0 0 4.238 0l6.5-6.5A1.5 1.5 0 0 0 18.72 12l-3.385-1.544a6 6 0 0 0-4.04-.214l-4.042 1.98Zm6.197-1.47 3.75-3.75a.75.75 0 0 1 1.061 0l1.875 1.875a.75.75 0 0 1 0 1.061l-3.75 3.75a.75.75 0 0 1-1.061 0l-1.875-1.875a.75.75 0 0 1 0-1.061Z',
            'task' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'case' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
            'invoice' => 'M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'client' => 'M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z',
        ];
    @endphp

    {{-- Items --}}
    @forelse($items as $item)
        @php $s = $severityStyles[$item['severity']] ?? $severityStyles['info']; @endphp
        <div class="bg-white rounded-xl border {{ $s['border'] }} p-5 hover:shadow-md transition-shadow flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="w-11 h-11 rounded-xl {{ $s['bg'] }} flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 {{ $s['text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPaths[$item['icon']] ?? $iconPaths['task'] }}"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="w-2 h-2 rounded-full {{ $s['dot'] }}"></span>
                    <h3 class="font-heading font-bold text-gray-900 {{ $s['text'] }} text-sm">{{ $item['title'] }}</h3>
                    <span class="text-[10px] px-2 py-0.5 rounded-full {{ $s['bg'] }} {{ $s['text'] }} font-bold">{{ $s['label'] }}</span>
                </div>
                <p class="text-sm text-gray-500 mt-1">{{ $item['description'] }}</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                @if(!empty($item['action']))
                    <a href="{{ $item['action']['url'] }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-500 text-white text-sm font-bold hover:bg-amber-600 transition shadow-sm">
                        {{ $item['action']['label'] }}
                    </a>
                @endif
                @if(!empty($item['url']))
                    <a href="{{ $item['url'] }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 text-gray-500 text-sm font-medium hover:bg-gray-50 transition">
                        {{ app()->getLocale() === 'ar' ? 'فتح' : 'Open' }}
                    </a>
                @endif
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-100 p-12 text-center">
            <div class="w-16 h-16 mx-auto rounded-full bg-green-50 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>
            <h3 class="font-heading font-bold text-gray-800 text-lg">لا شيء يحتاج انتباهك اليوم</h3>
            <p class="text-sm text-gray-400 mt-1">ستظهر هنا التنبيهات الذكية تلقائياً عند ظهورها</p>
        </div>
    @endforelse

</div>
@endsection