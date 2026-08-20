<aside class="fixed top-0 right-0 h-full w-64 bg-white z-50 flex flex-col transition-all duration-300 ease-in-out">
    {{-- Logo --}}
    <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-gold to-gold-dark flex items-center justify-center flex-shrink-0 shadow-lg shadow-gold/30">
                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                </svg>
            </div>
            <span class="text-gold-dark font-heading font-bold text-lg whitespace-nowrap">مُداوَلة</span>
        </div>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-1">
        {{-- Main Section --}}
        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-2">الرئيسية</p>

        <a href="{{ route('dashboard') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span>لوحة التحكم</span>
        </a>

        <a href="{{ route('cases.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm {{ request()->routeIs('cases.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            <span>القضايا</span>
        </a>

        <a href="{{ route('sessions.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm {{ request()->routeIs('sessions.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>الجلسات</span>
        </a>

        <a href="{{ route('tasks.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm {{ request()->routeIs('tasks.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <span>المهام</span>
        </a>

        <a href="{{ route('documents.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm {{ request()->routeIs('documents.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>المستندات</span>
        </a>

        <a href="{{ route('clients.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm {{ request()->routeIs('clients.*') ? 'active' : '' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span>العملاء</span>
        </a>

        {{-- Admin Section --}}
        @php
            $adminRole = Auth::check() && (in_array(Auth::user()->role, ['admin', 'developer']) || Auth::user()->hasPermission('users.view') || Auth::user()->hasPermission('feasibility.view') || Auth::user()->hasPermission('audit_log.view') || Auth::user()->hasPermission('settings.manage') || Auth::user()->hasPermission('backup.manage'));
        @endphp
        @if($adminRole)
            <div class="pt-4 pb-2">
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-2">الإدارة</p>
            </div>

            @if(Auth::user()->hasPermission('users.view') || in_array(Auth::user()->role, ['admin', 'developer']))
            <a href="{{ route('users.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>المستخدمون</span>
            </a>
            @endif

            @if(Auth::user()->hasPermission('feasibility.view') || in_array(Auth::user()->role, ['admin', 'developer']))
            <a href="{{ route('feasibility.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm {{ request()->routeIs('feasibility.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>دراسة الجدوى</span>
            </a>
            @endif

            @if(Auth::user()->hasPermission('audit_log.view') || in_array(Auth::user()->role, ['admin', 'developer']))
            <a href="{{ route('audit-log.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm {{ request()->routeIs('audit-log.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                <span>سجل المراجعة</span>
            </a>
            @endif

            @if(Auth::user()->hasPermission('settings.manage') || in_array(Auth::user()->role, ['admin', 'developer']))
            <a href="{{ route('settings.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>الإعدادات</span>
            </a>
            @endif

            @if(Auth::user()->hasPermission('backup.manage') || in_array(Auth::user()->role, ['admin', 'developer']))
            <a href="{{ route('backup.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm {{ request()->routeIs('backup.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
                <span>النسخ الاحتياطي</span>
            </a>
            @endif
        @endif
    </nav>

    {{-- Sidebar Footer --}}
    <div class="border-t border-gray-200 p-3 space-y-1">
        <a href="{{ route('language.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
            </svg>
            <span>{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</span>
        </a>

        <a href="{{ route('profile.edit') }}" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span>الملف الشخصي</span>
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 text-sm w-full hover:text-red-700">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span>تسجيل الخروج</span>
            </button>
        </form>
    </div>
</aside>
