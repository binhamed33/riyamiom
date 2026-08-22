@extends('client-portal.layout')
@section('title', __('portal.nav.home'))

@push('styles')
<style>
    .hm-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; margin: 1.3rem 0; }
    .hm-stat { padding: .95rem .8rem; text-align: center; }
    .hm-stat b { display: block; font-size: 1.55rem; font-weight: 700; line-height: 1.2; font-variant-numeric: tabular-nums; }
    .hm-stat span { font-size: .68rem; color: var(--fg-3); font-weight: 600; }

    /* الجلسة القادمة: أبرز ما في الصفحة — فائدة فورية بمجرد الدخول */
    .hm-next { padding: 1.4rem; display: flex; gap: 1.2rem; align-items: center; position: relative; overflow: hidden; }
    .hm-next::after {
        content: ''; position: absolute; inset-block: 0; inset-inline-start: 0; width: 3px;
        background: linear-gradient(var(--gold-2), var(--gold));
    }
    .hm-date {
        flex: none; width: 74px; text-align: center; padding: .6rem .3rem; border-radius: 14px;
        background: var(--gold-soft); border: 1px solid color-mix(in srgb, var(--gold) 24%, transparent);
    }
    .hm-day { display: block; font-size: 1.7rem; font-weight: 700; line-height: 1; color: var(--gold); font-variant-numeric: tabular-nums; }
    .hm-month { display: block; font-size: .68rem; font-weight: 600; color: var(--gold); margin-top: .2rem; }
    .hm-next-body { min-width: 0; }
    .hm-next-title { font-weight: 700; font-size: .95rem; margin: 0 0 .25rem; }
    .hm-next-meta { font-size: .78rem; color: var(--fg-3); margin: 0; }

    .hm-list { display: grid; gap: .6rem; }
    .hm-row {
        display: flex; align-items: center; gap: .8rem; padding: .95rem 1.1rem;
        transition: transform .18s cubic-bezier(.2,.7,.3,1), border-color .18s;
    }
    .hm-row:hover { transform: translateY(-2px); border-color: var(--line-2); }
    .hm-row-body { min-width: 0; flex: 1; }
    .hm-row-title { font-weight: 600; font-size: .88rem; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .hm-row-meta { font-size: .72rem; color: var(--fg-3); margin: .15rem 0 0; }
    .hm-chev { flex: none; color: var(--fg-3); }
    .hm-chev svg { width: 16px; height: 16px; }
    [dir="rtl"] .hm-chev svg { transform: scaleX(-1); }
</style>
@endpush

@section('content')
<div class="p-in">
    <h1 class="p-h1">{{ __('portal.home.greeting', ['name' => $client->name]) }}</h1>
    <p class="p-lede">{{ __('portal.home.lede') }}</p>
</div>

<div class="hm-stats p-in p-in-1">
    <div class="p-card hm-stat"><b>{{ $summary['total'] }}</b><span>{{ __('portal.home.total_cases') }}</span></div>
    <div class="p-card hm-stat"><b>{{ $summary['active'] }}</b><span>{{ __('portal.home.active_cases') }}</span></div>
    <div class="p-card hm-stat"><b>{{ $summary['upcoming_sessions'] }}</b><span>{{ __('portal.home.upcoming') }}</span></div>
</div>

@if ($nextSession && $nextSession->date)
    @php
        $when = \Illuminate\Support\Carbon::parse($nextSession->date);
        $days = (int) now()->startOfDay()->diffInDays($when->copy()->startOfDay(), false);
    @endphp
    <section class="p-in p-in-2" style="margin-bottom:1.2rem">
        <h2 class="p-h2">{{ __('portal.home.next_session') }}</h2>
        <a href="{{ route('client.portal.case', $nextSession->case_id) }}" class="p-card hm-next">
            <div class="hm-date">
                <span class="hm-day">{{ $when->format('d') }}</span>
                <span class="hm-month">{{ $when->translatedFormat('F') }}</span>
            </div>
            <div class="hm-next-body">
                <p class="hm-next-title">{{ $nextSession->case?->title ?? __('portal.sessions.title') }}</p>
                <p class="hm-next-meta">
                    {{ $when->translatedFormat('l') }} · {{ $when->format('h:i A') }}
                    @if ($nextSession->location) <br>{{ $nextSession->location }} @endif
                </p>
                <p style="margin:.5rem 0 0">
                    <span class="p-badge {{ $days <= 3 ? 'warn' : 'info' }}">
                        @if ($days <= 0) {{ __('portal.home.remaining_today') }}
                        @elseif ($days === 1) {{ __('portal.home.remaining_tomorrow') }}
                        @else {{ __('portal.home.remaining_days', ['count' => $days]) }}
                        @endif
                    </span>
                </p>
            </div>
        </a>
    </section>
@elseif (\App\Support\ClientPortal::showsSessions() && $summary['total'] > 0)
    <section class="p-card p-in p-in-2" style="margin-bottom:1.2rem">
        <div class="p-empty" style="padding:2rem 1.2rem">
            <p>{{ __('portal.empty.sessions') }}</p>
        </div>
    </section>
@endif

<section class="p-in p-in-2">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
        <h2 class="p-h2">{{ __('portal.home.recent') }}</h2>
        @if ($summary['total'] > 0)
            <a href="{{ route('client.portal.cases') }}" style="font-size:.74rem;color:var(--gold);font-weight:600">{{ __('portal.home.view_all') }} ←</a>
        @endif
    </div>

    @forelse ($recent as $case)
        <div class="hm-list" style="margin-bottom:.6rem">
            <a href="{{ route('client.portal.case', $case->id) }}" class="p-card hm-row">
                <div class="hm-row-body">
                    <p class="hm-row-title">{{ $case->title }}</p>
                    <p class="hm-row-meta">
                        @if ($case->case_number) <span dir="ltr">{{ $case->case_number }}</span> · @endif
                        {{ __('portal.cases.last_update') }} {{ $case->updated_at?->diffForHumans() }}
                    </p>
                </div>
                @include('client-portal.partials.status', ['status' => $case->status])
                <span class="hm-chev" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
                </span>
            </a>
        </div>
    @empty
        <div class="p-card">
            <div class="p-empty">
                <span class="p-empty-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3h9l5 5v13a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1zM9 12h6M9 16h6"/></svg>
                </span>
                <p>{{ __('portal.empty.cases') }}</p>
                <small>{{ __('portal.empty.cases_hint') }}</small>
            </div>
        </div>
    @endforelse
</section>

@include('client-portal.partials.contact')
@endsection
