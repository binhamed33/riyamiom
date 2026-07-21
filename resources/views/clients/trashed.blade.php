@extends('layouts.app')

@section('title', __('app.page_trashed_clients'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold">{{ __('app.page_trashed_clients') }}</h1>
        <a href="{{ route('clients.index') }}" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            {{ __('app.back_to_clients') }}
        </a>
    </div>

    <div class="bg-navy-light rounded-xl border border-ivory/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-ivory/10">
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.name') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.type') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.phone') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.email') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.cases') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.date') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ivory/5">
                    @forelse($clients as $client)
                        <tr class="hover:bg-navy-lighter/50 transition">
                            <td class="px-6 py-4">
                                <span class="text-ivory font-medium">{{ $client->name }}</span>
                            </td>
                            <td class="px-6 py-4">
                                @if($client->type === 'company')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gold/20 text-gold border border-gold/30">{{ __('app.company') }}</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30">{{ __('app.individual') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-ivory/70" dir="ltr">{{ $client->phone ?? '—' }}</td>
                            <td class="px-6 py-4 text-ivory/70">{{ $client->email ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <span class="bg-navy-lighter px-2.5 py-1 rounded-md text-ivory/70 text-sm">{{ $client->cases_count ?? 0 }}</span>
                            </td>
                            <td class="px-6 py-4 text-ivory/70 text-sm whitespace-nowrap">
                                {{ $client->deleted_at->format('Y/m/d H:i') }}
                            </td>
                            <td class="px-6 py-4">
                                <form method="POST" action="{{ route('clients.restore', $client->id) }}" class="contents" onsubmit="return confirm('{{ __("app.restore_client_confirm") }}')">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20 transition-colors">
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
                            <td colspan="7" class="px-6 py-12 text-center text-ivory/30">{{ __('app.no_trashed_clients') }}</td>
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
