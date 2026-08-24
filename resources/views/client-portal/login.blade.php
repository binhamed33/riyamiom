@extends('client-portal.layout')
@section('title', __('portal.login.welcome'))

@php
    $step = $challenge ? 2 : 1;
    $welcome = \App\Support\ClientPortal::welcome();
@endphp

@push('styles')
<style>
    .lg-grid { display: grid; gap: 1.6rem; align-items: center; }
    @media (min-width: 900px) { .lg-grid { grid-template-columns: 1.05fr .95fr; gap: 3.5rem; min-height: 62vh; } }

    /* الجانب البصري: خطوط هندسية مجرّدة تشير إلى مسار قضية — لا صور جاهزة */
    .lg-art { display: none; position: relative; }
    @media (min-width: 900px) { .lg-art { display: block; } }
    .lg-art-in {
        position: relative; border-radius: 26px; overflow: hidden; padding: 2.6rem;
        background: var(--surface); border: 1px solid var(--line); box-shadow: var(--shadow);
    }
    .lg-art-in::before {
        content: ''; position: absolute; inset: 0; opacity: .5;
        background-image:
            linear-gradient(var(--line) 1px, transparent 1px),
            linear-gradient(90deg, var(--line) 1px, transparent 1px);
        background-size: 34px 34px;
        mask-image: radial-gradient(circle at 30% 20%, #000 10%, transparent 72%);
    }
    .lg-art h2 { position: relative; font-size: 1.8rem; font-weight: 700; margin: 0 0 .6rem; letter-spacing: -.02em; }
    .lg-art p { position: relative; color: var(--fg-3); margin: 0; max-width: 34ch; font-size: .9rem; }

    .lg-track { position: relative; margin-top: 2.6rem; display: grid; gap: 1.05rem; }
    .lg-node { display: flex; align-items: center; gap: .85rem; font-size: .82rem; color: var(--fg-2); }
    .lg-dot {
        width: 11px; height: 11px; border-radius: 50%; flex: none; position: relative;
        background: var(--gold-soft); border: 2px solid var(--gold);
    }
    .lg-node:not(:last-child) .lg-dot::after {
        content: ''; position: absolute; inset-inline-start: 50%; top: 13px;
        width: 1px; height: 26px; background: var(--line-2); transform: translateX(-50%);
    }
    .lg-node span { animation: pRise .5s cubic-bezier(.16,1,.3,1) both; }
    .lg-node:nth-child(1) span { animation-delay: .18s; }
    .lg-node:nth-child(2) span { animation-delay: .28s; }
    .lg-node:nth-child(3) span { animation-delay: .38s; }
    .lg-node:nth-child(4) span { animation-delay: .48s; }

    .lg-panel { padding: 1.8rem 1.4rem 1.6rem; }
    @media (min-width: 560px) { .lg-panel { padding: 2.4rem 2.2rem 2rem; } }

    .lg-steps { display: flex; align-items: center; gap: .5rem; margin-bottom: 1.4rem; }
    .lg-pip { height: 3px; border-radius: 2px; background: var(--line-2); flex: 1; transition: background .35s; }
    .lg-pip.is-on { background: var(--gold); }
    .lg-step-label { font-size: .68rem; color: var(--fg-3); font-weight: 600; white-space: nowrap; }

    /* حقول آخر ٣ أرقام */
    .lg-digits { display: flex; gap: .6rem; direction: ltr; justify-content: center; margin: .3rem 0 .2rem; }
    .lg-digit {
        width: 60px; height: 68px; text-align: center; font-size: 1.6rem; font-weight: 700;
        border-radius: 15px; background: var(--surface-2); border: 1px solid var(--line);
        color: var(--fg); transition: border-color .18s, box-shadow .18s, transform .18s;
    }
    .lg-digit:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 4px var(--gold-soft); transform: translateY(-2px); }
    .lg-digit.is-bad { border-color: var(--bad); animation: lgShake .32s; }
    @keyframes lgShake { 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }

    .lg-spin { width: 15px; height: 15px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: lgSpin .7s linear infinite; }
    @keyframes lgSpin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { .lg-digit.is-bad { animation: none; } }
</style>
@endpush

@section('content')
<div class="lg-grid">
    <section class="lg-art" aria-hidden="true">
        <div class="lg-art-in">
            <h2>{{ __('portal.login.tagline') }}</h2>
            <p>{{ __('portal.login.lede') }}</p>

            <div class="lg-track">
                <div class="lg-node"><span class="lg-dot"></span><span>{{ __('portal.timeline.opened') }}</span></div>
                <div class="lg-node"><span class="lg-dot"></span><span>{{ __('portal.timeline.session_upcoming') }}</span></div>
                <div class="lg-node"><span class="lg-dot"></span><span>{{ __('portal.documents.title') }}</span></div>
                <div class="lg-node"><span class="lg-dot"></span><span>{{ __('portal.timeline.last_update') }}</span></div>
            </div>
        </div>
    </section>

    <section class="p-card lg-panel p-in">
        <div class="lg-steps">
            <span class="lg-pip is-on"></span>
            <span class="lg-pip @if($step === 2) is-on @endif"></span>
            <span class="lg-step-label">{{ __('portal.login.step_of', ['current' => $step, 'total' => 2]) }}</span>
        </div>

        @if ($step === 1)
            <h1 class="p-h1">{{ __('portal.login.welcome') }}</h1>
            <p class="p-lede" style="margin-bottom:1.5rem">{{ $welcome ?? __('portal.login.intro') }}</p>

            <form method="POST" action="{{ route('client.access.lookup') }}" data-portal-form novalidate>
                @csrf
                <label class="p-label" for="nid">{{ __('portal.login.national_id') }}</label>
                <input id="nid" name="national_id" type="text" inputmode="numeric" autocomplete="off"
                       class="p-field" required autofocus maxlength="40"
                       value="{{ old('national_id') }}" dir="ltr" style="text-align:start">
                <p class="p-hint">{{ __('portal.login.national_id_hint') }}</p>

                <button class="p-btn" style="width:100%;margin-top:1.3rem" data-submit>
                    <span data-label>{{ __('portal.login.continue') }}</span>
                </button>
            </form>
        @else
            <h1 class="p-h1">{{ __('portal.login.verify_title') }}</h1>
            <p class="p-lede">{{ __('portal.login.verify_intro') }}</p>

            @if (!empty($challenge['hint']))
                <p class="p-hint" style="margin-top:.7rem">
                    <span class="p-badge mute" dir="ltr"
                          aria-label="{{ __(($challenge['count'] ?? 1) > 1 ? 'portal.login.verify_hint_many' : 'portal.login.verify_hint', ['digits' => $challenge['hint']]) }}">{{ $challenge['hint'] }}</span>
                </p>
            @endif

            <form method="POST" action="{{ route('client.access.verify') }}" data-portal-form data-digits novalidate style="margin-top:1.5rem">
                @csrf
                <label class="p-label" for="d1" style="text-align:center">{{ __('portal.login.digits_label') }}</label>

                <div class="lg-digits">
                    <input id="d1" class="lg-digit" type="text" inputmode="numeric" autocomplete="one-time-code"
                           maxlength="1" pattern="[0-9]" aria-label="1" autofocus>
                    <input id="d2" class="lg-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" aria-label="2">
                    <input id="d3" class="lg-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" aria-label="3">
                </div>

                <input type="hidden" name="digits" data-digits-value>

                <button class="p-btn" style="width:100%;margin-top:1.3rem" data-submit disabled>
                    <span data-label>{{ __('portal.login.verify_action') }}</span>
                </button>
            </form>

            <form method="POST" action="{{ route('client.access.logout') }}" style="margin-top:.7rem">
                @csrf
                <button class="p-btn p-btn-ghost" style="width:100%">{{ __('portal.login.back') }}</button>
            </form>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    // ---- حالة الإرسال: قفل ضد النقر المزدوج ومؤشّر واضح
    document.querySelectorAll('[data-portal-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('[data-submit]');
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            var label = btn.querySelector('[data-label]');
            if (label) label.innerHTML = '<span class="lg-spin"></span>';
        });
    });

    // ---- حقول الأرقام الثلاثة
    var form = document.querySelector('[data-digits]');
    if (!form) return;

    var boxes = Array.prototype.slice.call(form.querySelectorAll('.lg-digit'));
    var hidden = form.querySelector('[data-digits-value]');
    var submit = form.querySelector('[data-submit]');

    function value() { return boxes.map(function (b) { return b.value; }).join(''); }

    function sync() {
        var v = value();
        hidden.value = v;
        submit.disabled = v.length !== 3;
    }

    function fill(text) {
        var digits = (text || '').replace(/\D/g, '').slice(0, 3).split('');
        boxes.forEach(function (b, i) { b.value = digits[i] || ''; });
        sync();
        var next = boxes[Math.min(digits.length, 2)];
        if (next) next.focus();
    }

    boxes.forEach(function (box, i) {
        box.addEventListener('input', function () {
            box.value = box.value.replace(/\D/g, '').slice(0, 1);
            box.classList.remove('is-bad');
            if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
            sync();
            // اكتمل الإدخال: نُرسل من غير ضغطة ثانية
            if (value().length === 3) form.requestSubmit();
        });

        box.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !box.value && i > 0) { boxes[i - 1].focus(); }
            if (e.key === 'ArrowLeft' && i > 0) { boxes[i - 1].focus(); }
            if (e.key === 'ArrowRight' && i < boxes.length - 1) { boxes[i + 1].focus(); }
        });

        box.addEventListener('paste', function (e) {
            e.preventDefault();
            fill((e.clipboardData || window.clipboardData).getData('text'));
        });

        box.addEventListener('focus', function () { box.select(); });
    });

    // بعد إخفاق التحقق تُمسح الخانات ويعود التركيز — لا يُعاد الإدخال يدوياً
    @if (session('portal_error'))
        boxes.forEach(function (b) { b.value = ''; b.classList.add('is-bad'); });
        setTimeout(function () { boxes[0].focus(); }, 60);
        sync();
    @endif
})();
</script>
@endpush
