@extends('layouts.app')

@section('title', __('app.page_my_sessions'))

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gold-dark">{{ __('app.my_sessions') }}</h1>
    </div>

    <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="px-4 py-3 text-gold-dark font-bold whitespace-nowrap">{{ __('app.date') }}</th>
                        <th class="px-4 py-3 text-gold-dark font-bold whitespace-nowrap">{{ __('app.location') }}</th>
                        <th class="px-4 py-3 text-gold-dark font-bold whitespace-nowrap">{{ __('app.case') }}</th>
                        <th class="px-4 py-3 text-gold-dark font-bold whitespace-nowrap">{{ __('app.status') }}</th>
                        <th class="px-4 py-3 text-gold-dark font-bold whitespace-nowrap">{{ __('app.notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-gray-900 text-xs whitespace-nowrap">{{ \Carbon\Carbon::parse($session->date)->format('Y/m/d H:i') }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $session->location }}</td>
                            <td class="px-4 py-3 text-gray-400 text-xs">
                                @if($session->case)
                                    <span class="font-mono">{{ $session->case->case_number }}</span>
                                    <span class="block text-gray-700 mt-0.5">{{ $session->case->title }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
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
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-xs max-w-[200px] truncate">{{ $session->notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-gray-500">
                                <svg class="w-16 h-16 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-lg">{{ __('app.no_client_sessions_msg') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($sessions->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $sessions->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
