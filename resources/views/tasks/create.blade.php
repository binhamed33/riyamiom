@extends('layouts.app')

@section('title', __('app.page_add_task'))

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-white">{{ __('app.page_add_task') }}</h2>
        <a href="{{ route('tasks.index') }}" class="text-white/50 hover:text-white/80 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </a>
    </div>

    <div class="bg-navy rounded-xl border border-white/10 p-6">
        <form method="POST" action="{{ route('tasks.store') }}" class="space-y-5">
            @csrf

            <div>
                <label for="title" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.title') }} <span class="text-red-500">*</span></label>
                <input type="text" id="title" name="title" value="{{ old('title') }}"
                       class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('title') border-red-500 @enderror"
                       placeholder="{{ __('app.task_title_placeholder') }}" required>
                @error('title')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.description') }}</label>
                <textarea id="description" name="description" rows="4"
                          class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('description') border-red-500 @enderror"
                          placeholder="{{ __('app.task_description_placeholder') }}">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="case_id" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.case') }}</label>
                    <select id="case_id" name="case_id" class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('case_id') border-red-500 @enderror">
                        <option value="">{{ __('app.no_case') }}</option>
                        @foreach ($cases as $case)
                            <option value="{{ $case->id }}" {{ old('case_id') == $case->id ? 'selected' : '' }}>
                                {{ $case->title }}
                            </option>
                        @endforeach
                    </select>
                    @error('case_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="assigned_to" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.task_assigned_to') }} <span class="text-red-500">*</span></label>
                    <select id="assigned_to" name="assigned_to" class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('assigned_to') border-red-500 @enderror" required>
                        <option value="">{{ __('app.choose_assignee') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ old('assigned_to') == $user->id ? 'selected' : '' }}>
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
                    <label for="priority" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.priority') }} <span class="text-red-500">*</span></label>
                    <select id="priority" name="priority" class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('priority') border-red-500 @enderror" required>
                        <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>{{ __('app.priority_low') }}</option>
                        <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>{{ __('app.priority_medium') }}</option>
                        <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>{{ __('app.priority_high') }}</option>
                        <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>{{ __('app.priority_urgent') }}</option>
                    </select>
                    @error('priority')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="due_date" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.due_date') }}</label>
                    <input type="date" id="due_date" name="due_date" value="{{ old('due_date') }}"
                           class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('due_date') border-red-500 @enderror">
                    @error('due_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.status') }} <span class="text-red-500">*</span></label>
                <select id="status" name="status" class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('status') border-red-500 @enderror" required>
                    <option value="pending" {{ old('status', 'pending') === 'pending' ? 'selected' : '' }}>{{ __('app.status_pending') }}</option>
                    <option value="in_progress" {{ old('status') === 'in_progress' ? 'selected' : '' }}>{{ __('app.status_in_progress') }}</option>
                    <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>{{ __('app.status_completed') }}</option>
                </select>
                @error('status')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3 pt-4">
                <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                    {{ __('app.save') }}
                </button>
                <a href="{{ route('tasks.index') }}" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                    {{ __('app.cancel') }}
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
