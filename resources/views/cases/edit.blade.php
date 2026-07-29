@extends('layouts.app')

@section('title', __('app.page_edit_case'))

@section('content')
<div class="max-w-4xl mx-auto" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-[#C9A55A]">{{ __('app.edit_case') }}: {{ $case->case_number }}</h1>
        <a href="{{ route('cases.show', $case->id) }}" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
            {{ __('app.back') }}
        </a>
    </div>

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-4 mb-6">
            <div class="flex items-center gap-2 mb-2">
                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-red-400 font-bold text-sm">{{ __('app.warning') }}</p>
            </div>
            <ul class="list-disc list-inside text-red-300 text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('cases.update', $case->id) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- People Card (moved to top) --}}
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6 space-y-5">
            <h2 class="text-lg font-bold text-[#C9A55A] border-b border-white/10 pb-3">{{ __('app.related_people') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Client --}}
                <div>
                    <label for="client_id" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_client') }} <span class="text-red-400">*</span></label>
                    <select name="client_id" id="client_id" required
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('client_id') border-red-500/50 @enderror">
                        <option value="">{{ __('app.choose_client') }}</option>
                        @foreach($clients ?? [] as $client)
                            <option value="{{ $client->id }}" {{ old('client_id', $case->client_id) == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                        @endforeach
                    </select>
                    @error('client_id')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Lawyer --}}
                <div>
                    <label for="lawyer_id" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_lawyer') }}</label>
                    <select name="lawyer_id" id="lawyer_id"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('lawyer_id') border-red-500/50 @enderror">
                        <option value="">اختر محامي القضيه</option>
                        @foreach($users ?? [] as $user)
                            <option value="{{ $user->id }}" {{ old('lawyer_id', $case->lawyer_id) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('lawyer_id')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Case Details Card --}}
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6 space-y-5">
            <h2 class="text-lg font-bold text-[#C9A55A] border-b border-white/10 pb-3">{{ __('app.case_details') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Court Case Number --}}
                <div>
                    <label for="case_number" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.court_case_number') }} <span class="text-red-400">*</span></label>
                    <input type="text" name="case_number" id="case_number" value="{{ old('case_number', $case->case_number) }}" required
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('case_number') border-red-500/50 @enderror">
                    @error('case_number')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Office Case Number --}}
                <div>
                    <label for="office_case_number" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.office_case_number') }}</label>
                    <input type="text" name="office_case_number" id="office_case_number" value="{{ old('office_case_number', $case->office_case_number) }}"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('office_case_number') border-red-500/50 @enderror">
                    @error('office_case_number')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Case Type --}}
                <div>
                    <label for="case_type" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_type') }}</label>
                    <select name="case_type" id="case_type"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('case_type') border-red-500/50 @enderror">
                        <option value="">{{ __('app.choose_case_type') }}</option>
                        <option value="مدني" {{ old('case_type', $case->case_type) === 'مدني' ? 'selected' : '' }}>مدني</option>
                        <option value="تجاري" {{ old('case_type', $case->case_type) === 'تجاري' ? 'selected' : '' }}>تجاري</option>
                        <option value="عمالي" {{ old('case_type', $case->case_type) === 'عمالي' ? 'selected' : '' }}>عمالي</option>
                        <option value="أحوال شخصية" {{ old('case_type', $case->case_type) === 'أحوال شخصية' ? 'selected' : '' }}>أحوال شخصية</option>
                        <option value="استثمار" {{ old('case_type', $case->case_type) === 'استثمار' ? 'selected' : '' }}>استثمار</option>
                        <option value="تنفيذ" {{ old('case_type', $case->case_type) === 'تنفيذ' ? 'selected' : '' }}>تنفيذ</option>
                    </select>
                    @error('case_type')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Court --}}
                <div x-data="{ manual: false }">
                    <div class="flex items-center gap-2 mb-1.5">
                        <label for="court" class="block text-sm font-medium text-white/30">{{ __('app.case_court') }} <span class="text-red-400">*</span></label>
                        <label class="flex items-center gap-1.5 cursor-pointer ml-auto">
                            <input type="checkbox" x-model="manual" class="rounded border-white/20 bg-white/5 text-[#C9A55A] focus:ring-[#C9A55A]/50">
                            <span class="text-xs text-white/40">{{ __('app.manual_entry') }}</span>
                        </label>
                    </div>
                    <template x-if="!manual">
                        @include('cases._court_select', ['selected' => old('court', $case->court)])
                    </template>
                    <template x-if="manual">
                        <input type="text" name="court" id="court" value="{{ old('court', $case->court) }}"
                            class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('court') border-red-500/50 @enderror">
                    </template>
                    @error('court')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Title (Court case number as name) --}}
            <div>
                <label for="title" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_title_number') }}</label>
                <input type="text" name="title" id="title" value="{{ old('title', $case->title) }}"
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('title') border-red-500/50 @enderror">
                @error('title')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Description --}}
            <div>
                <label for="description" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_description') }}</label>
                <textarea name="description" id="description" rows="4"
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] resize-y @error('description') border-red-500/50 @enderror">{{ old('description', $case->description) }}</textarea>
                @error('description')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Opponent Data Card --}}
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6 space-y-5">
            <h2 class="text-lg font-bold text-[#C9A55A] border-b border-white/10 pb-3">{{ __('app.opponent_data') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="opponent" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.opponent_name') }}</label>
                    <input type="text" name="opponent" id="opponent" value="{{ old('opponent', $case->opponent) }}"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('opponent') border-red-500/50 @enderror">
                    @error('opponent')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="opponent_phone" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.opponent_phone') }}</label>
                    <input type="text" name="opponent_phone" id="opponent_phone" value="{{ old('opponent_phone', $case->opponent_phone) }}"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('opponent_phone') border-red-500/50 @enderror"
                        placeholder="{{ __('app.opponent_phone_placeholder') }}">
                    @error('opponent_phone')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="opponent_address" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.opponent_address') }}</label>
                    <input type="text" name="opponent_address" id="opponent_address" value="{{ old('opponent_address', $case->opponent_address) }}"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('opponent_address') border-red-500/50 @enderror"
                        placeholder="{{ __('app.opponent_address_placeholder') }}">
                    @error('opponent_address')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Status & Priority Card --}}
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6 space-y-5">
            <h2 class="text-lg font-bold text-[#C9A55A] border-b border-white/10 pb-3">{{ __('app.case_status_priority') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Status --}}
                <div>
                    <label for="status" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.status') }}</label>
                    <select name="status" id="status"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('status') border-red-500/50 @enderror">
                        <option value="active" {{ old('status', $case->status) === 'active' ? 'selected' : '' }}>{{ __('app.status_active') }}</option>
                        <option value="pending" {{ old('status', $case->status) === 'pending' ? 'selected' : '' }}>{{ __('app.status_pending') }}</option>
                        <option value="overdue" {{ old('status', $case->status) === 'overdue' ? 'selected' : '' }}>{{ __('app.status_overdue') }}</option>
                        <option value="closed" {{ old('status', $case->status) === 'closed' ? 'selected' : '' }}>{{ __('app.status_closed') }}</option>
                        <option value="won" {{ old('status', $case->status) === 'won' ? 'selected' : '' }}>{{ __('app.status_won') }}</option>
                        <option value="lost" {{ old('status', $case->status) === 'lost' ? 'selected' : '' }}>{{ __('app.status_lost') }}</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Priority --}}
                <div>
                    <label for="priority" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.priority') }}</label>
                    <select name="priority" id="priority"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('priority') border-red-500/50 @enderror">
                        <option value="low" {{ old('priority', $case->priority) === 'low' ? 'selected' : '' }}>{{ __('app.priority_low') }}</option>
                        <option value="medium" {{ old('priority', $case->priority) === 'medium' ? 'selected' : '' }}>{{ __('app.priority_medium') }}</option>
                        <option value="high" {{ old('priority', $case->priority) === 'high' ? 'selected' : '' }}>{{ __('app.priority_high') }}</option>
                        <option value="urgent" {{ old('priority', $case->priority) === 'urgent' ? 'selected' : '' }}>{{ __('app.priority_urgent') }}</option>
                    </select>
                    @error('priority')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Dates Card --}}
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6 space-y-5">
            <h2 class="text-lg font-bold text-[#C9A55A] border-b border-white/10 pb-3">{{ __('app.case_dates') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Opened At --}}
                <div>
                    <label for="opened_at" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.opened_date') }}</label>
                    <input type="date" name="opened_at" id="opened_at" value="{{ old('opened_at', $case->opened_at?->format('Y-m-d')) }}"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('opened_at') border-red-500/50 @enderror">
                    @error('opened_at')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Next Session Date --}}
                <div>
                    <label for="next_date" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.next_session_date') }}</label>
                    <input type="date" name="next_date" id="next_date" value="{{ old('next_date', $case->next_date?->format('Y-m-d')) }}"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('next_date') border-red-500/50 @enderror">
                    @error('next_date')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3">
            <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                {{ __('app.save_changes') }}
            </button>
            <a href="{{ route('cases.show', $case->id) }}" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                {{ __('app.cancel') }}
            </a>
        </div>
    </form>
</div>
@endsection
