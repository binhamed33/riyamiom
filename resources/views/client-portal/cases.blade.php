@extends('client-portal.layout')
@section('title', __('portal.cases.title'))

@push('styles')
<style>
    .cs-grid { display: grid; gap: .75rem; }
    .cs-card { padding: 1.15rem 1.25rem; transition: transform .18s cubic-bezier(.2,.7,.3,1), border-color .18s; }
    .cs-card:hover { transform: translateY(-2px); border-color: var(--line-2); }
    .cs-head { display: flex; align-items: flex-start; gap: .8rem; margin-bottom: .7rem; }
    .cs-title { font-weight: 700; font-size: .98rem; margin: 0; line-height: 1.5; flex: 1; min-width: 0; }
    .cs-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .55rem .9rem; }
    .cs-meta dt { font-size: .66rem; color: var(--fg-3); font-weight: 600; margin: 0; }
    .cs-meta dd { font-size: .8rem; margin: .1rem 0 0; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cs-foot {
        display: flex; align-items: center; justify-content: space-between; gap: .8rem;
        margin-top: .9rem; padding-top: .8rem; border-top: 1px solid var(--line);
    }
    .cs-updated { font-size: .7rem; color: var(--fg-3); }
    .cs-go { font-size: .78rem; font-weight: 700; color: var(--gold); }
    @media (min-width: 700px) { .cs-meta { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
</style>
@endpush

@section('content')
<div class="p-in" style="margin-bottom:1.2rem">
    <h1 class="p-h1">{{ __('portal.cases.title') }}</h1>
    <p class="p-lede">{{ trans_choice('{0}|{1}:count قضية|[2,*]:count قضايا', $cases->total(), ['count' => $cases->total()]) }}</p>
</div>

@if ($cases->count())
    <div class="cs-grid">
        @foreach ($cases as $i => $case)
            @php $next = $case->next_date ? \Illuminate\Support\Carbon::parse($case->next_date) : null; @endphp
            <a href="{{ route('client.portal.case', $case->id) }}"
               class="p-card cs-card p-in" style="animation-delay:{{ min($i * 0.05, 0.3) }}s">
                <div class="cs-head">
                    <h2 class="cs-title">{{ $case->title }}</h2>
                    @include('client-portal.partials.status', ['status' => $case->status])
                </div>

                <dl class="cs-meta">
                    @if ($case->case_number)
                        <div><dt>{{ __('portal.cases.number') }}</dt><dd dir="ltr" style="text-align:start">{{ $case->case_number }}</dd></div>
                    @endif
                    @if ($case->court)
                        <div><dt>{{ __('portal.cases.court') }}</dt><dd>{{ $case->court }}</dd></div>
                    @endif
                    @if ($case->case_type ?? $case->type)
                        <div><dt>{{ __('portal.cases.type') }}</dt><dd>{{ $case->case_type ?? $case->type }}</dd></div>
                    @endif
                    @if ($next && \App\Support\ClientPortal::showsSessions())
                        <div><dt>{{ __('portal.cases.next_session') }}</dt><dd>{{ $next->format('Y-m-d') }}</dd></div>
                    @endif
                </dl>

                <div class="cs-foot">
                    <span class="cs-updated">{{ __('portal.cases.last_update') }} {{ $case->updated_at?->diffForHumans() }}</span>
                    <span class="cs-go">{{ __('portal.cases.view') }} ←</span>
                </div>
            </a>
        @endforeach
    </div>

    <div style="margin-top:1.4rem">{{ $cases->links() }}</div>
@else
    <div class="p-card p-in">
        <div class="p-empty">
            <span class="p-empty-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3h9l5 5v13a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1zM9 12h6M9 16h6"/></svg>
            </span>
            <p>{{ __('portal.empty.cases') }}</p>
            <small>{{ __('portal.empty.cases_hint') }}</small>
        </div>
    </div>
@endif

@include('client-portal.partials.contact')
@endsection
