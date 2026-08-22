@php
    $isRtl = app()->getLocale() === 'ar';
    $officeName = \App\Support\OfficeBrand::name();
    $officeLogo = \App\Support\OfficeBrand::logoUrl();
    $contact = \App\Support\ClientPortal::contact();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- بوابة خاصة بالعميل: لا تُفهرس --}}
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('portal.portal')) — {{ $officeName }}</title>

    <meta name="theme-color" content="#0b0d10" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f7f5f1" media="(prefers-color-scheme: light)">

    @if ($officeLogo)
        <link rel="icon" href="{{ $officeLogo }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        // يُطبَّق قبل أول رسم فلا تومض الصفحة
        (function () {
            try {
                var t = localStorage.getItem('mdp_theme');
                if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            } catch (e) {}
        })();
    </script>

    <style>
        :root {
            --bg: #f7f5f1;
            --surface: #ffffff;
            --surface-2: #f2efe9;
            --line: rgba(24, 26, 30, .10);
            --line-2: rgba(24, 26, 30, .18);
            --fg: #16181c;
            --fg-2: #4a4f57;
            --fg-3: #7d838d;
            --gold: #9c7734;
            --gold-2: #c9a25a;
            --gold-soft: rgba(156, 119, 52, .10);
            --ok: #2e7d5b;
            --warn: #a8761b;
            --bad: #b4472f;
            --info: #37627e;
            --shadow: 0 30px 70px -50px rgba(20, 22, 26, .45), 0 2px 6px -3px rgba(20, 22, 26, .10);
            --r: 18px;
            color-scheme: light;
        }

        :root[data-theme="dark"] {
            --bg: #0b0d10;
            --surface: #12151a;
            --surface-2: #171b21;
            --line: rgba(255, 255, 255, .09);
            --line-2: rgba(255, 255, 255, .16);
            --fg: #f0ede7;
            --fg-2: #b6bbc3;
            --fg-3: #838995;
            --gold: #d9b472;
            --gold-2: #eed7a6;
            --gold-soft: rgba(217, 180, 114, .12);
            --ok: #6fbf95;
            --warn: #d9a84e;
            --bad: #e07a63;
            --info: #7fa8c2;
            --shadow: 0 30px 70px -50px rgba(0, 0, 0, .8), 0 2px 6px -3px rgba(0, 0, 0, .5);
            color-scheme: dark;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--fg);
            font-family: 'IBM Plex Sans Arabic', system-ui, -apple-system, sans-serif;
            font-size: 15px;
            line-height: 1.75;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            padding-block-end: env(safe-area-inset-bottom);
        }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; }
        [hidden] { display: none !important; }

        .p-wrap { max-width: 980px; margin: 0 auto; padding: 0 1.05rem; }

        /* ---- الرأس ---- */
        .p-top {
            position: sticky; top: 0; z-index: 40;
            background: color-mix(in srgb, var(--bg) 88%, transparent);
            backdrop-filter: blur(14px);
            border-block-end: 1px solid var(--line);
        }
        .p-top-in { display: flex; align-items: center; gap: .8rem; min-height: 62px; }
        .p-office { display: flex; align-items: center; gap: .6rem; min-width: 0; }
        .p-logo { width: 34px; height: 34px; border-radius: 9px; object-fit: cover; flex: none; }
        .p-mark {
            width: 34px; height: 34px; border-radius: 9px; flex: none;
            display: grid; place-items: center; font-weight: 700; font-size: .85rem;
            background: linear-gradient(140deg, var(--gold-2), var(--gold)); color: #17130a;
        }
        .p-office-name { font-weight: 700; font-size: .9rem; line-height: 1.3; }
        .p-office-sub { font-size: .68rem; color: var(--fg-3); line-height: 1.3; }
        .p-top-actions { margin-inline-start: auto; display: flex; align-items: center; gap: .35rem; }
        .p-icon-btn {
            width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--line);
            background: transparent; color: var(--fg-2); cursor: pointer;
            display: grid; place-items: center; transition: border-color .18s, color .18s;
        }
        .p-icon-btn:hover { border-color: var(--line-2); color: var(--fg); }
        .p-icon-btn svg { width: 17px; height: 17px; }

        /* ---- التنقّل ---- */
        .p-nav { display: none; gap: .25rem; margin-inline-start: 1rem; }
        .p-nav a {
            padding: .45rem .85rem; border-radius: 999px; font-size: .82rem; font-weight: 600;
            color: var(--fg-3); transition: background .18s, color .18s;
        }
        .p-nav a:hover { color: var(--fg); }
        .p-nav a.is-on { background: var(--gold-soft); color: var(--gold); }

        .p-tabbar {
            position: fixed; inset-inline: 0; bottom: 0; z-index: 45;
            display: grid; grid-template-columns: repeat(3, 1fr);
            background: color-mix(in srgb, var(--surface) 94%, transparent);
            backdrop-filter: blur(14px);
            border-block-start: 1px solid var(--line);
            padding-block-end: env(safe-area-inset-bottom);
        }
        .p-tab {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: .2rem; min-height: 58px; font-size: .66rem; font-weight: 600; color: var(--fg-3);
        }
        .p-tab svg { width: 20px; height: 20px; }
        .p-tab.is-on { color: var(--gold); }

        main { padding-block: 1.4rem 5.6rem; }
        @media (min-width: 760px) {
            .p-nav { display: flex; }
            .p-tabbar { display: none; }
            main { padding-block: 2.2rem 3rem; }
            body { font-size: 15.5px; }
        }

        /* ---- عناصر ---- */
        .p-card {
            background: var(--surface); border: 1px solid var(--line);
            border-radius: var(--r); box-shadow: var(--shadow);
        }
        .p-h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 .25rem; letter-spacing: -.01em; }
        .p-lede { color: var(--fg-3); margin: 0; font-size: .88rem; }
        .p-h2 { font-size: .74rem; font-weight: 700; color: var(--gold); letter-spacing: .07em; margin: 0 0 .9rem; }

        .p-badge {
            display: inline-flex; align-items: center; gap: .3rem; padding: .22rem .6rem;
            border-radius: 999px; font-size: .68rem; font-weight: 700; white-space: nowrap;
            border: 1px solid transparent;
        }
        .p-badge.ok { background: color-mix(in srgb, var(--ok) 13%, transparent); color: var(--ok); }
        .p-badge.warn { background: color-mix(in srgb, var(--warn) 14%, transparent); color: var(--warn); }
        .p-badge.info { background: color-mix(in srgb, var(--info) 14%, transparent); color: var(--info); }
        .p-badge.mute { background: var(--surface-2); color: var(--fg-3); }

        .p-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
            min-height: 46px; padding: 0 1.2rem; border-radius: 12px; border: 1px solid transparent;
            font-weight: 700; font-size: .87rem; cursor: pointer;
            background: var(--gold); color: #fff;
            transition: transform .16s cubic-bezier(.2,.7,.3,1), opacity .16s;
        }
        .p-btn:hover { transform: translateY(-1px); }
        .p-btn:active { transform: translateY(0); }
        .p-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }
        .p-btn-ghost { background: transparent; border-color: var(--line-2); color: var(--fg); }

        .p-field {
            width: 100%; min-height: 52px; padding: 0 1rem; border-radius: 13px;
            background: var(--surface-2); border: 1px solid var(--line); color: var(--fg);
            font: inherit; font-size: 1rem; transition: border-color .18s, box-shadow .18s;
        }
        .p-field:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 4px var(--gold-soft); }
        .p-label { display: block; font-size: .78rem; font-weight: 600; color: var(--fg-2); margin-bottom: .4rem; }
        .p-hint { font-size: .72rem; color: var(--fg-3); margin: .45rem 0 0; }

        .p-alert {
            border-radius: 12px; padding: .8rem 1rem; font-size: .82rem; font-weight: 600;
            background: color-mix(in srgb, var(--bad) 10%, transparent);
            color: var(--bad); border: 1px solid color-mix(in srgb, var(--bad) 26%, transparent);
        }

        .p-empty { text-align: center; padding: 3rem 1.4rem; }
        .p-empty-mark {
            width: 54px; height: 54px; margin: 0 auto 1rem; border-radius: 16px;
            display: grid; place-items: center; background: var(--surface-2); color: var(--fg-3);
        }
        .p-empty-mark svg { width: 24px; height: 24px; }
        .p-empty p { margin: 0; color: var(--fg-2); font-weight: 600; }
        .p-empty small { display: block; margin-top: .4rem; color: var(--fg-3); font-weight: 400; }

        .p-foot { text-align: center; padding: 2rem 1rem 1rem; font-size: .7rem; color: var(--fg-3); }
        .p-foot a { color: var(--gold); font-weight: 600; }

        /* ---- حركة هادئة وهادفة ---- */
        @keyframes pRise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        .p-in { animation: pRise .4s cubic-bezier(.16,1,.3,1) both; }
        .p-in-1 { animation-delay: .04s; }
        .p-in-2 { animation-delay: .10s; }
        .p-in-3 { animation-delay: .16s; }

        :focus-visible { outline: 2px solid var(--gold); outline-offset: 3px; border-radius: 6px; }

        .p-skip {
            position: absolute; inset-inline-start: -9999px; top: .5rem;
            background: var(--surface); padding: .6rem 1rem; border-radius: 10px; z-index: 100;
        }
        .p-skip:focus { inset-inline-start: .8rem; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .001ms !important; transition-duration: .001ms !important; }
            .p-btn:hover { transform: none; }
        }
    </style>
    @stack('styles')
</head>
<body>
<a href="#main" class="p-skip">{{ __('portal.a11y.skip') }}</a>

@php $client = $client ?? null; @endphp

<header class="p-top">
    <div class="p-wrap p-top-in">
        <a href="{{ $client ? route('client.portal.home') : route('client.access') }}" class="p-office">
            @if ($officeLogo)
                <img src="{{ $officeLogo }}" alt="" class="p-logo">
            @else
                <span class="p-mark" aria-hidden="true">{{ mb_substr($officeName, 0, 1) }}</span>
            @endif
            <span style="min-width:0">
                <span class="p-office-name">{{ $officeName }}</span>
                <span class="p-office-sub">{{ __('portal.portal') }}</span>
            </span>
        </a>

        @if ($client)
            <nav class="p-nav" aria-label="{{ __('portal.a11y.menu') }}">
                <a href="{{ route('client.portal.home') }}" @class(['is-on' => request()->routeIs('client.portal.home')])>{{ __('portal.nav.home') }}</a>
                <a href="{{ route('client.portal.cases') }}" @class(['is-on' => request()->routeIs('client.portal.case*')])>{{ __('portal.nav.cases') }}</a>
                <a href="{{ route('client.portal.account') }}" @class(['is-on' => request()->routeIs('client.portal.account')])>{{ __('portal.nav.account') }}</a>
            </nav>
        @endif

        <div class="p-top-actions">
            <button type="button" class="p-icon-btn" data-theme-toggle aria-label="تبديل المظهر">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>
                </svg>
            </button>

            <a href="{{ route('language.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}"
               class="p-icon-btn" style="font-size:.66rem;font-weight:800"
               aria-label="{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}">
                {{ app()->getLocale() === 'ar' ? 'EN' : 'ع' }}
            </a>

            @if ($client)
                <form method="POST" action="{{ route('client.access.logout') }}">
                    @csrf
                    <button class="p-icon-btn" aria-label="{{ __('portal.nav.logout') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17l5-5-5-5M20 12H9M12 3H6a1 1 0 00-1 1v16a1 1 0 001 1h6"/>
                        </svg>
                    </button>
                </form>
            @endif
        </div>
    </div>
