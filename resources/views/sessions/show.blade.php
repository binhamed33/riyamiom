@extends('layouts.app')

@section('title', __('app.page_session_details'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-white">{{ __('app.session_details') }}</h2>
        <div class="flex items-center gap-2">
            <a href="{{ route('sessions.edit', $session) }}"
               class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                {{ __('app.edit') }}
            </a>
            <a href="{{ route('sessions.index') }}" class="text-white/50 hover:text-white/70 transition-colors text-sm flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ __('app.back') }}
            </a>
        </div>
    </div>

    <div class="bg-navy rounded-xl border border-white/10 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-white">{{ __('app.session_details') }}</h3>
            @switch($session->status)
                @case('upcoming')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-500/20 text-blue-400">
                        {{ __('app.status_upcoming') }}
                    </span>
                    @break
                @case('completed')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-500/20 text-green-400">
                        {{ __('app.status_completed') }}
                    </span>
                    @break
                @case('postponed')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-yellow-500/20 text-yellow-400">
                        {{ __('app.status_postponed') }}
                    </span>
                    @break
                @case('cancelled')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-red-500/20 text-red-400">
                        {{ __('app.status_cancelled') }}
                    </span>
                    @break
            @endswitch
        </div>

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <dt class="text-sm text-white/50 mb-1">{{ __('app.session_datetime') }}</dt>
                <dd class="text-white/80 font-medium">{{ $session->date->format('Y-m-d H:i') }}</dd>
            </div>
            <div>
                <dt class="text-sm text-white/50 mb-1">{{ __('app.location') }}</dt>
                <dd class="text-white/80 font-medium">{{ $session->location }}</dd>
            </div>
        </dl>

        @if ($session->notes)
            <div class="mt-6">
                <h4 class="text-sm text-white/50 mb-2">{{ __('app.notes') }}</h4>
                <div class="bg-white/5 rounded-lg p-4 text-white/70 leading-relaxed whitespace-pre-wrap">{{ $session->notes }}</div>
            </div>
        @endif

        @if ($session->report)
            <div class="mt-6">
                <h4 class="text-sm text-white/50 mb-2">{{ __('app.session_report') }}</h4>
                <div class="bg-white/5 rounded-lg p-4 text-white/70 leading-relaxed whitespace-pre-wrap">{{ $session->report }}</div>
            </div>
        @endif
    </div>

    <div class="bg-navy rounded-xl border border-white/10 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">{{ __('app.session_case_info') }}</h3>
        @if ($session->case)
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 font-medium">{{ $session->case->title }}</p>
                    <p class="text-sm text-white/50 mt-1">{{ __('app.case_number') }}: {{ $session->case->case_number }}</p>
                    @if ($session->case->client)
                        <p class="text-sm text-white/50">{{ __('app.case_client') }}: {{ $session->case->client->name }}</p>
                    @endif
                    @if ($session->case->lawyer)
                        <p class="text-sm text-white/50">{{ __('app.case_lawyer') }}: {{ $session->case->lawyer->name }}</p>
                    @endif
                </div>
                <a href="{{ route('cases.show', $session->case) }}"
                   class="text-[#C9A55A] hover:text-[#B89349] font-medium text-sm transition-colors">
                    {{ __('app.view_case') }}
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
            </div>
        @else
            <p class="text-white/50">{{ __('app.no_linked_case') }}</p>
        @endif
    </div>

    <div class="flex items-center gap-3">
        <form method="POST" action="{{ route('sessions.destroy', $session) }}" class="contents" x-data @submit.prevent="if(confirm('{{ __("app.confirm_delete_session") }}')) $el.submit()">
            @csrf
            @method('DELETE')
            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                {{ __('app.delete_session') }}
            </button>
        </form>
    </div>
</div>
@endsection
