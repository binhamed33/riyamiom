@extends('layouts.app')

@section('title', __('app.page_dashboard'))

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

@php $isMgmt = auth()->user()->isAdmin() || auth()->user()->isDeveloper() || auth()->user()->hasPermission('users.view') || auth()->user()->hasPermission('feasibility.view') || auth()->user()->hasPermission('audit_log.view') || auth()->user()->hasPermission('settings.manage') || auth()->user()->hasPermission('backup.manage'); @endphp

{{-- نبض الأتمتة — للإدارة: ماذا فعلت مُداوَلة نيابة عنك اليوم؟ --}}
@if($isMgmt)
    @php
        try {
            $autoToday = \App\Models\AutomationRun::whereDate('created_at', today())->where('status', 'success')->count();
            $autoFailed = \App\Models\AutomationRun::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();
        } catch (\Throwable $e) {
            $autoToday = 0;
            $autoFailed = 0;
        }
    @endphp
    @if($autoToday > 0 || $autoFailed > 0)
    <a href="{{ route('automations.runs') }}"
       class="flex items-center gap-3 rounded-2xl border px-5 py-3 transition hover:shadow-sm {{ $autoFailed ? 'bg-red-50/60 border-red-200' : 'bg-white border-gold/20' }}">
        <span class="text-xl">⚙️</span>
        <p class="flex-1 text-sm text-gray-700">
            <b class="text-gold-dark">مُداوَلة</b> نفّذت <b>{{ $autoToday }}</b> إجراءً تلقائياً اليوم
            @if($autoFailed)
                — <b class="text-red-600">{{ $autoFailed }} إخفاق يحتاج مراجعتك</b>
            @endif
        </p>
        <span class="text-xs font-bold text-gold-dark whitespace-nowrap">التفاصيل ←</span>
    </a>
    @endif
@endif

{{-- Today's Brief --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-heading font-bold text-gray-900">{{ $greeting }}، {{ auth()->user()->name }}</h1>
            <p class="text-xs text-gray-400 mt-0.5">إليك ما يحتاج انتباهك اليوم — {{ now()->format('l d F Y') }}</p>
        </div>
        {{-- الحضور من هنا مباشرة: نقرة واحدة صباحاً وواحدة مساءً --}}
        @if (!auth()->user()->isClient() && \App\Models\Setting::get('feature_hr', '0') !== '1')
        <div class="flex items-center gap-2 flex-shrink-0">
            @if(!$attendanceToday)
                <form method="POST" action="{{ route('hr.attendance.checkin') }}">@csrf
                    <button class="md-touch-pad text-xs font-bold text-white bg-gold-dark rounded-xl px-3.5 py-2.5 hover:opacity-90 transition">تسجيل الحضور</button>
                </form>
            @elseif($attendanceToday->check_out_at === null)
                <form method="POST" action="{{ route('hr.attendance.checkout') }}">@csrf
                    <button class="md-touch-pad text-xs font-bold text-gray-700 bg-gray-100 border border-gray-200 rounded-xl px-3.5 py-2.5 hover:bg-gray-200 transition">تسجيل الانصراف</button>
                </form>
            @else
                <span class="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-3.5 py-2.5">يومك مكتمل ✓</span>
            @endif
            @if($myPendingLeaves > 0)
                <a href="{{ route('hr.index', ['tab' => 'leaves']) }}" class="text-xs font-bold text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-xl px-3.5 py-2.5 hover:bg-yellow-100 transition">إجازة معلّقة ({{ $myPendingLeaves }})</a>
            @endif
        </div>
        @endif
        <a href="{{ route('attention.index') }}" class="md-touch-pad flex-shrink-0 text-xs font-bold text-gold-dark hover:text-gold-dark transition inline-flex items-center gap-1 bg-gold/10 border border-gold/15 rounded-xl px-3.5 py-2.5">
            عرض كل شيء
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ app()->getLocale() === 'ar' ? 'M15 12h4m0 0l-2-2m2 2l-2 2M5 12h10m-6-4l4 4-4 4' : 'M9 12h4m0 0l-2-2m2 2l-2 2m10 0a7 7 0 11-14 0 7 7 0 0114 0z' }}"/></svg>
        </a>
    </div>
    <div x-data="{ briefOpen: true }">
        <template x-if="briefOpen">
            <div class="md-zebra">
                @forelse($brief as $item)
                    @php
                        $sev = [
                            1 => ['dot' => 'bg-red-500', 'text' => 'text-red-700', 'bg' => 'bg-red-50'],
                            2 => ['dot' => 'bg-orange-500', 'text' => 'text-orange-700', 'bg' => 'bg-orange-50'],
                            3 => ['dot' => 'bg-gold-dark', 'text' => 'text-gold-dark', 'bg' => 'bg-gold/10'],
                            4 => ['dot' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'bg' => 'bg-emerald-50'],
                        ][$item['sev']] ?? ['dot' => 'bg-gray-400', 'text' => 'text-gray-600', 'bg' => 'bg-gray-50'];
                    @endphp
                    <a href="{{ $item['url'] }}" class="flex items-center gap-3 px-5 py-2.5 hover:bg-gray-50/70 transition-colors group">
                        <span class="w-2.5 h-2.5 rounded-full {{ $sev['dot'] }} flex-shrink-0"></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 group-hover:text-gold-dark transition truncate">{{ $item['title'] }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ $item['sub'] }}</p>
                        </div>
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-gold-dark transition flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ app()->getLocale() === 'ar' ? 'm11 19-7-7 7-7m8 14-7-7 7-7' : 'm9 5 7 7-7 7m8-14-7 7 7 7' }}"/></svg>
                    </a>
                @empty
                    <div class="px-5 py-6 text-center">
                        <p class="text-sm text-gray-400">لا يوجد ما يحتاج انتباهك اليوم — كل شيء تحت السيطرة.</p>
                    </div>
                @endforelse
            </div>
        </template>
        <button x-on:click="briefOpen = !briefOpen" :aria-expanded="briefOpen ? 'true' : 'false'" aria-label="{{ __('app.a11y_toggle_brief') }}" class="md-touch-pad w-full justify-center gap-1 py-2.5 text-[11px] font-bold text-gray-400 hover:text-gold-dark transition">
            <span x-text="briefOpen ? 'إخفاء القائمة' : 'إظهار اليوم باختصار'"></span>
            <svg class="w-3 h-3" :class="briefOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
        </button>
    </div>
