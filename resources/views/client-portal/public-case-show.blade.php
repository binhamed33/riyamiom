@extends('layouts.public')

@section('title', $case->title)

@section('content')
<div class="max-w-5xl mx-auto p-4 md:p-6 animate-fade-in">
    {{-- Header --}}
    <div class="bg-gradient-to-l from-amber-600 to-amber-700 rounded-2xl shadow-xl p-6 md:p-8 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button onclick="history.back()" class="text-white/70 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div>
                    <h1 class="text-xl font-bold text-white font-heading">{{ $case->title }}</h1>
                    <p class="text-amber-200 text-sm">رقم القضية: #{{ $case->office_case_number ?? $case->case_number }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('client.access.logout') }}">
                @csrf
                <button type="submit" class="bg-white/20 text-white px-3 py-1.5 rounded-xl text-sm hover:bg-white/30 transition">
                    تسجيل خروج
                </button>
            </form>
        </div>
    </div>

    {{-- Next Session Highlights --}}
    @if($nextSession)
    <div class="bg-gradient-to-l from-emerald-500 to-emerald-600 rounded-2xl shadow-lg p-6 mb-6">
        <div class="flex items-center gap-3 text-white mb-2">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            </svg>
            <h2 class="text-sm font-bold font-heading uppercase tracking-wider">الجلسة القادمة</h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-white">
            <div>
                <p class="text-emerald-100 text-xs">التاريخ</p>
                <p class="text-white font-bold text-lg">{{ $nextSession->date?->format('Y-m-d') }}</p>
            </div>
            <div>
                <p class="text-emerald-100 text-xs">المكان</p>
                <p class="text-white font-bold">{{ $nextSession->location ?? '—' }}</p>
            </div>
            <div>
                <p class="text-emerald-100 text-xs">الحالة</p>
                <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-lg font-medium bg-white/20 text-white">
                    @lang('app.status_' . $nextSession->status)
                </span>
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Main Info --}}
        <div class="md:col-span-2 space-y-6">
            {{-- Case Details --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="text-sm font-bold text-amber-600 uppercase tracking-wider mb-4 font-heading">تفاصيل القضية</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-400">رقم القضية</p>
                        <p class="text-sm font-medium text-gray-700">{{ $case->case_number }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">رقم المكتب</p>
                        <p class="text-sm font-medium text-gray-700">{{ $case->office_case_number ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">نوع القضية</p>
                        <p class="text-sm font-medium text-gray-700">{{ $case->case_type ?? $case->type }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">المحكمة</p>
                        <p class="text-sm font-medium text-gray-700">{{ $case->court }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">الحالة</p>
                        <span class="text-xs px-2 py-0.5 rounded-lg font-medium inline-block mt-1
                            {{ $case->status === 'active' ? 'bg-green-100 text-green-700' : '' }}
                            {{ $case->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                            {{ $case->status === 'closed' ? 'bg-gray-100 text-gray-500' : '' }}
                            {{ $case->status === 'won' ? 'bg-emerald-100 text-emerald-700' : '' }}
                            {{ $case->status === 'lost' ? 'bg-red-100 text-red-700' : '' }}
                            {{ $case->status === 'overdue' ? 'bg-orange-100 text-orange-700' : '' }}">
                            @lang('app.status_' . $case->status)
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">الأولوية</p>
                        <span class="text-xs px-2 py-0.5 rounded-lg font-medium inline-block mt-1
                            {{ $case->priority === 'urgent' ? 'bg-red-100 text-red-700' : '' }}
                            {{ $case->priority === 'high' ? 'bg-orange-100 text-orange-700' : '' }}
                            {{ $case->priority === 'medium' ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $case->priority === 'low' ? 'bg-gray-100 text-gray-500' : '' }}">
                            @lang('app.priority_' . $case->priority)
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">الخصم</p>
                        <p class="text-sm font-medium text-gray-700">{{ $case->opponent ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">هاتف الخصم</p>
                        <p class="text-sm font-medium text-gray-700">{{ $case->opponent_phone ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">محامي الخصم</p>
                        <p class="text-sm font-medium text-gray-700">{{ $case->opponent_lawyer ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">المحامي المسؤول</p>
                        <p class="text-sm font-medium text-gray-700">{{ $case->lawyer?->name ?? '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Description --}}
            @if($case->description)
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="text-sm font-bold text-amber-600 uppercase tracking-wider mb-3 font-heading">الوصف</h2>
                <p class="text-sm text-gray-600 leading-relaxed">{{ $case->description }}</p>
            </div>
            @endif

            {{-- Past Sessions --}}
            @if($pastSessions->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-amber-600 uppercase tracking-wider font-heading">الجلسات السابقة</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($pastSessions as $session)
                        <div class="px-6 py-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-bold text-gray-700">{{ $session->date?->format('Y-m-d') }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded-lg font-medium
                                        {{ $session->status === 'held' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $session->status === 'postponed' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                        {{ $session->status === 'cancelled' ? 'bg-red-100 text-red-700' : '' }}">
                                        @lang('app.status_' . $session->status)
                                    </span>
                                </div>
                                <span class="text-xs text-gray-400">{{ $session->location ?? '—' }}</span>
                            </div>
                            @if($session->notes)
                                <p class="text-sm text-gray-600 bg-gray-50 rounded-xl p-3 mt-2">{{ $session->notes }}</p>
                            @endif
                            @if($session->report)
                                <div class="mt-2">
                                    <p class="text-xs text-gray-400 mb-1">التقرير:</p>
                                    <p class="text-sm text-gray-600 bg-amber-50 rounded-xl p-3">{{ $session->report }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            @if($case->documents->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-amber-600 uppercase tracking-wider font-heading">المستندات</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($case->documents as $doc)
                        <div class="px-5 py-3">
                            <p class="text-sm font-medium text-gray-700">{{ $doc->title }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs text-gray-400">{{ $doc->type }}</span>
                                <span class="text-gray-300">·</span>
                                <span class="text-xs text-gray-400">{{ $doc->created_at?->format('Y-m-d') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
