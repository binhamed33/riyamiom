@php
    $officeName = \App\Support\OfficeBrand::name();
    $isRtl = app()->getLocale() === 'ar';
    $loginLogo = \App\Support\OfficeBrand::logoUrl();
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
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="{{ __('app.login_title') }} — {{ $officeName }} على منصة مُداوَلة لإدارة مكاتب المحاماة.">
    <meta name="color-scheme" content="light dark">
    <title>{{ __('app.login_title') }} — {{ $officeName }} · مُداوَلة</title>

    {{-- الأيقونة: هوية مُداوَلة، ويعلوها شعار المكتب إن رفعه --}}
    <link rel="icon" href="/favicon.ico">
    @if($loginLogo)
        <link rel="icon" type="{{ \App\Support\OfficeBrand::logoMime() }}" href="{{ $loginLogo }}">
    @endif

    {{-- السمة المحفوظة تُطبَّق قبل الرسم حتى لا يومض اللون --}}
    <script nonce="{{ $cspNonce }}">
        (function () {
            try {
                var t = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

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
        /* ============================================================
           رموز الهوية — مُداوَلة
           الوضع الافتراضي فاتح (مطابق للنظام)، والداكن يُفعَّل بـ data-theme="dark"
           ============================================================ */
        :root {
            --obsidian: #080B12;
            --navy: #0D111B;
            --charcoal: #121826;

            /* ذهب زخرفي — ثابت في الوضعين */
            --gold: #C9A227;
            --gold-soft: #E3C463;
            --gold-dim: #A98218;
            /* ذهب النص والروابط — يتباين مع الخلفية */
            --gold-ink: #8C6A12;

            --bg: #F6F3EC;
            --ink: #141922;
            --muted: #5C6675;
            --label: #3A4353;
            --ivory: var(--ink);

            --scene:
                radial-gradient(120% 90% at 78% 12%, rgba(201,162,39,0.10) 0%, transparent 45%),
                radial-gradient(90% 70% at 15% 85%, rgba(214,205,186,0.55) 0%, transparent 60%),
                linear-gradient(165deg, #FBF9F4 0%, #F4F0E7 55%, #EFEADF 100%);
            --noise-op: 0.020;
            --grid-op: 0.055;

            --panel-bg: linear-gradient(158deg, rgba(255,255,255,0.92) 0%, rgba(255,255,255,0.80) 55%, rgba(248,243,231,0.86) 100%);
            --panel-shadow: 0 30px 70px rgba(46,38,18,0.13), inset 0 1px 0 rgba(255,255,255,0.9);
            --panel-edge: linear-gradient(150deg, rgba(201,162,39,0.55), rgba(201,162,39,0.10) 32%, rgba(20,25,34,0.06) 58%, rgba(201,162,39,0.40));
            --top-hair: linear-gradient(90deg, transparent, rgba(201,162,39,0.75), transparent);

            --field-bg: rgba(255,255,255,0.82);
            --field-bg-focus: #FFFFFF;
            --field-bd: rgba(20,25,34,0.16);
            --field-bd-hover: rgba(20,25,34,0.30);
            --field-bd-focus: rgba(169,130,24,0.65);
            --field-ring: 0 0 0 3px rgba(201,162,39,0.14), 0 0 20px rgba(201,162,39,0.10);
            --placeholder: rgba(20,25,34,0.42);
            --icon: rgba(20,25,34,0.40);
            --autofill: #FFFFFF;

            --hair: rgba(20,25,34,0.12);
            --stroke-faint: rgba(169,130,24,0.22);
            --watermark: rgba(169,130,24,0.16);

            --err-bg: rgba(190,40,40,0.06);
            --err-bd: rgba(170,45,45,0.32);
            --err-ink: #A32222;
        }

        [data-theme="dark"] {
            --gold: #E5C158;
            --gold-soft: #F0D98A;
            --gold-dim: #A98218;
            --gold-ink: #E5C158;

            --bg: #080B12;
            --ink: #FFFFFF;
            --muted: #94A3B8;
            --label: rgba(244,240,232,0.78);

            --scene:
                radial-gradient(120% 90% at 78% 12%, rgba(200,169,107,0.07) 0%, transparent 45%),
                radial-gradient(90% 70% at 15% 85%, rgba(11,18,32,0.9) 0%, transparent 60%),
                linear-gradient(165deg, #0D111B 0%, #080B12 55%, #080B12 100%);
            --noise-op: 0.035;
            --grid-op: 0.05;

            --panel-bg: linear-gradient(158deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.022) 55%, rgba(224,201,138,0.025) 100%);
            --panel-shadow: 0 40px 90px rgba(0,0,0,0.55), inset 0 1px 0 rgba(255,255,255,0.07);
            --panel-edge: linear-gradient(150deg, rgba(224,201,138,0.32), rgba(224,201,138,0.05) 32%, rgba(255,255,255,0.05) 58%, rgba(200,169,107,0.22));
            --top-hair: linear-gradient(90deg, transparent, rgba(224,201,138,0.55), transparent);

            --field-bg: rgba(8,9,11,0.42);
            --field-bg-focus: rgba(10,12,16,0.55);
            --field-bd: rgba(146,153,165,0.20);
            --field-bd-hover: rgba(146,153,165,0.38);
            --field-bd-focus: rgba(224,201,138,0.55);
            --field-ring: 0 0 0 3px rgba(200,169,107,0.08), 0 0 26px rgba(200,169,107,0.07);
            --placeholder: rgba(255,255,255,0.42);
            --icon: rgba(146,153,165,0.65);
            --autofill: #080B12;

            --hair: rgba(146,153,165,0.14);
            --stroke-faint: rgba(200,169,107,0.10);
            --watermark: rgba(200,169,107,0.10);

            --err-bg: rgba(200,60,60,0.08);
            --err-bd: rgba(200,90,90,0.28);
            --err-ink: #F87979;
        }

        html { background: var(--bg); }

        body {
            background: var(--bg);
            color: var(--ink);
            font-family: 'IBM Plex Sans Arabic', sans-serif;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            transition: background-color 0.4s ease, color 0.4s ease;
        }

        h1, h2, h3, .font-verse, .font-editorial { font-family: 'Amiri', serif; }

        ::selection { background: rgba(201,162,39,0.25); color: var(--ink); }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(201,162,39,0.30); border-radius: 3px; }

        /* ============ CINEMATIC BASE ============ */
        .scene { position: fixed; inset: 0; overflow: hidden; background: var(--scene); z-index: 0; }

        .noise-layer { position: absolute; inset: 0; opacity: var(--noise-op); pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3'/%3E%3C/filter%3E%3Crect width='160' height='160' filter='url(%23n)' opacity='0.6'/%3E%3C/svg%3E");
        }

        .grid-faint { position: absolute; inset: 0; opacity: var(--grid-op);
            background-image: linear-gradient(rgba(169,130,24,0.30) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(169,130,24,0.30) 1px, transparent 1px);
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
            border: 1px solid var(--stroke-faint); opacity: 0;
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
            color: transparent; -webkit-text-stroke: 1px var(--watermark);
            text-stroke: 1px var(--watermark); user-select: none; pointer-events: none;
            opacity: 0; animation: fadeIn 3s ease 1.6s forwards; }

        .seal-rotor { position: absolute; top: 8%; inset-inline-start: 7%; width: 150px; height: 150px;
            pointer-events: none; animation: sealSpin 46s linear infinite;
            opacity: 0; animation: sealSpin 46s linear infinite, fadeIn 2.4s ease 1.2s forwards; }
        @keyframes sealSpin { to { transform: rotate(360deg); } }
        .seal-rotor svg { width: 100%; height: 100%; }

        .arch-trace { position: absolute; inset: 0; pointer-events: none; opacity: 0.055; }

        .verses-box { position: relative; }
        .verse-ring { position: absolute; inset: -22px; border-radius: 50%;
            border: 1px solid var(--stroke-faint); opacity: 0; animation: fadeIn 2.5s ease 1.3s forwards; }

        /* ============ LOGIN PANEL ============ */
        .panel-glass { position: relative;
            background: var(--panel-bg);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-radius: 26px;
            box-shadow: var(--panel-shadow);
            background-clip: padding-box; }
        .panel-glass::before { content: ''; position: absolute; inset: 0; border-radius: 26px; padding: 1px;
            background: var(--panel-edge);
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor; mask-composite: exclude; pointer-events: none; }
        .panel-glass .top-hair { position: absolute; top: 0; inset-inline: 12%; height: 1px;
            background: var(--top-hair); }

        .field-wrap { position: relative; }
        .field { width: 100%;
            background: var(--field-bg);
            border: 1px solid var(--field-bd);
            border-radius: 14px;
            color: var(--ink);
            padding: 0.95rem 2.9rem 0.95rem 2.9rem;
            font-size: 0.95rem;
            transition: border-color 0.45s cubic-bezier(0.16,1,0.3,1), box-shadow 0.45s cubic-bezier(0.16,1,0.3,1), background 0.45s;
            outline: none; }
        .field::placeholder { color: var(--placeholder); }
        .field:hover { border-color: var(--field-bd-hover); }
        .field:focus, .field-wrap:focus-within .field {
            border-color: var(--field-bd-focus);
            box-shadow: var(--field-ring);
            background: var(--field-bg-focus); }
        .field:-webkit-autofill, .field:-webkit-autofill:hover, .field:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 60px var(--autofill) inset !important;
            -webkit-text-fill-color: var(--ink) !important; caret-color: var(--ink); }

        .field-underline { position: absolute; bottom: -1px; inset-inline: 14px; height: 1.5px;
            background: linear-gradient(90deg, transparent, var(--gold-soft), transparent);
            transform: scaleX(0); transform-origin: center;
            transition: transform 0.55s cubic-bezier(0.16,1,0.3,1); pointer-events: none; }
        .field-wrap:focus-within .field-underline { transform: scaleX(1); }

        .field-icon { position: absolute; top: 50%; transform: translateY(-50%); inset-inline-start: 1.05rem;
            color: var(--icon); pointer-events: none; transition: color 0.4s; }
        .field-wrap:focus-within .field-icon, .field-wrap:hover .field-icon { color: var(--gold-ink); }

        .field-eye { position: absolute; top: 50%; transform: translateY(-50%); inset-inline-end: 0.85rem;
            color: var(--icon); cursor: pointer; padding: 0.35rem; border-radius: 8px;
            transition: color 0.3s; background: none; border: none; }
        .field-eye:hover { color: var(--gold-ink); }
        .field-eye:focus-visible { outline: 2px solid rgba(224,201,138,0.5); outline-offset: 2px; }

        .btn-enter { position: relative; width: 100%; overflow: hidden;
            background: linear-gradient(120deg, var(--gold-soft) 0%, var(--gold) 50%, var(--gold-dim) 100%);
            background-size: 200% 200%; color: #12100A;
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
            border: 1px solid var(--field-bd-hover); background: var(--field-bg); cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            transition: all 0.3s; position: relative; }
        .check-custom:checked { background: var(--gold); border-color: var(--gold-dim); }
        .check-custom:checked::after { content: ''; width: 9px; height: 5px;
            border-left: 2px solid #16130A; border-bottom: 2px solid #16130A;
            transform: rotate(-45deg) translate(0.5px, -1px); }
        .check-custom:focus-visible { outline: 2px solid rgba(224,201,138,0.6); outline-offset: 2px; }

        .alert-error { background: var(--err-bg); border: 1px solid var(--err-bd);
            backdrop-filter: blur(8px); color: var(--err-ink); border-radius: 14px; }

        .link-soft { color: var(--gold-ink); transition: color 0.3s; }
        .link-soft:hover { color: var(--gold-dim); text-decoration: underline; text-underline-offset: 3px; }
        .link-soft:focus-visible { outline: 2px solid var(--gold-dim); outline-offset: 3px; border-radius: 4px; }

        /* مبدّل السمة واللغة في التذييل */
        .foot-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.3rem 0.65rem;
            border-radius: 999px; border: 1px solid var(--hair); color: var(--muted);
            background: transparent; cursor: pointer; font-size: 0.72rem; font-weight: 600;
            transition: color 0.3s, border-color 0.3s, background 0.3s; }
        .foot-btn:hover { color: var(--gold-ink); border-color: var(--gold-dim); }
        .foot-btn:focus-visible { outline: 2px solid var(--gold-dim); outline-offset: 2px; }

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
            background: rgba(5,6,8,0.72); backdrop-filter: blur(7px); -webkit-backdrop-filter: blur(7px);
            opacity: 0; transition: opacity 0.4s ease; }
        /* لحظة سينمائية واحدة في الوضعين — ألوانها ثابتة ولا ترث رموز السمة */
        .auth-overlay .msg-gold { color: #F0D98A; }
        .auth-overlay .msg-ivory { color: #FFFFFF; }
        .auth-overlay.show { display: flex; opacity: 1; }
        .auth-overlay.fade-black { animation: fadeBlack 0.5s ease forwards; }
        @keyframes fadeBlack { to { background: rgba(5,6,8,0.98); backdrop-filter: blur(0px); } }

        /* ============ مشهد النجاح: ميزان العدل يستوي ============
           لحظة واحدة تُروى بالحركة: العمود يقوم، والكفّتان تتأرجحان،
           ثم تستويان — فيُختم الحكم. كلّها CSS: لا مكتبة ولا نصّ برمجي
           إضافي، فسياسة الأمان الصارمة لا تحجب شيئاً منها. */
        .judge-scene { position: relative; opacity: 0; transform: scale(0.93); filter: blur(7px);
            transition: opacity 0.75s ease, transform 1s cubic-bezier(0.16,1,0.3,1), filter 0.75s ease; }
        .judge-scene.show { opacity: 1; transform: scale(1); filter: blur(0); }
        /* لحظة الاستواء: تقدُّم محسوس للكاميرا لا اهتزاز — الحكم يستقرّ ولا يرتجّ */
        .judge-scene.strike { animation: verdictPush 0.7s cubic-bezier(0.2,1,0.3,1); }
        @keyframes verdictPush {
            0% { transform: scale(1); } 32% { transform: scale(1.035); } 100% { transform: scale(1); }
        }

        /* أشعّة خلفية — عمق لا زخرفة */
        .j-rays { opacity: 0; transform-box: view-box; transform-origin: 210px 40px; }
        .judge-scene.show .j-rays { animation: jFadeIn 1.8s ease 0.15s forwards; }
        @keyframes jFadeIn { to { opacity: 1; } }

        /* الخاتم المحفور: يهبط مائلاً ثم يستوي ويثبت */
        .j-seal { opacity: 0; transform-box: view-box; transform-origin: 210px 176px;
            transform: rotate(-15deg) scale(0.84); }
        .judge-scene.show .j-seal { animation: jSealLock 2s cubic-bezier(0.16,1,0.3,1) 0.1s forwards; }
        @keyframes jSealLock { to { opacity: 1; transform: rotate(0deg) scale(1); } }
        .j-seal-dots { transform-box: view-box; transform-origin: 210px 176px; }
        .judge-scene.show .j-seal-dots { animation: jSealTurn 46s linear 1.6s infinite; }
        @keyframes jSealTurn { to { transform: rotate(360deg); } }

        /* القاعدة ثم العمود: البناء من الأرض إلى الأعلى */
        .j-base { opacity: 0; }
        .judge-scene.show .j-base { animation: jFadeIn 0.7s ease 0.2s forwards; }
        .j-column { transform-box: view-box; transform-origin: 210px 292px; transform: scaleY(0); }
        .judge-scene.show .j-column { animation: jColumnRise 0.9s cubic-bezier(0.16,1,0.3,1) 0.3s forwards; }
        @keyframes jColumnRise { to { transform: scaleY(1); } }
        .j-finial { opacity: 0; transform-box: view-box; transform-origin: 210px 100px; transform: scale(0.2); }
        .judge-scene.show .j-finial { animation: jFinial 0.6s cubic-bezier(0.34,1.5,0.5,1) 1.05s forwards; }
        @keyframes jFinial { to { opacity: 1; transform: scale(1); } }

        /* العارضة تتأرجح وتستوي — والكفّتان تُعاكسان الميل فتبقيان أفقيتين */
        .j-beam-fade { opacity: 0; }
        .judge-scene.show .j-beam-fade { animation: jFadeIn 0.5s ease 1s forwards; }
        .j-beam { transform-box: view-box; transform-origin: 210px 108px; transform: rotate(-13deg); }
        .judge-scene.show .j-beam { animation: jBeamSettle 1.6s cubic-bezier(0.33,0.9,0.3,1) 1.05s forwards; }
        @keyframes jBeamSettle {
            0%   { transform: rotate(-13deg); }
            21%  { transform: rotate(8.6deg); }
            42%  { transform: rotate(-5.1deg); }
            62%  { transform: rotate(2.7deg); }
            80%  { transform: rotate(-1.2deg); }
            92%  { transform: rotate(0.4deg); }
            100% { transform: rotate(0deg); }
        }
        .j-pan-l { transform-box: view-box; transform-origin: 112px 108px; transform: rotate(13deg); }
        .j-pan-r { transform-box: view-box; transform-origin: 308px 108px; transform: rotate(13deg); }
        .judge-scene.show .j-pan-l,
        .judge-scene.show .j-pan-r { animation: jPanLevel 1.6s cubic-bezier(0.33,0.9,0.3,1) 1.05s forwards; }
        @keyframes jPanLevel {
            0%   { transform: rotate(13deg); }
            21%  { transform: rotate(-8.6deg); }
            42%  { transform: rotate(5.1deg); }
            62%  { transform: rotate(-2.7deg); }
            80%  { transform: rotate(1.2deg); }
            92%  { transform: rotate(-0.4deg); }
            100% { transform: rotate(0deg); }
        }

        /* ---- لحظة الاستواء ---- */
        /* خطّ الأفق: يُرسم من المحور إلى الطرفين فيُعلن التوازن */
        .j-level { opacity: 0; transform-box: view-box; transform-origin: 210px 108px; transform: scaleX(0.08); }
        .judge-scene.strike .j-level { animation: jLevelLock 0.9s cubic-bezier(0.16,1,0.3,1) forwards; }
        @keyframes jLevelLock {
            0%   { opacity: 0; transform: scaleX(0.08); }
            40%  { opacity: 0.95; }
            100% { opacity: 0.3; transform: scaleX(1); }
        }
        .j-flash { opacity: 0; transform-box: view-box; transform-origin: 210px 108px; transform: scale(0.3); }
        .judge-scene.strike .j-flash { animation: jFlash 0.55s ease-out forwards; }
        @keyframes jFlash { 0% { opacity: 1; transform: scale(0.3); } 100% { opacity: 0; transform: scale(2.4); } }
        .j-ring { opacity: 0; transform-box: view-box; transform-origin: 210px 108px; transform: scale(0.25); }
        .judge-scene.strike .j-ring-1 { animation: jRipple 1s ease-out forwards; }
        .judge-scene.strike .j-ring-2 { animation: jRipple 1.25s ease-out 0.2s forwards; }
        @keyframes jRipple { 0% { opacity: 0.85; transform: scale(0.25); } 100% { opacity: 0; transform: scale(3); } }
        /* الخاتم يشتعل عند الحكم */
        .judge-scene.strike .j-seal-ring { animation: jSealGlow 1.2s ease-out forwards; }
        @keyframes jSealGlow { 0% { stroke-opacity: 0.26; } 22% { stroke-opacity: 0.95; } 100% { stroke-opacity: 0.45; } }
        /* بريق يمرّ على المعدن بعد أن يستقرّ */
        .j-sheen { opacity: 0; transform-box: view-box; transform: translateX(-280px); }
        .judge-scene.strike .j-sheen { animation: jSheen 1.15s cubic-bezier(0.4,0,0.25,1) 0.15s forwards; }
        @keyframes jSheen {
            0% { opacity: 0; transform: translateX(-280px); }
            16% { opacity: 0.9; } 80% { opacity: 0.9; }
            100% { opacity: 0; transform: translateX(320px); }
        }
        /* عمود ضوء ينزل على الميزان لحظة الحكم */
        .j-shaft { opacity: 0; }
        .judge-scene.strike .j-shaft { animation: jShaft 1.5s ease-out forwards; }
        @keyframes jShaft { 0% { opacity: 0; } 16% { opacity: 0.9; } 100% { opacity: 0.22; } }
        /* توهّج يتمدّد من المحور */
        .j-bloom { opacity: 0; transform-box: view-box; transform-origin: 210px 150px; transform: scale(0.4); }
        .judge-scene.strike .j-bloom { animation: jBloom 1.4s ease-out forwards; }
        @keyframes jBloom {
            0% { opacity: 0; transform: scale(0.4); }
            28% { opacity: 1; }
            100% { opacity: 0; transform: scale(2.1); }
        }
        /* بعد الاستقرار: طفوٌ بطيء — المشهد حيّ لا جامد */
        .j-float { transform-box: view-box; transform-origin: 210px 292px; }
        .judge-scene.show .j-float { animation: jFloat 7.5s ease-in-out 3.4s infinite; }
        @keyframes jFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }

        /* غبار ذهبي يصعد من القاعدة */
        .j-mote { opacity: 0; transform-box: view-box; }
        .judge-scene.strike .j-mote { animation: jMote 2s ease-out forwards; }
        @keyframes jMote {
            0%   { opacity: 0; transform: translateY(8px) scale(0.4); }
            15%  { opacity: 1; }
            100% { opacity: 0; transform: translateY(-132px) scale(1.25); }
        }
        .judge-scene.strike .j-mote:nth-of-type(1) { animation-delay: 0.02s; }
        .judge-scene.strike .j-mote:nth-of-type(2) { animation-delay: 0.16s; }
        .judge-scene.strike .j-mote:nth-of-type(3) { animation-delay: 0.09s; }
        .judge-scene.strike .j-mote:nth-of-type(4) { animation-delay: 0.28s; }
        .judge-scene.strike .j-mote:nth-of-type(5) { animation-delay: 0.21s; }
        .judge-scene.strike .j-mote:nth-of-type(6) { animation-delay: 0.36s; }
        .judge-scene.strike .j-mote:nth-of-type(7) { animation-delay: 0.13s; }
        .judge-scene.strike .j-mote:nth-of-type(8) { animation-delay: 0.44s; }
        .judge-scene.strike .j-mote:nth-of-type(9)  { animation-delay: 0.06s; }
        .judge-scene.strike .j-mote:nth-of-type(10) { animation-delay: 0.32s; }
        .judge-scene.strike .j-mote:nth-of-type(11) { animation-delay: 0.19s; }
        .judge-scene.strike .j-mote:nth-of-type(12) { animation-delay: 0.5s; }

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
            .reveal, .reveal-bg, .scales-wrap, .visual-frame, .watermark-word,
            .verses-box .verse-ring, .brand-mark,
            .hairline-gold, .seal-rotor, #cursorGlow, .dust,
            .j-rays, .j-seal, .j-base, .j-beam-fade, .j-finial { opacity: 1; animation: none !important; }
            /* الميزان يظهر مستوياً بلا تأرجح لمن طلب تقليل الحركة */
            .j-column { transform: scaleY(1) !important; }
            .j-beam, .j-pan-l, .j-pan-r, .j-finial { transform: none !important; }
            .j-seal { transform: none !important; }
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

    <div class="relative z-10 min-h-screen flex flex-col">
      <div class="flex-1 flex flex-col lg:flex-row">

        {{-- ===== LOGIN SIDE (right in RTL, left in LTR) ===== --}}
        <main class="w-full lg:w-1/2 flex flex-col items-center justify-center px-6 sm:px-12 py-12 lg:py-10 relative">
            <div class="w-full max-w-md mx-auto">

                {{-- brand --}}
                <div class="text-center mb-10 reveal" style="animation-delay:0.5s;">
                    <div class="relative inline-block mb-5">
                        <div class="absolute inset-0 rounded-full bg-gold/15 blur-2xl" style="animation:glowPulse 5s ease-in-out infinite;"></div>
                        <div class="brand-mark relative w-16 h-16 mx-auto rounded-full border flex items-center justify-center overflow-hidden"
                             style="border-color:var(--stroke-faint);background:var(--panel-bg);box-shadow:0 0 40px rgba(201,162,39,0.14);">
                            @if($loginLogo)
                                {{-- شعار المكتب: يُقدَّم من نسخة هذا المكتب وحده — لا يمكن لمكتب آخر بلوغه --}}
                                <img src="{{ $loginLogo }}" alt="{{ __('app.office_logo_alt', ['office' => $officeName]) }}" class="w-full h-full object-cover">
                            @else
                                <svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2a10 10 0 100 20 10 10 0 000-20zm0 2.6L9.4 8.7 5 9.4l3.2 3.1L7.6 17 12 14.7 16.4 17l-.6-4.5L19 9.4l-4.4-.7L12 4.6z" fill="url(#mdMarkGold)"/>
                                    <defs><linearGradient id="mdMarkGold" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#E3C463"/><stop offset="1" stop-color="#A98218"/></linearGradient></defs>
                                </svg>
                            @endif
                        </div>
                    </div>
                    <h1 class="font-editorial text-4xl sm:text-[2.6rem] font-bold leading-snug mb-2" style="color:var(--ink);" dir="rtl">{{ $officeName }}</h1>
                    {{-- مُداوَلة = هوية المنتج؛ النقر عليها يفتح موقع المنصة --}}
                    <p class="text-sm tracking-wide" style="color:var(--muted);">
                        {{ __('app.login_title') }} ·
                        <a href="https://dev.riyami.om/" target="_blank" rel="noopener" class="link-soft font-semibold">مُداوَلة</a> ⚖
                    </p>
                </div>

                <div class="reveal" style="animation-delay:0.85s;">
                    <div class="panel-glass px-6 sm:px-9 py-9 relative overflow-hidden">
                        <div class="top-hair"></div>

                        <h2 class="font-editorial text-2xl sm:text-[1.7rem] font-bold leading-relaxed mb-2" style="color:var(--ink);">{{ __('app.login_welcome') }}</h2>
                        <p class="text-sm leading-relaxed mb-8" style="color:var(--muted);">{{ __('app.login_lead') }}</p>

                        @php $loginError = session('login_error') ?: session('error'); @endphp
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
                                <label for="email" class="block text-sm font-medium mb-2" style="color:var(--label);">{{ __('app.email') }}</label>
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
                                <label for="password" class="block text-sm font-medium mb-2" style="color:var(--label);">{{ __('app.password') }}</label>
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
                                @else
                                    {{-- لا توجد استعادة ذاتية في هذا النظام: نوجّه المستخدم لمدير المكتب بدل رابط معطّل --}}
                                    <button type="button" class="link-soft text-sm" data-forgot-hint aria-expanded="false" aria-controls="forgotHint">{{ __('app.forgot_password') }}</button>
                                @endif
                            </div>

                            <p id="forgotHint" class="hidden text-xs leading-relaxed rounded-xl px-3.5 py-3"
                               style="color:var(--muted);background:var(--field-bg);border:1px solid var(--hair);">
                                {{ __('app.forgot_password_hint') }}
                            </p>

                            {{-- submit --}}
                            <div class="pt-2 reveal" style="animation-delay:1.38s;">
                                <button type="submit" id="loginBtn" class="btn-enter flex items-center justify-center gap-2.5">
                                    <span class="btn-label">{{ __('app.login_button') }}</span>
                                    <span class="btn-loader hidden items-center gap-2.5"><span class="spinner-min"></span><span>{{ __('app.login_verifying') }}</span></span>
                                    <svg class="btn-arrow w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true" style="{{ $isRtl ? '' : 'transform:rotate(180deg);' }}">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l-7 7 7 7"/>
                                    </svg>
                                </button>
                            </div>
                        </form>

                        <div class="mt-5 pt-5 border-t text-center" style="border-color:rgba(146,153,165,0.14);">
                            <p class="text-xs" style="color:rgba(146,153,165,0.6);">{{ $officeName }} — <a href="https://dev.riyami.om/" target="_blank" class="link-soft" rel="noopener">مُداوَلة</a> · {{ __('app.login_tagline') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        {{-- ===== VISUAL SIDE (left in RTL) ===== --}}
        <aside class="w-full lg:w-1/2 relative flex flex-col items-center justify-center min-h-[38vh] lg:min-h-0 px-6 py-14 lg:py-0 overflow-hidden" aria-hidden="true">

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
                                <linearGradient id="mdGold" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0" stop-color="#F0D98A"/>
                                    <stop offset="0.5" stop-color="#E5C158"/>
                                    <stop offset="1" stop-color="#A98218"/>
                                </linearGradient>
                                <linearGradient id="mdGoldV" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" stop-color="#F0D98A"/>
                                    <stop offset="1" stop-color="#A98218"/>
                                </linearGradient>
                                <linearGradient id="mdPan" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" stop-color="#F0D98A" stop-opacity="0.55"/>
                                    <stop offset="0.55" stop-color="#E5C158" stop-opacity="0.22"/>
                                    <stop offset="1" stop-color="#A98218" stop-opacity="0.45"/>
                                </linearGradient>
                                <filter id="mdGlow" x="-40%" y="-40%" width="180%" height="180%">
                                    <feGaussianBlur stdDeviation="5" result="b"/>
                                    <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
                                </filter>
                            </defs>

                            {{-- ground glow --}}
                            <ellipse cx="160" cy="268" rx="116" ry="13" fill="rgba(200,169,107,0.16)" filter="url(#mdGlow)"/>
                            <ellipse cx="160" cy="268" rx="62" ry="7" fill="rgba(240,217,138,0.18)"/>

                            {{-- finial --}}
                            <rect x="157" y="86" width="6" height="18" rx="3" fill="url(#mdGoldV)"/>
                            <circle cx="160" cy="80" r="8" fill="url(#mdGold)"/>
                            <circle cx="157.5" cy="77.5" r="3" fill="rgba(240,217,138,0.75)"/>

                            {{-- beam --}}
                            <line x1="40" y1="104" x2="280" y2="104" stroke="url(#mdGold)" stroke-width="8" stroke-linecap="round"/>
                            <line x1="40" y1="101" x2="280" y2="101" stroke="rgba(240,217,138,0.5)" stroke-width="1.5" stroke-linecap="round"/>
                            <circle cx="40" cy="104" r="6.5" fill="url(#mdGoldV)"/>
                            <circle cx="280" cy="104" r="6.5" fill="url(#mdGoldV)"/>
                            <circle cx="38.5" cy="102" r="2" fill="rgba(240,217,138,0.8)"/>
                            <circle cx="278.5" cy="102" r="2" fill="rgba(240,217,138,0.8)"/>
                            <circle cx="160" cy="104" r="7.5" fill="url(#mdGold)"/>
                            <circle cx="158" cy="101.5" r="2.8" fill="rgba(240,217,138,0.7)"/>

                            {{-- pillar --}}
                            <rect x="149" y="104" width="22" height="118" rx="4" fill="url(#mdGoldV)"/>
                            <rect x="153" y="112" width="4" height="102" rx="2" fill="rgba(240,217,138,0.30)"/>
                            <rect x="143" y="124" width="34" height="6" rx="3" fill="rgba(232,213,164,0.5)"/>
                            <rect x="145" y="160" width="30" height="5" rx="2.5" fill="rgba(232,213,164,0.4)"/>
                            <rect x="145" y="194" width="30" height="5" rx="2.5" fill="rgba(232,213,164,0.4)"/>

                            {{-- base --}}
                            <path d="M134 222 L186 222 L198 240 L122 240 Z" fill="url(#mdGoldV)"/>
                            <rect x="138" y="226" width="44" height="2" rx="1" fill="rgba(240,217,138,0.4)"/>
                            <rect x="106" y="240" width="108" height="10" rx="5" fill="url(#mdGold)"/>
                            <rect x="90" y="252" width="140" height="11" rx="5.5" fill="url(#mdGold)"/>
                            <rect x="90" y="254" width="140" height="3" rx="1.5" fill="rgba(240,217,138,0.45)"/>

                            {{-- left shackle + chains + pan --}}
                            <line x1="64" y1="106" x2="64" y2="122" stroke="url(#mdGold)" stroke-width="2.2" stroke-linecap="round"/>
                            <circle cx="64" cy="122" r="3.5" fill="url(#mdGold)"/>
                            <path d="M64 122 L24 152 M64 122 L104 152 M64 122 L64 142" stroke="url(#mdGold)" stroke-width="2.2" stroke-linecap="round"/>
                            <path d="M24 152 A40 9.5 0 0 1 104 152" stroke="rgba(232,213,164,0.65)" stroke-width="1.6" fill="none"/>
                            <path d="M24 152 C28 176 40 188 64 188 C88 188 100 176 104 152 Z" fill="url(#mdPan)" stroke="url(#mdGold)" stroke-width="2.2"/>
                            <path d="M24 152 A40 9.5 0 0 0 104 152" stroke="url(#mdGold)" stroke-width="2.4" fill="none"/>
                            <path d="M31 158 Q64 174 97 158" stroke="rgba(240,217,138,0.35)" stroke-width="1" fill="none"/>

                            {{-- right shackle + chains + pan --}}
                            <line x1="256" y1="106" x2="256" y2="122" stroke="url(#mdGold)" stroke-width="2.2" stroke-linecap="round"/>
                            <circle cx="256" cy="122" r="3.5" fill="url(#mdGold)"/>
                            <path d="M256 122 L216 152 M256 122 L296 152 M256 122 L256 142" stroke="url(#mdGold)" stroke-width="2.2" stroke-linecap="round"/>
                            <path d="M216 152 A40 9.5 0 0 1 296 152" stroke="rgba(232,213,164,0.65)" stroke-width="1.6" fill="none"/>
                            <path d="M216 152 C220 176 232 188 256 188 C280 188 292 176 296 152 Z" fill="url(#mdPan)" stroke="url(#mdGold)" stroke-width="2.2"/>
                            <path d="M216 152 A40 9.5 0 0 0 296 152" stroke="url(#mdGold)" stroke-width="2.4" fill="none"/>
                            <path d="M223 158 Q256 174 289 158" stroke="rgba(240,217,138,0.35)" stroke-width="1" fill="none"/>

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

    {{-- ===== SUCCESS CINEMATIC OVERLAY (ميزان العدل يستوي) ===== --}}
    <div id="successOverlay" class="auth-overlay" role="status" aria-live="polite" aria-hidden="true">
        <div class="judge-scene" id="judgeScene">
            <svg class="w-[min(430px,84vw)] h-auto relative" viewBox="0 0 420 340" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <defs>
                    <radialGradient id="jHalo" cx="0.5" cy="0.42" r="0.72">
                        <stop offset="0" stop-color="rgba(200,169,107,0.22)"/>
                        <stop offset="0.5" stop-color="rgba(200,169,107,0.07)"/>
                        <stop offset="1" stop-color="rgba(200,169,107,0)"/>
                    </radialGradient>
                    {{-- ذهب مصقول: ضوء في الأعلى وظلّ في الأسفل، لا لون مسطّح --}}
                    <linearGradient id="jGold" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#FFF4D2"/>
                        <stop offset="0.34" stop-color="#EBD08A"/>
                        <stop offset="0.63" stop-color="#B8912E"/>
                        <stop offset="1" stop-color="#7E6013"/>
                    </linearGradient>
                    <linearGradient id="jGoldH" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0" stop-color="#8A6A14"/>
                        <stop offset="0.24" stop-color="#F4E3AC"/>
                        <stop offset="0.5" stop-color="#C8A96B"/>
                        <stop offset="0.76" stop-color="#F4E3AC"/>
                        <stop offset="1" stop-color="#8A6A14"/>
                    </linearGradient>
                    <linearGradient id="jSheenGrad" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0" stop-color="rgba(255,255,255,0)"/>
                        <stop offset="0.5" stop-color="rgba(255,252,238,0.9)"/>
                        <stop offset="1" stop-color="rgba(255,255,255,0)"/>
                    </linearGradient>
                    <linearGradient id="jShaftGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="rgba(255,246,214,0.55)"/>
                        <stop offset="0.55" stop-color="rgba(240,217,138,0.18)"/>
                        <stop offset="1" stop-color="rgba(240,217,138,0)"/>
                    </linearGradient>
                    <linearGradient id="jRayGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="rgba(240,217,138,0.22)"/>
                        <stop offset="1" stop-color="rgba(240,217,138,0)"/>
                    </linearGradient>
                    <filter id="jSoft" x="-60%" y="-60%" width="220%" height="220%">
                        <feGaussianBlur stdDeviation="7" result="b"/>
                        <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
                    </filter>
                    {{-- يُقصّ البريق على المعدن وحده وهو مستوٍ، فلا يسيل خارجه --}}
                    <clipPath id="jMetal">
                        <rect x="204" y="100" width="12" height="196" rx="3"/>
                        <rect x="150" y="288" width="120" height="9" rx="3"/>
                        <rect x="136" y="297" width="148" height="10" rx="3"/>
                        <rect x="120" y="307" width="180" height="11" rx="4"/>
                        <rect x="108" y="104" width="204" height="8" rx="4"/>
                        <path d="M74 150 Q112 186 150 150 Z"/>
                        <path d="M270 150 Q308 186 346 150 Z"/>
                    </clipPath>
                </defs>

                {{-- هالة وأشعّة: عمق خلف المشهد --}}
                <circle cx="210" cy="168" r="176" fill="url(#jHalo)"/>
                <g class="j-rays">
                    <path d="M210 24 L172 250 L248 250 Z" fill="url(#jRayGrad)" opacity="0.55"/>
                    <path d="M210 24 L128 258 L166 258 Z" fill="url(#jRayGrad)" opacity="0.3"/>
                    <path d="M210 24 L254 258 L292 258 Z" fill="url(#jRayGrad)" opacity="0.3"/>
                </g>

                {{-- عمود الضوء --}}
                <path class="j-shaft" d="M196 0 L224 0 L262 300 L158 300 Z" fill="url(#jShaftGrad)" filter="url(#jSoft)"/>

                {{-- الخاتم المحفور --}}
                <g class="j-seal">
                    <circle class="j-seal-ring" cx="210" cy="176" r="150" fill="none"
                            stroke="#C8A96B" stroke-opacity="0.26" stroke-width="1"/>
                    <circle class="j-seal-ring" cx="210" cy="176" r="132" fill="none"
                            stroke="#C8A96B" stroke-opacity="0.16" stroke-width="0.75"/>
                    <circle class="j-seal-dots" cx="210" cy="176" r="141" fill="none"
                            stroke="#E0C98A" stroke-opacity="0.32" stroke-width="2.5"
                            stroke-linecap="round" stroke-dasharray="0.5 17"/>
                </g>

                {{-- توهّج الحكم --}}
                <circle class="j-bloom" cx="210" cy="150" r="130" fill="url(#jHalo)"/>

                {{-- القاعدة: ثلاث درجات تنزل عرضاً كلّما هبطت --}}
                <g class="j-base">
                    <rect x="120" y="307" width="180" height="11" rx="4" fill="url(#jGoldH)"/>
                    <rect x="136" y="297" width="148" height="10" rx="3" fill="url(#jGold)"/>
                    <rect x="150" y="288" width="120" height="9" rx="3" fill="url(#jGoldH)" opacity="0.9"/>
                    <ellipse cx="210" cy="326" rx="152" ry="9" fill="rgba(200,169,107,0.12)" filter="url(#jSoft)"/>
                </g>

                <g class="j-float">
                {{-- العمود --}}
                <g class="j-column">
                    <rect x="204" y="100" width="12" height="192" rx="3" fill="url(#jGoldH)"/>
                    <rect x="207" y="100" width="2.5" height="192" fill="rgba(255,248,228,0.45)"/>
                </g>

                {{-- الميزان --}}
                <g class="j-beam-fade">
                    <g class="j-beam">
                        <rect x="108" y="104" width="204" height="8" rx="4" fill="url(#jGoldH)"/>
                        <rect x="108" y="105.5" width="204" height="2" rx="1" fill="rgba(255,248,228,0.5)"/>
                        <circle cx="112" cy="108" r="5" fill="url(#jGold)"/>
                        <circle cx="308" cy="108" r="5" fill="url(#jGold)"/>

                        {{-- الكفّة اليسرى --}}
                        <g class="j-pan-l">
                            <path d="M112 110 L80 150 M112 110 L144 150 M112 110 L112 150"
                                  stroke="#C8A96B" stroke-opacity="0.75" stroke-width="1.2"/>
                            <path d="M74 150 Q112 186 150 150 Z" fill="url(#jGold)" opacity="0.95"/>
                            <path d="M74 150 L150 150" stroke="#FFF4D2" stroke-opacity="0.6" stroke-width="2" stroke-linecap="round"/>
                            <path d="M74 150 Q112 186 150 150" fill="none" stroke="#7E6013" stroke-opacity="0.5" stroke-width="1"/>
                        </g>

                        {{-- الكفّة اليمنى --}}
                        <g class="j-pan-r">
                            <path d="M308 110 L276 150 M308 110 L340 150 M308 110 L308 150"
                                  stroke="#C8A96B" stroke-opacity="0.75" stroke-width="1.2"/>
                            <path d="M270 150 Q308 186 346 150 Z" fill="url(#jGold)" opacity="0.95"/>
                            <path d="M270 150 L346 150" stroke="#FFF4D2" stroke-opacity="0.6" stroke-width="2" stroke-linecap="round"/>
                            <path d="M270 150 Q308 186 346 150" fill="none" stroke="#7E6013" stroke-opacity="0.5" stroke-width="1"/>
                        </g>
                    </g>
                </g>

                {{-- تاج المحور --}}
                <g class="j-finial">
                    <circle cx="210" cy="100" r="9" fill="url(#jGold)"/>
                    <circle cx="210" cy="97" r="3" fill="rgba(255,248,228,0.75)"/>
                </g>

                </g>

                {{-- خطّ الاستواء: يُرسم من المحور فيُعلن التوازن --}}
                <rect class="j-level" x="66" y="107.2" width="288" height="1.6" rx="0.8" fill="#F0D98A"/>

                {{-- وميض المحور وحلقتاه --}}
                <circle class="j-flash" cx="210" cy="108" r="14" fill="none" stroke="#FFF8E4" stroke-width="4"/>
                <circle class="j-ring j-ring-1" cx="210" cy="108" r="18" fill="none" stroke="rgba(240,217,138,0.85)" stroke-width="2"/>
                <circle class="j-ring j-ring-2" cx="210" cy="108" r="26" fill="none" stroke="rgba(200,169,107,0.55)" stroke-width="1.2"/>

                {{-- بريق يمرّ على المعدن المستقرّ --}}
                <g clip-path="url(#jMetal)">
                    <rect class="j-sheen" x="-70" y="80" width="70" height="250" fill="url(#jSheenGrad)" transform="skewX(-18)"/>
                </g>

                {{-- غبار ذهبي يصعد من القاعدة --}}
                <g class="j-motes">
                    <circle class="j-mote" cx="146" cy="300" r="2.4" fill="#F0D98A"/>
                    <circle class="j-mote" cx="176" cy="308" r="1.7" fill="#E0C98A"/>
                    <circle class="j-mote" cx="204" cy="296" r="2.6" fill="#FFF4D2"/>
                    <circle class="j-mote" cx="232" cy="310" r="1.8" fill="#E0C98A"/>
                    <circle class="j-mote" cx="262" cy="299" r="2.2" fill="#F0D98A"/>
                    <circle class="j-mote" cx="128" cy="312" r="1.5" fill="#C8A96B"/>
                    <circle class="j-mote" cx="288" cy="305" r="2" fill="#F0D98A"/>
                    <circle class="j-mote" cx="210" cy="314" r="1.6" fill="#FFF4D2"/>
                    <circle class="j-mote" cx="160" cy="292" r="1.9" fill="#FFF4D2"/>
                    <circle class="j-mote" cx="248" cy="292" r="1.4" fill="#C8A96B"/>
                    <circle class="j-mote" cx="112" cy="302" r="1.7" fill="#E0C98A"/>
                    <circle class="j-mote" cx="302" cy="313" r="1.5" fill="#F0D98A"/>
                </g>
            </svg>
        </div>

        <div class="success-msg" id="successMsg">
            <p class="msg1 msg-gold font-verse text-2xl sm:text-[1.8rem] font-bold">تم التحقق بنجاح</p>
            <p class="msg2 msg-ivory font-editorial text-lg sm:text-xl mt-3">مرحبًا بك في منظومتك القانونية</p>
        </div>

        <div class="gold-wipe" id="goldWipe" aria-hidden="true"></div>
    </div>

    {{-- ===== FOOTER ===== --}}
    <footer class="relative z-20 py-5 px-6">
        <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-center sm:justify-between gap-2 text-xs" style="color:var(--muted);">
            <p class="flex items-center gap-2 flex-wrap justify-center">
                <span style="color:var(--gold);">◆</span>
                <span>{{ $officeName }} — © {{ date('Y') }}</span>
                <span aria-hidden="true">·</span>
                <a href="https://dev.riyami.om/" target="_blank" rel="noopener" class="link-soft font-semibold">مُداوَلة</a>
            </p>
            <div class="flex items-center gap-3">
                <button type="button" class="foot-btn" data-theme-toggle aria-pressed="false"
                        aria-label="{{ $isRtl ? 'تبديل الوضع الليلي' : 'Toggle dark mode' }}">
                    <svg data-theme-sun class="w-3.5 h-3.5 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4l1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                    </svg>
                    <svg data-theme-moon class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>
                    </svg>
                    <span data-theme-label>{{ $isRtl ? 'ليلي' : 'Dark' }}</span>
                </button>
                <a href="{{ route('language.switch', $isRtl ? 'en' : 'ar') }}" class="foot-btn" aria-label="{{ $isRtl ? 'Switch to English' : 'التبديل إلى العربية' }}">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/>
                    </svg>
                    {{ $isRtl ? 'English' : 'العربية' }}
                </a>
            </div>
        </div>
    </footer>
    </div>

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

            /* ---------- theme (نفس مفتاح التخزين المستخدم داخل النظام) ---------- */
            const themeBtn = document.querySelector('[data-theme-toggle]');
            if (themeBtn) {
                const sun = themeBtn.querySelector('[data-theme-sun]');
                const moon = themeBtn.querySelector('[data-theme-moon]');
                const label = themeBtn.querySelector('[data-theme-label]');
                const isAr = document.documentElement.getAttribute('lang') === 'ar';
                const paint = function (t) {
                    const dark = t === 'dark';
                    document.documentElement.setAttribute('data-theme', t);
                    themeBtn.setAttribute('aria-pressed', String(dark));
                    if (sun) sun.classList.toggle('hidden', !dark);
                    if (moon) moon.classList.toggle('hidden', dark);
                    if (label) label.textContent = dark ? (isAr ? 'نهاري' : 'Light') : (isAr ? 'ليلي' : 'Dark');
                };
                let current = 'light';
                try { current = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light'; } catch (e) {}
                paint(current);
                themeBtn.addEventListener('click', function () {
                    current = current === 'dark' ? 'light' : 'dark';
                    paint(current);
                    try { localStorage.setItem('theme', current); } catch (e) {}
                });
            }

            /* ---------- تلميح استعادة كلمة المرور (لا استعادة ذاتية في النظام) ---------- */
            const forgotBtn = document.querySelector('[data-forgot-hint]');
            const forgotHint = document.getElementById('forgotHint');
            if (forgotBtn && forgotHint) {
                forgotBtn.addEventListener('click', function () {
                    const open = forgotHint.classList.toggle('hidden') === false;
                    forgotBtn.setAttribute('aria-expanded', String(open));
                });
            }

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
            const saved = localStorage.getItem('mudawala_remembered_email') || localStorage.getItem('lexpro_remembered_email');
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

            /* ---------- success cinematic ---------- */
            function playSuccess(navigateTo) {
                if (reduced) { navigateTo(); return; }
                successOverlay.setAttribute('aria-hidden', 'false');
                successOverlay.classList.add('show');
                goldWipe.classList.remove('go');
                successMsg.classList.remove('show');
                // التوقيت يتبع الحركة: العمود يقوم، ثم تتأرجح الكفّتان
                // وتستويان عند 2.6ث — وعندها وحدها يقع الحكم.
                setTimeout(function () { judgeScene.classList.add('show'); }, 120);
                setTimeout(function () { judgeScene.classList.add('strike'); }, 2620);
                setTimeout(function () { successMsg.classList.add('show'); }, 2980);
                setTimeout(function () { goldWipe.classList.add('go'); }, 3760);
                setTimeout(function () { navigateTo(); }, 4300);
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
                    localStorage.setItem('mudawala_remembered_email', emailInput.value);
                    localStorage.removeItem('lexpro_remembered_email'); // ترحيل المفتاح القديم
                } else {
                    localStorage.removeItem('mudawala_remembered_email');
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
                    /* نجاح = الخادم أعاد التوجيه بعيداً عن صفحة الدخول (تُحترم intended) */
                    var landedOnLogin = true;
                    try { landedOnLogin = new URL(finalUrl, window.location.origin).pathname.replace(/\/+$/, '') === '/login'; } catch (e) {}
                    if (res.redirected && !landedOnLogin) {
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