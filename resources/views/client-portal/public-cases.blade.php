@extends('layouts.public')

@section('title', 'قضاياي')

@section('header-actions')
    <form method="POST" action="{{ route('client.access.logout') }}">
        @csrf
        <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm rounded-xl bg-red-50 text-red-600 hover:bg-red-100 transition-colors border border-red-100">
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M3 9l3-3m0 0l3 3m-3-3v12" />
            </svg>
            <span class="hidden sm:inline">تسجيل خروج</span>
        </button>
    </form>
@endsection

@section('content')
<div class="animate-fade-in">
    {{-- Welcome Header --}}
    <div class="bg-gradient-to-l from-gold via-gold-dark to-gold-deep rounded-2xl sm:rounded-3xl shadow-xl shadow-gold/20 p-5 sm:p-8 mb-4 sm:mb-6 relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(255,255,255,0.08),transparent_70%)]"></div>
        <div class="relative">
            <div class="flex items-start sm:items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 sm:gap-3 mb-1 sm:mb-2">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-white/15 flex items-center justify-center backdrop-blur-sm">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </div>
                        <h1 class="text-lg sm:text-2xl font-bold text-white font-heading">مرحباً {{ $match->name }}</h1>
                    </div>
                    <p class="text-gold-light/80 text-xs sm:text-sm mr-10 sm:mr-0">
                        قضاياك المسجلة في المكتب
                        <span class="inline-block mx-2 w-1 h-1 rounded-full bg-gold/50"></span>
                        <span class="font-medium text-gold-light">{{ $cases->count() }} قضية</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 sm:px-5 py-3 rounded-xl text-sm font-medium mb-4 sm:mb-6 flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 sm:px-5 py-3 rounded-xl text-sm font-medium mb-4 sm:mb-6 flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Cases List --}}
    @if($cases->isEmpty())
        <div class="bg-white rounded-2xl sm:rounded-3xl border border-gold/15 p-8 sm:p-16 text-center shadow-sm">
            <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-4 sm:mb-5 rounded-2xl bg-gold/10 flex items-center justify-center">
                <svg class="w-8 h-8 sm:w-10 sm:h-10 text-gold-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25 2.25M12 11.625l2.25-2.25M12 11.625l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                </svg>
            </div>
            <h3 class="text-base sm:text-lg font-bold text-gray-700 font-heading mb-1">لا توجد قضايا مسجلة</h3>
            <p class="text-sm text-gray-400">في حال وجود استفسار، يرجى التواصل مع المكتب</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-3 sm:gap-4">
            @foreach($cases as $case)
                <a href="{{ route('client.access.case', $case) }}"
                   class="group block bg-white rounded-2xl border border-gold/15 p-4 sm:p-5 card-hover shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            {{-- Badges --}}
                            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 mb-2">
                                <span class="text-[10px] sm:text-xs font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-lg">
                                    #{{ $case->office_case_number ?? $case->case_number }}
                                </span>
                                <span class="text-[10px] sm:text-xs px-2 py-0.5 rounded-lg font-medium
                                    {{ $case->status === 'active' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $case->status === 'pending' ? 'bg-gold/12 text-gold-dark' : '' }}
                                    {{ $case->status === 'closed' ? 'bg-gray-100 text-gray-500' : '' }}
                                    {{ $case->status === 'won' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                    {{ $case->status === 'lost' ? 'bg-red-100 text-red-700' : '' }}
                                    {{ $case->status === 'overdue' ? 'bg-orange-100 text-orange-700' : '' }}">
                                    @lang('app.status_' . $case->status)
                                </span>
                                @if($case->case_type)
                                    <span class="text-[10px] sm:text-xs text-gray-400 bg-gray-50 px-2 py-0.5 rounded-lg border border-gray-100">
                                        {{ $case->case_type }}
                                    </span>
                                @endif
                            </div>

                            {{-- Title --}}
                            <h3 class="text-sm sm:text-base font-bold text-gray-800 mb-1.5 font-heading group-hover:text-gold-dark transition-colors">{{ $case->title }}</h3>

                            {{-- Details --}}
                            <div class="flex flex-wrap gap-x-3 sm:gap-x-5 gap-y-1 text-xs sm:text-sm text-gray-500">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                    </svg>
                                    <span>{{ $case->court }}</span>
                                </span>
                                @if($case->lawyer)
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                    <span>{{ $case->lawyer->name }}</span>
                                </span>
                                @endif
                            </div>
                        </div>

                        {{-- Arrow --}}
                        <div class="flex-shrink-0 mt-1">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-300 group-hover:text-gold-dark transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
