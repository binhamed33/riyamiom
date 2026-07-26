@extends('layouts.app')

@section('title', __('app.page_tasks'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-white">{{ __('app.tasks') }}</h2>
        <a href="{{ route('tasks.create') }}"
           class="inline-flex items-center gap-2 bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('app.new_task') }}
        </a>
    </div>

    <form method="GET" action="{{ route('tasks.index') }}" class="bg-navy rounded-xl border border-white/10 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1">{{ __('app.status') }}</label>
                <select name="status" class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2 text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('app.status_pending') }}</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>{{ __('app.status_in_progress') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('app.status_completed') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1">{{ __('app.priority') }}</label>
                <select name="priority" class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2 text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>{{ __('app.priority_low') }}</option>
                    <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>{{ __('app.priority_medium') }}</option>
                    <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>{{ __('app.priority_high') }}</option>
                    <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>{{ __('app.priority_urgent') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1">{{ __('app.task_assigned_to') }}</label>
                <select name="assigned_to" class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2 text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" {{ request('assigned_to') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="bg-white/5 border border-gold/30 text-gold px-5 py-2 rounded-lg hover:bg-gold/10 transition text-sm">
                    {{ __('app.filter') }}
                </button>
                <a href="{{ route('tasks.index') }}" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                    {{ __('app.reset') }}
                </a>
            </div>
        </div>
    </form>

    <div class="bg-navy rounded-xl border border-white/10">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class=" text-white">
                <tr>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.title') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.case') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.task_assigned_to') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.status') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.priority') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.due_date') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/10">
                @forelse ($tasks as $task)
                    <tr class="hover:bg-white/5 transition-colors">
                        <td class="px-6 py-4 font-medium text-white">
                            {{ $task->title }}
                        </td>
                        <td class="px-6 py-4 text-white/60">
                            {{ $task->case->title ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-white/60">
                            {{ $task->assignee->name ?? '—' }}
                        </td>
                        <td class="px-6 py-4">
                            @switch($task->status)
                                @case('pending')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/10 text-white/80">
                                        {{ __('app.status_pending') }}
                                    </span>
                                    @break
                                @case('in_progress')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-400">
                                        {{ __('app.status_in_progress') }}
                                    </span>
                                    @break
                                @case('completed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-500/20 text-green-400">
                                        {{ __('app.status_completed') }}
                                    </span>
                                    @break
                                @case('cancelled')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-500/20 text-red-400">
                                        {{ __('app.status_cancelled') }}
                                    </span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-6 py-4">
                            @switch($task->priority)
                                @case('low')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/10 text-white/70">
                                        {{ __('app.priority_low') }}
                                    </span>
                                    @break
                                @case('medium')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-500/20 text-yellow-400">
                                        {{ __('app.priority_medium') }}
                                    </span>
                                    @break
                                @case('high')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-500/20 text-orange-400">
                                        {{ __('app.priority_high') }}
                                    </span>
                                    @break
                                @case('urgent')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-500/20 text-red-400">
                                        {{ __('app.priority_urgent') }}
                                    </span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-6 py-4 text-white/60">
                            {{ $task->due_date ? $task->due_date->format('Y-m-d') : '—' }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1">
                                <a href="{{ route('tasks.show', $task) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition-colors" title="{{ __('app.view') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </a>
                                <a href="{{ route('tasks.edit', $task) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[#C9A55A]/10 text-[#C9A55A] hover:bg-[#C9A55A]/20 transition-colors" title="{{ __('app.edit') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                </a>
                                <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="contents" onsubmit="return confirm('{{ __("app.confirm_delete") }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors" title="{{ __('app.delete') }}">
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
                        <td colspan="7" class="px-6 py-12 text-center text-white/50">
                            {{ __('app.no_tasks') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    @if ($tasks->hasPages())
        <div class="mt-4">
            {{ $tasks->links() }}
        </div>
    @endif
</div>
@endsection
