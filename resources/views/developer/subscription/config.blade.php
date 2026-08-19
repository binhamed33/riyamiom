@extends('layouts.app')

@section('title', 'Subscription Configuration')

@section('content')
<style nonce="{{ $cspNonce }}">
    .duration-option input:checked + span {
        background-color: #D4AF37;
        border-color: #D4AF37;
        color: #ffffff;
        box-shadow: 0 4px 14px rgba(212, 175, 55, 0.25);
    }
</style>

@php
    $statusKey = $info['key'];
    $isLive = in_array($statusKey, ['active', 'expiring_soon'], true);
    $isSuspended = $statusKey === 'suspended';
    $noneOrExpired = in_array($statusKey, ['none', 'expired'], true);
    $elapsedPct = 0;
    if ($info['start_at'] && $info['end_at']) {
        $total = max(1, (int) $info['start_at']->diffInSeconds($info['end_at']));
        $elapsed = max(0, min($total, (int) $info['start_at']->diffInSeconds(now())));
        $elapsedPct = (int) round($elapsed / $total * 100);
    }
@endphp

<div class="max-w-3xl mx-auto space-y-6" dir="rtl" x-data="{
        openModal(id) { const el = document.getElementById(id); if (!el) return; el.classList.remove('hidden'); el.classList.add('flex'); },
        closeModal(id) { const el = document.getElementById(id); if (!el) return; el.classList.add('hidden'); el.classList.remove('flex'); },
        confirmAction(formId, message) { if (confirm(message)) document.getElementById(formId).submit(); }
    }">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gold-dark flex items-center gap-2 uppercase tracking-widest">Subscription Configuration</h1>
            <p class="text-sm text-gray-500 mt-1">اشتراك هذه النسخة من النظام — صلاحية المطور فقط</p>
        </div>
        <div class="text-xs text-gray-400" dir="ltr">Server Time: {{ now()->format('d M Y H:i:s') }}</div>
    </div>

    {{-- Flash --}}
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

    {{-- Hero panel --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
        <div class="px-6 sm:px-8 py-6 border-b border-gray-100 flex items-center justify-between">
            <span class="text-xs font-bold tracking-widest text-gray-400 uppercase">SUBSCRIPTION</span>
            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold border {{ \App\Services\SubscriptionService::colorClasses($info['color']) }}">
                @if($isLive) <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                @elseif($statusKey === 'suspended') <span class="w-2 h-2 rounded-full bg-gray-600"></span>
                @elseif($statusKey === 'expired') <span class="w-2 h-2 rounded-full bg-red-500"></span>
                @else <span class="w-2 h-2 rounded-full bg-gray-300"></span>
                @endif
                {{ $info['label'] }}
            </span>
        </div>

        <div class="px-6 sm:px-8 py-8">
            @if($noneOrExpired)
                <div class="text-center py-6">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800 mb-1">{{ $statusKey === 'expired' ? 'انتهت صلاحية الاشتراك' : 'لا يوجد اشتراك مفعّل' }}</h2>
                    <p class="text-sm text-gray-500 mb-6">اختر مدة الاشتراك ثم اضغط Activate Subscription. سيبدأ الحساب من وقت السيرفر الحالي.</p>
                    <button @click="openModal('activateModal')" class="inline-flex items-center gap-2 bg-gold hover:bg-gold-dark text-white px-7 py-3 rounded-xl font-bold transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Activate Subscription
                    </button>
                </div>
            @elseif($isSuspended)
                <div class="text-center py-6">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800 mb-1">الاشتراك متوقف يدويًا</h2>
                    <p class="text-sm text-gray-500 mb-2">التواريخ الأصلية محفوظة:</p>
                    <p class="text-sm text-gray-700 mb-6">
                        <span dir="ltr">{{ $info['start_at']?->format('d M Y') }}</span> ← <span dir="ltr">{{ $info['end_at']?->format('d M Y') }}</span>
                    </p>
                    <button @click="openModal('reactivateModal')" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-7 py-3 rounded-xl font-bold transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Reactivate Subscription
                    </button>
                </div>
            @else
                {{-- Active / Expiring --}}
                <div class="flex flex-col sm:flex-row sm:items-center gap-8">
                    <div class="flex-1">
                        <p class="text-xs text-gray-400 mb-1">DURATION</p>
                        <p class="text-3xl font-extrabold tracking-wide text-gray-800 mb-6">{{ strtoupper(\App\Services\SubscriptionService::durationLabel($info['duration_months'] ?? 0)) }}</p>

                        <div class="grid grid-cols-2 gap-6 max-w-xs">
                            <div>
                                <p class="text-xs text-gray-400 mb-1">STARTED</p>
                                <p class="text-sm font-semibold text-gray-700" dir="ltr">{{ $info['start_at']?->format('d M Y') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">EXPIRES</p>
                                <p class="text-sm font-semibold text-gray-700" dir="ltr">{{ $info['end_at']?->format('d M Y') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="sm:w-64 bg-gradient-to-br from-charcoal to-navy text-white rounded-2xl p-6 text-center" style="background:linear-gradient(150deg,#121826,#0D111B);">
                        <p class="text-[11px] tracking-widest text-gray-400 mb-1">REMAINING</p>
                        <p class="text-4xl font-extrabold text-gold-light mb-2" style="color:#F0D98A;">{{ $info['remaining_days'] }} <span class="text-base font-semibold">DAYS</span></p>
                        <p class="text-[11px] text-gray-400 font-mono" dir="ltr" x-data="{
                            endTs: {{ $info['end_timestamp'] }},
                            remaining: {{ max(0, $info['end_timestamp'] - now()->timestamp) }},
                            init() { const self = this; const tick = () => { self.remaining = Math.max(0, self.endTs - Math.floor(Date.now() / 1000)); }; tick(); this._t = setInterval(tick, 1000); },
                            destroy() { if (this._t) clearInterval(this._t); }
                        }">
                            <span x-text="Math.floor(this.remaining / 86400) + 'd ' + Math.floor(this.remaining % 86400 / 3600) + 'h ' + Math.floor(this.remaining % 3600 / 60) + 'm ' + (this.remaining % 60) + 's'"></span>
                        </p>
                        <div class="mt-3 h-1.5 bg-white/10 rounded-full overflow-hidden">
                            <div class="h-full rounded-full {{ $statusKey === 'expiring_soon' ? 'bg-amber-400' : 'bg-emerald-400' }}" style="width: {{ $elapsedPct }}%"></div>
                        </div>
                        <p class="mt-2 text-[10px] text-gray-500">{{ $elapsedPct }}% من المدة مستهلكة</p>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-100 flex flex-wrap items-center gap-3">
                    <button @click="openModal('changeModal')" class="inline-flex items-center gap-2 bg-gold hover:bg-gold-dark text-white px-6 py-2.5 rounded-xl font-bold text-sm transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                        Change Subscription
                    </button>
                    <button @click="confirmAction('suspendForm', 'إيقاف اشتراك النظام؟ سيتوقف وصول جميع المستخدمين حتى إعادة التفعيل.')" class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-xl font-bold text-sm transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        Suspend
                    </button>
                    @if($statusKey === 'expiring_soon')
                        <span class="text-xs text-amber-600 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-full font-medium">تبقى {{ $info['remaining_days'] }} يوم فقط — تواصل مع العميل للتجديد</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Info note --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 text-sm text-gray-500 leading-relaxed">
        <p class="font-semibold text-gray-700 mb-1">مصدر الحقيقة هو وقت السيرفر (Server Time)</p>
        <p>التحقق من الاشتراك يتم حصريًا في Backend: <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs" dir="ltr">server_time &lt; subscription_end_at</code>. عند الانتهاء يُقفل النظام تلقائيًا عن جميع المستخدمين غير المطوّرين، ولا يمكن تجاوزه من المتصفح أو تغيير تاريخ الجهاز.</p>
    </div>

    {{-- Suspend form (hidden) --}}
    <form id="suspendForm" method="POST" action="{{ route('developer.subscription.suspend') }}" class="hidden">@csrf</form>

    {{-- Activate modal --}}
    <div id="activateModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800">Activate Subscription</h3>
                <button type="button" @click="closeModal('activateModal')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form method="POST" action="{{ route('developer.subscription.activate') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">مدة الاشتراك <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                        @foreach($durations as $d)
                            <label class="cursor-pointer duration-option">
                                <input type="radio" name="duration" value="{{ $d }}" {{ $d === 3 ? 'checked' : '' }} class="sr-only" required>
                                <span class="block text-center text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg py-3 transition-colors">{{ \App\Services\SubscriptionService::durationLabel($d) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="bg-gold/10 border border-gold/20 rounded-xl px-4 py-3 text-xs text-gray-600 leading-relaxed">
                    سيبدأ الاشتراك من وقت السيرفر الحالي: <span class="font-semibold" dir="ltr">{{ now()->format('d M Y H:i') }}</span>
                </div>
                <button type="submit" class="w-full bg-gold hover:bg-gold-dark text-white px-5 py-3 rounded-xl font-bold transition-colors">Activate Subscription</button>
            </form>
        </div>
    </div>

    {{-- Change modal (active) --}}
    <div id="changeModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800">Change Subscription</h3>
                <button type="button" @click="closeModal('changeModal')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <p class="text-sm text-gray-500 mb-4">
                الاشتراك حاليًا <span class="font-semibold text-emerald-600">نشط</span> ومتبقي منه
                <span class="font-semibold">{{ $info['remaining_days'] }} يوم</span>
                (ينتهي <span dir="ltr" class="font-semibold">{{ $info['end_at']?->format('d M Y') }}</span>).
            </p>
            <form method="POST" action="{{ route('developer.subscription.activate') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">المدة الجديدة (يبدأ الحساب من الآن ويُلغي الوقت المتبقي)</label>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                        @foreach($durations as $d)
                            <label class="cursor-pointer duration-option">
                                <input type="radio" name="duration" value="{{ $d }}" {{ $d === 3 ? 'checked' : '' }} class="sr-only" required>
                                <span class="block text-center text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg py-3 transition-colors">{{ \App\Services\SubscriptionService::durationLabel($d) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <label class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 cursor-pointer">
                    <input type="checkbox" name="confirm" value="1" required class="mt-0.5 w-4 h-4 text-amber-600 rounded focus:ring-amber-500">
                    <span class="text-xs text-amber-800 leading-relaxed">أؤكد أنني أريد استبدال الاشتراك الحالي وفقدان الوقت المتبقي (<b>{{ $info['remaining_days'] }} يوم</b>).</span>
                </label>
                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white px-5 py-3 rounded-xl font-bold transition-colors">حفظ الاشتراك الجديد</button>
            </form>
        </div>
    </div>

    {{-- Reactivate modal --}}
    <div id="reactivateModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800">Reactivate Subscription</h3>
                <button type="button" @click="closeModal('reactivateModal')" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <p class="text-sm text-gray-500 mb-4">
                إعادة تفعيل الاشتراك مع الحفاظ على التواريخ الأصلية (تنتهي <span dir="ltr" class="font-semibold">{{ $info['end_at']?->format('d M Y') }}</span>).
            </p>
            <form method="POST" action="{{ route('developer.subscription.reactivate') }}" class="space-y-4">
                @csrf
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 rounded-xl font-bold transition-colors">Reactivate Subscription</button>
            </form>
        </div>
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