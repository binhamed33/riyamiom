@extends('layouts.app')

@section('title', __('app.page_trashed_cases'))

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-gold-dark">{{ __('app.page_trashed_cases') }}</h1>
        <a href="{{ route('cases.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            {{ __('app.back_to_cases') }}
        </a>
    </div>

    {{-- Cases Table --}}
    <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="px-4 py-3 text-gold-dark font-bold whitespace-nowrap">{{ __('app.case_number') }}</th>
                        <th class="px-4 py-3 text-gold-dark font-bold whitespace-nowrap">{{ __('app.title') }}</th>
                        <th class="px-4 py-3 text-gold-dark font-bold whitespace-nowrap">{{ __('app.case_client') }}</th>
                        <th class="px-4 py-3 text-gold-dark font-bold whitespace-nowrap">{{ __('app.case_lawyer') }}</th>
                        <th class="px-4 py-3 text-gold-dark font-bold whitespace-nowrap">{{ __('app.status') }}</th>
                        <th class="px-4 py-3 text-gold-dark font-bold whitespace-nowrap">{{ __('app.date') }}</th>
                        <th class="px-4 py-3 text-gold-dark font-bold whitespace-nowrap">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($cases as $case)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-gray-900 font-mono text-xs">{{ $case->case_number }}</td>
                            <td class="px-4 py-3 text-gray-900 max-w-xs truncate">{{ $case->title }}</td>
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ $case->client->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ $case->lawyer->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($case->status === 'active') bg-green-100 text-green-700 border border-green-200
                                    @elseif($case->status === 'pending') bg-yellow-100 text-yellow-700 border border-yellow-200
                                    @elseif($case->status === 'overdue') bg-red-100 text-red-700 border border-red-200
                                    @elseif($case->status === 'closed') bg-gray-100 text-gray-400 border border-gray-200
                                    @elseif($case->status === 'won') bg-blue-100 text-blue-700 border border-blue-200
                                    @elseif($case->status === 'lost') bg-red-600/15 text-red-300 border border-red-600/30
                                    @elseif($case->status === 'adjudicated') bg-emerald-600/15 text-emerald-300 border border-emerald-600/30
                                    @elseif($case->status === 'fees_pending') bg-red-600/15 text-red-300 border border-red-600/30
                                    @else bg-gray-100 text-gray-400 border border-gray-200 @endif">
                                    @if($case->status === 'active') {{ __('app.status_active') }}
                                    @elseif($case->status === 'pending') {{ __('app.status_pending') }}
                                    @elseif($case->status === 'overdue') {{ __('app.status_overdue') }}
                                    @elseif($case->status === 'closed') {{ __('app.status_closed') }}
                                    @elseif($case->status === 'won') {{ __('app.status_won') }}
                                    @elseif($case->status === 'lost') {{ __('app.status_lost') }}
                                    @elseif($case->status === 'adjudicated') {{ __('app.status_adjudicated') }}
                                    @elseif($case->status === 'fees_pending') {{ __('app.status_fees_pending') }}
                                    @else {{ $case->status }}
                                    @endif
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
                                {{ $case->deleted_at->format('Y/m/d H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <form action="{{ route('cases.restore', $case->id) }}" method="POST" class="contents" data-confirm="{{ __('app.restore_case_confirm') }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition-colors" title="{{ __('app.restore') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                                <svg class="w-16 h-16 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <p class="text-lg">{{ __('app.no_trashed_cases') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($cases instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $cases->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $cases->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
