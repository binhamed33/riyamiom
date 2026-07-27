@extends('layouts.app')

@section('title', __('app.hr'))

@php $isAdmin = in_array(auth()->user()->role, ['developer', 'admin']); @endphp

@section('content')
<div class="">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-[#C9A55A]">{{ __('app.hr') }}</h1>
    </div>

    @if($isAdmin)
    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-5">
            <p class="text-xs text-white/40 mb-1">إجمالي الموظفين</p>
            <p class="text-2xl font-bold text-[#C9A55A]">{{ $stats['total_employees'] }}</p>
        </div>
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-5">
            <p class="text-xs text-white/40 mb-1">متوسط التقييم</p>
            <p class="text-2xl font-bold text-[#C9A55A]">{{ number_format($stats['avg_rating'] ?? 0, 1) }}/5</p>
        </div>
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-5">
            <p class="text-xs text-white/40 mb-1">إجمالي المكافآت</p>
            <p class="text-2xl font-bold text-green-400">{{ number_format($stats['total_bonuses'], 2) }} ر.ع</p>
        </div>
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-5">
            <p class="text-xs text-white/40 mb-1">إجمالي الجزاءات</p>
            <p class="text-2xl font-bold text-red-400">{{ number_format($stats['total_penalties'], 2) }} ر.ع</p>
        </div>
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-5">
            <p class="text-xs text-white/40 mb-1">إجازات pending</p>
            <p class="text-2xl font-bold text-yellow-400">{{ $stats['pending_leaves'] }}</p>
        </div>
    </div>
    @endif

    {{-- Tabs --}}
    <div class="mb-6 border-b border-white/10 flex gap-1 overflow-x-auto">
        @if($isAdmin)
        <a href="{{ route('hr.index', ['tab' => 'employees']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'employees' ? 'text-[#C9A55A] bg-white/5 border-b-2 border-[#C9A55A]' : 'text-white/40 hover:text-white/60' }}">الموظفون</a>
        @endif
        <a href="{{ route('hr.index', ['tab' => 'performance']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'performance' ? 'text-[#C9A55A] bg-white/5 border-b-2 border-[#C9A55A]' : 'text-white/40 hover:text-white/60' }}">التقييمات</a>
        <a href="{{ route('hr.index', ['tab' => 'bonuses']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'bonuses' ? 'text-[#C9A55A] bg-white/5 border-b-2 border-[#C9A55A]' : 'text-white/40 hover:text-white/60' }}">المكافآت</a>
        <a href="{{ route('hr.index', ['tab' => 'penalties']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'penalties' ? 'text-[#C9A55A] bg-white/5 border-b-2 border-[#C9A55A]' : 'text-white/40 hover:text-white/60' }}">الجزاءات</a>
        <a href="{{ route('hr.index', ['tab' => 'leaves']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'leaves' ? 'text-[#C9A55A] bg-white/5 border-b-2 border-[#C9A55A]' : 'text-white/40 hover:text-white/60' }}">الإجازات</a>
    </div>

    {{-- Tab Content --}}
    @if($tab === 'employees' && $isAdmin)
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 overflow-hidden">
            @if(count($chartData) > 0)
            <div class="p-5 border-b border-white/10">
                <h3 class="text-sm font-bold text-[#C9A55A] mb-4">تحليل أداء الموظفين</h3>
                <div class="grid grid-cols-2 gap-6 max-h-64">
                    <div><canvas id="hrCasesChart"></canvas></div>
                    <div><canvas id="hrRatingChart"></canvas></div>
                </div>
            </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-white/10"><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">الاسم</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">البريد</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">الدور</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">قضايا</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">مهام</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">التقييم</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">الحالة</th></tr></thead>
                    <tbody>
                        @foreach($employees as $emp)
                            @php $d = collect($chartData)->firstWhere('name', $emp->name) ?? ['cases'=>0,'tasks'=>0,'tasks_done'=>0,'rating'=>0]; @endphp
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="px-4 py-3 text-white">{{ $emp->name }}</td>
                                <td class="px-4 py-3 text-white/50">{{ $emp->email }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gold/10 text-[#C9A55A] border-[#C9A55A]/30">{{ $emp->role }}</span></td>
                                <td class="px-4 py-3 text-white/50">{{ $d['cases'] }}</td>
                                <td class="px-4 py-3 text-white/50">{{ $d['tasks_done'] }}/{{ $d['tasks'] }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $d['rating'] >= 4 ? 'bg-green-500/15 text-green-400 border-green-500/30' : ($d['rating'] >= 3 ? 'bg-yellow-500/15 text-yellow-400 border-yellow-500/30' : 'bg-white/10 text-white/40 border-white/20') }}">{{ $d['rating'] }}</span></td>
                                <td class="px-4 py-3">@if($emp->is_active)<span class="text-xs text-green-400">نشط</span>@else<span class="text-xs text-red-400">غير نشط</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    @elseif($tab === 'performance')
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 overflow-hidden">
            @if($isAdmin)
            <div class="p-4 border-b border-white/10 flex items-center justify-between">
                <h2 class="text-sm font-bold text-[#C9A55A]">التقييمات</h2>
                <button @click="$dispatch('open-modal', 'perfModal')" class="bg-gold hover:bg-gold-dark text-navy px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ تقييم</button>
            </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-white/10"><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">الموظف</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">التاريخ</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">التقييم</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">المقيم</th>@if($isAdmin)<th class="text-center px-4 py-3 font-bold text-[#C9A55A]">إجراءات</th>@endif</tr></thead>
                    <tbody>
                        @forelse($performances as $p)
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="px-4 py-3 text-white">{{ $p->employee->name }}</td>
                                <td class="px-4 py-3 text-white/50">{{ $p->review_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $p->rating >= 4 ? 'bg-green-500/15 text-green-400 border-green-500/30' : ($p->rating >= 3 ? 'bg-yellow-500/15 text-yellow-400 border-yellow-500/30' : 'bg-red-500/15 text-red-400 border-red-500/30') }}">{{ $p->rating }}/5</span></td>
                                <td class="px-4 py-3 text-white/50">{{ $p->reviewer->name }}</td>
                                @if($isAdmin)
                                <td class="px-4 py-3 text-center">
                                    <form method="POST" action="{{ route('hr.performance.destroy', $p) }}" onsubmit="return confirm('حذف التقييم؟')" class="inline">@csrf @method('DELETE')<button class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors flex items-center justify-center mx-auto"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                                </td>
                                @endif
                            </tr>
                            @if($p->notes)<tr class="border-b border-white/5"><td colspan="{{ $isAdmin ? 5 : 4 }}" class="px-4 pb-3 text-xs text-white/30">{{ $p->notes }}</td></tr>@endif
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 5 : 4 }}" class="px-4 py-12 text-center text-white/30">لا توجد تقييمات</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-white/10">{{ $performances->appends(['tab' => 'performance'])->links() }}</div>
        </div>

    @elseif($tab === 'bonuses')
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 overflow-hidden">
            @if($isAdmin)
            <div class="p-4 border-b border-white/10 flex items-center justify-between">
                <h2 class="text-sm font-bold text-[#C9A55A]">المكافآت</h2>
                <button @click="$dispatch('open-modal', 'bonusModal')" class="bg-gold hover:bg-gold-dark text-navy px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ مكافأة</button>
            </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-white/10"><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">الموظف</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">المبلغ</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">السبب</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">التاريخ</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">بواسطة</th>@if($isAdmin)<th class="text-center px-4 py-3 font-bold text-[#C9A55A]">إجراءات</th>@endif</tr></thead>
                    <tbody>
                        @forelse($bonuses as $b)
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="px-4 py-3 text-white">{{ $b->employee->name }}</td>
                                <td class="px-4 py-3 font-bold text-green-400">{{ number_format($b->amount, 2) }}</td>
                                <td class="px-4 py-3 text-white/50">{{ $b->reason }}</td>
                                <td class="px-4 py-3 text-white/50">{{ $b->date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-white/50">{{ $b->giver->name }}</td>
                                @if($isAdmin)
                                <td class="px-4 py-3 text-center">
                                    <form method="POST" action="{{ route('hr.bonuses.destroy', $b) }}" onsubmit="return confirm('حذف المكافأة؟')" class="inline">@csrf @method('DELETE')<button class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors flex items-center justify-center mx-auto"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 py-12 text-center text-white/30">لا توجد مكافآت</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-white/10">{{ $bonuses->appends(['tab' => 'bonuses'])->links() }}</div>
        </div>

    @elseif($tab === 'penalties')
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 overflow-hidden">
            @if($isAdmin)
            <div class="p-4 border-b border-white/10 flex items-center justify-between">
                <h2 class="text-sm font-bold text-[#C9A55A]">الجزاءات</h2>
                <button @click="$dispatch('open-modal', 'penaltyModal')" class="bg-gold hover:bg-gold-dark text-navy px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ جزاء</button>
            </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-white/10"><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">الموظف</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">المبلغ</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">السبب</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">التاريخ</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">بواسطة</th>@if($isAdmin)<th class="text-center px-4 py-3 font-bold text-[#C9A55A]">إجراءات</th>@endif</tr></thead>
                    <tbody>
                        @forelse($penalties as $p)
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="px-4 py-3 text-white">{{ $p->employee->name }}</td>
                                <td class="px-4 py-3 font-bold {{ $p->amount ? 'text-red-400' : 'text-white/30' }}">{{ $p->amount ? number_format($p->amount, 2) : '-' }}</td>
                                <td class="px-4 py-3 text-white/50">{{ $p->reason }}</td>
                                <td class="px-4 py-3 text-white/50">{{ $p->date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-white/50">{{ $p->giver->name }}</td>
                                @if($isAdmin)
                                <td class="px-4 py-3 text-center">
                                    <form method="POST" action="{{ route('hr.penalties.destroy', $p) }}" onsubmit="return confirm('حذف الجزاء؟')" class="inline">@csrf @method('DELETE')<button class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors flex items-center justify-center mx-auto"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 py-12 text-center text-white/30">لا توجد جزاءات</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-white/10">{{ $penalties->appends(['tab' => 'penalties'])->links() }}</div>
        </div>

    @elseif($tab === 'leaves')
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 overflow-hidden">
            <div class="p-4 border-b border-white/10 flex items-center justify-between">
                <h2 class="text-sm font-bold text-[#C9A55A]">الإجازات</h2>
                <button @click="$dispatch('open-modal', 'leaveModal')" class="bg-gold hover:bg-gold-dark text-navy px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ طلب إجازة</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-white/10"><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">الموظف</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">النوع</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">من</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">إلى</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">الحالة</th>@if($isAdmin)<th class="text-center px-4 py-3 font-bold text-[#C9A55A]">إجراءات</th>@endif</tr></thead>
                    <tbody>
                        @forelse($leaves as $l)
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="px-4 py-3 text-white">{{ $l->employee->name }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-white/10 text-white/50 border-white/20">{{ $l->type }}</span></td>
                                <td class="px-4 py-3 text-white/50">{{ $l->start_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-white/50">{{ $l->end_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $l->status === 'approved' ? 'bg-green-500/15 text-green-400 border-green-500/30' : ($l->status === 'rejected' ? 'bg-red-500/15 text-red-400 border-red-500/30' : 'bg-yellow-500/15 text-yellow-400 border-yellow-500/30') }}">{{ $l->status === 'approved' ? 'معتمدة' : ($l->status === 'rejected' ? 'مرفوضة' : 'قيد الانتظار') }}</span></td>
                                @if($isAdmin)
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        @if($l->status === 'pending')
                                            <form method="POST" action="{{ route('hr.leaves.approve', $l) }}" class="inline">@csrf<button class="w-8 h-8 rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20 transition-colors flex items-center justify-center" title="موافقة"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></button></form>
                                            <form method="POST" action="{{ route('hr.leaves.reject', $l) }}" class="inline">@csrf<button class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors flex items-center justify-center" title="رفض"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                                        @endif
                                        <form method="POST" action="{{ route('hr.leaves.destroy', $l) }}" onsubmit="return confirm('حذف الإجازة؟')" class="inline">@csrf @method('DELETE')<button class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors flex items-center justify-center" title="حذف"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @if($l->reason)<tr class="border-b border-white/5"><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 pb-3 text-xs text-white/30">{{ $l->reason }}</td></tr>@endif
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 py-12 text-center text-white/30">لا توجد إجازات</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-white/10">{{ $leaves->appends(['tab' => 'leaves'])->links() }}</div>
        </div>
    @endif
</div>

{{-- Performance Modal --}}
@if($isAdmin)
<div id="perfModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'perfModal') open = true" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-navy border border-[#C9A55A]/30 rounded-2xl shadow-2xl w-full max-w-lg" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="text-lg font-bold text-[#C9A55A]">إضافة تقييم</h3>
            <button @click="open = false" class="p-1 rounded-lg hover:bg-white/10 text-white/40 hover:text-white"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('hr.performance.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-white/30 mb-1.5">الموظف <span class="text-red-400">*</span></label>
                    <select name="employee_id" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required><option value="">اختر</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">التاريخ <span class="text-red-400">*</span></label>
                    <input type="date" name="review_date" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">التقييم <span class="text-red-400">*</span></label>
                    <select name="rating" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required><option value="">اختر</option>@for($i=1;$i<=5;$i++)<option value="{{ $i }}">{{ $i }} نجوم</option>@endfor</select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">ملاحظات</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>

{{-- Bonus Modal --}}
<div id="bonusModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'bonusModal') open = true" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-navy border border-[#C9A55A]/30 rounded-2xl shadow-2xl w-full max-w-lg" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="text-lg font-bold text-[#C9A55A]">إضافة مكافأة</h3>
            <button @click="open = false" class="p-1 rounded-lg hover:bg-white/10 text-white/40 hover:text-white"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('hr.bonuses.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-white/30 mb-1.5">الموظف <span class="text-red-400">*</span></label>
                    <select name="employee_id" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required><option value="">اختر</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">المبلغ <span class="text-red-400">*</span></label>
                    <input type="number" step="0.001" name="amount" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">التاريخ <span class="text-red-400">*</span></label>
                    <input type="date" name="date" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">السبب <span class="text-red-400">*</span></label>
                <textarea name="reason" rows="2" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>

{{-- Penalty Modal --}}
<div id="penaltyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'penaltyModal') open = true" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-navy border border-[#C9A55A]/30 rounded-2xl shadow-2xl w-full max-w-lg" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="text-lg font-bold text-[#C9A55A]">إضافة جزاء</h3>
            <button @click="open = false" class="p-1 rounded-lg hover:bg-white/10 text-white/40 hover:text-white"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('hr.penalties.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-white/30 mb-1.5">الموظف <span class="text-red-400">*</span></label>
                    <select name="employee_id" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required><option value="">اختر</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">المبلغ</label>
                    <input type="number" step="0.001" name="amount" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">التاريخ <span class="text-red-400">*</span></label>
                    <input type="date" name="date" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">السبب <span class="text-red-400">*</span></label>
                <textarea name="reason" rows="2" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Leave Modal --}}
<div id="leaveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'leaveModal') open = true" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-navy border border-[#C9A55A]/30 rounded-2xl shadow-2xl w-full max-w-lg" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="text-lg font-bold text-[#C9A55A]">طلب إجازة</h3>
            <button @click="open = false" class="p-1 rounded-lg hover:bg-white/10 text-white/40 hover:text-white"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('hr.leaves.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            @if($isAdmin && count($employees) > 0)
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">الموظف <span class="text-red-400">*</span></label>
                <select name="employee_id" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required><option value="">اختر</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
            </div>
            @else
            <input type="hidden" name="employee_id" value="{{ auth()->id() }}">
            @endif
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">النوع <span class="text-red-400">*</span></label>
                <select name="type" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required><option value="">اختر</option><option value="annual">سنوية</option><option value="sick">مرضية</option><option value="emergency">طارئة</option><option value="maternity">أمومة</option><option value="unpaid">بدون راتب</option><option value="other">أخرى</option></select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">من <span class="text-red-400">*</span></label>
                    <input type="date" name="start_date" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">إلى <span class="text-red-400">*</span></label>
                    <input type="date" name="end_date" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">السبب</label>
                <textarea name="reason" rows="2" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">إرسال الطلب</button>
                <button type="button" @click="open = false" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@if($tab === 'employees' && $isAdmin && count($chartData) > 0)
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    const names = {!! json_encode(array_column($chartData, 'name')) !!};
    const cases = {!! json_encode(array_column($chartData, 'cases')) !!};
    const tasks = {!! json_encode(array_column($chartData, 'tasks')) !!};
    const ratings = {!! json_encode(array_column($chartData, 'rating')) !!};

    new Chart(document.getElementById('hrCasesChart'), {
        type: 'bar',
        data: {
            labels: names,
            datasets: [
                { label: 'قضايا', data: cases, backgroundColor: '#C9A55A', borderRadius: 4 },
                { label: 'مهام', data: tasks, backgroundColor: '#60A5FA', borderRadius: 4 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { labels: { color: '#fff', font: { size: 10 } } } }, scales: { y: { beginAtZero: true, ticks: { color: '#fff' } }, x: { ticks: { color: '#fff', font: { size: 9 } } } } }
    });

    new Chart(document.getElementById('hrRatingChart'), {
        type: 'radar',
        data: {
            labels: names,
            datasets: [{ label: 'التقييم', data: ratings, backgroundColor: 'rgba(201,165,90,0.2)', borderColor: '#C9A55A', pointBackgroundColor: '#C9A55A' }]
        },
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { labels: { color: '#fff', font: { size: 10 } } } }, scales: { r: { beginAtZero: true, max: 5, ticks: { color: '#fff', font: { size: 9 }, stepSize: 1 }, grid: { color: 'rgba(255,255,255,0.1)' }, pointLabels: { color: '#fff', font: { size: 9 } } } } }
    });
});
</script>
@endif
@endpush
