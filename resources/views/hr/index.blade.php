@extends('layouts.app')

@section('title', __('app.hr'))

@php $isAdmin = in_array(auth()->user()->role, ['developer', 'admin']); @endphp

@section('content')
<div class="">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gold-dark">{{ __('app.hr') }}</h1>
    </div>

    @if($isAdmin)
    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gold/15 p-5">
            <p class="text-xs text-gray-400 mb-1">إجمالي الموظفين</p>
            <p class="text-2xl font-bold text-gold-dark">{{ $stats['total_employees'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-5">
            <p class="text-xs text-gray-400 mb-1">متوسط التقييم</p>
            <p class="text-2xl font-bold text-gold-dark">{{ number_format($stats['avg_rating'] ?? 0, 1) }}/5</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-5">
            <p class="text-xs text-gray-400 mb-1">إجمالي المكافآت</p>
            <p class="text-2xl font-bold text-green-700">{{ number_format($stats['total_bonuses'], 2) }} ر.ع</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-5">
            <p class="text-xs text-gray-400 mb-1">إجمالي الجزاءات</p>
            <p class="text-2xl font-bold text-red-700">{{ number_format($stats['total_penalties'], 2) }} ر.ع</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-5">
            <p class="text-xs text-gray-400 mb-1">إجازات pending</p>
            <p class="text-2xl font-bold text-yellow-700">{{ $stats['pending_leaves'] }}</p>
        </div>
    </div>
    @endif

    {{-- Tabs --}}
    <div class="mb-6 border-b border-gray-200 flex gap-1 overflow-x-auto">
        @if($isAdmin)
        <a href="{{ route('hr.index', ['tab' => 'employees']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'employees' ? 'text-gold-dark bg-gray-100 border-b-2 border-gold' : 'text-gray-400 hover:text-gray-600' }}">الموظفون</a>
        @endif
        <a href="{{ route('hr.index', ['tab' => 'performance']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'performance' ? 'text-gold-dark bg-gray-100 border-b-2 border-gold' : 'text-gray-400 hover:text-gray-600' }}">التقييمات</a>
        <a href="{{ route('hr.index', ['tab' => 'bonuses']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'bonuses' ? 'text-gold-dark bg-gray-100 border-b-2 border-gold' : 'text-gray-400 hover:text-gray-600' }}">المكافآت</a>
        <a href="{{ route('hr.index', ['tab' => 'penalties']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'penalties' ? 'text-gold-dark bg-gray-100 border-b-2 border-gold' : 'text-gray-400 hover:text-gray-600' }}">الجزاءات</a>
        <a href="{{ route('hr.index', ['tab' => 'leaves']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'leaves' ? 'text-gold-dark bg-gray-100 border-b-2 border-gold' : 'text-gray-400 hover:text-gray-600' }}">الإجازات</a>
    </div>

    {{-- Tab Content --}}
    @if($tab === 'employees' && $isAdmin)
        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
            @if(count($chartData) > 0)
            <div class="p-5 border-b border-gray-200">
                <h3 class="text-sm font-bold text-gold-dark mb-4">توزيع التقييمات</h3>
                <div class="flex items-center gap-8">
                    <div class="w-48 h-48"><canvas id="hrRatingChart"></canvas></div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3"><span class="w-3 h-3 rounded-full bg-green-400"></span><span class="text-sm text-gray-700">ممتاز (4-5): <strong class="text-gray-900">{{ $ratingDistribution['excellent'] }}</strong></span></div>
                        <div class="flex items-center gap-3"><span class="w-3 h-3 rounded-full bg-yellow-400"></span><span class="text-sm text-gray-700">جيد (3-4): <strong class="text-gray-900">{{ $ratingDistribution['good'] }}</strong></span></div>
                        <div class="flex items-center gap-3"><span class="w-3 h-3 rounded-full bg-red-400"></span><span class="text-sm text-gray-700">ضعيف (>3): <strong class="text-gray-900">{{ $ratingDistribution['poor'] }}</strong></span></div>
                    </div>
                </div>
            </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200"><th class="text-right px-4 py-3 font-bold text-gold-dark">الاسم</th><th class="text-right px-4 py-3 font-bold text-gold-dark">البريد</th><th class="text-right px-4 py-3 font-bold text-gold-dark">الدور</th><th class="text-right px-4 py-3 font-bold text-gold-dark">قضايا</th><th class="text-right px-4 py-3 font-bold text-gold-dark">مهام</th><th class="text-right px-4 py-3 font-bold text-gold-dark">التقييم</th><th class="text-right px-4 py-3 font-bold text-gold-dark">الحالة</th></tr></thead>
                    <tbody>
                        @foreach($employees as $emp)
                            @php $d = collect($chartData)->firstWhere('name', $emp->name) ?? ['cases'=>0,'tasks'=>0,'tasks_done'=>0,'rating'=>0]; @endphp
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-900">{{ $emp->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $emp->email }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gold/10 text-gold-dark border-gold/25">{{ $emp->role }}</span></td>
                                <td class="px-4 py-3 text-gray-500">{{ $d['cases'] }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $d['tasks_done'] }}/{{ $d['tasks'] }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $d['rating'] >= 4 ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : ($d['rating'] >= 3 ? 'bg-yellow-100 text-yellow-700 border-yellow-200' : 'bg-gray-100 text-gray-400 border-gray-200') }}">{{ $d['rating'] }}</span></td>
                                <td class="px-4 py-3">@if($emp->is_active)<span class="text-xs text-green-700">نشط</span>@else<span class="text-xs text-red-700">غير نشط</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    @elseif($tab === 'performance')
        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
            @if($isAdmin)
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gold-dark">التقييمات</h2>
                <button @click="$dispatch('open-modal', 'perfModal')" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ تقييم</button>
            </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200"><th class="text-right px-4 py-3 font-bold text-gold-dark">الموظف</th><th class="text-right px-4 py-3 font-bold text-gold-dark">التاريخ</th><th class="text-right px-4 py-3 font-bold text-gold-dark">التقييم</th><th class="text-right px-4 py-3 font-bold text-gold-dark">المقيم</th>@if($isAdmin)<th class="text-center px-4 py-3 font-bold text-gold-dark">إجراءات</th>@endif</tr></thead>
                    <tbody>
                        @forelse($performances as $p)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-900">{{ $p->employee->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $p->review_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $p->rating >= 4 ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : ($p->rating >= 3 ? 'bg-yellow-100 text-yellow-700 border-yellow-200' : 'bg-red-100 text-red-700 border-red-200') }}">{{ $p->rating }}/5</span></td>
                                <td class="px-4 py-3 text-gray-500">{{ $p->reviewer->name }}</td>
                                @if($isAdmin)
                                <td class="px-4 py-3 text-center">
                                    <form method="POST" action="{{ route('hr.performance.destroy', $p) }}" data-confirm="حذف التقييم؟" class="inline">@csrf @method('DELETE')<button aria-label="{{ __('app.delete') }}" class="w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors flex items-center justify-center mx-auto"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                                </td>
                                @endif
                            </tr>
                            @if($p->notes)<tr class="border-b border-gray-100"><td colspan="{{ $isAdmin ? 5 : 4 }}" class="px-4 pb-3 text-xs text-gray-400">{{ $p->notes }}</td></tr>@endif
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 5 : 4 }}" class="px-4 py-12 text-center text-gray-400">لا توجد تقييمات</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200">{{ $performances->appends(['tab' => 'performance'])->links() }}</div>
        </div>

    @elseif($tab === 'bonuses')
        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
            @if($isAdmin)
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gold-dark">المكافآت</h2>
                <button @click="$dispatch('open-modal', 'bonusModal')" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ مكافأة</button>
            </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200"><th class="text-right px-4 py-3 font-bold text-gold-dark">الموظف</th><th class="text-right px-4 py-3 font-bold text-gold-dark">المبلغ</th><th class="text-right px-4 py-3 font-bold text-gold-dark">السبب</th><th class="text-right px-4 py-3 font-bold text-gold-dark">التاريخ</th><th class="text-right px-4 py-3 font-bold text-gold-dark">بواسطة</th>@if($isAdmin)<th class="text-center px-4 py-3 font-bold text-gold-dark">إجراءات</th>@endif</tr></thead>
                    <tbody>
                        @forelse($bonuses as $b)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-900">{{ $b->employee->name }}</td>
                                <td class="px-4 py-3 font-bold text-green-700">{{ number_format($b->amount, 2) }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $b->reason }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $b->date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $b->giver->name }}</td>
                                @if($isAdmin)
                                <td class="px-4 py-3 text-center">
                                    <form method="POST" action="{{ route('hr.bonuses.destroy', $b) }}" data-confirm="حذف المكافأة؟" class="inline">@csrf @method('DELETE')<button aria-label="{{ __('app.delete') }}" class="w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors flex items-center justify-center mx-auto"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 py-12 text-center text-gray-400">لا توجد مكافآت</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200">{{ $bonuses->appends(['tab' => 'bonuses'])->links() }}</div>
        </div>

    @elseif($tab === 'penalties')
        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
            @if($isAdmin)
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gold-dark">الجزاءات</h2>
                <button @click="$dispatch('open-modal', 'penaltyModal')" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ جزاء</button>
            </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200"><th class="text-right px-4 py-3 font-bold text-gold-dark">الموظف</th><th class="text-right px-4 py-3 font-bold text-gold-dark">المبلغ</th><th class="text-right px-4 py-3 font-bold text-gold-dark">السبب</th><th class="text-right px-4 py-3 font-bold text-gold-dark">التاريخ</th><th class="text-right px-4 py-3 font-bold text-gold-dark">بواسطة</th>@if($isAdmin)<th class="text-center px-4 py-3 font-bold text-gold-dark">إجراءات</th>@endif</tr></thead>
                    <tbody>
                        @forelse($penalties as $p)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-900">{{ $p->employee->name }}</td>
                                <td class="px-4 py-3 font-bold {{ $p->amount ? 'text-red-700' : 'text-gray-400' }}">{{ $p->amount ? number_format($p->amount, 2) : '-' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $p->reason }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $p->date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $p->giver->name }}</td>
                                @if($isAdmin)
                                <td class="px-4 py-3 text-center">
                                    <form method="POST" action="{{ route('hr.penalties.destroy', $p) }}" data-confirm="حذف الجزاء؟" class="inline">@csrf @method('DELETE')<button aria-label="{{ __('app.delete') }}" class="w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors flex items-center justify-center mx-auto"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 py-12 text-center text-gray-400">لا توجد جزاءات</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200">{{ $penalties->appends(['tab' => 'penalties'])->links() }}</div>
        </div>

    @elseif($tab === 'leaves')
        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gold-dark">الإجازات</h2>
                <button @click="$dispatch('open-modal', 'leaveModal')" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ طلب إجازة</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200"><th class="text-right px-4 py-3 font-bold text-gold-dark">الموظف</th><th class="text-right px-4 py-3 font-bold text-gold-dark">النوع</th><th class="text-right px-4 py-3 font-bold text-gold-dark">من</th><th class="text-right px-4 py-3 font-bold text-gold-dark">إلى</th><th class="text-right px-4 py-3 font-bold text-gold-dark">الحالة</th>@if($isAdmin)<th class="text-center px-4 py-3 font-bold text-gold-dark">إجراءات</th>@endif</tr></thead>
                    <tbody>
                        @forelse($leaves as $l)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-900">{{ $l->employee->name }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gray-100 text-gray-500 border-gray-200">{{ $l->type }}</span></td>
                                <td class="px-4 py-3 text-gray-500">{{ $l->start_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $l->end_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $l->status === 'approved' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : ($l->status === 'rejected' ? 'bg-red-100 text-red-700 border-red-200' : 'bg-yellow-100 text-yellow-700 border-yellow-200') }}">{{ $l->status === 'approved' ? 'معتمدة' : ($l->status === 'rejected' ? 'مرفوضة' : 'قيد الانتظار') }}</span></td>
                                @if($isAdmin)
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        @if($l->status === 'pending')
                                            <form method="POST" action="{{ route('hr.leaves.approve', $l) }}" class="inline">@csrf<button class="w-8 h-8 rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition-colors flex items-center justify-center" title="موافقة"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></button></form>
                                            <form method="POST" action="{{ route('hr.leaves.reject', $l) }}" class="inline">@csrf<button class="w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors flex items-center justify-center" title="رفض"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                                        @endif
                                        <form method="POST" action="{{ route('hr.leaves.destroy', $l) }}" data-confirm="حذف الإجازة؟" class="inline">@csrf @method('DELETE')<button class="w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors flex items-center justify-center" title="حذف"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @if($l->reason)<tr class="border-b border-gray-100"><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 pb-3 text-xs text-gray-400">{{ $l->reason }}</td></tr>@endif
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 py-12 text-center text-gray-400">لا توجد إجازات</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200">{{ $leaves->appends(['tab' => 'leaves'])->links() }}</div>
        </div>
    @endif
