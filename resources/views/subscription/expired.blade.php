@php
    $officeName = \App\Models\Setting::get('office_name', 'LexPro');
    $supportEmail = \App\Models\Setting::get('office_email', 'admin@riyami.om');
    $supportPhone = \App\Models\Setting::get('office_phone', '');
    $isRtl = app()->getLocale() === 'ar';
@endphp
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>انتهت صلاحية الاشتراك — {{ $officeName }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script nonce="{{ $cspNonce }}" src="https://cdn.tailwindcss.com"></script>
    <script nonce="{{ $cspNonce }}">
        tailwind.config = {
            theme: { extend: { colors: {
                obsidian: '#080B12', navy: '#0D111B', charcoal: '#121826',
                gold: { DEFAULT: '#E5C158', soft: '#F0D98A', dim: '#A98218' },
                muted: '#94A3B8'
            } } }
        }
    </script>
    <style>
        :root {
            --obsidian: #080B12; --navy: #0D111B; --charcoal: #121826;
            --gold: #E5C158; --gold-soft: #F0D98A; --gold-dim: #A98218;
            --ivory: #FFFFFF; --muted: #94A3B8;
        }
        body {
            background: var(--obsidian); color: var(--ivory);
            font-family: 'IBM Plex Sans Arabic', sans-serif;
            min-height: 100vh; -webkit-font-smoothing: antialiased;
        }
        .scene {
            position: fixed; inset: 0; overflow: hidden; z-index: 0;
            background:
                radial-gradient(120% 90% at 78% 12%, rgba(200,169,107,0.08) 0%, transparent 45%),
                radial-gradient(90% 70% at 15% 85%, rgba(11,18,32,0.9) 0%, transparent 60%),
                linear-gradient(165deg, #0D111B 0%, #080B12 55%, #080B12 100%);
        }
        .grid-faint { position: absolute; inset: 0; opacity: 0.05;
            background-image: linear-gradient(rgba(200,169,107,0.25) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(200,169,107,0.25) 1px, transparent 1px);
            background-size: 72px 72px;
            mask-image: radial-gradient(ellipse 70% 70% at 50% 40%, black 20%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse 70% 70% at 50% 40%, black 20%, transparent 75%);
        }
        .noise-layer { position: absolute; inset: 0; opacity: 0.035; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3'/%3E%3C/filter%3E%3Crect width='160' height='160' filter='url(%23n)' opacity='0.6'/%3E%3C/svg%3E");
        }
        .panel {
            position: relative; max-width: 460px; margin: 0 auto;
            background: linear-gradient(158deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.022) 55%, rgba(224,201,138,0.025) 100%);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-radius: 26px; padding: 3rem 2.5rem;
            box-shadow: 0 40px 90px rgba(0,0,0,0.55), inset 0 1px 0 rgba(255,255,255,0.07);
        }
        .panel::before { content: ''; position: absolute; inset: 0; border-radius: 26px; padding: 1px;
            background: linear-gradient(150deg, rgba(224,201,138,0.32), rgba(224,201,138,0.05) 32%, rgba(255,255,255,0.05) 58%, rgba(200,169,107,0.22));
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor; mask-composite: exclude; pointer-events: none; }
        .seal { width: 84px; height: 84px; margin: 0 auto 1.5rem; border-radius: 50%;
            border: 1px solid rgba(224,201,138,0.35); display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 50px rgba(200,169,107,0.12); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            width: 100%; padding: 0.95rem; border-radius: 14px; font-weight: 700; font-size: 0.95rem;
            transition: transform 0.3s cubic-bezier(0.16,1,0.3,1), box-shadow 0.3s, opacity 0.3s; }
        .btn-gold { background: linear-gradient(120deg, var(--gold-soft), var(--gold) 50%, #D4AF37);
            color: #0D111B; }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 14px 44px rgba(200,169,107,0.3); }
        .btn-ghost { border: 1px solid rgba(146,153,165,0.25); color: var(--muted); background: transparent; }
        .btn-ghost:hover { color: var(--ivory); border-color: rgba(224,201,138,0.4); }
    </style>
</head>
<body>
    <div class="scene">
        <div class="noise-layer"></div>
        <div class="grid-faint"></div>
        <div style="position:absolute;width:520px;height:520px;border-radius:50%;filter:blur(120px);background:radial-gradient(circle, rgba(200,169,107,0.12), transparent 65%);top:-15%;inset-inline-start:60%;"></div>
    </div>

    <div class="relative z-10 min-h-screen flex items-center justify-center px-6 py-14">
        <div class="panel text-center">
            <div class="seal">
                <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="#E5C158" stroke-width="1.2">
                    <circle cx="12" cy="12" r="9"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 3.5L12 2l5 1.5"/>
                </svg>
            </div>
            <h1 class="font-serif text-2xl sm:text-3xl font-bold mb-3" style="color:var(--gold-soft);">انتهت صلاحية الاشتراك</h1>
            <p class="text-sm leading-relaxed mb-6" style="color:var(--muted);">
                انتهت مدة الاشتراك الخاصة بهذا النظام.
                يرجى التواصل مع المطور لتفعيل النظام من جديد.
            </p>

            <div class="space-y-3 mb-6">
                <a href="mailto:{{ $supportEmail }}?subject=تجديد اشتراك {{ $officeName }}" class="btn btn-gold">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    تواصل مع الإدارة
                </a>
                @if($supportPhone)
                    <a href="https://wa.me/{{ preg_replace('/\D+/', '', $supportPhone) }}?text=أرغب بتجديد اشتراك النظام" target="_blank" rel="noopener" class="btn btn-ghost">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h4l2 5-2.5 1.5a12 12 0 005 5L15 12l5 2v4a2 2 0 01-2 2A16 16 0 013 5z"/></svg>
                        واتساب
                    </a>
                @endif
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="text-xs underline-offset-4 hover:underline" style="color:rgba(146,153,165,0.6);">تسجيل الخروج</button>
            </form>
        </div>
    </div>
</body>
</html>