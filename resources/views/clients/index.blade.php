@extends('layouts.app')

@section('title', __('app.page_clients'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold-dark">{{ __('app.page_clients') }}</h1>
        <a href="{{ route('clients.create') }}" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
            {{ __('app.new_client_button') }}
        </a>
    </div>

    <form method="GET" action="{{ route('clients.index') }}">
        <div class="flex items-center gap-3">
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="{{ __('app.search_placeholder') }}"
                class="flex-1 rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
            >
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.search') }}</button>
            @if(request('search'))
                <a href="{{ route('clients.index') }}" class="text-gray-500 hover:text-gray-900 transition px-3 py-2.5">{{ __('app.clear') }}</a>
            @endif
        </div>
    </form>

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
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($clients as $client)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <a href="{{ route('clients.show', $client) }}" class="text-gray-700 hover:text-gold-dark transition font-medium">
                                    {{ $client->name }}
                                </a>
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
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('clients.edit', $client) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gold/12 text-gold-dark hover:bg-gold/15 transition-colors" title="{{ __('app.edit') }}">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('clients.destroy', $client) }}" class="contents" onsubmit="return confirm('{{ __("app.confirm_delete") }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors" title="{{ __('app.delete') }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400">{{ __('app.no_clients') }}</td>
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
