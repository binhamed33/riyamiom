@php
    $officeName = \App\Support\OfficeBrand::name();
    $isRtl = app()->getLocale() === 'ar';
    $logo = \App\Support\OfficeBrand::logoUrl();
    $note = \App\Models\Setting::get('maintenance_note');
    $isStaff = auth()->check() && (auth()->user()->isDeveloper() || auth()->user()->isAdmin());
@endphp
<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('app.maintenance_title') }} — {{ $officeName }}</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #F6F3EC; --ink: #141922; --muted: #5C6675; --gold: #C9A227; --gold-dark: #8C6A12; --line: rgba(20,25,34,0.10); }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { background:
                radial-gradient(120% 90% at 78% 8%, rgba(201,162,39,0.10) 0%, transparent 45%),
                linear-gradient(165deg, #FBF9F4 0%, #F4F0E7 55%, #EFEADF 100%);
            color: var(--ink); font-family: 'Tajawal', 'Cairo', sans-serif;
            display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { width: 100%; max-width: 34rem; text-align: center;
            background: rgba(255,255,255,0.86); backdrop-filter: blur(14px);
            border: 1px solid var(--line); border-radius: 26px; padding: 3rem 2rem;
            box-shadow: 0 30px 70px rgba(46,38,18,0.13); }
        .mark { width: 74px; height: 74px; margin: 0 auto 1.5rem; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; overflow: hidden;
            border: 1px solid rgba(201,162,39,0.35); background: #FFFFFF;
            box-shadow: 0 0 40px rgba(201,162,39,0.16); }
        .mark img { width: 100%; height: 100%; object-fit: cover; }
        h1 { font-family: 'Cairo', sans-serif; font-size: 1.65rem; margin: 0 0 0.75rem; line-height: 1.5; }
        p { color: var(--muted); line-height: 1.9; margin: 0 0 0.5rem; font-size: 0.95rem; }
        .note { margin-top: 1.5rem; padding: 1rem 1.25rem; border-radius: 14px;
            background: rgba(201,162,39,0.08); border: 1px solid rgba(201,162,39,0.25);
            color: var(--gold-dark); font-weight: 600; font-size: 0.9rem; line-height: 1.8; }
        /* شريط غير محدد المدة: يوحي بعمل جارٍ بلا ادّعاء وقت انتهاء */
        .bar { position: relative; height: 4px; border-radius: 999px; margin: 2rem 0 1.5rem;
            background: rgba(20,25,34,0.08); overflow: hidden; }
        .bar span { position: absolute; inset-block: 0; width: 38%; border-radius: 999px;
            background: linear-gradient(90deg, var(--gold-dark), var(--gold), #E3C463);
            animation: slide 1.9s cubic-bezier(0.65,0,0.35,1) infinite; }
        @keyframes slide { 0% { inset-inline-start: -40%; } 100% { inset-inline-start: 100%; } }
        @media (prefers-reduced-motion: reduce) { .bar span { animation: none; inset-inline-start: 0; width: 100%; opacity: 0.5; } }
        .foot { margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid var(--line);
            font-size: 0.75rem; color: rgba(92,102,117,0.85); }
        .foot a { color: var(--gold-dark); font-weight: 700; text-decoration: none; }
        .foot a:hover { text-decoration: underline; }
        .staff { margin-top: 1.25rem; }
        .staff a { display: inline-flex; align-items: center; gap: 0.5rem; min-height: 44px;
            padding: 0 1.25rem; border-radius: 12px; font-weight: 700; font-size: 0.85rem;
            background: var(--gold-dark); color: #FFFFFF; text-decoration: none; }
        .logout { background: none; border: 1px solid var(--line); color: var(--muted);
            font: inherit; font-size: 0.8rem; font-weight: 600; min-height: 44px; padding: 0 1rem;
            border-radius: 12px; cursor: pointer; margin-top: 0.75rem; }
    </style>
</head>
<body>
    <main class="card">
        <div class="mark">
            @if($logo)
                <img src="{{ $logo }}" alt="{{ $officeName }}">
            @else
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2a10 10 0 100 20 10 10 0 000-20zm0 2.6L9.4 8.7 5 9.4l3.2 3.1L7.6 17 12 14.7 16.4 17l-.6-4.5L19 9.4l-4.4-.7L12 4.6z" fill="#C9A227"/>
                </svg>
            @endif
        </div>

        <h1>{{ __('app.maintenance_title') }}</h1>
        <p>{{ __('app.maintenance_body') }}</p>
        <p>{{ __('app.maintenance_data_safe') }}</p>

        <div class="bar" role="status" aria-live="polite" aria-label="{{ __('app.maintenance_title') }}"><span></span></div>

        @if($note)
            <div class="note">{{ $note }}</div>
        @endif

        @if($isStaff)
            {{-- لوحة المطوّر تبقى متاحة أثناء الصيانة --}}
            <div class="staff">
                <a href="{{ auth()->user()->isDeveloper() ? route('developer.index') : route('settings.index') }}">
                    {{ __('app.maintenance_staff_access') }}
                </a>
            </div>
        @endif

        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout">{{ __('app.logout') }}</button>
            </form>
        @endauth

        <div class="foot">
            {{ $officeName }} · <a href="https://dev.riyami.om/" target="_blank" rel="noopener">مُداوَلة</a>
        </div>
    </main>
</body>
</html>
