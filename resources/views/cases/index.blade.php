@extends('layouts.app')

@section('title', __('app.page_cases'))

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-[#C9A55A]">{{ __('app.manage_cases') }}</h1>
        <div class="flex flex-wrap items-center gap-3">
            <form action="{{ route('cases.detectOverdue') }}" method="POST" class="contents">
                @csrf
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2.5 rounded-lg font-medium transition-colors text-sm inline-flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    {{ __('app.overdue_report') }}
                </button>
            </form>
            <a href="{{ route('cases.create') }}" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('app.add_new_case') }}
            </a>
            <a href="{{ route('cases.monthly') }}" class="bg-navy-darker hover:bg-navy-light text-ivory/70 border border-gold/20 px-5 py-2.5 rounded-lg font-medium transition-colors text-sm inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                القضايا الشهرية
            </a>
        </div>
    </div>

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('cases.index') }}" class="bg-navy rounded-xl border border-[#C9A55A]/20 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Search --}}
            <div class="lg:col-span-1">
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('app.search_placeholder_general') }}"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 pr-10 pl-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                    <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>

            {{-- Status --}}
            <div>
                <select name="status" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('app.status_active') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('app.status_pending') }}</option>
                    <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>{{ __('app.status_overdue') }}</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>{{ __('app.status_closed') }}</option>
                    <option value="won" {{ request('status') === 'won' ? 'selected' : '' }}>{{ __('app.status_won') }}</option>
                    <option value="lost" {{ request('status') === 'lost' ? 'selected' : '' }}>{{ __('app.status_lost') }}</option>
                </select>
            </div>

            {{-- Priority --}}
            <div>
                <select name="priority" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>{{ __('app.priority_low') }}</option>
                    <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>{{ __('app.priority_medium') }}</option>
                    <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>{{ __('app.priority_high') }}</option>
                    <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>{{ __('app.priority_urgent') }}</option>
                </select>
            </div>

            {{-- Submit --}}
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                    {{ __('app.filter') }}
                </button>
                <a href="{{ route('cases.index') }}" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                    {{ __('app.reset') }}
                </a>
            </div>
        </div>
    </form>

    {{-- Cases Table --}}
    <div class="bg-navy rounded-xl border border-[#C9A55A]/20 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead>
                    <tr class="border-b border-white/10">
                        <th class="px-3 py-2 text-[#C9A55A] font-bold whitespace-nowrap text-xs">{{ __('app.case_number') }}</th>
                        <th class="px-3 py-2 text-[#C9A55A] font-bold whitespace-nowrap text-xs">{{ __('app.court') }}</th>
                        <th class="px-3 py-2 text-[#C9A55A] font-bold whitespace-nowrap text-xs">{{ __('app.case_client') }}</th>
                        <th class="px-3 py-2 text-[#C9A55A] font-bold whitespace-nowrap text-xs">{{ __('app.case_opponent') }}</th>
                        <th class="px-3 py-2 text-[#C9A55A] font-bold whitespace-nowrap text-xs">{{ __('app.next_session_date') }}</th>
                        <th class="px-3 py-2 text-[#C9A55A] font-bold whitespace-nowrap text-xs">{{ __('app.case_type') }}</th>
                        <th class="px-3 py-2 text-[#C9A55A] font-bold whitespace-nowrap text-xs">{{ __('app.case_lawyer') }}</th>
                        <th class="px-3 py-2 text-[#C9A55A] font-bold whitespace-nowrap text-xs">{{ __('app.status') }}</th>
                        <th class="px-3 py-2 text-[#C9A55A] font-bold whitespace-nowrap text-xs">{{ __('app.priority') }}</th>
                        <th class="px-3 py-2 text-[#C9A55A] font-bold whitespace-nowrap text-xs">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($cases ?? [] as $case)
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="px-3 py-2 text-white font-mono font-medium text-xs whitespace-nowrap">{{ $case->case_number }}</td>
                            <td class="px-3 py-2 text-white/40 text-xs">{{ $case->court }}</td>
                            <td class="px-3 py-2 text-white text-xs">{{ $case->client->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-white/30 text-xs">{{ $case->opponent ?? '—' }}</td>
                            <td class="px-3 py-2 text-white/30 text-xs whitespace-nowrap">
                                @if($case->next_date)
                                    {{ \Carbon\Carbon::parse($case->next_date)->format('Y/m/d') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2 text-white/30 text-xs">{{ $case->case_type ?? '—' }}</td>
                            <td class="px-3 py-2 text-white/30 text-xs">{{ $case->lawyer->name ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap
                                    @if($case->status === 'active') bg-green-500/15 text-green-400 border border-green-500/30
                                    @elseif($case->status === 'pending') bg-yellow-500/15 text-yellow-400 border border-yellow-500/30
                                    @elseif($case->status === 'overdue') bg-red-500/15 text-red-400 border border-red-500/30
                                    @elseif($case->status === 'closed') bg-gray-500/15 text-white/40 border border-gray-500/30
                                    @elseif($case->status === 'won') bg-blue-500/15 text-blue-400 border border-blue-500/30
                                    @elseif($case->status === 'lost') bg-red-600/15 text-red-300 border border-red-600/30
                                    @else bg-gray-500/15 text-white/40 border border-gray-500/30 @endif">
                                    @if($case->status === 'active') {{ __('app.status_active') }}
                                    @elseif($case->status === 'pending') {{ __('app.status_pending') }}
                                    @elseif($case->status === 'overdue') {{ __('app.status_overdue') }}
                                    @elseif($case->status === 'closed') {{ __('app.status_closed') }}
                                    @elseif($case->status === 'won') {{ __('app.status_won') }}
                                    @elseif($case->status === 'lost') {{ __('app.status_lost') }}
                                    @else {{ $case->status }}
                                    @endif
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap
                                    @if($case->priority === 'low') bg-gray-500/15 text-white/40 border border-gray-500/30
                                    @elseif($case->priority === 'medium') bg-yellow-500/15 text-yellow-400 border border-yellow-500/30
                                    @elseif($case->priority === 'high') bg-orange-500/15 text-orange-400 border border-orange-500/30
                                    @elseif($case->priority === 'urgent') bg-red-500/15 text-red-400 border border-red-500/30
                                    @else bg-gray-500/15 text-white/40 border border-gray-500/30 @endif">
                                    @if($case->priority === 'low') {{ __('app.priority_low') }}
                                    @elseif($case->priority === 'medium') {{ __('app.priority_medium') }}
                                    @elseif($case->priority === 'high') {{ __('app.priority_high') }}
                                    @elseif($case->priority === 'urgent') {{ __('app.priority_urgent') }}
                                    @else {{ $case->priority }}
                                    @endif
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('cases.show', $case->id) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition-colors" title="{{ __('app.view') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    <a href="{{ route('cases.edit', $case->id) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-[#C9A55A]/10 text-[#C9A55A] hover:bg-[#C9A55A]/20 transition-colors" title="{{ __('app.edit') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form action="{{ route('cases.destroy', $case->id) }}" method="POST" class="contents" onsubmit="return confirm('{{ __("app.confirm_delete_case") }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors" title="{{ __('app.delete') }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center text-white/50">
                                <svg class="w-16 h-16 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <p class="text-lg">{{ __('app.no_cases') }}</p>
                                <a href="{{ route('cases.create') }}" class="mt-3 inline-block text-[#C9A55A] hover:underline text-sm">{{ __('app.add_case_prompt') }}</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if(isset($cases) && $cases instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $cases->hasPages())
            <div class="px-4 py-3 border-t border-white/10">
                {{ $cases->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
