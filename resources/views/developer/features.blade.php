@extends('layouts.app')

@section('title', 'إدارة الميزات')

@section('content')
<div class="space-y-6" dir="rtl">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-amber-600">🔘 إدارة الميزات</h1>
        <a href="{{ route('developer.index') }}" class="text-gray-400 text-sm hover:text-amber-600 transition">&larr; العودة للوحة المطور</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-200 text-green-700 px-5 py-3 rounded-xl text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-200 text-red-700 px-5 py-3 rounded-xl text-sm font-medium">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-gray-500 text-sm mb-5">
            من هنا تقدر تطفى أو تشغل أي ميزة في النظام. لما تطفّي ميزة، المستخدمين غير المطور بينحرفون للوحة الرئيسية مع رسالة "قيد التطوير".
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($features as $key => $label)
                <div class="flex items-center justify-between p-4 rounded-xl border border-gray-200 hover:border-amber-300 transition">
                    <span class="text-sm font-medium text-gray-700">{{ $label }}</span>
                    <form action="{{ route('developer.features.toggle') }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="key" value="{{ $key }}">
                        <input type="hidden" name="value" value="{{ $statuses[$key] === '1' ? '0' : '1' }}">
                        <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 {{ $statuses[$key] === '1' ? 'bg-red-400' : 'bg-green-400' }}">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-200 {{ $statuses[$key] === '1' ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
