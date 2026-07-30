@extends('layouts.public')

@section('title', 'قضاياي')

@section('content')
<div class="max-w-5xl mx-auto p-4 md:p-6 animate-fade-in">
    <div class="bg-gradient-to-l from-amber-600 to-amber-700 rounded-2xl shadow-xl p-6 md:p-8 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white font-heading">مرحباً {{ $match->name }}</h1>
                <p class="text-amber-200 text-sm mt-1">قضاياك المسجلة في المكتب</p>
            </div>
            <form method="POST" action="{{ route('client.access.logout') }}">
                @csrf
                <button type="submit" class="bg-white/20 text-white px-4 py-2 rounded-xl text-sm hover:bg-white/30 transition">
                    تسجيل خروج
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-200 text-green-700 px-5 py-3 rounded-xl text-sm font-medium mb-6">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-200 text-red-700 px-5 py-3 rounded-xl text-sm font-medium mb-6">
            {{ session('error') }}
        </div>
    @endif

    @if($cases->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25 2.25M12 11.625l2.25-2.25M12 11.625l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
            </svg>
            <p class="text-gray-500 font-medium">لا توجد قضايا مسجلة باسمك</p>
            <p class="text-gray-400 text-sm mt-1">في حال وجود استفسار، يرجى التواصل مع المكتب</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($cases as $case)
                <a href="{{ route('client.access.case', $case) }}"
                   class="block bg-white rounded-2xl border border-gray-200 p-5 hover:border-amber-300 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-lg">
                                    #{{ $case->office_case_number ?? $case->case_number }}
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-lg font-medium
                                    {{ $case->status === 'active' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $case->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    {{ $case->status === 'closed' ? 'bg-gray-100 text-gray-500' : '' }}
                                    {{ $case->status === 'won' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                    {{ $case->status === 'lost' ? 'bg-red-100 text-red-700' : '' }}
                                    {{ $case->status === 'overdue' ? 'bg-orange-100 text-orange-700' : '' }}">
                                    @lang('app.status_' . $case->status)
                                </span>
                            </div>
                            <h3 class="text-base font-bold text-gray-800 mb-1 font-heading">{{ $case->title }}</h3>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500">
                                <span>النوع: {{ $case->case_type ?? $case->type }}</span>
                                <span>المحكمة: {{ $case->court }}</span>
                                @if($case->lawyer)
                                    <span>المحامي: {{ $case->lawyer->name }}</span>
                                @endif
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-300 flex-shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
