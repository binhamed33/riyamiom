@extends('client-portal.layout')
@section('title', $case->title)

@push('styles')
<style>
    .cd-back { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; color: var(--fg-3); margin-bottom: .8rem; }
    .cd-back svg { width: 15px; height: 15px; }
    [dir="rtl"] .cd-back svg { transform: scaleX(-1); }

    .cd-hero { padding: 1.4rem; margin-bottom: 1rem; }
    .cd-title { font-size: 1.32rem; font-weight: 700; margin: 0 0 .5rem; line-height: 1.45; letter-spacing: -.01em; }
    .cd-sub { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-size: .76rem; color: var(--fg-3); }

    /* أقسام قابلة للطيّ — على الهاتف تُختصر الصفحة الطويلة */
    .cd-sec { margin-bottom: .75rem; overflow: hidden; }
    .cd-sum {
        display: flex; align-items: center; gap: .6rem; width: 100%;
        padding: 1.05rem 1.25rem; background: transparent; border: 0; cursor: pointer;
        color: var(--fg); text-align: start; font-weight: 700; font-size: .88rem;
    }
    .cd-sum-count { font-size: .68rem; color: var(--fg-3); font-weight: 600; }
    .cd-caret { margin-inline-start: auto; color: var(--fg-3); transition: transform .22s; }
    .cd-caret svg { width: 16px; height: 16px; display: block; }
    .cd-sum[aria-expanded="true"] .cd-caret { transform: rotate(180deg); }
    .cd-body { padding: 0 1.25rem 1.25rem; animation: pRise .28s cubic-bezier(.16,1,.3,1) both; }

    .cd-dl { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: .9rem 1rem; margin: 0; }
    @media (min-width: 640px) { .cd-dl { grid-template-columns: repeat(3, minmax(0,1fr)); } }
    .cd-dl dt { font-size: .66rem; color: var(--fg-3); font-weight: 600; margin: 0; }
    .cd-dl dd { font-size: .85rem; margin: .18rem 0 0; font-weight: 600; word-break: break-word; }

    .cd-item { display: flex; align-items: center; gap: .8rem; padding: .85rem 0; border-bottom: 1px solid var(--line); }
    .cd-item:last-child { border-bottom: 0; padding-bottom: 0; }
    .cd-item-body { flex: 1; min-width: 0; }
    .cd-item-title { font-size: .85rem; font-weight: 600; margin: 0; }
    .cd-item-meta { font-size: .72rem; color: var(--fg-3); margin: .12rem 0 0; }

    /* المسار الزمني */
    .cd-tl { position: relative; padding-inline-start: 1.4rem; }
    .cd-tl::before { content: ''; position: absolute; inset-block: .5rem; inset-inline-start: 4px; width: 1px; background: var(--line-2); }
    .cd-ev { position: relative; padding-block: .55rem; }
    .cd-ev::before {
        content: ''; position: absolute; inset-inline-start: -1.4rem; top: .95rem;
        width: 9px; height: 9px; border-radius: 50%; background: var(--surface);
        border: 2px solid var(--line-2);
    }
    .cd-ev.is-upcoming::before { border-color: var(--gold); background: var(--gold-soft); }
    .cd-ev.is-cancelled::before { border-color: var(--bad); }
    .cd-ev-title { font-size: .84rem; font-weight: 600; margin: 0; }
    .cd-ev-meta { font-size: .71rem; color: var(--fg-3); margin: .1rem 0 0; }

    .cd-doc-btn { flex: none; font-size: .74rem; font-weight: 700; color: var(--gold); padding: .35rem .7rem; border: 1px solid var(--line-2); border-radius: 9px; }
</style>
@endpush

@section('content')
<a href="{{ route('client.portal.cases') }}" class="cd-back">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg>
    {{ __('portal.cases.title') }}
</a>

<section class="p-card cd-hero p-in">
    <h1 class="cd-title">{{ $case->title }}</h1>
    <div class="cd-sub">
        @include('client-portal.partials.status', ['status' => $case->status])
        @if ($case->case_number)<span dir="ltr">{{ $case->case_number }}</span>@endif
        <span>·</span>
        <span>{{ __('portal.cases.last_update') }} {{ $case->updated_at?->diffForHumans() }}</span>
    </div>
</section>

{{-- معلومات القضية --}}
<section class="p-card cd-sec p-in p-in-1">
    <button type="button" class="cd-sum" data-acc aria-expanded="true" aria-controls="sec-info">
        {{ __('portal.cases.info') }}
        <span class="cd-caret" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg></span>
    </button>
    <div class="cd-body" id="sec-info">
        <dl class="cd-dl">
            @if ($case->case_number)<div><dt>{{ __('portal.cases.number') }}</dt><dd dir="ltr" style="text-align:start">{{ $case->case_number }}</dd></div>@endif
            @if ($case->case_type ?? $case->type)<div><dt>{{ __('portal.cases.type') }}</dt><dd>{{ $case->case_type ?? $case->type }}</dd></div>@endif
            @if ($case->court)<div><dt>{{ __('portal.cases.court') }}</dt><dd>{{ $case->court }}</dd></div>@endif
            <div><dt>{{ __('portal.cases.status') }}</dt><dd>{{ __('portal.status.' . $case->status) }}</dd></div>
            @if ($case->opened_at)<div><dt>{{ __('portal.cases.opened_at') }}</dt><dd>{{ \Illuminate\Support\Carbon::parse($case->opened_at)->format('Y-m-d') }}</dd></div>@endif
            @if (\App\Support\ClientPortal::showsLawyer() && $case->lawyer)
                <div><dt>{{ __('portal.cases.lawyer') }}</dt><dd>{{ $case->lawyer->name }}</dd></div>
            @endif
            @if (\App\Support\ClientPortal::showsOpponent() && $case->opponent)
                <div><dt>{{ __('portal.cases.opponent') }}</dt><dd>{{ $case->opponent }}</dd></div>
            @endif
        </dl>
    </div>
