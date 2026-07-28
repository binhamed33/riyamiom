@extends('layouts.app')

@section('title', "قضايا $monthName $year")

@section('content')
<div class="space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 print:hidden">
        <h1 class="text-2xl font-bold text-gold">📋 قضايا {{ $monthName }} {{ $year }}</h1>
        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="bg-gold hover:bg-gold-dark text-navy px-5 py-2.5 rounded-lg font-semibold transition-colors text-sm inline-flex items-center gap-2">
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

    {{-- Month Selector --}}
    <form method="GET" action="{{ route('cases.monthly') }}" class="bg-navy rounded-xl border border-gold/20 p-4 print:hidden">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-ivory/60 text-xs mb-1.5">الشهر</label>
                <select name="month" class="rounded-lg bg-navy-darker border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-gold focus:border-gold">
                    @foreach($months as $num => $name)
                        <option value="{{ $num }}" {{ (int)$month === $num ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-ivory/60 text-xs mb-1.5">السنة</label>
                <select name="year" class="rounded-lg bg-navy-darker border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-gold focus:border-gold">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ (int)$year === $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
                عرض
            </button>
        </div>
    </form>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 print:gap-3">
        <div class="bg-navy-light rounded-xl border border-ivory/10 p-4 text-center">
            <p class="text-2xl font-bold text-gold">{{ $summary['total'] }}</p>
            <p class="text-ivory/50 text-sm mt-1">إجمالي القضايا</p>
        </div>
        <div class="bg-navy-light rounded-xl border border-ivory/10 p-4 text-center">
            <p class="text-2xl font-bold text-green-400">{{ $summary['active'] }}</p>
            <p class="text-ivory/50 text-sm mt-1">نشطة</p>
        </div>
        <div class="bg-navy-light rounded-xl border border-ivory/10 p-4 text-center">
            <p class="text-2xl font-bold text-blue-400">{{ $summary['pending'] }}</p>
            <p class="text-ivory/50 text-sm mt-1">معلقة</p>
        </div>
        <div class="bg-navy-light rounded-xl border border-ivory/10 p-4 text-center">
            <p class="text-2xl font-bold text-ivory/60">{{ $summary['closed'] }}</p>
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
                    @forelse($cases as $case)
                        <tr class="border-b border-ivory/5 hover:bg-gold/5 transition">
                            <td class="px-4 py-3 text-ivory/50">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('cases.show', $case) }}" class="text-gold hover:text-gold-light font-medium">
                                    {{ $case->case_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-ivory/80 max-w-[200px] truncate">{{ $case->title }}</td>
                            <td class="px-4 py-3 text-ivory/70">
                                @if($case->client)
                                    <a href="{{ route('clients.show', $case->client) }}" class="hover:text-gold transition">
                                        {{ $case->client->name }}
                                    </a>
                                @else
                                    <span class="text-ivory/30">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-ivory/70">{{ $case->court ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $statusColors = [
                                        'active' => 'bg-green-500/15 text-green-400',
                                        'pending' => 'bg-yellow-500/15 text-yellow-400',
                                        'overdue' => 'bg-red-500/15 text-red-400',
                                        'closed' => 'bg-gray-500/15 text-gray-400',
                                        'won' => 'bg-emerald-500/15 text-emerald-400',
                                        'lost' => 'bg-red-500/15 text-red-400',
                                    ];
                                    $statusLabels = [
                                        'active' => 'نشطة', 'pending' => 'معلقة', 'overdue' => 'متأخرة',
                                        'closed' => 'مغلقة', 'won' => 'مربوحة', 'lost' => 'خاسرة',
                                    ];
                                    $color = $statusColors[$case->status] ?? 'bg-white/10 text-ivory/60';
                                    $label = $statusLabels[$case->status] ?? $case->status;
                                @endphp
                                <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold {{ $color }}">
                                    {{ $label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-ivory/50 text-xs">
                                {{ $case->opened_at ? $case->opened_at->format('Y-m-d') : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center text-ivory/30">
                                <svg class="w-12 h-12 mx-auto mb-3 text-ivory/10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <p>لا توجد قضايا في {{ $monthName }} {{ $year }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Footer for print --}}
    <div class="text-center text-ivory/30 text-xs pt-4 hidden print:block">
        <p>تم الإنشاء في {{ now()->format('Y-m-d H:i') }} — قضايا {{ $monthName }} {{ $year }}</p>
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
@endsection
