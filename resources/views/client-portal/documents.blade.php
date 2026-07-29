@extends('layouts.app')

@section('title', __('app.page_my_documents'))

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-amber-600">{{ __('app.my_documents') }}</h1>
    </div>

    <div class="bg-white rounded-xl border border-amber-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="px-4 py-3 text-amber-600 font-bold whitespace-nowrap">{{ __('app.title') }}</th>
                        <th class="px-4 py-3 text-amber-600 font-bold whitespace-nowrap">{{ __('app.type') }}</th>
                        <th class="px-4 py-3 text-amber-600 font-bold whitespace-nowrap">{{ __('app.table_size') }}</th>
                        <th class="px-4 py-3 text-amber-600 font-bold whitespace-nowrap">{{ __('app.case') }}</th>
                        <th class="px-4 py-3 text-amber-600 font-bold whitespace-nowrap">{{ __('app.access_level') }}</th>
                        <th class="px-4 py-3 text-amber-600 font-bold whitespace-nowrap">{{ __('app.table_uploaded') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($documents as $document)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded bg-amber-100 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <span class="text-gray-900">{{ $document->title }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-xs uppercase">{{ $document->file_type }}</td>
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ round($document->file_size / 1024, 1) }} KB</td>
                            <td class="px-4 py-3 text-gray-400 text-xs">
                                @if($document->case)
                                    <span class="font-mono">{{ $document->case->case_number }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($document->access_level === 'all') bg-green-100 text-green-700 border border-green-200
                                    @elseif($document->access_level === 'team') bg-blue-100 text-blue-700 border border-blue-200
                                    @else bg-red-100 text-red-700 border border-red-200 @endif">
                                    @if($document->access_level === 'all') {{ __('app.public') }}
                                    @elseif($document->access_level === 'team') {{ __('app.access_team') }}
                                    @else {{ __('app.access_private') }}
                                    @endif
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">{{ $document->created_at->format('Y/m/d') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-500">
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
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $documents->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
