@extends('layouts.app')

@section('title', __('app.page_my_documents'))

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-[#C9A55A]">{{ __('app.my_documents') }}</h1>
    </div>

    <div class="bg-navy rounded-xl border border-[#C9A55A]/20 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead>
                    <tr class="border-b border-white/10">
                        <th class="px-4 py-3 text-[#C9A55A] font-bold whitespace-nowrap">{{ __('app.title') }}</th>
                        <th class="px-4 py-3 text-[#C9A55A] font-bold whitespace-nowrap">{{ __('app.type') }}</th>
                        <th class="px-4 py-3 text-[#C9A55A] font-bold whitespace-nowrap">{{ __('app.table_size') }}</th>
                        <th class="px-4 py-3 text-[#C9A55A] font-bold whitespace-nowrap">{{ __('app.case') }}</th>
                        <th class="px-4 py-3 text-[#C9A55A] font-bold whitespace-nowrap">{{ __('app.access_level') }}</th>
                        <th class="px-4 py-3 text-[#C9A55A] font-bold whitespace-nowrap">{{ __('app.table_uploaded') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($documents as $document)
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded bg-[#C9A55A]/10 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-[#C9A55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <span class="text-white">{{ $document->title }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-white/30 text-xs uppercase">{{ $document->file_type }}</td>
                            <td class="px-4 py-3 text-white/30 text-xs">{{ round($document->file_size / 1024, 1) }} KB</td>
                            <td class="px-4 py-3 text-white/30 text-xs">
                                @if($document->case)
                                    <span class="font-mono">{{ $document->case->case_number }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($document->access_level === 'all') bg-green-500/15 text-green-400 border border-green-500/30
                                    @elseif($document->access_level === 'team') bg-blue-500/15 text-blue-400 border border-blue-500/30
                                    @else bg-red-500/15 text-red-400 border border-red-500/30 @endif">
                                    @if($document->access_level === 'all') {{ __('app.public') }}
                                    @elseif($document->access_level === 'team') {{ __('app.access_team') }}
                                    @else {{ __('app.access_private') }}
                                    @endif
                                </span>
                            </td>
                            <td class="px-4 py-3 text-white/30 text-xs whitespace-nowrap">{{ $document->created_at->format('Y/m/d') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-white/50">
                                <svg class="w-16 h-16 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="text-lg">{{ __('app.no_client_documents_msg') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($documents->hasPages())
            <div class="px-4 py-3 border-t border-white/10">
                {{ $documents->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection