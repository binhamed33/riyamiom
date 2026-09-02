@extends('layouts.app')

@section('title', __('app.page_tasks'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900">{{ __('app.tasks') }}</h2>
        <a href="{{ route('tasks.create') }}"
           class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('app.new_task') }}
        </a>
    </div>

    @php
        $activeFilters = collect(['status', 'priority', 'assigned_to', 'due', 'case_id', 'search'])
            ->filter(fn ($k) => filled(request($k)))->count();
        $selCls = 'w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40';
        $tabs = [
            ['label' => __('app.tab_all'), 'params' => [], 'active' => !request()->filled('due') && !request()->filled('status')],
            ['label' => __('app.tab_today'), 'params' => ['due' => 'today'], 'active' => request('due') === 'today'],
            ['label' => __('app.tab_upcoming'), 'params' => ['due' => 'upcoming'], 'active' => request('due') === 'upcoming'],
            ['label' => __('app.tab_overdue'), 'params' => ['due' => 'overdue'], 'active' => request('due') === 'overdue'],
            ['label' => __('app.tab_completed'), 'params' => ['status' => 'completed'], 'active' => request('status') === 'completed' && !request()->filled('due')],
        ];
    @endphp

    {{-- Quick tabs --}}
    <div class="flex flex-wrap items-center gap-2">
        @foreach($tabs as $tab)
            <a href="{{ route('tasks.index', $tab['params']) }}"
               class="md-tab inline-flex items-center px-4 py-2 rounded-full text-xs font-bold border transition-colors {{ $tab['active'] ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-200 hover:border-gold/40 hover:text-gold-dark' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>

    <x-filter-panel :action="route('tasks.index')" :count="$activeFilters" :clear-url="route('tasks.index')">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.status') }}</label>
                <select name="status" class="{{ $selCls }}">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('app.status_pending') }}</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>{{ __('app.status_in_progress') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('app.status_completed') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.priority') }}</label>
                <select name="priority" class="{{ $selCls }}">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>{{ __('app.priority_low') }}</option>
                    <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>{{ __('app.priority_medium') }}</option>
                    <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>{{ __('app.priority_high') }}</option>
                    <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>{{ __('app.priority_urgent') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.task_assigned_to') }}</label>
                <select data-no-create name="assigned_to" class="ts {{ $selCls }}">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" {{ request('assigned_to') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.due_filter') }}</label>
                <select name="due" class="{{ $selCls }}">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="today" {{ request('due') === 'today' ? 'selected' : '' }}>{{ __('app.due_today') }}</option>
                    <option value="week" {{ request('due') === 'week' ? 'selected' : '' }}>{{ __('app.due_week') }}</option>
                    <option value="upcoming" {{ request('due') === 'upcoming' ? 'selected' : '' }}>{{ __('app.due_upcoming') }}</option>
                    <option value="overdue" {{ request('due') === 'overdue' ? 'selected' : '' }}>{{ __('app.due_overdue') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.case') }}</label>
                <select data-no-create name="case_id" class="ts {{ $selCls }}">
                    <option value="">{{ __('app.all_cases') }}</option>
                    @foreach($filterCases as $fc)
                        <option value="{{ $fc->id }}" {{ request('case_id') == $fc->id ? 'selected' : '' }}>{{ $fc->office_case_number }} — {{ \Illuminate\Support\Str::limit($fc->title, 35) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}" class="{{ $selCls }}">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-primary hover:bg-primary-dark text-white px-5 py-2 rounded-lg font-semibold transition-colors text-sm">
                    {{ __('app.filter') }}
                </button>
            </div>
        </div>
    </x-filter-panel>
    @php
        $__sortOptions = ['created' => __('app.sort_newest'), 'due' => __('app.due_date'), 'priority' => __('app.priority'), 'status' => __('app.status'), 'title' => __('app.title'), 'case' => __('app.case'), 'assignee' => __('app.task_assigned_to')];
        $__sortDefault = 'created';
    @endphp
    {{-- منطقةُ الاستبدال: نقرةُ الترتيب تجلب هذا وحدَه، فلا تُرسَم
         القائمةُ الجانبيةُ من جديد ولا تقفز. --}}
    <div data-live="tasks">

    {{-- §3: المنجز خلف زرّه + §4: الترتيب --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <x-sort-bar :options="$__sortOptions" :default="$__sortDefault" :default-dir="$__sortDefaultDir ?? 'desc'" />
        <a href="{{ request()->fullUrlWithQuery(['done' => ($done ?? false) ? null : 1, 'page' => null]) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold border transition {{ ($done ?? false) ? 'bg-gold/12 text-gold-dark border-gold/25' : 'bg-white text-gray-400 border-gray-200 hover:text-gray-600' }}">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ ($done ?? false) ? __('app.show_active') : __('app.show_done') . ' (' . ($doneCount ?? 0) . ')' }}
        </a>
    </div>


    {{-- الهاتف: بطاقات بدل جدول يُسحب أفقياً --}}
    <div class="md:hidden bg-white rounded-xl border border-gray-200 overflow-hidden">
        @forelse ($tasks as $task)
            @php
                $tStatus = ['pending' => 'bg-gray-100 text-gray-800', 'in_progress' => 'bg-blue-100 text-blue-700', 'completed' => 'bg-green-100 text-green-700'];
                $tPrio = ['low' => 'bg-gray-100 text-gray-600', 'medium' => 'bg-blue-100 text-blue-700', 'high' => 'bg-orange-100 text-orange-700', 'urgent' => 'bg-red-100 text-red-700'];
                $overdue = $task->due_date && $task->status !== 'completed' && $task->due_date->isPast();
            @endphp
            <x-list-card :url="route('tasks.show', $task)" :title="$task->title" :subtitle="$task->case->title ?? null">
                <x-slot:badges>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $tStatus[$task->status] ?? 'bg-gray-100 text-gray-700' }}">
                        {{ __('app.status_' . $task->status) }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $tPrio[$task->priority] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ __('app.priority_' . $task->priority) }}
                    </span>
                    @if($overdue)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">{{ __('app.due_overdue') }}</span>
                    @endif
                </x-slot:badges>
                <x-slot:meta>
                    <x-list-meta :label="__('app.task_assigned_to')">{{ $task->assignee->name ?? '—' }}</x-list-meta>
                    <x-list-meta :label="__('app.due_date')">{{ $task->due_date?->format('Y-m-d') ?? '—' }}</x-list-meta>
                </x-slot:meta>
            </x-list-card>
        @empty
            <x-empty-state :title="__('app.no_tasks')" :hint="__('app.no_tasks_hint')" icon="tasks"
                :action-url="route('tasks.create')" :action-label="__('app.new_task')"
                :filtered="($activeFilters ?? 0) > 0" :clear-url="url()->current()" compact />
        @endforelse
    </div>

    <div class="hidden md:block bg-white rounded-xl border border-gray-200">
        <div class="overflow-x-auto md-scroll-x">
        <table class="w-full text-sm">
            <thead class=" text-gray-900">
                <tr>
                    @php $__s = request('sort', 'created'); $__d = request('dir', 'desc'); @endphp
                    <x-th-sort key="title" :label="__('app.title')" :sort="$__s" :dir="$__d" />
                    <x-th-sort key="case" :label="__('app.case')" :sort="$__s" :dir="$__d" />
                    <x-th-sort key="assignee" :label="__('app.task_assigned_to')" :sort="$__s" :dir="$__d" />
                    <x-th-sort key="status" :label="__('app.status')" :sort="$__s" :dir="$__d" />
                    <x-th-sort key="priority" :label="__('app.priority')" :sort="$__s" :dir="$__d" />
                    <x-th-sort key="due" :label="__('app.due_date')" :sort="$__s" :dir="$__d" />
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tasks as $task)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 font-medium text-gray-900">
                            {{ $task->title }}
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            {{ $task->case->title ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            {{ $task->assignee->name ?? '—' }}
                        </td>
                        <td class="px-6 py-4">
                            @switch($task->status)
                                @case('pending')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                                        {{ __('app.status_pending') }}
                                    </span>
                                    @break
                                @case('in_progress')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                        {{ __('app.status_in_progress') }}
                                    </span>
                                    @break
                                @case('completed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                        {{ __('app.status_completed') }}
                                    </span>
                                    @break
                                @case('cancelled')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                        {{ __('app.status_cancelled') }}
                                    </span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-6 py-4">
                            @switch($task->priority)
                                @case('low')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                        {{ __('app.priority_low') }}
                                    </span>
                                    @break
                                @case('medium')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                        {{ __('app.priority_medium') }}
                                    </span>
                                    @break
                                @case('high')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">
                                        {{ __('app.priority_high') }}
                                    </span>
                                    @break
                                @case('urgent')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                        {{ __('app.priority_urgent') }}
                                    </span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            {{ $task->due_date ? $task->due_date->format('Y-m-d') : '—' }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1">
                                <a href="{{ route('tasks.show', $task) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 transition-colors" title="{{ __('app.view') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </a>
                                <a href="{{ route('tasks.edit', $task) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gold/12 text-gold-dark hover:bg-gold/15 transition-colors" title="{{ __('app.edit') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                </a>
                                <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="contents" data-confirm="{{ __("app.confirm_delete") }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors" title="{{ __('app.delete') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                        <tr>
                            <td colspan="7" class="p-0">
                                <x-empty-state
                                    :title="__('app.no_tasks')"
                                    :hint="__('app.no_tasks_hint')"
                                    icon="tasks"
                                    :action-url="route('tasks.create')"
                                    :action-label="__('app.new_task')"
                                    :filtered="($activeFilters ?? 0) > 0"
                                    :clear-url="url()->current()" />
                            </td>
                        </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    @if ($tasks->hasPages())
        <div class="mt-4">
            <div data-live-nav>{{ $tasks->links() }}</div>
        </div>
    @endif

    </div>{{-- /منطقةُ الاستبدال --}}
</div>
@endsection
