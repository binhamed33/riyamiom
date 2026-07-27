@extends('layouts.app')

@section('title', 'الإدارة المالية')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-heading font-bold text-white">الإدارة المالية</h1>
</div>

{{-- Stats --}}
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="stat-card rounded-xl px-5 py-4">
        <p class="text-xs text-white/40 mb-1">إجمالي الدخل</p>
        <p class="text-2xl font-bold text-green-400">{{ number_format($stats['total_income'], 2) }} ر.ع</p>
    </div>
    <div class="stat-card rounded-xl px-5 py-4">
        <p class="text-xs text-white/40 mb-1">إجمالي المصروفات</p>
        <p class="text-2xl font-bold text-red-400">{{ number_format($stats['total_expense'], 2) }} ر.ع</p>
    </div>
    <div class="stat-card rounded-xl px-5 py-4">
        <p class="text-xs text-white/40 mb-1">الرصيد</p>
        <p class="text-2xl font-bold text-gold">{{ number_format($stats['balance'], 2) }} ر.ع</p>
    </div>
    <div class="stat-card rounded-xl px-5 py-4">
        <p class="text-xs text-white/40 mb-1">الفواتير غير المسددة</p>
        <p class="text-2xl font-bold text-yellow-400">{{ number_format($stats['unpaid_invoices_amount'], 2) }} ر.ع</p>
    </div>
</div>

{{-- Tabs --}}
<div class="mb-6 border-b border-ivory/5 flex gap-1">
    <a href="{{ route('finance.index', ['tab' => 'transactions']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg {{ $tab === 'transactions' ? 'text-gold bg-gold/5 border-b-2 border-gold' : 'text-white/50 hover:text-white/70' }}">المعاملات</a>
    <a href="{{ route('finance.index', ['tab' => 'invoices']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg {{ $tab === 'invoices' ? 'text-gold bg-gold/5 border-b-2 border-gold' : 'text-white/50 hover:text-white/70' }}">الفواتير</a>
    <a href="{{ route('finance.index', ['tab' => 'fees']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg {{ $tab === 'fees' ? 'text-gold bg-gold/5 border-b-2 border-gold' : 'text-white/50 hover:text-white/70' }}">رسوم القضايا</a>
</div>

