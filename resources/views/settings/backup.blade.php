@extends('layouts.app')

@section('title', __('app.page_backup'))

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#C9A55A]">{{ __('app.backup') }}</h1>
            <p class="text-white/40 text-sm mt-1">{{ __('app.manage_backups') }}</p>
        </div>
        <form action="{{ route('backup.create') }}" method="POST">
            @csrf
            <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                {{ __('app.create_backup') }}
            </button>
        </form>
    </div>

    {{-- Upload & Restore --}}
    <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-4">
        <form action="{{ route('backup.upload-restore') }}" method="POST" enctype="multipart/form-data" x-data="{ uploading: false }" @submit.prevent="uploading = true; $el.submit()">
            @csrf
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <div class="flex-1">
                    <label class="block text-white/40 text-xs mb-1.5">{{ __('app.upload_backup_file') }}</label>
                    <input type="file" name="backup_file" accept=".zip" required
                           class="w-full text-sm text-white/70 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gold/20 file:text-gold hover:file:bg-gold/30 file:cursor-pointer cursor-pointer bg-white/5 rounded-lg border border-white/10 px-3 py-1.5">
                </div>
                <button type="submit" :disabled="uploading"
                        class="bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm inline-flex items-center gap-2 mt-5 sm:mt-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <span x-show="!uploading">{{ __('app.restore_upload') }}</span>
                    <span x-show="uploading" x-cloak>{{ __('app.restoring') }}</span>
                </button>
            </div>
            @error('backup_file')
                <p class="text-red-400 text-xs mt-2">{{ $message }}</p>
            @enderror
        </form>
    </div>

    {{-- Security Warning --}}
    <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-yellow-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
            <div>
                <p class="text-yellow-400 text-sm font-bold">{{ __('app.backup_important_title') }}</p>
                <p class="text-yellow-400/70 text-xs mt-1">{{ __('app.backup_important_text') }}</p>
            </div>
        </div>
    </div>

    {{-- Auto Backup Status --}}
    <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-green-500/15 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-white text-sm font-bold">{{ __('app.auto_backup_enabled') }}</p>
                <p class="text-white/40 text-xs mt-0.5">{{ __('app.auto_backup_schedule') }}</p>
            </div>
        </div>
    </div>

    {{-- Backups Table --}}
    <div class="bg-navy rounded-xl border border-[#C9A55A]/20 overflow-hidden">
        @if(!empty($backups) && count($backups) > 0)
            <div class="px-4 py-3 border-b border-white/10 flex items-center justify-between">
                <span class="text-white/40 text-xs">{{ count($backups) }} {{ __('app.backup_count') }}</span>
                <span class="text-white/20 text-xs">{{ __('app.backup_auto_keep_30') }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="border-b border-white/10">
                            <th class="px-4 py-3 text-[#C9A55A] font-bold text-xs">{{ __('app.backup_file_name') }}</th>
                            <th class="px-4 py-3 text-[#C9A55A] font-bold text-xs">{{ __('app.backup_size') }}</th>
                            <th class="px-4 py-3 text-[#C9A55A] font-bold text-xs">{{ __('app.backup_date') }}</th>
                            <th class="px-4 py-3 text-[#C9A55A] font-bold text-xs">{{ __('app.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($backups as $backup)
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="px-4 py-3 text-white text-xs font-mono">{{ $backup['name'] }}</td>
                                <td class="px-4 py-3 text-white/30 text-xs">{{ $backup['size'] }} MB</td>
                                <td class="px-4 py-3 text-white/30 text-xs whitespace-nowrap">{{ $backup['date'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('backup.download', $backup['name']) }}"
                                           class="px-3 py-1.5 bg-blue-500/15 text-blue-400 hover:bg-blue-500/25 rounded-lg text-xs font-medium transition-colors inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                            {{ __('app.download') }}
                                        </a>
                                        <form action="{{ route('backup.restore', $backup['name']) }}" method="POST" class="contents" x-data @submit.prevent="if(confirm('{{ __("app.confirm_restore_backup") }}')) $el.submit()">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20 transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                </svg>
                                                {{ __('app.restore') }}
                                            </button>
                                        </form>
                                        <form action="{{ route('backup.destroy', $backup['name']) }}" method="POST" class="contents" x-data @submit.prevent="if(confirm('{{ __("app.confirm_delete_backup") }}')) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                {{ __('app.delete') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12 text-white/50">
                <svg class="w-16 h-16 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
                <p class="text-sm">{{ __('app.no_backups') }}</p>
                <p class="text-xs text-white/60 mt-1">{{ __('app.create_first_backup') }}</p>
            </div>
        @endif
    </div>

</div>
@endsection
