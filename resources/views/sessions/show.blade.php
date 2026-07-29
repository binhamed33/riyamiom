@extends('layouts.app')

@section('title', __('app.page_session_details'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900">{{ __('app.session_details') }}</h2>
        <div class="flex items-center gap-2">
            <a href="{{ route('sessions.edit', $session) }}"
               class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                {{ __('app.edit') }}
            </a>
            <a href="{{ route('sessions.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors text-sm flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ __('app.back') }}
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">{{ __('app.session_details') }}</h3>
            @switch($session->status)
                @case('upcoming')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-700">
                        {{ __('app.status_upcoming') }}
                    </span>
                    @break
                @case('completed')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-700">
                        {{ __('app.status_completed') }}
                    </span>
                    @break
                @case('postponed')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-700">
                        {{ __('app.status_postponed') }}
                    </span>
                    @break
                @case('cancelled')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-red-100 text-red-700">
                        {{ __('app.status_cancelled') }}
                    </span>
                    @break
            @endswitch
        </div>

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <dt class="text-sm text-gray-500 mb-1">{{ __('app.session_datetime') }}</dt>
                <dd class="text-gray-800 font-medium">{{ $session->date->format('Y-m-d H:i') }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 mb-1">{{ __('app.location') }}</dt>
                <dd class="text-gray-800 font-medium">{{ $session->location }}</dd>
            </div>
        </dl>

        @if ($session->notes)
            <div class="mt-6">
                <h4 class="text-sm text-gray-500 mb-2">{{ __('app.notes') }}</h4>
                <div class="bg-gray-100 rounded-lg p-4 text-gray-700 leading-relaxed whitespace-pre-wrap">{{ $session->notes }}</div>
            </div>
        @endif

        @if ($session->report)
            <div class="mt-6">
                <h4 class="text-sm text-gray-500 mb-2">{{ __('app.session_report') }}</h4>
                <div class="bg-gray-100 rounded-lg p-4 text-gray-700 leading-relaxed whitespace-pre-wrap">{{ $session->report }}</div>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('app.session_case_info') }}</h3>
        @if ($session->case)
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-800 font-medium">{{ $session->case->title }}</p>
                    <p class="text-sm text-gray-500 mt-1">{{ __('app.case_number') }}: {{ $session->case->case_number }}</p>
                    @if ($session->case->client)
                        <p class="text-sm text-gray-500">{{ __('app.case_client') }}: {{ $session->case->client->name }}</p>
                    @endif
                    @if ($session->case->lawyer)
                        <p class="text-sm text-gray-500">{{ __('app.case_lawyer') }}: {{ $session->case->lawyer->name }}</p>
                    @endif
                </div>
                <a href="{{ route('cases.show', $session->case) }}"
                   class="text-amber-700 hover:text-[#B89349] font-medium text-sm transition-colors">
                    {{ __('app.view_case') }}
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
            </div>
        @else
            <p class="text-gray-500">{{ __('app.no_linked_case') }}</p>
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
