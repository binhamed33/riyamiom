@extends('layouts.app')

@section('title', __('app.page_dashboard'))

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

@php $isMgmt = auth()->user()->isAdmin() || auth()->user()->isDeveloper() || auth()->user()->hasPermission('users.view') || auth()->user()->hasPermission('feasibility.view') || auth()->user()->hasPermission('audit_log.view') || auth()->user()->hasPermission('settings.manage') || auth()->user()->hasPermission('backup.manage'); @endphp

@if($isMgmt)
    {{-- Row 1: Key Metrics (Management only) --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-4 hover:border-[#C9A55A]/50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-[#C9A55A]/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-[#C9A55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div>
                    <p class="text-white/40 text-xs">{{ __('app.total_cases') }}</p>
                    <p class="text-xl font-bold text-white">{{ $totalCases }}</p>
                </div>
            </div>
        </div>

        <div class="bg-navy rounded-xl border border-green-500/20 p-4 hover:border-green-500/50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div>
                    <p class="text-white/40 text-xs">{{ __('app.active_cases') }}</p>
                    <p class="text-xl font-bold text-green-400">{{ $activeCases }}</p>
                </div>
            </div>
        </div>

        <div class="bg-navy rounded-xl border border-blue-500/20 p-4 hover:border-blue-500/50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-white/40 text-xs">{{ __('app.win_rate') }}</p>
                    <p class="text-xl font-bold text-blue-400">{{ $winRate }}%</p>
                </div>
            </div>
        </div>

        <div class="bg-navy rounded-xl border border-purple-500/20 p-4 hover:border-purple-500/50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <div>
                    <p class="text-white/40 text-xs">{{ __('app.task_completion') }}</p>
                    <p class="text-xl font-bold text-purple-400">{{ $tasksCompletionRate }}%</p>
                </div>
            </div>
        </div>

        <div class="bg-navy rounded-xl border border-red-500/20 p-4 hover:border-red-500/50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-white/40 text-xs">{{ __('app.overdue') }}</p>
                    <p class="text-xl font-bold text-red-400">{{ $overdueCases + $overdueTasks }}</p>
                </div>
            </div>
        </div>

        <div class="bg-navy rounded-xl border border-amber-500/20 p-4 hover:border-amber-500/50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-white/40 text-xs">{{ __('app.clients') }}</p>
                    <p class="text-xl font-bold text-amber-400">{{ $totalClients }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 2: Monthly Comparison + Today's Sessions --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-5">
            <p class="text-white/40 text-xs mb-2">{{ __('app.new_this_month') }}</p>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-bold text-[#C9A55A]">{{ $newCasesThisMonth }}</p>
                <p class="text-xs mb-1">
                    @if($newCasesLastMonth > 0)
                        @php $change = round((($newCasesThisMonth - $newCasesLastMonth) / $newCasesLastMonth) * 100); @endphp
                        @if($change >= 0)
                            <span class="text-green-400">+{{ $change }}%</span>
                        @else
                            <span class="text-red-400">{{ $change }}%</span>
                        @endif
                    @endif
                    <span class="text-white/30">{{ __('app.from_last_month') }}</span>
                </p>
            </div>
            <div class="mt-2 flex items-center gap-3 text-xs text-white/30">
                <span>{{ $newClientsThisMonth }} {{ __('app.new_client') }}</span>
                <span>{{ $totalDocuments }} {{ __('app.document') }}</span>
            </div>
        </div>

        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-5">
            <p class="text-white/40 text-xs mb-2">{{ __('app.today_sessions') }}</p>
            @if($todaySessions->count() > 0)
                <p class="text-3xl font-bold text-green-400">{{ $todaySessions->count() }}</p>
                <div class="mt-2 space-y-1">
                    @foreach($todaySessions->take(2) as $s)
                        <p class="text-xs text-white/50 truncate">{{ $s->case->title ?? '' }} — {{ $s->location ?? '' }}</p>
                    @endforeach
                </div>
            @else
                <p class="text-3xl font-bold text-white/20">0</p>
                <p class="text-xs text-white/30 mt-2">{{ __('app.no_sessions_today') }}</p>
            @endif
        </div>

        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-5">
            <p class="text-white/40 text-xs mb-2">{{ __('app.tasks_completed_week') }}</p>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-bold text-purple-400">{{ $completedThisWeek }}</p>
                <p class="text-xs text-white/30 mb-1">{{ __('app.out_of') }} {{ $totalTasks }} {{ __('app.total') }}</p>
            </div>
            <div class="mt-3 w-full bg-white/5 rounded-full h-2">
                <div class="bg-purple-500 h-2 rounded-full transition-all" style="width: {{ $tasksCompletionRate }}%"></div>
            </div>
        </div>
    </div>

    {{-- Row 3: Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
            <h2 class="text-sm font-bold text-[#C9A55A] mb-4">{{ __('app.cases_by_status') }}</h2>
            <div class="flex justify-center" style="height: 240px;">
                <canvas id="casesStatusChart"></canvas>
            </div>
        </div>

        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
            <h2 class="text-sm font-bold text-[#C9A55A] mb-4">{{ __('app.monthly_trend') }}</h2>
            <div style="height: 240px;">
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>

        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
            <h2 class="text-sm font-bold text-[#C9A55A] mb-4">{{ __('app.cases_by_lawyer') }}</h2>
            <div style="height: 240px;">
                <canvas id="casesByLawyerChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Row 4: Priority Distribution + Cases Summary --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
            <h2 class="text-sm font-bold text-[#C9A55A] mb-4">{{ __('app.cases_by_priority') }}</h2>
            <div class="space-y-3">
                @php
                    $priorityColors = ['urgent' => 'bg-red-500', 'high' => 'bg-orange-500', 'medium' => 'bg-yellow-500', 'low' => 'bg-gray-500'];
                    $priorityText = ['urgent' => 'text-red-400', 'high' => 'text-orange-400', 'medium' => 'text-yellow-400', 'low' => 'text-white/40'];
                    $priorityLabels = ['urgent' => __('app.priority_urgent'), 'high' => __('app.priority_high'), 'medium' => __('app.priority_medium'), 'low' => __('app.priority_low')];
                @endphp
                @foreach(['urgent', 'high', 'medium', 'low'] as $p)
                    @php $count = $casesByPriority[$p] ?? 0; @endphp
                    <div class="flex items-center gap-3">
                        <span class="text-xs {{ $priorityText[$p] ?? 'text-white/40' }} w-16">{{ $priorityLabels[$p] ?? $p }}</span>
                        <div class="flex-1 bg-white/5 rounded-full h-3">
                            <div class="{{ $priorityColors[$p] ?? 'bg-gray-500' }} h-3 rounded-full transition-all" style="width: {{ $totalCases > 0 ? round(($count / $totalCases) * 100) : 0 }}%"></div>
                        </div>
                        <span class="text-xs text-white/50 w-10 text-left" dir="ltr">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
            <h2 class="text-sm font-bold text-[#C9A55A] mb-4">{{ __('app.office_overview') }}</h2>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white/[0.03] rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-white">{{ $totalLawyers }}</p>
                    <p class="text-xs text-white/40">{{ __('app.lawyers') }}</p>
                </div>
                <div class="bg-white/[0.03] rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-white">{{ $totalSessions }}</p>
                    <p class="text-xs text-white/40">{{ __('app.total_sessions') }}</p>
                </div>
                <div class="bg-white/[0.03] rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-green-400">{{ $wonCases }}</p>
                    <p class="text-xs text-white/40">{{ __('app.won') }}</p>
                </div>
                <div class="bg-white/[0.03] rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-red-400">{{ $lostCases }}</p>
                    <p class="text-xs text-white/40">{{ __('app.lost') }}</p>
                </div>
            </div>
        </div>
    </div>
@else
    {{-- Lawyer/Staff/Client: Personal Dashboard --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-[#C9A55A]/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-[#C9A55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div>
                    <p class="text-white/40 text-xs">{{ __('app.total_cases') }}</p>
                    <p class="text-xl font-bold text-white">{{ $totalCases }}</p>
                </div>
            </div>
        </div>
        <div class="bg-navy rounded-xl border border-purple-500/20 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <div>
                    <p class="text-white/40 text-xs">{{ __('app.pending_tasks') }}</p>
                    <p class="text-xl font-bold text-purple-400">{{ $pendingTasks + $inProgressTasks }}</p>
                </div>
            </div>
        </div>
        <div class="bg-navy rounded-xl border border-green-500/20 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-white/40 text-xs">{{ __('app.upcoming_sessions') }}</p>
                    <p class="text-xl font-bold text-green-400">{{ $upcomingSessions->count() }}</p>
                </div>
            </div>
        </div>
    </div>
@endif

    {{-- Row 5: Overdue Tasks + Upcoming Sessions + Pending Tasks --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Overdue Tasks --}}
        <div class="bg-navy rounded-xl border border-red-500/20 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-red-400 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('app.overdue_tasks') }}
                </h2>
                @if($overdueTasks > 0)
                    <span class="px-2 py-0.5 bg-red-500/20 text-red-400 text-xs rounded-full font-bold">{{ $overdueTasks }}</span>
                @endif
            </div>
            <div class="space-y-2">
                @forelse($overdueTasksList as $task)
                    <div class="flex items-center gap-3 p-2.5 rounded-lg bg-red-500/5 border border-red-500/10">
                        <div class="w-2 h-2 rounded-full bg-red-400 flex-shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-white text-xs font-medium truncate">{{ $task->title }}</p>
                            <p class="text-red-400/60 text-[11px]">{{ $task->assignee?->name ?? '' }} — {{ $task->due_date?->format('Y/m/d') ?? '' }}</p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-6 text-green-400/50">
                        <svg class="w-8 h-8 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs">{{ __('app.no_overdue_tasks') }}</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Upcoming Sessions --}}
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-[#C9A55A]">{{ __('app.upcoming_sessions') }}</h2>
                <a href="{{ route('sessions.index') }}" class="text-xs text-[#C9A55A]/70 hover:text-[#C9A55A]">{{ __('app.view_all') }}</a>
            </div>
            <div class="space-y-2">
                @forelse($upcomingSessions as $session)
                    <div class="flex items-center gap-3 p-2.5 rounded-lg bg-white/5 border border-white/5 hover:border-[#C9A55A]/30 transition-colors">
                        <div class="w-10 h-10 rounded-lg bg-[#C9A55A]/10 flex items-center justify-center flex-shrink-0">
                            <div class="text-center leading-none">
                                <p class="text-[10px] text-[#C9A55A]/70">{{ $session->date?->format('M') ?? '' }}</p>
                                <p class="text-sm font-bold text-[#C9A55A]">{{ $session->date?->format('d') ?? '' }}</p>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-white text-xs font-medium truncate">{{ $session->case->title ?? '' }}</p>
                            <p class="text-white/40 text-[11px]">{{ $session->location ?? '' }} — {{ $session->type ?? '' }}</p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-6 text-white/30">
                        <p class="text-xs">{{ __('app.no_upcoming_sessions') }}</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Pending Tasks --}}
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-[#C9A55A]">{{ __('app.pending_tasks') }}</h2>
                <a href="{{ route('tasks.index') }}" class="text-xs text-[#C9A55A]/70 hover:text-[#C9A55A]">{{ __('app.view_all') }}</a>
            </div>
            <div class="space-y-2">
                @forelse($pendingTasksList->take(5) as $task)
                    <div class="flex items-center gap-3 p-2.5 rounded-lg bg-white/5 border border-white/5 hover:border-[#C9A55A]/30 transition-colors">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0
                            @if(($task->priority ?? '') === 'urgent') bg-red-500/15
                            @elseif(($task->priority ?? '') === 'high') bg-orange-500/15
                            @elseif(($task->priority ?? '') === 'medium') bg-yellow-500/15
                            @else bg-gray-500/15 @endif">
                            @if(($task->priority ?? '') === 'urgent')
                                <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            @else
                                <svg class="w-4 h-4 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-white text-xs font-medium truncate">{{ $task->title }}</p>
                            <p class="text-white/40 text-[11px]">{{ $task->assignee?->name ?? '' }} — {{ $task->due_date?->format('Y/m/d') ?? __('app.no_due_date') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-6 text-white/30">
                        <svg class="w-8 h-8 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs">{{ __('app.no_pending_tasks') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Row 6: Recent Activity --}}
    <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
        <h2 class="text-sm font-bold text-[#C9A55A] mb-4">{{ __('app.recent_activity') }}</h2>
        <div class="space-y-2">
            @forelse($recentActivity as $item)
                @php
                    $typeConfig = [
                        'case' => ['color' => 'bg-blue-500/15 text-blue-400', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
                        'task' => ['color' => 'bg-purple-500/15 text-purple-400', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                        'client' => ['color' => 'bg-amber-500/15 text-amber-400', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                        'document' => ['color' => 'bg-green-500/15 text-green-400', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        'log' => ['color' => 'bg-white/10 text-white/50', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ];
                    $config = $typeConfig[$item['icon']] ?? $typeConfig['log'];
                @endphp
                @if($item['url'])
                    <a href="{{ $item['url'] }}" class="flex items-center gap-3 p-3 rounded-lg bg-white/[0.02] border border-white/5 hover:border-[#C9A55A]/30 transition-colors group">
                @else
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-white/[0.02] border border-white/5">
                @endif
                    <div class="w-9 h-9 rounded-lg {{ $config['color'] }} flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $config['icon'] }}"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-xs font-medium truncate group-hover:text-[#C9A55A] transition-colors">{{ $item['title'] }}</p>
                        <p class="text-white/35 text-[11px] truncate">{{ $item['subtitle'] }}</p>
                    </div>
                    <span class="text-white/25 text-[11px] flex-shrink-0" dir="ltr">{{ $item['time']?->diffForHumans() ?? '' }}</span>
                @if($item['url'])
                    </a>
                @else
                    </div>
                @endif
            @empty
                <div class="text-center py-6 text-white/30">
                    <p class="text-xs">{{ __('app.no_recent_activity') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

@if($isMgmt)
@push('scripts')
<script nonce="{{ $cspNonce }}" src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function () {
    const goldColor = '#C9A55A';
    const bgColor = '#111B2E';

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
        };
        const colors = {
            active: '#22c55e', pending: '#eab308', overdue: '#ef4444',
            closed: '#6b7280', won: '#3b82f6', lost: '#dc2626',
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
                        labels: { color: '#9ca3af', padding: 12, font: { size: 11 }, usePointStyle: true, pointStyleWidth: 8 }
                    },
                    tooltip: {
                        backgroundColor: bgColor, titleColor: goldColor, bodyColor: '#fff',
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
                    x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9ca3af', font: { size: 11 } } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9ca3af', font: { size: 11 }, stepSize: 1 } }
                },
                plugins: {
                    legend: {
                        labels: { color: '#9ca3af', font: { size: 11 }, usePointStyle: true, pointStyleWidth: 8, padding: 12 }
                    },
                    tooltip: {
                        backgroundColor: bgColor, titleColor: goldColor, bodyColor: '#fff',
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
                    x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9ca3af', font: { size: 11 }, stepSize: 1 } },
                    y: { grid: { display: false }, ticks: { color: '#9ca3af', font: { size: 11 } } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: bgColor, titleColor: goldColor, bodyColor: '#fff',
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
