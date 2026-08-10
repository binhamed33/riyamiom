@extends('layouts.app')

@section('title', __('app.page_dashboard'))

@section('content')
<div class="space-y-5" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

@php $isMgmt = auth()->user()->isAdmin() || auth()->user()->isDeveloper() || auth()->user()->hasPermission('users.view') || auth()->user()->hasPermission('feasibility.view') || auth()->user()->hasPermission('audit_log.view') || auth()->user()->hasPermission('settings.manage') || auth()->user()->hasPermission('backup.manage'); @endphp

{{-- ===== L1: ما يحتاج انتباهك الآن ===== --}}
<div class="card overflow-hidden">
    <div class="card-header">
        <div>
            <h1 class="page-title text-lg sm:text-xl">{{ $greeting }}، {{ auth()->user()->name }}</h1>
            <p class="page-subtitle">{{ __('app.today_brief_subtitle', ['date' => now()->format('l d F Y')]) }}
                @if(!empty($brief) && $brief->isNotEmpty())
                    — <span class="text-amber-700 font-bold">{{ $brief->count() }} {{ __('app.items') }}</span>
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if(!auth()->user()->isClient())
            <a href="{{ route('cases.create') }}" class="btn btn-primary btn-sm">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                {{ __('app.add_new_case') }}
            </a>
            @endif
            <a href="{{ route('attention.index') }}" class="btn btn-secondary btn-sm">
                {{ __('app.view_all') }}
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ app()->getLocale() === 'ar' ? 'M15 12h4m0 0l-2-2m2 2l-2 2M5 12h10m-6-4l4 4-4 4' : 'M9 12h4m0 0l-2-2m2 2l-2 2m10 0a7 7 0 11-14 0 7 7 0 0114 0z' }}"/></svg>
            </a>
        </div>
    </div>
    <div x-data="{ briefOpen: true }">
        <template x-if="briefOpen">
            <div class="divide-y divide-gray-50">
                @forelse($brief as $item)
                    @php
                        $sev = [
                            1 => ['dot' => 'bg-red-500', 'text' => 'text-red-700', 'bg' => 'bg-red-50'],
                            2 => ['dot' => 'bg-orange-500', 'text' => 'text-orange-700', 'bg' => 'bg-orange-50'],
                            3 => ['dot' => 'bg-amber-500', 'text' => 'text-amber-700', 'bg' => 'bg-amber-50'],
                            4 => ['dot' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'bg' => 'bg-emerald-50'],
                        ][$item['sev']] ?? ['dot' => 'bg-gray-400', 'text' => 'text-gray-600', 'bg' => 'bg-gray-50'];
                    @endphp
                    <a href="{{ $item['url'] }}" class="flex items-center gap-3 px-5 py-2.5 hover:bg-gray-50/70 transition-colors group">
                        <span class="w-2.5 h-2.5 rounded-full {{ $sev['dot'] }} flex-shrink-0"></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 group-hover:text-amber-700 transition truncate">{{ $item['title'] }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ $item['sub'] }}</p>
                        </div>
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-amber-600 transition flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ app()->getLocale() === 'ar' ? 'm11 19-7-7 7-7m8 14-7-7 7-7' : 'm9 5 7 7-7 7m8-14-7 7 7 7' }}"/></svg>
                    </a>
                @empty
                    @forelse($attentionItems as $item)
                        @php
                            $sMap = [
                                'critical' => ['dot' => 'bg-red-500', 'text' => 'text-red-700'],
                                'warning'  => ['dot' => 'bg-orange-500', 'text' => 'text-orange-700'],
                                'info'     => ['dot' => 'bg-amber-500', 'text' => 'text-amber-700'],
                            ];
                            $s = $sMap[$item['severity'] ?? 'info'] ?? $sMap['info'];
                        @endphp
                        <a href="{{ !empty($item['action']) ? $item['action']['url'] : ($item['url'] ?? route('attention.index')) }}" class="flex items-center gap-3 px-5 py-2.5 hover:bg-gray-50/70 transition-colors group">
                            <span class="w-2.5 h-2.5 rounded-full {{ $s['dot'] }} flex-shrink-0"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium {{ $s['text'] }} truncate">{{ $item['title'] }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ $item['description'] ?? '' }}</p>
                            </div>
                        </a>
                    @empty
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <p class="empty-title">{{ __('app.all_under_control') }}</p>
                            <p class="empty-sub">{{ __('app.no_attention_today') }}</p>
                        </div>
                    @endforelse
                @endforelse
            </div>
        </template>
        <button x-on:click="briefOpen = !briefOpen" class="w-full flex items-center justify-center gap-1 py-1.5 text-[10px] font-bold text-gray-400 hover:text-amber-600 transition">
            <span x-text="briefOpen ? '{{ __('app.hide') }}' : '{{ __('app.show_brief') }}'"></span>
            <svg class="w-3 h-3" :class="briefOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
        </button>
    </div>
