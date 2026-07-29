@extends('layouts.app')

@section('title', __('app.page_my_cases'))

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-amber-600">{{ __('app.my_cases') }}</h1>
    </div>

    <div class="bg-white rounded-xl border border-amber-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="px-4 py-3 text-amber-600 font-bold whitespace-nowrap">{{ __('app.case_number') }}</th>
                        <th class="px-4 py-3 text-amber-600 font-bold whitespace-nowrap">{{ __('app.title') }}</th>
                        <th class="px-4 py-3 text-amber-600 font-bold whitespace-nowrap">{{ __('app.court') }}</th>
                        <th class="px-4 py-3 text-amber-600 font-bold whitespace-nowrap">{{ __('app.status') }}</th>
                        <th class="px-4 py-3 text-amber-600 font-bold whitespace-nowrap">{{ __('app.priority') }}</th>
                        <th class="px-4 py-3 text-amber-600 font-bold whitespace-nowrap">{{ __('app.case_lawyer') }}</th>
                        <th class="px-4 py-3 text-amber-600 font-bold whitespace-nowrap">{{ __('app.case_next_date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($cases as $case)
                        <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="window.location='{{ route('client.cases.show', $case) }}'">
                            <td class="px-4 py-3 text-gray-900 font-mono text-xs">{{ $case->case_number }}</td>
                            <td class="px-4 py-3 text-gray-900 max-w-xs truncate">{{ $case->title }}</td>
                            <td class="px-4 py-3 text-gray-400 max-w-[200px] truncate text-xs">{{ $case->court }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($case->status === 'active') bg-green-100 text-green-700 border border-green-200
                                    @elseif($case->status === 'pending') bg-yellow-100 text-yellow-700 border border-yellow-200
                                    @elseif($case->status === 'overdue') bg-red-100 text-red-700 border border-red-200
                                    @elseif($case->status === 'closed') bg-gray-100 text-gray-500 border border-gray-200
                                    @elseif($case->status === 'won') bg-blue-100 text-blue-700 border border-blue-200
                                    @elseif($case->status === 'lost') bg-red-100 text-red-700 border border-red-200
                                    @else bg-gray-100 text-gray-500 border border-gray-200 @endif">
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
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($case->priority === 'low') bg-gray-100 text-gray-500 border border-gray-200
                                    @elseif($case->priority === 'medium') bg-yellow-100 text-yellow-700 border border-yellow-200
                                    @elseif($case->priority === 'high') bg-orange-100 text-orange-700 border border-orange-200
                                    @elseif($case->priority === 'urgent') bg-red-100 text-red-700 border border-red-200
                                    @else bg-gray-100 text-gray-500 border border-gray-200 @endif">
                                    @if($case->priority === 'low') {{ __('app.priority_low') }}
                                    @elseif($case->priority === 'medium') {{ __('app.priority_medium') }}
                                    @elseif($case->priority === 'high') {{ __('app.priority_high') }}
                                    @elseif($case->priority === 'urgent') {{ __('app.priority_urgent') }}
                                    @else {{ $case->priority }}
                                    @endif
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ $case->lawyer->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
                                @if($case->next_date)
                                    {{ \Carbon\Carbon::parse($case->next_date)->format('Y/m/d') }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                                <svg class="w-16 h-16 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <p class="text-lg">{{ __('app.no_client_cases_msg') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($cases->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $cases->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
