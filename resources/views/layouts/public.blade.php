@php
    $officeName = \App\Models\Setting::get('office_name', 'LexPro');
    $isRtl = app()->getLocale() === 'ar';
@endphp
<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $officeName) - {{ $officeName }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">

    <script nonce="{{ $cspNonce }}" src="https://cdn.tailwindcss.com"></script>
    <script nonce="{{ $cspNonce }}">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gold: { DEFAULT: '#C9A55A', light: '#E0C878', dark: '#A8903E' },
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
        .animate-fade-in { animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    @stack('styles')
</head>
<body class="bg-gradient-to-br from-amber-50 to-orange-100 min-h-screen flex flex-col">
    <div class="flex-1">
        @yield('content')
    </div>

    <footer class="py-4 text-center">
        <div class="flex items-center justify-center gap-2">
            <span class="text-xs text-gray-400">LexPro &copy; {{ date('Y') }}</span>
            <span class="text-gray-300">|</span>
            <span class="text-xs text-gray-400">{{ $officeName }}</span>
            <span class="text-gray-300">|</span>
            <span class="text-xs text-gray-400">المطور عبدالرحمن الريامي</span>
        </div>
    </footer>
</body>
</html>
