@extends('layouts.app')

@section('title', __('app.page_edit_task'))

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">{{ __('app.page_edit_task') }}</h2>
        <a href="{{ route('tasks.index') }}" class="text-gray-500 hover:text-gray-800 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="{{ route('tasks.update', $task) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.title') }} <span class="text-red-500">*</span></label>
                <input type="text" id="title" name="title" value="{{ old('title', $task->title) }}"
                       class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('title') border-red-500 @enderror"
                       placeholder="{{ __('app.task_title_placeholder') }}" required>
                @error('title')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.description') }}</label>
                <textarea id="description" name="description" rows="4"
                          class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('description') border-red-500 @enderror"
                          placeholder="{{ __('app.task_description_placeholder') }}">{{ old('description', $task->description) }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.case') }}</label>
                    <select id="case_id" name="case_id" class="ts w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('case_id') border-red-500 @enderror">
                        <option value="">{{ __('app.no_case') }}</option>
                        @foreach($cases as $case)
                            <option value="{{ $case->id }}" @selected(old('case_id', $task->case_id) == $case->id)>
                                #{{ $case->office_case_number }} - {{ $case->case_number ?? '' }} - {{ $case->client?->phone ?? '' }} - {{ $case->client?->name ?? '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('case_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="assigned_to" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.task_assigned_to') }} <span class="text-red-500">*</span></label>
                    <select id="assigned_to" name="assigned_to" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('assigned_to') border-red-500 @enderror" required>
                        <option value="">{{ __('app.choose_assignee') }}</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(old('assigned_to', $task->assigned_to) == $user->id)>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('assigned_to')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.priority') }} <span class="text-red-500">*</span></label>
                    <select id="priority" name="priority" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('priority') border-red-500 @enderror" required>
                        <option value="low" {{ old('priority', $task->priority) === 'low' ? 'selected' : '' }}>{{ __('app.priority_low') }}</option>
                        <option value="medium" {{ old('priority', $task->priority) === 'medium' ? 'selected' : '' }}>{{ __('app.priority_medium') }}</option>
                        <option value="high" {{ old('priority', $task->priority) === 'high' ? 'selected' : '' }}>{{ __('app.priority_high') }}</option>
                        <option value="urgent" {{ old('priority', $task->priority) === 'urgent' ? 'selected' : '' }}>{{ __('app.priority_urgent') }}</option>
                    </select>
                    @error('priority')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="due_date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.due_date') }}</label>
                    <input type="date" id="due_date" name="due_date"
                           value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}"
                           class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('due_date') border-red-500 @enderror">
                    @error('due_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.status') }} <span class="text-red-500">*</span></label>
                <select id="status" name="status" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('status') border-red-500 @enderror" required>
                    <option value="pending" {{ old('status', $task->status) === 'pending' ? 'selected' : '' }}>{{ __('app.status_pending') }}</option>
                    <option value="in_progress" {{ old('status', $task->status) === 'in_progress' ? 'selected' : '' }}>{{ __('app.status_in_progress') }}</option>
                    <option value="completed" {{ old('status', $task->status) === 'completed' ? 'selected' : '' }}>{{ __('app.status_completed') }}</option>
                    <option value="cancelled" {{ old('status', $task->status) === 'cancelled' ? 'selected' : '' }}>{{ __('app.status_cancelled') }}</option>
                </select>
                @error('status')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3 pt-4">
                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                    {{ __('app.save_changes') }}
                </button>
                <a href="{{ route('tasks.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                    {{ __('app.cancel') }}
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
