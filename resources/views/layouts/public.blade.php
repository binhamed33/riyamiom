@php
    $officeName = \App\Models\Setting::get('office_name', 'مُداوَلة');
    $isRtl = app()->getLocale() === 'ar';
@endphp
<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#A98218">
    <title>@yield('title', $officeName) - {{ $officeName }}</title>

    <link rel="icon" href="/favicon.ico">
    @php
        $publicLogo = \App\Support\OfficeBrand::logoUrl();
        }
    @endphp
    @if($publicLogo)
        <link rel="icon" type="{{ $publicLogoType }}" href="{{ $publicLogo }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">

    <script nonce="{{ $cspNonce }}" src="https://cdn.tailwindcss.com"></script>
    <script nonce="{{ $cspNonce }}">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gold: { DEFAULT: '#D4AF37', light: '#E5C158', dark: '#A98218' },
                    },
                    fontFamily: {
                        body: ['Tajawal', 'sans-serif'],
                        heading: ['Cairo', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <style>
        * { font-family: 'Tajawal', sans-serif; }
        h1, h2, h3, h4, h5, h6, .font-heading { font-family: 'Cairo', sans-serif; }

        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        .animate-slide-up { animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }

        @media (max-width: 640px) {
            .mobile-full { margin-left: -1rem; margin-right: -1rem; border-radius: 0; }
        }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #D4AF37; border-radius: 3px; }
    </style>

    @stack('styles')
</head>
<body class="bg-gradient-to-br from-[#F9FAFB] via-white to-[#F7F8FA] min-h-screen flex flex-col">
    <header class="bg-white/80 backdrop-blur-sm border-b border-gold/15 sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-14 sm:h-16">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-gradient-to-br from-gold to-gold-dark flex items-center justify-center shadow-lg shadow-gold/20">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="font-heading font-bold text-sm sm:text-base text-[#111827] leading-tight">{{ $officeName }}</h1>
                        <p class="text-[10px] sm:text-xs text-gold-dark/70 leading-tight">بوابة المتابعة الإلكترونية</p>
                    </div>
                </div>
                @hasSection('header-actions')
                    <div class="flex items-center gap-2">
                        @yield('header-actions')
                    </div>
                @endif
            </div>
        </div>
    </header>

    <main class="flex-1">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 sm:py-8">
            @yield('content')
        </div>
    </main>

    <footer class="bg-white/50 border-t border-gold/15">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 sm:py-5">
            <div class="flex flex-col sm:flex-row items-center justify-center gap-1.5 sm:gap-3 text-[10px] sm:text-xs text-gray-400">
                <a href="{{ \App\Support\Mudawala::url() }}" target="_blank" class="hover:text-gold-dark transition-colors">مُداوَلة &copy; {{ date('Y') }}</a>
                <span class="hidden sm:inline text-gray-300">|</span>
                <span>{{ $officeName }}</span>
                <span class="hidden sm:inline text-gray-300">|</span>
                <a href="{{ \App\Support\Mudawala::url() }}" target="_blank" class="hover:text-gold-dark transition-colors">المطور عبدالرحمن الريامي</a>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
