@extends('layouts.app')

@section('title', __('app.page_documents'))

@section('content')
<div class="space-y-6" x-data="{ showUpload: false }">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-white">{{ __('app.page_documents') }} ({{ $documents->total() }})</h2>
        <button @click="showUpload = true"
            class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm inline-flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            {{ __('app.upload_document') }}
        </button>
    </div>
    <div class="flex items-center gap-2">
        @php $currentAccess = request('access_level'); @endphp
        <a href="{{ route('documents.index', array_merge(request()->query(), ['access_level' => ''])) }}"
           class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors {{ !$currentAccess ? 'bg-gold text-navy' : 'bg-white/10 text-white/70 hover:bg-white/20' }}">
            {{ __('app.access_public') }}
        </a>
        <a href="{{ route('documents.index', array_merge(request()->query(), ['access_level' => 'team'])) }}"
           class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $currentAccess === 'team' ? 'bg-gold text-navy' : 'bg-white/10 text-white/70 hover:bg-white/20' }}">
            {{ __('app.access_team') }}
        </a>
        <a href="{{ route('documents.index', array_merge(request()->query(), ['access_level' => 'private'])) }}"
           class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $currentAccess === 'private' ? 'bg-gold text-navy' : 'bg-white/10 text-white/70 hover:bg-white/20' }}">
            {{ __('app.access_private') }}
        </a>
    </div>



    <div x-show="showUpload" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true"
         @keydown.escape.window="showUpload = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity" @click="showUpload = false"></div>
            <div class="relative bg-navy rounded-xl border border-white/10 max-w-lg w-full p-6 space-y-4 z-10">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-white">{{ __('app.new_document') }}</h3>
                    <button @click="showUpload = false" class="text-white/40 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div>
                        <label for="doc_title" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.title') }} <span class="text-red-500">*</span></label>
                        <input type="text" id="doc_title" name="title" value="{{ old('title') }}"
                               class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('title') border-red-500 @enderror"
                               placeholder="{{ __('app.document_title_placeholder') }}" required>
                        @error('title')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="file" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.document_file') }} <span class="text-red-500">*</span></label>
                        <input type="file" id="file" name="file"
                               class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('file') border-red-500 @enderror"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required>
                        <p class="mt-1 text-xs text-white/50">{{ __('app.allowed_formats') }}</p>
                        @error('file')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="case_id" class="block text-sm font-medium text-white/70 mb-1">{{ __('app.case') }}</label>
                        <select id="case_id" name="case_id" class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('case_id') border-red-500 @enderror">
                            <option value="">{{ __('app.no_case') }}</option>
                            @foreach ($cases as $case)
                                <option value="{{ $case->id }}" {{ old('case_id') == $case->id ? 'selected' : '' }}>
                                    {{ $case->title }}
                                </option>
                            @endforeach
                        </select>
                        @error('case_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-white/70 mb-2">{{ __('app.document_access') }} <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="access_level" value="all"
                                       {{ old('access_level', 'all') === 'all' ? 'checked' : '' }}
                                       class="w-4 h-4 text-[#C9A55A] focus:ring-[#C9A55A] border-white/20">
                                <span class="text-sm text-white/70">{{ __('app.access_public') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="access_level" value="team"
                                       {{ old('access_level') === 'team' ? 'checked' : '' }}
                                       class="w-4 h-4 text-[#C9A55A] focus:ring-[#C9A55A] border-white/20">
                                <span class="text-sm text-white/70">{{ __('app.access_team') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="access_level" value="private"
                                       {{ old('access_level') === 'private' ? 'checked' : '' }}
                                       class="w-4 h-4 text-[#C9A55A] focus:ring-[#C9A55A] border-white/20">
                                <span class="text-sm text-white/70">{{ __('app.access_private') }}</span>
                            </label>
                        </div>
                        @error('access_level')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                            {{ __('app.upload') }}
                        </button>
                        <button type="button" @click="showUpload = false"
                                class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                            {{ __('app.cancel') }}
                        </button>
                    </div>
                </form>
            </div>
    </div>
    </div>


    <div class="bg-navy rounded-xl border border-white/10">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-white">
                <tr>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.title') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.case') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.uploaded_by') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.type') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.table_size') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.table_access') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.date') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/10">
                @forelse ($documents as $document)
                    <tr class="hover:bg-white/5 transition-colors">
                        <td class="px-6 py-4 font-medium text-white">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 {{ str_contains($document->file_type ?? '', 'pdf') ? 'text-red-500' : (str_contains($document->file_type ?? '', 'doc') ? 'text-blue-500' : (str_contains($document->file_type ?? '', 'xls') ? 'text-green-500' : (str_contains($document->file_type ?? '', 'jpg') || str_contains($document->file_type ?? '', 'jpeg') || str_contains($document->file_type ?? '', 'png') ? 'text-purple-500' : 'text-white/50'))) }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                {{ $document->title }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-white/60">{{ $document->case->title ?? '—' }}</td>
                        <td class="px-6 py-4 text-white/60">{{ $document->uploader->name ?? '—' }}</td>
                        <td class="px-6 py-4">
                            <span class="uppercase text-xs font-semibold text-white/50 bg-white/10 px-2 py-0.5 rounded">{{ strtoupper($document->file_type ?? '') }}</span>
                        </td>
                        <td class="px-6 py-4 text-white/60">{{ round(($document->file_size ?? 0) / 1024, 1) }} KB</td>
                        <td class="px-6 py-4">
                            @if (($document->access_level ?? '') === 'all')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-500/20 text-green-400">{{ __('app.access_public') }}</span>
                            @elseif (($document->access_level ?? '') === 'team')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-400">{{ __('app.access_team') }}</span>
                            @elseif (($document->access_level ?? '') === 'private')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-500/20 text-red-400">{{ __('app.access_private') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-white/60">
                            @if ($document->created_at){{ $document->created_at->format('Y-m-d') }}@endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1">
                                @php $previewable = in_array(strtolower($document->file_type ?? ''), ['pdf', 'jpg', 'jpeg', 'png']) @endphp
                                @if($previewable)
                                <a href="{{ route('documents.preview', $document) }}" target="_blank" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 hover:bg-purple-500/20 transition-colors" title="{{ __('app.preview') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </a>
                                @endif
                                <a href="{{ route('documents.download', $document) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20 transition-colors" title="{{ __('app.download') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                </a>
                                <form method="POST" action="{{ route('documents.destroy', $document) }}" class="contents" x-data x-ref="deleteForm" @submit.prevent="if(confirm('{{ __('app.confirm_delete') }}')) $el.submit()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors" title="{{ __('app.delete') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-white/50">{{ __('app.no_documents') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    @if ($documents->hasPages())
        <div class="mt-4">
            {{ $documents->links() }}
        </div>
    @endif
</div>
@endsection
