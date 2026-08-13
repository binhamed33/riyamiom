@php
    $isRtl = app()->getLocale() === 'ar';
@endphp

@extends('layouts.public')

@section('title', 'الدخول للبوابة')

@section('content')
<div class="flex items-center justify-center min-h-[70vh]">
    <div class="w-full max-w-md animate-slide-up">
        <div class="bg-white rounded-2xl sm:rounded-3xl shadow-xl shadow-gold/20 border border-gold/15 overflow-hidden">
            {{-- Header --}}
            <div class="bg-gradient-to-l from-gold via-gold-dark to-gold-deep px-6 sm:px-8 py-8 sm:py-10 text-center relative overflow-hidden">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_120%,rgba(255,255,255,0.1),transparent_70%)]"></div>
                <div class="relative">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 bg-white/15 rounded-2xl flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
                        <svg class="w-7 h-7 sm:w-8 sm:h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-bold text-white font-heading">متابعة القضايا</h2>
                    <p class="text-gold-light/80 text-sm sm:text-base mt-1.5">أدخل بياناتك للاطلاع على قضاياك</p>
                </div>
            </div>

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="mx-6 sm:mx-8 mt-5 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mx-6 sm:mx-8 mt-5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('client.access.lookup') }}" class="p-6 sm:p-8 space-y-5">
                @csrf

                <div>
                    <label for="credential" class="block text-sm font-medium text-gray-600 mb-2">
                        البريد الإلكتروني أو رقم الهاتف
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                            </svg>
                        </div>
                        <input type="text" id="credential" name="credential" value="{{ old('credential') }}"
                            class="w-full px-4 py-3.5 pr-11 rounded-xl border-2 border-gray-200 focus:border-gold-dark focus:ring-4 focus:ring-gold/20 outline-none transition-all text-sm bg-gray-50/50 hover:bg-white focus:bg-white"
                            placeholder="example@email.com أو 09xxxxxxxx"
                            autofocus
                            autocomplete="off">
                    </div>
                    @error('credential')
                        <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <button type="submit"
                    class="w-full bg-gradient-to-l from-gold via-gold-dark to-gold-deep text-white font-bold py-3.5 rounded-xl hover:from-gold-dark hover:via-gold-deep hover:to-gold-deep transition-all shadow-lg shadow-gold/20 active:scale-[0.98] text-sm sm:text-base">
                    <span class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                        استعلام
                    </span>
                </button>
            </form>

            <div class="px-6 sm:px-8 pb-6 sm:pb-8 text-center">
                <p class="text-xs text-gray-400">سيتم عرض القضايا المسجلة باسمك فقط</p>
            </div>
        </div>

        <div class="mt-4 sm:mt-6 text-center">
            <p class="text-xs text-gray-400 leading-relaxed">
                للمساعدة والاستفسار، يرجى التواصل مع المكتب
            </p>
        </div>
    </div>
</div>
@endsection
