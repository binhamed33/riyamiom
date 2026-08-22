@extends('layouts.public')

@section('title', $case->title)

@section('header-actions')
    <button type="button" data-history-back class="flex items-center gap-1.5 px-3 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm rounded-xl bg-gold/10 text-gold-dark hover:bg-gold/15 transition-colors border border-gold/15">
        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        <span class="hidden sm:inline">رجوع</span>
    </button>
    <form method="POST" action="{{ route('client.access.logout') }}">
        @csrf
        <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm rounded-xl bg-red-50 text-red-600 hover:bg-red-100 transition-colors border border-red-100">
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M3 9l3-3m0 0l3 3m-3-3v12" />
            </svg>
            <span class="hidden sm:inline">خروج</span>
        </button>
    </form>
@endsection

@section('content')
<div class="animate-fade-in">
    {{-- Case Header --}}
    <div class="bg-gradient-to-l from-gold via-gold-dark to-gold-deep rounded-2xl sm:rounded-3xl shadow-xl shadow-gold/20 p-5 sm:p-8 mb-4 sm:mb-6 relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_30%,rgba(255,255,255,0.08),transparent_70%)]"></div>
        <div class="relative">
            <div class="flex items-center gap-2 sm:gap-3 mb-2">
                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-white/15 flex items-center justify-center backdrop-blur-sm">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-base sm:text-xl font-bold text-white font-heading">{{ $case->title }}</h1>
                    <p class="text-gold-light/80 text-xs sm:text-sm mt-0.5">رقم القضية: #{{ $case->office_case_number ?? $case->case_number }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <span class="text-[10px] sm:text-xs px-2 py-0.5 sm:px-3 sm:py-1 rounded-lg font-medium
                    {{ $case->status === 'active' ? 'bg-green-500/20 text-green-200' : '' }}
                    {{ $case->status === 'pending' ? 'bg-amber-500/20 text-gold-light' : '' }}
                    {{ $case->status === 'closed' ? 'bg-gray-500/20 text-gray-200' : '' }}
                    {{ $case->status === 'won' ? 'bg-emerald-500/20 text-emerald-200' : '' }}
                    {{ $case->status === 'lost' ? 'bg-red-500/20 text-red-200' : '' }}
                    {{ $case->status === 'overdue' ? 'bg-orange-500/20 text-orange-200' : '' }}">
                    @lang('app.status_' . $case->status)
                </span>
                <span class="text-[10px] sm:text-xs text-gold-light/60">{{ $case->case_type ?? $case->type }}</span>
                <span class="text-gold-light/40">·</span>
                <span class="text-[10px] sm:text-xs text-gold-light/60">{{ $case->court }}</span>
            </div>
        </div>
    </div>

    {{-- Next Session Highlight --}}
    @if($nextSession)
    <div class="bg-gradient-to-l from-emerald-500 to-emerald-600 rounded-2xl sm:rounded-3xl shadow-lg shadow-emerald-200/30 p-4 sm:p-6 mb-4 sm:mb-6 relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_10%_50%,rgba(255,255,255,0.1),transparent_70%)]"></div>
        <div class="relative">
            <div class="flex items-center gap-2 text-white mb-3">
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
                <h2 class="text-xs sm:text-sm font-bold font-heading uppercase tracking-wider">الجلسة القادمة</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                <div class="bg-white/10 rounded-xl sm:rounded-2xl p-3 sm:p-4 backdrop-blur-sm">
                    <p class="text-emerald-100/70 text-[10px] sm:text-xs mb-0.5">التاريخ</p>
                    <p class="text-white font-bold text-sm sm:text-lg font-heading">{{ $nextSession->date?->format('Y-m-d') }}</p>
                </div>
                <div class="bg-white/10 rounded-xl sm:rounded-2xl p-3 sm:p-4 backdrop-blur-sm">
                    <p class="text-emerald-100/70 text-[10px] sm:text-xs mb-0.5">المكان</p>
                    <p class="text-white font-bold text-sm sm:text-base">{{ $nextSession->location ?? '—' }}</p>
                </div>
                <div class="bg-white/10 rounded-xl sm:rounded-2xl p-3 sm:p-4 backdrop-blur-sm">
                    <p class="text-emerald-100/70 text-[10px] sm:text-xs mb-0.5">الحالة</p>
                    <span class="inline-block mt-0.5 text-xs px-2 sm:px-3 py-0.5 sm:py-1 rounded-lg font-medium bg-white/20 text-white">
                        @lang('app.status_' . $nextSession->status)
                    </span>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Main Content --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        {{-- Left Column --}}
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
            {{-- Case Details Card --}}
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-gold/15 p-5 sm:p-7 shadow-sm">
                <h2 class="text-xs sm:text-sm font-bold text-gold-dark uppercase tracking-wider mb-4 sm:mb-5 font-heading flex items-center gap-2">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                    </svg>
                    تفاصيل القضية
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-2 gap-3 sm:gap-5">
                    <div class="bg-gray-50/80 rounded-xl p-3 sm:p-4">
                        <p class="text-[10px] sm:text-xs text-gray-400 mb-0.5">رقم القضية</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-800">{{ $case->case_number }}</p>
                    </div>
                    <div class="bg-gray-50/80 rounded-xl p-3 sm:p-4">
                        <p class="text-[10px] sm:text-xs text-gray-400 mb-0.5">رقم المكتب</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-800">{{ $case->office_case_number ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50/80 rounded-xl p-3 sm:p-4">
                        <p class="text-[10px] sm:text-xs text-gray-400 mb-0.5">نوع القضية</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-800">{{ $case->case_type ?? $case->type }}</p>
                    </div>
                    <div class="bg-gray-50/80 rounded-xl p-3 sm:p-4">
                        <p class="text-[10px] sm:text-xs text-gray-400 mb-0.5">المحكمة</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-800">{{ $case->court }}</p>
                    </div>
                    <div class="bg-gray-50/80 rounded-xl p-3 sm:p-4">
                        <p class="text-[10px] sm:text-xs text-gray-400 mb-0.5">الأولوية</p>
                        <span class="text-xs px-2 py-0.5 rounded-lg font-medium inline-block mt-0.5
                            {{ $case->priority === 'urgent' ? 'bg-red-100 text-red-700' : '' }}
                            {{ $case->priority === 'high' ? 'bg-orange-100 text-orange-700' : '' }}
                            {{ $case->priority === 'medium' ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $case->priority === 'low' ? 'bg-gray-100 text-gray-500' : '' }}">
                            @lang('app.priority_' . $case->priority)
                        </span>
                    </div>
                    <div class="bg-gray-50/80 rounded-xl p-3 sm:p-4">
                        <p class="text-[10px] sm:text-xs text-gray-400 mb-0.5">الخصم</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-800">{{ $case->opponent ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50/80 rounded-xl p-3 sm:p-4">
                        <p class="text-[10px] sm:text-xs text-gray-400 mb-0.5">هاتف الخصم</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-800 dir=ltr text-left">{{ $case->opponent_phone ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50/80 rounded-xl p-3 sm:p-4">
                        <p class="text-[10px] sm:text-xs text-gray-400 mb-0.5">المحامي المسؤول</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-800">{{ $case->lawyer?->name ?? '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Description --}}
            @if($case->description)
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-gold/15 p-5 sm:p-7 shadow-sm">
                <h2 class="text-xs sm:text-sm font-bold text-gold-dark uppercase tracking-wider mb-3 font-heading flex items-center gap-2">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                    </svg>
                    الوصف
                </h2>
                <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">{{ $case->description }}</p>
            </div>
            @endif

            {{-- Past Sessions --}}
            @if($pastSessions->isNotEmpty())
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-gold/15 shadow-sm overflow-hidden">
                <div class="px-5 sm:px-7 py-4 sm:py-5 border-b border-[#F0F2F5]">
                    <h2 class="text-xs sm:text-sm font-bold text-gold-dark uppercase tracking-wider font-heading flex items-center gap-2">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        الجلسات السابقة
                    </h2>
                </div>
                <div class="divide-y divide-[#EEF0F4]">
                    @foreach($pastSessions as $session)
                        <div class="px-5 sm:px-7 py-4 sm:py-5 hover:bg-[#F7F8FA] transition-colors">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <span class="text-xs sm:text-sm font-bold text-gray-700">{{ $session->date?->format('Y-m-d') }}</span>
                                    <span class="text-[10px] sm:text-xs px-2 py-0.5 rounded-lg font-medium
                                        {{ $session->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $session->status === 'postponed' ? 'bg-gold/12 text-gold-dark' : '' }}
                                        {{ $session->status === 'cancelled' ? 'bg-red-100 text-red-700' : '' }}">
                                        @lang('app.status_' . $session->status)
                                    </span>
                                </div>
                                <span class="text-[10px] sm:text-xs text-gray-400">{{ $session->location ?? '—' }}</span>
                            </div>
                            @if($session->notes)
                                <p class="text-xs sm:text-sm text-gray-600 bg-gray-50 rounded-xl p-3 sm:p-4 mt-2 leading-relaxed">{{ $session->notes }}</p>
                            @endif
                            @if($session->report)
                                <div class="mt-2">
                                    <p class="text-[10px] sm:text-xs text-gray-400 mb-1">التقرير:</p>
                                    <p class="text-xs sm:text-sm text-gray-700 bg-[#F8FAFC] rounded-xl p-3 sm:p-4 leading-relaxed border border-gold/15/50">{{ $session->report }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Right Sidebar --}}
        <div class="space-y-4 sm:space-y-6">
            {{-- Documents --}}
            @if($case->documents->isNotEmpty())
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-gold/15 shadow-sm overflow-hidden">
                <div class="px-5 sm:px-7 py-4 sm:py-5 border-b border-[#F0F2F5]">
                    <h2 class="text-xs sm:text-sm font-bold text-gold-dark uppercase tracking-wider font-heading flex items-center gap-2">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        المستندات
                        <span class="text-[10px] text-gray-400 font-normal mr-1">({{ $case->documents->count() }})</span>
                    </h2>
                </div>
                <div class="divide-y divide-[#EEF0F4]">
                    @foreach($case->documents as $doc)
                        <div class="px-5 sm:px-7 py-3 sm:py-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-700">{{ $doc->title }}</p>
                            <div class="flex items-center gap-1.5 sm:gap-2 mt-1">
                                <span class="text-[10px] sm:text-xs text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded">{{ $doc->type }}</span>
                                <span class="text-gray-300">·</span>
                                <span class="text-[10px] sm:text-xs text-gray-400">{{ $doc->created_at?->format('Y-m-d') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Quick Info --}}
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-gold/15 p-5 sm:p-7 shadow-sm">
                <h2 class="text-xs sm:text-sm font-bold text-gold-dark uppercase tracking-wider mb-4 font-heading flex items-center gap-2">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    معلومات
                </h2>
                <div class="space-y-3 text-xs sm:text-sm text-gray-500">
                    <div class="flex justify-between">
                        <span>تاريخ الإنشاء</span>
                        <span class="text-gray-700 font-medium">{{ $case->created_at?->format('Y-m-d') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>آخر تحديث</span>
                        <span class="text-gray-700 font-medium">{{ $case->updated_at?->format('Y-m-d') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