{{-- Tab Content --}}
<div class="card-premium rounded-xl">
    @if($tab === 'transactions')
        <div class="p-4 border-b border-ivory/5">
            <button onclick="document.getElementById('txForm').classList.toggle('hidden')" class="btn-gold px-4 py-2 rounded-xl text-sm font-bold">+ إضافة معاملة</button>
            <form id="txForm" method="POST" action="{{ route('finance.transactions.store') }}" class="hidden mt-4 p-4 bg-white/5 rounded-xl">
                @csrf
                <div class="grid grid-cols-4 gap-4">
                    <select name="type" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                        <option value="income">دخل</option>
                        <option value="expense">مصروف</option>
                    </select>
                    <input type="text" name="category" placeholder="التصنيف" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <input type="number" step="0.001" name="amount" placeholder="المبلغ" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <input type="date" name="date" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                </div>
                <div class="grid grid-cols-3 gap-4 mt-3">
                    <input type="text" name="payment_method" placeholder="طريقة الدفع" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white">
                    <input type="text" name="reference" placeholder="المرجع" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white">
                    <button type="submit" class="btn-gold rounded-xl text-sm font-bold">حفظ</button>
                </div>
                <textarea name="description" rows="2" placeholder="الوصف..." class="mt-3 w-full form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white"></textarea>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="border-b border-ivory/5"><th class="text-right px-4 py-3 text-xs font-bold text-white/40">النوع</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">التصنيف</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">المبلغ</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">التاريخ</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">طريقة الدفع</th><th></th></tr></thead>
                <tbody>
                    @foreach($transactions as $tx)
                        <tr class="border-b border-ivory/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full {{ $tx->type === 'income' ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' }}">{{ $tx->type === 'income' ? 'دخل' : 'مصروف' }}</span></td>
                            <td class="px-4 py-3 text-sm text-white/70">{{ $tx->category }}</td>
                            <td class="px-4 py-3 text-sm font-bold {{ $tx->type === 'income' ? 'text-green-400' : 'text-red-400' }}">{{ number_format($tx->amount, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $tx->date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $tx->payment_method ?? '-' }}</td>
                            <td class="px-4 py-3"><form method="POST" action="{{ route('finance.transactions.destroy', $tx) }}" onsubmit="return confirm('حذف؟')">@csrf @method('DELETE')<button class="text-red-400 hover:text-red-300 text-xs">حذف</button></form></td>
                        </tr>
                        @if($tx->description)
                            <tr><td colspan="6" class="px-4 pb-3 text-xs text-white/30">{{ $tx->description }}</td></tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $transactions->appends(['tab' => 'transactions'])->links() }}</div>

    @elseif($tab === 'invoices')
        <div class="p-4 border-b border-ivory/5">
            <button onclick="document.getElementById('invForm').classList.toggle('hidden')" class="btn-gold px-4 py-2 rounded-xl text-sm font-bold">+ إضافة فاتورة</button>
            <form id="invForm" method="POST" action="{{ route('finance.invoices.store') }}" class="hidden mt-4 p-4 bg-white/5 rounded-xl">
                @csrf
                <div class="grid grid-cols-4 gap-4">
                    <input type="text" name="invoice_number" placeholder="رقم الفاتورة" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <select name="client_id" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white">
                        <option value="">العميل (اختياري)</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.001" name="amount" placeholder="المبلغ" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <select name="status" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                        <option value="unpaid">غير مدفوعة</option>
                        <option value="paid">مدفوعة</option>
                        <option value="partial">مدفوعة جزئياً</option>
                        <option value="cancelled">ملغية</option>
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-4 mt-3">
                    <input type="date" name="issue_date" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <input type="date" name="due_date" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white">
                    <button type="submit" class="btn-gold rounded-xl text-sm font-bold">حفظ</button>
                </div>
                <textarea name="description" rows="2" placeholder="الوصف..." class="mt-3 w-full form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white"></textarea>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="border-b border-ivory/5"><th class="text-right px-4 py-3 text-xs font-bold text-white/40">رقم الفاتورة</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">العميل</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">المبلغ</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">المدفوع</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">المتبقي</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">الحالة</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">تاريخ الإصدار</th><th></th></tr></thead>
                <tbody>
                    @foreach($invoices as $inv)
                        <tr class="border-b border-ivory/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3 text-sm text-white/70">{{ $inv->invoice_number }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $inv->client?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-white/70">{{ number_format($inv->amount, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ number_format($inv->paid_amount, 2) }}</td>
                            <td class="px-4 py-3 text-sm font-bold {{ $inv->remaining_amount > 0 ? 'text-red-400' : 'text-green-400' }}">{{ number_format($inv->remaining_amount, 2) }}</td>
                            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full {{ $inv->status === 'paid' ? 'bg-green-500/10 text-green-400' : ($inv->status === 'partial' ? 'bg-yellow-500/10 text-yellow-400' : ($inv->status === 'cancelled' ? 'bg-white/10 text-white/40' : 'bg-red-500/10 text-red-400')) }}">{{ $inv->status === 'paid' ? 'مدفوعة' : ($inv->status === 'partial' ? 'جزئي' : ($inv->status === 'cancelled' ? 'ملغية' : 'غير مدفوعة')) }}</span></td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $inv->issue_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 flex gap-2">
                                @if($inv->status !== 'paid' && $inv->status !== 'cancelled')
                                    <form method="POST" action="{{ route('finance.invoices.pay', $inv) }}" class="inline">@csrf<button class="text-green-400 hover:text-green-300 text-xs">تسديد</button></form>
                                @endif
                                <form method="POST" action="{{ route('finance.invoices.destroy', $inv) }}" onsubmit="return confirm('حذف؟')">@csrf @method('DELETE')<button class="text-red-400 hover:text-red-300 text-xs">حذف</button></form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $invoices->appends(['tab' => 'invoices'])->links() }}</div>

    @elseif($tab === 'fees')
        <div class="p-4 border-b border-ivory/5">
            <button onclick="document.getElementById('feeForm').classList.toggle('hidden')" class="btn-gold px-4 py-2 rounded-xl text-sm font-bold">+ إضافة رسم</button>
            <form id="feeForm" method="POST" action="{{ route('finance.fees.store') }}" class="hidden mt-4 p-4 bg-white/5 rounded-xl">
                @csrf
                <div class="grid grid-cols-4 gap-4">
                    <select name="case_id" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                        <option value="">القضية</option>
                        @foreach($cases as $case)
                            <option value="{{ $case->id }}">{{ $case->case_number }} - {{ $case->title }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="fee_type" placeholder="نوع الرسم" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <input type="number" step="0.001" name="amount" placeholder="المبلغ" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <select name="status" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                        <option value="unpaid">غير مدفوعة</option>
                        <option value="paid">مدفوعة</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4 mt-3">
                    <input type="date" name="date" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <button type="submit" class="btn-gold rounded-xl text-sm font-bold">حفظ</button>
                </div>
                <textarea name="description" rows="2" placeholder="الوصف..." class="mt-3 w-full form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white"></textarea>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="border-b border-ivory/5"><th class="text-right px-4 py-3 text-xs font-bold text-white/40">القضية</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">نوع الرسم</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">المبلغ</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">الحالة</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">التاريخ</th><th></th></tr></thead>
                <tbody>
                    @foreach($fees as $fee)
                        <tr class="border-b border-ivory/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3 text-sm text-white/70">{{ $fee->case->case_number }}</td>
                            <td class="px-4 py-3 text-sm text-white/70">{{ $fee->fee_type }}</td>
                            <td class="px-4 py-3 text-sm font-bold text-white/70">{{ number_format($fee->amount, 2) }}</td>
                            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full {{ $fee->status === 'paid' ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' }}">{{ $fee->status === 'paid' ? 'مدفوع' : 'غير مدفوع' }}</span></td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $fee->date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3"><form method="POST" action="{{ route('finance.fees.destroy', $fee) }}" onsubmit="return confirm('حذف؟')">@csrf @method('DELETE')<button class="text-red-400 hover:text-red-300 text-xs">حذف</button></form></td>
                        </tr>
                        @if($fee->description)
                            <tr><td colspan="6" class="px-4 pb-3 text-xs text-white/30">{{ $fee->description }}</td></tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $fees->appends(['tab' => 'fees'])->links() }}</div>
    @endif
</div>
@endsection
