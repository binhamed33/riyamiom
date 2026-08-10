@extends('layouts.app')

@section('title', __('app.reports'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-amber-600 flex items-center gap-2">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            {{ __('app.reports') }}
        </h1>
    </div>

    {{-- Description --}}
    <div class="bg-white rounded-xl border border-amber-200 p-5">
        <p class="text-gray-600 text-sm">{{ __('app.reports_desc') }}</p>
        <p class="text-gray-400 text-xs mt-2">{{ __('app.reports_hint') }}</p>
    </div>

    {{-- Export Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Cases --}}
        <div class="bg-white rounded-xl border border-amber-200 p-5 hover:border-amber-300 transition-colors">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div>
                    <p class="text-gray-900 font-bold text-sm">{{ __('app.cases') }}</p>
                    <p class="text-gray-400 text-xs">{{ $counts['cases'] }} {{ __('app.record') }}</p>
                </div>
            </div>
            <a href="{{ route('export.cases') }}" class="block w-full text-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-colors">
                {{ __('app.export_excel') }}
            </a>
        </div>

        {{-- Sessions --}}
        <div class="bg-white rounded-xl border border-amber-200 p-5 hover:border-amber-300 transition-colors">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-gray-900 font-bold text-sm">{{ __('app.sessions') }}</p>
                    <p class="text-gray-400 text-xs">{{ $counts['sessions'] }} {{ __('app.record') }}</p>
                </div>
            </div>
            <a href="{{ route('export.sessions') }}" class="block w-full text-center px-4 py-2.5 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors">
                {{ __('app.export_excel') }}
            </a>
        </div>

        {{-- Tasks --}}
        <div class="bg-white rounded-xl border border-amber-200 p-5 hover:border-amber-300 transition-colors">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div>
                    <p class="text-gray-900 font-bold text-sm">{{ __('app.tasks') }}</p>
                    <p class="text-gray-400 text-xs">{{ $counts['tasks'] }} {{ __('app.record') }}</p>
                </div>
            </div>
            <a href="{{ route('export.tasks') }}" class="block w-full text-center px-4 py-2.5 bg-primary hover:bg-primary-dark text-white text-sm rounded-lg transition-colors">
                {{ __('app.export_excel') }}
            </a>
        </div>

        {{-- Clients --}}
        <div class="bg-white rounded-xl border border-amber-200 p-5 hover:border-amber-300 transition-colors">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-gray-900 font-bold text-sm">{{ __('app.clients') }}</p>
                    <p class="text-gray-400 text-xs">{{ $counts['clients'] }} {{ __('app.record') }}</p>
                </div>
            </div>
            <a href="{{ route('export.clients') }}" class="block w-full text-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg transition-colors">
                {{ __('app.export_excel') }}
            </a>
        </div>
    </div>

    {{-- Export All --}}
    <div class="bg-gradient-to-br from-amber-100 to-amber-50 rounded-xl border border-amber-300 p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-lg bg-amber-200 flex items-center justify-center">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </div>
                <div>
                    <p class="text-gray-900 font-bold">{{ __('app.export_all') }}</p>
                    <p class="text-gray-500 text-xs">{{ __('app.export_all_desc') }}</p>
                </div>
            </div>
            <a href="{{ route('export.all') }}" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                {{ __('app.export_excel') }}
            </a>
        </div>
    </div>
</div>

@endsection