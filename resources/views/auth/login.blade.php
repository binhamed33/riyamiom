@php
    $officeName = \App\Models\Setting::get('office_name', 'LexPro');
    $isRtl = app()->getLocale() === 'ar';
@endphp
<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
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
    <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script nonce="{{ $cspNonce }}" src="https://cdn.tailwindcss.com"></script>
    <script nonce="{{ $cspNonce }}">
        tailwind.config = {
            theme: { extend: { colors: {
                obsidian: '#080B12', navy: '#0D111B', charcoal: '#121826',
                gold: { DEFAULT: '#E5C158', soft: '#F0D98A', dim: '#A98218' },
                ivory: '#121826', muted: '#94A3B8'
            } } }
        }
    </script>

    <style>
        :root {
            --obsidian: #080B12;
            --navy: #0D111B;
            --charcoal: #121826;
            --gold: #E5C158;
            --gold-soft: #F0D98A;
            --gold-dim: #A98218;
            --ivory: #FFFFFF;
            --muted: #94A3B8;
        }

        body {
            background: var(--obsidian);
            color: var(--ivory);
            font-family: 'IBM Plex Sans Arabic', sans-serif;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, .font-verse, .font-editorial { font-family: 'Amiri', serif; }

        ::selection { background: rgba(200,169,107,0.25); color: var(--ivory); }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(200,169,107,0.22); border-radius: 3px; }

        /* ============ CINEMATIC BASE ============ */
        .scene { position: fixed; inset: 0; overflow: hidden; background:
            radial-gradient(120% 90% at 78% 12%, rgba(200,169,107,0.07) 0%, transparent 45%),
            radial-gradient(90% 70% at 15% 85%, rgba(11,18,32,0.9) 0%, transparent 60%),
            linear-gradient(165deg, #0D111B 0%, #080B12 55%, #080B12 100%);
            z-index: 0;
        }

        .noise-layer { position: absolute; inset: 0; opacity: 0.035; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3'/%3E%3C/filter%3E%3Crect width='160' height='160' filter='url(%23n)' opacity='0.6'/%3E%3C/svg%3E");
        }

        .grid-faint { position: absolute; inset: 0; opacity: 0.05;
            background-image: linear-gradient(rgba(200,169,107,0.25) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(200,169,107,0.25) 1px, transparent 1px);
            background-size: 72px 72px;
            mask-image: radial-gradient(ellipse 70% 70% at 50% 40%, black 20%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse 70% 70% at 50% 40%, black 20%, transparent 75%);
        }

        .ambient-orb { position: absolute; border-radius: 50%; filter: blur(110px); pointer-events: none; }

        .light-sweep { position: absolute; inset: -20%; pointer-events: none;
            background: linear-gradient(105deg, transparent 42%, rgba(224,201,138,0.05) 50%, transparent 58%);
            transform: translateX(-60%);
            animation: sweep 9s cubic-bezier(0.4,0,0.2,1) infinite;
        }
        @keyframes sweep { 0% { transform: translateX(-60%); } 55% { transform: translateX(40%); } 100% { transform: translateX(100%); } }

        /* cursor-following ambient glow */
        #cursorGlow { position: fixed; width: 540px; height: 540px; border-radius: 50%;
            background: radial-gradient(circle, rgba(200,169,107,0.055) 0%, rgba(200,169,107,0.02) 35%, transparent 65%);
            transform: translate(-270px, -270px); pointer-events: none; z-index: 1;
            left: 0; top: 0; will-change: transform; }

        /* gold dust */
        .dust { position: absolute; bottom: -8px; border-radius: 50%; pointer-events: none; opacity: 0;
            animation: dustRise linear infinite; }
        @keyframes dustRise {
            0% { transform: translateY(0) translateX(0); opacity: 0; }
            12% { opacity: 0.5; }
            85% { opacity: 0.4; }
            100% { transform: translateY(-110vh) translateX(24px); opacity: 0; }
        }

        /* ============ INTRO TIMELINE ============ */
        .reveal { opacity: 0; animation: fadeUp 1s cubic-bezier(0.16,1,0.3,1) forwards; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(26px); } to { opacity: 1; transform: translateY(0); } }

        .reveal-bg { opacity: 0; animation: fadeIn 1.4s ease 0.1s forwards; }
        @keyframes fadeIn { to { opacity: 1; } }

        .hairline-gold { position: absolute; height: 1px; width: 70%; max-width: 300px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            transform: scaleX(0); animation: hairlineIn 1.1s cubic-bezier(0.16,1,0.3,1) 0.35s forwards; }
        @keyframes hairlineIn { to { transform: scaleX(1); } }

        .scales-wrap { opacity: 0; animation: scalesIn 2.4s cubic-bezier(0.16,1,0.3,1) 0.4s forwards; }
        @keyframes scalesIn { 0% { opacity: 0; transform: translateY(40px) scale(0.94); } 100% { opacity: 1; transform: translateY(0) scale(1); } }

        .scales-sway { transform-origin: 50% 30%; animation: swaySettle 1.6s cubic-bezier(0.34,1.2,0.4,1) 1.1s forwards; }
        @keyframes swaySettle { 0% { transform: rotate(-2.4deg); } 50% { transform: rotate(1.3deg); } 100% { transform: rotate(0deg); } }

        .scales-float { animation: scalaFloat 10s ease-in-out 4.5s infinite; }
        @keyframes scalaFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-7px); } }

        /* ============ VISUAL SIDE ============ */
        .visual-frame { position: absolute; inset: 22px; pointer-events: none;
            border: 1px solid rgba(200,169,107,0.10); opacity: 0;
            animation: frameIn 2s ease 0.8s forwards; }
        @keyframes frameIn { to { opacity: 1; } }
        .visual-frame::before, .visual-frame::after {
            content: ''; position: absolute; width: 34px; height: 34px;
            border: 1px solid rgba(224,201,138,0.35); opacity: 0;
            animation: frameIn 1.4s ease 1.1s forwards; }
        .visual-frame::before { top: -1px; inset-inline-start: -1px; border-inline-end: 0; border-bottom: 0; }
        .visual-frame::after { bottom: -1px; inset-inline-end: -1px; border-inline-start: 0; border-top: 0; }

        .watermark-word { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
            font-family: 'Amiri', serif; font-size: clamp(11rem, 26vw, 24rem); line-height: 1;
            color: transparent; -webkit-text-stroke: 1px rgba(200,169,107,0.10);
            text-stroke: 1px rgba(200,169,107,0.10); user-select: none; pointer-events: none;
            opacity: 0; animation: fadeIn 3s ease 1.6s forwards; }

        .seal-rotor { position: absolute; top: 8%; inset-inline-start: 7%; width: 150px; height: 150px;
            pointer-events: none; animation: sealSpin 46s linear infinite;
            opacity: 0; animation: sealSpin 46s linear infinite, fadeIn 2.4s ease 1.2s forwards; }
        @keyframes sealSpin { to { transform: rotate(360deg); } }
        .seal-rotor svg { width: 100%; height: 100%; }

        .article-marks { position: absolute; bottom: 12%; inset-inline-end: 6%; text-align: center;
            font-family: 'Amiri', serif; color: rgba(200,169,107,0.30); font-size: 0.85rem; line-height: 2.1;
            letter-spacing: 0.4em; user-select: none; opacity: 0; animation: fadeIn 2.5s ease 1.4s forwards; }

        .arch-trace { position: absolute; inset: 0; pointer-events: none; opacity: 0.055; }

        .verses-box { position: relative; }
        .verse-ring { position: absolute; inset: -22px; border-radius: 50%;
            border: 1px solid rgba(200,169,107,0.10); opacity: 0; animation: fadeIn 2.5s ease 1.3s forwards; }

        /* ============ LOGIN PANEL ============ */
        .panel-glass { position: relative;
            background: linear-gradient(158deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.022) 55%, rgba(224,201,138,0.025) 100%);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-radius: 26px;
            box-shadow: 0 40px 90px rgba(0,0,0,0.55), inset 0 1px 0 rgba(255,255,255,0.07);
            background-clip: padding-box; }
        .panel-glass::before { content: ''; position: absolute; inset: 0; border-radius: 26px; padding: 1px;
            background: linear-gradient(150deg, rgba(224,201,138,0.32), rgba(224,201,138,0.05) 32%, rgba(255,255,255,0.05) 58%, rgba(200,169,107,0.22));
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor; mask-composite: exclude; pointer-events: none; }
        .panel-glass .top-hair { position: absolute; top: 0; inset-inline: 12%; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(224,201,138,0.55), transparent); }

        .field-wrap { position: relative; }
        .field { width: 100%;
            background: rgba(8,9,11,0.42);
            border: 1px solid rgba(146,153,165,0.20);
            border-radius: 14px;
            color: var(--ivory);
            padding: 0.95rem 2.9rem 0.95rem 2.9rem;
            font-size: 0.95rem;
            transition: border-color 0.45s cubic-bezier(0.16,1,0.3,1), box-shadow 0.45s cubic-bezier(0.16,1,0.3,1), background 0.45s;
            outline: none; }
        .field::placeholder { color: rgba(255,255,255,0.75); }
        .field:hover { border-color: rgba(146,153,165,0.38); }
        .field:focus, .field-wrap:focus-within .field {
            border-color: rgba(224,201,138,0.55);
            box-shadow: 0 0 0 3px rgba(200,169,107,0.08), 0 0 26px rgba(200,169,107,0.07);
            background: rgba(10,12,16,0.55); }
        .field:-webkit-autofill, .field:-webkit-autofill:hover, .field:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 60px #080B12 inset !important;
            -webkit-text-fill-color: var(--ivory) !important; caret-color: var(--ivory); }

        .field-underline { position: absolute; bottom: -1px; inset-inline: 14px; height: 1.5px;
            background: linear-gradient(90deg, transparent, var(--gold-soft), transparent);
            transform: scaleX(0); transform-origin: center;
            transition: transform 0.55s cubic-bezier(0.16,1,0.3,1); pointer-events: none; }
        .field-wrap:focus-within .field-underline { transform: scaleX(1); }

        .field-icon { position: absolute; top: 50%; transform: translateY(-50%); inset-inline-start: 1.05rem;
            color: rgba(146,153,165,0.65); pointer-events: none; transition: color 0.4s; }
        .field-wrap:focus-within .field-icon, .field-wrap:hover .field-icon { color: var(--gold-soft); }

        .field-eye { position: absolute; top: 50%; transform: translateY(-50%); inset-inline-end: 0.85rem;
            color: rgba(146,153,165,0.65); cursor: pointer; padding: 0.35rem; border-radius: 8px;
            transition: color 0.3s; background: none; border: none; }
        .field-eye:hover { color: var(--gold-soft); }
        .field-eye:focus-visible { outline: 2px solid rgba(224,201,138,0.5); outline-offset: 2px; }

        .btn-enter { position: relative; width: 100%; overflow: hidden;
            background: linear-gradient(120deg, var(--gold-soft) 0%, var(--gold) 50%, #D4AF37 100%);
            background-size: 200% 200%; color: #0D111B;
            border-radius: 14px; padding: 1rem; font-weight: 700; font-size: 1rem;
            transition: transform 0.35s cubic-bezier(0.16,1,0.3,1), box-shadow 0.35s, background-position 1.2s ease;
            animation: btnBreath 5s ease-in-out infinite; border: none; cursor: pointer; }
        @keyframes btnBreath { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        .btn-enter:hover { transform: translateY(-2px);
            box-shadow: 0 14px 44px rgba(200,169,107,0.32), 0 0 0 1px rgba(224,201,138,0.35); }
        .btn-enter:active { transform: translateY(0); }
        .btn-enter:focus-visible { outline: 2px solid rgba(224,201,138,0.7); outline-offset: 3px; }
        .btn-enter[disabled] { cursor: not-allowed; opacity: 0.82; }
        .btn-enter .btn-arrow { transition: transform 0.35s cubic-bezier(0.16,1,0.3,1), opacity 0.35s; opacity: 0.35; }
        .btn-enter:hover .btn-arrow { opacity: 1; transform: translateX(-4px); }

        .spinner-min { width: 17px; height: 17px; border-radius: 50%;
            border: 2px solid rgba(11,18,32,0.25); border-top-color: #0D111B;
            animation: spinAnim 0.7s linear infinite; }
        @keyframes spinAnim { to { transform: rotate(360deg); } }

        .check-custom { appearance: none; width: 18px; height: 18px; border-radius: 5px;
            border: 1px solid rgba(146,153,165,0.4); background: rgba(8,9,11,0.4); cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            transition: all 0.3s; position: relative; }
        .check-custom:checked { background: var(--gold); border-color: var(--gold); }
        .check-custom:checked::after { content: ''; width: 9px; height: 5px;
            border-inline-start: 2px solid #0D111B; border-bottom: 2px solid #0D111B;
            transform: rotate(-45deg) translate(0.5px, -1px); }
        .check-custom:focus-visible { outline: 2px solid rgba(224,201,138,0.6); outline-offset: 2px; }

        .alert-error { background: rgba(200,60,60,0.08); border: 1px solid rgba(200,90,90,0.28);
            backdrop-filter: blur(8px); color: #F87979; border-radius: 14px; }

        .link-soft { color: var(--gold); transition: color 0.3s; }
        .link-soft:hover { color: var(--gold-soft); }

        /* ============ LOADING STATE ============ */
        .btn-loading .btn-label { display: none; }
        .btn-loading .btn-loader { display: inline-flex !important; }

        /* ============ AUTH CINEMATIC (loading / success / error) ============ */
        main { transition: opacity 0.5s ease, filter 0.5s ease; }
        body.is-verifying main { opacity: 0.55; filter: blur(1.5px); }
        body.is-verifying #authLoadGlow { opacity: 1 !important; }

        .btn-enter::after { content: ''; position: absolute; top: 0; bottom: 0; width: 45%;
            left: -40%; background: linear-gradient(105deg, transparent, rgba(255,255,255,0.25) 45%, rgba(255,255,255,0.5) 50%, transparent 60%);
            transform: skewX(-18deg); opacity: 0; transition: opacity 0.3s; pointer-events: none; }
        .btn-enter.btn-loading::after { opacity: 1; animation: btnGoldLine 1.15s linear infinite; }
        @keyframes btnGoldLine { 0% { left: -40%; } 100% { left: 120%; } }

        /* ---- success overlay ---- */
        .auth-overlay { position: fixed; inset: 0; z-index: 9000; display: none; flex-direction: column;
            align-items: center; justify-content: center; gap: 1.75rem;
            background: rgba(5,6,8,0.62); backdrop-filter: blur(7px); -webkit-backdrop-filter: blur(7px);
            opacity: 0; transition: opacity 0.4s ease; }
        .auth-overlay.show { display: flex; opacity: 1; }
        .auth-overlay.fade-black { animation: fadeBlack 0.5s ease forwards; }
        @keyframes fadeBlack { to { background: rgba(5,6,8,0.98); backdrop-filter: blur(0px); } }

        .judge-scene { position: relative; opacity: 0; transform: scale(0.95); filter: blur(4px);
            transition: opacity 0.65s ease, transform 0.65s cubic-bezier(0.16,1,0.3,1), filter 0.65s ease; }
        .judge-scene.show { opacity: 1; transform: scale(1); filter: blur(0); }
        .judge-scene.strike { animation: camShake 0.13s linear; }
        @keyframes camShake {
            0%,100% { transform: translate(0,0); }
            25% { transform: translate(-3px,2px); }
            50% { transform: translate(3px,-1px); }
            75% { transform: translate(-2px,-2px); }
        }

        .judge-scene .gavel { transform: rotate(-34deg); transform-box: view-box; transform-origin: 329px 240px; }
        .judge-scene.strike .gavel { animation: gavelHit 0.26s cubic-bezier(0.3,1.3,0.4,1) forwards; }
        @keyframes gavelHit {
            0% { transform: rotate(-34deg); }
            70% { transform: rotate(4deg) translateY(6px); }
            100% { transform: rotate(2deg) translateY(10px); }
        }

        .judge-scene .strike-flash { opacity: 0; transform: scale(0.4); transform-box: view-box; transform-origin: 334px 242px; }
        .judge-scene.strike .strike-flash { animation: flashBurst 0.45s ease-out forwards; }
        @keyframes flashBurst { 0% { opacity: 1; transform: scale(0.4); } 100% { opacity: 0; transform: scale(2.6); } }

        .judge-scene .strike-ripple { opacity: 0; transform: scale(0.3); transform-box: view-box; transform-origin: 334px 242px; }
        .judge-scene.strike .strike-ripple { animation: rippleOut 0.75s ease-out forwards; }
        @keyframes rippleOut { 0% { opacity: 0.9; transform: scale(0.3); } 100% { opacity: 0; transform: scale(2.9); } }

        .success-msg { opacity: 0; text-align: center; padding: 0 1.5rem; }
        .success-msg.show { opacity: 1; transition: opacity 0.5s ease; }
        .success-msg .msg1 { opacity: 0; }
        .success-msg .msg2 { opacity: 0; }
        .success-msg.show .msg1 { animation: msgIn 0.55s cubic-bezier(0.16,1,0.3,1) forwards; }
        .success-msg.show .msg2 { animation: msgIn 0.55s cubic-bezier(0.16,1,0.3,1) 0.28s forwards; }
        @keyframes msgIn { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

        .gold-wipe { position: absolute; inset: -60%; pointer-events: none; opacity: 0;
            background: radial-gradient(circle at 50% 50%, rgba(232,213,164,0.85) 0%, rgba(200,169,107,0.35) 40%, transparent 72%);
            transform: scale(0.2); }
        .gold-wipe.go { animation: goldWipeOut 0.6s ease-in forwards; }
        @keyframes goldWipeOut { 0% { opacity: 0; transform: scale(0.2); } 55% { opacity: 1; } 100% { opacity: 0; transform: scale(3); } }

        /* ---- error state ---- */
        .panel-shake { animation: panelNudge 0.5s cubic-bezier(0.36,0.07,0.19,0.97); }
        @keyframes panelNudge {
            0%,100% { transform: translateX(0); }
            20% { transform: translateX(-6px); }
            40% { transform: translateX(5px); }
            60% { transform: translateX(-3px); }
            80% { transform: translateX(2px); }
        }
        .field-error { border-color: rgba(200,90,90,0.55) !important;
            box-shadow: 0 0 0 3px rgba(200,90,90,0.10), 0 0 22px rgba(200,90,90,0.07) !important; }
        .error-sweep { position: absolute; top: -1px; inset-inline: 8%; height: 1px; opacity: 0;
            background: linear-gradient(90deg, transparent, rgba(200,90,90,0.75), transparent);
            transform: scaleX(0.2); }
        .error-sweep.animate { animation: errSweep 0.45s ease-in-out forwards; }
        @keyframes errSweep { 0% { opacity: 0; transform: scaleX(0.2); } 40% { opacity: 1; } 100% { opacity: 0; transform: scaleX(1); } }

        /* ============ REDUCED MOTION ============ */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            .reveal, .reveal-bg, .scales-wrap, .visual-frame, .watermark-word, .article-marks, .verses-box .verse-ring,
            .hairline-gold, .seal-rotor, #cursorGlow, .dust { opacity: 1; animation: none !important; }
            #cursorGlow { display: none; }
        }
    @keyframes orbFloat { 0%,100% { transform: translate(0,0); } 50% { transform: translate(-28px, 22px); } }
        @keyframes glowPulse { 0%,100% { opacity: 0.12; } 50% { opacity: 0.3; } }
    </style>
</head>
<body>
    {{-- ===== CINEMATIC BACKGROUND ===== --}}
    <div class="scene">
        <div class="noise-layer"></div>
        <div class="grid-faint"></div>
        <div class="ambient-orb reveal-bg" style="width:520px;height:520px;background:radial-gradient(circle, rgba(200,169,107,0.14), transparent 65%);top:-12%;inset-inline-start:60%;animation:fadeIn 1.4s ease 0.1s forwards, orbFloat 22s ease-in-out infinite;"></div>
        <div class="ambient-orb reveal-bg" style="width:460px;height:460px;background:radial-gradient(circle, rgba(32,58,100,0.5), transparent 70%);bottom:-15%;inset-inline-end:55%;animation:fadeIn 1.4s ease 0.1s forwards, orbFloat 26s ease-in-out 6s infinite;"></div>
        <div class="light-sweep"></div>
        <div id="dustLayer" class="absolute inset-0 overflow-hidden pointer-events-none"></div>
    </div>
    <div id="cursorGlow" aria-hidden="true"></div>
    <div id="authLoadGlow" aria-hidden="true" style="position:fixed;inset:0;z-index:2;pointer-events:none;opacity:0;transition:opacity 0.7s ease;background:radial-gradient(circle at 50% 42%, rgba(200,169,107,0.10), transparent 60%);"></div>

    <div class="relative z-10 min-h-screen flex flex-col-reverse lg:flex-row">

        {{-- ===== LOGIN SIDE (right in RTL, left in LTR) ===== --}}
        <main class="w-full lg:w-1/2 flex flex-col items-center justify-center px-6 sm:px-12 py-14 pb-28 lg:py-10 relative">
            <div class="w-full max-w-md mx-auto">

                {{-- brand --}}
                <div class="text-center mb-10 reveal" style="animation-delay:0.5s;">
                    <div class="relative inline-block mb-5">
                        <div class="absolute inset-0 rounded-full bg-gold/15 blur-2xl" style="animation:glowPulse 5s ease-in-out infinite;"></div>
                        <div class="relative w-16 h-16 mx-auto rounded-full border border-gold/40 bg-gradient-to-br from-charcoal to-navy flex items-center justify-center shadow-[0_0_40px_rgba(200,169,107,0.12)]">
                            @php
                                $loginLogo = null;
                                foreach (['svg', 'png', 'jpg', 'jpeg'] as $ext) {
                                    if (is_file(public_path("img/office-logo.{$ext}"))) {
                                        $loginLogo = asset("img/office-logo.{$ext}") . '?v=' . @filemtime(public_path("img/office-logo.{$ext}"));
                                        break;
                                    }
                                }
                            @endphp
                            @if($loginLogo)
                                <img src="{{ $loginLogo }}" alt="{{ $officeName }}" class="w-full h-full object-cover rounded-full">
                            @else
                                <svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm0 2.6L9.4 8.7 5 9.4l3.2 3.1L7.6 17 12 14.7 16.4 17l-.6-4.5L19 9.4l-4.4-.7L12 4.6z" fill="url(#lgGold)"/>
                                    <defs><linearGradient id="lgGold" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#F0D98A"/><stop offset="1" stop-color="#A98218"/></linearGradient></defs>
                                </svg>
                            @endif
                        </div>
                    </div>
                    <h1 class="font-editorial text-4xl sm:text-[2.6rem] font-bold leading-snug mb-2" style="color:var(--ivory);" dir="rtl">{{ $officeName }}</h1>
                    <p class="text-muted text-sm tracking-wide" style="color:var(--muted);">{{ __('app.login_title') }} · LexPro ⚖</p>
                </div>

                <div class="reveal" style="animation-delay:0.85s;">
                    <div class="panel-glass px-6 sm:px-9 py-9 relative overflow-hidden">
                        <div class="top-hair"></div>

                        <h2 class="font-editorial text-2xl sm:text-[1.7rem] font-bold leading-relaxed mb-2" style="color:var(--ivory);">مرحبًا بك في منظومتك القانونية</h2>
                        <p class="text-sm leading-relaxed mb-8" style="color:var(--muted);">سجّل الدخول للوصول الآمن إلى قضاياك ومستنداتك ومعلوماتك القانونية.</p>

                        @php $loginError = session('login_error'); @endphp
                        @if($errors->any() || $loginError)
                            <div class="alert-error mb-6 p-4 flex items-start gap-3" role="alert">
                                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div class="text-sm font-medium leading-relaxed">
                                    @if($loginError)
                                        <p>{{ $loginError }}</p>
                                    @endif
                                    @foreach($errors->all() as $error)
                                        <p>{{ $error }}</p>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}" id="loginForm" class="space-y-5">
                            @csrf

                            {{-- dynamic error box (shown by JS после проверки данных) --}}
                            <div id="jsErrorBox" class="hidden alert-error mb-2 p-4 flex items-start gap-3" role="alert" aria-live="polite" style="opacity:0;transform:translateY(-6px);transition:opacity 0.35s ease, transform 0.35s ease;">
                                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                                </svg>
                                <div class="text-sm font-medium leading-relaxed">
                                    <p id="jsErrorTitle"></p>
                                    <p id="jsErrorDetail" class="text-[13px] mt-1 opacity-85" style="font-weight:400;"></p>
                                </div>
                            </div>

                            {{-- email --}}
                            <div class="reveal" style="animation-delay:1.0s;">
                                <label for="email" class="block text-sm font-medium mb-2" style="color:rgba(244,240,232,0.75);">{{ __('app.email') }}</label>
                                <div class="field-wrap">
                                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                                        class="field" placeholder="name@example.com" aria-label="{{ __('app.email') }}">
                                    <span class="field-underline" aria-hidden="true"></span>
                                    <svg class="field-icon w-[17px] h-[17px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            </div>

                            {{-- password --}}
                            <div class="reveal" style="animation-delay:1.12s;">
                                <label for="password" class="block text-sm font-medium mb-2" style="color:rgba(244,240,232,0.75);">{{ __('app.password') }}</label>
                                <div class="field-wrap">
                                    <input type="password" id="password" name="password" required autocomplete="current-password"
                                        class="field" placeholder="{{ __('app.password') }}"
                                        aria-label="{{ __('app.password') }}" data-password-toggle>
                                    <span class="field-underline" aria-hidden="true"></span>
                                    <svg class="field-icon w-[17px] h-[17px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    <button type="button" class="field-eye" aria-label="{{ $isRtl ? 'إظهار كلمة المرور' : 'Show password' }}" aria-pressed="false" data-eye-btn>
                                        <svg data-eye-open class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <svg data-eye-closed class="w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            {{-- remember / forgot --}}
                            <div class="flex items-center justify-between pt-1 reveal" style="animation-delay:1.24s;">
                                <label class="flex items-center gap-2.5 cursor-pointer select-none group">
                                    <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }} class="check-custom">
                                    <span class="text-sm" style="color:var(--muted);">{{ __('app.remember_me') }}</span>
                                </label>
                                @if(Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="link-soft text-sm">{{ __('app.forgot_password') }}</a>
                                @endif
                            </div>

                            {{-- submit --}}
                            <div class="pt-2 reveal" style="animation-delay:1.38s;">
                                <button type="submit" id="loginBtn" class="btn-enter flex items-center justify-center gap-2.5">
                                    <span class="btn-label">{{ __('app.login_button') }}</span>
                                    <span class="btn-loader hidden items-center gap-2.5"><span class="spinner-min"></span><span>جارٍ التحقق من بيانات الدخول...</span></span>
                                    <svg class="btn-arrow w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true" style="{{ $isRtl ? '' : 'transform:rotate(180deg);' }}">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l-7 7 7 7"/>
                                    </svg>
                                </button>
                            </div>
                        </form>

                        <div class="mt-5 pt-5 border-t text-center" style="border-color:rgba(146,153,165,0.14);">
                            <p class="text-xs" style="color:rgba(146,153,165,0.6);">{{ $officeName }} — <a href="{{ url('/portfolio') }}" target="_blank" class="link-soft" rel="noopener">LexPro</a> · منظومة قانونية متكاملة</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        {{-- ===== VISUAL SIDE (left in RTL) ===== --}}
        <aside class="w-full lg:w-1/2 relative flex flex-col items-center justify-center min-h-[38vh] lg:min-h-screen px-6 py-14 lg:py-0 overflow-hidden" aria-hidden="true">

            <div class="visual-frame"></div>
            <div class="watermark-word">عدل</div>

            <div class="seal-rotor" data-depth="0.02">
                <div style="opacity:0.16;">
                    <svg viewBox="0 0 100 100" fill="none">
                        <circle cx="50" cy="50" r="46" stroke="rgba(200,169,107,0.5)" stroke-width="0.7"/>
                        <circle cx="50" cy="50" r="41" stroke="rgba(200,169,107,0.35)" stroke-width="0.5" stroke-dasharray="2 3"/>
                        <path d="M50 20 L56 40 L76 44 L60 58 L64 80 L50 70 L36 80 L40 58 L24 44 L44 40 Z" fill="none" stroke="rgba(200,169,107,0.55)" stroke-width="0.6"/>
                        <circle cx="50" cy="50" r="8" stroke="rgba(200,169,107,0.5)" stroke-width="0.6"/>
                        <path d="M50 45a5 5 0 000 10z" fill="rgba(200,169,107,0.35)"/>
                    </svg>
                </div>
            </div>

            <div class="article-marks" dir="rtl">م ٥٨<br>م ٤٤<br>م ٢٥<br>م ١٢</div>

            {{-- Oman arch traces --}}
            <svg class="arch-trace" data-depth="0.03" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                <path d="M12 100 V62 Q12 22 50 22 Q88 22 88 62 V100" fill="none" stroke="rgba(224,201,138,0.7)" stroke-width="0.3"/>
                <path d="M23 100 V66 Q23 36 50 36 Q77 36 77 66 V100" fill="none" stroke="rgba(224,201,138,0.45)" stroke-width="0.25"/>
            </svg>

            {{-- gold hairline --}}
            <div class="hairline-gold" style="top:24%;"></div>

            {{-- scales --}}
            <div class="scales-wrap relative z-10">
                <div class="scales-sway">
                    <div class="scales-float">
                        {{-- halo --}}
                        <div class="absolute inset-0 -m-16 rounded-full opacity-40" style="background:radial-gradient(circle, rgba(200,169,107,0.10), transparent 65%);"></div>
                        <svg class="w-[230px] sm:w-[270px] lg:w-[330px] h-auto relative" viewBox="0 0 320 300" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $isRtl ? 'ميزان العدالة' : 'Scales of Justice' }}">
                            <defs>
                                <linearGradient id="lexGold" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0" stop-color="#F0D98A"/>
                                    <stop offset="0.5" stop-color="#E5C158"/>
                                    <stop offset="1" stop-color="#A98218"/>
                                </linearGradient>
                                <linearGradient id="lexGoldV" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" stop-color="#F0D98A"/>
                                    <stop offset="1" stop-color="#A98218"/>
                                </linearGradient>
                                <linearGradient id="lexPan" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" stop-color="#F0D98A" stop-opacity="0.6"/>
                                    <stop offset="1" stop-color="#E5C158" stop-opacity="0.10"/>
                                </linearGradient>
                                <filter id="lexGlow" x="-40%" y="-40%" width="180%" height="180%">
                                    <feGaussianBlur stdDeviation="5" result="b"/>
                                    <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
                                </filter>
                            </defs>

                            {{-- ground glow --}}
                            <ellipse cx="160" cy="262" rx="100" ry="12" fill="rgba(200,169,107,0.15)" filter="url(#lexGlow)"/>

                            {{-- finial --}}
                            <rect x="157" y="38" width="6" height="26" rx="3" fill="url(#lexGoldV)"/>
                            <circle cx="160" cy="32" r="7" fill="url(#lexGold)"/>
                            <circle cx="160" cy="71" r="9" fill="url(#lexGold)"/>

                            {{-- pillar --}}
                            <rect x="150" y="78" width="20" height="122" rx="4" fill="url(#lexGoldV)"/>
                            <rect x="144" y="90" width="32" height="5" rx="2.5" fill="rgba(232,213,164,0.55)"/>
                            <rect x="144" y="118" width="32" height="4" rx="2" fill="rgba(232,213,164,0.4)"/>
                            <rect x="144" y="146" width="32" height="4" rx="2" fill="rgba(232,213,164,0.4)"/>

                            {{-- base --}}
                            <path d="M126 200 h68 l-9 18 h-50 z" fill="url(#lexGoldV)"/>
                            <rect x="108" y="218" width="104" height="9" rx="4" fill="url(#lexGold)"/>
                            <rect x="122" y="227" width="76" height="6" rx="3" fill="rgba(168,135,75,0.5)"/>

                            {{-- beam --}}
                            <line x1="52" y1="96" x2="268" y2="96" stroke="url(#lexGold)" stroke-width="9" stroke-linecap="round"/>
                            <circle cx="52" cy="96" r="6" fill="url(#lexGoldV)"/>
                            <circle cx="268" cy="96" r="6" fill="url(#lexGoldV)"/>

                            {{-- left chains + pan --}}
                            <path d="M66 96 L66 118 M66 118 L40 134 M66 118 L92 134" stroke="url(#lexGold)" stroke-width="2.6" stroke-linecap="round"/>
                            <path d="M24 132 L100 132 Q96 160 62 160 Q28 160 24 132 Z" fill="url(#lexPan)" stroke="url(#lexGold)" stroke-width="2.4"/>
                            <path d="M30 132 Q62 150 94 132" stroke="rgba(232,213,164,0.5)" stroke-width="0.9" fill="none"/>

                            {{-- right chains + pan --}}
                            <path d="M254 96 L254 118 M254 118 L228 134 M254 118 L280 134" stroke="url(#lexGold)" stroke-width="2.6" stroke-linecap="round"/>
                            <path d="M220 132 L296 132 Q292 160 258 160 Q224 160 220 132 Z" fill="url(#lexPan)" stroke="url(#lexGold)" stroke-width="2.4"/>
                            <path d="M226 132 Q258 150 290 132" stroke="rgba(232,213,164,0.5)" stroke-width="0.9" fill="none"/>

                            {{-- specks --}}
                            <circle cx="160" cy="192" r="2" fill="rgba(224,201,138,0.5)"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- verse --}}
            <div class="verses-box mt-9 text-center px-8 max-w-xl relative">
                <div class="verse-ring"></div>
                <p class="reveal font-verse text-2xl sm:text-3xl lg:text-[2.1rem] leading-[1.9]" dir="rtl" style="animation-delay:1.5s;color:var(--gold-soft); text-shadow:0 0 30px rgba(200,169,107,0.18);">﴿وَإِذَا حَكَمْتُم بَيْنَ النَّاسِ أَن تَحْكُمُوا بِالْعَدْلِ﴾</p>
                <p class="reveal font-verse text-sm mt-3" style="animation-delay:1.85s;color:rgba(146,153,165,0.7);">سورة النساء — 58</p>
                <div class="mt-6 mx-auto h-px w-24" style="background:linear-gradient(90deg, transparent, rgba(200,169,107,0.4), transparent);"></div>
            </div>
        </aside>
    </div>

    {{-- ===== SUCCESS CINEMATIC OVERLAY (Judge / Hammer Strike) ===== --}}
    <div id="successOverlay" class="auth-overlay" role="status" aria-live="polite" aria-hidden="true">
        <div class="judge-scene" id="judgeScene">
            <svg class="w-[min(400px,80vw)] h-auto relative" viewBox="0 0 420 320" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <defs>
                    <radialGradient id="judgeHalo" cx="0.5" cy="0.35" r="0.8">
                        <stop offset="0" stop-color="rgba(200,169,107,0.16)"/>
                        <stop offset="0.6" stop-color="rgba(200,169,107,0.05)"/>
                        <stop offset="1" stop-color="rgba(200,169,107,0)"/>
                    </radialGradient>
                    <linearGradient id="judgeGold" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#F0D98A"/>
                        <stop offset="1" stop-color="#A98218"/>
                    </linearGradient>
                    <filter id="judgeSoft" x="-50%" y="-50%" width="200%" height="200%">
                        <feGaussianBlur stdDeviation="4" result="b"/>
                        <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
                    </filter>
                </defs>

                {{-- halo --}}
                <circle cx="210" cy="150" r="170" fill="url(#judgeHalo)"/>

                {{-- bench (منصة القضاء) --}}
                <rect x="74" y="224" width="272" height="9" rx="4" fill="url(#judgeGold)" opacity="0.65"/>
                <rect x="66" y="233" width="288" height="16" rx="3" fill="#121826"/>
                <rect x="66" y="249" width="288" height="10" rx="3" fill="#0D111B"/>
                <line x1="74" y1="228" x2="346" y2="228" stroke="rgba(224,201,138,0.5)" stroke-width="1"/>
                <circle cx="210" cy="248" r="4.5" fill="rgba(224,201,138,0.45)"/>
                <circle cx="210" cy="248" r="10.5" fill="none" stroke="rgba(200,169,107,0.25)"/>

                {{-- judge silhouette: shoulder + head --}}
                <path d="M150 176 Q150 150 178 146 Q196 144 210 144 Q224 144 242 146 Q270 150 270 176 L272 220 L148 220 Z" fill="#080B12"/>
                <path d="M146 176 L274 176 L272 222 L148 222 Z" fill="#182033"/>
                <path d="M196 146 Q205 152 210 152 Q215 152 224 146" stroke="rgba(224,201,138,0.35)" stroke-width="2" fill="none"/>
                <ellipse cx="210" cy="132" rx="27" ry="29" fill="#080B12"/>
                <ellipse cx="210" cy="132" rx="27" ry="29" fill="none" stroke="rgba(224,201,138,0.18)" stroke-width="1"/>
                <path d="M196 128 Q210 136 224 128" stroke="rgba(224,201,138,0.25)" stroke-width="1.5" fill="none"/>
                <rect x="204" y="152" width="12" height="10" fill="#080B12"/>

                {{-- hammer (raised) --}}
                <g class="gavel">
                    <rect x="326.5" y="172" width="5.5" height="62" rx="2.7" fill="url(#judgeGold)"/>
                    <rect x="307" y="164" width="46" height="12" rx="3" fill="url(#judgeGold)"/>
                    <rect x="307" y="164" width="46" height="4" rx="2" fill="rgba(255,248,228,0.5)"/>
                </g>

                {{-- impact point --}}
                <circle class="strike-flash" cx="334" cy="242" r="6" fill="none" stroke="#F0D98A" stroke-width="3"/>
                <circle class="strike-ripple" cx="334" cy="242" r="6" fill="none" stroke="rgba(224,201,138,0.8)" stroke-width="2"/>

                {{-- base glow under bench --}}
                <ellipse cx="210" cy="272" rx="150" ry="10" fill="rgba(200,169,107,0.10)" filter="url(#judgeSoft)"/>
            </svg>
        </div>

        <div class="success-msg" id="successMsg">
            <p class="msg1 font-verse text-2xl sm:text-[1.8rem] font-bold" style="color:var(--gold-soft);">تم التحقق بنجاح</p>
            <p class="msg2 font-editorial text-lg sm:text-xl mt-3" style="color:var(--ivory);">مرحبًا بك في منظومتك القانونية</p>
        </div>

        <div class="gold-wipe" id="goldWipe" aria-hidden="true"></div>
    </div>

    {{-- ===== FOOTER ===== --}}
    <footer class="absolute bottom-0 inset-x-0 z-20 py-5 px-6">
        <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-center sm:justify-between gap-2 text-xs" style="color:rgba(146,153,165,0.65);">
            <p class="flex items-center gap-2"><span style="color:var(--gold);">◆</span> {{ $officeName }} — © 2026 جميع الحقوق محفوظة</p>
            <div class="flex items-center gap-5">
                <button type="button" id="soundToggle" class="link-soft font-medium flex items-center gap-1.5" aria-pressed="false" aria-label="{{ $isRtl ? 'تفعيل صوت ضربة المطرقة' : 'Enable hammer sound' }}" title="{{ $isRtl ? 'صوت ضربة المطرقة' : 'Hammer strike sound' }}">
                    <svg class="w-4 h-4" id="soundOnIcon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="display:none;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15.54 8.46a5 5 0 010 7.07"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.07 4.93a10 10 0 010 14.14"/>
                    </svg>
                    <svg class="w-4 h-4" id="soundOffIcon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M22 9l-6 6M16 9l6 6"/>
                    </svg>
                    <span id="soundLabel">{{ $isRtl ? 'الصوت' : 'Sound' }}</span>
                </button>
                <a href="{{ route('language.switch', $isRtl ? 'en' : 'ar') }}" class="link-soft font-medium" aria-label="{{ $isRtl ? 'Switch to English' : 'التبديل إلى العربية' }}">
                    {{ $isRtl ? 'English' : 'العربية' }}
                </a>
            </div>
        </div>
    </footer>

    <script nonce="{{ $cspNonce }}">
        (function () {
            'use strict';
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            /* ---------- gold dust (very subtle) ---------- */
            const dustLayer = document.getElementById('dustLayer');
            if (dustLayer && !reduced && window.matchMedia('(pointer: fine)').matches) {
                for (let i = 0; i < 14; i++) {
                    const d = document.createElement('span');
                    const size = (Math.random() * 2 + 0.6).toFixed(1);
                    d.className = 'dust';
                    d.style.cssText = 'left:' + (Math.random() * 100).toFixed(1) + '%;width:' + size + 'px;height:' + size + 'px;' +
                        'background:rgba(224,201,138,' + (Math.random() * 0.35 + 0.15).toFixed(2) + ');' +
                        'box-shadow:0 0 6px rgba(224,201,138,0.35);' +
                        'animation-duration:' + (Math.random() * 18 + 16).toFixed(1) + 's;' +
                        'animation-delay:-' + (Math.random() * 30).toFixed(1) + 's;';
                    dustLayer.appendChild(d);
                }
            }

            /* ---------- cursor-following ambient glow + parallax ---------- */
            const glow = document.getElementById('cursorGlow');
            const fine = window.matchMedia('(pointer: fine)').matches;
            if (glow && fine && !reduced) {
                let tx = window.innerWidth / 2, ty = window.innerHeight / 2, cx = tx, cy = ty, raf = null;
                const depthEls = document.querySelectorAll('[data-depth]');
                window.addEventListener('mousemove', function (e) {
                    tx = e.clientX; ty = e.clientY;
                    depthEls.forEach(function (el) {
                        const dp = parseFloat(el.getAttribute('data-depth')) || 0;
                        el.style.transform = 'translate(' + ((tx - window.innerWidth / 2) * dp).toFixed(1) + 'px,' + ((ty - window.innerHeight / 2) * dp).toFixed(1) + 'px)';
                    });
                    if (!raf) {
                        raf = requestAnimationFrame(function tick() {
                            cx += (tx - cx) * 0.08; cy += (ty - cy) * 0.08;
                            glow.style.transform = 'translate(' + (cx - 270) + 'px,' + (cy - 270) + 'px)';
                            raf = null;
                        });
                    }
                });
            } else if (glow) { glow.style.display = 'none'; }

            /* ---------- password visibility ---------- */
            const pwd = document.querySelector('[data-password-toggle]');
            const eyeBtn = document.querySelector('[data-eye-btn]');
            if (pwd && eyeBtn) {
                const openIcon = eyeBtn.querySelector('[data-eye-open]');
                const closedIcon = eyeBtn.querySelector('[data-eye-closed]');
                eyeBtn.addEventListener('click', function () {
                    const show = pwd.type === 'password';
                    pwd.type = show ? 'text' : 'password';
                    eyeBtn.setAttribute('aria-pressed', String(show));
                    openIcon.classList.toggle('hidden', show);
                    closedIcon.classList.toggle('hidden', !show);
                });
            }

            /* ---------- remember me (localStorage, unchanged logic) ---------- */
            const emailInput = document.getElementById('email');
            const rememberCheck = document.getElementById('remember');
            const saved = localStorage.getItem('lexpro_remembered_email');
            if (saved && emailInput) {
                emailInput.value = saved;
                if (rememberCheck) rememberCheck.checked = true;
            }

            /* ---------- loading state ---------- */
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const jsErrorBox = document.getElementById('jsErrorBox');
            const successOverlay = document.getElementById('successOverlay');
            const judgeScene = document.getElementById('judgeScene');
            const successMsg = document.getElementById('successMsg');
            const goldWipe = document.getElementById('goldWipe');
            const authLoadGlow = document.getElementById('authLoadGlow');

            /* ---------- sound (Web Audio, OFF by default) ---------- */
            const soundToggle = document.getElementById('soundToggle');
            let soundOn = localStorage.getItem('lexpro_sound_hammer') === '1';
            let audioCtx = null;
            function refreshSoundUI() {
                if (!soundToggle) return;
                document.getElementById('soundOnIcon').style.display = soundOn ? 'block' : 'none';
                document.getElementById('soundOffIcon').style.display = soundOn ? 'none' : 'block';
                soundToggle.setAttribute('aria-pressed', String(soundOn));
            }
            refreshSoundUI();
            if (soundToggle) soundToggle.addEventListener('click', function () {
                soundOn = !soundOn;
                localStorage.setItem('lexpro_sound_hammer', soundOn ? '1' : '0');
                refreshSoundUI();
            });
            function playHammerSound() {
                if (!soundOn || reduced) return;
                try {
                    const Ctx = window.AudioContext || window.webkitAudioContext;
                    if (!Ctx) return;
                    audioCtx = audioCtx || new Ctx();
                    if (audioCtx.state === 'suspended') audioCtx.resume();
                    const t = audioCtx.currentTime;
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(220, t);
                    osc.frequency.exponentialRampToValueAtTime(70, t + 0.12);
                    gain.gain.setValueAtTime(0.0001, t);
                    gain.gain.exponentialRampToValueAtTime(0.3, t + 0.005);
                    gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.18);
                    osc.connect(gain); gain.connect(audioCtx.destination);
                    osc.start(t); osc.stop(t + 0.2);
                    const thump = audioCtx.createOscillator();
                    const tg = audioCtx.createGain();
                    thump.type = 'triangle';
                    thump.frequency.setValueAtTime(120, t + 0.02);
                    thump.frequency.exponentialRampToValueAtTime(50, t + 0.15);
                    tg.gain.setValueAtTime(0.0001, t + 0.02);
                    tg.gain.exponentialRampToValueAtTime(0.18, t + 0.03);
                    tg.gain.exponentialRampToValueAtTime(0.0001, t + 0.2);
                    thump.connect(tg); tg.connect(audioCtx.destination);
                    thump.start(t + 0.02); thump.stop(t + 0.22);
                } catch (e) { /* sound unavailable — flow continues */ }
            }

            /* ---------- success cinematic ---------- */
            function playSuccess(navigateTo) {
                if (reduced) { navigateTo(); return; }
                successOverlay.setAttribute('aria-hidden', 'false');
                successOverlay.classList.add('show');
                goldWipe.classList.remove('go');
                successMsg.classList.remove('show');
                setTimeout(function () { judgeScene.classList.add('show'); }, 150);
                setTimeout(function () {
                    judgeScene.classList.add('strike');
                    playHammerSound();
                }, 1100);
                setTimeout(function () { successMsg.classList.add('show'); }, 1550);
                setTimeout(function () { goldWipe.classList.add('go'); }, 2150);
                setTimeout(function () { navigateTo(); }, 2750);
            }

            /* ---------- error presentation ---------- */
            function showAuthError(title, detail) {
                document.getElementById('jsErrorTitle').textContent = title;
                document.getElementById('jsErrorDetail').textContent = detail;
                jsErrorBox.classList.remove('hidden');
                requestAnimationFrame(function () {
                    jsErrorBox.style.opacity = '1';
                    jsErrorBox.style.transform = 'translateY(0)';
                });
                const panel = document.querySelector('.panel-glass');
                if (!reduced) {
                    panel.classList.remove('panel-shake', 'error-sweep');
                    void panel.offsetWidth;
                    panel.classList.add('panel-shake');
                    document.querySelectorAll('.field').forEach(function (f) {
                        f.classList.add('field-error');
                        setTimeout(function () { f.classList.remove('field-error'); }, 2600);
                    });
                    setTimeout(function () { panel.classList.remove('panel-shake'); }, 650);
                }
            }

            /* ---------- submit via AJAX (same route, logic untouched) ---------- */
            loginForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (loginBtn.hasAttribute('disabled')) return;
                loginBtn.setAttribute('disabled', 'disabled');
                loginBtn.classList.add('btn-loading');
                document.body.classList.add('is-verifying');
                jsErrorBox.classList.add('hidden');
                jsErrorBox.style.opacity = '0';

                if (rememberCheck && rememberCheck.checked && emailInput) {
                    localStorage.setItem('lexpro_remembered_email', emailInput.value);
                } else {
                    localStorage.removeItem('lexpro_remembered_email');
                }

                const form = loginForm;
                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                    credentials: 'same-origin',
                    redirect: 'follow'
                }).then(function (res) {
                    const finalUrl = res.url || window.location.href;
                    if (res.redirected && finalUrl.indexOf('/dashboard') !== -1) {
                        playSuccess(function () { window.location.href = finalUrl; });
                        return;
                    }
                    /* authentication failed — server bounced back to /login */
                    return res.text().then(function (html) {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        let title = 'بيانات الدخول غير صحيحة';
                        let detail = 'يرجى التحقق من البريد الإلكتروني وكلمة المرور والمحاولة مرة أخرى.';
                        const msg = doc.body.textContent || '';
                        if (msg.indexOf('قفل الحساب') !== -1 || msg.indexOf('تعطيل حسابك') !== -1) {
                            title = 'تم تعليق تسجيل الدخول مؤقتًا';
                            detail = 'لأسباب أمنية، يرجى المحاولة لاحقًا أو التواصل مع إدارة النظام.';
                        }
                        showAuthError(title, detail);
                        loginBtn.removeAttribute('disabled');
                        loginBtn.classList.remove('btn-loading');
                        document.body.classList.remove('is-verifying');
                    });
                }).catch(function () {
                    /* network failure — fall back to native submit */
                    form.submit();
                });
            });

            /* ---------- error shake ---------- */
            const alertBox = document.querySelector('.alert-error');
            if (alertBox && !reduced) {
                alertBox.animate([{ transform: 'translateX(0)' }, { transform: 'translateX(-7px)' }, { transform: 'translateX(7px)' }, { transform: 'translateX(-4px)' }, { transform: 'translateX(4px)' }, { transform: 'translateX(0)' }], { duration: 450, easing: 'ease-in-out' });
            }
        })();
    </script>
</body>
</html>