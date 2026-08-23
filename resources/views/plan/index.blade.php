@extends('layouts.app')

@section('title', 'باقة المكتب')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900">باقة المكتب</h2>
        @if ($planName)
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-gold/12 text-gold-dark border border-gold/20">
                {{ $planName }}
            </span>
        @endif
    </div>

    @if (! $report)
        {{-- لم تصل حدود بعد: المكتب يعمل بلا قيد، ولا نُخيف بلا سبب --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <p class="text-sm text-gray-600 leading-relaxed">
                لم تصل بيانات الباقة إلى هذا المكتب بعد. النظام يعمل بلا قيود حتى تصل،
                @if ($linked)
                    وتصل تلقائياً خلال ساعة من ربطه بلوحة مُداوَلة.
                @else
                    وهذا المكتب غير مربوط بلوحة مُداوَلة حالياً.
                @endif
            </p>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-5">استهلاكك من حدود الباقة</h3>

            <div class="space-y-4">
                @foreach ($report as $row)
                    <div>
                        <div class="flex items-baseline justify-between mb-1.5">
                            <span class="text-sm font-medium text-gray-700">{{ $row['label'] }}</span>
                            <span class="text-sm font-bold {{ $row['reached'] ? 'text-red-600' : ($row['percent'] >= 80 ? 'text-amber-600' : 'text-gray-800') }}" dir="ltr">
                                {{ $row['used'] }} / {{ $row['limit'] }}
                            </span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full transition-all
                                        {{ $row['reached'] ? 'bg-red-500' : ($row['percent'] >= 80 ? 'bg-amber-500' : 'bg-gold-dark') }}"
                                 style="width: {{ max(2, $row['percent']) }}%"></div>
                        </div>
                        @if ($row['reached'])
                            <p class="mt-1 text-xs text-red-600">بلغتَ الحدّ — لا يمكن إضافة المزيد حتى تُرقّى الباقة.</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <p class="mt-5 text-xs text-gray-500 leading-relaxed">
                بياناتك كلّها محفوظة مهما بلغ الاستهلاك — الحدّ يمنع الإضافة الجديدة فقط، ولا يُحذف منها شيء أبداً.
            </p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">تحتاج مساحة أوسع؟</h3>
            <p class="text-sm text-gray-600 mb-4 leading-relaxed">
                أرسل طلب ترقية ويصل فريق مُداوَلة مباشرةً. لن يتغيّر شيء في مكتبك حتى تُوافق على الباقة الجديدة.
            </p>

            @error('upgrade')
                <p class="mb-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3">{{ $message }}</p>
            @enderror

            <form method="POST" action="{{ route('plan.upgrade') }}" class="space-y-3">
                @csrf
                <textarea name="reason" rows="2" maxlength="500"
                          class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                          placeholder="ما الذي تحتاجه؟ (اختياري)"></textarea>
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                    أرسل طلب ترقية
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
