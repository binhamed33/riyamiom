@extends('layouts.app')

@section('title', __('app.page_cases'))

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
                        <th class="px-3 py-2 text-amber-700 font-bold whitespace-nowrap text-xs">{{ __('app.case_number') }}</th>
                        <th class="px-3 py-2 text-amber-700 font-bold whitespace-nowrap text-xs">{{ __('app.court') }}</th>
                        <th class="px-3 py-2 text-amber-700 font-bold whitespace-nowrap text-xs">{{ __('app.case_client') }}</th>
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
                            <td class="px-3 py-2"><span class="font-mono font-medium text-gray-900 text-xs">{{ $case->case_number }}</span> <span class="text-gray-400 text-xs">「{{ $case->court }}」</span></td>
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
                                    @if($case->client?->phone)
                                    @php
                                        $waMsg = urlencode("يسر **شركة حمد الريامي للمحاماة (شركة مدنية للمحاماة)** أن تضع بين أيديكم خدمة **متابعة القضايا إلكترونياً**، وذلك حرصاً منا على تعزيز جودة الخدمات القانونية، وتوفير تجربة أكثر سهولة وشفافية لموكلينا الكرام.

يمكنكم الاطلاع على آخر مستجدات القضية، ومتابعة تفاصيلها بكل يسر، من خلال الدخول إلى الرابط التالي:

https://office.riyami.om/client-access

بعد فتح الرابط، يُرجى إدخال **رقم الهاتف** أو **البريد الإلكتروني** المسجل لدى المكتب، لتظهر لكم جميع تفاصيل القضية والمستجدات المتعلقة بها بشكل مباشر.

وفي حال واجهتكم أي صعوبة في الدخول أو كانت لديكم أي استفسارات، فإن فريقنا على أتم الاستعداد لخدمتكم والإجابة عن جميع استفساراتكم.

**شركة حمد الريامي للمحاماة (شركة مدنية للمحاماة)**
نعتز بثقتكم، ونسعى دائماً إلى تقديم خدمات قانونية احترافية بأعلى معايير الجودة.");
                                        $waPhone = ltrim($case->client->phone, '+');
                                    @endphp
                                    <a href="https://wa.me/{{ $waPhone }}?text={{ $waMsg }}" target="_blank" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition-colors" title="إرسال رابط المتابعة عبر واتساب">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                            <path d="M12 0C5.373 0 0 5.373 0 12c0 2.126.566 4.112 1.547 5.838L.043 23.626l6.015-1.828A11.943 11.943 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.6c-1.916 0-3.74-.496-5.328-1.37l-.382-.228-3.57 1.085 1.148-3.472-.248-.397A9.538 9.538 0 012.4 12c0-5.302 4.298-9.6 9.6-9.6s9.6 4.298 9.6 9.6-4.298 9.6-9.6 9.6z"/>
                                        </svg>
                                    </a>
                                    @endif
                                    <a href="{{ route('cases.edit', $case->id) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors" title="{{ __('app.edit') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form action="{{ route('cases.destroy', $case->id) }}" method="POST" class="contents" onsubmit="return confirm('{{ __("app.confirm_delete_case") }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors" title="{{ __('app.delete') }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
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
