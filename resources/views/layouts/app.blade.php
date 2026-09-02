@php
    // هوية المكتب من مصدر واحد (شعار المكتب ملك المكتب وحده)
    $officeName = \App\Support\OfficeBrand::name();
    $isRtl = app()->getLocale() === 'ar';
    $officeLogo = \App\Support\OfficeBrand::logoUrl();
    $officeLogoType = \App\Support\OfficeBrand::logoMime();
    // المظهر محفوظ للمستخدم نفسه ويُرسم من الخادم — فلا وميض ولا تأثير على زميله
    $themeKey = \App\Support\Appearance::themeKey();
    $palette = \App\Support\Appearance::palette();
    $primary = \App\Support\Appearance::primary();
    $appearanceMode = \App\Support\Appearance::mode();
    // تنبيهات الاشتراك إدارية: تظهر لمدير المكتب فقط — لا للمحامين والموظفين
    $subscriptionInfo = auth()->check() && auth()->user()->isAdmin()
        ? app(\App\Services\SubscriptionService::class)->info()
        : null;
@endphp
<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}" data-theme="{{ $appearanceMode }}" data-palette="{{ $themeKey }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script nonce="{{ $cspNonce }}">
        (function () {
            try {
                // الخادم رسم الوضع المحفوظ للمستخدم؛ نُبقي النسخة المحلية متطابقة
                localStorage.setItem('theme', '{{ $appearanceMode }}');
                /* حالة الشريط تُقرأ قبل أول إطار: قراءتها داخل Alpine
                   وحده تجعله يُرسم مفتوحاً ثم ينطوي أمام العين. */
                try { window.__sbOpen = localStorage.getItem('sidebarOpen') !== '0'; }
                catch (e) { window.__sbOpen = true; }
                var fs = parseInt(localStorage.getItem('fontSize') || '100', 10);
                if (fs !== 100 && [100, 110, 125].indexOf(fs) !== -1) {
                    document.documentElement.style.fontSize = (16 * fs / 100) + 'px';
                }
            } catch (e) {}
        })();
    </script>

    <title>@yield('title', __('app.dashboard')) - {{ $officeName }}</title>

    <link rel="icon" href="/favicon.ico">
    @if($officeLogo)
        <link rel="icon" type="{{ $officeLogoType }}" href="{{ $officeLogo }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link nonce="{{ $cspNonce }}" rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">

    <script nonce="{{ $cspNonce }}" src="https://cdn.tailwindcss.com"></script>
    <script nonce="{{ $cspNonce }}" defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script nonce="{{ $cspNonce }}" src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script nonce="{{ $cspNonce }}">
        // كل لون سمة يُقرأ من متغيّر CSS مع دعم درجة الشفافية،
        // فتغيير السمة يصبح تغيير سمة data-palette بلا إعادة تحميل
        function mdAccent(v) {
            return function (opts) {
                var a = opts && opts.opacityValue;
                return a === undefined ? 'rgb(var(' + v + '))' : 'rgb(var(' + v + ') / ' + a + ')';
            };
        }
        tailwind.config = {
            theme: {
                extend: {
                    colors: {

                        gold: { DEFAULT: mdAccent('--accent-rgb'), light: mdAccent('--accent-light-rgb'), dark: mdAccent('--accent-dark-rgb'), deep: mdAccent('--accent-deep-rgb'), hover: mdAccent('--accent-hover-rgb') },

                        primary: { DEFAULT: mdAccent('--accent-dark-rgb'), hover: mdAccent('--accent-rgb'), dark: mdAccent('--accent-deep-rgb'), light: mdAccent('--accent-light-rgb') },

                        background: '#F3EFE7',

                        surface: '#FCFAF6',

                        text: { DEFAULT: '#111827', secondary: '#4B5563', muted: '#6B7280' },

                        border: '#E4DFD4',

                        success: { DEFAULT: '#16A34A', light: '#DCFCE7', dark: '#166534' },

                        error: { DEFAULT: '#DC2626', light: '#FEE2E2', dark: '#991B1B' },

                        warning: { DEFAULT: '#D97706', light: '#FEF3C7', dark: '#92400E' },

                        info: { DEFAULT: '#2563EB', light: '#DBEAFE', dark: '#1E40AF' },

                        ai: { DEFAULT: '#7C3AED', light: '#EDE9FE', dark: '#5B21B6' },

                        gray: {

                            50: '#F9FAFB', 100: '#F3F4F6', 200: '#E5E7EB', 300: '#D1D5DB',

                            400: '#9CA3AF', 500: '#6B7280', 600: '#4B5563', 700: '#374151',

                            800: '#1F2937', 900: '#111827',

                        },

                        amber: {

                            50: '#FFFBEB', 100: '#FEF3C7', 200: '#FDE68A', 300: '#FCD34D',

                            400: '#FBBF24', 500: '#F59E0B', 600: '#D97706', 700: '#B45309',

                            800: '#92400E', 900: '#78350F',

                        },

                        red: {

                            50: '#FEF2F2', 100: '#FEE2E2', 200: '#FECACA', 300: '#FCA5A5',

                            400: '#F87171', 500: '#EF4444', 600: '#DC2626', 700: '#B91C1C',

                            800: '#991B1B', 900: '#7F1D1D',

                        },

                        green: {

                            50: '#F0FDF4', 100: '#DCFCE7', 200: '#BBF7D0', 300: '#86EFAC',

                            400: '#4ADE80', 500: '#22C55E', 600: '#16A34A', 700: '#15803D',

                            800: '#166534', 900: '#14532D',

                        },

                        emerald: {

                            50: '#ECFDF5', 100: '#D1FAE5', 200: '#A7F3D0', 300: '#6EE7B7',

                            400: '#34D399', 500: '#10B981', 600: '#059669', 700: '#047857',

                            800: '#065F46', 900: '#064E3B',

                        },

                        blue: {

                            50: '#EFF6FF', 100: '#DBEAFE', 200: '#BFDBFE', 300: '#93C5FD',

                            400: '#60A5FA', 500: '#3B82F6', 600: '#2563EB', 700: '#1D4ED8',

                            800: '#1E40AF', 900: '#1E3A8A',

                        },

                        purple: {

                            50: '#FAF5FF', 100: '#F3E8FF', 200: '#E9D5FF', 300: '#D8B4FE',

                            400: '#C084FC', 500: '#A855F7', 600: '#9333EA', 700: '#7E22CE',

                            800: '#6B21A8', 900: '#581C87',

                        },

                        yellow: {

                            50: '#FEFCE8', 100: '#FEF9C3', 200: '#FEF08A', 300: '#FDE047',

                            400: '#FACC15', 500: '#EAB308', 600: '#CA8A04', 700: '#A16207',

                            800: '#854D0E', 900: '#713F12',

                        },

                        orange: {

                            50: '#FFF7ED', 100: '#FFEDD5', 200: '#FED7AA', 300: '#FDBA74',

                            400: '#FB923C', 500: '#F97316', 600: '#EA580C', 700: '#C2410C',

                            800: '#9A3412', 900: '#7C2D12',

                        },

                        indigo: {

                            50: '#EEF2FF', 100: '#E0E7FF', 200: '#C7D2FE', 300: '#A5B4FC',

                            400: '#818CF8', 500: '#6366F1', 600: '#4F46E5', 700: '#4338CA',

                            800: '#3730A3', 900: '#312E81',

                        },

                        teal: {

                            50: '#F0FDFA', 100: '#CCFBF1', 200: '#99F6E4', 300: '#5EEAD4',

                            400: '#2DD4BF', 500: '#14B8A6', 600: '#0D9488', 700: '#0F766E',

                            800: '#115E59', 900: '#134E4A',

                        },

                        cyan: {

                            50: '#ECFEFF', 100: '#CFFAFE', 200: '#A5F3FC', 300: '#67E8F9',

                            400: '#22D3EE', 500: '#06B6D4', 600: '#0891B2', 700: '#0E7490',

                            800: '#155E75', 900: '#164E63',

                        },

                        sky: {

                            50: '#F0F9FF', 100: '#E0F2FE', 200: '#BAE6FD', 300: '#7DD3FC',

                            400: '#38BDF8', 500: '#0EA5E9', 600: '#0284C7', 700: '#0369A1',

                            800: '#075985', 900: '#0C4A6E',

                        },

                        violet: {

                            50: '#F5F3FF', 100: '#EDE9FE', 200: '#DDD6FE', 300: '#C4B5FD',

                            400: '#A78BFA', 500: '#8B5CF6', 600: '#7C3AED', 700: '#6D28D9',

                            800: '#5B21B6', 900: '#4C1D95',

                        },

                        pink: {

                            50: '#FDF2F8', 100: '#FCE7F3', 200: '#FBCFE8', 300: '#F9A8D4',

                            400: '#F472B6', 500: '#EC4899', 600: '#DB2777', 700: '#BE185D',

                            800: '#9D174D', 900: '#831843',

                        },

                        rose: {

                            50: '#FFF1F2', 100: '#FFE4E6', 200: '#FECDD3', 300: '#FDA4AF',

                            400: '#FB7185', 500: '#F43F5E', 600: '#E11D48', 700: '#BE123C',

                            800: '#9F1239', 900: '#881337',

                        },

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
        /* رموز السمة — مصدرها تفضيل المستخدم المحفوظ، ويُعاد رسمها من الخادم */
{!! \App\Support\Appearance::paletteCss() !!}

        :root {
            /* كل الرموز مشتقّة من ثلاثيات RGB أعلاه، فتتبدّل مع السمة فوراً */
            --accent: rgb(var(--accent-rgb));
            --accent-light: rgb(var(--accent-light-rgb));
            --accent-hover: rgb(var(--accent-hover-rgb));
            --accent-dark: rgb(var(--accent-dark-rgb));
            --accent-deep: rgb(var(--accent-deep-rgb));
            --accent-a06: rgb(var(--accent-rgb) / 0.06);
            --accent-a08: rgb(var(--accent-rgb) / 0.08);
            --accent-a10: rgb(var(--accent-rgb) / 0.10);
            --accent-a12: rgb(var(--accent-rgb) / 0.12);
            --accent-a15: rgb(var(--accent-rgb) / 0.15);
            --accent-a20: rgb(var(--accent-rgb) / 0.20);
            --accent-a30: rgb(var(--accent-rgb) / 0.30);
            --accent-a40: rgb(var(--accent-rgb) / 0.40);
        }
    </style>
    <style>
        [x-cloak] { display: none !important; }
        @media (max-width: 767px) {
            [dir="rtl"] aside[data-mobile-cloak] { transform: translateX(100%) !important; transition: none !important; }
            [dir="ltr"] aside[data-mobile-cloak] { transform: translateX(-100%) !important; transition: none !important; }
        }
        /* الرسوم البيانية لا تتجاوز عرض حاويتها على أي مقاس */
        canvas { max-width: 100%; }

        /* ===== لوحة المظهر ===== */
        .md-appearance { position: fixed; top: 4.25rem; z-index: 50; width: 22rem;
            background: #FFFFFF; border: 1px solid #E2E6EC; border-radius: 1rem;
            box-shadow: 0 24px 60px rgba(17,24,39,0.16); padding: 1.25rem; }
        /* أدوات الترويسة على اليسار في RTL وعلى اليمين في LTR، فاللوحة
           تنفتح في الاتجاه الذي تتّسع فيه الشاشة — بخصائص فيزيائية لأن
           المنطقية كانت تثبّتها على الجهة الخاطئة فتُقصّ خارج الشاشة. */
        .md-appearance { max-width: calc(100vw - 1.5rem); }
        @media (min-width: 768px) {
            /* أدوات الترويسة على اليسار في RTL وعلى اليمين في LTR */
            [dir="rtl"] .md-appearance { left: 1rem; right: auto; }
            [dir="ltr"] .md-appearance { right: 1rem; left: auto; }
        }
        [data-theme="dark"] .md-appearance { background: #121826; border-color: #252D3D; }

        @media (max-width: 767px) {
            /* على الهاتف: ورقة سفلية بعرض الشاشة بدل قائمة صغيرة */
            .md-appearance { left: 0; right: 0; bottom: 0; top: auto; width: auto;
                max-width: none; margin: 0; border-radius: 22px 22px 0 0; max-height: 88vh; overflow-y: auto;
                box-shadow: 0 -18px 50px rgba(0,0,0,0.22); }
        }
        .md-appearance-grab { width: 42px; height: 4px; border-radius: 999px; background: #D8DCE3; margin: -0.4rem auto 0.9rem; }
        [data-theme="dark"] .md-appearance-grab { background: #333C4E; }

        .md-mode-btn { display: flex; align-items: center; justify-content: center; gap: 0.55rem;
            padding: 0.7rem 0.5rem; border-radius: 0.85rem; border: 1.5px solid #E2E6EC;
            font-size: 0.82rem; font-weight: 700; color: #6B7280; background: transparent;
            transition: border-color 0.2s, color 0.2s, background 0.2s; min-height: 44px; }
        .md-mode-btn:hover { border-color: #C9CDD6; }
        .md-mode-btn.is-on { border-color: var(--accent); color: var(--accent-dark); background: var(--accent-a08); }
        [data-theme="dark"] .md-mode-btn { border-color: #252D3D; color: #94A3B8; }
        [data-theme="dark"] .md-mode-btn.is-on { color: var(--accent-light); }
        .md-mode-swatch { width: 18px; height: 18px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.12); flex: none; }
        .md-mode-light { background: linear-gradient(135deg, #FFFFFF 50%, #F1F3F7 50%); }
        .md-mode-dark { background: linear-gradient(135deg, #1B2231 50%, #080B12 50%); border-color: rgba(255,255,255,0.18); }

        /* بطاقة السمة: معاينة مصغّرة بألوانها هي، لا مجرد نقطة لون */
        .md-theme-card { padding: 0.5rem; border-radius: 0.85rem; border: 1.5px solid #E2E6EC;
            background: transparent; transition: border-color 0.2s, transform 0.15s; text-align: start; }
        .md-theme-card:hover { border-color: #C9CDD6; transform: translateY(-1px); }
        .md-theme-card.is-on { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-a12); }
        [data-theme="dark"] .md-theme-card { border-color: #252D3D; }

        .md-preview { display: flex; height: 52px; border-radius: 0.55rem; overflow: hidden;
            border: 1px solid rgba(17,24,39,0.08); background: #F7F8FA; }
        [data-theme="dark"] .md-preview { background: #0D111B; border-color: rgba(255,255,255,0.08); }
        .md-preview-side { width: 26%; background: rgb(var(--accent-dark-rgb)); }
        .md-preview-main { flex: 1; padding: 5px; display: flex; flex-direction: column; gap: 4px; }
        .md-preview-bar { height: 6px; border-radius: 3px; background: rgb(var(--accent-rgb)); width: 70%; }
        .md-preview-row { height: 5px; border-radius: 3px; background: rgba(17,24,39,0.12); width: 90%; }
        [data-theme="dark"] .md-preview-row { background: rgba(255,255,255,0.14); }
        .md-preview-btn { height: 9px; border-radius: 4px; background: rgb(var(--accent-light-rgb)); width: 45%; margin-top: auto; }

        .md-theme-name { display: flex; align-items: center; justify-content: space-between; gap: 0.35rem;
            margin-top: 0.5rem; font-size: 0.75rem; font-weight: 700; color: #4B5563; }
        [data-theme="dark"] .md-theme-name { color: #CBD5E1; }
        .md-theme-card.is-on .md-theme-name { color: var(--accent-dark); }
        [data-theme="dark"] .md-theme-card.is-on .md-theme-name { color: var(--accent-light); }

        /* ═══ حلقة التركيز: من يتنقّل بلوحة المفاتيح يجب أن يرى أين هو ═══
           لم يكن في النظام حلقةُ تركيز ظاهرة إلا على حقول .form-input،
           فمن يتنقّل بـTab — ومنهم من لا يستطيع استعمال الفأرة — كان
           يتحرّك أعمى بين الأزرار والروابط. focus-visible وحدها: النقر
           بالفأرة لا يُظهرها، فلا يتغيّر شيء لمن يستعمل الفأرة. */
        :focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
            border-radius: 6px;
        }

        /* المتصفحات التي لا تعرف focus-visible تُبقي سلوكها الافتراضي */
        a:focus-visible, button:focus-visible, [role="button"]:focus-visible,
        input:focus-visible, select:focus-visible, textarea:focus-visible,
        summary:focus-visible, [tabindex]:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }

        /* الأزرار الذهبية: حلقة داكنة فوقها لتُرى على أرضيتها */
        .bg-primary:focus-visible, .bg-gold-dark:focus-visible {
            outline-color: var(--accent-deep, #8C6A12);
            outline-offset: 3px;
        }

        /* شريط التقدّم العلوي */
        #mdProgress { position: fixed; top: 0; inset-inline-start: 0; height: 3px; width: 0;
            background: linear-gradient(90deg, var(--accent-dark), var(--accent), var(--accent-light));
            box-shadow: 0 0 10px var(--accent-a40); z-index: 9999; opacity: 0;
            transition: width 0.18s ease, opacity 0.25s ease; pointer-events: none; }
        @media (prefers-reduced-motion: reduce) { #mdProgress { transition: none; } }

        @media (max-width: 767px) {
            /* أهداف لمس مريحة: لا عنصر تفاعلي أقل من 44 بكسل */
            .sidebar-link, .bottom-nav a, .md-touch { min-height: 44px; }
            /* الجداول العريضة: تمرير أفقي سلس مع تلميح بصري */
            .md-scroll-x { -webkit-overflow-scrolling: touch; scroll-behavior: smooth; }
            /* رابط ممتدّ على البطاقة كلها */
            .md-stretch::after { content: ''; position: absolute; inset: 0; z-index: 1; }
            /* شرائح التبويب وأزرار الاختيار: هدف لمس مقبول */
            .md-tab, label:has(input[type="radio"]), label:has(input[type="checkbox"]) { min-height: 40px; display: inline-flex; align-items: center; }
            /* روابط التذييل وأزرار الطيّ: مساحة لمس معقولة بلا تغيير في الشكل */
            footer a { display: inline-flex; align-items: center; min-height: 40px; }
            .md-touch-pad { display: flex; align-items: center; min-height: 44px; }
            /* الشرائح الدائرية (تبويبات ومرشِّحات) تصبح أهدافاً قابلة للّمس */
            a[class*="rounded-full"], button[class*="rounded-full"] { min-height: 40px; display: inline-flex; align-items: center; }
        }

        /* أسطح فاتحة تريح العين.
           كان التعليق يعد بعاجٍ دافئ والقيمة #FFFFFF — بياضٌ صرف.
           شاشة كاملة منه تحت ضوء المكتب تُتعب النظر بعد ساعة.
           الأسطح هنا أخفض من البياض الصرف بقليل ومائلة إلى الدفء،
           والنصّ عليها لا يزال فوق 15:1 — أي أعلى بكثير من حدّ
           AAA. لا شيء غير لون الخلفية يتغيّر. */
        :root {
            --surface: #FCFAF6;   /* البطاقات واللوحات */
            --surface-2: #F7F4ED;  /* أرضية الصفحة */
            --surface-3: #F2EEE5;  /* الأشرطة الثانوية */
            --zebra: #F8F5EF;      /* السطر المتناوب في القوائم */
        }

        /* ══ صفوفٌ متناوبةُ اللون بدل خطٍّ فاصل ══════════════════
           الخطّ بين كل سطرين يصنع على قائمةٍ طويلة شبكةً من الخطوط
           الأفقيّة تُتعب العين وتزاحم النصّ. والتناوب يفصل بلا خط:
           فرقٌ في الأرضيّة يكفي لتمييز السطر ولا يُقرأ لوناً ثانياً —
           وهو ما تفعله جداول الحساب منذ الورق.

           التناوب بـ:where فلا وزنَ له، ويبقى التظليل عند المرور فوق
           السطر أعلى منه فيغلبه. ولولا ذلك لبقي السطر الزوجيّ على
           لونه تحت المؤشّر، فيبدو أنّ نصف الأسطر لا يستجيب. */
        .md-zebra > :where(*:nth-child(even)) { background-color: var(--zebra); }

        /* والجداول كذلك — كلُّها، بقاعدةٍ واحدة لا بتعديل أربعين جدولاً
           بيدٍ تنسى واحداً. و`:where` يجرّدها من وزنها، فيغلبها كلُّ ما
           هو أخصّ: تظليلُ المرور، وأرضيّةُ صفٍّ ملوّنٍ بحالته (مهمّة
           متأخّرة، رصيدٌ سالب) — تبقى على لونها ولا يبتلعها التناوب. */
        tbody > :where(tr:nth-child(even)) { background-color: var(--zebra); }

        /* ══ نقاطُ «يكتب» ══════════════════════════════════════════ */
        .md-dots { display: inline-flex; align-items: center; gap: 4px; height: 8px; }
        .md-dots i {
            width: 6px; height: 6px; border-radius: 99px; background: #10B981;
            display: block; animation: mdDot 1.25s ease-in-out infinite;
        }
        .md-dots i:nth-child(2) { animation-delay: .18s; }
        .md-dots i:nth-child(3) { animation-delay: .36s; }
        @keyframes mdDot {
            0%, 60%, 100% { opacity: .25; transform: translateY(0); }
            30%           { opacity: 1;   transform: translateY(-3px); }
        }
        /* من طلب تقليل الحركة يرى النقاط ثابتةً تتبدّل شفافيّتها فقط */
        @media (prefers-reduced-motion: reduce) {
            .md-dots i { animation: mdDotFade 1.4s ease-in-out infinite; }
            @keyframes mdDotFade { 0%, 100% { opacity: .3 } 50% { opacity: 1 } }
        }
        .bg-white, .bg-white\/60, .bg-white\/70, .bg-white\/80, .bg-white\/90 { background-color: var(--surface); }
        .bg-gray-50 { background-color: var(--surface-2); }
        .bg-gray-100 { background-color: var(--surface-3); }

        /* الحدود الرمادية كانت باردة (#E5E7EB) وسط أسطح دافئة فتبدو
           زرقاء بجانبها. هذه هي نفسها في القيمة، دافئة في المزاج. */
        .border-gray-100 { border-color: #EFEAE0; }
        .border-gray-200 { border-color: #E7E2D8; }
        .divide-gray-100 > * + * { border-color: #EFEAE0; }
        .divide-gray-200 > * + * { border-color: #E7E2D8; }

        /* رمادي 400 كان 2.19:1 فوق أفتح أسطحنا — ولم يكن مقروءاً فوق
           البياض الصرف قبلها (2.54:1). هذه القيمة ترفعه إلى 3.63:1
           وتبقيه أفتح من رمادي 500 فلا تنهار مراتب النصّ. */
        .text-gray-400 { color: #767C86; }

        * { font-family: 'Tajawal', sans-serif; }
        h1, h2, h3, h4, h5, h6, .font-heading { font-family: 'Cairo', sans-serif; }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--surface-3); }
        ::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-hover); }

        .sidebar-link { transition: all 0.15s ease; position: relative; }
        .sidebar-link:hover {
            background: var(--accent-a08);
            color: var(--accent);
        }
        .sidebar-link.active {
            background: var(--accent-a10);
            color: var(--accent);
        }
        .sidebar-link.active::before {
            content: '';
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: var(--accent);
            border-radius: 3px;
        }
        [dir="rtl"] .sidebar-link.active::before { right: -2px; }
        [dir="ltr"] .sidebar-link.active::before { left: -2px; }

        .sb-closed .sidebar-link span,
        .sb-closed .sidebar-section-title,
        .sb-closed .sidebar-logo-text,
        .sb-closed .sidebar-footer-text { display: none; }
        .sb-closed .sidebar-link { justify-content: center; padding-left: 0; padding-right: 0; }
        .sb-closed .sidebar-link svg { margin: 0; }

        .content-area { transition: margin-inline-start 0.3s cubic-bezier(0.4, 0, 0.2, 1); }

        /* Sidebar width toggles (not reliant on Tailwind CDN JIT) */
        /* ── أقسام الشريط الجانبي تُطوى بالنقر ──────────────────
           الجسم يُقاس بارتفاعه الفعلي لا برقمٍ نكتبه: قسمٌ يُضاف إليه
           رابطٌ غداً لا يُقصّ عند حدٍّ نسيناه. */
        .sb-section-body {
            overflow: hidden;
            transition: max-height 0.28s cubic-bezier(.4, 0, .2, 1), opacity 0.18s ease;
        }
        .sb-section-body[data-collapsed="1"] { max-height: 0 !important; opacity: 0; }
        .sb-section-head {
            display: flex; align-items: center; justify-content: space-between;
            width: 100%; cursor: pointer; user-select: none;
            border-radius: 0.5rem; transition: background 0.15s ease;
        }
        .sb-section-head:hover { background: rgba(148, 163, 184, 0.08); }
        .sb-section-head:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .sb-section-chevron {
            width: 0.85rem; height: 0.85rem; flex: none; margin-inline-end: 0.75rem;
            opacity: 0.5; transition: transform 0.28s cubic-bezier(.4, 0, .2, 1);
        }
        .sb-section-head[aria-expanded="false"] .sb-section-chevron { transform: rotate(-90deg); }

        /* الشريط مطويّاً: العناوين مخفيّة أصلاً، فلو بقي قسمٌ مطويّاً
           اختفت أيقوناته بلا عنوانٍ يُعيدها. تُفتح كلها قسراً. */
        .sb-closed .sb-section-body { max-height: none !important; opacity: 1 !important; }
        .sb-closed .sb-section-head { pointer-events: none; }

        @media (prefers-reduced-motion: reduce) {
            .sb-section-body, .sb-section-chevron { transition: none; }
        }

        .sb-open { width: 16rem; }
        .sb-closed { width: 72px; }
        [dir="rtl"] .ct-open { margin-right: 16rem; }
        [dir="ltr"] .ct-open { margin-left: 16rem; }
        [dir="rtl"] .ct-closed { margin-right: 72px; }
        [dir="ltr"] .ct-closed { margin-left: 72px; }
        @media (max-width: 767px) {
            [dir="rtl"] .ct-open, [dir="rtl"] .ct-closed { margin-right: 0 !important; }
            [dir="ltr"] .ct-open, [dir="ltr"] .ct-closed { margin-left: 0 !important; }
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.2s ease-out; }

        @supports not (view-transition-name: root) {
            .page-enter { animation: fadeIn 0.25s ease-out; }
        }

        /* --- Theme base (applied before first paint) --- */
        html { background-color: #F3EFE7; }
        html[data-theme="dark"] { background-color: #080B12; color-scheme: dark; }

        /* --- Premium page transitions (View Transitions API) --- */
        @view-transition { navigation: auto; }

        /* انتقالٌ سلس نقي: تلاشٍ قصير مع ارتفاع طفيف — بلا ألوان ولا
           تكبير ولا مرشحات، فالحركة الملحوظة تُقرأ اهتزازاً لا سلاسة */
        ::view-transition-old(root) {
            animation: pageOut 120ms ease-out both;
        }
        ::view-transition-new(root) {
            animation: pageIn 200ms ease-out both;
        }

        @keyframes pageOut {
            to { opacity: 0; }
        }
        @keyframes pageIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
            100% { transform: translateX(100%); opacity: 0; }
        }

        @media (prefers-reduced-motion: reduce) {
            ::view-transition-old(root), ::view-transition-new(root) { animation: none !important; }
            ::view-transition-new(root)::after { display: none; }
        }

        @keyframes slideDown { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
        .animate-slide-down { animation: slideDown 0.15s ease-out; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .badge-pulse { animation: pulse 2s infinite; }

        .glass-border { border: 1px solid var(--accent-a08); }
        .glass-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--accent-a08);
        }

        .dropdown-dark {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        .gold-shimmer {
            background: linear-gradient(90deg, transparent, var(--accent-a08), transparent);
            background-size: 200% 100%;
            animation: shimmer 3s infinite;
        }

.btn-gold {
            background: linear-gradient(135deg, var(--accent-hover), var(--accent) 45%, var(--accent-dark));
            color: #111827;
            transition: all 0.15s ease;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, var(--accent-hover), var(--accent));
            transform: translateY(-1px);
            box-shadow: 0 4px 15px var(--accent-a30);
        }
        .btn-gold:active {
            transform: translateY(0);
        }

        .btn-ghost {
            background: rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.6);
            transition: all 0.2s ease;
        }
        .btn-ghost:hover {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.8);
        }

        .card-hover {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .stat-card {
            background: linear-gradient(135deg, var(--accent-a20), transparent);
            border: 1px solid var(--accent-a08);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover {
            background: linear-gradient(135deg, var(--accent-a06), var(--accent-a20));
            border-color: var(--accent-a15);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        /* Form inputs */
        .form-input {
            transition: all 0.2s ease;
            border: 1px solid rgba(0,0,0,0.12);
        }
        .form-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-a15);
        }

        /* Table refinements */
        .table-row-hover tr {
            transition: background 0.15s ease;
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        /* Icon container for consistent sizing */
        .icon-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        .icon-container svg {
            width: 20px;
            height: 20px;
        }

        /* Card refinements */
        .card-premium {
            background: #fff;
            border: 1px solid var(--accent-a15);
            border-radius: 16px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-premium:hover {
            border-color: var(--accent-a30);
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }

        /* Top nav underline */
        .nav-link {
            position: relative;
            transition: color 0.2s ease;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--accent);
            transform: scaleX(0);
            transition: transform 0.2s ease;
        }
        .nav-link:hover::after,
        .nav-link.active::after {
            transform: scaleX(1);
        }

::-webkit-scrollbar-track { background: var(--surface-3); }
        ::-webkit-scrollbar-thumb { background: #C9CDD6; border-radius: 8px; }
        input[type=checkbox] { accent-color: var(--accent-dark) !important; }
        .ts-wrapper .ts-control { background: #FFFFFF !important; border: 1px solid #E2E6EC !important; color: #111827 !important; }
        .ts-wrapper .ts-control input { color: #111827 !important; }
        .ts-wrapper .ts-control:hover { border-color: #C9CDD6 !important; }
        .ts-wrapper.focus .ts-control { border-color: var(--accent-dark) !important; box-shadow: 0 0 0 2px var(--accent-a30) !important; }
        .ts-wrapper .ts-dropdown { background: #FFFFFF !important; border: 1px solid #E2E6EC !important; color: #111827 !important; }
        .ts-wrapper .ts-dropdown .option { color: #4B5563 !important; }
        .ts-wrapper .ts-dropdown .option.active { background: var(--accent-a12) !important; color: var(--accent-dark) !important; }
        .ts-wrapper .ts-dropdown .option:hover { background: var(--accent-a06) !important; }
        .ts-wrapper .ts-dropdown .option.highlight { background: var(--accent-a06) !important; }
        .ts-wrapper .ts-dropdown .create { color: var(--accent-dark) !important; }
        .ts-wrapper .ts-dropdown .no-results { color: #6B7280 !important; }
        .ts-wrapper .ts-control .item { color: #111827 !important; }
        .ts-wrapper.multi .ts-control .item { background: var(--accent-a12) !important; border: 1px solid var(--accent-a30) !important; color: var(--accent-dark) !important; }

        /* Dark mode */
        [data-theme="dark"] { --bg: #080B12; --card: #121826; --text: #FFFFFF; --text-muted: #94A3B8; --border: var(--accent-a15); --zebra: #161C2C; --surface-3: #161C2C; }

        /* شريط التمرير كان يرث ألوان الوضع الفاتح: مسارٌ كريميٌّ فاتح
           وإبهامٌ رماديٌّ باهت، فيُرسمان شريطاً ساطعاً على صفحةٍ سوداء
           يشدّ العين إليه في كل صفحة. هنا يذوب في الخلفيّة ولا يُرى
           إلا حين يُحتاج إليه. */
        [data-theme="dark"] ::-webkit-scrollbar-track { background: transparent; }
        [data-theme="dark"] ::-webkit-scrollbar-thumb { background: #2A3346; }
        [data-theme="dark"] ::-webkit-scrollbar-thumb:hover { background: #3A465E; }
        [data-theme="dark"] { scrollbar-color: #2A3346 transparent; }
        [data-theme="dark"] body { background-color: #080B12 !important; color: #FFFFFF !important; }
        [data-theme="dark"] .bg-white { background-color: #121826 !important; }
        [data-theme="dark"] .bg-gray-50 { background-color: #0D111B !important; }
        [data-theme="dark"] .bg-gray-100 { background-color: #0D111B !important; }
        [data-theme="dark"] .text-gray-900, [data-theme="dark"] .text-gray-800, [data-theme="dark"] .text-gray-700 { color: #FFFFFF !important; }
        [data-theme="dark"] .text-gray-600, [data-theme="dark"] .text-gray-500 { color: #CBD5E1 !important; }
        [data-theme="dark"] .text-gray-400 { color: #94A3B8 !important; }
        [data-theme="dark"] .text-gray-300 { color: #94A3B8 !important; }
[data-theme="dark"] .border-gray-200, [data-theme="dark"] .border-gray-100, [data-theme="dark"] .divide-gray-100, [data-theme="dark"] .divide-gray-200 { border-color: #252D3D !important; }
        [data-theme="dark"] .border-amber-200, [data-theme="dark"] .border-amber-300 { border-color: rgba(217,119,6,0.4) !important; }
        [data-theme="dark"] .border-amber-100 { border-color: rgba(217,119,6,0.25) !important; }
        [data-theme="dark"] .border-emerald-100 { border-color: rgba(22,163,74,0.25) !important; }
        [data-theme="dark"] .hover\:bg-gray-50:hover { background-color: #0D111B !important; }
        [data-theme="dark"] .hover\:bg-gray-100:hover { background-color: #182033 !important; }
        [data-theme="dark"] .hover\:text-gold-dark:hover, [data-theme="dark"] .hover\:text-gold-dark:hover { color: var(--accent-light) !important; }
        [data-theme="dark"] .sidebar-link { color: #94A3B8 !important; }
        [data-theme="dark"] .sidebar-link:hover { background: var(--accent-a10) !important; color: var(--accent-hover) !important; }
        [data-theme="dark"] .sidebar-link.active { background: var(--accent-a12) !important; color: var(--accent) !important; }
        [data-theme="dark"] header[style*="background"] { background: rgba(8,11,18,0.95) !important; }
        [data-theme="dark"] .bg-gold/12 { background-color: var(--accent-a15) !important; }
        [data-theme="dark"] .text-gold-dark, [data-theme="dark"] .text-gold-dark { color: var(--accent) !important; }
        [data-theme="dark"] .bg-green-100 { background-color: rgba(22,163,74,0.15) !important; }
        [data-theme="dark"] .text-green-700 { color: #4ADE80 !important; }
        [data-theme="dark"] .bg-red-100 { background-color: rgba(220,38,38,0.15) !important; }
        [data-theme="dark"] .text-red-700 { color: #F87171 !important; }
        [data-theme="dark"] .bg-blue-100 { background-color: rgba(37,99,235,0.15) !important; }
        [data-theme="dark"] .text-blue-700 { color: #60A5FA !important; }
        [data-theme="dark"] .bg-purple-100 { background-color: rgba(124,58,237,0.15) !important; }
        [data-theme="dark"] .text-purple-700 { color: #8B5CF6 !important; }
        [data-theme="dark"] .bg-emerald-100 { background-color: rgba(22,163,74,0.15) !important; }
        [data-theme="dark"] .text-emerald-700 { color: #4ADE80 !important; }
        [data-theme="dark"] .bg-yellow-100 { background-color: rgba(217,119,6,0.15) !important; }
        [data-theme="dark"] .bg-amber-100 { background-color: rgba(217,119,6,0.15) !important; }
        [data-theme="dark"] .text-yellow-700 { color: #F59E0B !important; }
        [data-theme="dark"] .bg-orange-100 { background-color: rgba(249,115,22,0.15) !important; }
        [data-theme="dark"] .text-orange-700 { color: #FB923C !important; }
        [data-theme="dark"] aside[style*="background"] { background: #0D111B !important; }
        [data-theme="dark"] aside div[style*="border-bottom"] { border-color: var(--accent-a10) !important; }
        [data-theme="dark"] .dropdown-dark { background: rgba(8,11,18,0.98) !important; border-color: var(--accent-a15) !important; }
        [data-theme="dark"] footer[style*="background"] { background: rgba(8,11,18,0.5) !important; }
        [data-theme="dark"] .btn-gold { background: linear-gradient(135deg, var(--accent), var(--accent-dark)) !important; color: #080B12 !important; }
        [data-theme="dark"] .card-premium { background: #121826 !important; border-color: var(--accent-a15) !important; }
        [data-theme="dark"] input, [data-theme="dark"] select, [data-theme="dark"] textarea { background-color: #0B1019 !important; border-color: #252D3D !important; color: #FFFFFF !important; }
        [data-theme="dark"] table thead { border-bottom-color: var(--accent-a10) !important; }
        [data-theme="dark"] .ts-wrapper .ts-control { background: #0B1019 !important; border-color: var(--accent-a15) !important; color: #FFFFFF !important; }
        [data-theme="dark"] .ts-wrapper .ts-dropdown { background: #121826 !important; border-color: #252D3D !important; color: #FFFFFF !important; }
        [data-theme="dark"] .guide-card { background-color: #121826 !important; background-image: linear-gradient(135deg, #121826, #0D111B) !important; }
        [data-theme="dark"] .guide-glass { background-color: rgba(18, 24, 38, 0.6) !important; }
        .font-pill { background-color: rgba(255, 255, 255, 0.78); border-color: var(--accent-a20); }
        [data-theme="dark"] .font-pill { background-color: rgba(8, 11, 18, 0.72); border-color: var(--accent-a20); }
        [data-theme="dark"] .ts-wrapper .ts-control input { color: #FFFFFF !important; }
        [data-theme="dark"] .ts-wrapper .ts-control input::placeholder { color: #94A3B8 !important; }
        [data-theme="dark"] .ts-wrapper .ts-control .item { color: #FFFFFF !important; }
        [data-theme="dark"] .ts-wrapper .ts-dropdown .ts-option { color: #CBD5E1 !important; }
        [data-theme="dark"] .ts-wrapper .ts-dropdown .ts-option:hover,
        [data-theme="dark"] .ts-wrapper .ts-dropdown .ts-option.active { background-color: var(--accent-a20) !important; color: var(--accent-hover) !important; }
        [data-theme="dark"] .ts-wrapper .ts-dropdown .ts-option.selected { color: var(--accent) !important; }
        [data-theme="dark"] .text-gold-dark { color: var(--accent-light) !important; }
        [data-theme="dark"] .hover\:text-gold-dark:hover { color: var(--accent-light) !important; }
        /* ====== إتمام الوضع الداكن ======
           الوضع الداكن هنا يعيد تعريف أصناف Tailwind الفاتحة بدل أن تحمل
           كل صفحة بديلاً لها. ما دون هذا السطر كان ناقصاً: لوحات ملوّنة
           فاتحة (bg-red-50) تبقى بيضاء بينما نصّها يُقلب فاتحاً، فلا يُقرأ.
           المصدر: جرد فعلي لأصناف القوالب لا تخميناً. */
        [data-theme="dark"] .bg-gray-200, [data-theme="dark"] .bg-gray-300 { background-color: #252D3D !important; }
        [data-theme="dark"] .border-gray-300, [data-theme="dark"] .border-gray-50,
        [data-theme="dark"] .divide-gray-50 { border-color: #252D3D !important; }
        [data-theme="dark"] .to-gray-100 { --tw-gradient-to: #0D111B !important; }

        [data-theme="dark"] .bg-red-50 { background-color: rgba(220,38,38,0.10) !important; }
        [data-theme="dark"] .bg-red-200 { background-color: rgba(220,38,38,0.22) !important; }
        [data-theme="dark"] .border-red-200 { border-color: rgba(220,38,38,0.35) !important; }
        [data-theme="dark"] .border-red-300 { border-color: rgba(220,38,38,0.45) !important; }
        [data-theme="dark"] .text-red-600, [data-theme="dark"] .text-red-800 { color: #F87171 !important; }

        [data-theme="dark"] .bg-green-50, [data-theme="dark"] .bg-emerald-50 { background-color: rgba(22,163,74,0.10) !important; }
        [data-theme="dark"] .bg-green-200 { background-color: rgba(22,163,74,0.22) !important; }
        [data-theme="dark"] .border-green-200, [data-theme="dark"] .border-emerald-200,
        [data-theme="dark"] .border-green-100 { border-color: rgba(22,163,74,0.35) !important; }
        [data-theme="dark"] .border-green-300 { border-color: rgba(22,163,74,0.45) !important; }
        [data-theme="dark"] .text-green-600, [data-theme="dark"] .text-green-800,
        [data-theme="dark"] .text-emerald-600 { color: #4ADE80 !important; }

        [data-theme="dark"] .bg-blue-50 { background-color: rgba(37,99,235,0.10) !important; }
        [data-theme="dark"] .bg-blue-200 { background-color: rgba(37,99,235,0.22) !important; }
        [data-theme="dark"] .border-blue-200 { border-color: rgba(37,99,235,0.35) !important; }
        [data-theme="dark"] .text-blue-800 { color: #60A5FA !important; }

        [data-theme="dark"] .bg-amber-50, [data-theme="dark"] .bg-yellow-50 { background-color: rgba(217,119,6,0.10) !important; }
        [data-theme="dark"] .border-yellow-200 { border-color: rgba(217,119,6,0.35) !important; }
        [data-theme="dark"] .text-amber-600, [data-theme="dark"] .text-amber-700,
        [data-theme="dark"] .text-amber-800 { color: #F59E0B !important; }

        [data-theme="dark"] .bg-orange-50 { background-color: rgba(249,115,22,0.10) !important; }
        [data-theme="dark"] .border-orange-200 { border-color: rgba(249,115,22,0.35) !important; }

        [data-theme="dark"] .bg-purple-200 { background-color: rgba(124,58,237,0.22) !important; }
        [data-theme="dark"] .border-purple-200 { border-color: rgba(124,58,237,0.35) !important; }

        [data-theme="dark"] .bg-indigo-50 { background-color: rgba(79,70,229,0.10) !important; }
        [data-theme="dark"] .border-indigo-200 { border-color: rgba(79,70,229,0.35) !important; }
        [data-theme="dark"] .text-indigo-700 { color: #818CF8 !important; }
        [data-theme="dark"] .bg-purple-50 { background-color: rgba(124,58,237,0.10) !important; }
        [data-theme="dark"] .bg-emerald-200 { background-color: rgba(22,163,74,0.22) !important; }
        [data-theme="dark"] .border-emerald-300 { border-color: rgba(22,163,74,0.45) !important; }
        [data-theme="dark"] .border-blue-100 { border-color: rgba(37,99,235,0.30) !important; }
        [data-theme="dark"] .border-blue-300 { border-color: rgba(37,99,235,0.45) !important; }
        [data-theme="dark"] .border-purple-300 { border-color: rgba(124,58,237,0.45) !important; }
        [data-theme="dark"] .bg-indigo-100 { background-color: rgba(79,70,229,0.15) !important; }
        [data-theme="dark"] .bg-indigo-200 { background-color: rgba(79,70,229,0.22) !important; }
        [data-theme="dark"] .bg-teal-100 { background-color: rgba(13,148,136,0.15) !important; }
        [data-theme="dark"] .text-teal-700 { color: #2DD4BF !important; }
</style>
    @stack('styles')
</head>
<body class="font-body min-h-screen" style="background-color: #F3EFE7; color: #111827;" x-data="{ sidebarOpen: window.__sbOpen, mobileOpen: false, profileOpen: false, theme: '{{ $appearanceMode }}', fontSize: parseInt(localStorage.getItem('fontSize') || '100', 10) || 100, fontStep(step) { const levels = [100, 110, 125]; let i = levels.indexOf(this.fontSize); if (i === -1) i = 0; const ni = Math.max(0, Math.min(levels.length - 1, i + step)); this.fontSize = levels[ni]; localStorage.setItem('fontSize', this.fontSize); document.documentElement.style.fontSize = (16 * this.fontSize / 100) + 'px'; } }" x-init="$el.closest('html').setAttribute('data-theme', theme); $watch('sidebarOpen', v => { try { localStorage.setItem('sidebarOpen', v ? '1' : '0'); } catch (e) {} })">

    {{-- Mobile Overlay --}}
    {{-- x-cloak واجب هنا: قبل أن يستيقظ Alpine كانت هذه الطبقة السوداء
         الثابتة تغطي الشاشة كلها في كل تحميل صفحة على الهاتف، فيبدو
         كأن لوحة جانبية فُتحت لمجرد الضغط على عنصر في الأسفل --}}
    <div
        x-cloak
        x-show="mobileOpen"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    @click="mobileOpen = false"
    class="fixed inset-0 bg-black/45 backdrop-blur-sm z-40 md:hidden"
    ></div>

    {{-- Sidebar --}}
    {{-- data-mobile-cloak: قبل Alpine لا يملك الشريط تحويلاً يخرجه من
         الشاشة، فكان يومض داخلاً من الجانب مع كل تنقّل على الهاتف.
         CSS أدناه يبقيه خارجها حتى يتولى Alpine الأمر --}}
    <aside
        data-mobile-cloak
        x-init="$el.removeAttribute('data-mobile-cloak')"
        class="fixed top-0 {{ $isRtl ? 'right-0' : 'left-0' }} h-full z-50 flex flex-col transition-all duration-300 ease-in-out"
        :class="[
            sidebarOpen ? 'sb-open' : 'sb-closed',
            mobileOpen ? 'translate-x-0' : '{{ $isRtl ? 'translate-x-full' : '-translate-x-full' }} md:translate-x-0'
        ]"
            style="background: var(--surface); {{ $isRtl ? 'border-left' : 'border-right' }}: 1px solid #E4DFD4;"
    >
        {{-- Logo --}}
        <div class="flex items-center justify-between h-16 px-4" style="border-bottom: 1px solid #E2E6EC;">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-gold-light to-gold-dark flex items-center justify-center flex-shrink-0 shadow-lg overflow-hidden" style="box-shadow: 0 8px 24px rgba(212, 175, 55, 0.22);">
                    @if($officeLogo)
                        <img src="{{ $officeLogo }}" alt="{{ $officeName }}" class="w-full h-full object-cover">
                    @else
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                        </svg>
                    @endif
                </div>
                <span class="sidebar-logo-text text-gold-dark font-heading font-bold text-[10px] leading-tight whitespace-normal max-w-[160px]" style="color: #D4AF37;">{{ $officeName }}</span>
            </div>
            <button @click="mobileOpen = false" class="md:hidden p-2 -m-2 text-gray-400 hover:text-gray-800 transition" aria-label="{{ __('app.close') }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-0.5">
            <p class="sidebar-section-title text-[11px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-3">{{ __('app.main_section') }}</p>

            <a href="{{ route('dashboard') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span>{{ __('app.dashboard') }}</span>
            </a>

            <a href="{{ route('attention.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('attention.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
            <span>{{ __('app.attention_center') }}</span>
            @php $attCount = \App\Services\AttentionService::itemsCount(); @endphp
            @if($attCount > 0)
            <span class="ms-auto text-[10px] px-1.5 py-0.5 rounded-full bg-gold text-[#111827] font-bold">{{ $attCount }}</span>
            @endif
            </a>

            <div class="pt-5 pb-2">
            <p class="sidebar-section-title text-[11px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-3">{{ __('app.daily_work_section') }}</p>
            </div>

            <a href="{{ route('cases.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('cases.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            <span>{{ __('app.cases') }}</span>
            </a>

            <a href="{{ route('sessions.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('sessions.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>{{ __('app.sessions') }}</span>
            </a>

            <a href="{{ route('appointments.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('appointments.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>المواعيد</span>
            </a>

            <a href="{{ route('tasks.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('tasks.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <span>{{ __('app.tasks') }}</span>
            </a>

            <a href="{{ route('clients.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('clients.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span>{{ __('app.clients') }}</span>
            </a>

            {{-- واتساب — يظهر لمن يملك صلاحية قراءته وحده. عنصرُ قائمةٍ
                 يقود إلى 403 أسوأ من غيابه: يوحي بميزةٍ مُنعت عمداً.

                 وحسابُ الموكّل مستثنًى صراحةً هنا كما في RoleMiddleware:
                 لو مُنح `whatsapp.view` بالخطأ (وشاشةُ الصلاحيات لا
                 تمنع ذلك) لرأى الرابطَ في قائمته، ولعدّ الوسمُ له
                 محادثاتِ موكّلين آخرين — رقماً هو في ذاته تسريب.

                 ═══ ولماذا Route::has قبل كلّ شيء ═══

                 لأنّ هذا القالبَ تحته كلُّ صفحةٍ في النظام. و`route()`
                 لمسارٍ غير معرَّف تُلقي استثناءً — فيسقط الموقعُ كلُّه،
                 لا الرابطُ وحده.

                 وقد وقع: مكتبٌ حُدِّث كودُه فأُعيدت ترجمةُ قوالبه
                 بينما بقيت مساراتُه تُقرأ من نسخةٍ قديمة (opcache أو
                 ملفُّ مساراتٍ مخزَّن)، فطلب القالبُ الجديد مساراً لا
                 تعرفه الطبقةُ التي تخدم الطلب — وردّت لوحةُ التحكّم 500
                 لكلّ مستخدم. رابطٌ في قائمةٍ لا يجوز أن يملك هذه
                 القدرة، فيُسأل عن وجوده قبل أن يُبنى. --}}
            @if(Route::has('whatsapp.index') && (\App\Support\WhatsAppSettings::inboxVisible() || Auth::user()->isDeveloper()) && !Auth::user()->isClient() && (Auth::user()->isDeveloper() || Auth::user()->isAdmin() || Auth::user()->hasPermission('whatsapp.view')))
            <a href="{{ route('whatsapp.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('whatsapp.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" />
            </svg>
            <span>{{ __('app.nav_whatsapp') }}</span>
            @php
                // عدٌّ محفوظٌ دقيقةً لا استعلامٌ في كل صفحة: هذه القيمة
                // تُرسم مع كلّ شاشة في النظام، واستعلامُها المباشر يجعل
                // واتساب يُبطئ لوحةَ التحكّم كلَّها. والحارسُ لمكتبٍ لم
                // تُنفَّذ هجراتُه بعد — عدمُ وجود الجدول لا يُسقط الموقع.
                $waUnread = \Illuminate\Support\Facades\Cache::remember('wa_unread_badge', 60, function () {
                    try {
                        return \App\Models\WhatsAppConversation::where('unread_count', '>', 0)->count();
                    } catch (\Throwable) {
                        return 0;
                    }
                });
            @endphp
            @if($waUnread > 0)
            <span class="mr-auto text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-emerald-500 text-white">{{ $waUnread }}</span>
            @endif
            </a>
            @endif

            <a href="{{ route('documents.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('documents.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>{{ __('app.documents') }}</span>
            </a>

            <a href="{{ route('chat.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('chat.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            <span>{{ __('app.chat') ?? 'المحادثات' }}</span>
            <span id="chatUnreadBadge" class="hidden mr-auto bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none" style="line-height:14px;">0</span>
            </a>

            @php
                $canSeeRecords = Auth::check() && (in_array(Auth::user()->role, ['admin', 'developer', 'lawyer', 'staff'])
                    || Auth::user()->hasPermission('audit_log.view')
                    || Auth::user()->hasPermission('feasibility.view'));
            @endphp
            @if($canSeeRecords)
            <div class="pt-5 pb-2">
            <p class="sidebar-section-title text-[11px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-3">{{ __('app.records_section') }}</p>
            </div>

                @if(in_array(Auth::user()->role, ['admin', 'developer', 'lawyer', 'staff']))
                <a href="{{ route('reports.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>{{ __('app.reports') }}</span>
                </a>
                @endif
                @if(Auth::user()->hasPermission('feasibility.view') || in_array(Auth::user()->role, ['admin', 'developer']))
                <a href="{{ route('feasibility.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('feasibility.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>{{ __('app.feasibility_study') }}</span>
                </a>
                @endif
                @if(Auth::user()->hasPermission('audit_log.view') || in_array(Auth::user()->role, ['admin', 'developer']))
                <a href="{{ route('audit-log.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('audit-log.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                <span>{{ __('app.audit_log') }}</span>
                </a>
                @endif
            @endif

            @if(!Auth::user()->isClient())
            <div class="pt-5 pb-2">
            <p class="sidebar-section-title text-[11px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-3">{{ __('app.admin_affairs') }}</p>
            </div>

            <a href="{{ route('hr.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('hr.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span>{{ __('app.hr') }}</span>
            </a>

            <a href="{{ route('finance.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('finance.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ __('app.finance') }}</span>
            </a>
            @endif

            @php
                $adminRole = Auth::check() && (in_array(Auth::user()->role, ['admin', 'developer'])
                    || Auth::user()->hasPermission('settings.manage')
                    || Auth::user()->hasPermission('backup.manage')
                    || Auth::user()->hasPermission('automations.manage')
                    || Auth::user()->hasPermission('templates.manage'));
            @endphp
            @if($adminRole)
            <div class="pt-5 pb-2">
            <p class="sidebar-section-title text-[11px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-3">{{ __('app.admin_section') }}</p>
            </div>

                @if(in_array(Auth::user()->role, ['admin', 'developer']))
                <a href="{{ route('users.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>{{ __('app.users') }}</span>
                </a>
                @endif

                @if(Auth::user()->hasPermission('automations.manage') || in_array(Auth::user()->role, ['admin', 'developer']))
                <a href="{{ route('automations.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('automations.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" transform="scale(0.5) translate(11 11)" />
                </svg>
                <span>مركز الأتمتة</span>
                </a>
                @endif

                @if(Auth::user()->hasPermission('templates.manage') || in_array(Auth::user()->role, ['admin', 'developer']))
                <a href="{{ route('case-templates.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('case-templates.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>القوالب الذكية</span>
                </a>
                @endif

                @if(Auth::user()->hasPermission('backup.manage') || in_array(Auth::user()->role, ['admin', 'developer']))
                <a href="{{ route('backup.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('backup.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
                <span>{{ __('app.backup') }}</span>
                </a>
                @endif

                @if(Auth::user()->hasPermission('settings.manage') || in_array(Auth::user()->role, ['admin', 'developer']))
                <a href="{{ route('settings.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>{{ __('app.settings') }}</span>
                </a>

                                @endif
            @endif

            @if(Auth::user()->role === 'developer')
            <div class="pt-5 pb-2">
            <p class="sidebar-section-title text-[11px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-3">{{ __('app.developer_section') }}</p>
            </div>

                <a href="{{ route('developer.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('developer.index') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
                <span>{{ __('app.developer_panel') }}</span>
                </a>

                <a href="{{ route('developer.subscription.config') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('developer.subscription.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span>إعدادات الاشتراك</span>
                </a>
            @endif
        </nav>

        {{-- Sidebar Footer — المساعدة والتواصل: حاضر وواضح، ولا ينافس عمل المكتب اليومي --}}
        <div class="p-3 space-y-0.5" style="border-top: 1px solid #E2E6EC;">
            <p class="sidebar-section-title text-[11px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-2">{{ __('app.help_section') }}</p>

            <a href="{{ route('guide') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <span class="sidebar-footer-text">{{ __('app.usage_guide') }}</span>
            </a>

            @if(!Auth::user()->isClient())
            <a href="{{ route('suggestions.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('suggestions.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                <span class="sidebar-footer-text">{{ __('app.suggestions_box') }}</span>
                @php $suggestionUnread = \App\Models\Suggestion::where('user_id', auth()->id())->whereNotNull('developer_reply')->where('reply_read', false)->count(); @endphp
                @if($suggestionUnread > 0)
                    <span class="ms-auto bg-emerald-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none" style="line-height:14px;">{{ $suggestionUnread > 9 ? '9+' : $suggestionUnread }}</span>
                @endif
            </a>
            @endif

            <a href="{{ route('language.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                </svg>
                <span class="sidebar-footer-text">{{ app()->getLocale() === 'ar' ? __('app.switch_to_en') : __('app.switch_to_ar') }}</span>
            </a>

            <a href="{{ route('profile.edit') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span class="sidebar-footer-text">{{ __('app.profile') }}</span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm w-full hover:text-red-700">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span class="sidebar-footer-text">{{ __('app.logout') }}</span>
                </button>
            </form>
        </div>

    {{-- أقسام الشريط الجانبي: تُطوى بالنقر، ويُحفظ اختيارك.

         البناء هنا لا في القالب: الأقسام في هذا الملف إخوةٌ مسطّحة
         تتخلّلها شرطياتُ صلاحيات، ولفّ كلٍّ منها بحاوية يدوياً يعني
         تحريك عشرات الكتل — وكلُّ تحريكٍ فرصةُ خطأ. هنا نلفّها وقت
         التحميل من الحقيقة التي في الصفحة نفسها.

         والقياس بـscrollHeight لا برقمٍ ثابت: قسمٌ يُضاف إليه رابطٌ
         غداً يفتح كاملاً بلا أن يتذكّر أحدٌ تعديل رقم. --}}
    <script nonce="{{ $cspNonce }}">
    (function () {
        var KEY = 'sbSections';

        function load() {
            try { return JSON.parse(localStorage.getItem(KEY) || '{}'); }
            catch (e) { return {}; }
        }
        function save(state) {
            try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
        }

        function build(root) {
            var titles = root.querySelectorAll('.sidebar-section-title');
            var state = load();

            Array.prototype.forEach.call(titles, function (title, i) {
                // العنوان قد يكون داخل غلافٍ للتباعد — الغلاف هو الرأس حينئذ
                var holder = (title.parentElement && title.parentElement !== root
                              && title.parentElement.children.length === 1)
                    ? title.parentElement : title;

                if (holder.dataset.sbBuilt) return;
                holder.dataset.sbBuilt = '1';

                // اجمع كل ما يلي العنوان حتى العنوان التالي
                var body = document.createElement('div');
                body.className = 'sb-section-body';
                var node = holder.nextElementSibling;

                while (node && !node.classList.contains('sidebar-section-title')
                            && !node.querySelector('.sidebar-section-title')) {
                    var next = node.nextElementSibling;
                    body.appendChild(node);
                    node = next;
                }

                if (!body.children.length) return;   // عنوانٌ بلا روابط: اتركه

                holder.parentNode.insertBefore(body, holder.nextSibling);

                // العنوان يصير زرّاً — بعنصرٍ يقبل لوحة المفاتيح
                var head = document.createElement('button');
                head.type = 'button';
                head.className = 'sb-section-head';
                head.setAttribute('aria-expanded', 'true');

                var chevron = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                chevron.setAttribute('class', 'sb-section-chevron');
                chevron.setAttribute('viewBox', '0 0 24 24');
                chevron.setAttribute('fill', 'none');
                chevron.setAttribute('stroke', 'currentColor');
                chevron.setAttribute('stroke-width', '2.5');
                var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('stroke-linecap', 'round');
                path.setAttribute('stroke-linejoin', 'round');
                path.setAttribute('d', 'M19 9l-7 7-7-7');
                chevron.appendChild(path);

                title.parentNode.insertBefore(head, title);
                head.appendChild(title);
                head.appendChild(chevron);

                var id = 'sb-sec-' + i;
                body.id = id + '-body';
                head.setAttribute('aria-controls', body.id);

                function apply(open, animate) {
                    head.setAttribute('aria-expanded', open ? 'true' : 'false');
                    if (open) {
                        body.removeAttribute('data-collapsed');
                        body.style.maxHeight = animate ? body.scrollHeight + 'px' : 'none';
                        if (animate) {
                            // بعد انتهاء الحركة نرفع السقف: قسمٌ يكبر لاحقاً
                            // لا يُقصّ عند ارتفاعٍ قيس لحظةَ الفتح
                            setTimeout(function () {
                                if (head.getAttribute('aria-expanded') === 'true') {
                                    body.style.maxHeight = 'none';
                                }
                            }, 300);
                        }
                    } else {
                        // من 'none' إلى صفر لا تُتحرّك: نثبّت الارتفاع أولاً
                        body.style.maxHeight = body.scrollHeight + 'px';
                        void body.offsetHeight;
                        body.setAttribute('data-collapsed', '1');
                    }
                }

                apply(state[id] !== false, false);

                head.addEventListener('click', function () {
                    var open = head.getAttribute('aria-expanded') !== 'true';
                    apply(open, true);
                    var s = load();
                    s[id] = open;
                    save(s);
                });
            });
        }

        /*
         * موضع تمرير الشريط يبقى كما تركه المستخدم بين الصفحات.
         *
         * القوائم أطول من الشاشة، فمن يعمل في قسمٍ أسفل الشريط ينزل
         * إليه ثم ينتقل صفحةً — فيرتدّ الشريط إلى أعلاه وينزل ثانية.
         * كل انتقالٍ تحميلٌ كامل للصفحة، وموضعُ التمرير لا ينجو منه
         * ما لم يُحفظ.
         *
         * في sessionStorage لا localStorage: الموضع يخصّ هذا اللسان
         * وحده. ولو حُفظ مشتركاً لجرّ لسانٌ مفتوحٌ على قسمٍ بعيد
         * لسانَ زميله إلى موضعه.
         */
        var SCROLL_KEY = 'sbScroll';

        function readScroll() {
            try { return parseInt(sessionStorage.getItem(SCROLL_KEY) || '0', 10) || 0; }
            catch (e) { return 0; }
        }

        function keepScroll(nav) {
            var saved = readScroll();
            if (saved > 0) {
                nav.scrollTop = saved;

                // محاولةٌ ثانية بعد اكتمال التحميل: الخطوط تصل متأخّرةً
                // فتتغيّر ارتفاعات السطور، واستعادةٌ قبلها تُقصّ إلى
                // ارتفاعٍ لم يكتمل — ويستقرّ الشريط في غير موضعه.
                window.addEventListener('load', function () {
                    if (nav.scrollTop < saved && !nav.dataset.sbMoved) {
                        nav.scrollTop = saved;
                    }
                });
            }

            var pending = false;
            nav.addEventListener('scroll', function () {
                nav.dataset.sbMoved = '1';   // حرّكه المستخدم: لا تُصحّح بعدها
                if (pending) return;
                pending = true;
                requestAnimationFrame(function () {
                    pending = false;
                    try { sessionStorage.setItem(SCROLL_KEY, String(nav.scrollTop)); } catch (e) {}
                });
            }, { passive: true });
        }

        function init() {
            var aside = document.querySelector('aside');
            if (!aside) return;
            var nav = aside.querySelector('nav');
            if (nav) build(nav);
            // تذييل «المساعدة والتواصل» خارج nav — وله عنوانه أيضاً
            Array.prototype.forEach.call(aside.children, function (child) {
                if (child.tagName === 'DIV' && child.querySelector('.sidebar-section-title')) {
                    build(child);
                }
            });
            // الاستعادة بعد البناء: البناء يطوي أقساماً فيقصر المحتوى،
            // واستعادةٌ قبله تُقصّ إلى ارتفاعٍ أصغر ثم تُحفظ مقصوصة.
            if (nav) keepScroll(nav);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>

    </aside>

    {{-- Main Content --}}
    <div
        class="content-area transition-all duration-300 min-h-screen"
        :class="sidebarOpen ? 'ct-open' : 'ct-closed'"
    >
        {{-- Top Bar --}}
        <header class="sticky top-0 z-30" style="background: rgba(252,250,246,0.95); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border-bottom: 1px solid rgba(212,175,55,0.08);">
            <div class="flex items-center justify-between h-16 px-4 sm:px-6">
                {{-- Right Side: Hamburger + Breadcrumb --}}
                <div class="flex items-center gap-3">
                    <button @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen ? 'true' : 'false'" aria-label="{{ __('app.a11y_toggle_menu') }}" class="md:hidden p-2 rounded-xl text-gray-400 hover:text-gray-800 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <button @click="sidebarOpen = !sidebarOpen" :aria-expanded="sidebarOpen ? 'true' : 'false'" aria-label="{{ __('app.a11y_toggle_sidebar') }}" class="hidden md:inline-flex p-2 rounded-xl text-gray-400 hover:text-gray-800 transition">
                        <svg x-show="sidebarOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $isRtl ? 'M13 5l7 7-7 7M5 5l7 7-7 7' : 'M11 19l-7-7 7-7m8 14l-7-7 7-7' }}"/>
                        </svg>
                        <svg x-show="!sidebarOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $isRtl ? 'M11 19l-7-7 7-7m8 14l-7-7 7-7' : 'M13 5l7 7-7 7M5 5l7 7-7 7' }}"/>
                        </svg>
                    </button>
                    @yield('breadcrumb')
                </div>

                {{-- Command Palette --}}
                <x-command-palette />

                {{-- Left Side --}}
                <div class="flex items-center gap-1">
                    <a href="{{ route('language.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}"
                        class="p-2 rounded-xl text-gray-400 hover:text-gold-dark transition" title="{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                    </a>

                    {{-- Font Size Control --}}
                    <div class="hidden sm:inline-flex items-center gap-0.5 rounded-xl border font-pill px-1 py-1" title="{{ __('app.font_size') }}">
                        <button @click="fontStep(-1)" :disabled="fontSize === 100"
                            class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-gray-500 transition hover:bg-gold/12 hover:text-gold-dark disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-gray-500" title="{{ __('app.font_decrease') }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
                        </button>
                        <button @click="fontSize = 100; localStorage.setItem('fontSize', 100); document.documentElement.style.fontSize = '16px'"
                            class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-sm font-black text-gold-dark transition hover:bg-gold/12" title="{{ __('app.font_reset') }}">A</button>
                        <button @click="fontStep(1)" :disabled="fontSize === 125"
                            class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-gray-500 transition hover:bg-gold/12 hover:text-gold-dark disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-gray-500" title="{{ __('app.font_increase') }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        </button>
                    </div>

                    {{-- المظهر: الوضع + السمة — يُطبَّقان فوراً بلا إعادة تحميل ويُحفظان لهذا المستخدم وحده --}}
                    <div class="relative" x-data="{
                            open: false,
                            theme: '{{ $themeKey }}',
                            mode: '{{ $appearanceMode }}',
                            saving: false,
                            apply() {
                                document.documentElement.setAttribute('data-theme', this.mode);
                                document.documentElement.setAttribute('data-palette', this.theme);
                                try { localStorage.setItem('theme', this.mode); } catch (e) {}
                                this.saving = true;
                                fetch('{{ route('appearance.update') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                        'Accept': 'application/json'
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ appearance: this.mode, theme: this.theme })
                                }).finally(() => { this.saving = false; });
                            },
                            setMode(m) { this.mode = m; this.apply(); },
                            setTheme(t) { this.theme = t; this.apply(); }
                        }">
                        <button @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                                class="p-2 rounded-xl transition text-gray-400 hover:text-gold-dark"
                                title="{{ __('app.appearance') }}" aria-label="{{ __('app.appearance') }}">
                            <svg x-show="mode === 'light'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
                            </svg>
                            <svg x-show="mode === 'dark'" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                            </svg>
                        </button>

                        {{-- خلفية معتمة للورقة السفلية على الهاتف --}}
                        <template x-teleport="body">
                            <div x-show="open" x-cloak x-transition.opacity @click="open = false"
                                 class="fixed inset-0 z-40 bg-black/35 md:hidden"></div>
                        </template>

                        <template x-teleport="body">
                        <div x-show="open" x-cloak @click.outside="open = false"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-3 md:-translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="md-appearance">

                            <div class="md-appearance-grab md:hidden" aria-hidden="true"></div>

                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-gray-800">{{ __('app.appearance') }}</h3>
                                <span x-show="saving" x-cloak class="text-[11px] text-gray-400">{{ __('app.saving') }}</span>
                            </div>

                            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">{{ __('app.appearance_mode') }}</p>
                            <div class="grid grid-cols-2 gap-2.5 mb-5">
                                <button type="button" @click="setMode('light')" class="md-mode-btn" :class="mode === 'light' ? 'is-on' : ''">
                                    <span class="md-mode-swatch md-mode-light" aria-hidden="true"></span>
                                    <span>{{ __('app.light_theme') }}</span>
                                </button>
                                <button type="button" @click="setMode('dark')" class="md-mode-btn" :class="mode === 'dark' ? 'is-on' : ''">
                                    <span class="md-mode-swatch md-mode-dark" aria-hidden="true"></span>
                                    <span>{{ __('app.dark_theme') }}</span>
                                </button>
                            </div>

                            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">{{ __('app.color_theme') }}</p>
                            <div class="grid grid-cols-2 gap-2.5">
                                @foreach(\App\Support\Appearance::options() as $opt)
                                    <button type="button" @click="setTheme('{{ $opt['key'] }}')"
                                            class="md-theme-card" data-palette="{{ $opt['key'] }}"
                                            :class="theme === '{{ $opt['key'] }}' ? 'is-on' : ''"
                                            :aria-pressed="theme === '{{ $opt['key'] }}' ? 'true' : 'false'">
                                        {{-- معاينة مصغّرة لواجهة النظام بألوان هذه السمة --}}
                                        <span class="md-preview" aria-hidden="true">
                                            <span class="md-preview-side"></span>
                                            <span class="md-preview-main">
                                                <span class="md-preview-bar"></span>
                                                <span class="md-preview-row"></span>
                                                <span class="md-preview-btn"></span>
                                            </span>
                                        </span>
                                        <span class="md-theme-name">
                                            <span>{{ $opt['label'] }}</span>
                                            <svg x-show="theme === '{{ $opt['key'] }}'" x-cloak class="w-3.5 h-3.5 text-gold-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </span>
                                    </button>
                                @endforeach
                            </div>

                            <p class="text-[11px] text-gray-400 mt-4 leading-relaxed">{{ __('app.appearance_hint') }}</p>
                        </div>
                        </template>
                    </div>

                    @php
                        $unreadCount = \App\Models\Notification::where('user_id', auth()->id())->where('is_read', false)->count();
                        $recentNotifications = \App\Models\Notification::where('user_id', auth()->id())->latest()->limit(10)->get();
                    @endphp
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="relative p-2 rounded-xl text-gray-400 hover:text-gray-700 transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            @if($unreadCount > 0)
                                <span class="absolute -top-0.5 -left-0.5 flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white bg-red-500 rounded-full badge-pulse">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                            @endif
                        </button>

                        <div
                            x-show="open"
                            x-cloak
                            @click.away="open = false"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute left-0 mt-2 w-80 rounded-2xl overflow-hidden z-50 dropdown-dark"
                            style="box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);"
                        >
                            <div class="p-4 flex items-center justify-between" style="border-bottom: 1px solid #E2E6EC;">
                                <h3 class="font-heading font-bold text-gray-900">{{ __('app.notifications') }}</h3>
                                @if($unreadCount > 0)
                                    <form method="POST" action="{{ route('notifications.readAll') }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-gold-dark hover:text-gold transition">{{ __('app.mark_all_read') }}</button>
                                    </form>
                                @endif
                            </div>
                            <div class="max-h-80 overflow-y-auto">
                                @forelse($recentNotifications as $notification)
                                    @php
                                        $notifTitle = $notification->localizedTitle() ?: ($notification->type === 'chat' ? __('app.new_message') : null);
                                    @endphp
                                        @if($notification->is_read)
                                        <div class="block px-4 py-3 transition" style="border-bottom: 1px solid rgba(212,175,55,0.04);">
                                            <div class="flex items-center justify-between">
                                                <p class="text-sm font-medium text-gray-500">{{ $notifTitle }}</p>
                                                @if($notification->message_count > 1)
                                                    <span class="text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded-full">{{ $notification->message_count }}</span>
                                                @endif
                                            </div>
                                            <p class="text-sm text-gray-500">{{ $notification->localizedMessage() }}</p>
                                            <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="block transition hover:bg-gray-50" style="border-bottom: 1px solid rgba(212,175,55,0.04);">
                                            @csrf
                                            <button type="submit" class="w-full text-right px-4 py-3">
                                                <div class="flex items-center justify-between">
                                                    <p class="text-sm font-medium text-gray-900">{{ $notifTitle }}</p>
                                                    @if($notification->message_count > 1)
                                                        <span class="text-[10px] text-gold-dark bg-gold/12 px-1.5 py-0.5 rounded-full">{{ $notification->message_count }}</span>
                                                    @endif
                                                </div>
                                                <p class="text-sm text-gray-700">{{ $notification->localizedMessage() }}</p>
                                                <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                                            </button>
                                        </form>
                                    @endif
                                @empty
                                    <div class="p-8 text-center text-gray-400 text-sm">
                                        <svg class="w-10 h-10 mx-auto mb-2 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                        {{ __('app.no_notifications') }}
                                    </div>
                                @endforelse
                            </div>
                            <a href="{{ route('notifications.index') }}" class="block p-3 text-center text-sm text-gold-dark font-medium transition hover:text-gold" style="border-top: 1px solid #E2E6EC;">{{ __('app.view_all_notifications') }}</a>
                        </div>
                    </div>

                    {{-- User Menu --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-2 p-1.5 rounded-xl transition">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-gold-light to-gold-dark flex items-center justify-center shadow-lg" style="box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                </svg>
                            </div>
                            <span class="hidden sm:block text-sm font-medium text-gray-700">{{ Auth::user()->name }}</span>
                            <svg class="w-4 h-4 text-gray-400 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div
                            x-show="open"
                            x-cloak
                            @click.away="open = false"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute left-0 mt-2 w-56 rounded-2xl overflow-hidden z-50 dropdown-dark"
                            style="box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);"
                        >
                            <div class="p-4" style="border-bottom: 1px solid #E2E6EC;">
                                <p class="font-heading font-bold text-gray-900 text-sm">{{ Auth::user()->name }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ Auth::user()->email }}</p>
                                <span class="inline-block mt-1 text-[10px] px-2 py-0.5 rounded-full bg-gold/12 text-gold-dark font-medium">{{ Auth::user()->role }}</span>
                            </div>
                            <div class="py-1">
                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-500 hover:text-gray-900 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    {{ __('app.profile') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</header>

        {{-- Subscription expiry banner --}}
        @if($subscriptionInfo)
            @if($subscriptionInfo['key'] === 'expired')
                <div class="px-4 sm:px-6 lg:px-8 pt-4">
                    <div class="flex items-center gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
                        <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 13h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2zm14 0v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/></svg>
                        <p class="text-sm font-semibold text-red-700 flex-1">انتهت صلاحية اشتراكك في النظام.</p>
                        <a href="{{ route('subscription.expired') }}" class="text-xs font-bold text-red-700 underline underline-offset-4 whitespace-nowrap">التفاصيل</a>
                    </div>
                </div>
            @elseif($subscriptionInfo['key'] === 'expiring_soon')
                <div class="px-4 sm:px-6 lg:px-8 pt-4">
                    <div class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                        <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-sm font-semibold text-amber-700 flex-1">
                            اشتراكك ينتهي بعد {{ $subscriptionInfo['remaining_days'] }} يوم — يرجى التواصل مع إدارة النظام للتجديد.
                        </p>
                    </div>
                </div>
            @endif
        @endif

        {{-- Page Content --}}
        <main class="p-4 sm:p-6 lg:p-8 page-enter pb-20 md:pb-8">
            {{-- بلوغُ حدّ الباقة: يُعرض قبل غيره — هو سببُ عدم وقوع ما طُلب --}}
            <x-attendance-toast :attendance-open="\App\Support\AttendanceGuard::openRecord(auth()->user())" />
            <x-limit-notice />

            @if(session('success'))
                <x-alert type="success" :message="session('success')" />
            @endif
            @if(session('error'))
                <x-alert type="error" :message="session('error')" />
            @endif
            @if(session('warning'))
                <x-alert type="warning" :message="session('warning')" />
            @endif
            @if(session('info'))
                <x-alert type="info" :message="session('info')" />
            @endif

            @php
                $todayMsg = \App\Models\Announcement::latest()->first();
                $todayMsgSeen = $todayMsg && \App\Models\AnnouncementRead::where('announcement_id', $todayMsg->id)->where('user_id', auth()->id())->exists();
                $todayMsgHtml = $todayMsg ? e($todayMsg->content) : '';
            @endphp
            @if($todayMsg && !$todayMsgSeen)
                <div x-data="{ hidden: false, locked: false }" x-show="!hidden" x-cloak x-transition:leave="transition ease-in duration-300" x-transition:leave-end="opacity-0 scale-95" class="fixed inset-0 z-[100] flex items-center justify-center p-4" dir="rtl">
                    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" @click="fetch('{{ route('announcements.seen', $todayMsg) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } }).catch(() => {}).finally(() => { hidden = true; })"></div>
                    <div class="relative w-full max-w-sm rounded-2xl bg-gradient-to-b from-[#121826] to-[#080B12] border border-gold/25 shadow-[0_30px_90px_-15px_rgba(0,0,0,0.65),inset_0_1px_0_rgba(212,175,55,0.12)] overflow-hidden">
                        <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-l from-gold-dark/70 via-gold/80 to-gold-dark/70"></div>
                        <div class="p-7 text-center">
                            <div class="w-14 h-14 mx-auto rounded-full bg-gradient-to-br from-gold/25 to-gold-dark/5 border border-gold/30 flex items-center justify-center mb-4 shadow-[0_0_30px_rgba(251,191,36,0.15)]">
                                <svg class="w-6 h-6 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                </svg>
                            </div>
                            <p class="text-[11px] tracking-[0.35em] text-gold-light/90 font-bold mb-2">{{ __('app.today_message') }}</p>
                            <div class="w-12 h-px mx-auto bg-gradient-to-l from-transparent via-gold/60 to-transparent mb-5"></div>
                            <p class="text-sm text-[#CBD5E1] leading-relaxed whitespace-pre-wrap">{!! $todayMsgHtml !!}</p>
                            <button type="button" x-bind:class="locked ? 'bg-gold text-[#080B12] border-gold' : 'text-gold-light border-gold/40 hover:bg-gold/10'" @click="fetch('{{ route('announcements.seen', $todayMsg) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } }).catch(() => {}).finally(() => { locked = true; setTimeout(() => hidden = true, 350); })" class="mt-6 inline-flex items-center gap-2 px-6 py-2.5 rounded-full border font-bold text-xs transition-all duration-300" title="قفل الرسالة">
                                <svg x-show="!locked" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 21.75h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                                <svg x-show="locked" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                                <span x-text="locked ? 'تم القفل' : 'قفل الرسالة'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>

        {{-- شريط التنقل السفلي — جوال فقط: أهم الأقسام في متناول الإبهام --}}
        @if(Auth::check() && !Auth::user()->isClient())
        @php
            $bottomNav = [
                ['route' => 'dashboard', 'is' => 'dashboard', 'label' => __('app.dashboard'), 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                ['route' => 'cases.index', 'is' => 'cases.*', 'label' => __('app.cases'), 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                ['route' => 'sessions.index', 'is' => 'sessions.*', 'label' => __('app.sessions'), 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ['route' => 'appointments.index', 'is' => 'appointments.*', 'label' => 'المواعيد', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['route' => 'tasks.index', 'is' => 'tasks.*', 'label' => __('app.tasks'), 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                ['route' => 'clients.index', 'is' => 'clients.*', 'label' => __('app.clients'), 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ];
        @endphp
        <style nonce="{{ $cspNonce }}">
            .bottom-nav { background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); }
            [data-theme="dark"] .bottom-nav { background: rgba(13,17,27,0.95) !important; border-color: rgba(212,175,55,0.15) !important; }
        </style>
        <nav class="md:hidden fixed bottom-0 inset-x-0 z-40 bottom-nav border-t border-gray-200"
             style="padding-bottom: env(safe-area-inset-bottom);" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
            <div class="grid grid-cols-5">
                @foreach($bottomNav as $item)
                    @php $active = request()->routeIs($item['is']); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="flex flex-col items-center gap-0.5 py-2 text-[10px] font-bold transition-colors {{ $active ? 'text-gold-dark' : 'text-gray-400 hover:text-gray-600' }}">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="{{ $active ? '2' : '1.5' }}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                        </svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
        @endif

        {{-- Footer --}}
        <footer style="border-top: 1px solid #E2E6EC; background: rgba(244,242,238,0.5);">
            <div class="px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <a href="{{ \App\Support\Mudawala::url() }}" target="_blank" class="text-sm font-heading font-bold hover:opacity-80 transition-opacity" style="color: #E5C158;">مُداوَلة</a>
                    <span class="text-xs text-gray-400">&copy;</span>
                    <span class="text-sm text-gray-500">{{ date('Y') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <p class="text-xs text-gray-400">{{ $officeName }}</p>
                    <span class="text-gray-300">|</span>
                    <a href="{{ \App\Support\Mudawala::url() }}" target="_blank" class="text-xs text-gray-400 hover:text-gold-dark transition">{{ __('app.product_credit') }}</a>
                </div>
            </div>
        </footer>
    </div>

    @auth
    <form id="autoLogoutForm" action="{{ route('logout') }}" method="POST" style="display:none;">
        @csrf
        {{-- خروجُ الخمول ليس «زرَّ الخروج»: العلامة تخبر الخادم ألّا
             يسجّل انصرافاً — وإلا عاد اختراعُ وقت الانصراف من بابٍ خلفي --}}
        <input type="hidden" name="auto" value="1">
    </form>

    <div id="autoLogoutOverlay" style="display:none;" class="fixed inset-0 z-[9999] flex items-center justify-center" data-autologout-backdrop role="alertdialog" aria-modal="true" aria-labelledby="autoLogoutTitle">
        <div class="bg-white border border-gray-200 rounded-2xl p-8 max-w-sm mx-4 text-center shadow-2xl card-premium">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-amber-500/10 flex items-center justify-center">
                <svg class="w-8 h-8 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
            </div>
            <h3 id="autoLogoutTitle" class="text-gray-900 font-bold text-lg mb-2" style="font-family: 'Cairo', sans-serif;">{{ __('app.session_warning_title') }}</h3>
            <p class="text-gray-500 text-sm mb-4">{{ __('app.session_warning_message') }} <span class="text-amber-400 font-bold" id="autoLogoutCountdown">60</span></p>
            <div class="w-full bg-gray-200 rounded-full h-2 mb-6">
                <div id="autoLogoutBar" class="bg-amber-400 h-2 rounded-full transition-all duration-1000" style="width: 100%"></div>
            </div>
            <div class="flex gap-3">
                <button type="button" data-autologout-continue class="flex-1 btn-gold py-3 rounded-xl font-bold text-sm">{{ __('app.continue') }}</button>
                <button type="button" data-autologout-logout class="flex-1 btn-ghost py-3 rounded-xl font-medium text-sm">{{ __('app.logout') }}</button>
            </div>
        </div>
    </div>
    @endauth

    <script nonce="{{ $cspNonce }}">
    (function() {
        var timer = null;
        var countdownTimer = null;
        var keepAliveTimer = null;
        var countdownVal = 60;
        // ═══ ساعةٌ لا عشرُ دقائق ═══
        //
        // المحامي يقرأ ملفّاً أو يقابل موكّلاً والشاشةُ مفتوحة، فيعود
        // فيجد نفسَه على وشك الخروج. والعشرُ دقائق تقيس «متى نقر»
        // لا «متى غاب».
        //
        // وعمرُ الجلسة على الخادم ساعتان، فالساعةُ ودقيقةُ التنبيه
        // تبقيان دونه بفارقٍ واسع: من ضغط «استمرار» تُجدَّد جلستُه
        // ولا يسقط في فراغٍ بين المهلتين.
        var TIMEOUT = 60 * 60 * 1000;
        var WARNING = 60;

        function sendKeepAlive(onDone) {
            var form = document.getElementById('autoLogoutForm');
            if (!form) return;
            var tokenInput = form.querySelector('input[name="_token"]');
            if (!tokenInput) return;
            fetch("{{ route('session.keepalive') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': tokenInput.value
                },
                body: '{}'
            })
            .then(function (res) {
                var dead = res.status === 401 || res.status === 419;
                try {
                    // المسار محميّ بـ auth: الجلسة المنتهية تُحوَّل إلى صفحة الدخول
                    if (res.redirected && new URL(res.url).pathname.indexOf('/login') === 0) dead = true;
                } catch (e) {}
                if (onDone) onDone(dead ? 401 : res.status);
            })
            .catch(function () { if (onDone) onDone(0); });
        }

        function resetTimer() {
            if (document.getElementById('autoLogoutOverlay').style.display !== 'none') return;
            clearTimeout(timer);
            timer = setTimeout(showWarning, TIMEOUT);
        }

        function showWarning() {
            var overlay = document.getElementById('autoLogoutOverlay');
            if (!overlay) return;
            overlay.style.display = 'flex';
            overlay.style.animation = 'fadeIn 0.3s ease-out';
            countdownVal = WARNING;
            updateDisplay();
            countdownTimer = setInterval(function() {
                countdownVal--;
                updateDisplay();
                if (countdownVal <= 0) {
                    doLogout();
                }
            }, 1000);
            // كل 10 ثوانٍ أثناء التنبيه نجدد الجلسة عند السيرفر حتى يُسجَّل
            // الخروج التلقائي بشكل صحيح عند انتهاء العد.
            //
            // وردُّ «الجلسة ماتت» يوقف كل شيء فوراً: تجاهلُه كان يُبقي
            // النبضة تقرع خادماً برمز CSRF ميت — تبويبٌ واحد منسيّ
            // بعد نوم الجهاز ملأ سجلَّ الأخطاء بمئات 419 في الساعة.
            clearInterval(keepAliveTimer);
            keepAliveTimer = setInterval(function() {
                sendKeepAlive(function (status) {
                    if (status === 401 || status === 419) doLogout(true);
                });
            }, 10000);
        }

        function updateDisplay() {
            var el = document.getElementById('autoLogoutCountdown');
            var bar = document.getElementById('autoLogoutBar');
            if (el) el.textContent = countdownVal;
            if (bar) bar.style.width = (countdownVal / WARNING * 100) + '%';
        }

        function dismiss() {
            var overlay = document.getElementById('autoLogoutOverlay');
            if (overlay) overlay.style.display = 'none';
            clearInterval(countdownTimer);
            clearInterval(keepAliveTimer);
            // نجدّد الجلسة عند الخادم أولاً، ثم نعيد ضبط المؤقّت بعد نجاحها
            sendKeepAlive(function (status) {
                if (status === 401 || status === 419) {
                    // الجلسة انتهت فعلاً عند الخادم — الخروج هو التصرّف الصحيح
                    doLogout(true);
                    return;
                }
                resetTimer();
            });
        }

        function doLogout(sessionDead) {
            clearTimeout(timer);
            clearInterval(countdownTimer);
            clearInterval(keepAliveTimer);
            if (sessionDead) {
                // الجلسة ميتة عند الخادم: إرسالُ نموذجٍ برمز CSRF ميت
                // يرتدّ صفحةَ 419 وسطرَ خطأ — صفحةُ الدخول هي المقصد
                window.location.replace('{{ route('login') }}');
                return;
            }
            var form = document.getElementById('autoLogoutForm');
            if (form) { form.submit(); } else { window.location.href = '{{ route('login') }}'; }
        }

        // معالجات حقيقية بدل onclick المضمّن — سياسة CSP تمنع المضمّن فلا يعمل
        var contBtn = document.querySelector('[data-autologout-continue]');
        var outBtn = document.querySelector('[data-autologout-logout]');
        var backdrop = document.querySelector('[data-autologout-backdrop]');
        if (contBtn) contBtn.addEventListener('click', dismiss);
        if (outBtn) outBtn.addEventListener('click', doLogout);
        if (backdrop) backdrop.addEventListener('click', function (e) { if (e.target === backdrop) dismiss(); });
        document.addEventListener('keydown', function (e) {
            var overlay = document.getElementById('autoLogoutOverlay');
            if (e.key === 'Escape' && overlay && overlay.style.display !== 'none') dismiss();
        });

        document.addEventListener('mousemove', resetTimer);
        document.addEventListener('keydown', resetTimer);
        document.addEventListener('scroll', resetTimer);
        document.addEventListener('click', resetTimer);
        document.addEventListener('touchstart', resetTimer);

        resetTimer();
    })();
    </script>

    @auth
    <script nonce="{{ $cspNonce }}">
    (function() {
        var POLL_INTERVAL = 30000;
        var lastUpdated = '{{ now()->toDateTimeString() }}';
        var currentUrl = window.location.pathname;
        var isFormPage = document.querySelector('form[method="POST"]') && !document.querySelector('.data-table');
        if (isFormPage) return;

        var indicator = document.createElement('div');
        indicator.id = 'syncIndicator';
        indicator.style.cssText = 'position:fixed;bottom:12px;left:12px;z-index:9999;display:none;padding:4px 10px;border-radius:8px;font-size:11px;color:var(--accent-dark);background:rgba(244,242,238,0.92);border:1px solid var(--accent-a30);transition:opacity 0.3s;pointer-events:none;';
        document.body.appendChild(indicator);

        function showIndicator() {
            indicator.textContent = '🔄 جاري التحديث...';
            indicator.style.display = 'block';
            indicator.style.opacity = '1';
        }
        function hideIndicator() {
            indicator.style.opacity = '0';
            setTimeout(function() { indicator.style.display = 'none'; }, 300);
        }

        function poll() {
            fetch('{{ route("sync") }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.updated_at && data.updated_at !== lastUpdated) {
                    lastUpdated = data.updated_at;
                    refreshContent();
                }
            })
            .catch(function() {});
        }

        function refreshContent() {
            showIndicator();
            fetch(currentUrl, {
                headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newMain = doc.querySelector('main');
                var oldMain = document.querySelector('main');
                if (newMain && oldMain) {
                    var scrollTop = window.scrollY;
                    oldMain.innerHTML = newMain.innerHTML;
                    window.scrollTo(0, scrollTop);
                }
                var newTitle = doc.querySelector('title');
                if (newTitle) document.title = newTitle.textContent;
                hideIndicator();
            })
            .catch(function() { hideIndicator(); });
        }

        setInterval(poll, POLL_INTERVAL);
    })();
    </script>
    @endauth

    {{-- تأكيد الإجراءات الحسّاسة: معالج مفوَّض يقرأ data-confirm.
         كانت التأكيدات مكتوبة onsubmit مضمَّناً، وسياسة CSP تمنع المضمَّن،
         فكانت عمليات الحذف تنفّذ بلا أي سؤال. --}}
    <script nonce="{{ $cspNonce }}">
        (function () {
            'use strict';
            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (!(form instanceof HTMLFormElement)) return;
                var msg = form.getAttribute('data-confirm');
                if (!msg || form.dataset.confirmed === '1') return;
                if (window.confirm(msg)) {
                    form.dataset.confirmed = '1';
                    return;
                }
                e.preventDefault();
                e.stopImmediatePropagation();
            }, true);

            // بدائل onclick التي كانت معطّلة بسياسة CSP
            document.addEventListener('click', function (e) {
                var printBtn = e.target.closest('[data-print-url]');
                if (printBtn) {
                    var w = window.open(printBtn.getAttribute('data-print-url'), '_blank', 'width=800,height=600');
                    if (w) { w.addEventListener('load', function () { w.print(); }); }
                    return;
                }
                var back = e.target.closest('[data-history-back]');
                if (back) { history.back(); return; }
                var printSelf = e.target.closest('[data-window-print]');
                if (printSelf) { window.print(); return; }
                var row = e.target.closest('[data-row-url]');
                if (row && !e.target.closest('a,button')) { window.location = row.getAttribute('data-row-url'); }
            });

            // قائمة تُرسل نموذجها فور الاختيار — بلا زر «حفظ» لكل صف.
            // التفويض لا معالج سطري: CSP يحجب السطري بصمت.
            document.addEventListener('change', function (e) {
                var sel = e.target.closest('[data-autosubmit]');
                if (sel && sel.form) { sel.form.submit(); }
            });

            // روابط تحمل data-confirm أيضاً (حذف عبر رابط)
            document.addEventListener('click', function (e) {
                var a = e.target.closest('a[data-confirm]');
                if (!a) return;
                if (!window.confirm(a.getAttribute('data-confirm'))) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                }
            }, true);
        })();
    </script>

    {{-- حالة التحميل: شريط علوي عند التنقّل والإرسال، وحارس ضدّ الإرسال المزدوج --}}
    <div id="mdProgress" aria-hidden="true"></div>
    <script nonce="{{ $cspNonce }}">
        (function () {
            'use strict';
            var bar = document.getElementById('mdProgress');
            var timer = null, width = 0;

            function start() {
                if (timer) return;
                width = 8;
                bar.style.opacity = '1';
                bar.style.width = width + '%';
                timer = setInterval(function () {
                    width += Math.max(0.4, (88 - width) / 14);
                    if (width > 92) width = 92;
                    bar.style.width = width + '%';
                }, 180);
            }
            function done() {
                if (!timer) return;
                clearInterval(timer); timer = null;
                bar.style.width = '100%';
                setTimeout(function () { bar.style.opacity = '0'; setTimeout(function () { bar.style.width = '0'; }, 250); }, 180);
            }

            // التنقّل بين الصفحات
            document.addEventListener('click', function (e) {
                var a = e.target.closest('a[href]');
                if (!a || a.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey) return;
                var href = a.getAttribute('href') || '';
                if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
                if (a.hasAttribute('download') || a.hasAttribute('x-on:click') || a.hasAttribute('@click')) return;
                try { if (new URL(href, location.href).origin !== location.origin) return; } catch (err) { return; }
                start();
            }, true);

            // قائمة تُرسل نموذجها عند التغيير — مفوَّضة لا مضمَّنة،
            // فسياسة CSP هنا تحجب onchange وأمثاله بصمت.
            document.addEventListener('change', function (e) {
                var el = e.target;
                if (!(el instanceof HTMLElement) || !el.hasAttribute('data-auto-submit')) return;
                var form = el.form || el.closest('form');
                if (form) form.requestSubmit ? form.requestSubmit() : form.submit();
            });

            // إرسال النماذج: شريط + منع الضغط المزدوج
            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (!(form instanceof HTMLFormElement) || form.dataset.noProgress === '1') return;
                start();
                var btns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                setTimeout(function () {
                    btns.forEach(function (b) {
                        if (b.disabled) return;
                        b.disabled = true;
                        b.dataset.mdBusy = '1';
                        b.style.opacity = '0.75';
                        b.style.cursor = 'progress';
                    });
                }, 0);
            }, true);

            // العودة من ذاكرة المتصفح: أعد تفعيل الأزرار وأخفِ الشريط
            window.addEventListener('pageshow', function () {
                done();
                document.querySelectorAll('[data-md-busy="1"]').forEach(function (b) {
                    b.disabled = false; b.removeAttribute('data-md-busy');
                    b.style.opacity = ''; b.style.cursor = '';
                });
            });
            window.addEventListener('beforeunload', start);
            window.addEventListener('load', done);
        })();
    </script>

    {{-- ألوان الرسوم: تُقرأ من الرموز وقت التشغيل، قبل أي سكربت يبني رسماً --}}
    @include('partials.chart-theme')

    @stack('scripts')

    <script nonce="{{ $cspNonce }}">
    document.addEventListener('click', function(e) {
        var el = e.target.closest('.__cf_email__');
        if (el) {
            e.preventDefault();
            try {
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = 800;
                osc.type = 'sine';
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.15);
            } catch(_) {}
            var toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:99999;background:rgba(212,175,55,0.95);color:#080B12;padding:16px 24px;border-radius:12px;font-size:14px;font-weight:600;box-shadow:0 8px 32px rgba(0,0,0,0.35);max-width:400px;text-align:center;direction:rtl;';
            toast.textContent = '🔐 هذا البريد الإلكتروني وجميع البيانات في الموقع مشفرة للحماية. للتواصل يرجى مراسلة المطور.';
            document.body.appendChild(toast);
            setTimeout(function() {
                toast.style.transition = 'opacity 0.5s';
                toast.style.opacity = '0';
                setTimeout(function() { toast.remove(); }, 500);
            }, 4000);
        }
    });
    </script>

    @auth
    <script nonce="{{ $cspNonce }}">
    var lastNotifId = 0;
    function notifSound() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var notes = [660, 880, 1100];
            notes.forEach(function(freq, i) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = freq;
                osc.type = 'sine';
                var t = ctx.currentTime + i * 0.08;
                gain.gain.setValueAtTime(0.12, t);
                gain.gain.exponentialRampToValueAtTime(0.01, t + 0.12);
                osc.start(t);
                osc.stop(t + 0.12);
            });
        } catch(_) {}
    }
    function showNotifToast(title, msg) {
        var existing = document.getElementById('notifToast');
        if (existing) { existing.remove(); }
        var t = document.createElement('div');
        t.id = 'notifToast';
        t.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%) translateY(-20px);z-index:99998;background:linear-gradient(135deg,rgba(255,255,255,0.95),rgba(251,249,245,0.95));backdrop-filter:blur(12px);border:1px solid rgba(212,175,55,0.3);color:#111827;padding:16px 24px;border-radius:16px;font-size:14px;font-weight:500;box-shadow:0 12px 48px rgba(0,0,0,0.45);max-width:420px;text-align:center;direction:rtl;opacity:0;transition:all 0.4s cubic-bezier(0.22,1,0.36,1);';
        // ═══ نصُّ الإشعار يُكتب كنصّ لا كترميز ═══
        //
        // كان العنوانُ والمتنُ يُلصقان في innerHTML. ومنذ صار واتساب
        // مصدرَ إشعارات، صار في المتن اسمُ ملفّ المُرسِل — وهو نصٌّ
        // يكتبه من يراسل المكتب كما يشاء، ولا يمرّ بأيّ استمارة عندنا.
        // فاسمٌ يحمل وسمَ صورةٍ بمعالجِ خطأ كان يُنفَّذ في متصفّح كلّ
        // موظّفٍ يفتح النظام، داخل جلسته وبصلاحياته — تسريبٌ للجلسة
        // بمجرّد رسالةِ واتساب لا يردّ عليها أحد.
        //
        // (ولا يُكتب هنا مثالٌ حرفيّ: هذا التعليق يُرسَل مع كلّ صفحة،
        //  فيصير النصُّ التوضيحيُّ نفسُه ما يبحث عنه فحصُ XSS.)
        //
        // فالهيكلُ ثابتٌ يُبنى هنا، والنصّان يوضعان بـtextContent —
        // فيُعرضان حرفاً بحرف ولا يُفسَّران ترميزاً مهما كان محتواهما.
        var wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex;align-items:center;gap:12px';

        var icon = document.createElement('div');
        icon.style.cssText = 'flex-shrink:0;width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#D4AF37,#E5C158);display:flex;align-items:center;justify-content:center;font-size:18px;box-shadow:0 4px 12px rgba(212,175,55,0.3)';
        icon.textContent = '🔔';

        var col = document.createElement('div');
        col.style.cssText = 'flex:1;text-align:right';

        var head = document.createElement('div');
        head.style.cssText = 'font-weight:700;color:#A98218;margin-bottom:4px';
        head.textContent = title == null ? '' : String(title);

        var line = document.createElement('div');
        line.style.cssText = 'font-size:12px;color:#A98218';
        line.textContent = msg == null ? '' : String(msg);

        col.appendChild(head);
        col.appendChild(line);
        wrap.appendChild(icon);
        wrap.appendChild(col);
        t.appendChild(wrap);
        document.body.appendChild(t);
        requestAnimationFrame(function() {
            t.style.transform = 'translateX(-50%) translateY(0)';
            t.style.opacity = '1';
        });
        setTimeout(function() {
            t.style.transform = 'translateX(-50%) translateY(-20px)';
            t.style.opacity = '0';
            setTimeout(function() { t.remove(); }, 500);
        }, 5000);
    }
    function pollNotif() {
        fetch('{{ route("notifications.latest") }}', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.has_new && data.notification && data.notification.id !== lastNotifId) {
                lastNotifId = data.notification.id;
                notifSound();
                showNotifToast(data.notification.title || 'إشعار جديد', data.notification.message || '');
            }
        })
        .catch(function() {});
    }
    setInterval(pollNotif, 15000);
    </script>
    @endauth

    <script nonce="{{ $cspNonce }}" src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script nonce="{{ $cspNonce }}">
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('select.ts').forEach(function(el) {
            new TomSelect(el, {
                create: !el.hasAttribute('data-no-create'),
                createOnBlur: !el.hasAttribute('data-no-create'),
                maxOptions: 100,
                placeholder: el.getAttribute('placeholder') || '',
                render: {
                    option_create: function(data, escape) {
                        return '<div class="create">إضافة: <strong>' + escape(data.input) + '</strong></div>';
                    }
                }
            });
        });
    });
    </script>

    @auth
    <style>
        [x-cloak] { display: none !important; }
        .ai-content { line-height: 1.9; }
        .ai-content strong { color: #8B5CF6; font-weight: 700; }
        .ai-content h1, .ai-content h2, .ai-content h3 { color: #6D28D9; font-weight: 700; margin: 0.75rem 0 0.4rem; line-height: 1.5; }
        .ai-content h1 { font-size: 1rem; }
        .ai-content h2 { font-size: 0.95rem; }
        .ai-content h3 { font-size: 0.9rem; }
        .ai-content ul { list-style: disc; padding-inline-start: 1.25rem; margin: 0.4rem 0; }
        .ai-content li { margin: 0.25rem 0; }
        .ai-content p { margin: 0.4rem 0; }
        .ai-content hr { border: 0; border-top: 1px dashed #EDE9FE; margin: 0.6rem 0; }
        .ai-content code { background: #EDE9FE; color: #6D28D9; padding: 0 0.3rem; border-radius: 0.25rem; font-size: 0.85em; }
    </style>

    {{-- Global AI Legal Assistant Widget --}}
    <div x-data="assistant">
        {{-- Floating Button --}}
        <button @click="toggle()" class="fixed bottom-24 md:bottom-6 {{ app()->getLocale() === 'ar' ? 'left-6' : 'right-6' }} z-[45] w-14 h-14 rounded-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white shadow-2xl flex items-center justify-center transition-transform hover:scale-105" title="{{ __('app.ai_chat_title') }}">
            <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            <svg x-show="open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

        {{-- Chat Panel --}}
        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4" class="fixed bottom-[10.5rem] md:bottom-24 {{ app()->getLocale() === 'ar' ? 'left-6' : 'right-6' }} z-[45] w-[380px] max-w-[calc(100vw-2rem)] bg-white border border-emerald-300 rounded-2xl shadow-2xl flex flex-col overflow-hidden" style="height: min(560px, calc(100vh - 220px));">
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 text-white">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/>
                    </svg>
                    <div>
                        <h3 class="font-bold text-sm">{{ __('app.ai_chat_title') }}</h3>
                        <p class="text-[10px] text-emerald-100">{{ __('app.ai_assistant_subtitle') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button @click="clearChat()" class="p-1.5 rounded-lg hover:bg-white/20 text-white transition-colors" title="{{ __('app.ai_chat_clear') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                    <button @click="close()" aria-label="{{ __('app.close') }}" class="p-1.5 rounded-lg hover:bg-white/20 text-white transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Messages --}}
            <div x-ref="box" class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-gray-50" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
                <template x-if="messages.length === 0">
                    <div class="text-center py-8">
                        <div class="w-14 h-14 mx-auto mb-3 bg-emerald-100 rounded-full flex items-center justify-center">
                            <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/>
                            </svg>
                        </div>
                        <p class="text-sm text-gray-600 leading-relaxed max-w-xs mx-auto">{{ __('app.ai_assistant_greeting') }}</p>

                        {{-- شاشة فارغة وحقلُ كتابة لا تقول للموظّف بماذا
                             يبدأ. هذه ثلاثة أسئلة يُفتح بها الباب. --}}
                        <div class="mt-4 space-y-2 max-w-xs mx-auto text-start">
                            <template x-for="q in starters" :key="q">
                                <button type="button" @click="ask(q)"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 hover:border-emerald-300 hover:bg-emerald-50 transition-colors text-start">
                                    <span x-text="q"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-for="m in messages" :key="m.id">
                    <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div class="max-w-[85%]">
                            <div :class="m.role === 'user'
                                ? (m.failed
                                    ? 'bg-red-50 border border-red-200 text-red-800 rounded-2xl rounded-tr-sm px-4 py-2.5'
                                    : 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-2xl rounded-tr-sm px-4 py-2.5 shadow-sm')
                                : 'bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-tl-sm px-4 py-2.5 shadow-sm'">
                                <div class="text-sm leading-relaxed" :class="m.role === 'user' ? 'whitespace-pre-wrap' : 'ai-content'" x-html="m.role === 'assistant' ? md(m.content) : m.content"></div>
                            </div>

                            {{-- تحت كل رسالة: وقتها، ونسخُ الجواب لمن
                                 يريد لصقه في مذكّرة. --}}
                            <div class="mt-1 flex items-center gap-2 px-1 text-[10px] text-gray-400"
                                 :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                                <span x-text="when(m.at)"></span>
                                {{-- كان نصّاً بحجم ١٠ بكسل بلا حشو: هدفٌ لا
                                     يُصاب بالإصبع على الجوّال، ولا يكاد
                                     يُرى. صار أيقونةً ونصّاً في زرٍّ له
                                     حدٌّ وحشوٌ وارتفاعٌ يُلمس. --}}
                                <template x-if="m.role === 'assistant'">
                                    <button type="button" @click="copy(m)"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-gray-500 hover:text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50 transition-colors"
                                        :class="copied === m.id ? 'text-emerald-700 border-emerald-300 bg-emerald-50' : ''"
                                        :title="'{{ __('app.ai_chat_copy') }}'">
                                        <svg x-show="copied !== m.id" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2M5 8h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2Z"/>
                                        </svg>
                                        <svg x-show="copied === m.id" x-cloak class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/>
                                        </svg>
                                        <span x-show="copied !== m.id">{{ __('app.ai_chat_copy') }}</span>
                                        <span x-show="copied === m.id" x-cloak>{{ __('app.ai_chat_copied') }}</span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- ثلاثُ نقاطٍ تنبض بدل كلمة «يكتب»: الكلمة تُقرأ فتشغل،
                     والنقاط تُفهم بلا قراءة — وهي عُرف المحادثات. --}}
                <div x-show="sending" class="flex justify-start">
                    <div class="bg-white border border-gray-200 rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm"
                         role="status" aria-label="{{ __('app.ai_chat_typing') }}">
                        <span class="md-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                    </div>
                </div>

                {{-- سؤالٌ أُجِّل جوابه: لا عطلَ هنا، بل انتظارٌ معلوم --}}
                <div x-show="pending" x-cloak class="text-xs text-gold-dark bg-gold/10 border border-gold/20 rounded-lg p-3 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-text="pending"></span>
                </div>

                {{-- أثناء الإعادة التلقائيّة يُطمأن المحامي، ولا يُعرض عطل --}}
                <div x-show="retrying > 0" x-cloak class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-3">
                    {{ __('app.ai_chat_retrying') }}
                </div>

                <div x-show="error && !retrying" x-cloak class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg p-3">
                    <p x-text="error"></p>
                    {{-- السؤال محفوظ في الخادم؛ إعادة المحاولة لا تعني
                         إعادة الكتابة. --}}
                    <button type="button" x-show="lastAsked" @click="retry()" :disabled="sending"
                        class="mt-2 font-semibold text-red-800 underline underline-offset-4 hover:no-underline disabled:opacity-50">
                        {{ __('app.ai_chat_retry') }}
                    </button>
                </div>
            </div>

            {{-- تأكيد حذف المحادثة — داخل النافذة، بأسلوب التطبيق --}}
            <div x-show="confirmClear" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="px-4 py-3 border-t border-red-100 bg-red-50/70">
                <p class="text-xs font-semibold text-red-800 mb-2">{{ __('app.ai_chat_clear_confirm') }}</p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="doClear()"
                        class="rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-bold px-3.5 py-1.5 transition-colors">
                        {{ __('app.ai_chat_clear_yes') }}
                    </button>
                    <button type="button" @click="confirmClear = false"
                        class="rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 text-xs font-semibold px-3.5 py-1.5 transition-colors">
                        {{ __('app.ai_chat_cancel') }}
                    </button>
                </div>
            </div>

            {{-- Input --}}
            <div class="px-4 py-3 border-t border-gray-200 bg-white">
                <p class="text-[10px] text-gray-400 mb-2">{{ __('app.ai_assistant_disclaimer') }}</p>
                <div class="flex items-end gap-2">
                    {{-- كان سطراً واحداً مهما طال السؤال، فيكتب المحامي
                         فقرةً لا يرى منها إلا آخرها. ينمو الآن إلى حدّ. --}}
                    {{-- الحقل لا يُقفل أثناء الانتظار: الجواب قد يتأخّر
                         ثوانيَ، والمحامي يكتب سؤاله التالي في أثنائها.
                         وقفلُه كان يجعل أيَّ تعثّرٍ يبدو تجميداً. --}}
                    <textarea x-model="input" x-ref="input" rows="1"
                        @input="grow($el)"
                        @keydown.enter.prevent="if (!$event.shiftKey) send()"
                        class="flex-1 rounded-xl bg-gray-50 border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 resize-none overflow-y-auto"
                        style="max-height: 140px;"
                        placeholder="{{ __('app.ai_assistant_placeholder') }}"></textarea>

                    {{-- أثناء الانتظار يصير الزرُّ «إيقاف»: كان الانتظار
                         بابًا مغلقًا لا مخرج منه إلا إعادة تحميل الصفحة. --}}
                    <button x-show="!sending" @click="send()" :disabled="!input.trim()"
                        class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white px-4 py-2.5 rounded-xl font-semibold transition-colors text-sm disabled:opacity-50 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        {{ __('app.ai_chat_send') }}
                    </button>
                    <button x-show="sending" x-cloak @click="stop()"
                        class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2.5 rounded-xl font-semibold transition-colors text-sm flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                        {{ __('app.ai_chat_stop') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script nonce="{{ $cspNonce }}">
    document.addEventListener('alpine:init', () => {
        Alpine.data('assistant', () => ({
            open: false,
            loaded: false,
            messages: [],
            input: '',
            sending: false,
            error: null,
            // آخر سؤال أخفق: يُعاد إرساله بضغطة بدل إعادة كتابته
            lastAsked: null,
            // معرّفه في الخادم — تُعاد المحاولة عليه فلا يتكرّر السؤال
            lastAskedId: null,
            // رقم المحاولة الجارية: يُعرض «يُعيد المحاولة» بدل رسالة عطل
            retrying: 0,
            // إشعارُ سؤالٍ مؤجَّل، ومؤقّتُ ترقّب جوابه
            pending: null,
            watching: null,
            // أداةُ قطع الطلب الجاري، وسؤالُ تأكيد الحذف
            aborter: null,
            confirmClear: false,
            copied: null,
            starters: @js([
                __('app.ai_starter_1'),
                __('app.ai_starter_2'),
                __('app.ai_starter_3'),
            ]),
            when(iso) {
                if (!iso) return '';
                try {
                    return new Date(iso).toLocaleTimeString('{{ app()->getLocale() === 'ar' ? 'ar-OM' : 'en-GB' }}',
                        { hour: '2-digit', minute: '2-digit' });
                } catch (e) { return ''; }
            },
            async copy(m) {
                try {
                    await navigator.clipboard.writeText(m.content);
                    this.copied = m.id;
                    setTimeout(() => { if (this.copied === m.id) this.copied = null; }, 1600);
                } catch (e) { /* متصفّح لا يمنح الحافظة */ }
            },
            ask(text) {
                this.input = text;
                this.send();
            },
            retry() {
                if (!this.lastAsked || this.sending) return;
                const text = this.lastAsked;
                // السؤال المخفق يُنزع من الشاشة ثم يُعاد إرساله
                this.messages = this.messages.filter(m => !(m.role === 'user' && m.content === text && m.failed));
                this.error = null;
                this.input = text;
                this.send();
            },
            grow(el) {
                if (!el) return;
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 140) + 'px';
            },
            md(text) {
                if (!text) return '';
                const esc = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const lines = esc.split('\n');
                let html = '', inList = false;
                const closeList = () => { if (inList) { html += '</ul>'; inList = false; } };
                for (let line of lines) {
                    line = line.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                               .replace(/`([^`]+)`/g, '<code>$1</code>');
                    const h1 = line.match(/^#\s+(.*)/);
                    const h2 = line.match(/^##\s+(.*)/);
                    const h3 = line.match(/^###\s+(.*)/);
                    const hr = /^---+$/.test(line.trim());
                    const li = line.match(/^\s*[-*]\s+(.*)/);
                    if (h1) { closeList(); html += '<h1>' + h1[1] + '</h1>'; }
                    else if (h2) { closeList(); html += '<h2>' + h2[1] + '</h2>'; }
                    else if (h3) { closeList(); html += '<h3>' + h3[1] + '</h3>'; }
                    else if (hr) { closeList(); html += '<hr>'; }
                    else if (li) { if (!inList) { html += '<ul>'; inList = true; } html += '<li>' + li[1] + '</li>'; }
                    else if (!line.trim()) { closeList(); }
                    else { closeList(); html += '<p>' + line + '</p>'; }
                }
                closeList();
                return html;
            },
            scrollChat() {
                const el = this.$refs.box;
                if (el) el.scrollTop = el.scrollHeight;
            },
            toggle() {
                if (this.open) { this.close(); return; }
                this.open = true;
                if (!this.loaded) this.loadHistory();
                this.$nextTick(() => this.scrollChat());
            },
            /*
             * الإغلاق أثناء الكتابة يوقفها.
             *
             * كان الردّ يواصل خلف نافذةٍ مغلقة، فمن أغلق وفتح وجد
             * المساعد «يكتب» ولا سبيل إلى شيء. يُقطع الطلب وتُرفع
             * النقاط — ويبقى الترقّبُ لسؤالٍ مؤجَّلٍ شغّالاً، فجوابه
             * وعدٌ قائم يظهر عند العودة.
             */
            close() {
                if (this.sending) {
                    if (this.aborter) { try { this.aborter.abort(); } catch (e) {} }
                    this.sending = false;
                    this.retrying = 0;
                    this.aborter = null;
                }
                this.confirmClear = false;
                this.open = false;
            },
            async loadHistory() {
                try {
                    const res = await fetch('{{ route("assistant.history") }}', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json().catch(() => null);
                    if (data && data.messages) this.messages = data.messages;
                } catch(e) {}
                this.loaded = true;
                this.$nextTick(() => this.scrollChat());
            },
            // تعثّرٌ عابرٌ لا يُعرض على المحامي — يُعاد عليه أوّلاً.
            //
            // كان أيُّ إخفاقٍ واحد يُظهر رسالة عطلٍ وزرَّ إعادةٍ يدويّ،
            // وأكثرُ ما يُخفق ازدحامٌ لحظيّ يزول في ثوانٍ. فصار
            // المتصفّح يُعيد تلقائياً بفاصلٍ متضاعف، ويُظهر أنّه يُعيد
            // بدل أن يُظهر عطلاً. ولا تُعاد إلا الأخطاء العابرة:
            // مفتاحٌ باطلٌ أو حدُّ طلباتٍ لا تُصلحه إعادة.
            // ٤٢٩ ليست هنا عمداً: الخادم يحوّل ازدحام المزوّد إلى ٥٠٣،
            // فـ٤٢٩ لا تأتي إلا من حدّ المعدّل عندنا — «أبطئ». وإعادتها
            // فوراً ثلاثاً تُغرق الحدّ وتضمن الإخفاق بدل أن تتجنّبه.
            RETRYABLE: [425, 500, 502, 503, 504],
            MAX_TRIES: 3,
            POLL_EVERY: 15000,
            POLL_TIMES: 40,

            async send() {
                const text = this.input.trim();
                if (!text || this.sending) return;
                const local = { id: 'user-' + Date.now(), role: 'user', content: text, at: new Date().toISOString() };
                this.messages.push(local);
                this.lastAsked = text;
                this.input = '';
                this.$nextTick(() => this.grow(this.$refs.input));
                this.error = null;
                this.retrying = 0;
                this.sending = true;
                this.aborter = new AbortController();
                this.$nextTick(() => this.scrollChat());

                try {

                // إن كانت هذه إعادةً لسؤالٍ محفوظ، بُدئ بمعرّفه فلا يتكرّر
                let questionId = this.lastAskedId;

                for (let attempt = 1; attempt <= this.MAX_TRIES; attempt++) {
                    let status = 0, data = null;
                    try {
                        const res = await fetch('{{ route("assistant.chat") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                            // معرّف السؤال المحفوظ يمنع تكراره في المحادثة
                            body: JSON.stringify(questionId ? { message: text, retry_of: questionId } : { message: text }),
                            signal: this.aborter.signal
                        });
                        status = res.status;
                        data = await res.json().catch(() => null);
                        // السؤال حُفظ في الخادم، فيأخذ معرّفه لا معرّفاً محلياً
                        if (data?.question_id) { questionId = data.question_id; local.id = questionId; this.lastAskedId = questionId; }

                        if (res.ok && data?.queued) {
                            // لم يُجب الآن؛ الجواب يأتي في الخلفيّة
                            this.lastAsked = null;
                            this.lastAskedId = null;
                            this.error = null;
                            local.failed = false;
                            this.pending = data.notice || '{{ __("app.ai_chat_queued") }}';
                            this.watchForAnswer(data.question_id);
                            break;
                        }

                        if (res.ok) {
                            this.messages.push({ id: data.id ?? ('ai-' + Date.now()), role: 'assistant', content: data.reply, at: data.at });
                            this.lastAsked = null;
                            this.lastAskedId = null;
                            this.error = null;
                            local.failed = false;
                            break;
                        }

                        if (status === 429) {
                            // حدّ المعدّل عندنا: انتظارٌ لا إعادة
                            local.failed = true;
                            this.error = '{{ __("app.ai_chat_too_fast") }}';
                            break;
                        }
                    } catch (e) {
                        if (e && e.name === 'AbortError') return;   // قطعَه المستخدم
                        status = 0;   // انقطاعُ شبكةٍ — عابرٌ يُعاد عليه
                    }

                    const transient = status === 0 || this.RETRYABLE.includes(status);
                    if (!transient || attempt === this.MAX_TRIES) {
                        local.failed = true;
                        this.error = data?.error
                            || (status === 0 ? '{{ __("app.connection_error") }}' : '{{ __("app.save_error") }}');
                        break;
                    }

                    this.retrying = attempt;
                    this.$nextTick(() => this.scrollChat());
                    await new Promise(r => setTimeout(r, 1200 * attempt + Math.random() * 400));
                }

                } finally {
                    // القفل يُفتح مهما جرى. كان يُفتح في آخر الدالة وحدها،
                    // فأيُّ استثناءٍ يقع قبله يترك `sending` مرفوعاً إلى
                    // الأبد: الحقل مقفل، والزرّ معطّل، ولا مخرج إلا إعادة
                    // تحميل الصفحة — وهو ما وقع فعلاً.
                    this.retrying = 0;
                    this.sending = false;
                    this.aborter = null;
                    this.$nextTick(() => this.scrollChat());
                }
            },

            /*
             * يقطع الطلب الجاري.
             *
             * كان الانتظار باباً مغلقاً: لا إيقافَ ولا كتابة حتى يعود
             * الجواب أو ينتهي الوقت. والقطع لا يمسّ ما حُفظ في الخادم —
             * السؤال باقٍ، وله زرُّ إعادةٍ إن أراد.
             */
            stop() {
                if (this.aborter) { try { this.aborter.abort(); } catch (e) {} }
                this.stopWatching();
                this.retrying = 0;
                this.sending = false;
                this.aborter = null;
                this.error = null;
            },
            /*
             * يترقّب جواباً مؤجَّلاً ويعرضه حين يصل.
             *
             * الجواب يُكتب في قاعدة البيانات من عاملِ الطابور، ولا سبيل
             * إلى إبلاغ المتصفّح به إلا بالسؤال — فيُسأل كلّ ربع دقيقة
             * عشر دقائق. والحدّ مقصود: ترقّبٌ بلا نهاية يستهلك الجهاز
             * ما دام اللسان مفتوحاً، والمحادثة تُحمَّل كاملةً عند فتحها
             * على كل حال.
             */
            watchForAnswer(questionId) {
                if (this.watching) clearInterval(this.watching);
                var left = this.POLL_TIMES;

                this.watching = setInterval(async () => {
                    if (--left < 0) { this.stopWatching(); return; }
                    try {
                        const res = await fetch('{{ route("assistant.history") }}', { headers: { 'Accept': 'application/json' } });
                        const data = await res.json().catch(() => null);
                        if (!data || !data.messages) return;

                        const answered = data.messages.some(m => m.role === 'assistant' && m.id > questionId);
                        if (answered) {
                            this.messages = data.messages;
                            this.stopWatching();
                            this.$nextTick(() => this.scrollChat());
                        }
                    } catch (e) { /* الشبكة ذهبت — تُعاد الدورة التالية */ }
                }, this.POLL_EVERY);
            },
            stopWatching() {
                if (this.watching) clearInterval(this.watching);
                this.watching = null;
                this.pending = null;
            },

            clearChat() {
                // حوارُ المتصفّح (confirm) بشكل النظام لا بشكل التطبيق،
                // ويحجب الصفحة كلَّها. السؤال الآن داخل النافذة نفسها.
                if (!this.messages.length) return;
                this.confirmClear = true;
            },
            async doClear() {
                this.confirmClear = false;
                this.stop();
                this.messages = [];
                this.error = null;
                this.lastAsked = null;
                try {
                    await fetch('{{ route("assistant.clear") }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                    });
                } catch(e) {}
            }
        }));
    });
    </script>
    @endauth

    @auth
        @if (!auth()->user()->isClient())
            <x-doc-viewer />
        @endif
    @endauth

    @include('partials.phone-mask')

</body>
</html>
