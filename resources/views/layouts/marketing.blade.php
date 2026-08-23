@php
    $mk = config('marketing');
    $officeName = $mk['office_name'];
@endphp
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#07090d">
    <title>@yield('title', 'مُداوَلة') — {{ $officeName }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <script nonce="{{ $cspNonce }}" src="https://cdn.tailwindcss.com"></script>
    <script nonce="{{ $cspNonce }}">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ink: { DEFAULT: '#07090d', 1: '#0a0d13', 2: '#0d1119' },
                        gold: { DEFAULT: '#e3bd62', soft: '#f2dda0', dark: '#9a741f' },
                        ivory: '#f1ece1',
                        muted: '#97a0b0',
                    },
                    fontFamily: {
                        body: ['Tajawal', 'sans-serif'],
                        latin: ['Inter', 'Tajawal', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <style>
        * { font-family: 'Tajawal', sans-serif; }
        .font-latin { font-family: 'Inter', 'Tajawal', sans-serif; }
        .btn-gold {
            background: linear-gradient(115deg, #f2dda0 0%, #e3bd62 42%, #c89c3a 78%, #e3bd62 100%);
            color: #141007;
            box-shadow: 0 10px 30px -12px rgba(227, 189, 98, 0.5);
            transition: all .4s cubic-bezier(.22,1,.36,1);
        }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 16px 40px -12px rgba(227, 189, 98, 0.65); }
        .btn-ghost { border: 1px solid rgba(241,236,225,.18); color: #f1ece1; transition: all .4s cubic-bezier(.22,1,.36,1); }
        .btn-ghost:hover { border-color: rgba(227,189,98,.45); background: rgba(227,189,98,.05); transform: translateY(-2px); }
        .card-lux {
            background: linear-gradient(158deg, rgba(255,255,255,.028) 0%, rgba(255,255,255,.012) 55%, rgba(227,189,98,.03) 100%);
            border: 1px solid rgba(241,236,225,.07);
            transition: all .5s cubic-bezier(.22,1,.36,1);
        }
        .card-lux:hover { border-color: rgba(227,189,98,.32); transform: translateY(-4px); box-shadow: 0 24px 60px -30px rgba(0,0,0,.8); }
        .eyebrow { letter-spacing: .3em; color: rgba(242,221,160,.9); }
        .gold-text {
            background: linear-gradient(115deg,#f2dda0 10%,#e3bd62 40%,#b98f33 65%,#f2dda0 90%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #07090d; }
        ::-webkit-scrollbar-thumb { background: rgba(227,189,98,.25); border-radius: 8px; }
        ::selection { background: rgba(227,189,98,.28); color: #f1ece1; }
    </style>

    @stack('styles')
</head>
<body class="bg-ink text-ivory antialiased min-h-screen flex flex-col" style="font-family:'Tajawal',sans-serif">

    <!-- شريط علوي -->
    <header class="sticky top-0 z-50 border-b border-gold/10 bg-ink/85 backdrop-blur-xl">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-16 sm:h-[72px]">
                <a href="{{ $mk['portfolio_url'] }}" class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg border border-gold/30 bg-gold/10 text-sm font-bold text-gold-soft font-latin">L</span>
                    <span class="flex flex-col leading-none">
                        <span class="font-latin font-bold tracking-widest text-ivory">Lex<span class="gold-text">Pro</span></span>
                        <span class="mt-0.5 text-[10px] tracking-widest text-muted">منظومة قانونية</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-1 md:flex" aria-label="التنقل الرئيسي">
                    <a href="{{ $mk['portfolio_url'] }}" class="rounded-full px-4 py-2 text-[13px] text-muted transition-colors hover:text-ivory">الرئيسية</a>
                    <a href="{{ route('marketing.features') }}" class="rounded-full px-4 py-2 text-[13px] text-muted transition-colors hover:text-ivory">المميزات</a>
                    <a href="{{ route('marketing.pricing') }}" class="rounded-full px-4 py-2 text-[13px] text-muted transition-colors hover:text-ivory">الأسعار</a>
                    <a href="{{ route('marketing.faq') }}" class="rounded-full px-4 py-2 text-[13px] text-muted transition-colors hover:text-ivory">الأسئلة الشائعة</a>
                    <a href="{{ route('marketing.contact') }}" class="rounded-full px-4 py-2 text-[13px] text-muted transition-colors hover:text-ivory">تواصل معنا</a>
                </nav>

                <a href="{{ route('marketing.register') }}" class="btn-gold rounded-full px-5 py-2.5 text-[13px] font-semibold">
                    ابدأ تجربتك المجانية
                </a>
            </div>
        </div>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    <!-- الفوتر -->
    <footer class="border-t border-gold/15 bg-ink-1">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-14 pb-8">
            <div class="grid gap-10 md:grid-cols-4">
                <div>
                    <p class="font-latin font-bold tracking-widest text-ivory">Lex<span class="gold-text">Pro</span></p>
                    <p class="mt-4 text-xs leading-7 text-muted">منظومة قانونية متكاملة لإدارة مكاتب وشركات المحاماة — القضايا، العملاء، المستندات، الجلسات، والمهام في منظومة واحدة آمنة.</p>
                </div>
                <div>
                    <p class="mb-4 text-xs font-semibold tracking-widest text-gold-soft">الموقع</p>
                    <ul class="flex flex-col gap-2.5 text-xs text-muted">
                        <li><a href="{{ $mk['portfolio_url'] }}" class="transition-colors hover:text-ivory">الرئيسية</a></li>
                        <li><a href="{{ route('marketing.features') }}" class="transition-colors hover:text-ivory">المميزات</a></li>
                        <li><a href="{{ route('marketing.pricing') }}" class="transition-colors hover:text-ivory">الأسعار</a></li>
                        <li><a href="{{ route('marketing.guide') }}" class="transition-colors hover:text-ivory">الدليل</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-4 text-xs font-semibold tracking-widest text-gold-soft">الدعم</p>
                    <ul class="flex flex-col gap-2.5 text-xs text-muted">
                        <li><a href="{{ route('marketing.faq') }}" class="transition-colors hover:text-ivory">الأسئلة الشائعة</a></li>
                        <li><a href="{{ route('marketing.blog') }}" class="transition-colors hover:text-ivory">المدونة</a></li>
                        <li><a href="{{ route('marketing.contact') }}" class="transition-colors hover:text-ivory">تواصل معنا</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-4 text-xs font-semibold tracking-widest text-gold-soft">التواصل</p>
                    <ul class="flex flex-col gap-2.5 text-xs text-muted">
                        <li class="font-latin">{{ $mk['phone'] }}</li>
                        <li class="font-latin" dir="ltr">{{ $mk['email'] }}</li>
                        <li>{{ $mk['address'] }}</li>
                        <li>{{ $mk['hours'] }}</li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 flex flex-col items-center justify-between gap-3 border-t border-ivory/10 pt-6 text-[11px] text-muted/70 sm:flex-row">
                <p>© {{ date('Y') }} {{ $mk['office_name'] }} — جميع الحقوق محفوظة</p>
                <div class="flex items-center gap-5">
                    <a href="{{ route('marketing.privacy') }}" class="transition-colors hover:text-gold-soft">سياسة الخصوصية</a>
                    <span class="h-1 w-1 rounded-full bg-gold/40"></span>
                    <a href="{{ route('marketing.terms') }}" class="transition-colors hover:text-gold-soft">الشروط والأحكام</a>
                </div>
            </div>
        </div>
    </footer>

    @stack('scripts')
    @include('partials.phone-mask')
</body>
</html>
