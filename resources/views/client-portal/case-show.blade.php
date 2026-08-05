@extends('layouts.app')

@section('title', __('app.page_my_case_details') . ': ' . $case->title)

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('client.cases') }}" class="p-2 rounded-lg bg-gray-100 text-gray-400 hover:text-gray-700 hover:bg-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-amber-600">{{ $case->title }}</h1>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
            @if($case->status === 'active') bg-green-100 text-green-700 border border-green-200
            @elseif($case->status === 'pending') bg-yellow-100 text-yellow-700 border border-yellow-200
            @elseif($case->status === 'overdue') bg-red-100 text-red-700 border border-red-200
            @elseif($case->status === 'closed') bg-gray-100 text-gray-500 border border-gray-200
            @elseif($case->status === 'won') bg-blue-100 text-blue-700 border border-blue-200
            @elseif($case->status === 'lost') bg-red-100 text-red-700 border border-red-200
            @elseif($case->status === 'adjudicated') bg-emerald-100 text-emerald-700 border border-emerald-200
            @elseif($case->status === 'fees_pending') bg-red-100 text-red-700 border border-red-200
            @endif">
            @if($case->status === 'active') {{ __('app.status_active') }}
            @elseif($case->status === 'pending') {{ __('app.status_pending') }}
            @elseif($case->status === 'overdue') {{ __('app.status_overdue') }}
            @elseif($case->status === 'closed') {{ __('app.status_closed') }}
            @elseif($case->status === 'won') {{ __('app.status_won') }}
            @elseif($case->status === 'lost') {{ __('app.status_lost') }}
            @elseif($case->status === 'adjudicated') {{ __('app.status_adjudicated') }}
            @elseif($case->status === 'fees_pending') {{ __('app.status_fees_pending') }}
            @endif
        </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-amber-200 p-6">
                <h2 class="text-lg font-bold text-amber-600 mb-4">{{ __('app.case_details') }}</h2>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-400">{{ __('app.case_number') }}:</span>
                        <p class="text-gray-900 font-mono mt-1">{{ $case->case_number }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">{{ __('app.case_court') }}:</span>
                        <p class="text-gray-900 mt-1">{{ $case->court }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">{{ __('app.type') }}:</span>
                        <p class="text-gray-900 mt-1">{{ $case->type }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">{{ __('app.case_opponent') }}:</span>
                        <p class="text-gray-900 mt-1">{{ $case->opponent }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">{{ __('app.case_priority') }}:</span>
                        <p class="text-gray-900 mt-1">
                            @if($case->priority === 'low') {{ __('app.priority_low') }}
                            @elseif($case->priority === 'medium') {{ __('app.priority_medium') }}
                            @elseif($case->priority === 'high') {{ __('app.priority_high') }}
                            @elseif($case->priority === 'urgent') {{ __('app.priority_urgent') }}
                            @endif
                        </p>
                    </div>
                    <div>
                        <span class="text-gray-400">{{ __('app.case_lawyer') }}:</span>
                        <p class="text-gray-900 mt-1">{{ $case->lawyer->name ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">{{ __('app.opened_date') }}:</span>
                        <p class="text-gray-900 mt-1">{{ \Carbon\Carbon::parse($case->opened_at)->format('Y/m/d') }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">{{ __('app.next_date') }}:</span>
                        <p class="text-gray-900 mt-1">{{ $case->next_date ? \Carbon\Carbon::parse($case->next_date)->format('Y/m/d') : '—' }}</p>
                    </div>
                </div>
                @if($case->description)
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <span class="text-gray-400 text-sm">{{ __('app.description') }}:</span>
                        <p class="text-gray-700 mt-1 text-sm leading-relaxed">{{ $case->description }}</p>
                    </div>
                @endif
            </div>

            @if($case->sessions->count())
                <div class="bg-white rounded-xl border border-amber-200 p-6">
                    <h2 class="text-lg font-bold text-amber-600 mb-4">{{ __('app.sessions') }}</h2>
                    <div class="space-y-3">
                        @foreach($case->sessions as $session)
                            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 border border-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-gray-900 text-sm font-medium">{{ $session->location }}</p>
                                        <p class="text-gray-400 text-xs">{{ \Carbon\Carbon::parse($session->date)->format('Y/m/d H:i') }}</p>
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($session->status === 'upcoming') bg-blue-100 text-blue-700 border border-blue-200
                                    @elseif($session->status === 'completed') bg-green-100 text-green-700 border border-green-200
                                    @elseif($session->status === 'postponed') bg-yellow-100 text-yellow-700 border border-yellow-200
                                    @else bg-gray-100 text-gray-500 border border-gray-200 @endif">
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
                <div class="bg-white rounded-xl border border-amber-200 p-6">
                    <h2 class="text-lg font-bold text-amber-600 mb-4">{{ __('app.documents') }}</h2>
                    <div class="space-y-2">
                        @foreach($case->documents as $document)
                            <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                                <div class="w-8 h-8 rounded bg-amber-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-gray-900 text-sm truncate">{{ $document->title }}</p>
                                    <p class="text-gray-400 text-xs">{{ strtoupper($document->file_type) }}</p>
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
