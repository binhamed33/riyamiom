@extends('layouts.app')

@section('title', __('app.evaluations'))

@section('content')
    {{-- تقييم الأداء جزء من الموارد البشرية — رابط الرجوع يثبّت الانتماء --}}
    <a href="{{ route('hr.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-400 hover:text-gold-dark transition mb-3">
        <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        الموارد البشرية
    </a>

<div class="max-w-6xl mx-auto space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gold-dark flex items-center gap-2">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 21h8m-4-4v4m-7-4h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                {{ __('app.evaluations') }}
            </h1>
        </div>

        {{-- Period Tabs --}}
        <div class="flex bg-white rounded-xl border border-gray-200 p-1">
            @foreach(['all' => __('app.evaluation_all'), 'month' => __('app.evaluation_this_month'), 'last_month' => __('app.evaluation_last_month')] as $value => $label)
                <a href="{{ route('evaluations.index', ['period' => $value]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $period === $value ? 'bg-gold text-[#111827]' : 'text-gray-500 hover:text-gold-dark' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- How it's calculated --}}
    <div class="bg-white rounded-xl border border-gold/15 p-5">
        <p class="text-gray-600 text-sm">{{ __('app.evaluations_desc') }}</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4 text-sm">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p class="font-bold text-blue-800">{{ __('app.evaluation_cases_weight') }}</p>
                <p class="text-blue-600 text-xs mt-1">{{ __('app.evaluation_cases_weight_desc') }}</p>
            </div>
            <div class="bg-gold/10 border border-gold/15 rounded-lg p-3">
                <p class="font-bold text-gold-dark">{{ __('app.evaluation_activity_weight') }}</p>
                <p class="text-gold-dark text-xs mt-1">{{ __('app.evaluation_activity_weight_desc') }}</p>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                <p class="font-bold text-green-800">{{ __('app.evaluation_quality_weight') }}</p>
                <p class="text-green-600 text-xs mt-1">{{ __('app.evaluation_quality_weight_desc') }}</p>
            </div>
        </div>
    </div>

    {{-- Rankings --}}
    <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
        @if(count($rows) === 0)
            {{-- «لا توجد بيانات» لا تقول للمستخدم ما الذي ينقص ولا ماذا يفعل --}}
            <div class="text-center px-6 py-14">
                <svg class="w-10 h-10 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 20h18M7 20V10m5 10V4m5 16v-7"/>
                </svg>
                <p class="text-gray-700 font-semibold text-sm">{{ __('app.empty_evaluations_title') }}</p>
                <p class="text-gray-400 text-xs mt-1.5 max-w-md mx-auto leading-relaxed">{{ __('app.empty_evaluations_body') }}</p>
            </div>
        @else
            @php
                $__sortOptions = ['score' => __('app.evaluation_rank'), 'name' => __('app.name'), 'cases' => __('app.cases'), 'tasks' => __('app.tasks')];
                $__sortDefault = 'score';
            @endphp

            {{-- §4: الترتيب --}}
            <div class="flex items-center gap-3 flex-wrap">
                <x-sort-bar :options="$__sortOptions" :default="$__sortDefault" :default-dir="$__sortDefaultDir ?? 'desc'" />
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="px-3 py-3 text-gold-dark font-bold text-xs">{{ __('app.evaluation_rank') }}</th>
                            <th class="px-3 py-3 text-gold-dark font-bold text-xs">{{ __('app.evaluation_employee') }}</th>
                            <th class="px-3 py-3 text-gold-dark font-bold text-xs">{{ __('app.evaluation_cases') }}</th>
                            <th class="px-3 py-3 text-gold-dark font-bold text-xs">{{ __('app.evaluation_sessions') }}</th>
                            <th class="px-3 py-3 text-gold-dark font-bold text-xs">{{ __('app.evaluation_tasks') }}</th>
                            <th class="px-3 py-3 text-gold-dark font-bold text-xs">{{ __('app.evaluation_documents') }}</th>
                            <th class="px-3 py-3 text-gold-dark font-bold text-xs">{{ __('app.evaluation_activity') }}</th>
                            <th class="px-3 py-3 text-gold-dark font-bold text-xs min-w-[140px]">{{ __('app.evaluation_score') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            @php
                                $m = $row['metrics'];
                                $gradeColors = [
                                    'excellent' => 'bg-green-100 text-green-700',
                                    'very_good' => 'bg-teal-100 text-teal-700',
                                    'good' => 'bg-blue-100 text-blue-700',
                                    'needs_improvement' => 'bg-red-100 text-red-700',
                                ];
                                $barColors = [
                                    'excellent' => 'bg-green-500',
                                    'very_good' => 'bg-teal-500',
                                    'good' => 'bg-blue-500',
                                    'needs_improvement' => 'bg-red-500',
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                                        {{ $row['rank'] === 1 ? 'bg-gold/12 text-gold-dark' : ($row['rank'] <= 3 ? 'bg-gray-200 text-gray-700' : 'bg-gray-100 text-gray-500') }}">
                                        {{ $row['rank'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="text-gray-900 font-semibold text-sm">{{ $row['name'] }}</p>
                                    <p class="text-gray-400 text-xs">{{ __("app.{$row['role']}") }}</p>
                                </td>
                                <td class="px-3 py-3 text-gray-600 text-xs">
                                    {{ $m['cases_total'] }}
                                    <span class="text-gray-400">({{ $m['cases_closed'] }} {{ __('app.evaluation_closed') }})</span>
                                </td>
                                <td class="px-3 py-3 text-gray-600 text-xs">
                                    {{ $m['sessions'] }}
                                    @if($m['session_reports'] > 0)
                                        <span class="text-gray-400">({{ $m['session_reports'] }} {{ __('app.evaluation_decisions') }})</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-gray-600 text-xs">
                                    {{ $m['tasks_completed'] }}/{{ $m['tasks_total'] }}
                                    @if($m['tasks_on_time'] > 0)
                                        <span class="text-gray-400">({{ $m['tasks_on_time'] }} {{ __('app.evaluation_on_time') }})</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-gray-600 text-xs">{{ $m['documents'] }}</td>
                                <td class="px-3 py-3 text-gray-600 text-xs">{{ $m['audit_actions'] }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full transition-all {{ $barColors[$m['grade']] }}" style="width: {{ $m['score'] }}%"></div>
                                        </div>
                                        <span class="font-bold text-sm text-gray-900 whitespace-nowrap">{{ $m['score'] }}</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full whitespace-nowrap {{ $gradeColors[$m['grade']] }}">
                                            {{ __("app.grade_{$m['grade']}") }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