</section>

{{-- الجلسات --}}
@if (\App\Support\ClientPortal::showsSessions())
    <section class="p-card cd-sec p-in p-in-1">
        <button type="button" class="cd-sum" data-acc aria-expanded="true" aria-controls="sec-sessions">
            {{ __('portal.sessions.title') }}
            <span class="cd-sum-count">{{ $sessions->count() }}</span>
            <span class="cd-caret" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg></span>
        </button>
        <div class="cd-body" id="sec-sessions">
            @forelse ($sessions as $s)
                @php $when = $s->date ? \Illuminate\Support\Carbon::parse($s->date) : null; @endphp
                <div class="cd-item">
                    <div class="cd-item-body">
                        <p class="cd-item-title">{{ $when?->translatedFormat('l، j F Y') ?? '—' }}</p>
                        <p class="cd-item-meta">{{ $when?->format('h:i A') }}@if ($s->location) · {{ $s->location }}@endif</p>
                    </div>
                    <span class="p-badge {{ $s->status === 'completed' ? 'ok' : ($s->status === 'cancelled' ? 'mute' : ($s->status === 'postponed' ? 'warn' : 'info')) }}">
                        {{ __('portal.sessions.' . ($s->status ?: 'upcoming')) }}
                    </span>
                </div>
            @empty
                <p style="color:var(--fg-3);font-size:.82rem;margin:.4rem 0 0">{{ __('portal.empty.sessions') }}</p>
            @endforelse
        </div>
    </section>
@endif

{{-- المسار الزمني --}}
@if (\App\Support\ClientPortal::showsTimeline())
    <section class="p-card cd-sec p-in p-in-2">
        <button type="button" class="cd-sum" data-acc aria-expanded="false" aria-controls="sec-timeline">
            {{ __('portal.timeline.title') }}
            <span class="cd-sum-count">{{ $timeline->count() }}</span>
            <span class="cd-caret" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg></span>
        </button>
        <div class="cd-body" id="sec-timeline" hidden>
            @if ($timeline->count())
                <div class="cd-tl">
                    @foreach ($timeline as $ev)
                        <div class="cd-ev @if($ev['state'] === 'upcoming') is-upcoming @elseif($ev['state'] === 'cancelled') is-cancelled @endif">
                            <p class="cd-ev-title">{{ $ev['title'] }}</p>
                            <p class="cd-ev-meta">
                                {{ $ev['at']->translatedFormat('j F Y') }}
                                @if ($ev['detail']) · {{ $ev['detail'] }} @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @else
                <p style="color:var(--fg-3);font-size:.82rem;margin:.4rem 0 0">{{ __('portal.empty.timeline') }}</p>
            @endif
        </div>
    </section>
@endif

{{-- المستندات --}}
@if (\App\Support\ClientPortal::showsDocuments())
    <section class="p-card cd-sec p-in p-in-2">
        <button type="button" class="cd-sum" data-acc aria-expanded="false" aria-controls="sec-docs">
            {{ __('portal.documents.title') }}
            <span class="cd-sum-count">{{ $documents->count() }}</span>
            <span class="cd-caret" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg></span>
        </button>
        <div class="cd-body" id="sec-docs" hidden>
            @forelse ($documents as $doc)
                <div class="cd-item">
                    <div class="cd-item-body">
                        <p class="cd-item-title">{{ $doc->title }}</p>
                        <p class="cd-item-meta">
                            {{ __('portal.documents.added') }} {{ $doc->created_at?->format('Y-m-d') }}
                            @if ($doc->file_size) · {{ number_format($doc->file_size / 1024, 0) }} KB @endif
                        </p>
                    </div>
                    <a href="{{ route('client.portal.document', $doc->id) }}" class="cd-doc-btn">{{ __('portal.documents.download') }}</a>
                </div>
            @empty
                <p style="color:var(--fg-3);font-size:.82rem;margin:.4rem 0 0">{{ __('portal.empty.documents') }}</p>
            @endforelse
        </div>
    </section>
@endif

@include('client-portal.partials.contact')
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    document.querySelectorAll('[data-acc]').forEach(function (btn) {
        var panel = document.getElementById(btn.getAttribute('aria-controls'));
        if (!panel) return;
        btn.addEventListener('click', function () {
            var open = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', open ? 'false' : 'true');
            panel.hidden = open;
        });
    });
</script>
@endpush
