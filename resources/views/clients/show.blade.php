@extends('layouts.app')

@section('title', $client->name)

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold">{{ $client->name }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('clients.edit', $client) }}" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.edit') }}</a>
            <a href="{{ route('clients.index') }}" class="text-ivory/50 hover:text-ivory transition">{{ __('app.back') }}</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-navy-light rounded-xl border border-ivory/10 p-6">
                <h2 class="text-lg font-semibold text-gold mb-4">{{ __('app.client_details') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-navy rounded-lg p-4">
                        <p class="text-ivory/40 text-sm mb-1">{{ __('app.name') }}</p>
                        <p class="text-ivory font-medium">{{ $client->name }}</p>
                    </div>
                    <div class="bg-navy rounded-lg p-4">
                        <p class="text-ivory/40 text-sm mb-1">{{ __('app.type') }}</p>
                        @if($client->type === 'company')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gold/20 text-gold border border-gold/30">{{ __('app.company') }}</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30">{{ __('app.individual') }}</span>
                        @endif
                    </div>
                    <div class="bg-navy rounded-lg p-4">
                        <p class="text-ivory/40 text-sm mb-1">{{ __('app.phone') }}</p>
                        <p class="text-ivory font-medium" dir="ltr">{{ $client->phone ?? '—' }}</p>
                    </div>
                    <div class="bg-navy rounded-lg p-4">
                        <p class="text-ivory/40 text-sm mb-1">{{ __('app.email') }}</p>
                        <p class="text-ivory font-medium">{{ $client->email ?? '—' }}</p>
                    </div>
                    <div class="bg-navy rounded-lg p-4">
                        <p class="text-ivory/40 text-sm mb-1">{{ __('app.client_national_id') }}</p>
                        <p class="text-ivory font-medium" dir="ltr">{{ $client->national_id ?? '—' }}</p>
                    </div>
                    <div class="bg-navy rounded-lg p-4">
                        <p class="text-ivory/40 text-sm mb-1">{{ __('app.company_name') }}</p>
                        <p class="text-ivory font-medium">{{ $client->company_name ?? '—' }}</p>
                    </div>
                    <div class="bg-navy rounded-lg p-4 md:col-span-2">
                        <p class="text-ivory/40 text-sm mb-1">{{ __('app.client_address') }}</p>
                        <p class="text-ivory font-medium">{{ $client->address ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="bg-navy-light rounded-xl border border-gold/20 p-6">
                <div class="text-center">
                    <p class="text-5xl font-bold text-gold">{{ $client->cases_count ?? 0 }}</p>
                    <p class="text-ivory/50 mt-2">{{ __('app.registered_cases') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-navy-light rounded-xl border border-ivory/10 overflow-hidden">
        <div class="px-6 py-4 border-b border-ivory/10">
            <h2 class="text-lg font-semibold text-gold">{{ __('app.client_cases') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-ivory/10">
                        <th class="text-right px-6 py-3 text-sm font-semibold text-gold">{{ __('app.case_number') }}</th>
                        <th class="text-right px-6 py-3 text-sm font-semibold text-gold">{{ __('app.title') }}</th>
                        <th class="text-right px-6 py-3 text-sm font-semibold text-gold">{{ __('app.status') }}</th>
                        <th class="text-right px-6 py-3 text-sm font-semibold text-gold">{{ __('app.case_lawyer') }}</th>
                        <th class="text-right px-6 py-3 text-sm font-semibold text-gold">{{ __('app.created_at') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ivory/5">
                    @forelse($client->cases ?? [] as $case)
                        <tr class="hover:bg-navy-lighter/50 transition">
                            <td class="px-6 py-4 text-ivory font-medium" dir="ltr">{{ $case->case_number }}</td>
                            <td class="px-6 py-4 text-ivory/70">{{ $case->title }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $case->status === 'active' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' :
                                       ($case->status === 'closed' ? 'bg-ivory/10 text-ivory/50 border border-ivory/10' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30') }}">
                                    {{ $case->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-ivory/70">{{ $case->lawyer->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-ivory/50 text-sm">{{ $case->created_at?->format('Y-m-d') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-ivory/30">{{ __('app.no_client_cases') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
