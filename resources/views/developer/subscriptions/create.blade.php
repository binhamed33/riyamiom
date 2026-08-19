@extends('layouts.app')

@section('title', 'إنشاء اشتراك')

@section('content')
<style nonce="{{ $cspNonce }}">
    .duration-option input:checked + span {
        background-color: #D4AF37;
        border-color: #D4AF37;
        color: #ffffff;
        box-shadow: 0 4px 14px rgba(212, 175, 55, 0.25);
    }
</style>
<div class="max-w-2xl mx-auto space-y-6" dir="rtl">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gold-dark">إنشاء اشتراك جديد</h1>
        <a href="{{ route('developer.subscriptions.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            رجوع
        </a>
    </div>

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

    <form method="POST" action="{{ route('developer.subscriptions.store') }}" class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
        @csrf

        <div>
            <label for="tenant_id" class="block text-sm font-medium text-gray-700 mb-2">العميل / مكتب المحاماة <span class="text-red-500">*</span></label>
            <select name="tenant_id" id="tenant_id" required
                class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                <option value="">اختر العميل...</option>
                @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}" {{ old('tenant_id') == $tenant->id || request('tenant_id') == $tenant->id ? 'selected' : '' }} {{ $tenant->subscription?->isActive() ? 'disabled' : '' }}>
                        {{ $tenant->name }}{{ $tenant->subscription?->isActive() ? ' (لديه اشتراك نشط)' : '' }}
                    </option>
                @endforeach
            </select>
            @error('tenant_id')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">مدة الاشتراك <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                @foreach($durations as $d)
                    <label class="cursor-pointer duration-option">
                        <input type="radio" name="duration" value="{{ $d }}" {{ old('duration', 3) == $d ? 'checked' : '' }} class="sr-only" required>
                        <span class="block text-center text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg py-3 transition-colors duration-150">
                            {{ \App\Services\SubscriptionService::durationLabel($d) }}
                        </span>
                    </label>
                @endforeach
            </div>
            @error('duration')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div class="bg-gradient-to-br from-gold/10 to-transparent border border-gold/20 rounded-xl p-4 text-sm text-gray-600 leading-relaxed">
            <p class="font-semibold text-gold-dark mb-1">يُحسب تاريخ الانتهاء تلقائيًا:</p>
            <p>تاريخ البدء (اليوم: <span class="font-semibold" dir="ltr">{{ now()->format('d/m/Y') }}</span>) + المدة المختارة = تاريخ الانتهاء</p>
            <p class="mt-1 text-xs text-gray-400">مثال: بدء 19/08/2026 + 3 أشهر = انتهاء 19/11/2026</p>
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">ملاحظات (اختياري)</label>
            <textarea name="notes" id="notes" rows="2" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" placeholder="أي تفاصيل إضافية...">{{ old('notes') }}</textarea>
        </div>

        <button type="submit" class="w-full bg-gold hover:bg-gold-dark text-white px-6 py-3 rounded-lg font-bold transition-colors shadow-sm">إنشاء الاشتراك</button>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">إضافة عميل / مكتب جديد</h2>
        <p class="text-sm text-gray-500 mb-4">لم يظهر العميل في القائمة؟ أضفه أولًا ثم أنشئ له اشتراكًا.</p>
        <form method="POST" action="{{ route('developer.subscriptions.tenant.store') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">اسم المكتب <span class="text-red-500">*</span></label>
                <input type="text" name="name" required class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">اسم الحساب / المدير</label>
                <input type="text" name="owner_name" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                <input type="email" name="email" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" dir="ltr">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف</label>
                <input type="text" name="phone" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" dir="ltr">
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition-colors">إضافة العميل</button>
            </div>
        </form>
    </div>
</div>
@endsection