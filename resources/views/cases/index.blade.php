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
        <h1 class="text-2xl font-bold text-gold-dark">{{ __('app.manage_cases') }}</h1>
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
            <a href="{{ route('cases.create') }}" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('app.add_new_case') }}
            </a>
            <a href="{{ route('cases.monthly') }}" class="bg-white hover:bg-gray-100 text-gray-600 border border-gold/15 px-5 py-2.5 rounded-lg font-medium transition-colors text-sm inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                {{ __('app.monthly_cases') }}
            </a>
        </div>
    </div>

    {{-- Filter Bar --}}
    @php
        $activeFilters = collect(['search', 'status', 'priority', 'lawyer_id', 'court', 'case_type', 'date_from', 'date_to'])
            ->filter(fn ($k) => filled(request($k)))->count();
        $selCls = 'w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40';
    @endphp
    <x-filter-panel :action="route('cases.index')" :count="$activeFilters"
                    :clear-url="route('cases.index', ['sort' => $sort, 'dir' => $dir])"
                    :hidden="['sort' => $sort, 'dir' => $dir]">
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
                        class="w-full rounded-lg bg-white border border-gray-200 pr-10 pl-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                    <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <div x-show="open" x-cloak class="absolute z-50 top-full mt-1 w-full bg-white border border-gold/15 rounded-lg shadow-lg overflow-hidden">
                        <template x-for="(r, i) in results" :key="i">
                            <a :href="r.url" x-html="r.label"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gold/10 transition-colors border-b border-gray-100 last:border-b-0"
                                :class="{ 'bg-gold/10': i === selected }"></a>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Status --}}
            <div>
                <select name="status" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('app.status_active') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('app.status_pending') }}</option>
                    <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>{{ __('app.status_overdue') }}</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>{{ __('app.status_closed') }}</option>
                    <option value="won" {{ request('status') === 'won' ? 'selected' : '' }}>{{ __('app.status_won') }}</option>
                    <option value="lost" {{ request('status') === 'lost' ? 'selected' : '' }}>{{ __('app.status_lost') }}</option>
                    <option value="adjudicated" {{ request('status') === 'adjudicated' ? 'selected' : '' }}>{{ __('app.status_adjudicated') }}</option>
                    <option value="fees_pending" {{ request('status') === 'fees_pending' ? 'selected' : '' }}>{{ __('app.status_fees_pending') }}</option>
                </select>
            </div>

            {{-- Priority --}}
            <div>
                <select name="priority" class="{{ $selCls }}">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>{{ __('app.priority_low') }}</option>
                    <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>{{ __('app.priority_medium') }}</option>
                    <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>{{ __('app.priority_high') }}</option>
                    <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>{{ __('app.priority_urgent') }}</option>
                </select>
            </div>

            {{-- Lawyer --}}
            <div>
                <select data-no-create name="lawyer_id" class="ts {{ $selCls }}">
                    <option value="">{{ __('app.all_lawyers') }}</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('lawyer_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Court --}}
            <div>
                <select name="court" class="{{ $selCls }}">
                    <option value="">{{ __('app.all_courts') }}</option>
                    @foreach($filterCourts as $court)
                        <option value="{{ $court }}" {{ request('court') === $court ? 'selected' : '' }}>{{ $court }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Type --}}
            <div>
                <select name="case_type" class="{{ $selCls }}">
                    <option value="">{{ __('app.all_types') }}</option>
                    @foreach($filterTypes as $type)
                        <option value="{{ $type }}" {{ request('case_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Date range --}}
            <div class="flex items-center gap-2">
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="{{ $selCls }}" title="{{ __('app.from_date') }}">
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="{{ $selCls }}" title="{{ __('app.to_date') }}">
            </div>

            {{-- Submit --}}
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                    {{ __('app.filter') }}
                </button>
            </div>
        </div>
    </x-filter-panel>

    {{-- §3: القضايا المنجزة خلف زرّها --}}
    <div class="flex justify-end">
        <a href="{{ request()->fullUrlWithQuery(['done' => ($done ?? false) ? null : 1, 'page' => null]) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold border transition {{ ($done ?? false) ? 'bg-gold/12 text-gold-dark border-gold/25' : 'bg-white text-gray-400 border-gray-200 hover:text-gray-600' }}">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ ($done ?? false) ? __('app.show_active') : __('app.done_cases_btn') . ' (' . ($doneCount ?? 0) . ')' }}
        </a>
    </div>


    {{-- الهاتف: بطاقات — جدول القضايا يبلغ ثلاثة أضعاف عرض الشاشة --}}
    <div class="md:hidden bg-white rounded-xl border border-gold/15 overflow-hidden">
        @forelse($cases ?? [] as $case)
            @php
                $csMap = [
                    'active' => 'bg-green-100 text-green-700 border-green-200',
                    'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                    'overdue' => 'bg-red-100 text-red-700 border-red-200',
                    'closed' => 'bg-gray-100 text-gray-500 border-gray-200',
                    'won' => 'bg-blue-100 text-blue-700 border-blue-200',
                    'lost' => 'bg-red-100 text-red-700 border-red-200',
                    'adjudicated' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                    'fees_pending' => 'bg-red-100 text-red-700 border-red-200',
                ];
                $cpMap = ['low' => 'bg-gray-100 text-gray-600', 'medium' => 'bg-blue-100 text-blue-700',
                          'high' => 'bg-orange-100 text-orange-700', 'urgent' => 'bg-red-100 text-red-700'];
            @endphp
            <x-list-card :url="route('cases.show', $case)"
                         :title="$case->client->name ?? $case->title"
                         :subtitle="$case->court . ($case->case_number ? ' — ' . $case->case_number : '')">
                <x-slot:badges>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-gold/12 text-gold-dark border border-gold/20" dir="ltr">{{ $case->office_case_number }}</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $csMap[$case->status] ?? 'bg-gray-100 text-gray-500 border-gray-200' }}">
                        {{ __('app.status_' . $case->status) }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $cpMap[$case->priority] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ __('app.priority_' . $case->priority) }}
                    </span>
                </x-slot:badges>
                <x-slot:meta>
                    <x-list-meta :label="__('app.case_opponent')">{{ $case->opponent ?? '—' }}</x-list-meta>
                    <x-list-meta :label="__('app.case_lawyer')">{{ $case->lawyer->name ?? '—' }}</x-list-meta>
                    <x-list-meta :label="__('app.case_type')">{{ $case->case_type ?? '—' }}</x-list-meta>
                    <x-list-meta :label="__('app.created_at')">{{ $case->created_at?->format('Y-m-d') ?? '—' }}</x-list-meta>
                </x-slot:meta>
            </x-list-card>
        @empty
            <x-empty-state :title="__('app.no_cases')" icon="cases"
                :action-url="route('cases.create')" :action-label="__('app.add_case_prompt')"
                :filtered="($activeFilters ?? 0) > 0" :clear-url="url()->current()" compact />
        @endforelse
    </div>

    {{-- Cases Table --}}
    <div class="hidden md:block bg-white rounded-xl border border-gold/15 overflow-hidden">
        <div class="overflow-x-auto md-scroll-x">
            <table class="w-full text-sm text-right">
                <thead>
                    <tr class="border-b border-gray-200">
                        @php
                            $sortableCols = ['number', 'court', 'client', 'opponent', 'type', 'lawyer', 'status', 'priority', 'created'];
                            $arrowCls = 'inline-flex items-center gap-1 font-bold whitespace-nowrap text-xs transition-colors';
                        @endphp
                        @foreach($sortableCols as $colKey)
                            @php
                                $isActive = $sort === $colKey;
                                $nextDir = ($isActive && $dir === 'asc') ? 'desc' : 'asc';
                                $label = [
                                    'number' => __('app.office_case_number'),
                                    'court' => __('app.case_court_with_number'),
                                    'client' => __('app.case_principal'),
                                    'opponent' => __('app.case_opponent'),
                                    'type' => __('app.case_type'),
                                    'lawyer' => __('app.case_lawyer'),
                                    'status' => __('app.status'),
                                    'priority' => __('app.priority'),
                                    'created' => __('app.created_at'),
                                ][$colKey];
                            @endphp
                            <th class="px-3 py-2 whitespace-nowrap text-xs">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => $colKey, 'dir' => $nextDir]) }}"
                                    class="{{ $arrowCls }} {{ $isActive ? 'text-gold-dark bg-gold/12/80 rounded-lg px-2 py-1' : 'text-gold-dark hover:text-gold-dark' }}">
                                    {{ $label }}
                                    @if($isActive)
                                        <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            @if($dir === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                            @endif
                                        </svg>
                                    @else
                                        <svg class="w-3 h-3 flex-shrink-0 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4M8 15l4 4 4-4"/>
                                        </svg>
                                    @endif
                                </a>
                            </th>
                        @endforeach
                        <th class="px-3 py-2 text-gold-dark font-bold whitespace-nowrap text-xs">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
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
                                    @elseif($case->status === 'adjudicated') bg-emerald-100 text-emerald-700 border border-emerald-200
                                    @elseif($case->status === 'fees_pending') bg-red-100 text-red-700 border border-red-200
                                    @else bg-gray-100 text-gray-400 border border-gray-200 @endif">
                                    @if($case->status === 'active') {{ __('app.status_active') }}
                                    @elseif($case->status === 'pending') {{ __('app.status_pending') }}
                                    @elseif($case->status === 'overdue') {{ __('app.status_overdue') }}
                                    @elseif($case->status === 'closed') {{ __('app.status_closed') }}
                                    @elseif($case->status === 'won') {{ __('app.status_won') }}
                                    @elseif($case->status === 'lost') {{ __('app.status_lost') }}
                                    @elseif($case->status === 'adjudicated') {{ __('app.status_adjudicated') }}
                                    @elseif($case->status === 'fees_pending') {{ __('app.status_fees_pending') }}
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
                            <td class="px-3 py-2 text-gray-400 text-xs whitespace-nowrap">{{ $case->created_at?->format('Y-m-d') }}</td>
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
                                        <button @click="send()" :disabled="sending" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition-colors disabled:opacity-50" title="{{ __('app.send_followup') }}">
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
                                    <a href="{{ route('cases.edit', $case->id) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-gold/12 text-gold-dark hover:bg-gold/15 transition-colors" title="{{ __('app.edit') }}">
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
                                            <div class="fixed inset-0 bg-black/45 backdrop-blur-sm" @click="open = false"></div>
                                            <div class="relative bg-white border border-red-300 rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
                                                <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-red-100 text-red-700 flex items-center justify-center">
                                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </div>
                                                <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('app.delete_case_title') }}</h3>
                                                <p class="text-sm text-gray-500 mb-6">{{ __('app.delete_case_body', ['case' => $case->case_number]) }}</p>
                                                <div class="flex gap-3 justify-center">
                                                    <button type="button" @click="document.getElementById('delete-case-{{ $case->id }}').submit()" class="bg-red-500 hover:bg-red-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.yes_delete') }}</button>
                                                    <button type="button" @click="open = false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">{{ __('app.cancel') }}</button>
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
                            <td colspan="10" class="p-0">
                                <x-empty-state
                                    :title="__('app.no_cases')"
                                    
                                    icon="cases"
                                    :action-url="route('cases.create')"
                                    :action-label="__('app.add_case_prompt')"
                                    :filtered="($activeFilters ?? 0) > 0"
                                    :clear-url="url()->current()" />
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
