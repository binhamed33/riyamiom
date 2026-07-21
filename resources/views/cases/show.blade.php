@extends('layouts.app')

@section('title', __('app.page_case_details') . ' - ' . $case->case_number)

@section('content')
<div class="space-y-6" dir="rtl" x-data="caseDetail()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#C9A55A]">{{ $case->title }}</h1>
            <p class="text-white/40 text-sm mt-1">{{ __('app.case_number') }}: <span class="text-white font-mono">{{ $case->case_number }}</span></p>
        </div>
        <div class="flex items-center gap-3">
            {{-- Summarize Button --}}
            <button @click="showSummary = true" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                {{ __('app.case_summary') }}
            </button>
            {{-- Download PDF Button --}}
            <a href="{{ route('cases.file', $case) }}" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                {{ __('app.download_case_pdf') }}
            </a>
            <a href="{{ route('cases.edit', $case->id) }}" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                {{ __('app.edit') }}
            </a>
            <form action="{{ route('cases.destroy', $case->id) }}" method="POST" class="contents" onsubmit="return confirm('{{ __('app.confirm_delete_case_full') }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                    {{ __('app.delete') }}
                </button>
            </form>
        </div>
    </div>

    {{-- Case Info Card --}}
    <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            {{-- Status --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.status') }}</p>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    @if($case->status === 'active') bg-green-500/15 text-green-400 border border-green-500/30
                    @elseif($case->status === 'pending') bg-yellow-500/15 text-yellow-400 border border-yellow-500/30
                    @elseif($case->status === 'overdue') bg-red-500/15 text-red-400 border border-red-500/30
                    @elseif($case->status === 'closed') bg-gray-500/15 text-white/40 border border-gray-500/30
                    @elseif($case->status === 'won') bg-blue-500/15 text-blue-400 border border-blue-500/30
                    @elseif($case->status === 'lost') bg-red-600/15 text-red-300 border border-red-600/30
                    @else bg-gray-500/15 text-white/40 border border-gray-500/30 @endif">
                    @if($case->status === 'active') {{ __('app.status_active') }}
                    @elseif($case->status === 'pending') {{ __('app.status_pending') }}
                    @elseif($case->status === 'overdue') {{ __('app.status_overdue') }}
                    @elseif($case->status === 'closed') {{ __('app.status_closed') }}
                    @elseif($case->status === 'won') {{ __('app.status_won') }}
                    @elseif($case->status === 'lost') {{ __('app.status_lost') }}
                    @else {{ $case->status }}
                    @endif
                </span>
            </div>

            {{-- Priority --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.priority') }}</p>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    @if($case->priority === 'low') bg-gray-500/15 text-white/40 border border-gray-500/30
                    @elseif($case->priority === 'medium') bg-yellow-500/15 text-yellow-400 border border-yellow-500/30
                    @elseif($case->priority === 'high') bg-orange-500/15 text-orange-400 border border-orange-500/30
                    @elseif($case->priority === 'urgent') bg-red-500/15 text-red-400 border border-red-500/30
                    @else bg-gray-500/15 text-white/40 border border-gray-500/30 @endif">
                    @if($case->priority === 'low') {{ __('app.priority_low') }}
                    @elseif($case->priority === 'medium') {{ __('app.priority_medium') }}
                    @elseif($case->priority === 'high') {{ __('app.priority_high') }}
                    @elseif($case->priority === 'urgent') {{ __('app.priority_urgent') }}
                    @else {{ $case->priority }}
                    @endif
                </span>
            </div>

            {{-- Type --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.case_type') }}</p>
                <p class="text-white text-sm">{{ $case->type ?? '—' }}</p>
            </div>

            {{-- Court --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.case_court') }}</p>
                <p class="text-white text-sm">{{ $case->court }}</p>
            </div>

            {{-- Client --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.case_client') }}</p>
                <p class="text-white text-sm">{{ $case->client->name ?? '—' }}</p>
            </div>

            {{-- Lawyer --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.case_lawyer') }}</p>
                <p class="text-white text-sm">{{ $case->lawyer->name ?? '—' }}</p>
            </div>

            {{-- Opponent --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.case_opponent') }}</p>
                <p class="text-white text-sm">{{ $case->opponent ?? '—' }}</p>
            </div>

            {{-- Opened At --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.opened_date') }}</p>
                <p class="text-white text-sm">{{ $case->opened_at?->format('Y/m/d') ?? '—' }}</p>
            </div>

            {{-- Next Date --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.next_date') }}</p>
                <p class="text-white text-sm">{{ $case->next_date?->format('Y/m/d') ?? '—' }}</p>
            </div>
        </div>

        {{-- Description --}}
        @if($case->description)
            <div class="mt-5 pt-5 border-t border-white/10">
                <p class="text-white/40 text-xs mb-2">{{ __('app.case_description') }}</p>
                <p class="text-white text-sm leading-relaxed">{{ $case->description }}</p>
            </div>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="bg-navy rounded-xl border border-[#C9A55A]/20 overflow-hidden">
        {{-- Tab Headers --}}
        <div class="flex border-b border-white/10" role="tablist">
            <button @click="activeTab = 'sessions'" :class="activeTab === 'sessions' ? 'text-[#C9A55A] border-b-2 border-[#C9A55A] bg-white/5' : 'text-white/40 hover:text-white/30'"
                class="flex-1 px-4 py-3 text-sm font-medium transition-colors" role="tab">
                {{ __('app.sessions_tab') }} ({{ $case->sessions->count() ?? 0 }})
            </button>
            <button @click="activeTab = 'tasks'" :class="activeTab === 'tasks' ? 'text-[#C9A55A] border-b-2 border-[#C9A55A] bg-white/5' : 'text-white/40 hover:text-white/30'"
                class="flex-1 px-4 py-3 text-sm font-medium transition-colors" role="tab">
                {{ __('app.tasks_tab') }} ({{ $case->tasks->count() ?? 0 }})
            </button>
            <button @click="activeTab = 'documents'" :class="activeTab === 'documents' ? 'text-[#C9A55A] border-b-2 border-[#C9A55A] bg-white/5' : 'text-white/40 hover:text-white/30'"
                class="flex-1 px-4 py-3 text-sm font-medium transition-colors" role="tab">
                {{ __('app.documents_tab') }} ({{ $case->documents->count() ?? 0 }})
            </button>
        </div>

        {{-- Tab Content: Sessions --}}
        <div x-show="activeTab === 'sessions'" x-cloak class="p-4">
            @if($case->sessions && $case->sessions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-right">
                        <thead>
                            <tr class="border-b border-white/10">
                                <th class="px-3 py-2 text-[#C9A55A] font-bold text-xs">{{ __('app.table_date') }}</th>
                                <th class="px-3 py-2 text-[#C9A55A] font-bold text-xs">{{ __('app.table_type') }}</th>
                                <th class="px-3 py-2 text-[#C9A55A] font-bold text-xs">{{ __('app.table_hall') }}</th>
                                <th class="px-3 py-2 text-[#C9A55A] font-bold text-xs">{{ __('app.table_judge') }}</th>
                                <th class="px-3 py-2 text-[#C9A55A] font-bold text-xs">{{ __('app.status') }}</th>
                                <th class="px-3 py-2 text-[#C9A55A] font-bold text-xs">{{ __('app.table_notes') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($case->sessions as $session)
                                <tr class="hover:bg-white/5 transition-colors">
                                    <td class="px-3 py-2.5 text-white text-xs whitespace-nowrap">{{ $session->date?->format('Y/m/d H:i') ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-white/30 text-xs">{{ $session->location ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-white/30 text-xs">—</td>
                                    <td class="px-3 py-2.5 text-white/30 text-xs">—</td>
                                    <td class="px-3 py-2.5">
                                        <span class="text-xs px-2 py-0.5 rounded-full
                                            @if(($session->status ?? '') === 'completed') bg-green-500/15 text-green-400
                                            @elseif(($session->status ?? '') === 'cancelled') bg-red-500/15 text-red-400
                                            @else bg-blue-500/15 text-blue-400 @endif">
                                            {{ $session->status ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-white/40 text-xs max-w-[200px] truncate">{{ $session->notes ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8 text-white/50">
                    <svg class="w-12 h-12 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm">{{ __('app.no_sessions_recorded') }}</p>
                </div>
            @endif
        </div>

        {{-- Tab Content: Tasks --}}
        <div x-show="activeTab === 'tasks'" x-cloak class="p-4">
            @if($case->tasks && $case->tasks->count() > 0)
                <div class="space-y-2">
                    @foreach($case->tasks as $task)
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-white/5 border border-white/5 hover:border-[#C9A55A]/20 transition-colors">
                            <div class="flex-shrink-0">
                                @if($task->status === 'completed')
                                    <div class="w-5 h-5 rounded-full bg-green-500/20 flex items-center justify-center">
                                        <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                @else
                                    <div class="w-5 h-5 rounded-full border-2 border-white/20"></div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-white text-sm {{ $task->status === 'completed' ? 'line-through opacity-50' : '' }}">{{ $task->title }}</p>
                                @if($task->due_date)
                                    <p class="text-white/50 text-xs mt-0.5">{{ __('app.deadline') }} {{ \Carbon\Carbon::parse($task->due_date)->format('Y/m/d') }}</p>
                                @endif
                            </div>
                            <span class="px-2 py-0.5 text-xs rounded-full flex-shrink-0
                                @if(($task->priority ?? '') === 'urgent') bg-red-500/15 text-red-400
                                @elseif(($task->priority ?? '') === 'high') bg-orange-500/15 text-orange-400
                                @elseif(($task->priority ?? '') === 'medium') bg-yellow-500/15 text-yellow-400
                                @else bg-gray-500/15 text-white/40 @endif">
                                {{ $task->priority ?? '' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-white/50">
                    <svg class="w-12 h-12 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-sm">{{ __('app.no_tasks_recorded') }}</p>
                </div>
            @endif
        </div>

        {{-- Tab Content: Documents --}}
        <div x-show="activeTab === 'documents'" x-cloak class="p-4">
            @if($case->documents && $case->documents->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($case->documents as $document)
                        <div class="p-3 rounded-lg bg-white/5 border border-white/5 hover:border-[#C9A55A]/20 transition-colors">
        <div class="flex flex-wrap items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-[#C9A55A]/10 flex items-center justify-center flex-shrink-0">
                                    @if(str_contains($document->file_type ?? '', 'pdf'))
                                        <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                    @elseif(str_contains($document->file_type ?? '', 'image'))
                                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-white text-sm truncate">{{ $document->title ?? $document->name ?? __('app.documents') }}</p>
                                    <p class="text-white/50 text-xs">{{ $document->created_at?->format('Y/m/d') ?? '' }}</p>
                                </div>
                                @if(isset($document->file_path))
                                    <a href="{{ route('documents.download', $document) }}" target="_blank"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition-colors flex-shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-white/50">
                    <svg class="w-12 h-12 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm">{{ __('app.no_documents_attached') }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Summary Modal --}}
    <div x-show="showSummary" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showSummary = false"></div>

        {{-- Modal --}}
        <div class="relative bg-navy border border-[#C9A55A]/30 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100">
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
                <h3 class="text-lg font-bold text-[#C9A55A]">{{ __('app.case_summary') }}</h3>
                <button @click="showSummary = false" class="p-1 rounded-lg hover:bg-white/10 text-white/40 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="summary-body px-6 py-5 overflow-y-auto flex-1 space-y-4" dir="rtl">
                {{-- Status & Priority --}}
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-white/[0.03] rounded-xl p-3 border border-white/5">
                        <p class="text-xs text-white/50 mb-1">{{ __('app.status') }}</p>
                        @php
                            $statusMap = ['active'=>__('app.status_active'),'pending'=>__('app.status_pending'),'overdue'=>__('app.status_overdue'),'closed'=>__('app.status_closed'),'won'=>__('app.status_won'),'lost'=>__('app.status_lost')];
                            $statusColors = ['active'=>'text-emerald-400','pending'=>'text-amber-400','overdue'=>'text-red-400','closed'=>'text-white/40','won'=>'text-green-400','lost'=>'text-red-400'];
                        @endphp
                        <p class="font-bold {{ $statusColors[$case->status] ?? 'text-white' }}">{{ $statusMap[$case->status] ?? $case->status }}</p>
                    </div>
                    <div class="bg-white/[0.03] rounded-xl p-3 border border-white/5">
                        <p class="text-xs text-white/50 mb-1">{{ __('app.priority') }}</p>
                        @php
                            $priorityMap = ['low'=>__('app.priority_low'),'medium'=>__('app.priority_medium'),'high'=>__('app.priority_high'),'urgent'=>__('app.priority_urgent')];
                            $priorityColors = ['low'=>'text-white/40','medium'=>'text-amber-400','high'=>'text-orange-400','urgent'=>'text-red-400'];
                        @endphp
                        <p class="font-bold {{ $priorityColors[$case->priority] ?? 'text-white' }}">{{ $priorityMap[$case->priority] ?? $case->priority }}</p>
                    </div>
                </div>

                {{-- Info --}}
                <div class="bg-white/[0.03] rounded-xl p-4 border border-white/5 space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-white/50 text-sm">{{ __('app.case_number') }}</span>
                        <span class="text-white font-mono text-sm">{{ $case->case_number }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-white/50 text-sm">{{ __('app.court') }}</span>
                        <span class="text-white text-sm text-left max-w-[60%]">{{ $case->court }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-white/50 text-sm">{{ __('app.type') }}</span>
                        <span class="text-white text-sm">{{ $case->type ?: '—' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-white/50 text-sm">{{ __('app.case_client') }}</span>
                        <span class="text-[#C9A55A] text-sm font-medium">{{ $case->client?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-white/50 text-sm">{{ __('app.case_lawyer') }}</span>
                        <span class="text-white text-sm">{{ $case->lawyer?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-white/50 text-sm">{{ __('app.case_opponent') }}</span>
                        <span class="text-white text-sm">{{ $case->opponent ?: '—' }}</span>
                    </div>
                </div>

                {{-- Description --}}
                @if($case->description)
                    <div class="bg-white/[0.03] rounded-xl p-4 border border-white/5">
                        <p class="text-white/50 text-xs mb-2">{{ __('app.case_description') }}</p>
                        <p class="text-white/70 text-sm leading-relaxed">{{ $case->description }}</p>
                    </div>
                @endif

                {{-- Dates --}}
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-white/[0.03] rounded-xl p-3 border border-white/5">
                        <p class="text-xs text-white/50 mb-1">{{ __('app.opened_date') }}</p>
                        <p class="text-white text-sm">{{ $case->opened_at ? $case->opened_at->format('d/m/Y') : '—' }}</p>
                    </div>
                    <div class="bg-white/[0.03] rounded-xl p-3 border border-white/5">
                        <p class="text-xs text-white/50 mb-1">{{ __('app.next_date') }}</p>
                        <p class="text-white text-sm">{{ $case->next_date ? $case->next_date->format('d/m/Y') : '—' }}</p>
                    </div>
                </div>

                {{-- Counts --}}
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-white/[0.03] rounded-xl p-3 border border-white/5 text-center">
                        <p class="text-2xl font-bold text-[#C9A55A]">{{ $case->sessions->count() }}</p>
                        <p class="text-xs text-white/50 mt-1">{{ __('app.sessions') }}</p>
                    </div>
                    <div class="bg-white/[0.03] rounded-xl p-3 border border-white/5 text-center">
                        <p class="text-2xl font-bold text-[#C9A55A]">{{ $case->tasks->count() }}</p>
                        <p class="text-xs text-white/50 mt-1">{{ __('app.tasks') }}</p>
                    </div>
                    <div class="bg-white/[0.03] rounded-xl p-3 border border-white/5 text-center">
                        <p class="text-2xl font-bold text-[#C9A55A]">{{ $case->documents->count() }}</p>
                        <p class="text-xs text-white/50 mt-1">{{ __('app.documents') }}</p>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-3 border-t border-white/10 flex justify-between items-center">
                <button @click="printSummary()" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    {{ __('app.print_summary') }}
                </button>
                <button @click="copySummary()" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    {{ __('app.copy_summary') }}
                </button>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
    function caseDetail() {
        return {
            activeTab: 'sessions',
            showSummary: false,
            init() {},
            copySummary() {
                const el = document.querySelector('.summary-body');
                if (el) {
                    navigator.clipboard.writeText(el.innerText).then(() => {
                        alert('{{ __("app.summary_copied") }}');
                    });
                }
            },
            printSummary() {
                const el = document.querySelector('.summary-body');
                if (!el) return;
                const win = window.open('', '_blank');
                win.document.write('<html><head><title>{{ __("app.case_summary") }}</title><style>body{font-family:Cairo,Tajawal,sans-serif;padding:30px;direction:rtl;color:#333}h2{color:#C9A55A;border-bottom:2px solid #C9A55A;padding-bottom:10px}.row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee}.label{color:#888}.value{font-weight:bold}</style></head><body>');
                win.document.write('<h2>{{ __("app.case_summary") }} - {{ $case->case_number }}</h2>');
                win.document.write(el.innerText);
                win.document.write('</body></html>');
                win.document.close();
                win.print();
            }
        };
    }
</script>
@endpush

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