</div>

{{-- Attention Center --}}
@if($attentionItems->isNotEmpty())
    <div class="bg-white rounded-2xl border border-gold/15 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3" style="background: linear-gradient(90deg, rgba(212,175,55,0.12), rgba(212,175,55,0.03)); border-bottom: 1px solid rgba(212,175,55,0.18);">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-gold-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                </svg>
                <h2 class="font-heading font-bold text-gray-900 text-sm">مركز الانتباه</h2>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-500 text-white font-bold">{{ $attentionItems->count() }} تنبيه</span>
            </div>
            <a href="{{ route('attention.index') }}" class="text-xs font-bold text-gold-dark hover:text-gold-dark transition inline-flex items-center gap-1">
                عرض الكل
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ app()->getLocale() === 'ar' ? 'M15 12h4m0 0l-2-2m2 2l-2 2M5 12h10m-6-4l4 4-4 4' : 'M9 12h4m0 0l-2-2m2 2l-2 2m10 0a7 7 0 11-14 0 7 7 0 0114 0z' }}"/></svg>
            </a>
        </div>
        <div class="md-zebra">
            @php
                $sevMap = [
                    'critical' => ['border' => 'border-red-200', 'bg' => 'bg-red-50', 'dot' => 'bg-red-500', 'text' => 'text-red-700'],
                    'warning'  => ['border' => 'border-orange-200', 'bg' => 'bg-orange-50', 'dot' => 'bg-orange-500', 'text' => 'text-orange-700'],
                    'info'     => ['border' => 'border-gold/15', 'bg' => 'bg-gold/10', 'dot' => 'bg-gold-dark', 'text' => 'text-gold-dark'],
                ];
            @endphp
            @foreach($attentionItems as $item)
                @php $s = $sevMap[$item['severity']] ?? $sevMap['info']; @endphp
                <div class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50/60 transition-colors">
                    <span class="w-2.5 h-2.5 rounded-full {{ $s['dot'] }} flex-shrink-0"></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium {{ $s['text'] }} truncate">{{ $item['title'] }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ $item['description'] }}</p>
                    </div>
                    @if(!empty($item['action']))
                        <a href="{{ $item['action']['url'] }}" class="flex-shrink-0 text-xs font-bold text-gold-dark hover:text-gold-dark transition">{{ $item['action']['label'] }}</a>
                    @elseif(!empty($item['url']))
                        <a href="{{ $item['url'] }}" class="flex-shrink-0 text-xs font-bold text-gray-400 hover:text-gold-dark transition">{{ __('app.open') }}</a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