</div>

{{-- Performance Modal --}}
@if($isAdmin)
<div id="perfModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'perfModal') open = true" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/45 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-white border border-gold/25 rounded-2xl shadow-2xl w-full max-w-lg" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gold-dark">إضافة تقييم</h3>
            <button @click="open = false" aria-label="{{ __('app.close') }}" class="p-1 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-900"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('hr.performance.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">الموظف <span class="text-red-700">*</span></label>
                    <select name="employee_id" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required><option value="">اختر</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">التاريخ <span class="text-red-700">*</span></label>
                    <input type="date" name="review_date" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">التقييم <span class="text-red-700">*</span></label>
                    <select name="rating" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required><option value="">اختر</option>@for($i=1;$i<=5;$i++)<option value="{{ $i }}">{{ $i }} نجوم</option>@endfor</select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">ملاحظات</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>

{{-- Bonus Modal --}}
<div id="bonusModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'bonusModal') open = true" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/45 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-white border border-gold/25 rounded-2xl shadow-2xl w-full max-w-lg" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gold-dark">إضافة مكافأة</h3>
            <button @click="open = false" aria-label="{{ __('app.close') }}" class="p-1 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-900"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('hr.bonuses.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">الموظف <span class="text-red-700">*</span></label>
                    <select name="employee_id" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required><option value="">اختر</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">المبلغ <span class="text-red-700">*</span></label>
                    <input type="number" step="0.001" name="amount" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">التاريخ <span class="text-red-700">*</span></label>
                    <input type="date" name="date" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">السبب <span class="text-red-700">*</span></label>
                <textarea name="reason" rows="2" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>

{{-- Penalty Modal --}}
<div id="penaltyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'penaltyModal') open = true" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/45 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-white border border-gold/25 rounded-2xl shadow-2xl w-full max-w-lg" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gold-dark">إضافة جزاء</h3>
            <button @click="open = false" aria-label="{{ __('app.close') }}" class="p-1 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-900"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('hr.penalties.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">الموظف <span class="text-red-700">*</span></label>
                    <select name="employee_id" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required><option value="">اختر</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">المبلغ</label>
                    <input type="number" step="0.001" name="amount" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">التاريخ <span class="text-red-700">*</span></label>
                    <input type="date" name="date" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">السبب <span class="text-red-700">*</span></label>
                <textarea name="reason" rows="2" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Leave Modal --}}
<div id="leaveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'leaveModal') open = true" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/45 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-white border border-gold/25 rounded-2xl shadow-2xl w-full max-w-lg" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gold-dark">طلب إجازة</h3>
            <button @click="open = false" aria-label="{{ __('app.close') }}" class="p-1 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-900"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('hr.leaves.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            @if(auth()->user()->isAdmin() || auth()->user()->isDeveloper())
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">الموظف <span class="text-red-700">*</span></label>
                <select name="employee_id" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required><option value="">اختر</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
            </div>
            @else
            <input type="hidden" name="employee_id" value="{{ auth()->id() }}">
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">الموظف</label>
                <input type="text" value="{{ auth()->user()->name }}" disabled class="w-full rounded-lg bg-gray-100 border border-gray-200 px-4 py-2.5 text-gray-900 text-sm">
            </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">النوع <span class="text-red-700">*</span></label>
                <select name="type" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required><option value="">اختر</option><option value="annual">سنوية</option><option value="sick">مرضية</option><option value="emergency">طارئة</option><option value="maternity">أمومة</option><option value="unpaid">بدون راتب</option><option value="other">أخرى</option></select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">من <span class="text-red-700">*</span></label>
                    <input type="date" name="start_date" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">إلى <span class="text-red-700">*</span></label>
                    <input type="date" name="end_date" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">السبب</label>
                <textarea name="reason" rows="2" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">إرسال الطلب</button>
                <button type="button" @click="open = false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@if($tab === 'employees' && $isAdmin && count($chartData) > 0)
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('hrRatingChart'), {
        type: 'doughnut',
        data: {
            labels: ['ممتاز (4-5)', 'جيد (3-4)', 'ضعيف (>3)'],
            datasets: [{
                data: [{{ $ratingDistribution['excellent'] }}, {{ $ratingDistribution['good'] }}, {{ $ratingDistribution['poor'] }}],
                backgroundColor: ['#4ADE80', '#F59E0B', '#F87171'],
                borderColor: '#E2E6EC',
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '65%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) { return ctx.parsed + ' موظف'; }
                    }
                }
            }
        }
    });
});
</script>
@endif
@endpush
