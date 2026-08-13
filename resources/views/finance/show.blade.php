@extends('layouts.app')

@section('title', __('app.finance') . ' - تفاصيل')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    @if(isset($transaction))
        @php $item = $transaction; $type = 'transaction'; $title = 'المعاملة'; @endphp
    @elseif(isset($invoice))
        @php $item = $invoice; $type = 'invoice'; $title = 'الفاتورة'; @endphp
    @elseif(isset($fee))
        @php $item = $fee; $type = 'fee'; $title = 'الرسم'; @endphp
    @endif

    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gold-dark">{{ $title }}</h2>
        <div class="flex items-center gap-2">
            <a href="{{ route('finance.' . $type . 's.print', $item) }}" target="_blank"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2.5 rounded-lg font-medium transition-colors text-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                طباعة
            </a>
            <a href="{{ route('finance.index', ['tab' => $type . 's']) }}" class="text-gray-500 hover:text-gray-700 transition-colors text-sm flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                عودة
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gold/15 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">تفاصيل {{ $title }}</h3>
            @if($type === 'transaction')
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border {{ $item->type === 'income' ? 'bg-green-100 text-green-700 border-green-200' : 'bg-red-100 text-red-700 border-red-200' }}">{{ $item->type === 'income' ? 'دخل' : 'مصروف' }}</span>
            @elseif($type === 'invoice')
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border {{ $item->status === 'paid' ? 'bg-green-100 text-green-700 border-green-200' : ($item->status === 'partial' ? 'bg-yellow-100 text-yellow-700 border-yellow-200' : ($item->status === 'cancelled' ? 'bg-gray-100 text-gray-400 border-gray-200' : 'bg-red-100 text-red-700 border-red-200')) }}">{{ $item->status === 'paid' ? 'مدفوعة' : ($item->status === 'partial' ? 'جزئي' : ($item->status === 'cancelled' ? 'ملغية' : 'غير مدفوعة')) }}</span>
            @else
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border {{ $item->status === 'paid' ? 'bg-green-100 text-green-700 border-green-200' : 'bg-red-100 text-red-700 border-red-200' }}">{{ $item->status === 'paid' ? 'مدفوع' : 'غير مدفوع' }}</span>
            @endif
        </div>

        @if($type === 'transaction')
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-gray-500 mb-1">التصنيف</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->category }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">المبلغ</dt>
                    <dd class="text-gray-800 font-medium {{ $item->type === 'income' ? 'text-green-700' : 'text-red-700' }}">{{ number_format($item->amount, 2) }} ر.ع</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">التاريخ</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->date->format('Y-m-d') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">طريقة الدفع</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->payment_method ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">المرجع</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->reference ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">بواسطة</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->user->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">تاريخ الإضافة</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->created_at->format('Y-m-d H:i') }}</dd>
                </div>
            </dl>

        @elseif($type === 'invoice')
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-gray-500 mb-1">رقم الفاتورة</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->invoice_number }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">العميل</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->client->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">المبلغ</dt>
                    <dd class="text-gray-800 font-medium">{{ number_format($item->amount, 2) }} ر.ع</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">المبلغ المدفوع</dt>
                    <dd class="text-gray-800 font-medium text-green-700">{{ number_format($item->paid_amount, 2) }} ر.ع</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">المبلغ المتبقي</dt>
                    <dd class="text-gray-800 font-medium {{ $item->remaining_amount > 0 ? 'text-red-700' : 'text-green-700' }}">{{ number_format($item->remaining_amount, 2) }} ر.ع</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">تاريخ الإصدار</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->issue_date->format('Y-m-d') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">تاريخ الاستحقاق</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->due_date?->format('Y-m-d') ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">بواسطة</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->user->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">تاريخ الإضافة</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->created_at->format('Y-m-d H:i') }}</dd>
                </div>
            </dl>

        @elseif($type === 'fee')
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-gray-500 mb-1">القضية</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->case->case_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">نوع الرسم</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->fee_type }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">المبلغ</dt>
                    <dd class="text-gray-800 font-medium">{{ number_format($item->amount, 2) }} ر.ع</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">التاريخ</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->date->format('Y-m-d') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">بواسطة</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->user->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 mb-1">تاريخ الإضافة</dt>
                    <dd class="text-gray-800 font-medium">{{ $item->created_at->format('Y-m-d H:i') }}</dd>
                </div>
            </dl>
        @endif

        @if($item->description)
            <div class="mt-6">
                <h4 class="text-sm text-gray-500 mb-2">الوصف</h4>
                <div class="bg-gray-100 rounded-lg p-4 text-gray-700 leading-relaxed">{{ $item->description }}</div>
            </div>
        @endif

        @if(method_exists($item, 'getAttachmentUrlAttribute') && $item->attachment_url)
            <div class="mt-6">
                <h4 class="text-sm text-gray-500 mb-2">المرفق</h4>
                <a href="{{ $item->attachment_url }}" target="_blank"
                   class="inline-flex items-center gap-2 bg-gold/12 text-gold-dark hover:bg-gold/15 px-4 py-2 rounded-lg transition-colors text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    {{ $item->attachment_name ?? 'تحميل المرفق' }}
                </a>
            </div>
        @endif
    </div>
</div>
@endsection