@if($isMgmt)
    {{-- Row 1: بطاقات KPI — عنوان، رقم بطل، مؤشر نسبة، وسطر إضافي لكلٍّ --}}
    @php
        // مؤشر التغير الشهري: يُحسب فقط حين يوجد شهر سابق يُقارن به
        $delta = function (int $now, int $prev): ?int {
            return $prev > 0 ? (int) round((($now - $prev) / $prev) * 100) : null;
        };
        $casesDelta = $delta($newCasesThisMonth, $newCasesLastMonth);
        $clientsDelta = $delta($newClientsThisMonth, $newClientsLastMonth);
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <x-kpi-card :title="__('app.total_cases')" :value="$totalCases" accent="gold"
            :delta="$casesDelta" :sub="'+' . $newCasesThisMonth . ' ' . __('app.new_this_month')">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
        </x-kpi-card>

        <x-kpi-card :title="__('app.active_cases')" :value="$activeCases" accent="green"
            :sub="($totalCases > 0 ? round(($activeCases / $totalCases) * 100) : 0) . '٪ ' . __('app.of_total')">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
        </x-kpi-card>

        <x-kpi-card :title="__('app.win_rate')" :value="$winRate . '٪'" accent="blue"
            :sub="$wonCases . ' ' . __('app.won_cases') . ' / ' . $lostCases . ' ' . __('app.lost_cases')">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </x-kpi-card>

        <x-kpi-card :title="__('app.task_completion')" :value="$tasksCompletionRate . '٪'" accent="purple"
            :sub="$completedThisWeek . ' ' . __('app.tasks_completed_week')">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
        </x-kpi-card>

        <x-kpi-card :title="__('app.overdue')" :value="$overdueCases + $overdueTasks" accent="red"
            :sub="$overdueCases . ' ' . __('app.cases') . ' · ' . $overdueTasks . ' ' . __('app.tasks')">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </x-kpi-card>

        <x-kpi-card :title="__('app.clients')" :value="$totalClients" accent="gold"
            :delta="$clientsDelta" :sub="'+' . $newClientsThisMonth . ' ' . __('app.new_this_month') . ' · ' . $newDocumentsThisMonth . ' ' . __('app.document')">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </x-kpi-card>
    </div>

    {{-- Row 2: Monthly Comparison + Today's Sessions --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gold/15 p-5">
            <p class="text-gray-400 text-xs mb-2">{{ __('app.new_this_month') }}</p>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-bold text-gold-dark">{{ $newCasesThisMonth }}</p>
                <p class="text-xs mb-1">
                    @if($newCasesLastMonth > 0)
                        @php $change = round((($newCasesThisMonth - $newCasesLastMonth) / $newCasesLastMonth) * 100); @endphp
                        @if($change >= 0)
                            <span class="text-green-700">+{{ $change }}%</span>
                        @else
                            <span class="text-red-700">{{ $change }}%</span>
                        @endif
                    @endif
                    <span class="text-gray-400">{{ __('app.from_last_month') }}</span>
                </p>
            </div>
            <div class="mt-2 flex items-center gap-3 text-xs text-gray-400">
                <span>{{ $newClientsThisMonth }} {{ __('app.new_client') }}</span>
                <span>{{ $totalDocuments }} {{ __('app.document') }}</span>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gold/15 p-5">
            <p class="text-gray-400 text-xs mb-2">{{ __('app.today_sessions') }}</p>
            @if($todaySessions->count() > 0)
                <p class="text-3xl font-bold text-green-700">{{ $todaySessions->count() }}</p>
                <div class="mt-2 space-y-1">
                    @foreach($todaySessions->take(2) as $s)
                        <p class="text-xs text-gray-500 truncate">{{ $s->case->title ?? '' }} — {{ $s->location ?? '' }}</p>
                    @endforeach
                </div>
            @else
                <p class="text-3xl font-bold text-gray-300">0</p>
                <p class="text-xs text-gray-400 mt-2">{{ __('app.no_sessions_today') }}</p>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gold/15 p-5">
            <p class="text-gray-400 text-xs mb-2">{{ __('app.tasks_completed_week') }}</p>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-bold text-purple-700">{{ $completedThisWeek }}</p>
                <p class="text-xs text-gray-400 mb-1">{{ __('app.out_of') }} {{ $totalTasks }} {{ __('app.total') }}</p>
            </div>
            <div class="mt-3 w-full bg-gray-100 rounded-full h-2">
                <div class="bg-purple-500 h-2 rounded-full transition-all" style="width: {{ $tasksCompletionRate }}%"></div>
            </div>
        </div>
    </div>

    {{-- Row 3: Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark mb-4">{{ __('app.cases_by_status') }}</h2>
            <div class="flex justify-center" style="height: 240px;">
                <canvas id="casesStatusChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark mb-4">{{ __('app.monthly_trend') }}</h2>
            <div style="height: 240px;">
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark mb-4">{{ __('app.cases_by_lawyer') }}</h2>
            <div style="height: 240px;">
                <canvas id="casesByLawyerChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Row 4: Priority Distribution + Cases Summary --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark mb-4">{{ __('app.cases_by_priority') }}</h2>
            <div class="space-y-4">
                @php
                    // ألوان الحالة لا ألوان سلاسل — والنص بحبر النص دائماً
                    $priorityColors = ['urgent' => 'bg-red-500', 'high' => 'bg-orange-400', 'medium' => 'bg-amber-400', 'low' => 'bg-gray-300'];
                    $priorityLabels = ['urgent' => __('app.priority_urgent'), 'high' => __('app.priority_high'), 'medium' => __('app.priority_medium'), 'low' => __('app.priority_low')];
                @endphp
                @foreach(['urgent', 'high', 'medium', 'low'] as $p)
                    @php
                        $count = $casesByPriority[$p] ?? 0;
                        $pct = $totalCases > 0 ? round(($count / $totalCases) * 100) : 0;
                    @endphp
                    <div>
                        <div class="flex items-baseline justify-between mb-1.5">
                            <span class="text-xs font-medium text-gray-600 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full {{ $priorityColors[$p] }} inline-block"></span>
                                {{ $priorityLabels[$p] ?? $p }}
                            </span>
                            <span class="text-xs text-gray-500" style="font-variant-numeric: tabular-nums">
                                {{ $count }} <span class="text-gray-400">({{ $pct }}٪)</span>
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                            <div class="{{ $priorityColors[$p] }} h-2 rounded-full transition-all duration-500 {{ $count > 0 ? 'min-w-[8px]' : '' }}" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
                <p class="text-[11px] text-gray-400 pt-1">{{ __('app.total') }}: {{ $totalCases }} {{ __('app.cases') }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark mb-4">{{ __('app.office_overview') }}</h2>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ $totalLawyers }}</p>
                    <p class="text-xs text-gray-400">{{ __('app.lawyers') }}</p>
                </div>
                <div class="bg-white rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ $totalSessions }}</p>
                    <p class="text-xs text-gray-400">{{ __('app.total_sessions') }}</p>
                </div>
                <div class="bg-white rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-green-700">{{ $wonCases }}</p>
                    <p class="text-xs text-gray-400">{{ __('app.won') }}</p>
                </div>
                <div class="bg-white rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-red-700">{{ $lostCases }}</p>
                    <p class="text-xs text-gray-400">{{ __('app.lost') }}</p>
                </div>
            </div>
        </div>
    </div>
@else
    {{-- Lawyer/Staff/Client: Personal Dashboard --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-white rounded-xl border border-gold/15 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gold/12 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-gold-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('app.total_cases') }}</p>
                    <p class="text-xl font-bold text-gray-900">{{ $totalCases }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-purple-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('app.pending_tasks') }}</p>
                    <p class="text-xl font-bold text-purple-700">{{ $pendingTasks + $inProgressTasks }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-green-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">{{ __('app.upcoming_sessions') }}</p>
                    <p class="text-xl font-bold text-green-700">{{ $upcomingSessions->count() }}</p>
                </div>
            </div>
        </div>
    </div>
@endif

    {{-- Row 5: Overdue Tasks + Upcoming Sessions + Pending Tasks --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Overdue Tasks --}}
        <div class="bg-white rounded-xl border border-red-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-red-700 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('app.overdue_tasks') }}
                </h2>
                @if($overdueTasks > 0)
                    <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded-full font-bold">{{ $overdueTasks }}</span>
                @endif
            </div>
            <div class="space-y-2">
                @forelse($overdueTasksList as $task)
                    <div class="flex items-center gap-3 p-2.5 rounded-lg bg-red-100 border border-red-200">
                        <div class="w-2 h-2 rounded-full bg-red-700 flex-shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-gray-900 text-xs font-medium truncate">{{ $task->title }}</p>
                            <p class="text-red-700/60 text-[11px]">{{ $task->assignee?->name ?? '' }} — {{ $task->due_date?->format('Y/m/d') ?? '' }}</p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-6 text-green-700/50">
                        <svg class="w-8 h-8 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs">{{ __('app.no_overdue_tasks') }}</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Upcoming Sessions --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-gold-dark">{{ __('app.upcoming_sessions') }}</h2>
                <a href="{{ route('sessions.index') }}" class="text-xs text-gray-600 hover:text-gold-dark">{{ __('app.view_all') }}</a>
            </div>
            <div class="space-y-2">
                @forelse($upcomingSessions as $session)
                    <div class="flex items-center gap-3 p-2.5 rounded-lg bg-gray-100 border border-gray-100 hover:border-gold/25 transition-colors">
                        <div class="w-10 h-10 rounded-lg bg-gold/12 flex items-center justify-center flex-shrink-0">
                            <div class="text-center leading-none">
                                <p class="text-[10px] text-gray-600">{{ $session->date?->format('M') ?? '' }}</p>
                                <p class="text-sm font-bold text-gold-dark">{{ $session->date?->format('d') ?? '' }}</p>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-gray-900 text-xs font-medium truncate">{{ $session->case->title ?? '' }}</p>
                            <p class="text-gray-400 text-[11px]">{{ $session->location ?? '' }} — {{ $session->type ?? '' }}</p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-6 text-gray-400">
                        <p class="text-xs">{{ __('app.no_upcoming_sessions') }}</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Pending Tasks --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-gold-dark">{{ __('app.pending_tasks') }}</h2>
                <a href="{{ route('tasks.index') }}" class="text-xs text-gray-600 hover:text-gold-dark">{{ __('app.view_all') }}</a>
            </div>
            <div class="space-y-2">
                @forelse($pendingTasksList->take(5) as $task)
                    <div class="flex items-center gap-3 p-2.5 rounded-lg bg-gray-100 border border-gray-100 hover:border-gold/25 transition-colors">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0
                            @if(($task->priority ?? '') === 'urgent') bg-red-100
                            @elseif(($task->priority ?? '') === 'high') bg-orange-500/15
                            @elseif(($task->priority ?? '') === 'medium') bg-yellow-500/15
                            @else bg-gray-500/15 @endif">
                            @if(($task->priority ?? '') === 'urgent')
                                <svg class="w-4 h-4 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            @else
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-gray-900 text-xs font-medium truncate">{{ $task->title }}</p>
                            <p class="text-gray-400 text-[11px]">{{ $task->assignee?->name ?? '' }} — {{ $task->due_date?->format('Y/m/d') ?? __('app.no_due_date') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-6 text-gray-400">
                        <svg class="w-8 h-8 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs">{{ __('app.no_pending_tasks') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Row 6: Recent Activity --}}
    <div class="bg-white rounded-xl border border-gold/15 p-6">
        <h2 class="text-sm font-bold text-gold-dark mb-4">{{ __('app.recent_activity') }}</h2>
        <div class="space-y-2">
            @forelse($recentActivity as $item)
                @php
                    $typeConfig = [
                        'case' => ['color' => 'bg-blue-100 text-blue-700', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
                        'task' => ['color' => 'bg-purple-100 text-purple-700', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                        'client' => ['color' => 'bg-gold/12 text-gold-dark', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                        'document' => ['color' => 'bg-green-100 text-green-700', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        'log' => ['color' => 'bg-gray-100 text-gray-500', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ];
                    $config = $typeConfig[$item['icon']] ?? $typeConfig['log'];
                @endphp
                @if($item['url'])
                    <a href="{{ $item['url'] }}" class="flex items-center gap-3 p-3 rounded-lg bg-white border border-gray-100 hover:border-gold/25 transition-colors group">
                @else
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-white border border-gray-100">
                @endif
                    <div class="w-9 h-9 rounded-lg {{ $config['color'] }} flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $config['icon'] }}"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-gray-900 text-xs font-medium truncate group-hover:text-gold-dark transition-colors">{{ $item['title'] }}</p>
                        <p class="text-gray-500 text-[11px] truncate">{{ $item['subtitle'] }}</p>
                    </div>
                    <span class="text-gray-400 text-[11px] flex-shrink-0" dir="ltr">{{ $item['time']?->diffForHumans() ?? '' }}</span>
                @if($item['url'])
                    </a>
                @else
                    </div>
                @endif
            @empty
                <div class="text-center py-6 text-gray-400">
                    <p class="text-xs">{{ __('app.no_recent_activity') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

@if($isMgmt)
@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function () {
    // الألوان تُقرأ من الرموز وقت البناء لا كنصّ 'var(--accent)':
    // الـcanvas لا يفهم متغيّرات CSS، فكانت كل الأعمدة تُرسم سوداء.
    var charts = [];

    function build() {
        charts.forEach(function (c) { c.destroy(); });
        charts = [];

        // === القضايا حسب الحالة (حلقي) ===
        var statusCtx = document.getElementById('casesStatusChart');
        if (statusCtx) {
            var chartData = @json($casesByStatus);
            var labels = {
                active: '{{ __("app.status_active") }}',
                pending: '{{ __("app.status_pending") }}',
                overdue: '{{ __("app.status_overdue") }}',
                closed: '{{ __("app.status_closed") }}',
                won: '{{ __("app.status_won") }}',
                lost: '{{ __("app.status_lost") }}',
                adjudicated: '{{ __("app.status_adjudicated") }}',
                fees_pending: '{{ __("app.status_fees_pending") }}',
            };
            // حالة لا فئة: ألوان الحالة محجوزة، والاسم مكتوب في المفتاح
            // فلا يُعرَف الحال باللون وحده
            var tone = {
                active: 'good', adjudicated: 'good', won: 'good',
                pending: 'warn', fees_pending: 'warn',
                overdue: 'bad', lost: 'bad',
                closed: 'idle',
            };
            var keys = Object.keys(chartData).filter(function (k) { return chartData[k] > 0; });

            if (keys.length) {
                charts.push(new Chart(statusCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: keys.map(function (k) { return labels[k] || k; }),
                        datasets: [{
                            data: keys.map(function (k) { return chartData[k]; }),
                            backgroundColor: keys.map(function (k) { return MdChart.status(tone[k] || 'idle'); }),
                            // فاصل بلون السطح بين الشرائح — يفصلها بلا خطّ ثقيل
                            borderColor: MdChart.surface(),
                            borderWidth: 2,
                            hoverOffset: 6,
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '66%',
                        plugins: { legend: MdChart.legend(), tooltip: MdChart.tooltip() }
                    }
                }));
            }
        }

        // === الاتجاه الشهري (أعمدة) ===
        var trendCtx = document.getElementById('monthlyTrendChart');
        if (trendCtx) {
            var trendData = @json($monthlyTrend);
            charts.push(new Chart(trendCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: trendData.map(function (d) { return d.label; }),
                    datasets: [
                        {
                            label: '{{ __("app.new_cases") }}',
                            data: trendData.map(function (d) { return d.new; }),
                            backgroundColor: MdChart.series(0),
                            borderRadius: 4,
                            borderSkipped: false,
                        },
                        {
                            label: '{{ __("app.closed") }}',
                            data: trendData.map(function (d) { return d.closed; }),
                            backgroundColor: MdChart.series(1),
                            borderRadius: 4,
                            borderSkipped: false,
                        }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        x: MdChart.scale({ grid: { display: false } }),
                        y: MdChart.scale({ beginAtZero: true, ticks: { color: MdChart.inkMuted(), precision: 0, font: { size: 11 } } })
                    },
                    plugins: { legend: MdChart.legend(), tooltip: MdChart.tooltip() }
                }
            }));
        }

        // === القضايا حسب المحامي (أعمدة أفقية) ===
        var lawyerCtx = document.getElementById('casesByLawyerChart');
        if (lawyerCtx) {
            var lawyerData = @json($casesByLawyer);
            // سلسلة واحدة = مقدار لا هويّة: لون واحد بلون سمة المكتب،
            // ولا مفتاح ألوان لأن العنوان يسمّيها
            charts.push(new Chart(lawyerCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: Object.keys(lawyerData),
                    datasets: [{
                        data: Object.values(lawyerData),
                        backgroundColor: MdChart.accent(0.85),
                        hoverBackgroundColor: MdChart.accent(1),
                        borderRadius: 4,
                        borderSkipped: false,
                        barThickness: 18,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        x: MdChart.scale({ beginAtZero: true, ticks: { color: MdChart.inkMuted(), precision: 0, font: { size: 11 } } }),
                        y: MdChart.scale({ grid: { display: false } })
                    },
                    plugins: { legend: { display: false }, tooltip: MdChart.tooltip() }
                }
            }));
        }
    }

    build();
    MdChart.onThemeChange(build);   // تبديل الوضع يعيد الرسم بألوان الوضع الجديد
});
</script>
@endpush
@endif

@endsection
