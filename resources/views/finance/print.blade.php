@php
    $isRtl = app()->getLocale() === 'ar';
@endphp
<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>طباعة - {{ $type === 'transaction' ? 'معاملة' : ($type === 'invoice' ? 'فاتورة' : 'رسم') }}</title>
    <style>
        @page { margin: 1.5cm; size: A4; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', 'Tajawal', 'Cairo', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #111827;
            padding: 20px;
        }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #D4AF37; padding-bottom: 15px; }
        .header h1 { font-size: 20px; color: #111827; margin-bottom: 4px; }
        .header p { font-size: 11px; color: #666; }
        .title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .title-row h2 { font-size: 16px; color: #D4AF37; }
        .title-row .badge {
            display: inline-block; padding: 4px 12px; border-radius: 4px;
            font-size: 11px; font-weight: bold;
        }
        .badge-income { background: #DCFCE7; color: #166534; }
        .badge-expense { background: #FEE2E2; color: #991B1B; }
        .badge-paid { background: #DCFCE7; color: #166534; }
        .badge-unpaid { background: #FEE2E2; color: #991B1B; }
        .badge-partial { background: #FEF3C7; color: #92400E; }
        .badge-cancelled { background: #E2E6EC; color: #4B5563; }
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.details td, table.details th {
            padding: 8px 12px; border: 1px solid #ddd; text-align: {{ $isRtl ? 'right' : 'left' }};
        }
        table.details th {
            background: #f5f5f5; font-weight: bold; font-size: 11px; color: #555; width: 35%;
        }
        table.details td { font-size: 12px; color: #111827; }
        .description { margin-top: 15px; padding: 12px; background: #f9f9f9; border-radius: 4px; }
        .description h4 { font-size: 11px; color: #555; margin-bottom: 4px; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #aaa; border-top: 1px solid #eee; padding-top: 15px; }
        .print-btn { display: block; margin: 20px auto; padding: 10px 30px; background: #D4AF37; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; }
        @media print { .print-btn { display: none; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">طباعة / Print</button>

    <div class="header">
        @php $officeLogoData = \App\Support\OfficeBrand::logoDataUri(); @endphp
        @if($officeLogoData)
            <img src="{{ $officeLogoData }}" alt="" style="max-height:56px;max-width:180px;margin:0 auto 8px;display:block;">
        @endif
        <h1>{{ \App\Models\Setting::get('office_name', 'مُداوَلة') }}</h1>
        <p>{{ \App\Models\Setting::get('office_address', '') }}</p>
    </div>

    <div class="title-row">
        <h2>{{ $type === 'transaction' ? 'سند معاملة مالية' : ($type === 'invoice' ? 'فاتورة' : 'سند رسم قضية') }}</h2>
        <span class="badge {{ $type === 'transaction' ? ($item->type === 'income' ? 'badge-income' : 'badge-expense') : ($item->status === 'paid' ? 'badge-paid' : ($item->status === 'partial' ? 'badge-partial' : ($item->status === 'cancelled' ? 'badge-cancelled' : 'badge-unpaid'))) }}">
            {{ $type === 'transaction' ? ($item->type === 'income' ? 'دخل' : 'مصروف') : ($item->status === 'paid' ? 'مدفوع' : ($item->status === 'partial' ? 'مدفوع جزئياً' : ($item->status === 'cancelled' ? 'ملغي' : 'غير مدفوع'))) }}
        </span>
    </div>

    @if($type === 'transaction')
    <table class="details">
        <tr><th>التصنيف</th><td>{{ $item->category }}</td></tr>
        <tr><th>المبلغ</th><td><strong>{{ number_format($item->amount, 2) }} ر.ع</strong></td></tr>
        <tr><th>التاريخ</th><td>{{ $item->date->format('Y-m-d') }}</td></tr>
        <tr><th>طريقة الدفع</th><td>{{ $item->payment_method ?? '-' }}</td></tr>
        <tr><th>المرجع</th><td>{{ $item->reference ?? '-' }}</td></tr>
        <tr><th>بواسطة</th><td>{{ $item->user->name ?? '-' }}</td></tr>
        <tr><th>تاريخ الإضافة</th><td>{{ $item->created_at->format('Y-m-d H:i') }}</td></tr>
    </table>
    @elseif($type === 'invoice')
    <table class="details">
        <tr><th>رقم الفاتورة</th><td>{{ $item->invoice_number }}</td></tr>
        <tr><th>العميل</th><td>{{ $item->client->name ?? '-' }}</td></tr>
        <tr><th>المبلغ</th><td><strong>{{ number_format($item->amount, 2) }} ر.ع</strong></td></tr>
        <tr><th>المبلغ المدفوع</th><td>{{ number_format($item->paid_amount, 2) }} ر.ع</td></tr>
        <tr><th>المبلغ المتبقي</th><td>{{ number_format($item->remaining_amount, 2) }} ر.ع</td></tr>
        <tr><th>تاريخ الإصدار</th><td>{{ $item->issue_date->format('Y-m-d') }}</td></tr>
        <tr><th>تاريخ الاستحقاق</th><td>{{ $item->due_date?->format('Y-m-d') ?? '-' }}</td></tr>
        <tr><th>بواسطة</th><td>{{ $item->user->name ?? '-' }}</td></tr>
        <tr><th>تاريخ الإضافة</th><td>{{ $item->created_at->format('Y-m-d H:i') }}</td></tr>
    </table>
    @elseif($type === 'fee')
    <table class="details">
        <tr><th>القضية</th><td>{{ $item->case->case_number ?? '-' }}</td></tr>
        <tr><th>نوع الرسم</th><td>{{ $item->fee_type }}</td></tr>
        <tr><th>المبلغ</th><td><strong>{{ number_format($item->amount, 2) }} ر.ع</strong></td></tr>
        <tr><th>التاريخ</th><td>{{ $item->date->format('Y-m-d') }}</td></tr>
        <tr><th>بواسطة</th><td>{{ $item->user->name ?? '-' }}</td></tr>
        <tr><th>تاريخ الإضافة</th><td>{{ $item->created_at->format('Y-m-d H:i') }}</td></tr>
    </table>
    @endif

    @if($item->description)
    <div class="description">
        <h4>الوصف / Description</h4>
        <p>{{ $item->description }}</p>
    </div>
    @endif

    <div class="footer">
        <p>تمت الطباعة في {{ now()->format('Y-m-d H:i') }}</p>
        <p>{{ \App\Models\Setting::get('office_name', 'مُداوَلة') }} &mdash; جميع الحقوق محفوظة</p>
    </div>
</body>
</html>
