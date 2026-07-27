@extends('layouts.app')

@section('title', __('app.finance'))

@section('content')
@php $isFinAdmin = in_array(auth()->user()->role, ['developer', 'admin']); @endphp
<div class="">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-[#C9A55A]">{{ __('app.finance') }}</h1>
    </div>

    @if($isFinAdmin)
    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-5">
            <p class="text-xs text-white/40 mb-1">إجمالي الدخل</p>
            <p class="text-2xl font-bold text-green-400">{{ number_format($stats['total_income'], 2) }} ر.ع</p>
        </div>
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-5">
            <p class="text-xs text-white/40 mb-1">إجمالي المصروفات</p>
            <p class="text-2xl font-bold text-red-400">{{ number_format($stats['total_expense'], 2) }} ر.ع</p>
        </div>
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-5">
            <p class="text-xs text-white/40 mb-1">الرصيد</p>
            <p class="text-2xl font-bold text-[#C9A55A]">{{ number_format($stats['balance'], 2) }} ر.ع</p>
        </div>
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 p-5">
            <p class="text-xs text-white/40 mb-1">الفواتير غير المسددة</p>
            <p class="text-2xl font-bold text-yellow-400">{{ number_format($stats['unpaid_invoices_amount'], 2) }} ر.ع</p>
        </div>
    </div>
    @endif

    {{-- Tabs --}}
    <div class="mb-6 border-b border-white/10 flex gap-1 overflow-x-auto">
        <a href="{{ route('finance.index', ['tab' => 'transactions']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'transactions' ? 'text-[#C9A55A] bg-white/5 border-b-2 border-[#C9A55A]' : 'text-white/40 hover:text-white/60' }}">المعاملات</a>
        <a href="{{ route('finance.index', ['tab' => 'invoices']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'invoices' ? 'text-[#C9A55A] bg-white/5 border-b-2 border-[#C9A55A]' : 'text-white/40 hover:text-white/60' }}">الفواتير</a>
        <a href="{{ route('finance.index', ['tab' => 'fees']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'fees' ? 'text-[#C9A55A] bg-white/5 border-b-2 border-[#C9A55A]' : 'text-white/40 hover:text-white/60' }}">رسوم القضايا</a>
    </div>

    {{-- Content --}}
    @if($tab === 'transactions')
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 overflow-hidden">
            <div class="p-4 border-b border-white/10 flex items-center justify-between">
                <h2 class="text-sm font-bold text-[#C9A55A]">المعاملات المالية</h2>
                <button @click="$dispatch('open-modal', 'txModal')" class="bg-gold hover:bg-gold-dark text-navy px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ معاملة</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-white/10"><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">النوع</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">التصنيف</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">المبلغ</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">التاريخ</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">طريقة الدفع</th><th class="text-center px-4 py-3 font-bold text-[#C9A55A]">إجراءات</th></tr></thead>
                    <tbody>
                        @forelse($transactions as $tx)
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $tx->type === 'income' ? 'bg-green-500/15 text-green-400 border-green-500/30' : 'bg-red-500/15 text-red-400 border-red-500/30' }}">{{ $tx->type === 'income' ? 'دخل' : 'مصروف' }}</span></td>
                                <td class="px-4 py-3 text-white">{{ $tx->category }}</td>
                                <td class="px-4 py-3 font-bold {{ $tx->type === 'income' ? 'text-green-400' : 'text-red-400' }}">{{ number_format($tx->amount, 2) }}</td>
                                <td class="px-4 py-3 text-white/50">{{ $tx->date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-white/50">{{ $tx->payment_method ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="{{ route('finance.transactions.show', $tx) }}" class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition-colors flex items-center justify-center"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                                        @if($isFinAdmin)<button @click="$dispatch('open-modal', 'txEditModal'); $dispatch('set-tx', {!! $tx->toJson() !!})" class="w-8 h-8 rounded-lg bg-[#C9A55A]/10 text-[#C9A55A] hover:bg-[#C9A55A]/20 transition-colors flex items-center justify-center"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>@endif
                                        @if($isFinAdmin)<form method="POST" action="{{ route('finance.transactions.destroy', $tx) }}" onsubmit="return confirm('حذف المعاملة؟')" class="inline">@csrf @method('DELETE')<button class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors flex items-center justify-center"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>@endif
                                    </div>
                                </td>
                            </tr>
                            @if($tx->description)<tr class="border-b border-white/5"><td colspan="6" class="px-4 pb-3 text-xs text-white/30">{{ $tx->description }}</td></tr>@endif
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-white/30">لا توجد معاملات</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-white/10">{{ $transactions->appends(['tab' => 'transactions'])->links() }}</div>
        </div>

    @elseif($tab === 'invoices')
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 overflow-hidden">
            <div class="p-4 border-b border-white/10 flex items-center justify-between">
                <h2 class="text-sm font-bold text-[#C9A55A]">الفواتير</h2>
                <button @click="$dispatch('open-modal', 'invModal')" class="bg-gold hover:bg-gold-dark text-navy px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ فاتورة</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-white/10"><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">رقم الفاتورة</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">العميل</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">المبلغ</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">المتبقي</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">الحالة</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">تاريخ الإصدار</th><th class="text-center px-4 py-3 font-bold text-[#C9A55A]">إجراءات</th></tr></thead>
                    <tbody>
                        @forelse($invoices as $inv)
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="px-4 py-3 text-white font-medium">{{ $inv->invoice_number }}</td>
                                <td class="px-4 py-3 text-white/50">{{ $inv->client?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-white">{{ number_format($inv->amount, 2) }}</td>
                                <td class="px-4 py-3 font-bold {{ $inv->remaining_amount > 0 ? 'text-red-400' : 'text-green-400' }}">{{ number_format($inv->remaining_amount, 2) }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $inv->status === 'paid' ? 'bg-green-500/15 text-green-400 border-green-500/30' : ($inv->status === 'partial' ? 'bg-yellow-500/15 text-yellow-400 border-yellow-500/30' : ($inv->status === 'cancelled' ? 'bg-gray-500/15 text-white/40 border-gray-500/30' : 'bg-red-500/15 text-red-400 border-red-500/30')) }}">{{ $inv->status === 'paid' ? 'مدفوعة' : ($inv->status === 'partial' ? 'جزئي' : ($inv->status === 'cancelled' ? 'ملغية' : 'غير مدفوعة')) }}</span></td>
                                <td class="px-4 py-3 text-white/50">{{ $inv->issue_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="{{ route('finance.invoices.show', $inv) }}" class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition-colors flex items-center justify-center"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                                        @if($isFinAdmin)<button @click="$dispatch('open-modal', 'invEditModal'); $dispatch('set-inv', {!! $inv->toJson() !!})" class="w-8 h-8 rounded-lg bg-[#C9A55A]/10 text-[#C9A55A] hover:bg-[#C9A55A]/20 transition-colors flex items-center justify-center"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>@endif
                                        @if($isFinAdmin && $inv->status !== 'paid' && $inv->status !== 'cancelled')<form method="POST" action="{{ route('finance.invoices.pay', $inv) }}" class="inline">@csrf<button class="w-8 h-8 rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20 transition-colors flex items-center justify-center" title="تسديد"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button></form>@endif
                                        @if($isFinAdmin)<form method="POST" action="{{ route('finance.invoices.destroy', $inv) }}" onsubmit="return confirm('حذف الفاتورة؟')" class="inline">@csrf @method('DELETE')<button class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors flex items-center justify-center"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>@endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-12 text-center text-white/30">لا توجد فواتير</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-white/10">{{ $invoices->appends(['tab' => 'invoices'])->links() }}</div>
        </div>

    @elseif($tab === 'fees')
        <div class="bg-navy rounded-xl border border-[#C9A55A]/20 overflow-hidden">
            <div class="p-4 border-b border-white/10 flex items-center justify-between">
                <h2 class="text-sm font-bold text-[#C9A55A]">رسوم القضايا</h2>
                <button @click="$dispatch('open-modal', 'feeModal')" class="bg-gold hover:bg-gold-dark text-navy px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ رسم</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-white/10"><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">القضية</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">نوع الرسم</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">المبلغ</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">الحالة</th><th class="text-right px-4 py-3 font-bold text-[#C9A55A]">التاريخ</th><th class="text-center px-4 py-3 font-bold text-[#C9A55A]">إجراءات</th></tr></thead>
                    <tbody>
                        @forelse($fees as $fee)
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="px-4 py-3 text-white">{{ $fee->case->case_number }}</td>
                                <td class="px-4 py-3 text-white/70">{{ $fee->fee_type }}</td>
                                <td class="px-4 py-3 text-white font-bold">{{ number_format($fee->amount, 2) }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $fee->status === 'paid' ? 'bg-green-500/15 text-green-400 border-green-500/30' : 'bg-red-500/15 text-red-400 border-red-500/30' }}">{{ $fee->status === 'paid' ? 'مدفوع' : 'غير مدفوع' }}</span></td>
                                <td class="px-4 py-3 text-white/50">{{ $fee->date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="{{ route('finance.fees.show', $fee) }}" class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition-colors flex items-center justify-center"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                                        @if($isFinAdmin)<button @click="$dispatch('open-modal', 'feeEditModal'); $dispatch('set-fee', {!! $fee->toJson() !!})" class="w-8 h-8 rounded-lg bg-[#C9A55A]/10 text-[#C9A55A] hover:bg-[#C9A55A]/20 transition-colors flex items-center justify-center"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>@endif
                                        @if($isFinAdmin)<form method="POST" action="{{ route('finance.fees.destroy', $fee) }}" onsubmit="return confirm('حذف الرسم؟')" class="inline">@csrf @method('DELETE')<button class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors flex items-center justify-center"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>@endif
                                    </div>
                                </td>
                            </tr>
                            @if($fee->description)<tr class="border-b border-white/5"><td colspan="6" class="px-4 pb-3 text-xs text-white/30">{{ $fee->description }}</td></tr>@endif
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-white/30">لا توجد رسوم</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-white/10">{{ $fees->appends(['tab' => 'fees'])->links() }}</div>
        </div>
    @endif
</div>

{{-- Add Transaction Modal --}}
<div id="txModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'txModal') open = true" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-navy border border-[#C9A55A]/30 rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col" x-on:click.away="open = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="text-lg font-bold text-[#C9A55A]">إضافة معاملة</h3>
            <button @click="open = false" class="p-1 rounded-lg hover:bg-white/10 text-white/40 hover:text-white transition-colors"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('finance.transactions.store') }}" enctype="multipart/form-data" class="px-6 py-5 overflow-y-auto space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">النوع <span class="text-red-400">*</span></label>
                    <select name="type" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required><option value="income">دخل</option><option value="expense">مصروف</option></select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">التصنيف <span class="text-red-400">*</span></label>
                    <input type="text" name="category" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">المبلغ <span class="text-red-400">*</span></label>
                    <input type="number" step="0.001" name="amount" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">التاريخ <span class="text-red-400">*</span></label>
                    <input type="date" name="date" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">طريقة الدفع</label>
                    <input type="text" name="payment_method" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">المرجع</label>
                    <input type="text" name="reference" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">الوصف</label>
                <textarea name="description" rows="2" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">مرفق (اختياري)</label>
                <input type="file" name="attachment" class="w-full text-white/50 text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gold/10 file:text-gold hover:file:bg-gold/20">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>

@if($isFinAdmin)
{{-- Edit Transaction Modal --}}
<div id="txEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false, tx: {} }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'txEditModal') open = true" x-on:set-tx.window="tx = $event.detail" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-navy border border-[#C9A55A]/30 rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="text-lg font-bold text-[#C9A55A]">تعديل المعاملة</h3>
            <button @click="open = false" class="p-1 rounded-lg hover:bg-white/10 text-white/40 hover:text-white"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="" x-bind:action="'/finance/transactions/' + tx.id" enctype="multipart/form-data" class="px-6 py-5 overflow-y-auto space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">النوع</label>
                    <select name="type" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required x-model="tx.type"><option value="income">دخل</option><option value="expense">مصروف</option></select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">التصنيف</label>
                    <input type="text" name="category" x-model="tx.category" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">المبلغ</label>
                    <input type="number" step="0.001" name="amount" x-model="tx.amount" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">التاريخ</label>
                    <input type="date" name="date" x-model="tx.date" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">طريقة الدفع</label>
                    <input type="text" name="payment_method" x-model="tx.payment_method" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">المرجع</label>
                    <input type="text" name="reference" x-model="tx.reference" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">الوصف</label>
                <textarea name="description" rows="2" x-model="tx.description" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">تغيير المرفق</label>
                <input type="file" name="attachment" class="w-full text-white/50 text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gold/10 file:text-gold">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">حفظ التغييرات</button>
                <button type="button" @click="open = false" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Add Invoice Modal --}}
<div id="invModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'invModal') open = true" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-navy border border-[#C9A55A]/30 rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="text-lg font-bold text-[#C9A55A]">إضافة فاتورة</h3>
            <button @click="open = false" class="p-1 rounded-lg hover:bg-white/10 text-white/40 hover:text-white"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('finance.invoices.store') }}" enctype="multipart/form-data" class="px-6 py-5 overflow-y-auto space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">رقم الفاتورة <span class="text-red-400">*</span></label>
                    <input type="text" name="invoice_number" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">العميل</label>
                    <select name="client_id" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"><option value="">اختر</option>@foreach($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">المبلغ <span class="text-red-400">*</span></label>
                    <input type="number" step="0.001" name="amount" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">الحالة <span class="text-red-400">*</span></label>
                    <select name="status" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required><option value="unpaid">غير مدفوعة</option><option value="paid">مدفوعة</option><option value="partial">مدفوعة جزئياً</option><option value="cancelled">ملغية</option></select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">تاريخ الإصدار <span class="text-red-400">*</span></label>
                    <input type="date" name="issue_date" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">تاريخ الاستحقاق</label>
                    <input type="date" name="due_date" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">الوصف</label>
                <textarea name="description" rows="2" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">مرفق (اختياري)</label>
                <input type="file" name="attachment" class="w-full text-white/50 text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gold/10 file:text-gold">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>

@if($isFinAdmin)
{{-- Edit Invoice Modal --}}
<div id="invEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false, inv: {} }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'invEditModal') open = true" x-on:set-inv.window="inv = $event.detail" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-navy border border-[#C9A55A]/30 rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="text-lg font-bold text-[#C9A55A]">تعديل الفاتورة</h3>
            <button @click="open = false" class="p-1 rounded-lg hover:bg-white/10 text-white/40 hover:text-white"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="" x-bind:action="'/finance/invoices/' + inv.id" enctype="multipart/form-data" class="px-6 py-5 overflow-y-auto space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">رقم الفاتورة</label>
                    <input type="text" name="invoice_number" x-model="inv.invoice_number" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">المبلغ</label>
                    <input type="number" step="0.001" name="amount" x-model="inv.amount" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">الحالة</label>
                    <select name="status" x-model="inv.status" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"><option value="unpaid">غير مدفوعة</option><option value="paid">مدفوعة</option><option value="partial">مدفوعة جزئياً</option><option value="cancelled">ملغية</option></select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">تاريخ الإصدار</label>
                    <input type="date" name="issue_date" x-model="inv.issue_date" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">تاريخ الاستحقاق</label>
                    <input type="date" name="due_date" x-model="inv.due_date" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">الوصف</label>
                <textarea name="description" rows="2" x-model="inv.description" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">تغيير المرفق</label>
                <input type="file" name="attachment" class="w-full text-white/50 text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gold/10 file:text-gold">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Add Fee Modal --}}
<div id="feeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'feeModal') open = true" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-navy border border-[#C9A55A]/30 rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="text-lg font-bold text-[#C9A55A]">إضافة رسم قضية</h3>
            <button @click="open = false" class="p-1 rounded-lg hover:bg-white/10 text-white/40 hover:text-white"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('finance.fees.store') }}" class="px-6 py-5 overflow-y-auto space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-white/30 mb-1.5">القضية <span class="text-red-400">*</span></label>
                    <select name="case_id" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required><option value="">اختر</option>@foreach($cases as $case)<option value="{{ $case->id }}">{{ $case->case_number }} - {{ $case->title }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">نوع الرسم <span class="text-red-400">*</span></label>
                    <input type="text" name="fee_type" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">المبلغ <span class="text-red-400">*</span></label>
                    <input type="number" step="0.001" name="amount" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">الحالة <span class="text-red-400">*</span></label>
                    <select name="status" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required><option value="unpaid">غير مدفوع</option><option value="paid">مدفوع</option></select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">التاريخ <span class="text-red-400">*</span></label>
                    <input type="date" name="date" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">الوصف</label>
                <textarea name="description" rows="2" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>

@if($isFinAdmin)
{{-- Edit Fee Modal --}}
<div id="feeEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false, fee: {} }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'feeEditModal') open = true" x-on:set-fee.window="fee = $event.detail" @keydown.escape="open = false">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-navy border border-[#C9A55A]/30 rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="text-lg font-bold text-[#C9A55A]">تعديل الرسم</h3>
            <button @click="open = false" class="p-1 rounded-lg hover:bg-white/10 text-white/40 hover:text-white"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="" x-bind:action="'/finance/fees/' + fee.id" class="px-6 py-5 overflow-y-auto space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-white/30 mb-1.5">القضية</label>
                    <select name="case_id" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"><option value="">اختر</option>@foreach($cases as $case)<option value="{{ $case->id }}" x-bind:selected="fee.case_id == {{ $case->id }}">{{ $case->case_number }} - {{ $case->title }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">نوع الرسم</label>
                    <input type="text" name="fee_type" x-model="fee.fee_type" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">المبلغ</label>
                    <input type="number" step="0.001" name="amount" x-model="fee.amount" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">الحالة</label>
                    <select name="status" x-model="fee.status" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"><option value="unpaid">غير مدفوع</option><option value="paid">مدفوع</option></select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/30 mb-1.5">التاريخ</label>
                    <input type="date" name="date" x-model="fee.date" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/30 mb-1.5">الوصف</label>
                <textarea name="description" rows="2" x-model="fee.description" class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-white/10 hover:bg-white/20 text-white/70 px-6 py-2.5 rounded-lg font-medium text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
