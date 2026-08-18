@extends('layouts.app')

@section('title', __('app.page_add_case'))

@section('content')
<div class="max-w-4xl mx-auto" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gold-dark">{{ __('app.add_new_case') }}</h1>
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
    <form action="{{ route('cases.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- Case Details Card --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6 space-y-5">
            <h2 class="text-lg font-bold text-gold-dark border-b border-gray-200 pb-3">{{ __('app.case_details') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Court Case Number --}}
                <div>
                    <label for="case_number" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.court_case_number') }} <span class="text-red-700">*</span></label>
                    <input type="text" name="case_number" id="case_number" value="{{ old('case_number') }}" required
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('case_number') border-red-500/50 @enderror"
                        placeholder="{{ __('app.case_number_placeholder') }}">
                    @error('case_number')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Case Type --}}
                @php
                    $caseTypePresets = ['مدني','تجاري','عمالي','أحوال شخصية','جزائي','تنفيذ مدني','تنفيذ جزائي','قضاء مستعجل','أوامر على العرائض','إفلاس وإعادة هيكلة','إيجارات','مرور','أحداث','اداري','استثمار','استشكال','تظلمات'];
                @endphp
                <div x-data="{ manual: {{ in_array(old('case_type'), $caseTypePresets, true) ? 'false' : (old('case_type') ? 'true' : 'false') }} }">
                    <div class="flex items-center gap-2 mb-1.5">
                        <label for="case_type" class="block text-sm font-medium text-gray-400">{{ __('app.case_type') }}</label>
                        <label class="flex items-center gap-1.5 cursor-pointer ml-auto">
                            <input type="checkbox" x-model="manual" class="rounded border-gray-200 bg-gray-100 text-gold-dark focus:ring-gold-dark/50">
                            <span class="text-xs text-gray-400">{{ __('app.manual_entry') }}</span>
                        </label>
                    </div>
                    <template x-if="!manual">
                        <select name="case_type" id="case_type"
                            class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('case_type') border-red-500/50 @enderror">
                            <option value="">{{ __('app.choose_case_type') }}</option>
                            <option value="اداري" {{ old('case_type') === 'اداري' ? 'selected' : '' }}>اداري</option>
                            <option value="أحداث" {{ old('case_type') === 'أحداث' ? 'selected' : '' }}>أحداث</option>
                            <option value="أحوال شخصية" {{ old('case_type') === 'أحوال شخصية' ? 'selected' : '' }}>أحوال شخصية</option>
                            <option value="استثمار" {{ old('case_type') === 'استثمار' ? 'selected' : '' }}>استثمار</option>
                            <option value="استشكال" {{ old('case_type') === 'استشكال' ? 'selected' : '' }}>استشكال</option>
                            <option value="أوامر على العرائض" {{ old('case_type') === 'أوامر على العرائض' ? 'selected' : '' }}>أوامر على العرائض</option>
                            <option value="إفلاس وإعادة هيكلة" {{ old('case_type') === 'إفلاس وإعادة هيكلة' ? 'selected' : '' }}>إفلاس وإعادة هيكلة</option>
                            <option value="إيجارات" {{ old('case_type') === 'إيجارات' ? 'selected' : '' }}>إيجارات</option>
                            <option value="تجاري" {{ old('case_type') === 'تجاري' ? 'selected' : '' }}>تجاري</option>
                            <option value="تظلمات" {{ old('case_type') === 'تظلمات' ? 'selected' : '' }}>تظلمات</option>
                            <option value="تنفيذ جزائي" {{ old('case_type') === 'تنفيذ جزائي' ? 'selected' : '' }}>تنفيذ جزائي</option>
                            <option value="تنفيذ مدني" {{ old('case_type') === 'تنفيذ مدني' ? 'selected' : '' }}>تنفيذ مدني</option>
                            <option value="جزائي" {{ old('case_type') === 'جزائي' ? 'selected' : '' }}>جزائي</option>
                            <option value="مدني" {{ old('case_type') === 'مدني' ? 'selected' : '' }}>مدني</option>
                            <option value="مرور" {{ old('case_type') === 'مرور' ? 'selected' : '' }}>مرور</option>
                            <option value="قضاء مستعجل" {{ old('case_type') === 'قضاء مستعجل' ? 'selected' : '' }}>قضاء مستعجل</option>
                            <option value="عمالي" {{ old('case_type') === 'عمالي' ? 'selected' : '' }}>عمالي</option>
                        </select>
                    </template>
                    <template x-if="manual">
                        <input type="text" name="case_type" id="case_type" value="{{ old('case_type') }}"
                            class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('case_type') border-red-500/50 @enderror"
                            placeholder="اكتب نوع القضية يدوياً">
                    </template>
                    @error('case_type')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Court --}}
                <div x-data="{ manual: false }">
                    <div class="flex items-center gap-2 mb-1.5">
                        <label for="court" class="block text-sm font-medium text-gray-400">{{ __('app.case_court') }} <span class="text-red-700">*</span></label>
                        <label class="flex items-center gap-1.5 cursor-pointer ml-auto">
                            <input type="checkbox" x-model="manual" class="rounded border-gray-200 bg-gray-100 text-gold-dark focus:ring-gold-dark/50">
                            <span class="text-xs text-gray-400">{{ __('app.manual_entry') }}</span>
                        </label>
                    </div>
                    <template x-if="!manual">
                        @include('cases._court_select', ['selected' => old('court')])
                    </template>
                    <template x-if="manual">
                        <input type="text" name="court" id="court" value="{{ old('court') }}"
                            class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('court') border-red-500/50 @enderror"
                            placeholder="{{ __('app.court_manual_placeholder') }}">
                    </template>
                    @error('court')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Description --}}
            <div>
                <label for="description" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.case_description') }} <span class="text-red-700">*</span></label>
                <textarea name="description" id="description" rows="4" required
                    class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 resize-y @error('description') border-red-500/50 @enderror"
                    placeholder="{{ __('app.case_description_placeholder') }}">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Optional Additions Card --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6 space-y-4" x-data="{ showDoc: false, showTask: false, showNote: false }">
            <div class="border-b border-gray-200 pb-3">
                <h2 class="text-lg font-bold text-gold-dark">{{ __('app.add_optional') }}</h2>
                <p class="text-xs text-gray-400 mt-1">{{ __('app.add_optional_hint') }}</p>
            </div>

            {{-- Document --}}
            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <button type="button" @click="showDoc = !showDoc" class="w-full flex items-center justify-between gap-2 px-4 py-3 bg-gray-50 hover:bg-gray-100 transition">
                    <span class="text-sm font-bold text-gray-700">{{ __('app.add_document') }}</span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform text-gold-dark" :class="showDoc ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="showDoc" class="px-4 py-4 space-y-3 border-t border-gray-100">
                    <div>
                        <label for="doc_title" class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.document_title') }}</label>
                        <input type="text" name="doc_title" id="doc_title" value="{{ old('doc_title') }}"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                            placeholder="مثال: مذكرة دفاع / البطاقة الشخصية / عقد الإيجار">
                    </div>
                    <div>
                        <label for="doc_file" class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.document_file') }}</label>
                        <input type="file" name="doc_file" id="doc_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                            class="w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gold/10 file:px-4 file:py-2 file:text-gold-dark file:font-semibold hover:file:bg-gold/12">
                    </div>
                    <div>
                        <label for="doc_access_level" class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.access_level') }}</label>
                        <select name="doc_access_level" id="doc_access_level"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                            <option value="all" {{ old('doc_access_level', 'all') === 'all' ? 'selected' : '' }}>{{ __('app.access_all') }}</option>
                            <option value="team" {{ old('doc_access_level') === 'team' ? 'selected' : '' }}>{{ __('app.access_team') }}</option>
                            <option value="private" {{ old('doc_access_level') === 'private' ? 'selected' : '' }}>{{ __('app.access_private') }}</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Task --}}
            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <button type="button" @click="showTask = !showTask" class="w-full flex items-center justify-between gap-2 px-4 py-3 bg-gray-50 hover:bg-gray-100 transition">
                    <span class="text-sm font-bold text-gray-700">{{ __('app.add_task') }}</span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform text-gold-dark" :class="showTask ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="showTask" class="px-4 py-4 space-y-3 border-t border-gray-100">
                    <div>
                        <label for="task_title" class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.task_title') }}</label>
                        <input type="text" name="task_title" id="task_title" value="{{ old('task_title') }}"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                            placeholder="مثال: إعداد مذكرة الدفاع / مراجعة المستندات">
                    </div>
                    <div>
                        <label for="task_description" class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.task_description') }}</label>
                        <textarea name="task_description" id="task_description" rows="2"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 resize-y">{{ old('task_description') }}</textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label for="task_due_date" class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.task_due_date') }}</label>
                            <input type="date" name="task_due_date" id="task_due_date" value="{{ old('task_due_date') }}"
                                class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                        </div>
                        <div>
                            <label for="task_priority" class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.priority') }}</label>
                            <select name="task_priority" id="task_priority"
                                class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                                <option value="low" {{ old('task_priority') === 'low' ? 'selected' : '' }}>{{ __('app.priority_low') }}</option>
                                <option value="medium" {{ old('task_priority', 'medium') === 'medium' ? 'selected' : '' }}>{{ __('app.priority_medium') }}</option>
                                <option value="high" {{ old('task_priority') === 'high' ? 'selected' : '' }}>{{ __('app.priority_high') }}</option>
                                <option value="urgent" {{ old('task_priority') === 'urgent' ? 'selected' : '' }}>{{ __('app.priority_urgent') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="task_assigned_to" class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.task_assigned_to') }}</label>
                            <select name="task_assigned_to" id="task_assigned_to"
                                class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                                <option value="">{{ __('app.choose_assignee') }}</option>
                                @foreach($users ?? [] as $user)
                                    <option value="{{ $user->id }}" {{ old('task_assigned_to', auth()->id()) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Note --}}
            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <button type="button" @click="showNote = !showNote" class="w-full flex items-center justify-between gap-2 px-4 py-3 bg-gray-50 hover:bg-gray-100 transition">
                    <span class="text-sm font-bold text-gray-700">{{ __('app.add_note') }}</span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform text-gold-dark" :class="showNote ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="showNote" class="px-4 py-4 space-y-3 border-t border-gray-100">
                    <div>
                        <label for="note_title" class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.note_title') }}</label>
                        <input type="text" name="note_title" id="note_title" value="{{ old('note_title') }}"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                            placeholder="مثال: اتفاق شفهي مع الموكل">
                    </div>
                    <div>
                        <label for="note_content" class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.note_content') }}</label>
                        <textarea name="note_content" id="note_content" rows="2"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 resize-y">{{ old('note_content') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- People Card --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6 space-y-5">
            <h2 class="text-lg font-bold text-gold-dark border-b border-gray-200 pb-3">{{ __('app.related_people') }}</h2>

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
                            if (sel.tomselect) {
                                sel.tomselect.addOption({ value: data.id, text: data.name });
                                sel.tomselect.addItem(data.id);
                                sel.tomselect.clear(true);
                            } else {
                                const opt = new Option(data.name, data.id, true, true);
                                sel.appendChild(opt);
                            }
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
                        <button type="button" @click="newMode = !newMode" class="text-xs text-gold-dark hover:text-gold-dark transition-colors font-medium">
                            <span x-text="newMode ? '← {{ __("app.existing_client") }}' : '{{ __("app.new_client_inline") }}'"></span>
                        </button>
                    </div>

                    <div x-show="!newMode">
                        <select name="client_id" id="client_id" required data-no-create placeholder="اكتب لتبحث عن عميل..."
                            class="ts w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('client_id') border-red-500/50 @enderror">
                            <option value="">{{ __('app.choose_client') }}</option>
                            @foreach($clients ?? [] as $client)
                                <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="newMode" x-transition class="bg-white border border-gold/15 rounded-xl p-4 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">{{ __('app.client_full_name') }} <span class="text-red-700">*</span></label>
                                <input type="text" x-model="nc.name"
                                    class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                                    placeholder="{{ __('app.client_name_placeholder') }}">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">{{ __('app.client_phone') }}</label>
                                <input type="text" x-model="nc.phone"
                                    class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                                    placeholder="99123456" dir="ltr">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">{{ __('app.client_email') }}</label>
                                <input type="email" x-model="nc.email"
                                    class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                                    placeholder="email@example.com" dir="ltr">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">{{ __('app.client_national_id') }}</label>
                                <input type="text" x-model="nc.national_id"
                                    class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                                    placeholder="{{ __('app.national_id_placeholder') }}" dir="ltr">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">{{ __('app.address') }}</label>
                            <input type="text" x-model="nc.address"
                                class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                                placeholder="{{ __('app.client_address_placeholder') }}">
                        </div>
                        @if($errors->has('client_id'))
                            <p class="text-xs text-red-700">{{ $errors->first('client_id') }}</p>
                        @endif
                        <div x-show="error" class="text-xs text-red-700" x-text="error"></div>
                        <button type="button" @click="saveClient()" :disabled="saving || !nc.name.trim()"
                            class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm disabled:opacity-50">
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
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('lawyer_id') border-red-500/50 @enderror"
                        {{ auth()->user()->isAdmin() || auth()->user()->isDeveloper() ? '' : 'disabled' }}>
                        <option value="">اختر محامي القضية</option>
                        @foreach($users ?? [] as $user)
                            <option value="{{ $user->id }}" {{ old('lawyer_id', auth()->id()) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('lawyer_id')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Opponent Data Card --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6 space-y-5">
            <h2 class="text-lg font-bold text-gold-dark border-b border-gray-200 pb-3">{{ __('app.opponent_data') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="opponent" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.opponent_name') }} <span class="text-red-700">*</span></label>
                    <input type="text" name="opponent" id="opponent" value="{{ old('opponent') }}" required
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('opponent') border-red-500/50 @enderror"
                        placeholder="{{ __('app.opponent_name_placeholder') }}">
                    @error('opponent')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="opponent_phone" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.opponent_phone') }}</label>
                    <input type="text" name="opponent_phone" id="opponent_phone" value="{{ old('opponent_phone') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('opponent_phone') border-red-500/50 @enderror"
                        placeholder="{{ __('app.opponent_phone_placeholder') }}">
                    @error('opponent_phone')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="opponent_address" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.opponent_address') }}</label>
                    <input type="text" name="opponent_address" id="opponent_address" value="{{ old('opponent_address') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('opponent_address') border-red-500/50 @enderror"
                        placeholder="{{ __('app.opponent_address_placeholder') }}">
                    @error('opponent_address')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="opponent_lawyer" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.opponent_lawyer') }}</label>
                    <input type="text" name="opponent_lawyer" id="opponent_lawyer" value="{{ old('opponent_lawyer') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('opponent_lawyer') border-red-500/50 @enderror"
                        placeholder="{{ __('app.opponent_lawyer_placeholder') }}">
                    @error('opponent_lawyer')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="opponent_civil_number" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.opponent_civil_number') }}</label>
                    <input type="text" name="opponent_civil_number" id="opponent_civil_number" value="{{ old('opponent_civil_number') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('opponent_civil_number') border-red-500/50 @enderror"
                        placeholder="{{ __('app.opponent_civil_number_placeholder') }}">
                    @error('opponent_civil_number')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Sessions Card --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6 space-y-4" x-data="sessionsManager()">
            <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                <h2 class="text-lg font-bold text-gold-dark">{{ __('app.sessions') }}</h2>
                <button type="button" @click="addSession()" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg font-semibold transition-colors text-sm inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('app.add_session') }}
                </button>
            </div>

            <template x-for="(s, i) in sessions" :key="i">
                <div class="border border-gray-200 rounded-xl p-4 space-y-3 relative">
                    <button type="button" @click="removeSession(i)" class="absolute top-2 left-2 text-red-500 hover:text-red-700 transition-colors" title="{{ __('app.delete') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.table_date') }} <span class="text-red-700">*</span></label>
                            <input type="datetime-local" :name="'sessions['+i+'][date]'" x-model="s.date" required
                                class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.status') }}</label>
                            <select :name="'sessions['+i+'][status]'" x-model="s.status"
                                class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                                <option value="upcoming">{{ __('app.status_upcoming') }}</option>
                                <option value="completed">{{ __('app.status_completed') }}</option>
                                <option value="postponed">{{ __('app.status_postponed') }}</option>
                                <option value="cancelled">{{ __('app.status_cancelled') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.session_decision') }}</label>
                            <input type="text" :name="'sessions['+i+'][report]'" x-model="s.report"
                                class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                                placeholder="{{ __('app.session_decision_placeholder') }}">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1">{{ __('app.table_notes') }}</label>
                        <textarea :name="'sessions['+i+'][notes]'" x-model="s.notes" rows="2"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 resize-y"
                            placeholder="{{ __('app.session_notes_placeholder') }}"></textarea>
                    </div>
                </div>
            </template>

            <p x-show="sessions.length === 0" class="text-center py-6 text-gray-400 text-sm">{{ __('app.no_sessions_recorded') }}</p>
        </div>

        <script nonce="{{ $cspNonce }}">
        function sessionsManager() {
            return {
                sessions: [],
                addSession() {
                    this.sessions.push({ date: '', location: '', status: 'upcoming', report: '', notes: '' });
                },
                removeSession(i) {
                    this.sessions.splice(i, 1);
                }
            }
        }
        </script>

        {{-- Status & Priority Card --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6 space-y-5">
            <h2 class="text-lg font-bold text-gold-dark border-b border-gray-200 pb-3">{{ __('app.case_status_priority') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Status --}}
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.status') }}</label>
                    <select name="status" id="status"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('status') border-red-500/50 @enderror">
                        <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>{{ __('app.status_active') }}</option>
                        <option value="pending" {{ old('status') === 'pending' ? 'selected' : '' }}>{{ __('app.status_pending') }}</option>
                        <option value="closed" {{ old('status') === 'closed' ? 'selected' : '' }}>{{ __('app.status_closed') }}</option>
                        <option value="won" {{ old('status') === 'won' ? 'selected' : '' }}>{{ __('app.status_won') }}</option>
                        <option value="lost" {{ old('status') === 'lost' ? 'selected' : '' }}>{{ __('app.status_lost') }}</option>
                        <option value="adjudicated" {{ old('status') === 'adjudicated' ? 'selected' : '' }}>{{ __('app.status_adjudicated') }}</option>
                        <option value="fees_pending" {{ old('status') === 'fees_pending' ? 'selected' : '' }}>{{ __('app.status_fees_pending') }}</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Priority --}}
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-400 mb-1.5">{{ __('app.priority') }}</label>
                    <select name="priority" id="priority"
                        class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('priority') border-red-500/50 @enderror">
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
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                {{ __('app.add_case_button') }}
            </button>
            <a href="{{ route('cases.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                {{ __('app.cancel') }}
            </a>
        </div>
    </form>
</div>

@endsection
