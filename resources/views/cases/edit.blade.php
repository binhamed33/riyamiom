@extends('layouts.app')

@section('title', __('app.page_edit_case'))

@section('content')
<div class="max-w-4xl mx-auto" dir="rtl">

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

        {{-- Basic Info Card --}}
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6 space-y-5">
            <h2 class="text-lg font-bold text-[#C9A55A] border-b border-white/10 pb-3">{{ __('app.basic_info') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Case Number --}}
                <div>
                    <label for="case_number" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_number') }} <span class="text-red-400">*</span></label>
                    <input type="text" name="case_number" id="case_number" value="{{ old('case_number', $case->case_number) }}" required
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('case_number') border-red-500/50 @enderror">
                    @error('case_number')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Title --}}
                <div>
                    <label for="title" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_title') }} <span class="text-red-400">*</span></label>
                    <input type="text" name="title" id="title" value="{{ old('title', $case->title) }}" required
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('title') border-red-500/50 @enderror">
                    @error('title')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Type --}}
                <div>
                    <label for="type" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_type') }}</label>
                    <input type="text" name="type" id="type" value="{{ old('type', $case->type) }}"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('type') border-red-500/50 @enderror">
                    @error('type')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Opponent --}}
                <div>
                    <label for="opponent" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_opponent') }}</label>
                    <input type="text" name="opponent" id="opponent" value="{{ old('opponent', $case->opponent) }}"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('opponent') border-red-500/50 @enderror">
                    @error('opponent')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
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
                    <select name="court" id="court"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('court') border-red-500/50 @enderror">
                        <option value="">{{ __('app.choose_court') }}</option>
                        <optgroup label="المحاكم العليا والاستئناف">
                            <option value="المحكمة العليا" {{ old('court', $case->court) === 'المحكمة العليا' ? 'selected' : '' }}>المحكمة العليا</option>
                            <option value="محكمة استئناف مسقط" {{ old('court', $case->court) === 'محكمة استئناف مسقط' ? 'selected' : '' }}>محكمة استئناف مسقط</option>
                            <option value="محكمة استئناف الشمال" {{ old('court', $case->court) === 'محكمة استئناف الشمال' ? 'selected' : '' }}>محكمة استئناف الشمال</option>
                            <option value="محكمة استئناف جنوب الباطنة" {{ old('court', $case->court) === 'محكمة استئناف جنوب الباطنة' ? 'selected' : '' }}>محكمة استئناف جنوب الباطنة</option>
                            <option value="محكمة استئناف الداخلية" {{ old('court', $case->court) === 'محكمة استئناف الداخلية' ? 'selected' : '' }}>محكمة استئناف الداخلية</option>
                            <option value="محكمة استئناف البريمي" {{ old('court', $case->court) === 'محكمة استئناف البريمي' ? 'selected' : '' }}>محكمة استئناف البريمي</option>
                            <option value="محكمة استئناف ظفار" {{ old('court', $case->court) === 'محكمة استئناف ظفار' ? 'selected' : '' }}>محكمة استئناف ظفار</option>
                            <option value="محكمة استئناف شمال الباطنة" {{ old('court', $case->court) === 'محكمة استئناف شمال الباطنة' ? 'selected' : '' }}>محكمة استئناف شمال الباطنة</option>
                            <option value="محكمة استئناف مسندم" {{ old('court', $case->court) === 'محكمة استئناف مسندم' ? 'selected' : '' }}>محكمة استئناف مسندم</option>
                            <option value="محكمة استئناف الوسطى" {{ old('court', $case->court) === 'محكمة استئناف الوسطى' ? 'selected' : '' }}>محكمة استئناف الوسطى</option>
                            <option value="محكمة استئناف الشرقية" {{ old('court', $case->court) === 'محكمة استئناف الشرقية' ? 'selected' : '' }}>محكمة استئناف الشرقية</option>
                            <option value="محكمة استئناف جنوب الشرقية" {{ old('court', $case->court) === 'محكمة استئناف جنوب الشرقية' ? 'selected' : '' }}>محكمة استئناف جنوب الشرقية</option>
                            <option value="محكمة استئناف عمان" {{ old('court', $case->court) === 'محكمة استئناف عمان' ? 'selected' : '' }}>محكمة استئناف عمان</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - مسقط">
                            <option value="المحكمة الابتدائية بمسقط" {{ old('court', $case->court) === 'المحكمة الابتدائية بمسقط' ? 'selected' : '' }}>المحكمة الابتدائية بمسقط</option>
                            <option value="المحكمة الابتدائية بوطيّة" {{ old('court', $case->court) === 'المحكمة الابتدائية بوطيّة' ? 'selected' : '' }}>المحكمة الابتدائية بوطيّة</option>
                            <option value="المحكمة الابتدائية بالخوض" {{ old('court', $case->court) === 'المحكمة الابتدائية بالخوض' ? 'selected' : '' }}>المحكمة الابتدائية بالخوض</option>
                            <option value="المحكمة الابتدائية بقابس" {{ old('court', $case->court) === 'المحكمة الابتدائية بقابس' ? 'selected' : '' }}>المحكمة الابتدائية بقابس</option>
                            <option value="المحكمة الابتدائية بالعامر" {{ old('court', $case->court) === 'المحكمة الابتدائية بالعامر' ? 'selected' : '' }}>المحكمة الابتدائية بالعامر</option>
                            <option value="المحكمة الابتدائية بالسيب" {{ old('court', $case->court) === 'المحكمة الابتدائية بالسيب' ? 'selected' : '' }}>المحكمة الابتدائية بالسيب</option>
                            <option value="المحكمة الابتدائية ببوشر" {{ old('court', $case->court) === 'المحكمة الابتدائية ببوشر' ? 'selected' : '' }}>المحكمة الابتدائية ببوشر</option>
                            <option value="المحكمة الابتدائية بالمكيلة" {{ old('court', $case->court) === 'المحكمة الابتدائية بالمكيلة' ? 'selected' : '' }}>المحكمة الابتدائية بالمكيلة</option>
                            <option value="المحكمة الابتدائية بمسقط الجديدة" {{ old('court', $case->court) === 'المحكمة الابتدائية بمسقط الجديدة' ? 'selected' : '' }}>المحكمة الابتدائية بمسقط الجديدة</option>
                            <option value="المحكمة الابتدائية بالخابورة" {{ old('court', $case->court) === 'المحكمة الابتدائية بالخابورة' ? 'selected' : '' }}>المحكمة الابتدائية بالخابورة</option>
                            <option value="المحكمة الابتدائية بالرستاق" {{ old('court', $case->court) === 'المحكمة الابتدائية بالرستاق' ? 'selected' : '' }}>المحكمة الابتدائية بالرستاق</option>
                            <option value="المحكمة الابتدائية بسمائل" {{ old('court', $case->court) === 'المحكمة الابتدائية بسمائل' ? 'selected' : '' }}>المحكمة الابتدائية بسمائل</option>
                            <option value="المحكمة الابتدائية ببهلاء" {{ old('court', $case->court) === 'المحكمة الابتدائية ببهلاء' ? 'selected' : '' }}>المحكمة الابتدائية ببهلاء</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - الشمال">
                            <option value="المحكمة الابتدائية بالخزارة" {{ old('court', $case->court) === 'المحكمة الابتدائية بالخزارة' ? 'selected' : '' }}>المحكمة الابتدائية بالخزارة</option>
                            <option value="المحكمة الابتدائية بالخوير" {{ old('court', $case->court) === 'المحكمة الابتدائية بالخوير' ? 'selected' : '' }}>المحكمة الابتدائية بالخوير</option>
                            <option value="المحكمة الابتدائية لوادي عما" {{ old('court', $case->court) === 'المحكمة الابتدائية لوادي عما' ? 'selected' : '' }}>المحكمة الابتدائية لوادي عما</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - الداخلية">
                            <option value="المحكمة الابتدائية بنزوى" {{ old('court', $case->court) === 'المحكمة الابتدائية بنزوى' ? 'selected' : '' }}>المحكمة الابتدائية بنزوى</option>
                            <option value="المحكمة الابتدائية بعبري" {{ old('court', $case->court) === 'المحكمة الابتدائية بعبري' ? 'selected' : '' }}>المحكمة الابتدائية بعبري</option>
                            <option value="المحكمة الابتدائية بالعمارة" {{ old('court', $case->court) === 'المحكمة الابتدائية بالعمارة' ? 'selected' : '' }}>المحكمة الابتدائية بالعمارة</option>
                            <option value="المحكمة الابتدائية بالحمراء" {{ old('court', $case->court) === 'المحكمة الابتدائية بالحمراء' ? 'selected' : '' }}>المحكمة الابتدائية بالحمراء</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - ظفار">
                            <option value="المحكمة الابتدائية بصلالة" {{ old('court', $case->court) === 'المحكمة الابتدائية بصلالة' ? 'selected' : '' }}>المحكمة الابتدائية بصلالة</option>
                            <option value="المحكمة الابتدائية بمزيونة" {{ old('court', $case->court) === 'المحكمة الابتدائية بمزيونة' ? 'selected' : '' }}>المحكمة الابتدائية بمزيونة</option>
                            <option value="المحكمة الابتدائية بطاقة" {{ old('court', $case->court) === 'المحكمة الابتدائية بطاقة' ? 'selected' : '' }}>المحكمة الابتدائية بطاقة</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - الشرقية">
                            <option value="المحكمة الابتدائية ببركاء" {{ old('court', $case->court) === 'المحكمة الابتدائية ببركاء' ? 'selected' : '' }}>المحكمة الابتدائية ببركاء</option>
                            <option value="المحكمة الابتدائية بالعمارة" {{ old('court', $case->court) === 'المحكمة الابتدائية بالعمارة' ? 'selected' : '' }}>المحكمة الابتدائية بالعمارة</option>
                            <option value="المحكمة الابتدائية بمحضّة" {{ old('court', $case->court) === 'المحكمة الابتدائية بمحضّة' ? 'selected' : '' }}>المحكمة الابتدائية بمحضّة</option>
                            <option value="المحكمة الابتدائية بالصوير" {{ old('court', $case->court) === 'المحكمة الابتدائية بالصوير' ? 'selected' : '' }}>المحكمة الابتدائية بالصوير</option>
                            <option value="المحكمة الابتدائية بإيبرا" {{ old('court', $case->court) === 'المحكمة الابتدائية بإيبرا' ? 'selected' : '' }}>المحكمة الابتدائية بإيبرا</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - البريمي">
                            <option value="المحكمة الابتدائية بالبريمي" {{ old('court', $case->court) === 'المحكمة الابتدائية بالبريمي' ? 'selected' : '' }}>المحكمة الابتدائية بالبريمي</option>
                            <option value="المحكمة الابتدائية بالسويق" {{ old('court', $case->court) === 'المحكمة الابتدائية بالسويق' ? 'selected' : '' }}>المحكمة الابتدائية بالسويق</option>
                            <option value="المحكمة الابتدائية بالخزارة (البريمي)" {{ old('court', $case->court) === 'المحكمة الابتدائية بالخزارة (البريمي)' ? 'selected' : '' }}>المحكمة الابتدائية بالخزارة (البريمي)</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - مسندم">
                            <option value="المحكمة الابتدائية بخصب" {{ old('court', $case->court) === 'المحكمة الابتدائية بخصب' ? 'selected' : '' }}>المحكمة الابتدائية بخصب</option>
                            <option value="المحكمة الابتدائية بالخزارة (مسندم)" {{ old('court', $case->court) === 'المحكمة الابتدائية بالخزارة (مسندم)' ? 'selected' : '' }}>المحكمة الابتدائية بالخزارة (مسندم)</option>
                            <option value="المحكمة الابتدائية بدباء" {{ old('court', $case->court) === 'المحكمة الابتدائية بدباء' ? 'selected' : '' }}>المحكمة الابتدائية بدباء</option>
                        </optgroup>
                    </select>
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

                {{-- Next Date --}}
                <div>
                    <label for="next_date" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.next_date') }}</label>
                    <input type="date" name="next_date" id="next_date" value="{{ old('next_date', $case->next_date?->format('Y-m-d')) }}"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('next_date') border-red-500/50 @enderror">
                    @error('next_date')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- People Card --}}
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
                    <label for="lawyer_id" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_lawyer') }} <span class="text-red-400">*</span></label>
                    <select name="lawyer_id" id="lawyer_id" required
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('lawyer_id') border-red-500/50 @enderror">
                        <option value="">{{ __('app.choose_lawyer') }}</option>
                        @foreach($lawyers ?? [] as $lawyer)
                            <option value="{{ $lawyer->id }}" {{ old('lawyer_id', $case->lawyer_id) == $lawyer->id ? 'selected' : '' }}>{{ $lawyer->name }}</option>
                        @endforeach
                    </select>
                    @error('lawyer_id')
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
