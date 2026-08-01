@extends('layouts.app')

@section('title', __('app.page_case_details') . ' - ' . $case->case_number)

@push('styles')
<script nonce="{{ $cspNonce }}">
document.addEventListener('alpine:init', () => {
    Alpine.data('caseDetail', () => ({
        activeTab: 'sessions',
        showSummary: false,
        reportModal: false,
        reportSessionId: null,
        reportText: '',
        reportSaving: false,
        analyzing: false,
        analysis: @json($case->ai_analysis),
        analysisError: null,
        chatOpen: false,
        portalSending: false,
        portalResult: null,
        async sendPortalMessage() {
            if (this.portalSending) return;
            this.portalSending = true;
            this.portalResult = null;
            try {
                const res = await fetch('{{ route('cases.sendPortalMessage', $case) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => null);
                if (res.ok && data?.success) {
                    this.portalResult = { ok: true, text: data.message };
                } else {
                    this.portalResult = { ok: false, text: data?.error || '{{ __("app.save_error") }}' };
                    if (data?.fallback_wa_link) {
                        window.open(data.fallback_wa_link, '_blank');
                    }
                }
            } catch(e) {
                this.portalResult = { ok: false, text: '{{ __("app.connection_error") }}' };
            }
            this.portalSending = false;
            this.$nextTick(() => { if (this.portalResult) setTimeout(() => this.portalResult = null, 7000); });
        },
        aiMessages: @json($aiMessagesData ?? []),
        aiInput: '',
        aiSending: false,
        aiChatError: null,
        openAiChat() {
            this.chatOpen = true;
            this.$nextTick(() => this.scrollChat());
        },
        scrollChat() {
            const el = this.$refs.chatBox;
            if (el) el.scrollTop = el.scrollHeight;
        },
        md(text) {
            if (!text) return '';
            const esc = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const lines = esc.split('\n');
            let html = '', inList = false;
            const closeList = () => { if (inList) { html += '</ul>'; inList = false; } };
            for (let line of lines) {
                line = line.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                           .replace(/`([^`]+)`/g, '<code>$1</code>');
                const h1 = line.match(/^#\s+(.*)/);
                const h2 = line.match(/^##\s+(.*)/);
                const h3 = line.match(/^###\s+(.*)/);
                const hr = /^---+$/.test(line.trim());
                const li = line.match(/^\s*[-*]\s+(.*)/);
                if (h1) { closeList(); html += '<h1>' + h1[1] + '</h1>'; }
                else if (h2) { closeList(); html += '<h2>' + h2[1] + '</h2>'; }
                else if (h3) { closeList(); html += '<h3>' + h3[1] + '</h3>'; }
                else if (hr) { closeList(); html += '<hr>'; }
                else if (li) { if (!inList) { html += '<ul>'; inList = true; } html += '<li>' + li[1] + '</li>'; }
                else if (!line.trim()) { closeList(); }
                else { closeList(); html += '<p>' + line + '</p>'; }
            }
            closeList();
            return html;
        },
        async sendAiMessage() {
            const text = this.aiInput.trim();
            if (!text || this.aiSending) return;
            this.aiMessages.push({ id: 'user-' + Date.now(), role: 'user', content: text });
            this.aiInput = '';
            this.aiChatError = null;
            this.aiSending = true;
            this.$nextTick(() => this.scrollChat());
            try {
                const res = await fetch('{{ route('cases.ai_chat', $case) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ message: text })
                });
                const data = await res.json().catch(() => null);
                if (!res.ok) {
                    this.aiChatError = data?.error || '{{ __("app.save_error") }}';
                } else {
                    this.aiMessages.push({ id: 'ai-' + Date.now(), role: 'assistant', content: data.reply });
                }
            } catch(e) {
                this.aiChatError = '{{ __("app.connection_error") }}';
            }
            this.aiSending = false;
            this.$nextTick(() => this.scrollChat());
        },
        sessions: @json($sessionsData),
        quickSession: { date: '', location: '', status: 'upcoming', notes: '' },
        quickAdding: false,
        async quickAddSession() {
            if (this.quickAdding || !this.quickSession.date || !this.quickSession.location) return;
            this.quickAdding = true;
            try {
                const res = await fetch('{{ route('sessions.quick') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ case_id: {{ $case->id }}, ...this.quickSession })
                });
                const data = await res.json().catch(() => null);
                if (res.ok && data?.success) {
                    this.sessions.push(data.session);
                    this.quickSession = { date: '', location: '', status: 'upcoming', notes: '' };
                } else {
                    alert(data?.message || '{{ __("app.save_error") }}');
                }
            } catch(e) {
                alert('{{ __("app.connection_error") }}');
            }
            this.quickAdding = false;
        },
        sessionStatusLabel(status) {
            return { upcoming: '{{ __("app.status_upcoming") }}', completed: '{{ __("app.status_completed") }}', postponed: '{{ __("app.status_postponed") }}', cancelled: '{{ __("app.status_cancelled") }}' }[status] || status || '—';
        },
        sessionStatusClass(status) {
            return { completed: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-700', postponed: 'bg-orange-100 text-orange-700', upcoming: 'bg-blue-100 text-blue-700' }[status] || 'bg-blue-100 text-blue-700';
        },
        get reportSession() {
            if (!this.reportSessionId) return null;
            return this.sessions.find(s => s.id === this.reportSessionId) || null;
        },
        openReport(id) {
            this.reportSessionId = id;
            const s = this.reportSession;
            this.reportText = s ? s.report || '' : '';
            this.reportModal = true;
        },
        async saveReport() {
            const s = this.reportSession;
            if (!s || !s.id) return;
            this.reportSaving = true;
            try {
                const res = await fetch('{{ url('sessions') }}/' + s.id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        case_id: s.case_id,
                        date: s.date,
                        location: s.location,
                        status: s.status,
                        notes: s.notes || '',
                        report: this.reportText
                    })
                });
                this.reportSaving = false;
                if (res.ok) {
                    s.report = this.reportText;
                    this.reportModal = false;
                    this.reportSessionId = null;
                } else {
                    alert('{{ __("app.save_error") }}');
                }
            } catch(e) {
                this.reportSaving = false;
                alert('{{ __("app.connection_error") }}');
            }
        },
        async runAnalysis(force = false) {
            if (this.analyzing) return;
            if (this.analysis && !force) {
                this.showSummary = true;
                return;
            }
            this.analyzing = true;
            this.analysisError = null;
            this.showSummary = true;
            try {
                const res = await fetch('{{ route('cases.analyze', $case) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => null);
                if (!res.ok) {
                    this.analysisError = data?.error || '{{ __("app.save_error") }}';
                } else {
                    this.analysis = data.analysis;
                }
            } catch(e) {
                this.analysisError = '{{ __("app.connection_error") }}';
            }
            this.analyzing = false;
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

    {{-- Portal Notice (auto-send result after case creation) --}}
    @if(session('portal_notice'))
        <div class="flex items-start gap-3 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-xl text-sm" x-data="{ show: true }" x-show="show" x-cloak>
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="flex-1">{{ session('portal_notice') }}</span>
            <button @click="show = false" class="text-blue-400 hover:text-blue-700 font-bold px-1">&times;</button>
        </div>
    @endif

    {{-- Print Header (visible only in print) --}}
    <div class="print-only print-header">
        <h1 style="font-size:20px;color:#C9A55A;margin:0;">{{ __('app.case_number') }}: {{ $case->case_number }}</h1>
        <p style="color:#666;font-size:12px;margin:2px 0;">{{ $case->created_at->format('Y-m-d') }}</p>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-amber-700">{{ $case->case_number }}</h1>
            @if($case->title && $case->title !== $case->case_number)
                <p class="text-gray-400 text-sm mt-1">{{ $case->title }}</p>
            @endif
            @if($case->case_type)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 border border-amber-300 mt-2">
                    {{ $case->case_type }}
                </span>
            @endif
        </div>
        <div class="flex items-center gap-3">
            {{-- Summarize Button --}}
            <button @click="showSummary = true" class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                {{ __('app.case_summary') }}
            </button>
            {{-- AI Analysis Button --}}
            <button @click="runAnalysis()" :disabled="analyzing" class="bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm flex items-center gap-2">
                <svg x-show="!analyzing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/>
                </svg>
                <svg x-show="analyzing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="analyzing ? 'جارِ التحليل...' : 'تحليل بالذكاء الاصطناعي'"></span>
            </button>
            {{-- AI Chat Button --}}
            <button @click="openAiChat()" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
                {{ __('app.ai_chat_title') }}
            </button>
            {{-- Print Button --}}
            <button @click="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm flex items-center gap-2 no-print">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                {{ __('app.print') }}
            </button>
            {{-- Download PDF Button --}}
            <a href="{{ route('cases.file', $case) }}" class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                {{ __('app.download_case_pdf') }}
            </a>
            <a href="{{ route('cases.edit', $case->id) }}" class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
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
    <div class="bg-white rounded-xl border border-amber-200 p-6">
        <h2 class="text-lg font-bold text-amber-700 border-b border-gray-200 pb-3 mb-5">{{ __('app.related_people') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ __('app.case_client') }}</p>
                <div class="flex items-center gap-2">
                    <p class="text-gray-900 text-sm font-medium">{{ $case->client->name ?? '—' }}</p>
                    @if($case->client && ($case->client->phone || $case->client->email))
                    <button @click="sendPortalMessage()" :disabled="portalSending" class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors disabled:opacity-50" title="إرسال رسالة المتابعة للموكل تلقائياً (إيميل وواتساب)">
                        <svg x-show="!portalSending" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        <svg x-show="portalSending" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                    <div x-show="portalResult" x-cloak class="text-xs font-medium mt-0.5" :class="portalResult?.ok ? 'text-green-600' : 'text-red-600'" x-text="portalResult?.text"></div>
                    @endif
                </div>
            </div>
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ __('app.case_lawyer') }}</p>
                <p class="text-gray-900 text-sm">{{ $case->lawyer->name ?? '—' }}</p>
            </div>
        </div>
    </div>

    {{-- Case Info Card --}}
    <div class="bg-white rounded-xl border border-amber-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            {{-- Status --}}
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ __('app.status') }}</p>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    @if($case->status === 'active') bg-green-100 text-green-700 border border-green-200
                    @elseif($case->status === 'pending') bg-yellow-100 text-yellow-700 border border-yellow-200
                    @elseif($case->status === 'overdue') bg-red-100 text-red-700 border border-red-200
                    @elseif($case->status === 'closed') bg-gray-100 text-gray-400 border border-gray-200
                    @elseif($case->status === 'won') bg-blue-100 text-blue-700 border border-blue-200
                    @elseif($case->status === 'lost') bg-red-100 text-red-700 border border-red-200
                    @else bg-gray-100 text-gray-400 border border-gray-200 @endif">
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
                <p class="text-gray-400 text-xs mb-1">{{ __('app.priority') }}</p>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    @if($case->priority === 'low') bg-gray-100 text-gray-400 border border-gray-200
                    @elseif($case->priority === 'medium') bg-yellow-100 text-yellow-700 border border-yellow-200
                    @elseif($case->priority === 'high') bg-orange-100 text-orange-700 border border-orange-200
                    @elseif($case->priority === 'urgent') bg-red-100 text-red-700 border border-red-200
                    @else bg-gray-100 text-gray-400 border border-gray-200 @endif">
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
                <p class="text-gray-400 text-xs mb-1">{{ __('app.case_type') }}</p>
                <p class="text-gray-900 text-sm">{{ $case->case_type ?? '—' }}</p>
            </div>

            {{-- Court + Case Number --}}
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ __('app.case_court') }}</p>
                <p class="text-gray-900 text-sm">{{ $case->court }} <span class="text-gray-400 text-xs">({{ $case->case_number }})</span></p>
            </div>

            {{-- Office Case Number --}}
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ __('app.office_case_number') }}</p>
                <p class="text-gray-900 text-sm">{{ $case->office_case_number ?? '—' }}</p>
            </div>

        </div>
    </div>

    {{-- Opponent Data Card --}}
    <div class="bg-white rounded-xl border border-amber-200 p-6">
        <h2 class="text-lg font-bold text-amber-700 border-b border-gray-200 pb-3 mb-5">{{ __('app.opponent_data') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ __('app.opponent_name') }}</p>
                <p class="text-gray-900 text-sm">{{ $case->opponent ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ __('app.opponent_phone') }}</p>
                <p class="text-gray-900 text-sm">{{ $case->opponent_phone ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ __('app.opponent_address') }}</p>
                <p class="text-gray-900 text-sm">{{ $case->opponent_address ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ __('app.opponent_lawyer') }}</p>
                <p class="text-gray-900 text-sm">{{ $case->opponent_lawyer ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ __('app.opponent_civil_number') }}</p>
                <p class="text-gray-900 text-sm">{{ $case->opponent_civil_number ?? '—' }}</p>
            </div>
        </div>
    </div>

    {{-- Description --}}
    @if($case->description)
    <div class="bg-white rounded-xl border border-amber-200 p-6">
        <p class="text-gray-400 text-xs mb-2">{{ __('app.case_description') }}</p>
        <p class="text-gray-900 text-sm leading-relaxed">{{ $case->description }}</p>
    </div>
    @endif

    {{-- Tabs --}}
    <div class="bg-white rounded-xl border border-amber-200 overflow-hidden">
        {{-- Tab Headers --}}
        <div class="flex border-b border-gray-200" role="tablist">
            <button @click="activeTab = 'sessions'" :class="activeTab === 'sessions' ? 'text-amber-700 border-b-2 border-amber-700 bg-gray-100' : 'text-gray-400 hover:text-gray-600'"
                class="flex-1 px-4 py-3 text-sm font-medium transition-colors" role="tab">
                {{ __('app.sessions_tab') }} (<span x-text="sessions.length">{{ $case->sessions->count() ?? 0 }}</span>)
            </button>
            <button @click="activeTab = 'tasks'" :class="activeTab === 'tasks' ? 'text-amber-700 border-b-2 border-amber-700 bg-gray-100' : 'text-gray-400 hover:text-gray-600'"
                class="flex-1 px-4 py-3 text-sm font-medium transition-colors" role="tab">
                {{ __('app.tasks_tab') }} ({{ $case->tasks->count() ?? 0 }})
            </button>
            <button @click="activeTab = 'documents'" :class="activeTab === 'documents' ? 'text-amber-700 border-b-2 border-amber-700 bg-gray-100' : 'text-gray-400 hover:text-gray-600'"
                class="flex-1 px-4 py-3 text-sm font-medium transition-colors" role="tab">
                {{ __('app.documents_tab') }} ({{ $case->documents->count() ?? 0 }})
            </button>
        </div>

        {{-- Tab Content: Sessions --}}
        <div x-show="activeTab === 'sessions'" x-cloak class="p-4">
            <form @submit.prevent="quickAddSession()" class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-4">
                <div class="flex items-end gap-3 flex-wrap">
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('app.table_date') }} *</label>
                        <input type="datetime-local" x-model="quickSession.date" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('app.session_location') }} *</label>
                        <input type="text" x-model="quickSession.location" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                    </div>
                    <div class="w-36">
                        <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('app.status') }}</label>
                        <select x-model="quickSession.status"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                            <option value="upcoming">{{ __('app.status_upcoming') }}</option>
                            <option value="completed">{{ __('app.status_completed') }}</option>
                            <option value="postponed">{{ __('app.status_postponed') }}</option>
                            <option value="cancelled">{{ __('app.status_cancelled') }}</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('app.table_notes') }}</label>
                        <input type="text" x-model="quickSession.notes"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                    </div>
                    <button type="submit" :disabled="quickAdding"
                            class="bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg font-semibold transition-colors text-sm inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span x-text="quickAdding ? '{{ __('app.saving') }}...' : '{{ __('app.add_session') }}'"></span>
                    </button>
                </div>
            </form>
            <div x-show="sessions.length > 0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-right">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="px-3 py-2 text-amber-700 font-bold text-xs">{{ __('app.table_date') }}</th>
                                <th class="px-3 py-2 text-amber-700 font-bold text-xs">{{ __('app.table_type') }}</th>
                                <th class="px-3 py-2 text-amber-700 font-bold text-xs">{{ __('app.status') }}</th>
                                <th class="px-3 py-2 text-amber-700 font-bold text-xs">{{ __('app.table_notes') }}</th>
                                <th class="px-3 py-2 text-amber-700 font-bold text-xs">{{ __('app.session_decision') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="s in sessions" :key="s.id">
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-3 py-2.5 text-gray-900 text-xs whitespace-nowrap" x-text="s.date || '—'"></td>
                                    <td class="px-3 py-2.5 text-gray-400 text-xs" x-text="s.location || '—'"></td>
                                    <td class="px-3 py-2.5">
                                        <span class="text-xs px-2 py-0.5 rounded-full" :class="sessionStatusClass(s.status)" x-text="sessionStatusLabel(s.status)"></span>
                                    </td>
                                    <td class="px-3 py-2.5 text-gray-400 text-xs max-w-[200px] truncate" x-text="s.notes || '—'"></td>
                                    <td class="px-3 py-2.5">
                                        <button @click="openReport(s.id)" class="text-xs"
                                            :class="reportSession && reportSession.id === s.id && reportText ? 'text-amber-700' : 'text-gray-400 hover:text-amber-700'"
                                            x-text="s.report ? '{{ __('app.view_decision') }}' : '{{ __('app.add_decision') }}'"></button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
            <div x-show="sessions.length === 0" class="text-center py-8 text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p class="text-sm">{{ __('app.no_sessions_recorded') }}</p>
            </div>
        </div>

        {{-- Tab Content: Tasks --}}
        <div x-show="activeTab === 'tasks'" x-cloak class="p-4">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm text-gray-400">{{ __('app.tasks_tab') }} ({{ $case->tasks->count() ?? 0 }})</p>
                <a href="{{ route('tasks.create', ['case_id' => $case->id]) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition-colors" title="إضافة مهمة">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </a>
            </div>
            @if($case->tasks && $case->tasks->count() > 0)
                <div class="space-y-2">
                    @foreach($case->tasks as $task)
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-100 border border-gray-100 hover:border-amber-200 transition-colors">
                            <div class="flex-shrink-0">
                                @if($task->status === 'completed')
                                    <div class="w-5 h-5 rounded-full bg-green-100 flex items-center justify-center">
                                        <svg class="w-3 h-3 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                @else
                                    <div class="w-5 h-5 rounded-full border-2 border-gray-200"></div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-gray-900 text-sm {{ $task->status === 'completed' ? 'line-through opacity-50' : '' }}">{{ $task->title }}</p>
                                @if($task->due_date)
                                    <p class="text-gray-500 text-xs mt-0.5">{{ __('app.deadline') }} {{ \Carbon\Carbon::parse($task->due_date)->format('Y/m/d') }}</p>
                                @endif
                            </div>
                            <span class="px-2 py-0.5 text-xs rounded-full flex-shrink-0
                                @if(($task->priority ?? '') === 'urgent') bg-red-100 text-red-700
                                @elseif(($task->priority ?? '') === 'high') bg-orange-100 text-orange-700
                                @elseif(($task->priority ?? '') === 'medium') bg-yellow-100 text-yellow-700
                                @else bg-gray-100 text-gray-400 @endif">
                                {{ $task->priority ?? '' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-sm">{{ __('app.no_tasks_recorded') }}</p>
                </div>
            @endif
        </div>

        {{-- Tab Content: Documents --}}
        <div x-show="activeTab === 'documents'" x-cloak class="p-4">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm text-gray-400">{{ __('app.documents_tab') }} ({{ $case->documents->count() ?? 0 }})</p>
                <a href="{{ route('documents.index', ['case_id' => $case->id]) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition-colors" title="إضافة مستند">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </a>
            </div>
            @if($case->documents && $case->documents->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($case->documents as $document)
                        <div class="p-3 rounded-lg bg-gray-100 border border-gray-100 hover:border-amber-200 transition-colors">
        <div class="flex flex-wrap items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0">
                                    @if(str_contains($document->file_type ?? '', 'pdf'))
                                        <svg class="w-5 h-5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                    @elseif(str_contains($document->file_type ?? '', 'image'))
                                        <svg class="w-5 h-5 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-gray-900 text-sm truncate">{{ $document->title ?? $document->name ?? __('app.documents') }}</p>
                                    <p class="text-gray-500 text-xs">{{ $document->created_at?->format('Y/m/d') ?? '' }}</p>
                                </div>
                                @if(isset($document->file_path))
                                    @php $canView = in_array(strtolower($document->file_type ?? ''), ['pdf', 'jpg', 'jpeg', 'png']) @endphp
                                    @if($canView)
                                    <a href="{{ route('documents.preview', $document) }}" target="_blank"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-purple-100 text-purple-700 hover:bg-purple-200 transition-colors flex-shrink-0"
                                        title="{{ __('app.preview') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178zM15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </a>
                                    @endif
                                    <a href="{{ route('documents.download', $document) }}" target="_blank"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 transition-colors flex-shrink-0"
                                        title="{{ __('app.download') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
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
        <div class="relative bg-white border border-amber-300 rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-amber-700">{{ __('app.session_decision') }}</h3>
                <button @click="reportModal = false" class="p-1 rounded-lg hover:bg-gray-200 text-gray-400 hover:text-gray-900 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-5 overflow-y-auto flex-1">
                <textarea x-model="reportText" rows="8"
                    class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 resize-y"
                    placeholder="{{ __('app.session_decision_placeholder') }}"></textarea>
            </div>
            <div class="px-6 py-3 border-t border-gray-200 flex justify-end gap-3">
                <button @click="reportModal = false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">
                    {{ __('app.cancel') }}
                </button>
                <button @click="saveReport()" :disabled="reportSaving"
                    class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm disabled:opacity-50">
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
        <div class="relative bg-white border border-amber-300 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100">
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-amber-700">{{ __('app.case_summary') }}</h3>
                <button @click="showSummary = false" class="p-1 rounded-lg hover:bg-gray-200 text-gray-400 hover:text-gray-900 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="summary-body px-6 py-5 overflow-y-auto flex-1 space-y-4" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
                {{-- Status & Priority --}}
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-white rounded-xl p-3 border border-gray-100">
                        <p class="text-xs text-gray-500 mb-1">{{ __('app.status') }}</p>
                        @php
                            $statusMap = ['active'=>__('app.status_active'),'pending'=>__('app.status_pending'),'overdue'=>__('app.status_overdue'),'closed'=>__('app.status_closed'),'won'=>__('app.status_won'),'lost'=>__('app.status_lost')];
                            $statusColors = ['active'=>'text-emerald-700','pending'=>'text-amber-700','overdue'=>'text-red-700','closed'=>'text-gray-400','won'=>'text-green-700','lost'=>'text-red-700'];
                        @endphp
                        <p class="font-bold {{ $statusColors[$case->status] ?? 'text-gray-900' }}">{{ $statusMap[$case->status] ?? $case->status }}</p>
                    </div>
                    <div class="bg-white rounded-xl p-3 border border-gray-100">
                        <p class="text-xs text-gray-500 mb-1">{{ __('app.priority') }}</p>
                        @php
                            $priorityMap = ['low'=>__('app.priority_low'),'medium'=>__('app.priority_medium'),'high'=>__('app.priority_high'),'urgent'=>__('app.priority_urgent')];
                            $priorityColors = ['low'=>'text-gray-400','medium'=>'text-amber-700','high'=>'text-orange-700','urgent'=>'text-red-700'];
                        @endphp
                        <p class="font-bold {{ $priorityColors[$case->priority] ?? 'text-gray-900' }}">{{ $priorityMap[$case->priority] ?? $case->priority }}</p>
                    </div>
                </div>

                {{-- Info --}}
                <div class="bg-white rounded-xl p-4 border border-gray-100 space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 text-sm">{{ __('app.case_number') }}</span>
                        <span class="text-gray-900 font-mono text-sm">{{ $case->case_number }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 text-sm">{{ __('app.case_court') }}</span>
                        <span class="text-gray-900 text-sm">{{ $case->court }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 text-sm">{{ __('app.case_type') }}</span>
                        <span class="text-gray-900 text-sm">{{ $case->case_type ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 text-sm">{{ __('app.case_client') }}</span>
                        <span class="text-amber-700 text-sm font-medium">{{ $case->client?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 text-sm">{{ __('app.case_lawyer') }}</span>
                        <span class="text-gray-900 text-sm">{{ $case->lawyer?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 text-sm">{{ __('app.opponent_name') }}</span>
                        <span class="text-gray-900 text-sm">{{ $case->opponent ?: '—' }}</span>
                    </div>
                </div>

                {{-- Description --}}
                @if($case->description)
                    <div class="bg-white rounded-xl p-4 border border-gray-100">
                        <p class="text-gray-500 text-xs mb-2">{{ __('app.case_description') }}</p>
                        <p class="text-gray-700 text-sm leading-relaxed">{{ $case->description }}</p>
                    </div>
                @endif

                {{-- AI Analysis --}}
                <div x-show="analysis || analyzing" class="bg-white rounded-xl p-5 border border-indigo-200">
                    <div class="flex items-center justify-between mb-3 bg-indigo-50 -m-5 mb-4 p-4 rounded-t-xl border-b border-indigo-200">
                        <h4 class="text-sm font-bold text-indigo-700 flex items-center gap-2">
                            <svg class="w-4 h-4 no-print" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                            </svg>
                            تحليل الذكاء الاصطناعي
                        </h4>
                        <span class="text-[10px] text-indigo-400 font-medium bg-white px-2 py-0.5 rounded-full border border-indigo-200">Gemini</span>
                        <button @click="runAnalysis(true)" class="text-[10px] text-indigo-500 hover:text-indigo-700 font-medium bg-white px-2 py-0.5 rounded-full border border-indigo-200 hover:bg-indigo-50 transition-colors">
                            <svg class="w-3 h-3 inline -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            إعادة التحليل
                        </button>
                    </div>
                    <div x-show="analyzing" class="flex items-center gap-3 text-indigo-600 text-sm py-2">
                        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        جارِ تحليل القضية... قد يستغرق التحليل بضع ثوانٍ
                    </div>
                    <div x-show="analysis" x-html="md(analysis)" class="ai-content text-sm text-gray-800 leading-relaxed"></div>
                    <div x-show="analysisError" x-text="analysisError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3"></div>
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-2 gap-3">
                    @php $latestSession = $case->sessions->sortByDesc('date')->first(); @endphp
                    <div class="bg-white rounded-xl p-3 border border-gray-100">
                        <p class="text-xs text-gray-500 mb-1">{{ __('app.total_sessions') }}</p>
                        <p class="text-gray-900 text-sm font-bold" x-text="sessions.length">{{ $case->sessions->count() }}</p>
                    </div>
                    <div class="bg-white rounded-xl p-3 border border-gray-100">
                        <p class="text-xs text-gray-500 mb-1">{{ __('app.last_session_date') }}</p>
                        <p class="text-gray-900 text-sm">{{ $latestSession?->date?->format('Y/m/d') ?? '—' }}</p>
                    </div>
                </div>

                {{-- Counts --}}
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-white rounded-xl p-3 border border-gray-100 text-center">
                        <p class="text-2xl font-bold text-amber-700" x-text="sessions.length">{{ $case->sessions->count() }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ __('app.sessions') }}</p>
                    </div>
                    <div class="bg-white rounded-xl p-3 border border-gray-100 text-center">
                        <p class="text-2xl font-bold text-amber-700">{{ $case->tasks->count() }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ __('app.tasks') }}</p>
                    </div>
                    <div class="bg-white rounded-xl p-3 border border-gray-100 text-center">
                        <p class="text-2xl font-bold text-amber-700">{{ $case->documents->count() }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ __('app.documents') }}</p>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-3 border-t border-gray-200 flex justify-between items-center">
                <button @click="printSummary()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    {{ __('app.print_summary') }}
                </button>
                <button @click="copySummary()" class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    {{ __('app.copy_summary') }}
                </button>
            </div>
        </div>
    </div>

{{-- AI Chat Modal --}}
<div x-show="chatOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="chatOpen = false"></div>
    <div class="relative bg-white border border-emerald-300 rounded-2xl shadow-2xl w-full max-w-lg max-h-[85vh] flex flex-col"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 bg-gradient-to-r from-emerald-600 to-teal-600 rounded-t-2xl">
            <div class="flex items-center gap-2 text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/>
                </svg>
                <div>
                    <h3 class="font-bold text-sm">{{ __('app.ai_chat_title') }}</h3>
                    <p class="text-[10px] text-emerald-100">{{ __('app.ai_chat_subtitle') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-white bg-white/20 px-2 py-0.5 rounded-full">Gemini</span>
                <button @click="chatOpen = false" class="p-1 rounded-lg hover:bg-white/20 text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <div x-ref="chatBox" class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-gray-50" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
            <template x-if="aiMessages.length === 0">
                <div class="text-center py-8">
                    <div class="w-14 h-14 mx-auto mb-3 bg-emerald-100 rounded-full flex items-center justify-center">
                        <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                    </div>
                    <p class="text-sm text-gray-600 leading-relaxed max-w-xs mx-auto">{{ __('app.ai_chat_greeting') }}</p>
                </div>
            </template>

            <template x-for="m in aiMessages" :key="m.id ? m.id : $index">
                <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="m.role === 'user'
                        ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-2xl rounded-tr-sm max-w-[85%] px-4 py-2.5 shadow-sm'
                        : 'bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-tl-sm max-w-[85%] px-4 py-2.5 shadow-sm'">
                        <div class="text-sm leading-relaxed" :class="m.role === 'user' ? 'whitespace-pre-wrap' : 'ai-content'" x-html="m.role === 'assistant' ? md(m.content) : m.content"></div>
                        <p class="text-[10px] mt-1.5" :class="m.role === 'user' ? 'text-emerald-100' : 'text-gray-400'" x-text="m.created_at || ''"></p>
                    </div>
                </div>
            </template>

            <div x-show="aiSending" class="flex justify-start">
                <div class="bg-white border border-gray-200 rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-xs text-gray-500">{{ __('app.ai_chat_typing') }}</span>
                </div>
            </div>

            <div x-show="aiChatError" x-text="aiChatError" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg p-3"></div>
        </div>

        <div class="px-4 py-3 border-t border-gray-200 bg-white">
            <p class="text-[10px] text-gray-400 mb-2">{{ __('app.ai_chat_disclaimer') }}</p>
            <div class="flex items-end gap-2">
                <textarea x-model="aiInput" rows="1" :disabled="aiSending"
                    @keydown.enter.prevent="if (!$event.shiftKey) sendAiMessage()"
                    class="flex-1 rounded-xl bg-gray-50 border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 resize-none disabled:opacity-50"
                    placeholder="{{ __('app.ai_chat_placeholder') }}"></textarea>
                <button @click="sendAiMessage()" :disabled="aiSending || !aiInput.trim()"
                    class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white px-4 py-2.5 rounded-xl font-semibold transition-colors text-sm disabled:opacity-50 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    {{ __('app.ai_chat_send') }}
                </button>
            </div>
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
    .ai-content { line-height: 1.9; }
    .ai-content strong { color: #6d28d9; font-weight: 700; }
    .ai-content h1, .ai-content h2, .ai-content h3 { color: #5b21b6; font-weight: 700; margin: 1rem 0 0.5rem; line-height: 1.5; }
    .ai-content h1 { font-size: 1.05rem; }
    .ai-content h2 { font-size: 1rem; }
    .ai-content h3 { font-size: 0.95rem; }
    .ai-content ul { list-style: disc; padding-inline-start: 1.25rem; margin: 0.5rem 0; }
    .ai-content li { margin: 0.3rem 0; }
    .ai-content p { margin: 0.5rem 0; }
    .ai-content hr { border: 0; border-top: 1px dashed #c7d2fe; margin: 0.75rem 0; }
    .ai-content code { background: #ede9fe; color: #5b21b6; padding: 0 0.3rem; border-radius: 0.25rem; font-size: 0.85em; }
</style>
@endsection
