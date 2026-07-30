@extends('layouts.app')

@section('title', __('app.page_add_case'))

@section('content')
<div class="max-w-4xl mx-auto" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-amber-700">{{ __('app.add_new_case') }}</h1>
        <a href="{{ route('cases.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
            {{ __('app.back') }}
        </a>
    </div>

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="bg-red-100 border border-red-200 rounded-xl p-4 mb-6" id="validationErrors">
            <div class="flex items-center gap-2 mb-2">
                <svg class="w-5 h-5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-red-700 font-bold text-sm">{{ __('app.warning') }}</p>
            </div>
            <ul class="list-disc list-inside text-red-700 text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('cases.store') }}" method="POST" class="space-y-6">
        @csrf

        {{-- Case Details Card --}}
        <div class="bg-white rounded-xl border border-amber-200 p-6 space-y-5">
            <h2 class="text-lg font-bold text-amber-700 border-b border-gray-200 pb-3">{{ __('app.case_details') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Court Case Number --}}
                <div>
                    <label for="case_number" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.court_case_number') }}</label>
                    <input type="text" name="case_number" id="case_number" value="{{ old('case_number', $generatedNumber) }}"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('case_number') border-red-500/50 @enderror"
                        placeholder="{{ __('app.case_number_placeholder') }}">
                    @error('case_number')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Case Type --}}
                <div>
                    <label for="case_type" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.case_type') }}</label>
                    <select name="case_type" id="case_type"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('case_type') border-red-500/50 @enderror">
                        <option value="">{{ __('app.choose_case_type') }}</option>
                        <option value="مدني" {{ old('case_type') === 'مدني' ? 'selected' : '' }}>مدني</option>
                        <option value="تجاري" {{ old('case_type') === 'تجاري' ? 'selected' : '' }}>تجاري</option>
                        <option value="عمالي" {{ old('case_type') === 'عمالي' ? 'selected' : '' }}>عمالي</option>
                        <option value="أحوال شخصية" {{ old('case_type') === 'أحوال شخصية' ? 'selected' : '' }}>أحوال شخصية</option>
                        <option value="استثمار" {{ old('case_type') === 'استثمار' ? 'selected' : '' }}>استثمار</option>
                        <option value="تنفيذ" {{ old('case_type') === 'تنفيذ' ? 'selected' : '' }}>تنفيذ</option>
                        <option value="جزائي" {{ old('case_type') === 'جزائي' ? 'selected' : '' }}>جزائي</option>
                    </select>
                    @error('case_type')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Court --}}
                <div x-data="{ manual: false }">
                    <div class="flex items-center gap-2 mb-1.5">
                        <label for="court" class="block text-sm font-medium text-gray-400">{{ __('app.case_court') }} <span class="text-red-700">*</span></label>
                        <label class="flex items-center gap-1.5 cursor-pointer ml-auto">
                            <input type="checkbox" x-model="manual" class="rounded border-gray-200 bg-gray-100 text-amber-500 focus:ring-amber-500/50">
                            <span class="text-xs text-gray-400">{{ __('app.manual_entry') }}</span>
                        </label>
                    </div>
                    <template x-if="!manual">
                        @include('cases._court_select', ['selected' => old('court')])
                    </template>
                    <template x-if="manual">
                        <input type="text" name="court" id="court" value="{{ old('court') }}"
                            class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('court') border-red-500/50 @enderror"
                            placeholder="{{ __('app.court_manual_placeholder') }}">
                    </template>
                    @error('court')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Title (Court case number as name) --}}
            <div>
                <label for="title" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.case_title_number') }} <span class="text-red-700">*</span></label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" required
                    class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('title') border-red-500/50 @enderror"
                    placeholder="{{ __('app.case_title_number_placeholder') }}">
                @error('title')
                    <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                @enderror
            </div>

            {{-- Description --}}
            <div>
                <label for="description" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.case_description') }} <span class="text-red-700">*</span></label>
                <textarea name="description" id="description" rows="4" required
                    class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 resize-y @error('description') border-red-500/50 @enderror"
                    placeholder="{{ __('app.case_description_placeholder') }}">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- People Card --}}
        <div class="bg-white rounded-xl border border-amber-200 p-6 space-y-5">
            <h2 class="text-lg font-bold text-amber-700 border-b border-gray-200 pb-3">{{ __('app.related_people') }}</h2>

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
                        <label for="client_id" class="block text-sm font-medium text-gray-400">{{ __('app.case_client') }} <span class="text-red-700">*</span></label>
                        <button type="button" @click="newMode = !newMode" class="text-xs text-amber-700 hover:text-amber-800 transition-colors font-medium">
                            <span x-text="newMode ? '← {{ __("app.existing_client") }}' : '{{ __("app.new_client_inline") }}'"></span>
                        </button>
                    </div>

                    <div x-show="!newMode">
                        <select name="client_id" id="client_id" required
                            class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('client_id') border-red-500/50 @enderror">
                            <option value="">{{ __('app.choose_client') }}</option>
                            @foreach($clients ?? [] as $client)
                                <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="newMode" x-transition class="bg-white border border-amber-200 rounded-xl p-4 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">{{ __('app.client_full_name') }} <span class="text-red-700">*</span></label>
                                <input type="text" x-model="nc.name"
                                    class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                    placeholder="{{ __('app.client_name_placeholder') }}">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">{{ __('app.client_phone') }}</label>
                                <input type="text" x-model="nc.phone"
                                    class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                    placeholder="99123456" dir="ltr">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">{{ __('app.client_email') }}</label>
                                <input type="email" x-model="nc.email"
                                    class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                    placeholder="email@example.com" dir="ltr">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">{{ __('app.client_national_id') }}</label>
                                <input type="text" x-model="nc.national_id"
                                    class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                    placeholder="{{ __('app.national_id_placeholder') }}" dir="ltr">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">{{ __('app.address') }}</label>
                            <input type="text" x-model="nc.address"
                                class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                placeholder="{{ __('app.client_address_placeholder') }}">
                        </div>
                        @if($errors->has('client_id'))
                            <p class="text-xs text-red-700">{{ $errors->first('client_id') }}</p>
                        @endif
                        <div x-show="error" class="text-xs text-red-700" x-text="error"></div>
                        <button type="button" @click="saveClient()" :disabled="saving || !nc.name.trim()"
                            class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm disabled:opacity-50">
                            <span x-text="saving ? '{{ __("app.saving") }}' : '{{ __("app.save_client_add") }}'"></span>
                        </button>
                    </div>
                    @error('client_id')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Lawyer --}}
                <div>
                    <label for="lawyer_id" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.case_lawyer') }}</label>
                    <select name="lawyer_id" id="lawyer_id"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('lawyer_id') border-red-500/50 @enderror">
                        <option value="">اختر محامي القضية</option>
                        @foreach($users ?? [] as $user)
                            <option value="{{ $user->id }}" {{ old('lawyer_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('lawyer_id')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Opponent Data Card --}}
        <div class="bg-white rounded-xl border border-amber-200 p-6 space-y-5">
            <h2 class="text-lg font-bold text-amber-700 border-b border-gray-200 pb-3">{{ __('app.opponent_data') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="opponent" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.opponent_name') }} <span class="text-red-700">*</span></label>
                    <input type="text" name="opponent" id="opponent" value="{{ old('opponent') }}" required
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('opponent') border-red-500/50 @enderror"
                        placeholder="{{ __('app.opponent_name_placeholder') }}">
                    @error('opponent')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="opponent_phone" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.opponent_phone') }}</label>
                    <input type="text" name="opponent_phone" id="opponent_phone" value="{{ old('opponent_phone') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('opponent_phone') border-red-500/50 @enderror"
                        placeholder="{{ __('app.opponent_phone_placeholder') }}">
                    @error('opponent_phone')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="opponent_address" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.opponent_address') }}</label>
                    <input type="text" name="opponent_address" id="opponent_address" value="{{ old('opponent_address') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('opponent_address') border-red-500/50 @enderror"
                        placeholder="{{ __('app.opponent_address_placeholder') }}">
                    @error('opponent_address')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="opponent_lawyer" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.opponent_lawyer') }}</label>
                    <input type="text" name="opponent_lawyer" id="opponent_lawyer" value="{{ old('opponent_lawyer') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('opponent_lawyer') border-red-500/50 @enderror"
                        placeholder="{{ __('app.opponent_lawyer_placeholder') }}">
                    @error('opponent_lawyer')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="opponent_civil_number" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.opponent_civil_number') }}</label>
                    <input type="text" name="opponent_civil_number" id="opponent_civil_number" value="{{ old('opponent_civil_number') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('opponent_civil_number') border-red-500/50 @enderror"
                        placeholder="{{ __('app.opponent_civil_number_placeholder') }}">
                    @error('opponent_civil_number')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Dates Card --}}
        <div class="bg-white rounded-xl border border-amber-200 p-6 space-y-5">
            <h2 class="text-lg font-bold text-amber-700 border-b border-gray-200 pb-3">{{ __('app.case_dates') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Opened At --}}
                <div>
                    <label for="opened_at" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.opened_date') }} <span class="text-red-700">*</span></label>
                    <input type="date" name="opened_at" id="opened_at" value="{{ old('opened_at', date('Y-m-d')) }}" required
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('opened_at') border-red-500/50 @enderror">
                    @error('opened_at')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Next Session Date --}}
                <div>
                    <label for="next_date" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.next_session_date') }}</label>
                    <input type="date" name="next_date" id="next_date" value="{{ old('next_date') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('next_date') border-red-500/50 @enderror">
                    @error('next_date')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Status & Priority Card --}}
        <div class="bg-white rounded-xl border border-amber-200 p-6 space-y-5">
            <h2 class="text-lg font-bold text-amber-700 border-b border-gray-200 pb-3">{{ __('app.case_status_priority') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Status --}}
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.status') }}</label>
                    <select name="status" id="status"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('status') border-red-500/50 @enderror">
                        <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>{{ __('app.status_active') }}</option>
                        <option value="pending" {{ old('status', 'pending') === 'pending' ? 'selected' : '' }}>{{ __('app.status_pending') }}</option>
                        <option value="closed" {{ old('status') === 'closed' ? 'selected' : '' }}>{{ __('app.status_closed') }}</option>
                        <option value="won" {{ old('status') === 'won' ? 'selected' : '' }}>{{ __('app.status_won') }}</option>
                        <option value="lost" {{ old('status') === 'lost' ? 'selected' : '' }}>{{ __('app.status_lost') }}</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Priority --}}
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.priority') }}</label>
                    <select name="priority" id="priority"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 @error('priority') border-red-500/50 @enderror">
                        <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>{{ __('app.priority_low') }}</option>
                        <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>{{ __('app.priority_medium') }}</option>
                        <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>{{ __('app.priority_high') }}</option>
                        <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>{{ __('app.priority_urgent') }}</option>
                    </select>
                    @error('priority')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3">
            <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                {{ __('app.add_case_button') }}
            </button>
            <a href="{{ route('cases.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                {{ __('app.cancel') }}
            </a>
        </div>
    </form>
</div>

@endsection
