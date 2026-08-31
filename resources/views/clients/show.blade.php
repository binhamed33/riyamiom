@extends('layouts.app')

@section('title', $client->name)

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold-dark">{{ $client->name }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('clients.edit', $client) }}" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.edit') }}</a>
            <a href="{{ route('clients.index') }}" class="text-gray-500 hover:text-gray-700 transition">{{ __('app.back') }}</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gold-dark mb-4">{{ __('app.client_details') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-gray-400 text-sm mb-1">{{ __('app.name') }}</p>
                        <p class="text-gray-700 font-medium">{{ $client->name }}</p>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-gray-400 text-sm mb-1">{{ __('app.type') }}</p>
                        @if($client->type === 'company')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gold/12 text-gold-dark border border-gold/25">{{ __('app.company') }}</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 border border-blue-200">{{ __('app.individual') }}</span>
                        @endif
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-gray-400 text-sm mb-1">{{ __('app.phone') }}</p>
                        <p class="text-gray-700 font-medium" dir="ltr">{{ $client->phone ?? '—' }}</p>
                        {{-- رقمُ واتساب المشتقّ منه: يُرى هنا لا يُخمَّن.
                             ولا يُعرض إن كان الرقمُ هو نفسَه بعد التطبيع. --}}
                        @php $waNumber = \App\Models\WhatsAppContact::displayWaId($client->phone); @endphp
                        @if($waNumber !== '' && ltrim($waNumber, '+') !== preg_replace('/\D+/', '', (string) $client->phone))
                            <p class="text-[11px] text-gray-400 mt-0.5" dir="ltr">واتساب: {{ $waNumber }}</p>
                        @endif
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-gray-400 text-sm mb-1">{{ __('app.email') }}</p>
                        <p class="text-gray-700 font-medium">{{ $client->email ?? '—' }}</p>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-gray-400 text-sm mb-1">{{ __('app.client_national_id') }}</p>
                        <p class="text-gray-700 font-medium" dir="ltr">{{ $client->national_id ?? '—' }}</p>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-gray-400 text-sm mb-1">{{ __('app.company_name') }}</p>
                        <p class="text-gray-700 font-medium">{{ $client->company_name ?? '—' }}</p>
                    </div>
                    <div class="bg-white rounded-lg p-4 md:col-span-2">
                        <p class="text-gray-400 text-sm mb-1">{{ __('app.client_address') }}</p>
                        <p class="text-gray-700 font-medium">{{ $client->address ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="bg-white rounded-xl border border-gold/15 p-6">
                <div class="text-center">
                    <p class="text-5xl font-bold text-gold-dark">{{ $client->cases_count ?? 0 }}</p>
                    <p class="text-gray-500 mt-2">{{ __('app.registered_cases') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gold-dark">{{ __('app.client_cases') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-right px-6 py-3 text-sm font-semibold text-gold-dark">{{ __('app.case_number') }}</th>
                        <th class="text-right px-6 py-3 text-sm font-semibold text-gold-dark">{{ __('app.title') }}</th>
                        <th class="text-right px-6 py-3 text-sm font-semibold text-gold-dark">{{ __('app.status') }}</th>
                        <th class="text-right px-6 py-3 text-sm font-semibold text-gold-dark">{{ __('app.case_lawyer') }}</th>
                        <th class="text-right px-6 py-3 text-sm font-semibold text-gold-dark">{{ __('app.created_at') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($client->cases ?? [] as $case)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 text-gray-700 font-medium" dir="ltr">{{ $case->case_number }}</td>
                            <td class="px-6 py-4 text-gray-700">{{ $case->title }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $case->status === 'active' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' :
                                       ($case->status === 'closed' ? 'bg-gray-100 text-gray-500 border border-gray-200' : 'bg-gold/12 text-gold-dark border border-gold/15') }}">
                                    {{ $case->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-700">{{ $case->lawyer->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-gray-500 text-sm">{{ $case->created_at?->format('Y-m-d') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-400">{{ __('app.no_client_cases') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection