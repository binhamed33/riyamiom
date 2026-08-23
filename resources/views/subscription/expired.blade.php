@php
    $officeName = \App\Support\OfficeBrand::name();
    $logo = \App\Support\OfficeBrand::logoUrl();
    $supportEmail = \App\Models\Setting::get('office_email', 'admin@riyami.om');
    $info = app(\App\Services\SubscriptionService::class)->info();
    $endAt = $info['end_at'] ?? null;
    $suspended = ($info['key'] ?? null) === \App\Services\SubscriptionService::STATUS_SUSPENDED;
    $isRtl = app()->getLocale() === 'ar';
@endphp
{{-- صفحة انتهاء الاشتراك.

     كانت تبني شكلها كلّه على Tailwind من شبكة توزيع خارجية. وهذه
     بالذات هي الصفحة التي تُفتح حين لا يعمل شيء: فإن تأخّر ذلك
     المصدر أو حجبه مزوّد، انهارت إلى نصٍّ متراكم على سواد — وهو ما
     رآه صاحب النظام ووصفه بأنه قبيح.

     صارت مكتفيةً بنفسها: لا سكربت، ولا صنف من الخارج، ولا شيء
     يُنتظر تحميله. وعلى عائلة صفحة الصيانة نفسها، فالحالتان
     «النظام لا يعمل الآن» فليكونا وجهاً واحداً.

     وزرّ الواتساب أُزيل — القناة المعتمدة البريد وحده. --}}
<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $suspended ? 'الاشتراك متوقف' : 'انتهت صلاحية الاشتراك' }} — {{ $officeName }}</title>
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

        /* الطمأنة أولاً: أول ما يسأل عنه الموظّف هو مصير بيانات مكتبه */
        .safe { margin-top: 1.5rem; padding: 0.9rem 1.25rem; border-radius: 14px;
            background: rgba(201,162,39,0.08); border: 1px solid rgba(201,162,39,0.25);
            color: var(--gold-dark); font-weight: 600; font-size: 0.9rem; line-height: 1.8;
            display: flex; align-items: center; justify-content: center; gap: 0.6rem; }
        .safe svg { flex: none; }

        .facts { margin: 1.75rem 0 0; padding: 0; list-style: none;
            border-top: 1px solid var(--line); }
        .facts li { display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            padding: 0.8rem 0.25rem; border-bottom: 1px solid var(--line); font-size: 0.875rem; }
        .facts dt, .facts .k { color: var(--muted); font-weight: 500; }
        .facts .v { font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }

        .cta { display: inline-flex; align-items: center; justify-content: center; gap: 0.55rem;
            width: 100%; min-height: 48px; margin-top: 1.75rem; padding: 0 1.25rem;
            border-radius: 14px; font-weight: 700; font-size: 0.92rem; text-decoration: none;
            background: linear-gradient(135deg, var(--gold-dark), #A88222 55%, var(--gold));
            color: #FFFFFF; box-shadow: 0 12px 30px rgba(140,106,18,0.22);
            transition: transform 0.25s cubic-bezier(0.16,1,0.3,1), box-shadow 0.25s; }
        .cta:hover { transform: translateY(-2px); box-shadow: 0 18px 40px rgba(140,106,18,0.3); }
        .cta:focus-visible { outline: 2px solid var(--gold-dark); outline-offset: 3px; }

        .mail { display: block; margin-top: 0.85rem; font-size: 0.8rem; color: var(--muted); }
        .mail a { color: var(--gold-dark); font-weight: 700; text-decoration: none; }
        .mail a:hover { text-decoration: underline; }

        .logout { background: none; border: 1px solid var(--line); color: var(--muted);
            font: inherit; font-size: 0.8rem; font-weight: 600; min-height: 44px; padding: 0 1rem;
            border-radius: 12px; cursor: pointer; margin-top: 1.25rem; }
        .logout:hover { color: var(--ink); border-color: rgba(20,25,34,0.2); }

        .foot { margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid var(--line);
            font-size: 0.75rem; color: rgba(92,102,117,0.85); }
        .foot a { color: var(--gold-dark); font-weight: 700; text-decoration: none; }
        .foot a:hover { text-decoration: underline; }

        @media (max-width: 420px) {
            .card { padding: 2.25rem 1.35rem; border-radius: 20px; }
            h1 { font-size: 1.4rem; }
            .facts li { flex-direction: column; align-items: flex-start; gap: 0.2rem; }
        }
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

        <h1>{{ $suspended ? 'الاشتراك متوقف مؤقتاً' : 'انتهت صلاحية الاشتراك' }}</h1>

        <p>
            {{ $suspended
                ? 'أُوقف اشتراك ' . $officeName . ' مؤقتاً. تواصل مع إدارة مُداوَلة لإعادة التفعيل.'
                : 'انتهت مدة اشتراك ' . $officeName . '. النظام يعود إلى العمل فور تجديد الاشتراك.' }}
        </p>

        <div class="safe">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v6c0 4.4-3 7.6-7 9-4-1.4-7-4.6-7-9V6l7-3z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.5 12l1.8 1.8L15 10"/>
            </svg>
            بياناتك كلّها محفوظة كما هي — لم يُحذف منها شيء.
        </div>

        <ul class="facts">
            <li><span class="k">المكتب</span> <span class="v">{{ $officeName }}</span></li>
            @if($endAt)
                <li>
                    <span class="k">{{ $suspended ? 'نهاية المدة المدفوعة' : 'تاريخ الانتهاء' }}</span>
                    <span class="v" dir="ltr">{{ $endAt->format('Y-m-d') }}</span>
                </li>
                @if(! $suspended)
                    <li>
                        <span class="k">منذ</span>
                        <span class="v">{{ \App\Support\ArabicCount::days((int) max(0, $endAt->diffInDays(now()))) }}</span>
                    </li>
                @endif
            @endif
        </ul>

        <a class="cta" href="mailto:{{ $supportEmail }}?subject={{ rawurlencode('تجديد اشتراك ' . $officeName) }}">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7l9 6 9-6M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1z"/>
            </svg>
            راسل الإدارة لتجديد الاشتراك
        </a>

        <span class="mail">أو مباشرةً: <a href="mailto:{{ $supportEmail }}" dir="ltr">{{ $supportEmail }}</a></span>

        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout">تسجيل الخروج</button>
            </form>
        @endauth

        <div class="foot">
            {{ $officeName }} · <a href="{{ \App\Support\Mudawala::url() }}" target="_blank" rel="noopener">مُداوَلة</a>
        </div>
    </main>
</body>
</html>
