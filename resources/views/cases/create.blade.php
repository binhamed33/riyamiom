@extends('layouts.app')

@section('title', __('app.page_add_case'))

@section('content')
<div class="max-w-4xl mx-auto" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-[#C9A55A]">{{ __('app.add_new_case') }}</h1>
        <a href="{{ route('cases.index') }}" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
            {{ __('app.back') }}
        </a>
    </div>

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-4 mb-6" id="validationErrors">
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
    <form action="{{ route('cases.store') }}" method="POST" class="space-y-6">
        @csrf

        {{-- Basic Info Card --}}
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6 space-y-5">
            <h2 class="text-lg font-bold text-[#C9A55A] border-b border-white/10 pb-3">{{ __('app.basic_info') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Case Number --}}
                <div>
                    <label for="case_number" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_number') }} <span class="text-red-400">*</span></label>
                    <input type="text" name="case_number" id="case_number" value="{{ old('case_number') }}" required
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('case_number') border-red-500/50 @enderror"
                        placeholder="{{ __('app.case_number_placeholder') }}">
                    @error('case_number')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Title --}}
                <div>
                    <label for="title" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_title') }} <span class="text-red-400">*</span></label>
                    <input type="text" name="title" id="title" value="{{ old('title') }}" required
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('title') border-red-500/50 @enderror"
                        placeholder="{{ __('app.case_title_placeholder') }}">
                    @error('title')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Type --}}
                <div>
                    <label for="type" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_type') }} <span class="text-red-400">*</span></label>
                    <input type="text" name="type" id="type" value="{{ old('type') }}" required
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('type') border-red-500/50 @enderror"
                        placeholder="{{ __('app.case_type_placeholder') }}">
                    @error('type')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Opponent --}}
                <div>
                    <label for="opponent" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_opponent') }} <span class="text-red-400">*</span></label>
                    <input type="text" name="opponent" id="opponent" value="{{ old('opponent') }}" required
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('opponent') border-red-500/50 @enderror"
                        placeholder="{{ __('app.opponent_name_placeholder') }}">
                    @error('opponent')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Description --}}
            <div>
                <label for="description" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_description') }} <span class="text-red-400">*</span></label>
                <textarea name="description" id="description" rows="4" required
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] resize-y @error('description') border-red-500/50 @enderror"
                    placeholder="{{ __('app.case_description_placeholder') }}">{{ old('description') }}</textarea>
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
                    <select name="court" id="court" required
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('court') border-red-500/50 @enderror">
                        <option value="">{{ __('app.choose_court') }}</option>
                        <optgroup label="المحاكم العليا والاستئناف">
                            <option value="المحكمة العليا" {{ old('court') === 'المحكمة العليا' ? 'selected' : '' }}>المحكمة العليا</option>
                            <option value="محكمة استئناف مسقط" {{ old('court') === 'محكمة استئناف مسقط' ? 'selected' : '' }}>محكمة استئناف مسقط</option>
                            <option value="محكمة استئناف الشمال" {{ old('court') === 'محكمة استئناف الشمال' ? 'selected' : '' }}>محكمة استئناف الشمال</option>
                            <option value="محكمة استئناف جنوب الباطنة" {{ old('court') === 'محكمة استئناف جنوب الباطنة' ? 'selected' : '' }}>محكمة استئناف جنوب الباطنة</option>
                            <option value="محكمة استئناف الداخلية" {{ old('court') === 'محكمة استئناف الداخلية' ? 'selected' : '' }}>محكمة استئناف الداخلية</option>
                            <option value="محكمة استئناف البريمي" {{ old('court') === 'محكمة استئناف البريمي' ? 'selected' : '' }}>محكمة استئناف البريمي</option>
                            <option value="محكمة استئناف ظفار" {{ old('court') === 'محكمة استئناف ظفار' ? 'selected' : '' }}>محكمة استئناف ظفار</option>
                            <option value="محكمة استئناف شمال الباطنة" {{ old('court') === 'محكمة استئناف شمال الباطنة' ? 'selected' : '' }}>محكمة استئناف شمال الباطنة</option>
                            <option value="محكمة استئناف مسندم" {{ old('court') === 'محكمة استئناف مسندم' ? 'selected' : '' }}>محكمة استئناف مسندم</option>
                            <option value="محكمة استئناف الوسطى" {{ old('court') === 'محكمة استئناف الوسطى' ? 'selected' : '' }}>محكمة استئناف الوسطى</option>
                            <option value="محكمة استئناف الشرقية" {{ old('court') === 'محكمة استئناف الشرقية' ? 'selected' : '' }}>محكمة استئناف الشرقية</option>
                            <option value="محكمة استئناف جنوب الشرقية" {{ old('court') === 'محكمة استئناف جنوب الشرقية' ? 'selected' : '' }}>محكمة استئناف جنوب الشرقية</option>
                            <option value="محكمة استئناف عمان" {{ old('court') === 'محكمة استئناف عمان' ? 'selected' : '' }}>محكمة استئناف عمان</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - مسقط">
                            <option value="المحكمة الابتدائية بمسقط" {{ old('court') === 'المحكمة الابتدائية بمسقط' ? 'selected' : '' }}>المحكمة الابتدائية بمسقط</option>
                            <option value="المحكمة الابتدائية بوطيّة" {{ old('court') === 'المحكمة الابتدائية بوطيّة' ? 'selected' : '' }}>المحكمة الابتدائية بوطيّة</option>
                            <option value="المحكمة الابتدائية بالخوض" {{ old('court') === 'المحكمة الابتدائية بالخوض' ? 'selected' : '' }}>المحكمة الابتدائية بالخوض</option>
                            <option value="المحكمة الابتدائية بقابس" {{ old('court') === 'المحكمة الابتدائية بقابس' ? 'selected' : '' }}>المحكمة الابتدائية بقابس</option>
                            <option value="المحكمة الابتدائية بالعامر" {{ old('court') === 'المحكمة الابتدائية بالعامر' ? 'selected' : '' }}>المحكمة الابتدائية بالعامر</option>
                            <option value="المحكمة الابتدائية بالسيب" {{ old('court') === 'المحكمة الابتدائية بالسيب' ? 'selected' : '' }}>المحكمة الابتدائية بالسيب</option>
                            <option value="المحكمة الابتدائية ببوشر" {{ old('court') === 'المحكمة الابتدائية ببوشر' ? 'selected' : '' }}>المحكمة الابتدائية ببوشر</option>
                            <option value="المحكمة الابتدائية بالمكيلة" {{ old('court') === 'المحكمة الابتدائية بالمكيلة' ? 'selected' : '' }}>المحكمة الابتدائية بالمكيلة</option>
                            <option value="المحكمة الابتدائية بمسقط الجديدة" {{ old('court') === 'المحكمة الابتدائية بمسقط الجديدة' ? 'selected' : '' }}>المحكمة الابتدائية بمسقط الجديدة</option>
                            <option value="المحكمة الابتدائية بالخابورة" {{ old('court') === 'المحكمة الابتدائية بالخابورة' ? 'selected' : '' }}>المحكمة الابتدائية بالخابورة</option>
                            <option value="المحكمة الابتدائية بالرستاق" {{ old('court') === 'المحكمة الابتدائية بالرستاق' ? 'selected' : '' }}>المحكمة الابتدائية بالرستاق</option>
                            <option value="المحكمة الابتدائية بسمائل" {{ old('court') === 'المحكمة الابتدائية بسمائل' ? 'selected' : '' }}>المحكمة الابتدائية بسمائل</option>
                            <option value="المحكمة الابتدائية ببهلاء" {{ old('court') === 'المحكمة الابتدائية ببهلاء' ? 'selected' : '' }}>المحكمة الابتدائية ببهلاء</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - الشمال">
                            <option value="المحكمة الابتدائية بالخزارة" {{ old('court') === 'المحكمة الابتدائية بالخزارة' ? 'selected' : '' }}>المحكمة الابتدائية بالخزارة</option>
                            <option value="المحكمة الابتدائية بالخوير" {{ old('court') === 'المحكمة الابتدائية بالخوير' ? 'selected' : '' }}>المحكمة الابتدائية بالخوير</option>
                            <option value="المحكمة الابتدائية لوادي عما" {{ old('court') === 'المحكمة الابتدائية لوادي عما' ? 'selected' : '' }}>المحكمة الابتدائية لوادي عما</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - الداخلية">
                            <option value="المحكمة الابتدائية بنزوى" {{ old('court') === 'المحكمة الابتدائية بنزوى' ? 'selected' : '' }}>المحكمة الابتدائية بنزوى</option>
                            <option value="المحكمة الابتدائية بعبري" {{ old('court') === 'المحكمة الابتدائية بعبري' ? 'selected' : '' }}>المحكمة الابتدائية بعبري</option>
                            <option value="المحكمة الابتدائية بالعمارة" {{ old('court') === 'المحكمة الابتدائية بالعمارة' ? 'selected' : '' }}>المحكمة الابتدائية بالعمارة</option>
                            <option value="المحكمة الابتدائية بالحمراء" {{ old('court') === 'المحكمة الابتدائية بالحمراء' ? 'selected' : '' }}>المحكمة الابتدائية بالحمراء</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - ظفار">
                            <option value="المحكمة الابتدائية بصلالة" {{ old('court') === 'المحكمة الابتدائية بصلالة' ? 'selected' : '' }}>المحكمة الابتدائية بصلالة</option>
                            <option value="المحكمة الابتدائية بمزيونة" {{ old('court') === 'المحكمة الابتدائية بمزيونة' ? 'selected' : '' }}>المحكمة الابتدائية بمزيونة</option>
                            <option value="المحكمة الابتدائية بطاقة" {{ old('court') === 'المحكمة الابتدائية بطاقة' ? 'selected' : '' }}>المحكمة الابتدائية بطاقة</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - الشرقية">
                            <option value="المحكمة الابتدائية ببركاء" {{ old('court') === 'المحكمة الابتدائية ببركاء' ? 'selected' : '' }}>المحكمة الابتدائية ببركاء</option>
                            <option value="المحكمة الابتدائية بالعمارة" {{ old('court') === 'المحكمة الابتدائية بالعمارة' ? 'selected' : '' }}>المحكمة الابتدائية بالعمارة</option>
                            <option value="المحكمة الابتدائية بمحضّة" {{ old('court') === 'المحكمة الابتدائية بمحضّة' ? 'selected' : '' }}>المحكمة الابتدائية بمحضّة</option>
                            <option value="المحكمة الابتدائية بالصوير" {{ old('court') === 'المحكمة الابتدائية بالصوير' ? 'selected' : '' }}>المحكمة الابتدائية بالصوير</option>
                            <option value="المحكمة الابتدائية بإيبرا" {{ old('court') === 'المحكمة الابتدائية بإيبرا' ? 'selected' : '' }}>المحكمة الابتدائية بإيبرا</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - البريمي">
                            <option value="المحكمة الابتدائية بالبريمي" {{ old('court') === 'المحكمة الابتدائية بالبريمي' ? 'selected' : '' }}>المحكمة الابتدائية بالبريمي</option>
                            <option value="المحكمة الابتدائية بالسويق" {{ old('court') === 'المحكمة الابتدائية بالسويق' ? 'selected' : '' }}>المحكمة الابتدائية بالسويق</option>
                            <option value="المحكمة الابتدائية بالخزارة (البريمي)" {{ old('court') === 'المحكمة الابتدائية بالخزارة (البريمي)' ? 'selected' : '' }}>المحكمة الابتدائية بالخزارة (البريمي)</option>
                        </optgroup>
                        <optgroup label="المحاكم الابتدائية - مسندم">
                            <option value="المحكمة الابتدائية بخصب" {{ old('court') === 'المحكمة الابتدائية بخصب' ? 'selected' : '' }}>المحكمة الابتدائية بخصب</option>
                            <option value="المحكمة الابتدائية بالخزارة (مسندم)" {{ old('court') === 'المحكمة الابتدائية بالخزارة (مسندم)' ? 'selected' : '' }}>المحكمة الابتدائية بالخزارة (مسندم)</option>
                            <option value="المحكمة الابتدائية بدباء" {{ old('court') === 'المحكمة الابتدائية بدباء' ? 'selected' : '' }}>المحكمة الابتدائية بدباء</option>
                        </optgroup>
                    </select>
                </template>
                <template x-if="manual">
                    <input type="text" name="court" id="court" value="{{ old('court') }}"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('court') border-red-500/50 @enderror"
                        placeholder="{{ __('app.court_manual_placeholder') }}">
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
                        <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>{{ __('app.status_active') }}</option>
                        <option value="pending" {{ old('status', 'pending') === 'pending' ? 'selected' : '' }}>{{ __('app.status_pending') }}</option>
                        <option value="closed" {{ old('status') === 'closed' ? 'selected' : '' }}>{{ __('app.status_closed') }}</option>
                        <option value="won" {{ old('status') === 'won' ? 'selected' : '' }}>{{ __('app.status_won') }}</option>
                        <option value="lost" {{ old('status') === 'lost' ? 'selected' : '' }}>{{ __('app.status_lost') }}</option>
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
                        <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>{{ __('app.priority_low') }}</option>
                        <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>{{ __('app.priority_medium') }}</option>
                        <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>{{ __('app.priority_high') }}</option>
                        <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>{{ __('app.priority_urgent') }}</option>
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
                    <label for="opened_at" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.opened_date') }} <span class="text-red-400">*</span></label>
                    <input type="date" name="opened_at" id="opened_at" value="{{ old('opened_at', date('Y-m-d')) }}" required
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('opened_at') border-red-500/50 @enderror">
                    @error('opened_at')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Next Date --}}
                <div>
                    <label for="next_date" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.next_date') }}</label>
                    <input type="date" name="next_date" id="next_date" value="{{ old('next_date') }}"
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

            <div x-data="{
                newMode: false,
                saving: false,
                error: '',
                nc: { name: '', phone: '', email: '', national_id: '', address: '' },
                async saveClient() {
                    this.error = '';
                    if (!this.nc.name.trim()) { this.error = '{{ __("app.name_required") }}'; return; }
                    this.saving = true;
                    try {
                        const res = await fetch('{{ route('clients.ajax') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                            body: JSON.stringify(this.nc)
                        });
                        this.saving = false;
                        if (res.ok) {
                            const data = await res.json();
                            const sel = document.getElementById('client_id');
                            const opt = new Option(data.name, data.id, true, true);
                            sel.appendChild(opt);
                            this.nc = { name: '', phone: '', email: '', national_id: '', address: '' };
                            this.newMode = false;
                        } else {
                            const err = await res.json();
                            this.error = err.message || '{{ __("app.save_error") }}';
                        }
                    } catch(e) {
                        this.saving = false;
                        this.error = '{{ __("app.connection_error") }}';
                    }
                }
            }">
                {{-- Client --}}
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center gap-2 mb-1.5">
                        <label for="client_id" class="block text-sm font-medium text-white/30">{{ __('app.case_client') }} <span class="text-red-400">*</span></label>
                        <button type="button" @click="newMode = !newMode" class="text-xs text-[#C9A55A] hover:text-[#B89349] transition-colors font-medium">
                            <span x-text="newMode ? '← {{ __("app.existing_client") }}' : '{{ __("app.new_client_inline") }}'"></span>
                        </button>
                    </div>

                    {{-- Existing client dropdown --}}
                    <div x-show="!newMode">
                        <select name="client_id" id="client_id" required
                            class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('client_id') border-red-500/50 @enderror">
                            <option value="">{{ __('app.choose_client') }}</option>
                            @foreach($clients ?? [] as $client)
                                <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Inline new client fields --}}
                    <div x-show="newMode" x-transition class="bg-white/[0.03] border border-[#C9A55A]/20 rounded-xl p-4 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-white/40 mb-1">{{ __('app.client_full_name') }} <span class="text-red-400">*</span></label>
                                <input type="text" x-model="nc.name"
                                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-3 py-2 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                                    placeholder="{{ __('app.client_name_placeholder') }}">
                            </div>
                            <div>
                                <label class="block text-xs text-white/40 mb-1">{{ __('app.client_phone') }}</label>
                                <input type="text" x-model="nc.phone"
                                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-3 py-2 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                                    placeholder="99123456" dir="ltr">
                            </div>
                            <div>
                                <label class="block text-xs text-white/40 mb-1">{{ __('app.client_email') }}</label>
                                <input type="email" x-model="nc.email"
                                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-3 py-2 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                                    placeholder="email@example.com" dir="ltr">
                            </div>
                            <div>
                                <label class="block text-xs text-white/40 mb-1">{{ __('app.client_national_id') }}</label>
                                <input type="text" x-model="nc.national_id"
                                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-3 py-2 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                                    placeholder="{{ __('app.national_id_placeholder') }}" dir="ltr">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1">{{ __('app.address') }}</label>
                            <input type="text" x-model="nc.address"
                                class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-3 py-2 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                                placeholder="{{ __('app.client_address_placeholder') }}">
                        </div>
                        @if($errors->has('client_id'))
                            <p class="text-xs text-red-400">{{ $errors->first('client_id') }}</p>
                        @endif
                        <div x-show="error" class="text-xs text-red-400" x-text="error"></div>
                        <button type="button" @click="saveClient()" :disabled="saving || !nc.name.trim()"
                            class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm disabled:opacity-50">
                            <span x-text="saving ? '{{ __("app.saving") }}' : '{{ __("app.save_client_add") }}'"></span>
                        </button>
                    </div>
                    @error('client_id')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Lawyer --}}
                <div>
                    <label for="lawyer_id" class="block text-sm font-medium text-white/30 mb-1.5">{{ __('app.case_lawyer') }}</label>
                    <select name="lawyer_id" id="lawyer_id"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('lawyer_id') border-red-500/50 @enderror">
                        <option value="">{{ __('app.choose_lawyer') }}</option>
                        @foreach($lawyers ?? [] as $lawyer)
                            <option value="{{ $lawyer->id }}" {{ old('lawyer_id') == $lawyer->id ? 'selected' : '' }}>{{ $lawyer->name }}</option>
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
                {{ __('app.add_case_button') }}
            </button>
            <a href="{{ route('cases.index') }}" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                {{ __('app.cancel') }}
            </a>
        </div>
    </form>
</div>

@endsection
