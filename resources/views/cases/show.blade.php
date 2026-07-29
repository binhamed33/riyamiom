@extends('layouts.app')

@section('title', __('app.page_case_details') . ' - ' . $case->case_number)

@push('styles')
<script nonce="{{ $cspNonce }}">
document.addEventListener('alpine:init', () => {
    Alpine.data('caseDetail', () => ({
        activeTab: 'sessions',
        showSummary: false,
        reportModal: false,
        reportSession: null,
        reportText: '',
        reportSaving: false,
        async openReport(session) {
            this.reportSession = session;
            this.reportText = session.report || '';
            this.reportModal = true;
        },
        async saveReport() {
            if (!this.reportSession || !this.reportSession.id) return;
            this.reportSaving = true;
            try {
                const res = await fetch('{{ route('sessions.update', '') }}/' + this.reportSession.id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        case_id: this.reportSession.case_id,
                        date: this.reportSession.date,
                        location: this.reportSession.location,
                        status: this.reportSession.status,
                        notes: this.reportSession.notes || '',
                        report: this.reportText
                    })
                });
                this.reportSaving = false;
                if (res.ok) {
                    this.reportSession.report = this.reportText;
                    this.reportModal = false;
                    this.reportSession = null;
                } else {
                    alert('{{ __("app.save_error") }}');
                }
            } catch(e) {
                this.reportSaving = false;
                alert('{{ __("app.connection_error") }}');
            }
        },
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
            const printContent = el.cloneNode(true);
            printContent.querySelectorAll('button, .no-print').forEach(b => b.remove());
            const win = window.open('', '_blank');
            if (!win) return;
            win.document.write('<html><head><title>{{ __("app.case_summary") }} - {{ $case->case_number }}</title>');
            win.document.write('<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&family=Tajawal:wght@400;700&display=swap">');
            win.document.write('</head><body>');
            win.document.write('<div style="max-width:700px;margin:30px auto;font-family:Tajawal,Cairo,sans-serif;direction:rtl;padding:20px;color:#333;">');
            win.document.write('<h2 style="color:#C9A55A;border-bottom:2px solid #C9A55A;padding-bottom:10px;">{{ __("app.case_summary") }} - {{ $case->case_number }}</h2>');
            win.document.write(printContent.innerHTML);
            win.document.write('</div></body></html>');
            win.document.close();
            setTimeout(() => win.print(), 300);
        }
    }));
});
</script>
<style>
    @media print {
        @page { margin: 10mm; size: A4; }
        body { background: white !important; color: black !important; }
        aside, header, footer, form, [x-cloak] { display: none !important; }
        .no-print { display: none !important; }
        [dir="rtl"] .content-area { margin: 0 !important; }
        [dir="ltr"] .content-area { margin: 0 !important; }
        main { padding: 0 !important; }
        * { box-shadow: none !important; text-shadow: none !important; }
        .print-header { border-bottom: 2px solid #C9A55A; padding-bottom: 10px; margin-bottom: 20px; }
        .print-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #ddd; padding-top: 5px; }
    }
    .print-only { display: none; }
    @media print { .print-only { display: block !important; } }
</style>
@endpush

@section('content')
<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" x-data="caseDetail">

    {{-- Print Header (visible only in print) --}}
    <div class="print-only print-header">
        <h1 style="font-size:20px;color:#C9A55A;margin:0;">{{ __('app.case_number') }}: {{ $case->case_number }}</h1>
        <p style="color:#666;font-size:12px;margin:2px 0;">{{ $case->created_at->format('Y-m-d') }}</p>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#C9A55A]">{{ $case->case_number }}</h1>
            @if($case->title && $case->title !== $case->case_number)
                <p class="text-white/40 text-sm mt-1">{{ $case->title }}</p>
            @endif
            @if($case->case_type)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#C9A55A]/15 text-[#C9A55A] border border-[#C9A55A]/30 mt-2">
                    {{ $case->case_type }}
                </span>
            @endif
        </div>
        <div class="flex items-center gap-3">
            {{-- Summarize Button --}}
            <button @click="showSummary = true" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                {{ __('app.case_summary') }}
            </button>
            {{-- Print Button --}}
            <button @click="window.print()" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm flex items-center gap-2 no-print">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                {{ __('app.print') }}
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
            <form action="{{ route('cases.destroy', $case->id) }}" method="POST" class="contents" x-data @submit.prevent="if(confirm('{{ __('app.confirm_delete_case_full') }}')) $el.submit()">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                    {{ __('app.delete') }}
                </button>
            </form>
        </div>
    </div>

    {{-- Related People Section (at top) --}}
    <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
        <h2 class="text-lg font-bold text-[#C9A55A] border-b border-white/10 pb-3 mb-5">{{ __('app.related_people') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.case_client') }}</p>
                <p class="text-white text-sm font-medium">{{ $case->client->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.case_lawyer') }}</p>
                <p class="text-white text-sm">{{ $case->lawyer->name ?? '—' }}</p>
            </div>
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

            {{-- Case Type --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.case_type') }}</p>
                <p class="text-white text-sm">{{ $case->case_type ?? '—' }}</p>
            </div>

            {{-- Court + Case Number --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.case_court') }}</p>
                <p class="text-white text-sm">{{ $case->court }} <span class="text-white/30 text-xs">({{ $case->case_number }})</span></p>
            </div>

            {{-- Office Case Number --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.office_case_number') }}</p>
                <p class="text-white text-sm">{{ $case->office_case_number ?? '—' }}</p>
            </div>

            {{-- Opened At --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.opened_date') }}</p>
                <p class="text-white text-sm">{{ $case->opened_at?->format('Y/m/d') ?? '—' }}</p>
            </div>

            {{-- Next Session Date --}}
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.next_session_date') }}</p>
                <p class="text-white text-sm">{{ $case->next_date?->format('Y/m/d') ?? '—' }}</p>
            </div>
        </div>
    </div>

    {{-- Opponent Data Card --}}
    <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
        <h2 class="text-lg font-bold text-[#C9A55A] border-b border-white/10 pb-3 mb-5">{{ __('app.opponent_data') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.opponent_name') }}</p>
                <p class="text-white text-sm">{{ $case->opponent ?? '—' }}</p>
            </div>
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.opponent_phone') }}</p>
                <p class="text-white text-sm">{{ $case->opponent_phone ?? '—' }}</p>
            </div>
            <div>
                <p class="text-white/40 text-xs mb-1">{{ __('app.opponent_address') }}</p>
                <p class="text-white text-sm">{{ $case->opponent_address ?? '—' }}</p>
            </div>
        </div>
    </div>

    {{-- Description --}}
    @if($case->description)
    <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-6">
        <p class="text-white/40 text-xs mb-2">{{ __('app.case_description') }}</p>
        <p class="text-white text-sm leading-relaxed">{{ $case->description }}</p>
    </div>
    @endif

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
                                <th class="px-3 py-2 text-[#C9A55A] font-bold text-xs">{{ __('app.session_report') }}</th>
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
                                    <td class="px-3 py-2.5">
                                        <button @click="openReport({
                                            id: {{ $session->id }},
                                            case_id: {{ $session->case_id }},
                                            date: '{{ $session->date }}',
                                            location: '{{ addslashes($session->location) }}',
                                            status: '{{ $session->status }}',
                                            notes: '{{ addslashes($session->notes ?? '') }}',
                                            report: '{{ addslashes($session->report ?? '') }}'
                                        })" class="text-xs"
                                            :class="reportSession && reportSession.id === {{ $session->id }} && reportText ? 'text-[#C9A55A]' : 'text-white/30 hover:text-[#C9A55A]'">
                                            {{ $session->report ? __('app.view_report') : __('app.add_report') }}
                                        </button>
                                    </td>
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
                                    @php $canView = in_array(strtolower($document->file_type ?? ''), ['pdf', 'jpg', 'jpeg', 'png']) @endphp
                                    @if($canView)
                                    <a href="{{ route('documents.preview', $document) }}" target="_blank"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 hover:bg-purple-500/20 transition-colors flex-shrink-0"
                                        title="{{ __('app.preview') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178zM15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </a>
                                    @endif
                                    <a href="{{ route('documents.download', $document) }}" target="_blank"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition-colors flex-shrink-0"
                                        title="{{ __('app.download') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
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

    {{-- Report Modal --}}
    <div x-show="reportModal" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="reportModal = false"></div>
        <div class="relative bg-navy border border-[#C9A55A]/30 rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
                <h3 class="text-lg font-bold text-[#C9A55A]">{{ __('app.session_report') }}</h3>
                <button @click="reportModal = false" class="p-1 rounded-lg hover:bg-white/10 text-white/40 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-5 overflow-y-auto flex-1">
                <textarea x-model="reportText" rows="8"
                    class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] resize-y"
                    placeholder="{{ __('app.session_report_placeholder') }}"></textarea>
            </div>
            <div class="px-6 py-3 border-t border-white/10 flex justify-end gap-3">
                <button @click="reportModal = false" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                    {{ __('app.cancel') }}
                </button>
                <button @click="saveReport()" :disabled="reportSaving"
                    class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm disabled:opacity-50">
                    <span x-text="reportSaving ? '{{ __("app.saving") }}' : '{{ __("app.save") }}'"></span>
                </button>
            </div>
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
            <div class="summary-body px-6 py-5 overflow-y-auto flex-1 space-y-4" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
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
                        <span class="text-white/50 text-sm">{{ __('app.case_court') }}</span>
                        <span class="text-white text-sm">{{ $case->court }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-white/50 text-sm">{{ __('app.case_type') }}</span>
                        <span class="text-white text-sm">{{ $case->case_type ?? '—' }}</span>
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
                        <span class="text-white/50 text-sm">{{ __('app.opponent_name') }}</span>
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
                        <p class="text-xs text-white/50 mb-1">{{ __('app.next_session_date') }}</p>
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
@if(request()->has('print'))
<script nonce="{{ $cspNonce }}">window.onload = function() { window.print(); }</script>
@endif
@endpush

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
