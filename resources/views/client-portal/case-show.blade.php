@extends('layouts.app')

@section('title', __('app.page_my_case_details') . ': ' . $case->title)

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('client.cases') }}" class="p-2 rounded-lg bg-white/5 text-white/40 hover:text-white hover:bg-white/10 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-[#C9A55A]">{{ $case->title }}</h1>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
            @if($case->status === 'active') bg-green-500/15 text-green-400 border border-green-500/30
            @elseif($case->status === 'pending') bg-yellow-500/15 text-yellow-400 border border-yellow-500/30
            @elseif($case->status === 'overdue') bg-red-500/15 text-red-400 border border-red-500/30
            @elseif($case->status === 'closed') bg-gray-500/15 text-white/40 border border-gray-500/30
            @elseif($case->status === 'won') bg-blue-500/15 text-blue-400 border border-blue-500/30
            @elseif($case->status === 'lost') bg-red-600/15 text-red-300 border border-red-600/30
            @endif">
            @if($case->status === 'active') {{ __('app.status_active') }}
            @elseif($case->status === 'pending') {{ __('app.status_pending') }}
            @elseif($case->status === 'overdue') {{ __('app.status_overdue') }}
            @elseif($case->status === 'closed') {{ __('app.status_closed') }}
            @elseif($case->status === 'won') {{ __('app.status_won') }}
            @elseif($case->status === 'lost') {{ __('app.status_lost') }}
            @endif
        </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
                <h2 class="text-lg font-bold text-[#C9A55A] mb-4">{{ __('app.case_details') }}</h2>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-white/40">{{ __('app.case_number') }}:</span>
                        <p class="text-white font-mono mt-1">{{ $case->case_number }}</p>
                    </div>
                    <div>
                        <span class="text-white/40">{{ __('app.case_court') }}:</span>
                        <p class="text-white mt-1">{{ $case->court }}</p>
                    </div>
                    <div>
                        <span class="text-white/40">{{ __('app.type') }}:</span>
                        <p class="text-white mt-1">{{ $case->type }}</p>
                    </div>
                    <div>
                        <span class="text-white/40">{{ __('app.case_opponent') }}:</span>
                        <p class="text-white mt-1">{{ $case->opponent }}</p>
                    </div>
                    <div>
                        <span class="text-white/40">{{ __('app.case_priority') }}:</span>
                        <p class="text-white mt-1">
                            @if($case->priority === 'low') {{ __('app.priority_low') }}
                            @elseif($case->priority === 'medium') {{ __('app.priority_medium') }}
                            @elseif($case->priority === 'high') {{ __('app.priority_high') }}
                            @elseif($case->priority === 'urgent') {{ __('app.priority_urgent') }}
                            @endif
                        </p>
                    </div>
                    <div>
                        <span class="text-white/40">{{ __('app.case_lawyer') }}:</span>
                        <p class="text-white mt-1">{{ $case->lawyer->name ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-white/40">{{ __('app.opened_date') }}:</span>
                        <p class="text-white mt-1">{{ \Carbon\Carbon::parse($case->opened_at)->format('Y/m/d') }}</p>
                    </div>
                    <div>
                        <span class="text-white/40">{{ __('app.next_date') }}:</span>
                        <p class="text-white mt-1">{{ $case->next_date ? \Carbon\Carbon::parse($case->next_date)->format('Y/m/d') : '—' }}</p>
                    </div>
                </div>
                @if($case->description)
                    <div class="mt-4 pt-4 border-t border-white/10">
                        <span class="text-white/40 text-sm">{{ __('app.description') }}:</span>
                        <p class="text-white mt-1 text-sm leading-relaxed">{{ $case->description }}</p>
                    </div>
                @endif
            </div>

            @if($case->sessions->count())
                <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
                    <h2 class="text-lg font-bold text-[#C9A55A] mb-4">{{ __('app.sessions') }}</h2>
                    <div class="space-y-3">
                        @foreach($case->sessions as $session)
                            <div class="flex items-center justify-between p-3 rounded-lg bg-white/5 border border-white/5">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-[#C9A55A]/10 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-[#C9A55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-white text-sm font-medium">{{ $session->location }}</p>
                                        <p class="text-white/40 text-xs">{{ \Carbon\Carbon::parse($session->date)->format('Y/m/d H:i') }}</p>
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($session->status === 'upcoming') bg-blue-500/15 text-blue-400 border border-blue-500/30
                                    @elseif($session->status === 'completed') bg-green-500/15 text-green-400 border border-green-500/30
                                    @elseif($session->status === 'postponed') bg-yellow-500/15 text-yellow-400 border border-yellow-500/30
                                    @else bg-gray-500/15 text-white/40 border border-gray-500/30 @endif">
                                    @if($session->status === 'upcoming') {{ __('app.status_upcoming') }}
                                    @elseif($session->status === 'completed') {{ __('app.status_completed') }}
                                    @elseif($session->status === 'postponed') {{ __('app.status_postponed') }}
                                    @else {{ __('app.status_cancelled') }}
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            @if($case->documents->count())
                <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
                    <h2 class="text-lg font-bold text-[#C9A55A] mb-4">{{ __('app.documents') }}</h2>
                    <div class="space-y-2">
                        @foreach($case->documents as $document)
                            <div class="flex items-center gap-3 p-3 rounded-lg bg-white/5 border border-white/5">
                                <div class="w-8 h-8 rounded bg-[#C9A55A]/10 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-[#C9A55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-white text-sm truncate">{{ $document->title }}</p>
                                    <p class="text-white/40 text-xs">{{ strtoupper($document->file_type) }}</p>
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