@php
    $officeName = \App\Models\Setting::get('office_name', 'LexPro');
@endphp
<!DOCTYPE html>
<html dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('app.login_title') }} — LexPro</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">

    <script nonce="{{ $cspNonce }}" src="https://cdn.tailwindcss.com"></script>
    <script nonce="{{ $cspNonce }}">
        tailwind.config = {
            theme: { extend: { colors: { gold: { DEFAULT: '#C9A55A', light: '#E0C878', dark: '#A8903E' } } } }
        }
    </script>
    <script nonce="{{ $cspNonce }}" defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        * { font-family: 'Tajawal', sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Cairo', sans-serif; }
        body { background: #F4F2EE; min-height: 100vh; overflow: hidden; }

        .login-bg { position: fixed; inset: 0; z-index: 0; }
        .login-bg::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse 120% 80% at 70% 10%, rgba(191,155,48,0.04) 0%, transparent 50%), radial-gradient(ellipse 80% 60% at 20% 90%, rgba(191,155,48,0.03) 0%, transparent 50%), radial-gradient(ellipse 100% 100% at 50% 50%, rgba(244,242,238,1) 0%, rgba(237,232,220,1) 100%); }

        .particle { position: absolute; border-radius: 50%; pointer-events: none; opacity: 0; animation: particleFloat linear infinite; }
        @keyframes particleFloat { 0% { opacity: 0; transform: translateY(100vh) scale(0); } 10% { opacity: 1; } 90% { opacity: 1; } 100% { opacity: 0; transform: translateY(-10vh) scale(1); } }

        @keyframes floatOrb1 { 0%,100% { transform: translate(0,0) scale(1); opacity: 0.08; } 50% { transform: translate(-30px,20px) scale(1.08); opacity: 0.14; } }
        @keyframes floatOrb2 { 0%,100% { transform: translate(0,0) scale(1); opacity: 0.05; } 50% { transform: translate(20px,-30px) scale(1.05); opacity: 0.1; } }

        .grid-pattern { position: absolute; inset: 0; background-image: linear-gradient(rgba(191,155,48,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(191,155,48,0.04) 1px, transparent 1px); background-size: 60px 60px; mask-image: radial-gradient(ellipse 60% 60% at 50% 50%, black 30%, transparent 70%); -webkit-mask-image: radial-gradient(ellipse 60% 60% at 50% 50%, black 30%, transparent 70%); }

        .card-luxury { background: #FFFFFF; border: 1px solid #E2DED6; box-shadow: 0 4px 24px rgba(0,0,0,0.04); }

        .input-luxury { background: #FFFFFF; border: 1px solid #D4CFC7; transition: all 0.4s cubic-bezier(0.16,1,0.3,1); color: #111111; }
        .input-luxury:focus { border-color: rgba(191,155,48,0.5); box-shadow: 0 0 0 4px rgba(191,155,48,0.08); outline: none; }
        .input-luxury::placeholder { color: #B0ABA3; }
        .input-luxury:-webkit-autofill { -webkit-box-shadow: 0 0 0 30px white inset !important; -webkit-text-fill-color: #111111 !important; caret-color: #111111; }

        .btn-login { background: linear-gradient(135deg, #C9A55A 0%, #B89545 50%, #C9A55A 100%); background-size: 200% 200%; animation: btnGradient 4s ease infinite; position: relative; overflow: hidden; transition: all 0.4s cubic-bezier(0.16,1,0.3,1); color: #FFFFFF; }
        @keyframes btnGradient { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        .btn-login::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, transparent 20%, rgba(255,255,255,0.2) 50%, transparent 80%); background-size: 250% 250%; animation: btnShimmer 3s ease-in-out infinite; }
        @keyframes btnShimmer { 0% { background-position: 250% 0; } 100% { background-position: -250% 0; } }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 16px 48px rgba(201,165,90,0.3); }
        .btn-login:active { transform: translateY(0); box-shadow: 0 4px 16px rgba(201,165,90,0.15); }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(35px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeInLeft { from { opacity: 0; transform: translateX(-30px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes scaleIn { from { opacity: 0; transform: scale(0.92); } to { opacity: 1; transform: scale(1); } }
        @keyframes glowPulse { 0%,100% { opacity: 0.2; } 50% { opacity: 0.4; } }

        .anim-up { animation: fadeInUp 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
        .anim-left { animation: fadeInLeft 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
        .anim-scale { animation: scaleIn 0.6s cubic-bezier(0.16,1,0.3,1) forwards; }

        .d1 { animation-delay: 0.08s; opacity: 0; } .d2 { animation-delay: 0.15s; opacity: 0; }
        .d3 { animation-delay: 0.22s; opacity: 0; } .d4 { animation-delay: 0.29s; opacity: 0; }
        .d5 { animation-delay: 0.36s; opacity: 0; } .d6 { animation-delay: 0.43s; opacity: 0; }
        .d7 { animation-delay: 0.5s; opacity: 0; }  .d8 { animation-delay: 0.57s; opacity: 0; }

        @keyframes shake { 0%,100% { transform: translateX(0); } 20% { transform: translateX(-6px); } 40% { transform: translateX(6px); } 60% { transform: translateX(-4px); } 80% { transform: translateX(4px); } }
        .shake { animation: shake 0.5s ease-in-out; }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: transparent; } ::-webkit-scrollbar-thumb { background: rgba(191,155,48,0.15); border-radius: 3px; }
        .deco-line { position: absolute; background: linear-gradient(180deg, transparent, rgba(191,155,48,0.08), transparent); }
        .spinner { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #FFFFFF; border-radius: 50%; animation: spinAnim 0.6s linear infinite; }
        @keyframes spinAnim { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="login-bg"><div class="grid-pattern"></div></div>
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-[1]" id="particles"></div>
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-[1]">
        <div class="absolute w-[500px] h-[500px] rounded-full blur-[120px]" style="background:rgba(191,155,48,0.06);top:-15%;right:20%;animation:floatOrb1 20s ease-in-out infinite;"></div>
        <div class="absolute w-[400px] h-[400px] rounded-full blur-[100px]" style="background:rgba(191,155,48,0.04);bottom:-10%;left:10%;animation:floatOrb2 25s ease-in-out infinite 5s;"></div>
    </div>
    <div class="fixed inset-0 pointer-events-none z-[1] hidden lg:block">
        <div class="deco-line w-px h-36" style="top:10%;right:15%;"></div>
        <div class="deco-line w-px h-28" style="top:20%;right:25%;"></div>
        <div class="deco-line w-px h-40" style="top:8%;left:20%;"></div>
        <div class="deco-line w-px h-32" style="top:30%;left:12%;"></div>
    </div>

    <div class="relative z-10 min-h-screen flex">

        {{-- LEFT: Brand --}}
        <div class="hidden lg:flex lg:w-1/2 items-center justify-center p-14 relative">
            <div class="max-w-sm text-center anim-left d2">

                <a href="{{ url('/portfolio') }}" target="_blank" class="w-24 h-24 mx-auto mb-10 relative block">
                    <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-amber-400 to-amber-600 opacity-10 blur-2xl" style="animation:glowPulse 4s ease-in-out infinite;"></div>
                    <div class="relative w-full h-full rounded-3xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-2xl overflow-hidden">
                        @if(is_file(public_path('img/office-logo.svg')))
                            <img src="{{ asset('img/office-logo.svg') }}" alt="{{ $officeName }}" class="w-full h-full object-cover">
                        @else
                            <svg class="w-14 h-14 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                            </svg>
                        @endif
                    </div>
                </a>

                <h1 class="text-4xl font-black text-gray-900 mb-3 tracking-tight">{{ $officeName }}</h1>

                <div class="flex items-center justify-center gap-3 mb-6">
                    <div class="h-px flex-1 max-w-[60px] bg-gradient-to-r from-transparent to-amber-300"></div>
                    <svg class="w-3.5 h-3.5 text-amber-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                    <div class="h-px flex-1 max-w-[60px] bg-gradient-to-l from-transparent to-amber-300"></div>
                </div>

                <a href="{{ url('/portfolio') }}" target="_blank" class="inline-flex items-center gap-1.5 text-gray-400 hover:text-amber-600 transition-all duration-300 text-xs font-mono tracking-[0.15em] uppercase mb-12 group">
                    <span>Powered by</span>
                    <span class="text-amber-600 group-hover:text-amber-700">LexPro</span>
                    <svg class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-all" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8l-4 4m0 0l4 4m-4-4H21"/></svg>
                </a>

                <div class="space-y-3">
                    <div class="flex items-center gap-4 p-4 rounded-2xl bg-white/80 border border-gray-200" style="text-align:{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4.5 h-4.5 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <div>
                            <h3 class="text-gray-800 font-semibold text-sm">إدارة القضايا</h3>
                            <p class="text-gray-500 text-xs mt-0.5">تتبع شامل للقضايا والجلسات</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 p-4 rounded-2xl bg-white/80 border border-gray-200" style="text-align:{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4.5 h-4.5 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-gray-800 font-semibold text-sm">إدارة المستندات</h3>
                            <p class="text-gray-500 text-xs mt-0.5">تنظيم وحفظ الملفات القانونية</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 p-4 rounded-2xl bg-white/80 border border-gray-200" style="text-align:{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4.5 h-4.5 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-gray-800 font-semibold text-sm">التقارير</h3>
                            <p class="text-gray-500 text-xs mt-0.5">تحليلات ولوحات تفاعلية</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Login --}}
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-10 relative">

            <a href="{{ route('language.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}"
                class="fixed top-6 left-6 z-50 flex items-center gap-2 px-4 py-2.5 rounded-xl text-gray-500 hover:text-gray-700 hover:bg-gray-200 transition-all text-sm border border-gray-300 backdrop-blur-sm anim-up d1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                {{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}
            </a>

            <a href="{{ url('/portfolio') }}" target="_blank" class="lg:hidden fixed top-6 right-6 z-50 anim-up d1">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                    </svg>
                </div>
            </a>

            <div class="w-full max-w-md anim-up d3">
                <div class="card-luxury rounded-3xl overflow-hidden relative">
                    <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-amber-400/30 to-transparent"></div>

                    <div class="px-10 pt-12 pb-8">

                        <div class="text-center mb-8 anim-scale d4">
                            <div class="w-20 h-20 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-2xl lg:hidden">
                                <svg class="w-12 h-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900 mb-1.5 anim-up d5" style="font-family:'Cairo',sans-serif;">{{ __('app.login_title') }}</h2>
                            <p class="text-gray-500 text-sm anim-up d5">{{ __('app.login_subtitle') ?? 'أدخل بياناتك للوصول إلى النظام' }}</p>
                        </div>

                        @php $loginError = session('login_error'); @endphp
                        @if($errors->any() || $loginError)
                            <div class="mb-6 p-4 rounded-2xl border border-red-200 bg-red-50">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <svg class="w-4 h-4 text-red-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div>
                                        @if($loginError)
                                            <p class="text-sm text-red-700 font-medium">{{ $loginError }}</p>
                                        @endif
                                        @foreach($errors->all() as $error)
                                            <p class="text-sm text-red-700 font-medium">{{ $error }}</p>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}" class="space-y-5">
                            @csrf

                            <div class="anim-up d5">
                                <label for="email" class="block text-sm font-medium text-gray-600 mb-2">{{ __('app.email') }}</label>
                                <div class="relative group">
                                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                                        class="input-luxury w-full px-5 py-3.5 {{ app()->getLocale() === 'ar' ? 'pr-12' : 'pl-12' }} rounded-2xl text-sm" placeholder="name@example.com">
                                    <div class="absolute inset-y-0 {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }} flex items-center pointer-events-none">
                                        <svg class="w-4 h-4 text-gray-400 group-focus-within:text-amber-600 transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <div class="anim-up d6">
                                <label for="password" class="block text-sm font-medium text-gray-600 mb-2">{{ __('app.password') }}</label>
                                <div class="relative group" x-data="{ show: false }">
                                    <input :type="show ? 'text' : 'password'" id="password" name="password" required
                                        class="input-luxury w-full px-5 py-3.5 {{ app()->getLocale() === 'ar' ? 'pr-12 pl-12' : 'pl-12 pr-12' }} rounded-2xl text-sm" placeholder="••••••••">
                                    <div class="absolute inset-y-0 {{ app()->getLocale() === 'ar' ? 'right-0 pr-4' : 'left-0 pl-4' }} flex items-center pointer-events-none">
                                        <svg class="w-4 h-4 text-gray-400 group-focus-within:text-amber-600 transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                    <button type="button" @click="show = !show"
                                        class="absolute inset-y-0 {{ app()->getLocale() === 'ar' ? 'left-0 pl-4' : 'right-0 pr-4' }} flex items-center text-gray-400 hover:text-gray-600 transition-colors duration-200">
                                        <svg x-show="!show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg x-show="show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-1 anim-up d6">
                                <label class="flex items-center gap-2.5 cursor-pointer group">
                                    <div class="relative" x-data="{ checked: {{ old('remember') ? 'true' : 'false' }} }">
                                        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}
                                            class="sr-only peer" @change="checked = $event.target.checked">
                                        <div class="w-4.5 h-4.5 rounded-lg border border-gray-300 bg-white peer-checked:bg-amber-500 peer-checked:border-amber-500 transition-all duration-200 flex items-center justify-center">
                                            <svg x-show="checked" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                    </div>
                                    <span class="text-sm text-gray-500 group-hover:text-gray-700 transition-colors">{{ __('app.remember_me') }}</span>
                                </label>
                                @if(Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-sm text-amber-600 hover:text-amber-700 transition-colors duration-200">{{ __('app.forgot_password') }}</a>
                                @endif
                            </div>

                            <div class="pt-3 anim-up d7">
                                <button type="submit" id="loginBtn"
                                    class="btn-login w-full py-4 px-6 text-white font-bold text-base rounded-2xl relative" style="font-family:'Cairo',sans-serif;">
                                    <span id="btnText" class="relative z-10 flex items-center justify-center gap-2.5">
                                        {{ __('app.login_button') }}
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8l-4 4m0 0l4 4m-4-4H21"/></svg>
                                    </span>
                                    <span id="btnSpinner" class="relative z-10 hidden items-center justify-center gap-2">
                                        <div class="spinner"></div>
                                        <span class="text-sm">جاري تسجيل الدخول...</span>
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="mx-10 h-px bg-gradient-to-r from-transparent via-amber-200 to-transparent"></div>
                    <div class="px-10 py-4 text-center">
                        <p class="text-gray-400 text-xs">{{ $officeName }} — Powered by <a href="{{ url('/portfolio') }}" target="_blank" class="text-amber-600 hover:text-amber-700 transition-colors">LexPro</a></p>
                    </div>
                </div>

                <div class="text-center mt-8 anim-up d8">
                    <p class="text-gray-400 text-xs">&copy; {{ date('Y') }} {{ $officeName }}. جميع الحقوق محفوظة.</p>
                </div>
            </div>
        </div>
    </div>

    <script nonce="{{ $cspNonce }}">
        document.addEventListener('DOMContentLoaded', function() {
            const c = document.getElementById('particles'); if (!c) return;
            for (let i = 0; i < 20; i++) { const p = document.createElement('div'); p.className = 'particle'; const s = Math.random() * 3 + 1; p.style.cssText = `width:${s}px;height:${s}px;left:${Math.random()*100}%;background:rgba(191,155,48,${Math.random()*0.2+0.06});animation-duration:${Math.random()*20+15}s;animation-delay:${Math.random()*15}s;`; c.appendChild(p); }

            const emailInput = document.getElementById('email');
            const rememberCheck = document.getElementById('remember');
            const savedEmail = localStorage.getItem('lexpro_remembered_email');
            if (savedEmail && emailInput) {
                emailInput.value = savedEmail;
                if (rememberCheck) rememberCheck.checked = true;
            }
        });
        document.querySelector('form')?.addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn'); const t = document.getElementById('btnText'); const s = document.getElementById('btnSpinner');
            if (btn && t && s) { t.classList.add('hidden'); s.classList.remove('hidden'); btn.style.pointerEvents = 'none'; }

            const emailInput = document.getElementById('email');
            const rememberCheck = document.getElementById('remember');
            if (rememberCheck && rememberCheck.checked && emailInput) {
                localStorage.setItem('lexpro_remembered_email', emailInput.value);
            } else {
                localStorage.removeItem('lexpro_remembered_email');
            }
        });
    </script>
</body>
</html>
