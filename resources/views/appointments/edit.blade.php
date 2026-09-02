@extends('layouts.app')

@section('title', 'تعديل موعد')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-gray-800">تعديل موعد</h1>
        <p class="text-xs text-gray-500 mt-1">
            تغييرُ الوقت أو الإلغاء يصل الموكّلَ رسالةً؛ أمّا الملاحظاتُ والحالةُ «تمّ» فلا.
        </p>
    </div>

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm px-4 py-3">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('appointments.update', $appointment) }}" class="bg-white rounded-2xl border border-gray-200 p-5">
        @csrf @method('PUT')
        @include('appointments._form')

        <div class="mt-4">
            <label class="block text-xs font-semibold text-gray-600 mb-1" for="status">الحالة</label>
            <select id="status" name="status" class="w-full md:w-1/2 rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
                @foreach(\App\Models\Appointment::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $appointment->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2 mt-6">
            <button class="text-xs font-bold px-5 py-2.5 rounded-xl bg-gold text-white hover:bg-gold-dark">حفظ</button>
            <a href="{{ route('appointments.index') }}" class="text-xs px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600">رجوع</a>
        </div>
    </form>

    <form method="POST" action="{{ route('appointments.destroy', $appointment) }}" class="mt-3"
          data-confirm="حذفُ الموعد يُلغيه ويُبلغ الموكّل. أتريد المتابعة؟">
        @csrf @method('DELETE')
        <button class="text-[11px] text-red-600 hover:underline">حذف الموعد</button>
    </form>
</div>

@push('scripts')
{{-- التأكيدُ يُربط هنا لا في onsubmit: سياسةُ CSP تمنع السكربتَ المضمّن --}}
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.dataset.confirm)) { e.preventDefault(); }
        });
    });
});
</script>
@endpush
@endsection
