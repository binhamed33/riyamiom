@extends('layouts.app')

@section('title', __('app.page_feasibility_study'))

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <h1 class="text-2xl sm:text-3xl font-bold text-gold-dark flex items-center gap-3">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            {{ __('app.feasibility_study') }}
        </h1>
        <div class="flex items-center gap-2 text-gray-400 text-xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            {{ now()->format('Y/m/d') }}
        </div>
    </div>

    {{-- ما الذي تقوله هذه الصفحة — قبل أي رقم --}}
    @php
        $decidedAll = $wonCasesAll + $lostCasesAll;
        $verdictKey = $decidedAll === 0
            ? 'mixed'
            : ($officeWinRate >= 60 && $officeTaskRate >= 60 ? 'strong'
                : ($officeWinRate >= 40 || $officeTaskRate >= 40 ? 'mixed' : 'weak'));
        $verdictTone = ['strong' => 'good', 'mixed' => 'warn', 'weak' => 'bad'][$verdictKey];
        $toneClass = [
            'good' => 'border-green-200 bg-green-50',
            'warn' => 'border-yellow-200 bg-amber-50',
            'bad'  => 'border-red-200 bg-red-50',
        ][$verdictTone];
        $toneText = ['good' => 'text-green-800', 'warn' => 'text-amber-800', 'bad' => 'text-red-800'][$verdictTone];
    @endphp

    <div class="rounded-xl border {{ $toneClass }} p-5">
        <p class="text-xs font-bold text-gray-500">{{ __('app.feas_verdict') }}</p>
        <p class="text-lg font-bold {{ $toneText }} mt-1">{{ __('app.feas_verdict_' . $verdictKey) }}</p>
        <p class="text-sm text-gray-600 mt-2 leading-relaxed">
            {{ __('app.feas_verdict_body', [
                'won' => $wonCasesAll,
                'decided' => $decidedAll,
                'done' => $completedTasksAll,
                'tasks' => $totalTasksAll,
            ]) }}
        </p>
        <p class="text-xs text-gray-500 mt-3 leading-relaxed border-t border-gray-200 pt-3">
            <span class="font-semibold">{{ __('app.feas_intro_title') }}</span>
            {{ __('app.feas_intro_body') }}
        </p>
    </div>

    {{-- الأرقام، وكلٌّ منها يقول مِمَّ حُسب --}}
    @php
        $cards = [
            ['label' => __('app.office_win_rate'), 'value' => $officeWinRate . '%',
             'how' => __('app.feas_how_win'), 'accent' => 'text-green-700',
             'empty' => $decidedAll === 0 ? __('app.feas_no_decided_hint') : null],
            ['label' => __('app.office_task_rate'), 'value' => $officeTaskRate . '%',
             'how' => __('app.feas_how_task'), 'accent' => 'text-purple-700', 'empty' => null],
            ['label' => __('app.deadline_compliance'), 'value' => $avgDeadline . '%',
             'how' => __('app.feas_how_deadline'), 'accent' => 'text-blue-700', 'empty' => null],
            ['label' => __('app.team_average'), 'value' => $avgOverall . '%',
             'how' => __('app.feas_how_overall'), 'accent' => 'text-gold-dark', 'empty' => null],
        ];
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach ($cards as $card)
            <div class="bg-white rounded-xl border border-gold/15 p-4 flex flex-col">
                <p class="text-gray-500 text-xs font-semibold">{{ $card['label'] }}</p>
                <p class="text-3xl font-bold {{ $card['accent'] }} mt-1 num">{{ $card['value'] }}</p>
                <p class="text-[11px] text-gray-400 mt-2 leading-relaxed flex-1">
                    <span class="font-semibold text-gray-500">{{ __('app.feas_how') }}:</span>
                    {{ $card['how'] }}
                </p>
                @if ($card['empty'])
                    <p class="text-[11px] text-amber-700 mt-2 leading-relaxed">{{ $card['empty'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- أرقام المكتب الخام --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-gold/15 p-4">
            <p class="text-gray-400 text-xs">{{ __('app.total_lawyers') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1 num">{{ $totalLawyers }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-4">
            <p class="text-gray-400 text-xs">{{ __('app.total_cases') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1 num">{{ $totalCasesAll }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-4">
            <p class="text-gray-400 text-xs">{{ __('app.total_tasks') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1 num">{{ $totalTasksAll }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-4">
            <p class="text-gray-400 text-xs">{{ __('app.status_won') }} / {{ __('app.status_lost') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1 num">{{ $wonCasesAll }} / {{ $lostCasesAll }}</p>
        </div>
    </div>

    {{-- Top & Least Performer --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Top Performer --}}
        @if(isset($topPerformer))
        <div class="bg-gradient-to-br from-gold/15 to-gold/5 rounded-xl border border-gold/25 p-6">
            <div class="flex items-center gap-3 mb-3">
                <svg class="w-5 h-5 text-gold-dark" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                <span class="text-gold-dark text-sm font-bold">{{ __('app.top_performer') }}</span>
            </div>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-700 text-xl font-bold">{{ $topPerformer['user']->name }}</p>
                    <p class="text-gray-500 text-xs mt-1">{{ $topPerformer['total_cases'] }} {{ __('app.cases') }} · {{ $topPerformer['total_tasks'] }} {{ __('app.tasks') }} · {{ $topPerformer['active_days'] }} {{ __('app.days') }}</p>
                </div>
                <div class="text-left">
                    <p class="text-4xl font-bold text-gold-dark">{{ $topPerformer['overall'] }}%</p>
                    <p class="text-gray-400 text-xs">{{ __('app.efficiency_rate') }}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-4">
                <div class="text-center">
                    <p class="text-sm font-bold text-green-700">{{ $topPerformer['success_rate'] }}%</p>
                    <p class="text-[10px] text-gray-400">{{ __('app.success_rate') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-sm font-bold text-purple-700">{{ $topPerformer['task_completion'] }}%</p>
                    <p class="text-[10px] text-gray-400">{{ __('app.task_completion') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-sm font-bold text-blue-700">{{ $topPerformer['deadline_compliance'] }}%</p>
                    <p class="text-[10px] text-gray-400">{{ __('app.deadline_compliance') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-sm font-bold text-gold-dark num">{{ $topPerformer['productivity'] }}</p>
                    <p class="text-[10px] text-gray-400">{{ __('app.feas_unit_tasks_day') }}</p>
                </div>
            </div>
        </div>
        @endif

        {{-- Least Performer --}}
        @if(isset($leastPerformer) && $leastPerformer['user']->id !== ($topPerformer['user']->id ?? null))
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex items-center gap-3 mb-3">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                <span class="text-gray-400 text-sm font-bold">{{ __('app.needs_improvement') }}</span>
            </div>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-700 text-xl font-bold">{{ $leastPerformer['user']->name }}</p>
                    <p class="text-gray-500 text-xs mt-1">{{ $leastPerformer['total_cases'] }} {{ __('app.cases') }} · {{ $leastPerformer['total_tasks'] }} {{ __('app.tasks') }} · {{ $leastPerformer['active_days'] }} {{ __('app.days') }}</p>
                </div>
                <div class="text-left">
                    <p class="text-4xl font-bold text-gray-400">{{ $leastPerformer['overall'] }}%</p>
                    <p class="text-gray-400 text-xs">{{ __('app.efficiency_rate') }}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-4">
                <div class="text-center">
                    <p class="text-sm font-bold text-gray-500">{{ $leastPerformer['success_rate'] }}%</p>
                    <p class="text-[10px] text-gray-400">{{ __('app.success_rate') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-sm font-bold text-gray-500">{{ $leastPerformer['task_completion'] }}%</p>
                    <p class="text-[10px] text-gray-400">{{ __('app.task_completion') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-sm font-bold text-gray-500">{{ $leastPerformer['deadline_compliance'] }}%</p>
                    <p class="text-[10px] text-gray-400">{{ __('app.deadline_compliance') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-sm font-bold text-gray-500 num">{{ $leastPerformer['productivity'] }}</p>
                    <p class="text-[10px] text-gray-400">{{ __('app.feas_unit_tasks_day') }}</p>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- الرسوم: لكلٍّ سؤالٌ يجيب عنه بالعربية الواضحة، وجدولُ أرقامه تحته --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- مقارنة الكفاءة --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark">{{ __('app.efficiency_comparison') }}</h2>
            <p class="text-[11px] text-gray-400 mt-1 mb-4 leading-relaxed">{{ __('app.efficiency_comparison_help') }}</p>
            @php
                // عضوٌ بلا قضايا ولا مهام لا يصنع مقارنة — الصفر ليس بياناً
                $effHasData = collect($efficiencyData ?? [])
                    ->contains(fn ($r) => ($r['overall'] ?? 0) + ($r['success_rate'] ?? 0) + ($r['task_completion'] ?? 0) > 0);
            @endphp
            @if($effHasData)
                <div style="height: 260px;">
                    <canvas id="efficiencyChart"></canvas>
                </div>
                <details class="mt-3">
                    <summary class="text-[11px] font-bold text-gray-400 cursor-pointer select-none hover:text-gold-dark transition">{{ __('app.show_numbers') }}</summary>
                    <div class="overflow-x-auto mt-2">
                        <table class="w-full text-[11px]">
                            <thead>
                                <tr class="text-gray-400 border-b border-gray-200">
                                    <th class="py-1.5 text-right font-bold">{{ __('app.name') }}</th>
                                    <th class="py-1.5 text-right font-bold">{{ __('app.efficiency_rate') }}</th>
                                    <th class="py-1.5 text-right font-bold">{{ __('app.success_rate') }}</th>
                                    <th class="py-1.5 text-right font-bold">{{ __('app.task_completion') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100" style="font-variant-numeric: tabular-nums">
                                @foreach($efficiencyData as $row)
                                    <tr>
                                        <td class="py-1.5 text-gray-700">{{ $row['user']['name'] ?? '—' }}</td>
                                        <td class="py-1.5 text-gray-600">{{ $row['overall'] }}٪</td>
                                        <td class="py-1.5 text-gray-600">{{ $row['success_rate'] }}٪</td>
                                        <td class="py-1.5 text-gray-600">{{ $row['task_completion'] }}٪</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @else
                <p class="text-xs text-gray-400 text-center py-12">{{ __('app.no_data_yet') }}</p>
            @endif
        </div>

        {{-- اتجاهات القضايا الشهرية --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark">{{ __('app.monthly_case_trends') }}</h2>
            <p class="text-[11px] text-gray-400 mt-1 mb-4 leading-relaxed">{{ __('app.monthly_case_trends_help') }}</p>
            @php
                // ستة أشهر تُعاد دائماً ولو بأصفار — فالفراغ أن تخلو من أي حركة
                $trendHasData = collect($monthlyTrend ?? [])
                    ->contains(fn ($m) => ($m['new'] ?? 0) + ($m['won'] ?? 0) + ($m['lost'] ?? 0) > 0);
            @endphp
            @if($trendHasData)
                <div style="height: 260px;">
                    <canvas id="casesTrendChart"></canvas>
                </div>
                <details class="mt-3">
                    <summary class="text-[11px] font-bold text-gray-400 cursor-pointer select-none hover:text-gold-dark transition">{{ __('app.show_numbers') }}</summary>
                    <div class="overflow-x-auto mt-2">
                        <table class="w-full text-[11px]">
                            <thead>
                                <tr class="text-gray-400 border-b border-gray-200">
                                    <th class="py-1.5 text-right font-bold">{{ __('app.month') }}</th>
                                    <th class="py-1.5 text-right font-bold">{{ __('app.new_cases') }}</th>
                                    <th class="py-1.5 text-right font-bold">{{ __('app.won') }}</th>
                                    <th class="py-1.5 text-right font-bold">{{ __('app.lost') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100" style="font-variant-numeric: tabular-nums">
                                @foreach($monthlyTrend as $m)
                                    <tr>
                                        <td class="py-1.5 text-gray-700">{{ $m['label'] }}</td>
                                        <td class="py-1.5 text-gray-600">{{ $m['new'] }}</td>
                                        <td class="py-1.5 text-gray-600">{{ $m['won'] }}</td>
                                        <td class="py-1.5 text-gray-600">{{ $m['lost'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @else
                <p class="text-xs text-gray-400 text-center py-12">{{ __('app.no_data_yet') }}</p>
            @endif
        </div>

        {{-- القضايا حسب النوع --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark">{{ __('app.cases_by_type') }}</h2>
            <p class="text-[11px] text-gray-400 mt-1 mb-4 leading-relaxed">{{ __('app.cases_by_type_help') }}</p>
            @php $typeTotal = array_sum($casesByType ?? []); @endphp
            @if($typeTotal > 0)
                <div class="flex justify-center" style="height: 260px;">
                    <canvas id="casesTypeChart"></canvas>
                </div>
                <details class="mt-3">
                    <summary class="text-[11px] font-bold text-gray-400 cursor-pointer select-none hover:text-gold-dark transition">{{ __('app.show_numbers') }}</summary>
                    <table class="w-full text-[11px] mt-2">
                        <tbody class="divide-y divide-gray-100" style="font-variant-numeric: tabular-nums">
                            @foreach(collect($casesByType)->filter()->sortDesc() as $type => $count)
                                <tr>
                                    <td class="py-1.5 text-gray-700">{{ $type }}</td>
                                    <td class="py-1.5 text-gray-600 text-left">{{ $count }} <span class="text-gray-400">({{ round(($count / $typeTotal) * 100) }}٪)</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            @else
                <p class="text-xs text-gray-400 text-center py-12">{{ __('app.no_data_yet') }}</p>
            @endif
        </div>
    </div>

    {{-- Metric Comparison Radar --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Radar Chart --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark">{{ __('app.team_comparison') }}</h2>
            <p class="text-[11px] text-gray-400 mt-1 mb-4 leading-relaxed">{{ __('app.team_comparison_help') }}</p>
            @if($effHasData ?? false)
                <div class="flex justify-center" style="height: 300px;">
                    <canvas id="radarChart"></canvas>
                </div>
            @else
                <p class="text-xs text-gray-400 text-center py-16">{{ __('app.no_data_yet') }}</p>
            @endif
        </div>

        {{-- Office Averages Breakdown --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark mb-4">{{ __('app.office_averages') }}</h2>
            <div class="space-y-4">
                @php
                    $metrics = [
                        ['label' => __('app.efficiency_rate'), 'value' => $avgOverall, 'color' => 'bg-amber-500'],
                        ['label' => __('app.success_rate'), 'value' => $avgSuccess, 'color' => 'bg-green-500'],
                        ['label' => __('app.task_completion'), 'value' => $avgTaskComp, 'color' => 'bg-purple-500'],
                        ['label' => __('app.deadline_compliance'), 'value' => $avgDeadline, 'color' => 'bg-blue-500'],
                    ];
                @endphp
                @foreach($metrics as $m)
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-gray-500">{{ $m['label'] }}</span>
                        <span class="text-sm font-bold text-gray-900">{{ $m['value'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-3">
                        <div class="{{ $m['color'] }} h-3 rounded-full transition-all" style="width: {{ $m['value'] }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mt-6 pt-4 border-t border-gray-100">
                <h3 class="text-xs font-bold text-gray-400 mb-3">{{ __('app.workload_distribution') }}</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach($efficiencyData as $entry)
                    <div class="text-center bg-gray-50 rounded-lg p-3">
                        <p class="text-lg font-bold text-gold-dark">{{ $entry['total_cases'] }}</p>
                        <p class="text-[10px] text-gray-400">{{ $entry['user']->name }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Full Leaderboard --}}
    <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
        <div class="px-6 py-4 border-b border-gold/15">
            <h2 class="text-sm font-bold text-gold-dark">{{ __('app.leaderboard') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gold-dark/70 w-12">#</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gold-dark/70">{{ __('app.name') }}</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gold-dark/70">{{ __('app.efficiency_rate') }}</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gold-dark/70">{{ __('app.total_cases') }}</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gold-dark/70">{{ __('app.success_rate') }}</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gold-dark/70">{{ __('app.task_completion') }}</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gold-dark/70">{{ __('app.deadline_compliance') }}</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gold-dark/70">{{ __('app.overdue') }}</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gold-dark/70">{{ __('app.productivity') }}</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gold-dark/70">{{ __('app.sessions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($efficiencyData as $index => $entry)
                        <tr class="hover:bg-gray-50 transition {{ $index === 0 ? 'bg-gold/10' : '' }}">
                            <td class="px-4 py-3">
                                @if($index === 0)
                                    <span class="w-7 h-7 rounded-full bg-gold/20 text-gold-dark text-sm font-bold inline-flex items-center justify-center">1</span>
                                @elseif($index === 1)
                                    <span class="w-7 h-7 rounded-full bg-gray-100 text-gray-600 text-sm font-bold inline-flex items-center justify-center">2</span>
                                @elseif($index === 2)
                                    <span class="w-7 h-7 rounded-full bg-amber-700/20 text-amber-500 text-sm font-bold inline-flex items-center justify-center">3</span>
                                @else
                                    <span class="text-gray-400 text-sm pl-1">{{ $index + 1 }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-gray-700 font-medium text-sm">{{ $entry['user']->name }}</p>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $eff = $entry['overall'];
                                    $barColor = $eff > 80 ? 'bg-emerald-500' : ($eff >= 60 ? 'bg-amber-500' : 'bg-red-500');
                                    $textColor = $eff > 80 ? 'text-emerald-700' : ($eff >= 60 ? 'text-gold-dark' : 'text-red-700');
                                @endphp
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-100 rounded-full h-2">
                                        <div class="{{ $barColor }} h-2 rounded-full transition-all" style="width: {{ $eff }}%"></div>
                                    </div>
                                    <span class="{{ $textColor }} text-xs font-semibold w-10 text-left" dir="ltr">{{ $eff }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-700 text-sm">{{ $entry['total_cases'] }}</td>
                            <td class="px-4 py-3 text-gray-700 text-sm">{{ $entry['success_rate'] }}%</td>
                            <td class="px-4 py-3 text-gray-700 text-sm">{{ $entry['task_completion'] }}%</td>
                            <td class="px-4 py-3 text-gray-700 text-sm">{{ $entry['deadline_compliance'] }}%</td>
                            <td class="px-4 py-3">
                                @if($entry['overdue_tasks'] > 0)
                                    <span class="text-red-700 text-sm font-medium">{{ $entry['overdue_tasks'] }}</span>
                                @else
                                    <span class="text-green-700 text-xs">0</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700 text-sm">{{ $entry['productivity'] }}</td>
                            <td class="px-4 py-3 text-gray-700 text-sm">{{ $entry['total_sessions'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-14 text-center">
                                <svg class="w-10 h-10 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 15l4-4 3 3 5-6"/>
                                </svg>
                                <p class="text-gray-700 font-semibold text-sm">{{ __('app.empty_feasibility_title') }}</p>
                                <p class="text-gray-400 text-xs mt-1.5 max-w-md mx-auto leading-relaxed">{{ __('app.empty_feasibility_body') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function () {
    // الألوان من الرموز وقت البناء — الـcanvas لا يفهم var(--accent)
    var goldColor = MdChart.accent(1);
    var bgColor = MdChart.surface();
    var gridColor = MdChart.grid();
    const tickColor = '#4B5563';

    // === Efficiency Comparison (Grouped Bar) ===
    const effCtx = document.getElementById('efficiencyChart');
    if (effCtx) {
        const data = @json($efficiencyData);
        new Chart(effCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.map(d => d.user.name),
                datasets: [
                    { label: '{{ __("app.efficiency_rate") }}', data: data.map(d => d.overall), backgroundColor: MdChart.series(0), borderRadius: 4, borderSkipped: false },
                    { label: '{{ __("app.success_rate") }}', data: data.map(d => d.success_rate), backgroundColor: MdChart.series(1), borderRadius: 4, borderSkipped: false },
                    { label: '{{ __("app.task_completion") }}', data: data.map(d => d.task_completion), backgroundColor: MdChart.series(2), borderRadius: 4, borderSkipped: false },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false }, ticks: { color: tickColor, font: { size: 10 } } },
                    y: { grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 10 }, callback: v => v + '%' }, max: 100 }
                },
                plugins: {
                    legend: { labels: { color: tickColor, font: { size: 10 }, usePointStyle: true, pointStyleWidth: 8, padding: 12 } },
                    tooltip: MdChart.tooltip()
                }
            }
        });
    }

    // === Monthly Cases Trend (Line) ===
    const trendCtx = document.getElementById('casesTrendChart');
    if (trendCtx) {
        const trend = @json($monthlyTrend);
        new Chart(trendCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: trend.map(t => t.label),
                datasets: [
                    { label: '{{ __("app.new_cases") }}', data: trend.map(t => t.new), borderColor: MdChart.series(0), backgroundColor: MdChart.withAlpha(MdChart.series(0), 0.14), fill: true, tension: 0.3, borderWidth: 2, pointRadius: 4, pointBackgroundColor: MdChart.series(0) },
                    { label: '{{ __("app.won") }}', data: trend.map(t => t.won), borderColor: MdChart.status('good'), backgroundColor: 'transparent', fill: false, tension: 0.3, borderWidth: 2, pointRadius: 4, pointBackgroundColor: MdChart.status('good') },
                    { label: '{{ __("app.lost") }}', data: trend.map(t => t.lost), borderColor: MdChart.status('bad'), backgroundColor: 'transparent', fill: false, tension: 0.3, borderWidth: 2, pointRadius: 4, pointBackgroundColor: MdChart.status('bad') },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 10 } } },
                    y: { grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 10 }, stepSize: 1 } }
                },
                plugins: {
                    legend: { labels: { color: tickColor, font: { size: 10 }, usePointStyle: true, pointStyleWidth: 8, padding: 12 } },
                    tooltip: MdChart.tooltip()
                }
            }
        });
    }

    // === توزيع القضايا حسب النوع (حلقي) ===
    const typeCtx = document.getElementById('casesTypeChart');
    if (typeCtx) {
        const typeData = @json($casesByType);
        // ثلاث فئات بألوان ثابتة ثم «أخرى» — لا لون رابعاً مولَّداً،
        // ولا دوران يعيد استعمال اللون نفسه لفئتين
        const sorted = Object.keys(typeData)
            .filter(k => typeData[k] > 0)
            .sort((a, b) => typeData[b] - typeData[a]);
        const top = sorted.slice(0, 3);
        const restTotal = sorted.slice(3).reduce((sum, k) => sum + typeData[k], 0);

        const labels = top.slice();
        const values = top.map(k => typeData[k]);
        const colors = top.map((_, i) => MdChart.series(i));

        if (restTotal > 0) {
            labels.push(@json(__('app.other')));
            values.push(restTotal);
            colors.push(MdChart.status('idle'));
        }

        if (labels.length) {
            new Chart(typeCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderColor: MdChart.surface(),
                        borderWidth: 2,
                        hoverOffset: 6,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '62%',
                    plugins: { legend: MdChart.legend(), tooltip: MdChart.tooltip() }
                }
            });
        }
    }

    // === Radar Chart (Team Comparison) ===
    const radarCtx = document.getElementById('radarChart');
    if (radarCtx) {
        const radarData = @json($efficiencyData);
        // ثلاثة على الأكثر: رادار بستّ سلاسل لا يُقرأ، والألوان ثابتة لا تدور
        const radarTop = radarData.slice(0, 3);
        new Chart(radarCtx.getContext('2d'), {
            type: 'radar',
            data: {
                labels: ['{{ __("app.success_rate") }}', '{{ __("app.task_completion") }}', '{{ __("app.deadline_compliance") }}', '{{ __("app.productivity") }}'],
                datasets: radarTop.map((d, i) => ({
                    label: d.user.name,
                    // الإنتاجية بمقياسها المُعايَر (٠–١٠٠) لا بعدد المهام في اليوم،
                    // فتُقارَن على نفس محور بقية المقاييس
                    data: [d.success_rate, d.task_completion, d.deadline_compliance, d.productivity_score],
                    borderColor: MdChart.series(i),
                    backgroundColor: MdChart.withAlpha(MdChart.series(i), 0.14),
                    pointBackgroundColor: MdChart.series(i),
                    pointBorderColor: MdChart.series(i),
                    borderWidth: 2,
                    pointRadius: 3,
                }))
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true, max: 100,
                        grid: { color: gridColor },
                        angleLines: { color: gridColor },
                        pointLabels: { color: tickColor, font: { size: 10 } },
                        ticks: { display: false }
                    }
                },
                plugins: {
                    legend: { position: 'bottom', labels: { color: tickColor, font: { size: 10 }, usePointStyle: true, pointStyleWidth: 8, padding: 12 } },
                    tooltip: MdChart.tooltip()
                }
            }
        });
    }
});
</script>
@endpush

@endsection
