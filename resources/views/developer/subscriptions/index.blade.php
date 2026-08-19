@extends('layouts.app')

@section('title', 'إدارة الاشتراكات')

@section('content')
<style nonce="{{ $cspNonce }}">
    .duration-option input:checked + span {
        background-color: #D4AF37;
        border-color: #D4AF37;
        color: #ffffff;
        box-shadow: 0 4px 14px rgba(212, 175, 55, 0.25);
    }
    .duration-option-green input:checked + span {
        background-color: #10B981;
        border-color: #10B981;
        color: #ffffff;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);
    }
</style>
<div class="space-y-6" dir="rtl" x-data="{
        openModal(id) { const el = document.getElementById(id); if (!el) return; el.classList.remove('hidden'); el.classList.add('flex'); },
        closeModal(id) { const el = document.getElementById(id); if (!el) return; el.classList.add('hidden'); el.classList.remove('flex'); },
        confirmAction(formId, message) { if (confirm(message)) document.getElementById(formId).submit(); }
    }">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gold-dark flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
                إدارة الاشتراكات
            </h1>
            <p class="text-sm text-gray-500 mt-1">إدارة اشتراكات العملاء ومكاتب المحاماة — صلاحية المطور فقط</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('developer.subscriptions.create') }}" class="inline-flex items-center gap-2 bg-gold hover:bg-gold-dark text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                إنشاء اشتراك
            </a>
            <a href="#new-tenant" @click="openModal('newTenantModal')" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                إضافة عميل
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-3 rounded-xl text-sm font-medium">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-5 py-3 rounded-xl text-sm font-medium">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-5 py-3 rounded-xl text-sm font-medium">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <p class="text-gray-400 text-xs font-medium">إجمالي العملاء</p>
                <span class="text-2xl font-bold text-gray-800">{{ $stats['total'] }}</span>
            </div>
            <p class="text-xs text-gray-400 mt-1">مكاتب المحاماة المسجلة</p>
        </div>
        <div class="bg-white rounded-xl border border-emerald-200 p-5">
            <div class="flex items-center justify-between">
                <p class="text-gray-400 text-xs font-medium">اشتراكات نشطة</p>
                <span class="text-2xl font-bold text-emerald-600">{{ $stats['active'] }}</span>
            </div>
            <p class="text-xs text-emerald-500 mt-1">ضمن فترة الصلاحية</p>
        </div>
        <div class="bg-white rounded-xl border border-amber-200 p-5">
            <div class="flex items-center justify-between">
                <p class="text-gray-400 text-xs font-medium">قريبة من الانتهاء</p>
                <span class="text-2xl font-bold text-amber-600">{{ $stats['expiring'] }}</span>
            </div>
            <p class="text-xs text-amber-500 mt-1">تبقى 7 أيام أو أقل</p>
        </div>
        <div class="bg-white rounded-xl border border-red-200 p-5">
            <div class="flex items-center justify-between">
                <p class="text-gray-400 text-xs font-medium">منتهي / متوقف / بدون اشتراك</p>
                <span class="text-2xl font-bold text-red-600">{{ $stats['blocked'] + $stats['no_subscription'] }}</span>
            </div>
            <p class="text-xs text-red-400 mt-1">بحاجة إلى إجراء</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('developer.subscriptions.index') }}" class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-[220px] relative">
            <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالاسم أو البريد أو الهاتف..."
                class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 pl-4 pr-9 py-2.5 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
        </div>
        <select name="status" class="rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
            <option value="">كل الحالات</option>
            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>🟢 نشط</option>
            <option value="expiring_soon" {{ $status === 'expiring_soon' ? 'selected' : '' }}>🟠 قريب من الانتهاء</option>
            <option value="expired" {{ $status === 'expired' ? 'selected' : '' }}>🔴 منتهي</option>
            <option value="suspended" {{ $status === 'suspended' ? 'selected' : '' }}>⚫ متوقف يدويًا</option>
            <option value="none" {{ $status === 'none' ? 'selected' : '' }}>بدون اشتراك</option>
        </select>
        <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-colors">بحث</button>
        <a href="{{ route('developer.subscriptions.index') }}" class="text-sm text-gray-500 hover:text-gray-700">مسح الفلتر</a>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr class="text-right text-xs text-gray-500 font-semibold uppercase tracking-wide">
                        <th class="px-5 py-3.5">العميل / المكتب</th>
                        <th class="px-5 py-3.5">الحساب</th>
                        <th class="px-5 py-3.5">الحالة</th>
                        <th class="px-5 py-3.5">النوع</th>
                        <th class="px-5 py-3.5">البداية</th>
                        <th class="px-5 py-3.5">الانتهاء</th>
                        <th class="px-5 py-3.5 w-52">المتبقي</th>
                        <th class="px-5 py-3.5">آخر تحديث</th>
                        <th class="px-5 py-3.5">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                        @php
                            $t = $row->tenant;
                            $s = $row->subscription;
                            $info = $row->status;
                            $badge = \App\Services\SubscriptionService::colorClasses($info['color']);
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-gold/20 to-gold/40 flex items-center justify-center text-gold-dark font-bold text-sm">
                                        {{ mb_substr($t->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 text-sm">{{ $t->name }}</p>
                                        @if($t->owner_name)
                                            <p class="text-xs text-gray-400">{{ $t->owner_name }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm text-gray-700" dir="ltr">{{ $t->email ?? '—' }}</p>
                                <p class="text-xs text-gray-400" dir="ltr">{{ $t->phone ?? '' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border {{ $badge }}">
                                    @if($info['key'] === 'active') 🟢
                                    @elseif($info['key'] === 'expiring_soon') 🟠
                                    @elseif($info['key'] === 'expired') 🔴
                                    @elseif($info['key'] === 'suspended') ⚫
                                    @else ⚪
                                    @endif
                                    {{ $info['label'] }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-700">
                                {{ $s ? \App\Services\SubscriptionService::durationLabel($s->plan_duration_months) : '—' }}
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-600">{{ $s ? $s->start_date->format('d/m/Y') : '—' }}</td>
                            <td class="px-5 py-4 text-sm text-gray-600" dir="ltr">{{ $s ? $s->end_date->format('d/m/Y') : '—' }}</td>
                            <td class="px-5 py-4">
                                @if($s && $info['key'] !== 'suspended' && $info['key'] !== 'none')
                                    @php
                                        $ratio = (int) round($s->elapsedRatio() * 100);
                                        $bar = $info['key'] === 'expired' ? 'bg-red-500' : ($info['key'] === 'expiring_soon' ? 'bg-amber-500' : 'bg-emerald-500');
                                    @endphp
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-gray-600 font-medium">{{ $info['key'] === 'expired' ? 'منتهي' : $info['remaining_days'] . ' يوم' }}</span>
                                            <span class="text-gray-400">{{ $ratio }}%</span>
                                        </div>
                                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full transition-all {{ $bar }}" style="width: {{ $ratio }}%"></div>
                                        </div>
                                        @if($info['key'] !== 'expired')
                                            <p class="text-[11px] text-gray-400 font-mono" dir="ltr"
                                               x-data="{
                                                   endTs: {{ $s->end_date->endOfDay()->getTimestamp() }},
                                                   remaining: {{ $s->secondsRemaining() }},
                                                   init() { const self = this; const tick = () => { self.remaining = Math.max(0, self.endTs - Math.floor(Date.now() / 1000)); }; tick(); this._t = setInterval(tick, 1000); },
                                                   destroy() { if (this._t) clearInterval(this._t); }
                                               }">
                                                <span x-text="Math.floor(this.remaining / 86400) + 'd · ' + Math.floor(this.remaining % 86400 / 3600) + 'h · ' + Math.floor(this.remaining % 3600 / 60) + 'm · ' + (this.remaining % 60) + 's'"></span>
                                            </p>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-sm text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-xs text-gray-400" dir="ltr">{{ $s ? $s->updated_at?->format('d/m/Y H:i') : '—' }}</td>
                            <td class="px-5 py-4">
                                @if($s)
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <button @click="openModal('extendModal{{ $t->id }}')" class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-2.5 py-1.5 rounded-lg transition-colors" title="تمديد">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            تمديد
                                        </button>
                                        <button @click="openModal('changeModal{{ $t->id }}')" class="inline-flex items-center gap-1 text-xs font-medium text-gold-dark hover:text-amber-700 bg-amber-50 hover:bg-amber-100 px-2.5 py-1.5 rounded-lg transition-colors" title="تغيير">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                                            تغيير
                                        </button>
                                        @if(!$s->isSuspended())
                                            <button @click="confirmAction('suspendForm{{ $t->id }}', 'إيقاف اشتراك {{ $t->name }}؟')" class="inline-flex items-center gap-1 text-xs font-medium text-gray-600 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 px-2.5 py-1.5 rounded-lg transition-colors" title="إيقاف">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                إيقاف
                                            </button>
                                        @else
                                            <button @click="openModal('reactivateModal{{ $t->id }}')" class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1.5 rounded-lg transition-colors" title="إعادة تفعيل">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                                تفعيل
                                            </button>
                                        @endif
                                        <button @click="confirmAction('terminateForm{{ $t->id }}', 'إنهاء اشتراك {{ $t->name }} نهائيًا؟ لا يمكن التراجع.')" class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded-lg transition-colors" title="إنهاء">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            إنهاء
                                        </button>
                                    </div>
                                @else
                                    <a href="{{ route('developer.subscriptions.create', ['tenant_id' => $t->id]) }}" class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1.5 rounded-lg transition-colors">
                                        + اشتراك
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-12 text-center text-gray-400">لا توجد نتائج مطابقة</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($rows, 'links'))
            <div class="px-5 py-3 border-t border-gray-100">{{ $rows->links() }}</div>
        @endif
    </div>
</div>

{{-- Hidden forms for actions --}}
@foreach($rows as $row)
    @php
        $t = $row->tenant;
        $s = $row->subscription;
    @endphp
    @if($s)
        <form id="suspendForm{{ $t->id }}" method="POST" action="{{ route('developer.subscriptions.suspend', $t) }}" class="hidden">@csrf</form>
        <form id="terminateForm{{ $t->id }}" method="POST" action="{{ route('developer.subscriptions.terminate', $t) }}" class="hidden">@csrf</form>

        <div id="extendModal{{ $t->id }}" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800">تمديد اشتراك {{ $t->name }}</h3>
                    <button type="button" @click="closeModal('extendModal{{ $t->id }}')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
                <p class="text-sm text-gray-500 mb-4">ينتهي الاشتراك حاليًا في <span class="font-semibold" dir="ltr">{{ $s->end_date->format('d/m/Y') }}</span>. اختر المدة المراد إضافتها:</p>
                <form method="POST" action="{{ route('developer.subscriptions.extend', $t) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-5 gap-2">
                        @foreach(\App\Models\Subscription::DURATION_OPTIONS as $d)
                            <label class="cursor-pointer duration-option">
                                <input type="radio" name="duration" value="{{ $d }}" {{ $d === 1 ? 'checked' : '' }} class="sr-only" required>
                                <span class="block text-center text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg py-2.5 transition-colors">{{ \App\Services\SubscriptionService::durationLabel($d) }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="w-full bg-gold hover:bg-gold-dark text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-colors">تمديد الاشتراك</button>
                </form>
            </div>
        </div>

        <div id="changeModal{{ $t->id }}" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800">تغيير اشتراك {{ $t->name }}</h3>
                    <button type="button" @click="closeModal('changeModal{{ $t->id }}')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
                <form method="POST" action="{{ route('developer.subscriptions.change', $t) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">المدة (إعادة حساب تاريخ الانتهاء من البداية)</label>
                        <div class="grid grid-cols-5 gap-2">
                            @foreach(\App\Models\Subscription::DURATION_OPTIONS as $d)
                                <label class="cursor-pointer duration-option">
                                    <input type="radio" name="duration" value="{{ $d }}" {{ $s->plan_duration_months === $d ? 'checked' : '' }} class="sr-only">
                                    <span class="block text-center text-xs font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg py-2.5 transition-colors">{{ $d }} شهر</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label for="changeEnd{{ $t->id }}" class="block text-sm font-medium text-gray-700 mb-2">أو تحديد تاريخ انتهاء مباشر</label>
                        <input type="date" name="end_date" id="changeEnd{{ $t->id }}" value="{{ $s->end_date->format('Y-m-d') }}"
                            class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" dir="ltr">
                    </div>
                    <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-colors">حفظ التغيير</button>
                </form>
            </div>
        </div>

        @if($s->isSuspended())
            <div id="reactivateModal{{ $t->id }}" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">إعادة تفعيل اشتراك {{ $t->name }}</h3>
                        <button type="button" @click="closeModal('reactivateModal{{ $t->id }}')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">اختر مدة الاشتراك الجديدة (إن كان الاشتراك منتهيًا فسيبدأ من اليوم):</p>
                    <form method="POST" action="{{ route('developer.subscriptions.reactivate', $t) }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-5 gap-2">
                            @foreach(\App\Models\Subscription::DURATION_OPTIONS as $d)
                                <label class="cursor-pointer duration-option-green">
                                    <input type="radio" name="duration" value="{{ $d }}" {{ $d === 1 ? 'checked' : '' }} class="sr-only" required>
                                    <span class="block text-center text-xs font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg py-2.5 transition-colors">{{ $d }} شهر</span>
                                </label>
                            @endforeach
                        </div>
                        <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-colors">إعادة التفعيل</button>
                    </form>
                </div>
            </div>
        @endif
    @endif
@endforeach

{{-- Add Tenant Modal --}}
<div id="newTenantModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">إضافة عميل / مكتب محاماة جديد</h3>
            <button type="button" @click="closeModal('newTenantModal')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('developer.subscriptions.tenant.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">اسم المكتب <span class="text-red-500">*</span></label>
                <input type="text" name="name" required class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم الحساب / المدير</label>
                    <input type="text" name="owner_name" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                    <input type="email" name="email" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" dir="ltr">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف</label>
                <input type="text" name="phone" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" dir="ltr">
            </div>
            <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-colors">إضافة العميل</button>
        </form>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce }}">
    document.querySelectorAll('.fixed.inset-0.z-50').forEach(function (m) {
        m.addEventListener('click', function (e) {
            if (e.target === m) m.classList.add('hidden');
        });
    });
</script>
@endpush
@endsection