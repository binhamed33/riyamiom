@extends('layouts.app')

@section('title', __('app.page_edit_session'))

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-white">{{ __('app.page_edit_session') }}</h2>
        <a href="{{ route('sessions.index') }}" class="text-white/50 hover:text-white/80 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </a>
    </div>

    <div class="bg-navy rounded-xl border border-white/10 p-6">
        <form method="POST" action="{{ route('sessions.update', $session) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="case_id" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.case') }} <span class="text-red-500">*</span></label>
                <select id="case_id" name="case_id" class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('case_id') border-red-500 @enderror" required>
                    <option value="">{{ __('app.choose_case') }}</option>
                    @foreach ($cases as $case)
                        <option value="{{ $case->id }}" {{ old('case_id', $session->case_id) == $case->id ? 'selected' : '' }}>
                            {{ $case->title }}
                        </option>
                    @endforeach
                </select>
                @error('case_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="date" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.session_datetime') }} <span class="text-red-500">*</span></label>
                <input type="datetime-local" id="date" name="date"
                       value="{{ old('date', $session->date->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('date') border-red-500 @enderror" required>
                @error('date')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="location" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.location') }} <span class="text-red-500">*</span></label>
                <input type="text" id="location" name="location" value="{{ old('location', $session->location) }}"
                       class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('location') border-red-500 @enderror"
                       placeholder="{{ __('app.session_location_placeholder') }}" required>
                @error('location')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.status') }} <span class="text-red-500">*</span></label>
                <select id="status" name="status" class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('status') border-red-500 @enderror" required>
                    <option value="upcoming" {{ old('status', $session->status) === 'upcoming' ? 'selected' : '' }}>{{ __('app.status_upcoming') }}</option>
                    <option value="completed" {{ old('status', $session->status) === 'completed' ? 'selected' : '' }}>{{ __('app.status_completed') }}</option>
                    <option value="postponed" {{ old('status', $session->status) === 'postponed' ? 'selected' : '' }}>{{ __('app.status_postponed') }}</option>
                    <option value="cancelled" {{ old('status', $session->status) === 'cancelled' ? 'selected' : '' }}>{{ __('app.status_cancelled') }}</option>
                </select>
                @error('status')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.notes') }}</label>
                <textarea id="notes" name="notes" rows="4"
                          class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('notes') border-red-500 @enderror"
                          placeholder="{{ __('app.session_notes_placeholder') }}">{{ old('notes', $session->notes) }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3 pt-4">
                <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                    {{ __('app.save_changes') }}
                </button>
                <a href="{{ route('sessions.index') }}" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                    {{ __('app.cancel') }}
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
