@php
    $officeName = \App\Models\Setting::get('office_name', 'LexPro');
@endphp
<!DOCTYPE html>
<html dir="rtl" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('app.dashboard')) - {{ $officeName }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.add('light-theme');
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: { DEFAULT: '#111B2E', light: '#1a2744', dark: '#0A0F1E' },
                        gold: { DEFAULT: '#C9A55A', light: '#E0C878', dark: '#A8903E', 50: '#FBF7EC' },
                        ivory: { DEFAULT: '#F5F0E8', light: '#FAF8F4', dark: '#EDE5D6' },
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
        * { font-family: 'Tajawal', sans-serif; }
        h1, h2, h3, h4, h5, h6, .font-heading { font-family: 'Cairo', sans-serif; }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #0A0F1E; }
        ::-webkit-scrollbar-thumb { background: #C9A55A; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #E0C878; }

        .sidebar-link { transition: all 0.15s ease; position: relative; }
        .sidebar-link:hover {
            background: rgba(201, 165, 90, 0.08);
            color: #C9A55A;
        }
        .sidebar-link.active {
            background: rgba(201, 165, 90, 0.1);
            color: #C9A55A;
        }
        .sidebar-link.active::before {
            content: '';
            position: absolute;
            right: -2px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: #C9A55A;
            border-radius: 3px;
        }

        .sb-closed .sidebar-link span,
        .sb-closed .sidebar-section-title,
        .sb-closed .sidebar-logo-text,
        .sb-closed .sidebar-footer-text { display: none; }
        .sb-closed .sidebar-link { justify-content: center; padding-left: 0; padding-right: 0; }
        .sb-closed .sidebar-link svg { margin: 0; }

        .content-area { transition: margin-right 0.3s cubic-bezier(0.4, 0, 0.2, 1); }

        /* Sidebar width toggles (not reliant on Tailwind CDN JIT) */
        .sb-open { width: 16rem; }
        .sb-closed { width: 72px; }
        .ct-open { margin-right: 16rem; }
        .ct-closed { margin-right: 72px; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.2s ease-out; }

        @keyframes slideDown { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
        .animate-slide-down { animation: slideDown 0.15s ease-out; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .badge-pulse { animation: pulse 2s infinite; }

        .glass-border { border: 1px solid rgba(201,165,90,0.08); }
        .glass-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(201,165,90,0.08);
        }

        .dropdown-dark {
            background: rgba(14, 20, 38, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(201,165,90,0.1);
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        .gold-shimmer {
            background: linear-gradient(90deg, transparent, rgba(201, 165, 90, 0.08), transparent);
            background-size: 200% 100%;
            animation: shimmer 3s infinite;
        }

        .btn-gold {
            background: linear-gradient(135deg, #C9A55A, #B8933E);
            color: #111B2E;
            transition: all 0.15s ease;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #D4B06A, #C9A55A);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(201,165,90,0.3);
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
            background: linear-gradient(135deg, rgba(201,165,90,0.03), transparent);
            border: 1px solid rgba(201,165,90,0.08);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover {
            background: linear-gradient(135deg, rgba(201,165,90,0.06), rgba(201,165,90,0.02));
            border-color: rgba(201,165,90,0.15);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        /* Form inputs */
        .form-input {
            transition: all 0.2s ease;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .form-input:focus {
            border-color: #C9A55A;
            box-shadow: 0 0 0 3px rgba(201,165,90,0.1);
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
            background: linear-gradient(135deg, rgba(17,27,46,0.8), rgba(17,27,46,0.95));
            border: 1px solid rgba(201,165,90,0.08);
            border-radius: 16px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-premium:hover {
            border-color: rgba(201,165,90,0.15);
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
            background: #C9A55A;
            transform: scaleX(0);
            transition: transform 0.2s ease;
        }
        .nav-link:hover::after,
        .nav-link.active::after {
            transform: scaleX(1);
        }

        /* ========================================
           LIGHT THEME - Soft, Eye-Comfortable Palette
           Warm tones, reduced contrast, easy on eyes
           ======================================== */
        .light-theme body {
            background: #f7f2eb !important;
            color: #3d3d4a !important;
        }

        .light-theme .content-area {
            color: #3d3d4a !important;
        }

        .light-theme .content-area > header {
            background: rgba(247, 242, 235, 0.92) !important;
            backdrop-filter: blur(20px) !important;
            border-bottom: 1px solid #d5cfc4 !important;
        }
        .light-theme .content-area > header button,
        .light-theme .content-area > header a {
            color: #7a7a7a !important;
        }
        .light-theme .content-area > header button:hover,
        .light-theme .content-area > header a:hover {
            color: #C9A55A !important;
        }

        /* Cards - warm off-white, not pure white */
        .light-theme .bg-navy,
        .light-theme .bg-\[\#111B2E\] {
            background: #fdfbf9 !important;
            border-color: #d5cfc4 !important;
            box-shadow: 0 1px 3px rgba(160,140,110,0.06), 0 4px 12px rgba(0,0,0,0.03) !important;
        }
        .light-theme .card-premium {
            background: #fdfbf9 !important;
            border-color: #d5cfc4 !important;
        }

        /* Stat cards */
        .light-theme .stat-card {
            background: #fdfbf9 !important;
            border-color: #d5cfc4 !important;
        }
        .light-theme .stat-card:hover {
            background: #faf6ef !important;
            border-color: rgba(201,165,90,0.35) !important;
        }

        /* Inputs - warm field bg with clear borders */
        .light-theme .content-area input,
        .light-theme .content-area textarea,
        .light-theme .content-area select {
            background: #f5f0e9 !important;
            color: #3d3d4a !important;
            border-color: #d0c9be !important;
        }
        .light-theme .content-area input:focus,
        .light-theme .content-area textarea:focus,
        .light-theme .content-area select:focus {
            border-color: #C9A55A !important;
            box-shadow: 0 0 0 3px rgba(201, 165, 90, 0.15) !important;
        }
        .light-theme .content-area input::placeholder,
        .light-theme .content-area textarea::placeholder {
            color: #b0a99e !important;
        }
        .light-theme .content-area select option {
            background: #fdfbf9 !important;
            color: #3d3d4a !important;
        }

        /* Text colors - warm brown undertones instead of cool blue */
        .light-theme .content-area .text-white { color: #3d3d4a !important; }
        .light-theme .content-area .text-white\/80 { color: #4a4a55 !important; }
        .light-theme .content-area .text-white\/70 { color: #555560 !important; }
        .light-theme .content-area .text-white\/60 { color: #60606a !important; }
        .light-theme .content-area .text-white\/50 { color: #6a6a75 !important; }
        .light-theme .content-area .text-white\/40 { color: #808088 !important; }
        .light-theme .content-area .text-white\/30 { color: #95959c !important; }
        .light-theme .content-area .text-white\/20 { color: #aaaaaf !important; }
        .light-theme .content-area .text-ivory,
        .light-theme .content-area .text-ivory\/90 { color: #3d3d4a !important; }
        .light-theme .content-area .text-ivory\/80 { color: #4a4a55 !important; }
        .light-theme .content-area .text-ivory\/70 { color: #555560 !important; }
        .light-theme .content-area .text-ivory\/60 { color: #60606a !important; }
        .light-theme .content-area .text-ivory\/50 { color: #6a6a75 !important; }
        .light-theme .content-area .text-ivory\/40 { color: #808088 !important; }
        .light-theme .content-area .text-ivory\/30 { color: #95959c !important; }
        .light-theme .content-area .text-ivory\/10 { color: #b5b5b8 !important; }
        .light-theme .content-area .text-ivory\/5 { color: #c8c8cb !important; }
        .light-theme .content-area .bg-gray-50 { background: #ede8e1 !important; }
        .light-theme .content-area .bg-gray-100 { background: #e5dfd8 !important; }

        /* Borders - clear structure, warm tones */
        .light-theme .content-area .border-white\/10,
        .light-theme .content-area .border-white\/15,
        .light-theme .content-area .border-white\/20 { border-color: #d5cfc4 !important; }
        .light-theme .content-area .border-white\/5 { border-color: #e3ddd4 !important; }
        .light-theme .content-area .border-ivory\/10 { border-color: #d5cfc4 !important; }
        .light-theme .content-area .border-ivory\/5 { border-color: #e3ddd4 !important; }
        .light-theme .content-area .border-ivory\/30 { border-color: #c5beB2 !important; }

        /* Background overrides */
        .light-theme .content-area .bg-white\/5 { background: #f5f0e9 !important; }
        .light-theme .content-area .bg-white\/\[0\.03\] { background: #f3eee7 !important; }
        .light-theme .content-area .bg-white\/\[0\.04\] { background: #f3eee7 !important; }
        .light-theme .content-area .bg-navy-lighter { background: #ede8e1 !important; }
        .light-theme .content-area .bg-navy-light { background: #fdfbf9 !important; }
        .light-theme .content-area .bg-ivory\/10 { background: rgba(160,140,110,0.06) !important; }

        /* Hover states */
        .light-theme .content-area .hover\:bg-navy-lighter\/50:hover { background: #e5dfd8 !important; }
        .light-theme .content-area .hover\:bg-white\/10:hover { background: #f2ece4 !important; }
        .light-theme .content-area .hover\:bg-\[#0f2240\]:hover { background: #f2ece4 !important; }
        .light-theme .content-area .hover\:text-white:hover { color: #3d3d4a !important; }

        /* Table */
        .light-theme .content-area table { color: #3d3d4a !important; }
        .light-theme .content-area thead th {
            color: #60606a !important;
            border-bottom-color: #d5cfc4 !important;
            background: #ede8e1 !important;
            font-weight: 600;
        }
        .light-theme .content-area tbody td { border-bottom-color: #e3ddd4 !important; color: #3d3d4a !important; }
        .light-theme .content-area tbody tr:hover td { background: #f5f0e9 !important; }
        .light-theme .content-area .divide-y.divide-white\/5 > *,
        .light-theme .content-area .divide-y.divide-ivory\/5 > * { border-color: #e3ddd4 !important; }

        /* Status badges - deeper for contrast on light */
        .light-theme .content-area .text-green-400 { color: #166534 !important; }
        .light-theme .content-area .text-yellow-400 { color: #854d0e !important; }
        .light-theme .content-area .text-red-400 { color: #b91c1c !important; }
        .light-theme .content-area .text-blue-400 { color: #1d4ed8 !important; }
        .light-theme .content-area .text-emerald-400 { color: #047857 !important; }
        .light-theme .content-area .text-amber-400 { color: #b45309 !important; }
        .light-theme .content-area .text-purple-400 { color: #6d28d9 !important; }
        .light-theme .content-area .text-red-300 { color: #b91c1c !important; }

        /* Badge backgrounds */
        .light-theme .content-area .bg-green-500\/15 { background: rgba(34,197,94,0.08) !important; }
        .light-theme .content-area .bg-yellow-500\/15 { background: rgba(234,179,8,0.08) !important; }
        .light-theme .content-area .bg-red-500\/15 { background: rgba(239,68,68,0.08) !important; }
        .light-theme .content-area .bg-blue-500\/15 { background: rgba(59,130,246,0.08) !important; }
        .light-theme .content-area .bg-gray-500\/15 { background: rgba(107,114,128,0.06) !important; }
        .light-theme .content-area .bg-emerald-500\/15 { background: rgba(16,185,129,0.08) !important; }
        .light-theme .content-area .bg-purple-500\/15 { background: rgba(168,85,247,0.08) !important; }
        .light-theme .content-area .bg-amber-500\/15 { background: rgba(245,158,11,0.08) !important; }
        .light-theme .content-area .bg-red-500\/10 { background: rgba(239,68,68,0.05) !important; }
        .light-theme .content-area .bg-green-500\/10 { background: rgba(34,197,94,0.05) !important; }
        .light-theme .content-area .bg-blue-500\/10 { background: rgba(59,130,246,0.05) !important; }
        .light-theme .content-area .bg-yellow-500\/10 { background: rgba(234,179,8,0.05) !important; }
        .light-theme .content-area .bg-red-500\/20 { background: rgba(239,68,68,0.07) !important; }
        .light-theme .content-area .bg-green-500\/20 { background: rgba(34,197,94,0.07) !important; }
        .light-theme .content-area .bg-blue-500\/20 { background: rgba(59,130,246,0.07) !important; }
        .light-theme .content-area .bg-gold\/5 { background: rgba(201,165,90,0.07) !important; }
        .light-theme .content-area .bg-gold\/10 { background: rgba(201,165,90,0.10) !important; }
        .light-theme .content-area .bg-gold\/20 { background: rgba(201,165,90,0.14) !important; }

        /* Gold text */
        .light-theme .content-area .text-gold { color: #8a6e28 !important; }
        .light-theme .content-area h1.text-\[\#C9A55A\] { color: #8a6e28 !important; }

        /* Labels */
        .light-theme .content-area label { color: #555560 !important; }

        /* Buttons */
        .light-theme .btn-ghost {
            background: rgba(160,140,110,0.06) !important;
            color: #555560 !important;
        }
        .light-theme .btn-ghost:hover {
            background: rgba(160,140,110,0.10) !important;
            color: #3d3d4a !important;
        }

        /* Dropdowns */
        .light-theme .dropdown-dark {
            background: #fdfbf9 !important;
            border: 1px solid #d5cfc4 !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06) !important;
        }
        .light-theme .dropdown-dark h3,
        .light-theme .dropdown-dark a p,
        .light-theme .dropdown-dark span { color: #3d3d4a !important; }
        .light-theme .dropdown-dark a { color: #555560 !important; }
        .light-theme .dropdown-dark a:hover { background: #ede8e1 !important; }
        .light-theme .dropdown-dark [style*="border-bottom"] { border-color: #e3ddd4 !important; }
        .light-theme .dropdown-dark [style*="border-top"] { border-color: #e3ddd4 !important; }

        /* Pagination */
        .light-theme .content-area nav a { background: #fdfbf9 !important; color: #555560 !important; border: 1px solid #d5cfc4 !important; }
        .light-theme .content-area nav a:hover { border-color: #C9A55A !important; color: #C9A55A !important; }
        .light-theme .content-area .bg-\[\#C9A55A\] { background: #C9A55A !important; color: #111B2E !important; }

        /* Scrollbar */
        .light-theme ::-webkit-scrollbar-track { background: #ede8e1; }
        .light-theme ::-webkit-scrollbar-thumb { background: #b5a98c; border-radius: 3px; }

        /* Empty state */
        .light-theme .content-area .text-white\/20 { color: #aaaaaf !important; }
        .light-theme .content-area .text-white\/10 { color: #c8c8cb !important; }

        /* Checkbox */
        .light-theme input[type="checkbox"] { accent-color: #C9A55A !important; }

        /* Alert enhancement */
        .light-theme .bg-red-500\/10 { background: rgba(239,68,68,0.04) !important; }

        /* Gold border accent cards */
        .light-theme .border-\[\#C9A55A\]\/20 { border-color: rgba(201,165,90,0.25) !important; }

        /* Sidebar in light mode - keep dark but slightly lighter */
        .light-theme aside[style*="linear-gradient"] {
            background: linear-gradient(180deg, #161e30 0%, #0d1220 100%) !important;
        }
        .light-theme aside .text-white\/50 { color: rgba(255,255,255,0.55) !important; }
        .light-theme aside .text-white\/20 { color: rgba(255,255,255,0.25) !important; }
        .light-theme aside .sidebar-link:hover { background: rgba(201,165,90,0.10) !important; }
    </style>
    @stack('styles')
</head>
<body class="font-body min-h-screen" style="background-color: #0A0F1E; color: rgba(255,255,255,0.8);" x-data="{ sidebarOpen: true, mobileOpen: false, profileOpen: false }">

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
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden"
    ></div>

    {{-- Sidebar --}}
    <aside
        class="fixed top-0 right-0 h-full z-50 flex flex-col transition-all duration-300 ease-in-out"
        :class="[
            sidebarOpen ? 'sb-open' : 'sb-closed',
            mobileOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'
        ]"
        style="background: linear-gradient(180deg, #111B2E 0%, #060A14 100%); border-left: 1px solid rgba(201,165,90,0.06);"
    >
        {{-- Logo --}}
        <div class="flex items-center justify-between h-16 px-4" style="border-bottom: 1px solid rgba(201,165,90,0.06);">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#C9A55A] to-[#A8903E] flex items-center justify-center flex-shrink-0 shadow-lg" style="box-shadow: 0 8px 24px rgba(201, 165, 90, 0.25);">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                    </svg>
                </div>
                <span class="sidebar-logo-text text-white font-heading font-bold text-lg whitespace-nowrap" style="background: linear-gradient(135deg, #C9A55A, #E0C878); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">{{ $officeName }}</span>
            </div>
            <button @click="mobileOpen = false" class="lg:hidden text-white/40 hover:text-white/80 transition">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-0.5">
            <p class="sidebar-section-title text-[11px] font-bold text-white/20 uppercase tracking-wider px-3 mb-3">{{ __('app.main_section') }}</p>

            <a href="{{ route('dashboard') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>{{ __('app.dashboard') }}</span>
            </a>

            <a href="{{ route('cases.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm {{ request()->routeIs('cases.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span>{{ __('app.cases') }}</span>
            </a>

            <a href="{{ route('sessions.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm {{ request()->routeIs('sessions.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>{{ __('app.sessions') }}</span>
            </a>

            <a href="{{ route('tasks.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm {{ request()->routeIs('tasks.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                <span>{{ __('app.tasks') }}</span>
            </a>

            <a href="{{ route('documents.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm {{ request()->routeIs('documents.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>{{ __('app.documents') }}</span>
            </a>

            <a href="{{ route('clients.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm {{ request()->routeIs('clients.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span>{{ __('app.clients') }}</span>
            </a>

            {{-- Admin Section --}}
            @if(Auth::check() && in_array(Auth::user()->role, ['admin', 'developer']))
                <div class="pt-5 pb-2">
                    <p class="sidebar-section-title text-[11px] font-bold text-white/20 uppercase tracking-wider px-3 mb-3">{{ __('app.admin_section') }}</p>
                </div>

                <a href="{{ route('users.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span>{{ __('app.users') }}</span>
                </a>

                <a href="{{ route('feasibility.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm {{ request()->routeIs('feasibility.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span>{{ __('app.feasibility_study') }}</span>
                </a>

                <a href="{{ route('audit-log.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm {{ request()->routeIs('audit-log.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    <span>{{ __('app.audit_log') }}</span>
                </a>

                <a href="{{ route('settings.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>{{ __('app.settings') }}</span>
                </a>

                <a href="{{ route('backup.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm {{ request()->routeIs('backup.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                    </svg>
                    <span>{{ __('app.backup') }}</span>
                </a>

                <a href="{{ route('reports.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>{{ __('app.reports') }}</span>
                </a>
            @endif
        </nav>

        {{-- Sidebar Footer --}}
        <div class="p-3 space-y-0.5" style="border-top: 1px solid rgba(201,165,90,0.06);">
            <a href="{{ route('language.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                </svg>
                <span class="sidebar-footer-text">{{ app()->getLocale() === 'ar' ? __('app.switch_to_en') : __('app.switch_to_ar') }}</span>
            </a>

            <a href="{{ route('profile.edit') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span class="sidebar-footer-text">{{ __('app.profile') }}</span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-white/50 text-sm w-full hover:text-red-400">
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
        <header class="sticky top-0 z-30" style="background: rgba(10, 15, 30, 0.85); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border-bottom: 1px solid rgba(201,165,90,0.08);">
            <div class="flex items-center justify-between h-16 px-4 sm:px-6">
                {{-- Right Side: Hamburger + Breadcrumb --}}
                <div class="flex items-center gap-3">
                    <button @click="mobileOpen = !mobileOpen" class="lg:hidden p-2 rounded-xl text-white/40 hover:text-white/80 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <button @click="sidebarOpen = !sidebarOpen" class="hidden lg:inline-flex p-2 rounded-xl text-white/40 hover:text-white/80 transition">
                        <svg x-show="sidebarOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                        </svg>
                        <svg x-show="!sidebarOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                        </svg>
                    </button>
                    @yield('breadcrumb')
                </div>

                {{-- Global Search --}}
                <div x-data="{ open: false, query: '', results: [], searching: false }" class="relative mx-2 flex-1 max-w-md">
                    <div class="relative">
                        <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-ref="searchInput" x-model="query" @input.debounce.300ms="if(query.length > 1) { searching = true; fetch('{{ route('search') }}?q=' + encodeURIComponent(query), { headers: {'Accept': 'application/json'} }).then(r => r.json()).then(d => { results = d; searching = false; }).catch(() => { results = []; searching = false; }) } else { results = [] }" @focus="open = query.length > 1" @click.away="open = false" @keydown.escape="open = false" @keydown.enter="if(results.length) window.location = results[0].url" type="text" placeholder="{{ __('app.search') }}..." class="w-full bg-white/5 border border-white/10 rounded-xl px-9 py-2 text-sm text-white/70 placeholder-white/30 focus:outline-none focus:border-[#C9A55A]/50 focus:bg-white/10 transition-all">
                    </div>
                    <div x-show="open && results.length > 0" x-cloak class="absolute top-full right-0 left-0 mt-2 bg-navy-light border border-[#C9A55A]/20 rounded-xl shadow-xl overflow-hidden z-50 max-h-80 overflow-y-auto">
                        <template x-for="r in results" :key="r.url">
                            <a :href="r.url" @click="open = false" class="flex items-center gap-3 px-4 py-3 text-sm text-white/70 hover:bg-white/5 hover:text-white transition border-b border-white/5 last:border-0">
                                <span class="w-6 h-6 rounded-lg flex items-center justify-center text-xs font-bold" :class="{'bg-blue-500/15 text-blue-400': r.type === 'case', 'bg-amber-500/15 text-amber-400': r.type === 'client'}">
                                    <span x-text="r.type === 'case' ? 'ق' : 'ع'"></span>
                                </span>
                                <span x-text="r.label"></span>
                            </a>
                        </template>
                    </div>
                    <div x-show="open && query.length > 1 && results.length === 0 && !searching" class="absolute top-full right-0 left-0 mt-2 bg-navy-light border border-[#C9A55A]/20 rounded-xl shadow-xl overflow-hidden z-50">
                        <div class="p-4 text-xs text-white/30 text-center">لا توجد نتائج</div>
                    </div>
                </div>

                {{-- Left Side --}}
                <div class="flex items-center gap-1">
                    <a href="{{ route('language.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}"
                        class="p-2 rounded-xl text-white/40 hover:text-[#C9A55A] transition" title="{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                    </a>

                    {{-- Theme Toggle --}}
                    <div x-data="{ theme: localStorage.getItem('theme') || 'dark' }" x-init="$watch('theme', v => { localStorage.setItem('theme', v); document.documentElement.classList.toggle('light-theme', v === 'light'); }); document.documentElement.classList.toggle('light-theme', theme === 'light');">
                        <button @click="theme = theme === 'dark' ? 'light' : 'dark'"
                            class="p-2 rounded-xl text-white/40 hover:text-[#C9A55A] transition" :title="theme === 'dark' ? '{{ __('app.light_theme') }}' : '{{ __('app.dark_theme') }}'">
                            <svg x-show="theme === 'dark'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <svg x-show="theme === 'light'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="display:none;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                        </button>
                    </div>

                    {{-- Notifications --}}
                    @php
                        $unreadCount = \App\Models\Notification::where('user_id', auth()->id())->where('is_read', false)->count();
                        $recentNotifications = \App\Models\Notification::where('user_id', auth()->id())->latest()->limit(10)->get();
                    @endphp
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="relative p-2 rounded-xl text-white/40 hover:text-white/80 transition">
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
                            <div class="p-4 flex items-center justify-between" style="border-bottom: 1px solid rgba(201,165,90,0.06);">
                                <h3 class="font-heading font-bold text-white">{{ __('app.notifications') }}</h3>
                                @if($unreadCount > 0)
                                    <form method="POST" action="{{ route('notifications.readAll') }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-[#C9A55A] hover:text-[#E0C878] transition">{{ __('app.mark_all_read') }}</button>
                                    </form>
                                @endif
                            </div>
                            <div class="max-h-80 overflow-y-auto">
                                @forelse($recentNotifications as $notification)
                                    <a href="{{ $notification->is_read ? '#' : route('notifications.read', $notification->id) }}" class="block px-4 py-3 transition hover:bg-white/[0.03]" style="border-bottom: 1px solid rgba(201,165,90,0.04);">
                                        <p class="text-sm text-white/70">{{ $notification->message }}</p>
                                        <p class="text-xs text-white/30 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                                    </a>
                                @empty
                                    <div class="p-8 text-center text-white/30 text-sm">
                                        <svg class="w-10 h-10 mx-auto mb-2 text-white/10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                        {{ __('app.no_notifications') }}
                                    </div>
                                @endforelse
                            </div>
                            <a href="{{ route('notifications.index') }}" class="block p-3 text-center text-sm text-[#C9A55A] font-medium transition hover:text-[#E0C878]" style="border-top: 1px solid rgba(201,165,90,0.06);">{{ __('app.view_all_notifications') }}</a>
                        </div>
                    </div>

                    {{-- User Menu --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-2 p-1.5 rounded-xl transition">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#C9A55A] to-[#A8903E] flex items-center justify-center shadow-lg" style="box-shadow: 0 4px 12px rgba(201, 165, 90, 0.3);">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                </svg>
                            </div>
                            <span class="hidden sm:block text-sm font-medium text-white/70">{{ Auth::user()->name }}</span>
                            <svg class="w-4 h-4 text-white/30 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
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
                            <div class="p-4" style="border-bottom: 1px solid rgba(201,165,90,0.06);">
                                <p class="font-heading font-bold text-white text-sm">{{ Auth::user()->name }}</p>
                                <p class="text-xs text-white/30 mt-0.5">{{ Auth::user()->email }}</p>
                                <span class="inline-block mt-1 text-[10px] px-2 py-0.5 rounded-full bg-[#C9A55A]/10 text-[#C9A55A] font-medium">{{ Auth::user()->role }}</span>
                            </div>
                            <div class="py-1">
                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-white/60 hover:text-white transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    {{ __('app.profile') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- Page Content --}}
        <main class="p-4 sm:p-6 lg:p-8 animate-fade-in">
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

            @yield('content')
        </main>

        {{-- Footer --}}
        <footer style="border-top: 1px solid rgba(201,165,90,0.06); background: rgba(10, 15, 30, 0.5);">
            <div class="px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <a href="{{ url('/portfolio') }}" target="_blank" class="text-sm font-heading font-bold hover:opacity-80 transition-opacity" style="color: #C9A55A;">LexPro</a>
                    <span class="text-xs text-white/20">&copy;</span>
                    <span class="text-sm text-white/40">{{ date('Y') }}</span>
                </div>
                <p class="text-xs text-white/30">{{ $officeName }} &mdash; {{ __('app.all_rights') }}</p>
            </div>
        </footer>
    </div>

    @auth
    <form id="autoLogoutForm" action="{{ route('logout') }}" method="POST" style="display:none;">
        @csrf
    </form>

    <div id="autoLogoutOverlay" style="display:none;" class="fixed inset-0 z-[9999] flex items-center justify-center" onclick="if(event.target===this)autoLogoutDismiss()">
        <div class="bg-[#141E33] border border-[#C9A55A]/10 rounded-2xl p-8 max-w-sm mx-4 text-center shadow-2xl card-premium">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-amber-500/10 flex items-center justify-center">
                <svg class="w-8 h-8 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
            </div>
            <h3 class="text-white font-bold text-lg mb-2" style="font-family: 'Cairo', sans-serif;">{{ __('app.session_warning_title') }}</h3>
            <p class="text-white/50 text-sm mb-4">{{ __('app.session_warning_message') }} <span class="text-amber-400 font-bold" id="autoLogoutCountdown">60</span></p>
            <div class="w-full bg-white/10 rounded-full h-2 mb-6">
                <div id="autoLogoutBar" class="bg-amber-400 h-2 rounded-full transition-all duration-1000" style="width: 100%"></div>
            </div>
            <div class="flex gap-3">
                <button onclick="autoLogoutDismiss()" class="flex-1 btn-gold py-3 rounded-xl font-bold text-sm">{{ __('app.continue') }}</button>
                <button onclick="document.getElementById('autoLogoutForm').submit()" class="flex-1 btn-ghost py-3 rounded-xl font-medium text-sm">{{ __('app.logout') }}</button>
            </div>
        </div>
    </div>
    @endauth

    <script>
    (function() {
        var timer = null;
        var countdownTimer = null;
        var countdownVal = 60;
        var TIMEOUT = 10 * 60 * 1000;
        var WARNING = 60;

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
                    document.getElementById('autoLogoutForm').submit();
                }
            }, 1000);
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
    <script>
    (function() {
        var POLL_INTERVAL = 30000;
        var lastUpdated = '{{ now()->toDateTimeString() }}';
        var currentUrl = window.location.pathname;
        var isFormPage = document.querySelector('form[method="POST"]') && !document.querySelector('.data-table');
        if (isFormPage) return;

        var indicator = document.createElement('div');
        indicator.id = 'syncIndicator';
        indicator.style.cssText = 'position:fixed;bottom:12px;left:12px;z-index:9999;display:none;padding:4px 10px;border-radius:8px;font-size:11px;color:#C9A55A;background:rgba(17,27,46,0.9);border:1px solid rgba(201,165,90,0.2);transition:opacity 0.3s;pointer-events:none;';
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


</body>
</html>
