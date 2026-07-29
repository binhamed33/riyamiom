@extends('layouts.app')

@section('title', __('app.page_edit_client'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-amber-600">{{ __('app.page_edit_client') }}</h1>
        <a href="{{ route('clients.index') }}" class="text-gray-500 hover:text-gray-700 transition">{{ __('app.back_to_list') }}</a>
    </div>

    <form method="POST" action="{{ route('clients.update', $client) }}" class="bg-white rounded-xl border border-gray-200 p-8 space-y-6">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-sm font-medium text-amber-600 mb-2">{{ __('app.client_full_name') }} <span class="text-red-700">*</span></label>
            <input
                type="text"
                name="name"
                id="name"
                value="{{ old('name', $client->name) }}"
                class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                required
            >
            @error('name')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-amber-600 mb-3">{{ __('app.client_type') }} <span class="text-red-700">*</span></label>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="type" value="individual" {{ old('type', $client->type) === 'individual' ? 'checked' : '' }} class="w-4 h-4 text-amber-600 bg-white border-gray-300 focus:ring-amber-600" required>
                    <span class="text-gray-700">{{ __('app.individual') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="type" value="company" {{ old('type', $client->type) === 'company' ? 'checked' : '' }} class="w-4 h-4 text-amber-600 bg-white border-gray-300 focus:ring-amber-600">
                    <span class="text-gray-700">{{ __('app.company') }}</span>
                </label>
            </div>
            @error('type')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="phone" class="block text-sm font-medium text-amber-600 mb-2">{{ __('app.client_phone') }}</label>
                <input
                    type="text"
                    name="phone"
                    id="phone"
                    value="{{ old('phone', $client->phone) }}"
                    class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                    dir="ltr"
                >
                @error('phone')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-amber-600 mb-2">{{ __('app.client_email') }}</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email', $client->email) }}"
                    class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-amber-600 mb-2">{{ __('app.client_address') }}</label>
            <textarea
                name="address"
                id="address"
                rows="3"
                class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 resize-none"
            >{{ old('address', $client->address) }}</textarea>
            @error('address')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="national_id" class="block text-sm font-medium text-amber-600 mb-2">{{ __('app.client_national_id') }}</label>
                <input
                    type="text"
                    name="national_id"
                    id="national_id"
                    value="{{ old('national_id', $client->national_id) }}"
                    class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                    dir="ltr"
                >
                @error('national_id')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="company_name" class="block text-sm font-medium text-amber-600 mb-2">{{ __('app.company_name') }}</label>
                <input
                    type="text"
                    name="company_name"
                    id="company_name"
                    value="{{ old('company_name', $client->company_name) }}"
                    class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                >
                @error('company_name')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex items-center gap-4 pt-4 border-t border-gray-200">
            <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.save_changes') }}</button>
            <a href="{{ route('clients.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">{{ __('app.cancel') }}</a>
        </div>
    </form>
</div>
@endsection