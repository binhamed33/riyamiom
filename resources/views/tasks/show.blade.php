@extends('layouts.app')

@section('title', __('app.page_task_details'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900">{{ __('app.task_details') }}</h2>
        <div class="flex items-center gap-2">
            <a href="{{ route('tasks.edit', $task) }}"
               class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                {{ __('app.edit') }}
            </a>
            <a href="{{ route('tasks.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors text-sm flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ __('app.back') }}
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">{{ $task->title }}</h3>
            <div class="flex items-center gap-2">
                @switch($task->status)
                    @case('pending')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-gray-100 text-gray-800">
                            {{ __('app.status_pending') }}
                        </span>
                        @break
                    @case('in_progress')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-700">
                            {{ __('app.status_in_progress') }}
                        </span>
                        @break
                    @case('completed')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-700">
                            {{ __('app.status_completed') }}
                        </span>
                        @break
                    @case('cancelled')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-red-100 text-red-700">
                            {{ __('app.status_cancelled') }}
                        </span>
                        @break
                @endswitch

                @switch($task->priority)
                    @case('low')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-gray-100 text-gray-700">
                            {{ __('app.priority_low') }}
                        </span>
                        @break
                    @case('medium')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-700">
                            {{ __('app.priority_medium') }}
                        </span>
                        @break
                    @case('high')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-orange-100 text-orange-700">
                            {{ __('app.priority_high') }}
                        </span>
                        @break
                    @case('urgent')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-red-100 text-red-700">
                            {{ __('app.priority_urgent') }}
                        </span>
                        @break
                @endswitch
            </div>
        </div>

        @if ($task->description)
            <div class="mb-6">
                <h4 class="text-sm text-gray-500 mb-2">{{ __('app.description') }}</h4>
                <div class="bg-gray-100 rounded-lg p-4 text-gray-700 leading-relaxed whitespace-pre-wrap">{{ $task->description }}</div>
            </div>
        @endif

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <dt class="text-sm text-gray-500 mb-1">{{ __('app.task_assigned_to') }}</dt>
                <dd class="text-gray-800 font-medium">{{ $task->assignee->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 mb-1">{{ __('app.created_by') }}</dt>
                <dd class="text-gray-800 font-medium">{{ $task->creator->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 mb-1">{{ __('app.due_date') }}</dt>
                <dd class="text-gray-800 font-medium">
                    {{ $task->due_date ? $task->due_date->format('Y-m-d') : '—' }}
                    @if ($task->due_date && $task->due_date->isPast() && $task->status !== 'completed')
                        <span class="text-red-700 text-xs font-semibold mr-2">{{ __('app.overdue') }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 mb-1">{{ __('app.creation_date') }}</dt>
                <dd class="text-gray-800 font-medium">{{ $task->created_at->format('Y-m-d H:i') }}</dd>
            </div>
            @if ($task->completed_at)
                <div>
                    <dt class="text-sm text-gray-500 mb-1">{{ __('app.completion_date') }}</dt>
                    <dd class="text-gray-800 font-medium">{{ \Carbon\Carbon::parse($task->completed_at)->format('Y-m-d H:i') }}</dd>
                </div>
            @endif
        </dl>
    </div>

    @if ($task->case)
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('app.linked_case') }}</h3>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-800 font-medium">{{ $task->case->title }}</p>
                    <p class="text-sm text-gray-500 mt-1">{{ __('app.case_number') }}: {{ $task->case->case_number }}</p>
                </div>
                <a href="{{ route('cases.show', $task->case) }}"
                   class="text-gold-dark hover:text-[#A88D57] font-medium text-sm transition-colors">
                    {{ __('app.view_case') }}
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
            </div>
        </div>
    @endif

    @if ($task->status !== 'completed' && $task->status !== 'cancelled')
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('app.change_status') }}</h3>
            <div class="flex items-center gap-3">
                @if ($task->status === 'pending')
                    <form method="POST" action="{{ route('tasks.update', $task) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="title" value="{{ $task->title }}">
                        <input type="hidden" name="description" value="{{ $task->description }}">
                        <input type="hidden" name="case_id" value="{{ $task->case_id }}">
                        <input type="hidden" name="assigned_to" value="{{ $task->assigned_to }}">
                        <input type="hidden" name="priority" value="{{ $task->priority }}">
                        <input type="hidden" name="due_date" value="{{ $task->due_date?->format('Y-m-d') }}">
                        <input type="hidden" name="status" value="in_progress">
                        <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                            {{ __('app.start_progress') }}
                        </button>
                    </form>
                @endif

                @if ($task->status === 'pending' || $task->status === 'in_progress')
                    <form method="POST" action="{{ route('tasks.update', $task) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="title" value="{{ $task->title }}">
                        <input type="hidden" name="description" value="{{ $task->description }}">
                        <input type="hidden" name="case_id" value="{{ $task->case_id }}">
                        <input type="hidden" name="assigned_to" value="{{ $task->assigned_to }}">
                        <input type="hidden" name="priority" value="{{ $task->priority }}">
                        <input type="hidden" name="due_date" value="{{ $task->due_date?->format('Y-m-d') }}">
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                            {{ __('app.complete_task') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <div class="flex items-center gap-3">
        <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="contents" x-data @submit.prevent="if(confirm('{{ __("app.confirm_delete_task") }}')) $el.submit()">
            @csrf
            @method('DELETE')
            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                {{ __('app.delete_task') }}
            </button>
        </form>
    </div>
</div>
@endsection
