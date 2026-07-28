@extends('layouts.app')

@section('title', "قضايا $monthName $year")

@section('content')
<div class="space-y-6" dir="rtl" x-data="monthlyCases()" x-init="init()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 print:hidden">
        <h1 class="text-2xl font-bold text-gold">📋 قضايا <span x-text="monthNames[month]"></span> <span x-text="year"></span></h1>
        <div class="flex items-center gap-3">
            <button id="printBtn" class="bg-gold hover:bg-gold-dark text-navy px-5 py-2.5 rounded-lg font-semibold transition-colors text-sm inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                طباعة
            </button>
            <a href="{{ route('cases.index') }}" class="bg-white/10 hover:bg-white/20 text-white/70 px-5 py-2.5 rounded-lg font-medium transition-colors text-sm">
                رجوع
            </a>
        </div>
    </div>

    {{-- Month/Year Panel --}}
    <div class="bg-navy rounded-xl border border-gold/20 p-5 print:hidden" x-data="{ yearOpen: false }">
        {{-- Year Bar --}}
        <div class="flex items-center gap-2 mb-4 pb-4 border-b border-ivory/5">
            <span class="text-ivory/50 text-xs font-bold uppercase tracking-wider ml-3">السنة</span>
            <div class="flex flex-wrap gap-1.5">
                <template x-for="y in years" :key="y">
                    <button @click="year = y; fetchData()"
                        class="px-4 py-1.5 rounded-lg text-sm font-medium transition-all duration-150"
                        :class="year === y ? 'bg-gold text-navy-darkest shadow-lg shadow-gold/20' : 'bg-white/5 text-ivory/60 hover:bg-white/10 hover:text-ivory'"
                        x-text="y">
                    </button>
                </template>
            </div>
        </div>
        {{-- Month Grid --}}
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
            <template x-for="(name, num) in monthNames" :key="num">
                <button @click="month = num; fetchData()"
                    class="px-3 py-3 rounded-xl text-sm font-medium transition-all duration-150 text-center"
                    :class="month == num ? 'bg-gold text-navy-darkest shadow-lg shadow-gold/20 ring-2 ring-gold/50' : 'bg-white/5 text-ivory/60 hover:bg-white/10 hover:text-ivory hover:shadow-md'"
                    x-text="name">
                </button>
            </template>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 print:gap-3">
        <div class="bg-navy-light rounded-xl border border-ivory/10 p-4 text-center">
            <p class="text-2xl font-bold text-gold" x-text="summary.total">0</p>
            <p class="text-ivory/50 text-sm mt-1">إجمالي القضايا</p>
        </div>
        <div class="bg-navy-light rounded-xl border border-ivory/10 p-4 text-center">
            <p class="text-2xl font-bold text-green-400" x-text="summary.active">0</p>
            <p class="text-ivory/50 text-sm mt-1">نشطة</p>
        </div>
        <div class="bg-navy-light rounded-xl border border-ivory/10 p-4 text-center">
            <p class="text-2xl font-bold text-blue-400" x-text="summary.pending">0</p>
            <p class="text-ivory/50 text-sm mt-1">معلقة</p>
        </div>
        <div class="bg-navy-light rounded-xl border border-ivory/10 p-4 text-center">
            <p class="text-2xl font-bold text-ivory/60" x-text="summary.closed">0</p>
            <p class="text-ivory/50 text-sm mt-1">منتهية</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-navy-light rounded-xl border border-ivory/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gold/10 border-b border-gold/20">
                        <th class="px-4 py-3 text-right text-gold font-bold">#</th>
                        <th class="px-4 py-3 text-right text-gold font-bold">رقم القضية</th>
                        <th class="px-4 py-3 text-right text-gold font-bold">الموضوع</th>
                        <th class="px-4 py-3 text-right text-gold font-bold">العميل</th>
                        <th class="px-4 py-3 text-right text-gold font-bold">المحكمة</th>
                        <th class="px-4 py-3 text-right text-gold font-bold">الحالة</th>
                        <th class="px-4 py-3 text-right text-gold font-bold">تاريخ الفتح</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(caseItem, idx) in cases" :key="caseItem.id">
                        <tr class="border-b border-ivory/5 hover:bg-gold/5 transition">
                            <td class="px-4 py-3 text-ivory/50" x-text="idx + 1"></td>
                            <td class="px-4 py-3">
                                <a :href="caseItem.show_url" class="text-gold hover:text-gold-light font-medium" x-text="caseItem.case_number"></a>
                            </td>
                            <td class="px-4 py-3 text-ivory/80 max-w-[200px] truncate" x-text="caseItem.title"></td>
                            <td class="px-4 py-3 text-ivory/70">
                                <template x-if="caseItem.client_name">
                                    <a :href="caseItem.client_url" class="hover:text-gold transition" x-text="caseItem.client_name"></a>
                                </template>
                                <template x-if="!caseItem.client_name">
                                    <span class="text-ivory/30">—</span>
                                </template>
                            </td>
                            <td class="px-4 py-3 text-ivory/70" x-text="caseItem.court || '—'"></td>
                            <td class="px-4 py-3">
                                <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold" :class="statusColors[caseItem.status]" x-text="statusLabels[caseItem.status] || caseItem.status"></span>
                            </td>
                            <td class="px-4 py-3 text-ivory/50 text-xs" x-text="caseItem.opened_at || '—'"></td>
                        </tr>
                    </template>
                    <tr x-show="cases.length === 0 && !loading">
                        <td colspan="7" class="px-4 py-16 text-center text-ivory/30">
                            <svg class="w-12 h-12 mx-auto mb-3 text-ivory/10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <p x-text="'لا توجد قضايا في ' + monthNames[month] + ' ' + year"></p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Footer for print --}}
    <div class="text-center text-ivory/30 text-xs pt-4 hidden print:block">
        <p>تم الإنشاء في {{ now()->format('Y-m-d H:i') }}</p>
    </div>
