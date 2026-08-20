@php
    $officeName = \App\Models\Setting::get('office_name', 'مُداوَلة');
    $isRtl = app()->getLocale() === 'ar';
    $officeLogo = null;
    foreach (['svg', 'png', 'jpg', 'jpeg'] as $ext) {
        if (is_file(public_path("img/office-logo.{$ext}"))) {
            $officeLogo = asset("img/office-logo.{$ext}") . '?v=' . @filemtime(public_path("img/office-logo.{$ext}"));
            $officeLogoType = $ext === 'svg' ? 'image/svg+xml' : "image/{$ext}";
            break;
        }
    }
    {{-- تنبيهات الاشتراك إدارية: تظهر لمدير المكتب فقط — لا للمحامين والموظفين --}}
    $subscriptionInfo = auth()->check() && auth()->user()->isAdmin()
        ? app(\App\Services\SubscriptionService::class)->info()
        : null;
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

    <script nonce="{{ $cspNonce }}">
        (function () {
            try {
                if (localStorage.getItem('theme') === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
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
        tailwind.config = {
            theme: {
                extend: {
                    colors: {

                        gold: { DEFAULT: '#D4AF37', light: '#F0D98A', dark: '#A98218', deep: '#8C6A12', hover: '#E5C158' },

                        primary: { DEFAULT: '#D4AF37', hover: '#E5C158', dark: '#A98218', light: '#F0D98A' },

                        background: '#F7F8FA',

                        surface: '#FFFFFF',

                        text: { DEFAULT: '#111827', secondary: '#4B5563', muted: '#6B7280' },

                        border: '#E2E6EC',

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
        [x-cloak] { display: none !important; }

        /* Warm ivory surfaces (light mode) */
        .bg-white, .bg-white\/60, .bg-white\/70, .bg-white\/80, .bg-white\/90 { background-color: #FFFFFF; }

        * { font-family: 'Tajawal', sans-serif; }
        h1, h2, h3, h4, h5, h6, .font-heading { font-family: 'Cairo', sans-serif; }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #F2F4F7; }
        ::-webkit-scrollbar-thumb { background: #D4AF37; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #E5C158; }

        .sidebar-link { transition: all 0.15s ease; position: relative; }
        .sidebar-link:hover {
            background: rgba(212, 175, 55, 0.08);
            color: #D4AF37;
        }
        .sidebar-link.active {
            background: rgba(212, 175, 55, 0.1);
            color: #D4AF37;
        }
        .sidebar-link.active::before {
            content: '';
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: #D4AF37;
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
        html { background-color: #F7F8FA; }
        html[data-theme="dark"] { background-color: #080B12; color-scheme: dark; }

        /* --- Premium page transitions (View Transitions API) --- */
        @view-transition { navigation: auto; }

        ::view-transition-old(root) {
            animation: pageOut 300ms cubic-bezier(0.4, 0, 0.2, 1) both;
        }
        ::view-transition-new(root) {
            animation: pageIn 600ms cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        ::view-transition-new(root)::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(105deg, transparent 35%, rgba(212, 175, 55, 0.22) 50%, transparent 65%);
            animation: goldSweep 750ms cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes pageOut {
            to { opacity: 0; transform: translateY(8px) scale(0.992); filter: saturate(0.7) brightness(0.85); }
        }
        @keyframes pageIn {
            from { opacity: 0; transform: translateY(18px) scale(0.995); filter: saturate(0.8) brightness(1.05); }
            to { opacity: 1; transform: translateY(0) scale(1); filter: none; }
        }
        @keyframes goldSweep {
            0% { transform: translateX(-100%); opacity: 0; }
            45% { opacity: 1; }
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

        .glass-border { border: 1px solid rgba(212,175,55,0.08); }
        .glass-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(212,175,55,0.08);
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
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.08), transparent);
            background-size: 200% 100%;
            animation: shimmer 3s infinite;
        }

.btn-gold {
            background: linear-gradient(135deg, #E5C158, #D4AF37 45%, #A98218);
            color: #111827;
            transition: all 0.15s ease;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #E5C158, #D4AF37);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(212,175,55,0.3);
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
            background: linear-gradient(135deg, rgba(212,175,55,0.03), transparent);
            border: 1px solid rgba(212,175,55,0.08);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover {
            background: linear-gradient(135deg, rgba(212,175,55,0.06), rgba(212,175,55,0.02));
            border-color: rgba(212,175,55,0.15);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        /* Form inputs */
        .form-input {
            transition: all 0.2s ease;
            border: 1px solid rgba(0,0,0,0.12);
        }
        .form-input:focus {
            border-color: #D4AF37;
            box-shadow: 0 0 0 3px rgba(212,175,55,0.15);
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
            border: 1px solid rgba(212,175,55,0.15);
            border-radius: 16px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-premium:hover {
            border-color: rgba(212,175,55,0.3);
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
            background: #D4AF37;
            transform: scaleX(0);
            transition: transform 0.2s ease;
        }
        .nav-link:hover::after,
        .nav-link.active::after {
            transform: scaleX(1);
        }

::-webkit-scrollbar-track { background: #F2F4F7; }
        ::-webkit-scrollbar-thumb { background: #C9CDD6; border-radius: 8px; }
        input[type=checkbox] { accent-color: #A98218 !important; }
        .ts-wrapper .ts-control { background: #FFFFFF !important; border: 1px solid #E2E6EC !important; color: #111827 !important; }
        .ts-wrapper .ts-control input { color: #111827 !important; }
        .ts-wrapper .ts-control:hover { border-color: #C9CDD6 !important; }
        .ts-wrapper.focus .ts-control { border-color: #A98218 !important; box-shadow: 0 0 0 2px rgba(212,175,55,0.3) !important; }
        .ts-wrapper .ts-dropdown { background: #FFFFFF !important; border: 1px solid #E2E6EC !important; color: #111827 !important; }
        .ts-wrapper .ts-dropdown .option { color: #4B5563 !important; }
        .ts-wrapper .ts-dropdown .option.active { background: rgba(212,175,55,0.12) !important; color: #A98218 !important; }
        .ts-wrapper .ts-dropdown .option:hover { background: rgba(212,175,55,0.06) !important; }
        .ts-wrapper .ts-dropdown .option.highlight { background: rgba(212,175,55,0.05) !important; }
        .ts-wrapper .ts-dropdown .create { color: #A98218 !important; }
        .ts-wrapper .ts-dropdown .no-results { color: #6B7280 !important; }
        .ts-wrapper .ts-control .item { color: #111827 !important; }
        .ts-wrapper.multi .ts-control .item { background: rgba(212,175,55,0.12) !important; border: 1px solid rgba(212,175,55,0.3) !important; color: #A98218 !important; }

        /* Dark mode */
        [data-theme="dark"] { --bg: #080B12; --card: #121826; --text: #FFFFFF; --text-muted: #94A3B8; --border: rgba(212,175,55,0.15); }
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
        [data-theme="dark"] .hover\:bg-gray-50:hover { background-color: #0D111B !important; }
        [data-theme="dark"] .hover\:bg-gray-100:hover { background-color: #182033 !important; }
        [data-theme="dark"] .hover\:text-gold-dark:hover, [data-theme="dark"] .hover\:text-gold-dark:hover { color: #F0D98A !important; }
        [data-theme="dark"] .sidebar-link { color: #94A3B8 !important; }
        [data-theme="dark"] .sidebar-link:hover { background: rgba(212,175,55,0.1) !important; color: #E5C158 !important; }
        [data-theme="dark"] .sidebar-link.active { background: rgba(212,175,55,0.12) !important; color: #D4AF37 !important; }
        [data-theme="dark"] header[style*="background"] { background: rgba(8,11,18,0.95) !important; }
        [data-theme="dark"] .bg-gold/12 { background-color: rgba(212,175,55,0.15) !important; }
        [data-theme="dark"] .text-gold-dark, [data-theme="dark"] .text-gold-dark { color: #D4AF37 !important; }
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
        [data-theme="dark"] .text-yellow-700 { color: #F59E0B !important; }
        [data-theme="dark"] .bg-orange-100 { background-color: rgba(249,115,22,0.15) !important; }
        [data-theme="dark"] .text-orange-700 { color: #FB923C !important; }
        [data-theme="dark"] aside[style*="background"] { background: #0D111B !important; }
        [data-theme="dark"] aside div[style*="border-bottom"] { border-color: rgba(212,175,55,0.1) !important; }
        [data-theme="dark"] .dropdown-dark { background: rgba(8,11,18,0.98) !important; border-color: rgba(212,175,55,0.15) !important; }
        [data-theme="dark"] footer[style*="background"] { background: rgba(8,11,18,0.5) !important; }
        [data-theme="dark"] .btn-gold { background: linear-gradient(135deg, #D4AF37, #A98218) !important; color: #080B12 !important; }
        [data-theme="dark"] .card-premium { background: #121826 !important; border-color: rgba(212,175,55,0.15) !important; }
        [data-theme="dark"] input, [data-theme="dark"] select, [data-theme="dark"] textarea { background-color: #0B1019 !important; border-color: #252D3D !important; color: #FFFFFF !important; }
        [data-theme="dark"] table thead { border-bottom-color: rgba(212,175,55,0.1) !important; }
        [data-theme="dark"] .ts-wrapper .ts-control { background: #0B1019 !important; border-color: rgba(212,175,55,0.15) !important; color: #FFFFFF !important; }
        [data-theme="dark"] .ts-wrapper .ts-dropdown { background: #121826 !important; border-color: #252D3D !important; color: #FFFFFF !important; }
        [data-theme="dark"] .guide-card { background-color: #121826 !important; background-image: linear-gradient(135deg, #121826, #0D111B) !important; }
        [data-theme="dark"] .guide-glass { background-color: rgba(18, 24, 38, 0.6) !important; }
        .font-pill { background-color: rgba(255, 255, 255, 0.78); border-color: rgba(212, 175, 55, 0.35); }
        [data-theme="dark"] .font-pill { background-color: rgba(8, 11, 18, 0.72); border-color: rgba(212, 175, 55, 0.28); }
        [data-theme="dark"] .ts-wrapper .ts-control input { color: #FFFFFF !important; }
        [data-theme="dark"] .ts-wrapper .ts-control input::placeholder { color: #94A3B8 !important; }
        [data-theme="dark"] .ts-wrapper .ts-control .item { color: #FFFFFF !important; }
        [data-theme="dark"] .ts-wrapper .ts-dropdown .ts-option { color: #CBD5E1 !important; }
        [data-theme="dark"] .ts-wrapper .ts-dropdown .ts-option:hover,
        [data-theme="dark"] .ts-wrapper .ts-dropdown .ts-option.active { background-color: rgba(212, 175, 55, 0.18) !important; color: #E5C158 !important; }
        [data-theme="dark"] .ts-wrapper .ts-dropdown .ts-option.selected { color: #D4AF37 !important; }
        [data-theme="dark"] .text-gold-dark { color: #F0D98A !important; }
        [data-theme="dark"] .hover\:text-gold-dark:hover { color: #F0D98A !important; }
</style>
    @stack('styles')
</head>
<body class="font-body min-h-screen" style="background-color: #F7F8FA; color: #111827;" x-data="{ sidebarOpen: true, mobileOpen: false, profileOpen: false, theme: localStorage.getItem('theme') || 'light', fontSize: parseInt(localStorage.getItem('fontSize') || '100', 10) || 100, fontStep(step) { const levels = [100, 110, 125]; let i = levels.indexOf(this.fontSize); if (i === -1) i = 0; const ni = Math.max(0, Math.min(levels.length - 1, i + step)); this.fontSize = levels[ni]; localStorage.setItem('fontSize', this.fontSize); document.documentElement.style.fontSize = (16 * this.fontSize / 100) + 'px'; } }" x-init="$el.closest('html').setAttribute('data-theme', theme)">

    {{-- Mobile Overlay --}}
    <div
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
    <aside
        class="fixed top-0 {{ $isRtl ? 'right-0' : 'left-0' }} h-full z-50 flex flex-col transition-all duration-300 ease-in-out"
        :class="[
            sidebarOpen ? 'sb-open' : 'sb-closed',
            mobileOpen ? 'translate-x-0' : '{{ $isRtl ? 'translate-x-full' : '-translate-x-full' }} md:translate-x-0'
        ]"
            style="background: #FFFFFF; {{ $isRtl ? 'border-left' : 'border-right' }}: 1px solid #E2E6EC;"
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
            <button @click="mobileOpen = false" class="md:hidden text-gray-400 hover:text-gray-800 transition">
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

            <a href="{{ route('tasks.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('tasks.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                <span>{{ __('app.tasks') }}</span>
            </a>

            <a href="{{ route('documents.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('documents.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>{{ __('app.documents') }}</span>
            </a>

            <a href="{{ route('clients.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('clients.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span>{{ __('app.clients') }}</span>
            </a>

            <a href="{{ route('chat.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('chat.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <span>{{ __('app.chat') ?? 'المحادثات' }}</span>
                <span id="chatUnreadBadge" class="hidden mr-auto bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none" style="line-height:14px;">0</span>
            </a>

            @if(!Auth::user()->isClient())
            <a href="{{ route('suggestions.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('suggestions.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                <span>{{ __('app.suggestions_box') }}</span>
                @php $suggestionUnread = \App\Models\Suggestion::where('user_id', auth()->id())->whereNotNull('developer_reply')->where('reply_read', false)->count(); @endphp
                @if($suggestionUnread > 0)
                    <span class="mr-auto bg-green-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none badge-pulse" style="line-height:14px;">{{ $suggestionUnread > 9 ? '9+' : $suggestionUnread }}</span>
                @endif
            </a>
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

            {{-- Admin Section --}}
            @php
                $adminRole = Auth::check() && (in_array(Auth::user()->role, ['admin', 'developer']) || Auth::user()->hasPermission('feasibility.view') || Auth::user()->hasPermission('audit_log.view') || Auth::user()->hasPermission('settings.manage') || Auth::user()->hasPermission('backup.manage'));
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

                @if(Auth::user()->hasPermission('settings.manage') || in_array(Auth::user()->role, ['admin', 'developer']))
                <a href="{{ route('settings.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>{{ __('app.settings') }}</span>
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

                @if(in_array(Auth::user()->role, ['admin', 'developer', 'lawyer', 'staff']))
                <a href="{{ route('reports.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>{{ __('app.reports') }}</span>
                </a>
                @endif

                @if(in_array(Auth::user()->role, ['admin', 'developer', 'lawyer', 'staff']))
                <a href="{{ route('evaluations.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm {{ request()->routeIs('evaluations.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8m-4-4v4m-7-4h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span>{{ __('app.evaluations') }}</span>
                </a>
                @endif

@if(Auth::user()->role === 'developer')
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
            @endif
        </nav>

        {{-- Sidebar Footer --}}
        <div class="p-3 space-y-0.5" style="border-top: 1px solid #E2E6EC;">
            <a href="{{ route('guide') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-500 text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <span class="sidebar-footer-text">{{ __('app.usage_guide') }}</span>
            </a>

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
    </aside>

    {{-- Main Content --}}
    <div
        class="content-area transition-all duration-300 min-h-screen"
        :class="sidebarOpen ? 'ct-open' : 'ct-closed'"
    >
        {{-- Top Bar --}}
        <header class="sticky top-0 z-30" style="background: rgba(255,255,255,0.95); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border-bottom: 1px solid rgba(212,175,55,0.08);">
            <div class="flex items-center justify-between h-16 px-4 sm:px-6">
                {{-- Right Side: Hamburger + Breadcrumb --}}
                <div class="flex items-center gap-3">
                    <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 rounded-xl text-gray-400 hover:text-gray-800 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <button @click="sidebarOpen = !sidebarOpen" class="hidden md:inline-flex p-2 rounded-xl text-gray-400 hover:text-gray-800 transition">
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

                    {{-- Theme Toggle --}}
                    <button @click="theme = theme === 'dark' ? 'light' : 'dark'; $el.closest('html').setAttribute('data-theme', theme); localStorage.setItem('theme', theme)"
                        class="p-2 rounded-xl transition" :class="theme === 'dark' ? 'text-gold-light hover:text-gold-light' : 'text-gray-400 hover:text-gold-dark'" title="{{ app()->getLocale() === 'ar' ? 'تغيير السمة' : 'Toggle Theme' }}">
                        <svg x-show="theme === 'light'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
                        </svg>
                        <svg x-show="theme === 'dark'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                        </svg>
                    </button>
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
                                        $notifTitle = $notification->title ?? ($notification->type === 'chat' ? __('app.new_message') : null);
                                    @endphp
                                        @if($notification->is_read)
                                        <div class="block px-4 py-3 transition" style="border-bottom: 1px solid rgba(212,175,55,0.04);">
                                            <div class="flex items-center justify-between">
                                                <p class="text-sm font-medium text-gray-500">{{ $notifTitle }}</p>
                                                @if($notification->message_count > 1)
                                                    <span class="text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded-full">{{ $notification->message_count }}</span>
                                                @endif
                                            </div>
                                            <p class="text-sm text-gray-500">{{ $notification->message }}</p>
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
                                                <p class="text-sm text-gray-700">{{ $notification->message }}</p>
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
        <main class="p-4 sm:p-6 lg:p-8 page-enter">
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
                if ($todayMsg && !Auth::user()->isClient() && !$todayMsgSeen) {
                    $todayMsgHtml = preg_replace(
                        '/الاقتراحات/u',
                        '<a href="' . e(route('suggestions.index')) . '" data-seen="' . e(route('announcements.seen', $todayMsg)) . '" class="underline font-bold" @click="fetch($el.dataset.seen, { method: \'POST\', headers: { \'X-CSRF-TOKEN\': document.querySelector(\'meta[name=csrf-token]\')?.content } }).catch(() => {})">$0</a>',
                        $todayMsgHtml
                    );
                }
            @endphp
            @if($todayMsg && !$todayMsgSeen)
                <div x-data="{ hidden: false, locked: false }" x-show="!hidden" x-cloak x-transition:leave="transition ease-in duration-300" x-transition:leave-end="opacity-0 scale-95" class="fixed inset-0 z-[100] flex items-center justify-center p-4" dir="rtl">
                    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="fetch('{{ route('announcements.seen', $todayMsg) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } }).catch(() => {}).finally(() => { hidden = true; })"></div>
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

        {{-- Footer --}}
        <footer style="border-top: 1px solid #E2E6EC; background: rgba(244,242,238,0.5);">
            <div class="px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <a href="https://dev.riyami.om/" target="_blank" class="text-sm font-heading font-bold hover:opacity-80 transition-opacity" style="color: #E5C158;">مُداوَلة</a>
                    <span class="text-xs text-gray-400">&copy;</span>
                    <span class="text-sm text-gray-500">{{ date('Y') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <p class="text-xs text-gray-400">{{ $officeName }}</p>
                    <span class="text-gray-300">|</span>
                    <a href="https://dev.riyami.om/" target="_blank" class="text-xs text-gray-400 hover:text-gold-dark transition">{{ __('app.developer_credit') }}</a>
                </div>
            </div>
        </footer>
    </div>

    @auth
    <form id="autoLogoutForm" action="{{ route('logout') }}" method="POST" style="display:none;">
        @csrf
    </form>

    <div id="autoLogoutOverlay" style="display:none;" class="fixed inset-0 z-[9999] flex items-center justify-center" onclick="if(event.target===this)autoLogoutDismiss()">
        <div class="bg-white border border-gray-200 rounded-2xl p-8 max-w-sm mx-4 text-center shadow-2xl card-premium">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-amber-500/10 flex items-center justify-center">
                <svg class="w-8 h-8 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
            </div>
            <h3 class="text-gray-900 font-bold text-lg mb-2" style="font-family: 'Cairo', sans-serif;">{{ __('app.session_warning_title') }}</h3>
            <p class="text-gray-500 text-sm mb-4">{{ __('app.session_warning_message') }} <span class="text-amber-400 font-bold" id="autoLogoutCountdown">60</span></p>
            <div class="w-full bg-gray-200 rounded-full h-2 mb-6">
                <div id="autoLogoutBar" class="bg-amber-400 h-2 rounded-full transition-all duration-1000" style="width: 100%"></div>
            </div>
            <div class="flex gap-3">
                <button onclick="autoLogoutDismiss()" class="flex-1 btn-gold py-3 rounded-xl font-bold text-sm">{{ __('app.continue') }}</button>
                <button onclick="document.getElementById('autoLogoutForm').submit()" class="flex-1 btn-ghost py-3 rounded-xl font-medium text-sm">{{ __('app.logout') }}</button>
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
        var TIMEOUT = 10 * 60 * 1000;
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
            .then(function(res) { if (onDone) onDone(res.status); })
            .catch(function() { if (onDone) onDone(0); });
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
                    clearInterval(countdownTimer);
                    clearInterval(keepAliveTimer);
                    document.getElementById('autoLogoutForm').submit();
                }
            }, 1000);
            // كل 10 ثوانٍ أثناء التنبيه نجدد الجلسة عند السيرفر حتى يُسجَّل
            // الخروج التلقائي بشكل صحيح عند انتهاء العد
            clearInterval(keepAliveTimer);
            keepAliveTimer = setInterval(function() { sendKeepAlive(); }, 10000);
        }

        function updateDisplay() {
            var el = document.getElementById('autoLogoutCountdown');
            var bar = document.getElementById('autoLogoutBar');
            if (el) el.textContent = countdownVal;
            if (bar) bar.style.width = (countdownVal / WARNING * 100) + '%';
        }

        window.autoLogoutDismiss = function() {
            var overlay = document.getElementById('autoLogoutOverlay');
            if (overlay) overlay.style.display = 'none';
            clearInterval(countdownTimer);
            clearInterval(keepAliveTimer);
            sendKeepAlive();
            resetTimer();
        };

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
        indicator.style.cssText = 'position:fixed;bottom:12px;left:12px;z-index:9999;display:none;padding:4px 10px;border-radius:8px;font-size:11px;color:#A98218;background:rgba(244,242,238,0.9);border:1px solid rgba(212,175,55,0.3);transition:opacity 0.3s;pointer-events:none;';
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
        t.innerHTML = '<div style="display:flex;align-items:center;gap:12px"><div style="flex-shrink:0;width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#D4AF37,#E5C158);display:flex;align-items:center;justify-content:center;font-size:18px;box-shadow:0 4px 12px rgba(212,175,55,0.3)">🔔</div><div style="flex:1;text-align:right"><div style="font-weight:700;color:#A98218;margin-bottom:4px">' + title + '</div><div style="font-size:12px;color:#A98218">' + msg + '</div></div></div>';
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
        <button @click="toggle()" class="fixed bottom-6 {{ app()->getLocale() === 'ar' ? 'left-6' : 'right-6' }} z-40 w-14 h-14 rounded-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white shadow-2xl flex items-center justify-center transition-transform hover:scale-105" title="{{ __('app.ai_chat_title') }}">
            <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            <svg x-show="open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

        {{-- Chat Panel --}}
        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4" class="fixed bottom-24 {{ app()->getLocale() === 'ar' ? 'left-6' : 'right-6' }} z-40 w-[380px] max-w-[calc(100vw-2rem)] bg-white border border-emerald-300 rounded-2xl shadow-2xl flex flex-col overflow-hidden" style="height: min(560px, calc(100vh - 140px));">
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
                    <button @click="open = false" class="p-1.5 rounded-lg hover:bg-white/20 text-white transition-colors">
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
                    </div>
                </template>

                <template x-for="m in messages" :key="m.id">
                    <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div :class="m.role === 'user'
                            ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-2xl rounded-tr-sm max-w-[85%] px-4 py-2.5 shadow-sm'
                            : 'bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-tl-sm max-w-[85%] px-4 py-2.5 shadow-sm'">
                            <div class="text-sm leading-relaxed" :class="m.role === 'user' ? 'whitespace-pre-wrap' : 'ai-content'" x-html="m.role === 'assistant' ? md(m.content) : m.content"></div>
                        </div>
                    </div>
                </template>

                <div x-show="sending" class="flex justify-start">
                    <div class="bg-white border border-gray-200 rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-xs text-gray-500">{{ __('app.ai_chat_typing') }}</span>
                    </div>
                </div>

                <div x-show="error" x-text="error" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg p-3"></div>
            </div>

            {{-- Input --}}
            <div class="px-4 py-3 border-t border-gray-200 bg-white">
                <p class="text-[10px] text-gray-400 mb-2">{{ __('app.ai_assistant_disclaimer') }}</p>
                <div class="flex items-end gap-2">
                    <textarea x-model="input" rows="1" :disabled="sending"
                        @keydown.enter.prevent="if (!$event.shiftKey) send()"
                        class="flex-1 rounded-xl bg-gray-50 border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 resize-none disabled:opacity-50"
                        placeholder="{{ __('app.ai_assistant_placeholder') }}"></textarea>
                    <button @click="send()" :disabled="sending || !input.trim()"
                        class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white px-4 py-2.5 rounded-xl font-semibold transition-colors text-sm disabled:opacity-50 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        {{ __('app.ai_chat_send') }}
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
                this.open = !this.open;
                if (this.open && !this.loaded) this.loadHistory();
                this.$nextTick(() => this.scrollChat());
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
            async send() {
                const text = this.input.trim();
                if (!text || this.sending) return;
                this.messages.push({ id: 'user-' + Date.now(), role: 'user', content: text });
                this.input = '';
                this.error = null;
                this.sending = true;
                this.$nextTick(() => this.scrollChat());
                try {
                    const res = await fetch('{{ route("assistant.chat") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify({ message: text })
                    });
                    const data = await res.json().catch(() => null);
                    if (!res.ok) {
                        this.error = data?.error || '{{ __("app.save_error") }}';
                    } else {
                        this.messages.push({ id: 'ai-' + Date.now(), role: 'assistant', content: data.reply });
                    }
                } catch(e) {
                    this.error = '{{ __("app.connection_error") }}';
                }
                this.sending = false;
                this.$nextTick(() => this.scrollChat());
            },
            async clearChat() {
                this.messages = [];
                this.error = null;
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

</body>
</html>
