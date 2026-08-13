@extends('layouts.app')

@section('title', __('app.page_trashed_clients'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold-dark">{{ __('app.page_trashed_clients') }}</h1>
        <a href="{{ route('clients.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            {{ __('app.back_to_clients') }}
        </a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.name') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.type') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.phone') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.email') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.cases') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.date') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($clients as $client)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <span class="text-gray-700 font-medium">{{ $client->name }}</span>
                            </td>
                            <td class="px-6 py-4">
                                @if($client->type === 'company')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gold/12 text-gold-dark border border-gold/15">{{ __('app.company') }}</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 border border-blue-200">{{ __('app.individual') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-700" dir="ltr">{{ $client->phone ?? '—' }}</td>
                            <td class="px-6 py-4 text-gray-700">{{ $client->email ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <span class="bg-gray-100 px-2.5 py-1 rounded-md text-gray-700 text-sm">{{ $client->cases_count ?? 0 }}</span>
                            </td>
                            <td class="px-6 py-4 text-gray-700 text-sm whitespace-nowrap">
                                {{ $client->deleted_at->format('Y/m/d H:i') }}
                            </td>
                            <td class="px-6 py-4">
                                <form method="POST" action="{{ route('clients.restore', $client->id) }}" class="contents" onsubmit="return confirm('{{ __("app.restore_client_confirm") }}')">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                        </svg>
                                        {{ __('app.restore') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400">{{ __('app.no_trashed_clients') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(method_exists($clients, 'links'))
        <div class="mt-4">
            {{ $clients->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