</div>

@if(!auth()->user()->isClient())
{{-- ===== L2: آخر القضايا ===== --}}
<div class="card">
    <div class="card-header">
        <h2 class="section-title">{{ __('app.recent_cases') }}</h2>
        <a href="{{ route('cases.index') }}" class="btn btn-ghost-btn btn-sm">{{ __('app.view_all') }} ←</a>
    </div>
    @if($recentCases->isNotEmpty())
        <div class="divide-y divide-gray-50">
            @foreach($recentCases as $case)
                <a href="{{ route('cases.show', $case) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50/70 transition-colors group">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4.5 h-4.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 group-hover:text-amber-700 transition truncate">
                            {{ $case->case_number }}
                            @if($case->title && $case->title !== $case->case_number)
                                <span class="text-gray-400 font-normal">— {{ $case->title }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-400 truncate">{{ $case->client->name ?? '—' }} · {{ $case->court ?? '' }}</p>
                    </div>
                    <span class="chip {{ $case->status === 'active' ? 'bg-green-100 text-green-700' : ($case->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : ($case->status === 'overdue' ? 'bg-red-100 text-red-700' : ($case->status === 'won' ? 'bg-blue-100 text-blue-700' : ($case->status === 'closed' ? 'bg-gray-100 text-gray-500' : 'bg-gray-100 text-gray-500')))) }} flex-shrink-0">{{ __('app.status_' . $case->status) }}</span>
                </a>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <div class="empty-icon">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <p class="empty-title">{{ __('app.no_cases') }}</p>
            <a href="{{ route('cases.create') }}" class="btn btn-primary btn-sm mt-4">{{ __('app.add_new_case') }}</a>
        </div>
    @endif
</div>

{{-- ===== L3: اليوم — جلسات ومهام ===== --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="card lg:col-span-1">
        <div class="card-header">
            <h2 class="section-title">{{ __('app.upcoming_sessions') }}</h2>
            <a href="{{ route('sessions.index') }}" class="btn btn-ghost-btn btn-sm">{{ __('app.view_all') }}</a>
        </div>
        <div class="card-body space-y-2">
            @forelse($upcomingSessions->take(5) as $session)
                <a href="{{ route('sessions.show', $session) }}" class="flex items-center gap-3 p-2.5 rounded-xl bg-gray-50 border border-gray-100 hover:border-amber-300 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0">
                        <div class="text-center leading-none">
                            <p class="text-[10px] text-gray-600">{{ $session->date?->format('M') ?? '' }}</p>
                            <p class="text-sm font-bold text-amber-700">{{ $session->date?->format('d') ?? '' }}</p>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-gray-900 text-xs font-medium truncate">{{ $session->case->title ?? '' }}</p>
                        <p class="text-gray-400 text-[11px] truncate">{{ $session->location ?? '' }} — {{ $session->type ?? '' }}</p>
                    </div>
                </a>
            @empty
                <div class="empty-state !py-8">
                    <div class="empty-icon !w-10 !h-10">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <p class="empty-title">{{ __('app.no_upcoming_sessions') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="card lg:col-span-1">
        <div class="card-header">
            <h2 class="section-title text-red-700">{{ __('app.overdue_tasks') }}</h2>
            @if($overdueTasks > 0)
                <span class="chip bg-red-100 text-red-700">{{ $overdueTasks }}</span>
            @endif
        </div>
        <div class="card-body space-y-2">
            @forelse($overdueTasksList as $task)
                <a href="{{ route('tasks.show', $task) }}" class="flex items-center gap-3 p-2.5 rounded-xl bg-red-50 border border-red-100 hover:border-red-300 transition-colors">
                    <div class="w-2 h-2 rounded-full bg-red-700 flex-shrink-0"></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-gray-900 text-xs font-medium truncate">{{ $task->title }}</p>
                        <p class="text-red-700/60 text-[11px]">{{ $task->assignee?->name ?? '' }} — {{ $task->due_date?->format('Y/m/d') ?? '' }}</p>
                    </div>
                </a>
            @empty
                <div class="empty-state !py-8">
                    <div class="empty-icon !w-10 !h-10">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="empty-title">{{ __('app.no_overdue_tasks') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="card lg:col-span-1">
        <div class="card-header">
            <h2 class="section-title">{{ __('app.pending_tasks') }}</h2>
            <a href="{{ route('tasks.index') }}" class="btn btn-ghost-btn btn-sm">{{ __('app.view_all') }}</a>
        </div>
        <div class="card-body space-y-2">
            @forelse($pendingTasksList->take(5) as $task)
                <a href="{{ route('tasks.show', $task) }}" class="flex items-center gap-3 p-2.5 rounded-xl bg-gray-50 border border-gray-100 hover:border-amber-300 transition-colors">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0
                        @if(($task->priority ?? '') === 'urgent') bg-red-100
                        @elseif(($task->priority ?? '') === 'high') bg-orange-500/15
                        @elseif(($task->priority ?? '') === 'medium') bg-yellow-500/15
                        @else bg-gray-500/15 @endif">
                        <svg class="w-4 h-4 {{ ($task->priority ?? '') === 'urgent' ? 'text-red-700' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-gray-900 text-xs font-medium truncate">{{ $task->title }}</p>
                        <p class="text-gray-400 text-[11px] truncate">{{ $task->assignee?->name ?? '' }} — {{ $task->due_date?->format('Y/m/d') ?? __('app.no_due_date') }}</p>
                    </div>
                </a>
            @empty
                <div class="empty-state !py-8">
                    <div class="empty-icon !w-10 !h-10">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="empty-title">{{ __('app.no_pending_tasks') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@else
    {{-- لوحة العميل الشخصية --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="card p-4">
            <p class="muted text-xs">{{ __('app.total_cases') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totalCases }}</p>
        </div>
        <div class="card p-4">
            <p class="muted text-xs">{{ __('app.pending_tasks') }}</p>
            <p class="text-2xl font-bold text-purple-700 mt-1">{{ $pendingTasks + $inProgressTasks }}</p>
        </div>
        <div class="card p-4">
            <p class="muted text-xs">{{ __('app.upcoming_sessions') }}</p>
            <p class="text-2xl font-bold text-green-700 mt-1">{{ $upcomingSessions->count() }}</p>
        </div>
    </div>
@endif

@if($isMgmt)
{{-- ===== L4: مؤشرات المكتب ===== --}}
<div class="card">
    <div class="card-header">
        <h2 class="section-title">{{ __('app.office_overview') }}</h2>
    </div>
    <div class="card-body grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 hover:border-amber-200 transition-colors">
            <p class="muted text-xs">{{ __('app.total_cases') }}</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ $totalCases }}</p>
        </div>
        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 hover:border-amber-200 transition-colors">
            <p class="muted text-xs">{{ __('app.active_cases') }}</p>
            <p class="text-xl font-bold text-green-700 mt-1">{{ $activeCases }}</p>
        </div>
        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 hover:border-amber-200 transition-colors">
            <p class="muted text-xs">{{ __('app.win_rate') }}</p>
            <p class="text-xl font-bold text-blue-700 mt-1">{{ $winRate }}%</p>
        </div>
        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 hover:border-amber-200 transition-colors">
            <p class="muted text-xs">{{ __('app.task_completion') }}</p>
            <p class="text-xl font-bold text-purple-700 mt-1">{{ $tasksCompletionRate }}%</p>
        </div>
        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 hover:border-amber-200 transition-colors">
            <p class="muted text-xs">{{ __('app.overdue') }}</p>
            <p class="text-xl font-bold text-red-700 mt-1">{{ $overdueCases + $overdueTasks }}</p>
        </div>
        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 hover:border-amber-200 transition-colors">
            <p class="muted text-xs">{{ __('app.clients') }}</p>
            <p class="text-xl font-bold text-amber-700 mt-1">{{ $totalClients }}</p>
        </div>
    </div>
</div>

{{-- L5: تبويبات المؤشرات المالية + التقدّم --}}
<div class="card">
    <div class="card-body grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <p class="muted text-xs">{{ __('app.new_this_month') }}</p>
            <div class="flex items-end gap-2 mt-1">
                <p class="text-3xl font-bold text-amber-700">{{ $newCasesThisMonth }}</p>
                <p class="text-xs mb-1">
                    @if($newCasesLastMonth > 0)
                        @php $change = round((($newCasesThisMonth - $newCasesLastMonth) / $newCasesLastMonth) * 100); @endphp
                        <span class="{{ $change >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ $change >= 0 ? '+' : '' }}{{ $change }}%</span>
                    @endif
                    <span class="text-gray-400">{{ __('app.from_last_month') }}</span>
                </p>
            </div>
            <div class="mt-2 flex items-center gap-3 text-xs text-gray-400">
                <span>{{ $newClientsThisMonth }} {{ __('app.new_client') }}</span>
                <span>{{ $totalDocuments }} {{ __('app.document') }}</span>
            </div>
        </div>
        <div class="sm:border-r sm:border-gray-100 sm:pr-4">
            <p class="muted text-xs">{{ __('app.today_sessions') }}</p>
            @if($todaySessions->count() > 0)
                <p class="text-3xl font-bold text-green-700 mt-1">{{ $todaySessions->count() }}</p>
                <div class="mt-2 space-y-1">
                    @foreach($todaySessions->take(2) as $s)
                        <p class="text-xs text-gray-500 truncate">{{ $s->case->title ?? '' }} — {{ $s->location ?? '' }}</p>
                    @endforeach
                </div>
            @else
                <p class="text-3xl font-bold text-gray-300 mt-1">0</p>
                <p class="text-xs text-gray-400 mt-2">{{ __('app.no_sessions_today') }}</p>
            @endif
        </div>
        <div class="sm:border-r sm:border-gray-100 sm:pr-4">
            <p class="muted text-xs">{{ __('app.tasks_completed_week') }}</p>
            <div class="flex items-end gap-2 mt-1">
                <p class="text-3xl font-bold text-purple-700">{{ $completedThisWeek }}</p>
                <p class="text-xs text-gray-400 mb-1">{{ __('app.out_of') }} {{ $totalTasks }} {{ __('app.total') }}</p>
            </div>
            <div class="mt-3 w-full bg-gray-100 rounded-full h-2">
                <div class="bg-purple-500 h-2 rounded-full transition-all" style="width: {{ $tasksCompletionRate }}%"></div>
            </div>
        </div>
    </div>
</div>

{{-- L6: الرسوم البيانية --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="card p-5">
        <h2 class="text-sm font-bold text-gray-900 mb-4">{{ __('app.cases_by_status') }}</h2>
        <div class="flex justify-center" style="height: 240px;">
            <canvas id="casesStatusChart"></canvas>
        </div>
    </div>
    <div class="card p-5">
        <h2 class="text-sm font-bold text-gray-900 mb-4">{{ __('app.monthly_trend') }}</h2>
        <div style="height: 240px;">
            <canvas id="monthlyTrendChart"></canvas>
        </div>
    </div>
    <div class="card p-5">
        <h2 class="text-sm font-bold text-gray-900 mb-4">{{ __('app.cases_by_lawyer') }}</h2>
        <div style="height: 240px;">
            <canvas id="casesByLawyerChart"></canvas>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="card p-5">
        <h2 class="text-sm font-bold text-gray-900 mb-4">{{ __('app.cases_by_priority') }}</h2>
        <div class="space-y-3">
            @php
                $priorityColors = ['urgent' => 'bg-red-500', 'high' => 'bg-orange-500', 'medium' => 'bg-yellow-500', 'low' => 'bg-gray-500'];
                $priorityText = ['urgent' => 'text-red-700', 'high' => 'text-orange-400', 'medium' => 'text-yellow-400', 'low' => 'text-gray-400'];
                $priorityLabels = ['urgent' => __('app.priority_urgent'), 'high' => __('app.priority_high'), 'medium' => __('app.priority_medium'), 'low' => __('app.priority_low')];
            @endphp
            @foreach(['urgent', 'high', 'medium', 'low'] as $p)
                @php $count = $casesByPriority[$p] ?? 0; @endphp
                <div class="flex items-center gap-3">
                    <span class="text-xs {{ $priorityText[$p] ?? 'text-gray-400' }} w-16">{{ $priorityLabels[$p] ?? $p }}</span>
                    <div class="flex-1 bg-gray-100 rounded-full h-3">
                        <div class="{{ $priorityColors[$p] ?? 'bg-gray-500' }} h-3 rounded-full transition-all" style="width: {{ $totalCases > 0 ? round(($count / $totalCases) * 100) : 0 }}%"></div>
                    </div>
                    <span class="text-xs text-gray-500 w-10 text-left" dir="ltr">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    </div>
    <div class="card p-5">
        <h2 class="text-sm font-bold text-gray-900 mb-4">{{ __('app.recent_activity') }}</h2>
        <div class="space-y-2 max-h-[340px] overflow-y-auto">
            @forelse($recentActivity as $item)
                @php
                    $typeConfig = [
                        'case' => ['color' => 'bg-blue-100 text-blue-700', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
                        'task' => ['color' => 'bg-purple-100 text-purple-700', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                        'client' => ['color' => 'bg-amber-100 text-amber-700', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                        'document' => ['color' => 'bg-green-100 text-green-700', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        'log' => ['color' => 'bg-gray-100 text-gray-500', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ];
                    $config = $typeConfig[$item['icon']] ?? $typeConfig['log'];
                @endphp
                <div class="flex items-center gap-3 p-2.5 rounded-xl bg-gray-50 border border-gray-100">
                    <div class="w-9 h-9 rounded-lg {{ $config['color'] }} flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $config['icon'] }}"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-gray-900 text-xs font-medium truncate">{{ $item['title'] }}</p>
                        <p class="text-gray-500 text-[11px] truncate">{{ $item['subtitle'] }}</p>
                    </div>
                    <span class="text-gray-400 text-[11px] flex-shrink-0" dir="ltr">{{ $item['time']?->diffForHumans() ?? '' }}</span>
                </div>
            @empty
                <div class="empty-state !py-8">
                    <p class="empty-title">{{ __('app.no_recent_activity') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endif
</div>

@if($isMgmt)
@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function () {
    const goldColor = '#C9A55A';
    const bgColor = '#FFFFFF';

    // === Cases by Status (Doughnut) ===
    const statusCtx = document.getElementById('casesStatusChart');
    if (statusCtx) {
        const chartData = @json($casesByStatus);
        const labels = {
            active: '{{ __("app.status_active") }}',
            pending: '{{ __("app.status_pending") }}',
            overdue: '{{ __("app.status_overdue") }}',
            closed: '{{ __("app.status_closed") }}',
            won: '{{ __("app.status_won") }}',
            lost: '{{ __("app.status_lost") }}',
            adjudicated: '{{ __("app.status_adjudicated") }}',
            fees_pending: '{{ __("app.status_fees_pending") }}',
        };
        const colors = {
            active: '#22c55e', pending: '#eab308', overdue: '#ef4444',
            closed: '#6b7280', won: '#3b82f6', lost: '#dc2626',
            adjudicated: '#10b981', fees_pending: '#ef4444',
        };
        const data = Object.keys(chartData).filter(k => chartData[k] > 0);
        new Chart(statusCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: data.map(k => labels[k] || k),
                datasets: [{
                    data: data.map(k => chartData[k]),
                    backgroundColor: data.map(k => colors[k] || '#6b7280'),
                    borderColor: bgColor, borderWidth: 3,
                    hoverBorderColor: goldColor, hoverBorderWidth: 2,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#6b7280', padding: 12, font: { size: 11 }, usePointStyle: true, pointStyleWidth: 8 }
                    },
                    tooltip: {
                        backgroundColor: '#1f2937', titleColor: goldColor, bodyColor: '#fff',
                        borderColor: goldColor, borderWidth: 1, padding: 10, rtl: true,
                    }
                }
            }
        });
    }

    // === Monthly Trend (Bar) ===
    const trendCtx = document.getElementById('monthlyTrendChart');
    if (trendCtx) {
        const trendData = @json($monthlyTrend);
        new Chart(trendCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: trendData.map(d => d.label),
                datasets: [
                    {
                        label: '{{ __("app.new_cases") }}',
                        data: trendData.map(d => d.new),
                        backgroundColor: goldColor + '99',
                        borderRadius: 4,
                    },
                    {
                        label: '{{ __("app.closed") }}',
                        data: trendData.map(d => d.closed),
                        backgroundColor: '#6b728099',
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { grid: { color: 'rgba(0,0,0,0.06)' }, ticks: { color: '#9ca3af', font: { size: 11 } } },
                    y: { grid: { color: 'rgba(0,0,0,0.06)' }, ticks: { color: '#9ca3af', font: { size: 11 }, stepSize: 1 } }
                },
                plugins: {
                    legend: {
                        labels: { color: '#6b7280', font: { size: 11 }, usePointStyle: true, pointStyleWidth: 8, padding: 12 }
                    },
                    tooltip: {
                        backgroundColor: '#1f2937', titleColor: goldColor, bodyColor: '#fff',
                        borderColor: goldColor, borderWidth: 1, padding: 10, rtl: true,
                    }
                }
            }
        });
    }

    // === Cases by Lawyer (Horizontal Bar) ===
    const lawyerCtx = document.getElementById('casesByLawyerChart');
    if (lawyerCtx) {
        const lawyerData = @json($casesByLawyer);
        const names = Object.keys(lawyerData);
        const counts = Object.values(lawyerData);
        new Chart(lawyerCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: names,
                datasets: [{
                    data: counts,
                    backgroundColor: goldColor + '99',
                    borderRadius: 4,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { grid: { color: 'rgba(0,0,0,0.06)' }, ticks: { color: '#9ca3af', font: { size: 11 }, stepSize: 1 } },
                    y: { grid: { display: false }, ticks: { color: '#6b7280', font: { size: 11 } } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1f2937', titleColor: goldColor, bodyColor: '#fff',
                        borderColor: goldColor, borderWidth: 1, padding: 10, rtl: true,
                    }
                }
            }
        });
    }
});
</script>
@endpush
@endif

@endsection