</header>

<main id="main" class="p-wrap">
    @if (session('portal_error'))
        <div class="p-alert p-in" role="alert" style="margin-bottom:1.2rem">{{ session('portal_error') }}</div>
    @endif

    @yield('content')
</main>

@if ($client)
    <nav class="p-tabbar" aria-label="{{ __('portal.a11y.menu') }}">
        <a href="{{ route('client.portal.home') }}" class="p-tab @if(request()->routeIs('client.portal.home')) is-on @endif">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11l9-8 9 8M5 9.5V20a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V9.5"/></svg>
            {{ __('portal.nav.home') }}
        </a>
        <a href="{{ route('client.portal.cases') }}" class="p-tab @if(request()->routeIs('client.portal.case*')) is-on @endif">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3h9l5 5v13a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1zM9 12h6M9 16h6"/></svg>
            {{ __('portal.nav.cases') }}
        </a>
        <a href="{{ route('client.portal.account') }}" class="p-tab @if(request()->routeIs('client.portal.account')) is-on @endif">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 19a4 4 0 00-8 0M12 11a3 3 0 100-6 3 3 0 000 6z"/></svg>
            {{ __('portal.nav.account') }}
        </a>
    </nav>
@endif

<footer class="p-foot">
    {{ $officeName }}
    <span style="opacity:.4">·</span>
    <a href="https://dev.riyami.om/" target="_blank" rel="noopener">{{ __('portal.powered_by', ['brand' => __('portal.brand')]) }}</a>
</footer>

<script>
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var root = document.documentElement;
            var dark = root.getAttribute('data-theme') === 'dark';
            if (dark) { root.removeAttribute('data-theme'); } else { root.setAttribute('data-theme', 'dark'); }
            try { localStorage.setItem('mdp_theme', dark ? 'light' : 'dark'); } catch (e) {}
        });
    });
</script>
@stack('scripts')
</body>
</html>
