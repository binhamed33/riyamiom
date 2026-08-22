@extends('layouts.app')

@section('title', __('app.page_add_session'))

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">{{ __('app.page_add_session') }}</h2>
        <a href="{{ route('sessions.index') }}" aria-label="{{ __('app.back') }}" class="text-gray-400 hover:text-gray-800 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </a>
    </div>

    @if($selectedCaseId && $selectedCase = $cases->firstWhere('id', $selectedCaseId))
    <div class="bg-gold/10 rounded-xl border border-gold/15 p-4 mb-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
            <div>
                <p class="text-gray-500 text-xs">{{ __('app.case_number') }}</p>
                <p class="text-gray-900 font-medium">{{ $selectedCase->case_number }}</p>
            </div>
            <div>
                <p class="text-gray-500 text-xs">{{ __('app.case_client') }}</p>
                <p class="text-gray-900 font-medium">{{ $selectedCase->client->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-500 text-xs">{{ __('app.case_opponent') }}</p>
                <p class="text-gray-900 font-medium">{{ $selectedCase->opponent ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-500 text-xs">{{ __('app.case_court') }}</p>
                <p class="text-gray-900 font-medium">{{ $selectedCase->court ?? '—' }}</p>
            </div>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="{{ route('sessions.store') }}" class="space-y-5">
            @csrf

            <div>
                <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.case') }} <span class="text-red-500">*</span></label>
                <select id="case_id" name="case_id" class="ts w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('case_id') border-red-500 @enderror" required>
                    <option value="">{{ __('app.choose_case') }}</option>
                    @foreach ($cases as $case)
                        <option value="{{ $case->id }}" {{ old('case_id', $selectedCaseId ?? '') == $case->id ? 'selected' : '' }}>
                            #{{ $case->office_case_number }} - {{ $case->case_number ?? '' }} - {{ $case->client?->phone ?? '' }} - {{ $case->client?->name ?? '' }}
                        </option>
                    @endforeach
                </select>
                @error('case_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.session_datetime') }} <span class="text-red-500">*</span></label>
                <input type="datetime-local" id="date" name="date" value="{{ old('date') }}"
                       class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('date') border-red-500 @enderror" required>
                @error('date')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="location" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.location') }} <span class="text-red-500">*</span></label>
                <input type="text" id="location" name="location" value="{{ old('location') }}"
                       class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('location') border-red-500 @enderror"
                       placeholder="{{ __('app.session_location_placeholder') }}" required>
                @error('location')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.status') }} <span class="text-red-500">*</span></label>
                <select id="status" name="status" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('status') border-red-500 @enderror" required>
                    <option value="upcoming" {{ old('status') === 'upcoming' ? 'selected' : '' }}>{{ __('app.status_upcoming') }}</option>
                    <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>{{ __('app.status_completed') }}</option>
                    <option value="postponed" {{ old('status') === 'postponed' ? 'selected' : '' }}>{{ __('app.status_postponed') }}</option>
                    <option value="cancelled" {{ old('status') === 'cancelled' ? 'selected' : '' }}>{{ __('app.status_cancelled') }}</option>
                </select>
                @error('status')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.notes') }}</label>
                <textarea id="notes" name="notes" rows="4"
                          class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('notes') border-red-500 @enderror"
                          placeholder="{{ __('app.session_notes_placeholder') }}">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="report" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.session_decision') }}</label>
                <textarea id="report" name="report" rows="4"
                          class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('report') border-red-500 @enderror"
                          placeholder="{{ __('app.session_decision_placeholder') }}">{{ old('report') }}</textarea>
                @error('report')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3 pt-4">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                    {{ __('app.save') }}
                </button>
                <a href="{{ route('sessions.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                    {{ __('app.cancel') }}
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
