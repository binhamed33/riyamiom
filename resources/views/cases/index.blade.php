@extends('layouts.app')

@section('title', __('app.page_cases'))

@push('styles')
<script nonce="{{ $cspNonce }}">
document.addEventListener('alpine:init', () => {
    Alpine.data('sendPortal', (caseId) => ({
        sending: false,
        result: null,
        async send() {
            if (this.sending) return;
            this.sending = true;
            this.result = null;
            try {
                const res = await fetch('{{ url('cases') }}/' + caseId + '/send-portal-message', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => null);
                if (res.ok && data?.success) {
                    this.result = { ok: true, text: data.message };
                } else {
                    this.result = { ok: false, text: data?.error || '{{ __("app.save_error") }}' };
                    if (data?.fallback_wa_link) {
                        window.open(data.fallback_wa_link, '_blank');
                    }
                }
            } catch(e) {
                this.result = { ok: false, text: '{{ __("app.connection_error") }}' };
            }
            this.sending = false;
            setTimeout(() => this.result = null, 6000);
        }
    }));
});
</script>
@endpush

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-amber-700">{{ __('app.manage_cases') }}</h1>
        <div class="flex flex-wrap items-center gap-3">
            <form action="{{ route('cases.detectOverdue') }}" method="POST" class="contents">
                @csrf
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2.5 rounded-lg font-medium transition-colors text-sm inline-flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    {{ __('app.overdue_report') }}
                </button>
            </form>
            <a href="{{ route('cases.create') }}" class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('app.add_new_case') }}
            </a>
            <a href="{{ route('cases.monthly') }}" class="bg-white hover:bg-gray-100 text-gray-600 border border-amber-200 px-5 py-2.5 rounded-lg font-medium transition-colors text-sm inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                القضايا الشهرية
            </a>
        </div>
    </div>

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('cases.index') }}" class="bg-white rounded-xl border border-amber-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Search --}}
            <div class="lg:col-span-1">
                <div class="relative" x-data="{ open: false, results: [], selected: -1 }">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('app.search_placeholder_general') }}"
                        x-on:input.debounce.300ms="if ($el.value.length > 1) { fetch('{{ route('search') }}?q=' + encodeURIComponent($el.value)).then(r => r.json()).then(d => { results = d.filter(r => r.type === 'case'); open = results.length > 0; selected = -1; }) } else { open = false }"
                        x-on:keydown.down.prevent="if (open) { selected = Math.min(selected + 1, results.length - 1) }"
                        x-on:keydown.up.prevent="if (open) { selected = Math.max(selected - 1, -1) }"
                        x-on:keydown.enter.prevent="if (open && selected >= 0 && results[selected]) { window.location = results[selected].url } else { $el.closest('form').submit() }"
                        x-on:blur="setTimeout(() => open = false, 200)"
                        x-on:focus="if (results.length > 0) open = true"
                        autocomplete="off"
                        class="w-full rounded-lg bg-white border border-gray-200 pr-10 pl-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                    <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <div x-show="open" x-cloak class="absolute z-50 top-full mt-1 w-full bg-white border border-amber-200 rounded-lg shadow-lg overflow-hidden">
                        <template x-for="(r, i) in results" :key="i">
                            <a :href="r.url" x-html="r.label"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-amber-50 transition-colors border-b border-gray-100 last:border-b-0"
                                :class="{ 'bg-amber-50': i === selected }"></a>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Status --}}
            <div>
                <select name="status" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('app.status_active') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('app.status_pending') }}</option>
                    <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>{{ __('app.status_overdue') }}</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>{{ __('app.status_closed') }}</option>
                    <option value="won" {{ request('status') === 'won' ? 'selected' : '' }}>{{ __('app.status_won') }}</option>
                    <option value="lost" {{ request('status') === 'lost' ? 'selected' : '' }}>{{ __('app.status_lost') }}</option>
                </select>
            </div>

            {{-- Priority --}}
            <div>
                <select name="priority" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>{{ __('app.priority_low') }}</option>
                    <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>{{ __('app.priority_medium') }}</option>
                    <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>{{ __('app.priority_high') }}</option>
                    <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>{{ __('app.priority_urgent') }}</option>
                </select>
            </div>

            {{-- Submit --}}
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                    {{ __('app.filter') }}
                </button>
                <a href="{{ route('cases.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                    {{ __('app.reset') }}
                </a>
            </div>
        </div>
    </form>

    {{-- Cases Table --}}
    <div class="bg-white rounded-xl border border-amber-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="px-3 py-2 text-amber-700 font-bold whitespace-nowrap text-xs">{{ __('app.office_case_number') }}</th>
                        <th class="px-3 py-2 text-amber-700 font-bold whitespace-nowrap text-xs">{{ __('app.case_court_with_number') }}</th>
                        <th class="px-3 py-2 text-amber-700 font-bold whitespace-nowrap text-xs">{{ __('app.case_principal') }}</th>
                        <th class="px-3 py-2 text-amber-700 font-bold whitespace-nowrap text-xs">{{ __('app.case_opponent') }}</th>
                        <th class="px-3 py-2 text-amber-700 font-bold whitespace-nowrap text-xs">{{ __('app.case_type') }}</th>
                        <th class="px-3 py-2 text-amber-700 font-bold whitespace-nowrap text-xs">{{ __('app.case_lawyer') }}</th>
                        <th class="px-3 py-2 text-amber-700 font-bold whitespace-nowrap text-xs">{{ __('app.status') }}</th>
                        <th class="px-3 py-2 text-amber-700 font-bold whitespace-nowrap text-xs">{{ __('app.priority') }}</th>
                        <th class="px-3 py-2 text-amber-700 font-bold whitespace-nowrap text-xs">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($cases ?? [] as $case)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-3 py-2 text-gray-900 font-mono font-medium text-xs whitespace-nowrap">{{ $case->office_case_number }}</td>
                            <td class="px-3 py-2 text-gray-900 text-xs whitespace-nowrap">
                                {{ $case->court }}
                                @if(!empty($case->case_number))
                                    <span class="block text-gray-400 font-normal">{{ $case->case_number }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-900 text-xs">{{ $case->client->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-400 text-xs">{{ $case->opponent ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-400 text-xs">{{ $case->case_type ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-400 text-xs">{{ $case->lawyer->name ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap
                                    @if($case->status === 'active') bg-green-100 text-green-700 border border-green-200
                                    @elseif($case->status === 'pending') bg-yellow-100 text-yellow-700 border border-yellow-200
                                    @elseif($case->status === 'overdue') bg-red-100 text-red-700 border border-red-200
                                    @elseif($case->status === 'closed') bg-gray-100 text-gray-400 border border-gray-200
                                    @elseif($case->status === 'won') bg-blue-100 text-blue-700 border border-blue-200
                                    @elseif($case->status === 'lost') bg-red-100 text-red-700 border border-red-200
                                    @else bg-gray-100 text-gray-400 border border-gray-200 @endif">
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
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap
                                    @if($case->priority === 'low') bg-gray-100 text-gray-400 border border-gray-200
                                    @elseif($case->priority === 'medium') bg-yellow-100 text-yellow-700 border border-yellow-200
                                    @elseif($case->priority === 'high') bg-orange-100 text-orange-700 border border-orange-200
                                    @elseif($case->priority === 'urgent') bg-red-100 text-red-700 border border-red-200
                                    @else bg-gray-100 text-gray-400 border border-gray-200 @endif">
                                    @if($case->priority === 'low') {{ __('app.priority_low') }}
                                    @elseif($case->priority === 'medium') {{ __('app.priority_medium') }}
                                    @elseif($case->priority === 'high') {{ __('app.priority_high') }}
                                    @elseif($case->priority === 'urgent') {{ __('app.priority_urgent') }}
                                    @else {{ $case->priority }}
                                    @endif
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('cases.show', $case->id) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 transition-colors" title="{{ __('app.view') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    @if($case->client && ($case->client->phone || $case->client->email))
                                    <div class="relative" x-data="sendPortal({{ $case->id }})">
                                        <button @click="send()" :disabled="sending" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition-colors disabled:opacity-50" title="إرسال رسالة المتابعة للموكل تلقائياً (إيميل وواتساب)">
                                            <svg x-show="!sending" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                            </svg>
                                            <svg x-show="sending" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24" style="display: none;">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                        <div x-show="result" x-cloak class="absolute top-full left-1/2 -translate-x-1/2 mt-2 z-50 rounded-lg px-3 py-1.5 text-xs font-medium shadow-lg max-w-[280px]" :class="result?.ok ? 'bg-green-600 text-white' : 'bg-red-600 text-white'" x-text="result?.text"></div>
                                    </div>
                                    @endif
                                    <a href="{{ route('cases.edit', $case->id) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors" title="{{ __('app.edit') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    @if($case->created_by === auth()->id() || in_array(auth()->user()->role, ['developer', 'admin']))
                                    <div class="relative" x-data="{ open: false }">
                                        <button type="button" @click="open = true" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors" title="{{ __('app.delete') }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                        <form id="delete-case-{{ $case->id }}" action="{{ route('cases.destroy', $case->id) }}" method="POST" class="hidden">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" @keydown.escape="open = false">
                                            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
                                            <div class="relative bg-white border border-red-300 rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
                                                <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-red-100 text-red-700 flex items-center justify-center">
                                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </div>
                                                <h3 class="text-lg font-bold text-gray-900 mb-2">تأكيد حذف القضية</h3>
                                                <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف القضية <span class="font-semibold text-gray-900">{{ $case->case_number }}</span>؟ لا يمكن التراجع عن هذا الإجراء.</p>
                                                <div class="flex gap-3 justify-center">
                                                    <button type="button" @click="document.getElementById('delete-case-{{ $case->id }}').submit()" class="bg-red-500 hover:bg-red-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">نعم، احذف</button>
                                                    <button type="button" @click="open = false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-gray-500">
                                <svg class="w-16 h-16 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <p class="text-lg">{{ __('app.no_cases') }}</p>
                                <a href="{{ route('cases.create') }}" class="mt-3 inline-block text-amber-700 hover:underline text-sm">{{ __('app.add_case_prompt') }}</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if(isset($cases) && $cases instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $cases->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $cases->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
