@extends('layouts.public')

@section('title', 'بوابة الموكل')

@section('content')
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md animate-fade-in">
        <div class="bg-white rounded-2xl shadow-xl border border-amber-200 overflow-hidden">
            <div class="bg-gradient-to-l from-amber-600 to-amber-700 px-6 py-8 text-center">
                <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white font-heading">بوابة الموكل</h1>
                <p class="text-amber-200 text-sm mt-1">الاستعلام عن القضايا</p>
            </div>

            @if(session('success'))
                <div class="mx-6 mt-4 bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm font-medium">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mx-6 mt-4 bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm font-medium">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('client.access.lookup') }}" class="p-6 space-y-5">
                @csrf

                <div>
                    <label for="credential" class="block text-sm font-medium text-gray-600 mb-1.5">البريد الإلكتروني أو رقم الهاتف</label>
                    <input type="text" id="credential" name="credential" value="{{ old('credential') }}"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-amber-400 focus:ring-2 focus:ring-amber-200 outline-none transition text-sm"
                        placeholder="example@email.com أو 09xxxxxxxx"
                        autofocus>
                    @error('credential')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="w-full bg-gradient-to-l from-amber-600 to-amber-700 text-white font-bold py-3 rounded-xl hover:from-amber-700 hover:to-amber-800 transition shadow-lg shadow-amber-200">
                    استعلام
                </button>
            </form>

            <div class="px-6 pb-6 text-center">
                <p class="text-xs text-gray-400">سيتم عرض القضايا المسجلة باسمك فقط</p>
            </div>
        </div>
    </div>
</div>
@endsection
