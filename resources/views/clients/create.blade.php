@extends('layouts.app')

@section('title', __('app.page_add_client'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold">{{ __('app.page_add_client') }}</h1>
        <a href="{{ route('clients.index') }}" class="text-ivory/50 hover:text-ivory transition">{{ __('app.back_to_list') }}</a>
    </div>

    <form method="POST" action="{{ route('clients.store') }}" class="bg-navy-light rounded-xl border border-ivory/10 p-8 space-y-6">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-gold mb-2">{{ __('app.client_full_name') }} <span class="text-red-400">*</span></label>
            <input
                type="text"
                name="name"
                id="name"
                value="{{ old('name') }}"
                class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                required
            >
            @error('name')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gold mb-3">{{ __('app.client_type') }} <span class="text-red-400">*</span></label>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="type" value="individual" {{ old('type') === 'individual' ? 'checked' : '' }} class="w-4 h-4 text-gold bg-navy border-ivory/30 focus:ring-gold" required>
                    <span class="text-ivory">{{ __('app.individual') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="type" value="company" {{ old('type') === 'company' ? 'checked' : '' }} class="w-4 h-4 text-gold bg-navy border-ivory/30 focus:ring-gold">
                    <span class="text-ivory">{{ __('app.company') }}</span>
                </label>
            </div>
            @error('type')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="phone" class="block text-sm font-medium text-gold mb-2">{{ __('app.client_phone') }}</label>
                <input
                    type="text"
                    name="phone"
                    id="phone"
                    value="{{ old('phone') }}"
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                    dir="ltr"
                >
                @error('phone')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gold mb-2">{{ __('app.client_email') }}</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email') }}"
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-gold mb-2">{{ __('app.client_address') }}</label>
            <textarea
                name="address"
                id="address"
                rows="3"
                class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] resize-none"
            >{{ old('address') }}</textarea>
            @error('address')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="national_id" class="block text-sm font-medium text-gold mb-2">{{ __('app.client_national_id') }}</label>
                <input
                    type="text"
                    name="national_id"
                    id="national_id"
                    value="{{ old('national_id') }}"
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                    dir="ltr"
                >
                @error('national_id')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="company_name" class="block text-sm font-medium text-gold mb-2">{{ __('app.company_name') }}</label>
                <input
                    type="text"
                    name="company_name"
                    id="company_name"
                    value="{{ old('company_name') }}"
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                >
                @error('company_name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex items-center gap-4 pt-4 border-t border-ivory/10">
            <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.add_client') }}</button>
            <a href="{{ route('clients.index') }}" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">{{ __('app.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
