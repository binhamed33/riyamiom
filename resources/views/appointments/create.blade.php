@extends('layouts.app')

@section('title', 'حجز موعد')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-gray-800">حجز موعد</h1>
        <p class="text-xs text-gray-500 mt-1">يصل الموكّلَ تأكيدٌ على واتساب وبريدُه فور الحفظ.</p>
    </div>

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm px-4 py-3">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('appointments.store') }}" class="bg-white rounded-2xl border border-gray-200 p-5">
        @csrf
        @include('appointments._form')

        <div class="flex items-center gap-2 mt-6">
            <button class="text-xs font-bold px-5 py-2.5 rounded-xl bg-gold text-white hover:bg-gold-dark">احجز وأبلغ الموكّل</button>
            <a href="{{ route('appointments.index') }}" class="text-xs px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600">إلغاء</a>
        </div>
    </form>
</div>
@endsection
