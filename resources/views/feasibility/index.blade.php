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

    {{-- Office-Wide Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
        <div class="bg-white rounded-xl border border-gold/15 p-4">
            <p class="text-gray-400 text-xs">{{ __('app.total_lawyers') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totalLawyers }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-4">
            <p class="text-gray-400 text-xs">{{ __('app.total_cases') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totalCasesAll }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-4">
            <p class="text-gray-400 text-xs">{{ __('app.office_win_rate') }}</p>
            <p class="text-2xl font-bold text-green-700 mt-1">{{ $officeWinRate }}%</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-4">
            <p class="text-gray-400 text-xs">{{ __('app.office_task_rate') }}</p>
            <p class="text-2xl font-bold text-purple-700 mt-1">{{ $officeTaskRate }}%</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-4">
            <p class="text-gray-400 text-xs">{{ __('app.total_tasks') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totalTasksAll }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-4">
            <p class="text-gray-400 text-xs">{{ __('app.team_average') }}</p>
            <p class="text-2xl font-bold text-gold-dark mt-1">{{ $avgOverall }}%</p>
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
                    <p class="text-sm font-bold text-gold-dark">{{ $topPerformer['productivity'] }}</p>
                    <p class="text-[10px] text-gray-400">{{ __('app.productivity') }}</p>
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
                    <p class="text-sm font-bold text-gray-500">{{ $leastPerformer['productivity'] }}</p>
                    <p class="text-[10px] text-gray-400">{{ __('app.productivity') }}</p>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Efficiency Comparison (Bar) --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark mb-4">{{ __('app.efficiency_comparison') }}</h2>
            <div style="height: 260px;">
                <canvas id="efficiencyChart"></canvas>
            </div>
        </div>

        {{-- Cases Trend (Line) --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark mb-4">{{ __('app.monthly_case_trends') }}</h2>
            <div style="height: 260px;">
                <canvas id="casesTrendChart"></canvas>
            </div>
        </div>

        {{-- Cases by Type (Pie) --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark mb-4">{{ __('app.cases_by_type') }}</h2>
            <div class="flex justify-center" style="height: 260px;">
                <canvas id="casesTypeChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Metric Comparison Radar --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Radar Chart --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark mb-4">{{ __('app.team_comparison') }}</h2>
            <div class="flex justify-center" style="height: 300px;">
                <canvas id="radarChart"></canvas>
            </div>
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
    const goldColor = 'var(--accent)';
    const bgColor = '#FFFFFF';
    const gridColor = 'rgba(0,0,0,0.06)';
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
                    { label: '{{ __("app.efficiency_rate") }}', data: data.map(d => d.overall), backgroundColor: goldColor + 'cc', borderRadius: 4 },
                    { label: '{{ __("app.success_rate") }}', data: data.map(d => d.success_rate), backgroundColor: '#22C55E99', borderRadius: 4 },
                    { label: '{{ __("app.task_completion") }}', data: data.map(d => d.task_completion), backgroundColor: '#8B5CF699', borderRadius: 4 },
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
                    tooltip: { backgroundColor: bgColor, titleColor: goldColor, bodyColor: '#fff', borderColor: goldColor, borderWidth: 1, padding: 10, rtl: true }
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
                    { label: '{{ __("app.new_cases") }}', data: trend.map(t => t.new), borderColor: goldColor, backgroundColor: goldColor + '22', fill: true, tension: 0.3, pointRadius: 4, pointBackgroundColor: goldColor },
                    { label: '{{ __("app.won") }}', data: trend.map(t => t.won), borderColor: '#22C55E', backgroundColor: '#22C55E22', fill: false, tension: 0.3, pointRadius: 4, pointBackgroundColor: '#22C55E' },
                    { label: '{{ __("app.lost") }}', data: trend.map(t => t.lost), borderColor: '#EF4444', backgroundColor: '#EF444422', fill: false, tension: 0.3, pointRadius: 4, pointBackgroundColor: '#EF4444' },
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
                    tooltip: { backgroundColor: bgColor, titleColor: goldColor, bodyColor: '#fff', borderColor: goldColor, borderWidth: 1, padding: 10, rtl: true }
                }
            }
        });
    }

    // === Cases by Type (Doughnut) ===
    const typeCtx = document.getElementById('casesTypeChart');
    if (typeCtx) {
        const typeData = @json($casesByType);
        const typeColors = ['var(--accent)', '#22C55E', '#3B82F6', '#8B5CF6', '#EF4444', 'var(--accent)', '#06B6D4', '#EC4899'];
        const labels = @json($casesByType);
        const keys = Object.keys(labels);
        new Chart(typeCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: keys,
                datasets: [{
                    data: keys.map(k => labels[k]),
                    backgroundColor: keys.map((_, i) => typeColors[i % typeColors.length] + 'cc'),
                    borderColor: bgColor, borderWidth: 3,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: tickColor, padding: 10, font: { size: 10 }, usePointStyle: true, pointStyleWidth: 8 } },
                    tooltip: { backgroundColor: bgColor, titleColor: goldColor, bodyColor: '#fff', borderColor: goldColor, borderWidth: 1, padding: 10, rtl: true }
                }
            }
        });
    }

    // === Radar Chart (Team Comparison) ===
    const radarCtx = document.getElementById('radarChart');
    if (radarCtx) {
        const radarData = @json($efficiencyData);
        const radarColors = ['var(--accent)', '#22C55E', '#3B82F6', '#8B5CF6', 'var(--accent)', '#EF4444'];
        new Chart(radarCtx.getContext('2d'), {
            type: 'radar',
            data: {
                labels: ['{{ __("app.success_rate") }}', '{{ __("app.task_completion") }}', '{{ __("app.deadline_compliance") }}', '{{ __("app.productivity") }}'],
                datasets: radarData.map((d, i) => ({
                    label: d.user.name,
                    data: [d.success_rate, d.task_completion, d.deadline_compliance, Math.min(d.productivity, 100)],
                    borderColor: radarColors[i % radarColors.length],
                    backgroundColor: radarColors[i % radarColors.length] + '22',
                    pointBackgroundColor: radarColors[i % radarColors.length],
                    pointBorderColor: radarColors[i % radarColors.length],
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
                    tooltip: { backgroundColor: bgColor, titleColor: goldColor, bodyColor: '#fff', borderColor: goldColor, borderWidth: 1, padding: 10, rtl: true }
                }
            }
        });
    }
});
</script>
@endpush

@endsection
