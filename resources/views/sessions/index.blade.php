@extends('layouts.app')

@section('title', __('app.page_sessions'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900">{{ __('app.sessions') }}</h2>
        <a href="{{ route('sessions.create') }}"
           class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('app.new_session') }}
        </a>
    </div>

    <form method="GET" action="{{ route('sessions.index') }}" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.status') }}</label>
                <select name="status" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="upcoming" {{ request('status') === 'upcoming' ? 'selected' : '' }}>{{ __('app.status_upcoming') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('app.status_completed') }}</option>
                    <option value="postponed" {{ request('status') === 'postponed' ? 'selected' : '' }}>{{ __('app.status_postponed') }}</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('app.status_cancelled') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.from_date') }}</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.to_date') }}</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="bg-gray-100 border border-amber-300 text-amber-600 px-5 py-2 rounded-lg hover:bg-amber-200 transition text-sm">
                    {{ __('app.filter') }}
                </button>
                <a href="{{ route('sessions.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                    {{ __('app.reset') }}
                </a>
            </div>
        </div>
    </form>

    <div class="bg-white rounded-xl border border-gray-200">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class=" text-gray-900">
                <tr>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.case_court_with_number') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.case_principal') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.case_opponent') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.date') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.status') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($sessions as $session)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 font-medium text-gray-900">
                            {{ $session->case->court ?? '—' }}
                            @if(!empty($session->case->case_number))
                                <span class="block text-xs text-gray-500 font-normal">{{ $session->case->case_number }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            {{ $session->case->client->name ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            {{ $session->case->opponent ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            {{ $session->date->format('Y-m-d') }}
                        </td>
                        <td class="px-6 py-4">
                            @switch($session->status)
                                @case('upcoming')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                        {{ __('app.status_upcoming') }}
                                    </span>
                                    @break
                                @case('completed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                        {{ __('app.status_completed') }}
                                    </span>
                                    @break
                                @case('postponed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                        {{ __('app.status_postponed') }}
                                    </span>
                                    @break
                                @case('cancelled')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                        {{ __('app.status_cancelled') }}
                                    </span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1">
                                <a href="{{ route('sessions.show', $session) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 transition-colors" title="{{ __('app.view') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </a>
                                <a href="{{ route('sessions.edit', $session) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors" title="{{ __('app.edit') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                </a>
                                <form method="POST" action="{{ route('sessions.destroy', $session) }}" class="contents" onsubmit="return confirm('{{ __("app.confirm_delete") }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors" title="{{ __('app.delete') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            {{ __('app.no_sessions') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    @if ($sessions->hasPages())
        <div class="mt-4">
            {{ $sessions->links() }}
        </div>
    @endif
</div>
@endsection
