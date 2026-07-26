@extends('layouts.app')

@section('title', __('app.page_my_sessions'))

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-[#C9A55A]">{{ __('app.my_sessions') }}</h1>
    </div>

    <div class="bg-navy rounded-xl border border-[#C9A55A]/20 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead>
                    <tr class="border-b border-white/10">
                        <th class="px-4 py-3 text-[#C9A55A] font-bold whitespace-nowrap">{{ __('app.date') }}</th>
                        <th class="px-4 py-3 text-[#C9A55A] font-bold whitespace-nowrap">{{ __('app.location') }}</th>
                        <th class="px-4 py-3 text-[#C9A55A] font-bold whitespace-nowrap">{{ __('app.case') }}</th>
                        <th class="px-4 py-3 text-[#C9A55A] font-bold whitespace-nowrap">{{ __('app.status') }}</th>
                        <th class="px-4 py-3 text-[#C9A55A] font-bold whitespace-nowrap">{{ __('app.notes') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($sessions as $session)
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="px-4 py-3 text-white text-xs whitespace-nowrap">{{ \Carbon\Carbon::parse($session->date)->format('Y/m/d H:i') }}</td>
                            <td class="px-4 py-3 text-white">{{ $session->location }}</td>
                            <td class="px-4 py-3 text-white/30 text-xs">
                                @if($session->case)
                                    <span class="font-mono">{{ $session->case->case_number }}</span>
                                    <span class="block text-white mt-0.5">{{ $session->case->title }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
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
                            </td>
                            <td class="px-4 py-3 text-white/30 text-xs max-w-[200px] truncate">{{ $session->notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-white/50">
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
            <div class="px-4 py-3 border-t border-white/10">
                {{ $sessions->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection