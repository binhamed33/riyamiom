@extends('client-portal.layout')
@section('title', __('portal.login.welcome'))

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

    .lg-spin { width: 15px; height: 15px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: lgSpin .7s linear infinite; }
    @keyframes lgSpin { to { transform: rotate(360deg); } }
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
        @php
            // الدخولُ برمز واتساب وحدَه. الهاتفُ محفوظٌ في الجلسة بعد طلب الرمز —
            // فوجودُه يعني «أدخل الرمزَ الذي وصلك»، وغيابُه «اكتب رقمَك ليصلك الرمز».
            $otpPhone = session('portal_otp_phone');
            $otpWaitLeft = $otpPhone
                ? max(0, \App\Services\ClientPortal\PortalOtp::RESEND_SECONDS - (now()->timestamp - (int) session('portal_otp_at', 0)))
                : 0;
        @endphp

        <h1 class="p-h1">{{ __('portal.login.welcome') }}</h1>

        @if (session('portal_notice'))
            <p class="p-hint" style="margin-bottom:.7rem">{{ session('portal_notice') }}</p>
        @endif

        @if (!$otpPhone)
            <p class="p-lede" style="margin-bottom:1.5rem">أدخل رقم هاتفك المسجَّل لدى المكتب — يصلك رمزُ الدخول على واتساب.</p>

            <form method="POST" action="{{ route('client.access.otp') }}" data-portal-form novalidate>
                @csrf
                <label class="p-label" for="otp-phone">رقم الهاتف</label>
                <input id="otp-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                       class="p-field" required autofocus maxlength="20" dir="ltr" style="text-align:start"
                       placeholder="9xxxxxxx">
                <p class="p-hint">الرقم نفسُه المسجَّل في ملفّك لدى المكتب.</p>

                <button class="p-btn" style="width:100%;margin-top:1.3rem" data-submit>
                    <span data-label>أرسل الرمز</span>
                </button>
            </form>
        @else
            <p class="p-lede" style="margin-bottom:1rem">أدخل الرمزَ المكوَّن من ستّة أرقامٍ الذي وصلك على واتساب.</p>

            <form method="POST" action="{{ route('client.access.otp.verify') }}" data-portal-form novalidate>
                @csrf
                <label class="p-label" for="otp-code">رمز التحقّق</label>
                <input id="otp-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                       class="p-field" required autofocus maxlength="6" pattern="[0-9]{6}" dir="ltr"
                       style="text-align:center;letter-spacing:.5em;font-size:1.3rem">

                <button class="p-btn" style="width:100%;margin-top:1.3rem" data-submit>
                    <span data-label>دخول</span>
                </button>
            </form>

            <form method="POST" action="{{ route('client.access.otp') }}" style="margin-top:.7rem">
                @csrf
                <input type="hidden" name="phone" value="{{ $otpPhone }}">
                <button class="p-btn p-btn-ghost" style="width:100%" data-otp-resend
                        @if($otpWaitLeft > 0) disabled data-wait="{{ $otpWaitLeft }}" @endif>
                    <span data-resend-label>@if($otpWaitLeft > 0)رمزٌ جديد بعد {{ $otpWaitLeft }} ث@else أرسل رمزاً جديداً @endif</span>
                </button>
            </form>

            {{-- رقمٌ أُدخل خطأً كان يحبس صاحبَه أمام «رمزٍ» لن يصل --}}
            <form method="POST" action="{{ route('client.access.otp.reset') }}" style="margin-top:.4rem">
                @csrf
                <button class="p-hint" style="width:100%;background:none;border:0;cursor:pointer;text-align:center">
                    تغيير الرقم
                </button>
            </form>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    // ---- عدّادُ «رمزٌ جديد»: الزرُّ معطَّلٌ دقيقتين ويَعُدّ أمام العين —
    // بلا عدٍّ ظاهرٍ يظنّ الزائرُ العطبَ ويهجر الصفحة
    var resend = document.querySelector('[data-otp-resend][data-wait]');
    if (resend) {
        var left = parseInt(resend.dataset.wait, 10) || 0;
        var label = resend.querySelector('[data-resend-label]');
        var tick = setInterval(function () {
            left--;
            if (left <= 0) {
                clearInterval(tick);
                resend.disabled = false;
                if (label) label.textContent = 'أرسل رمزاً جديداً';
            } else if (label) {
                label.textContent = 'رمزٌ جديد بعد ' + left + ' ث';
            }
        }, 1000);
    }

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
})();
</script>
@endpush