</div>

{{-- Print Styles --}}
@push('styles')
<style nonce="{{ $cspNonce }}">
@media print {
    body { background: white !important; }
    aside, .sidebar, .content-area aside, nav, .print\\:hidden { display: none !important; }
    .content-area { margin-right: 0 !important; margin-left: 0 !important; padding: 20px !important; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #C9A55A !important; color: white !important; padding: 10px 12px !important; font-size: 12px !important; }
    td { padding: 8px 12px !important; border: 1px solid #ddd !important; font-size: 11px !important; color: #333 !important; }
    tr:nth-child(even) td { background: #f8f6f0 !important; }
    a { color: #C9A55A !important; text-decoration: none !important; }
    .text-gold { color: #C9A55A !important; }
}
</style>
@endpush

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.getElementById('printBtn')?.addEventListener('click', function() {
    window.print();
});

function monthlyCases() {
    return {
        month: {{ $month }},
        year: {{ $year }},
        loading: false,
        cases: JSON.parse('{!! json_encode($casesJson) !!}'),
        summary: JSON.parse('{!! json_encode($summaryJson) !!}'),
        monthNames: {!! json_encode($months) !!},
        years: {!! json_encode($years) !!},
        statusColors: {
            active: 'bg-green-500/15 text-green-400',
            pending: 'bg-yellow-500/15 text-yellow-400',
            overdue: 'bg-red-500/15 text-red-400',
            closed: 'bg-gray-500/15 text-gray-400',
            won: 'bg-emerald-500/15 text-emerald-400',
            lost: 'bg-red-500/15 text-red-400',
        },
        statusLabels: {
            active: 'نشطة', pending: 'معلقة', overdue: 'متأخرة',
            closed: 'مغلقة', won: 'مربوحة', lost: 'خاسرة',
        },
        init() {},
        fetchData() {
            this.loading = true;
            fetch('/cases/monthly/data?month=' + this.month + '&year=' + this.year, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                this.cases = data.cases;
                this.summary = data.summary;
                this.loading = false;
            })
            .catch(() => { this.loading = false; });
        }
    }
}
</script>
@endpush
@endsection
