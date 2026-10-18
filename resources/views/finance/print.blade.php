@php
    $isRtl = app()->getLocale() === 'ar';
    $officeName = \App\Support\OfficeBrand::name();
    $officeAddress = \App\Models\Setting::get('office_address', '');
    $officePhone = \App\Models\Setting::get('office_phone', '');
    $officeEmail = \App\Models\Setting::get('office_email', '');
    $currency = __('app.currency_omr');
    // موكّلُ السند وقضيّتُه: الفاتورةُ تحملهما، والرسمُ يحمل قضيّتَه وموكّلُها موكّلُه
    $linkedCase = $type === 'transaction' ? null : $item->case;
    $client = $type === 'invoice' ? $item->client : ($type === 'fee' ? $item->case?->client : null);
    $caseLabel = fn ($case) => $case->office_case_number ? '#' . $case->office_case_number : $case->case_number;
@endphp
<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('app.print') }} - {{ $type === 'transaction' ? 'معاملة' : ($type === 'invoice' ? 'فاتورة ' . $item->invoice_number : 'رسم') }}</title>
    {{-- خطّا النظام نفسُهما: كانت الصفحةُ لا تحمّل Tajawal فيسقط المتصفّح إلى DejaVu Sans --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        @page { margin: 1.5cm; size: A4; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Tajawal', 'Cairo', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #111827;
            padding: 20px;
        }
        h1, h2, h4 { font-family: 'Cairo', 'Tajawal', sans-serif; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #D4AF37; padding-bottom: 15px; }
        .header h1 { font-size: 20px; color: #111827; margin-bottom: 4px; }
        .header p { font-size: 11px; color: #666; }
        .header .contact span + span::before { content: ' · '; }
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
        .section-title { font-size: 12px; font-weight: bold; color: #555; margin: 0 0 6px; }
        .description { margin-top: 15px; padding: 12px; background: #f9f9f9; border-radius: 4px; }
        .description h4 { font-size: 11px; color: #555; margin-bottom: 4px; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #aaa; border-top: 1px solid #eee; padding-top: 15px; }
        .print-btn { display: block; margin: 20px auto; padding: 10px 30px; background: #D4AF37; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; font-family: inherit; }
        @media print { .print-btn { display: none; } }
    </style>
</head>
<body>
    <button type="button" class="print-btn" data-window-print>{{ __('app.print') }}</button>

    <div class="header">
        @php $officeLogoData = \App\Support\OfficeBrand::logoDataUri(); @endphp
        @if($officeLogoData)
            <img src="{{ $officeLogoData }}" alt="" style="max-height:56px;max-width:180px;margin:0 auto 8px;display:block;">
        @endif
        <h1>{{ $officeName }}</h1>
        @if($officeAddress)<p>{{ $officeAddress }}</p>@endif
        @if($officePhone || $officeEmail)
            <p class="contact">
                @if($officePhone)<span>{{ __('app.phone') }}: <bdi dir="ltr">{{ $officePhone }}</bdi></span>@endif
                @if($officeEmail)<span>{{ __('app.email') }}: <bdi dir="ltr">{{ $officeEmail }}</bdi></span>@endif
            </p>
        @endif
    </div>

    <div class="title-row">
        <h2>{{ $type === 'transaction' ? 'سند معاملة مالية' : ($type === 'invoice' ? 'فاتورة' : 'سند رسم قضية') }}</h2>
        <span class="badge {{ $type === 'transaction' ? ($item->type === 'income' ? 'badge-income' : 'badge-expense') : ($item->status === 'paid' ? 'badge-paid' : ($item->status === 'partial' ? 'badge-partial' : ($item->status === 'cancelled' ? 'badge-cancelled' : 'badge-unpaid'))) }}">
            {{ $type === 'transaction' ? ($item->type === 'income' ? 'دخل' : 'مصروف') : ($type === 'invoice' ? __('app.invoice_status_' . $item->status) : ($item->status === 'paid' ? __('app.paid') : __('app.unpaid'))) }}
        </span>
    </div>

    @if($client)
    <p class="section-title">{{ __('app.finance_client_details') }}</p>
    <table class="details">
        <tr><th>{{ __('app.client') }}</th><td>{{ $client->name }}</td></tr>
        @if($client->phone)<tr><th>{{ __('app.phone') }}</th><td><bdi dir="ltr">{{ $client->phone }}</bdi></td></tr>@endif
        @if($client->email)<tr><th>{{ __('app.email') }}</th><td><bdi dir="ltr">{{ $client->email }}</bdi></td></tr>@endif
        @if($client->address)<tr><th>{{ __('app.address') }}</th><td>{{ $client->address }}</td></tr>@endif
    </table>
    @endif

    @if($type === 'transaction')
    <table class="details">
        <tr><th>التصنيف</th><td>{{ $item->category }}</td></tr>
        <tr><th>المبلغ</th><td><strong>{{ number_format($item->amount, 2) }} {{ $currency }}</strong></td></tr>
        <tr><th>التاريخ</th><td><bdi dir="ltr">{{ $item->date->format('Y-m-d') }}</bdi></td></tr>
        <tr><th>طريقة الدفع</th><td>{{ $item->payment_method ?? '-' }}</td></tr>
        <tr><th>المرجع</th><td>{{ $item->reference ?? '-' }}</td></tr>
        <tr><th>بواسطة</th><td>{{ $item->user->name ?? '-' }}</td></tr>
        <tr><th>{{ __('app.finance_added_at') }}</th><td><bdi dir="ltr">{{ $item->created_at->format('Y-m-d H:i') }}</bdi></td></tr>
    </table>
    @elseif($type === 'invoice')
    <table class="details">
        <tr><th>رقم الفاتورة</th><td>{{ $item->invoice_number }}</td></tr>
        @if($linkedCase)<tr><th>{{ __('app.finance_case') }}</th><td><bdi dir="ltr">{{ $caseLabel($linkedCase) }}</bdi> — {{ $linkedCase->title }}</td></tr>@endif
        <tr><th>المبلغ</th><td><strong>{{ number_format($item->amount, 2) }} {{ $currency }}</strong></td></tr>
        <tr><th>المبلغ المدفوع</th><td>{{ number_format($item->paid_amount, 2) }} {{ $currency }}</td></tr>
        {{-- الملغاةُ لا متبقّيَ عليها: كانت تُطبع بمبلغها كاملاً «متبقياً» --}}
        <tr><th>المبلغ المتبقي</th><td>{{ $item->status === 'cancelled' ? __('app.invoice_status_cancelled') : number_format($item->remaining_amount, 2) . ' ' . $currency }}</td></tr>
        <tr><th>تاريخ الإصدار</th><td><bdi dir="ltr">{{ $item->issue_date->format('Y-m-d') }}</bdi></td></tr>
        <tr><th>تاريخ الاستحقاق</th><td>@if($item->due_date)<bdi dir="ltr">{{ $item->due_date->format('Y-m-d') }}</bdi>@else - @endif</td></tr>
        <tr><th>بواسطة</th><td>{{ $item->user->name ?? '-' }}</td></tr>
        <tr><th>{{ __('app.finance_added_at') }}</th><td><bdi dir="ltr">{{ $item->created_at->format('Y-m-d H:i') }}</bdi></td></tr>
    </table>
    @elseif($type === 'fee')
    <table class="details">
        <tr><th>{{ __('app.finance_case') }}</th><td>@if($linkedCase)<bdi dir="ltr">{{ $caseLabel($linkedCase) }}</bdi> — {{ $linkedCase->title }}@else - @endif</td></tr>
        <tr><th>نوع الرسم</th><td>{{ $item->fee_type }}</td></tr>
        <tr><th>المبلغ</th><td><strong>{{ number_format($item->amount, 2) }} {{ $currency }}</strong></td></tr>
        <tr><th>التاريخ</th><td><bdi dir="ltr">{{ $item->date->format('Y-m-d') }}</bdi></td></tr>
        <tr><th>بواسطة</th><td>{{ $item->user->name ?? '-' }}</td></tr>
        <tr><th>{{ __('app.finance_added_at') }}</th><td><bdi dir="ltr">{{ $item->created_at->format('Y-m-d H:i') }}</bdi></td></tr>
    </table>
    @endif

    @if($item->description)
    <div class="description">
        <h4>{{ __('app.description') }}</h4>
        <p>{{ $item->description }}</p>
    </div>
    @endif

    <div class="footer">
        <p>{{ __('app.finance_printed_at') }} <bdi dir="ltr">{{ now()->format('Y-m-d H:i') }}</bdi></p>
        <p>{{ $officeName }} &mdash; جميع الحقوق محفوظة</p>
    </div>
{{-- ‏nonce: سياسةُ المحتوى لا تشغّل سكربتاً بلاه، فكان زرُّ الطباعة لا يفعل شيئاً --}}
<script nonce="{{ $cspNonce ?? '' }}">
    document.querySelectorAll('[data-window-print]').forEach(function (b) {
        b.addEventListener('click', function () { window.print(); });
    });
</script>
</body>
</html>
