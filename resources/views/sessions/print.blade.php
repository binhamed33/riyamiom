<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <title>جدول الجلسات — {{ \App\Support\OfficeBrand::name() }}</title>
    {{--
        ورقة تُحمل إلى المحكمة، لا صفحة تُتصفَّح: بلا شريط جانبي ولا
        أزرار على الورق، وبحدود تصمد على الطابعة بالأبيض والأسود.
        وترويسة الجدول تتكرر في كل صفحة فلا يضيع معنى الأعمدة.
    --}}
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Tajawal', 'Segoe UI', Tahoma, sans-serif;
            color: #111827; background: #fff; margin: 0; font-size: 12px; line-height: 1.7;
        }
        .head { display: flex; align-items: center; gap: 14px; border-bottom: 2px solid #C9A227; padding-bottom: 10px; margin-bottom: 6px; }
        .logo { width: 54px; height: 54px; object-fit: contain; flex: none; }
        .office { font-size: 17px; font-weight: 800; margin: 0; }
        .doc { font-size: 12px; color: #4B5563; margin: 2px 0 0; }
        .meta { display: flex; justify-content: space-between; gap: 12px; font-size: 11px; color: #6B7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #D1D5DB; padding: 6px 8px; text-align: right; vertical-align: top; }
        th { background: #F3F4F6; font-size: 11px; font-weight: 800; }
        td.num { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .badge { border: 1px solid #9CA3AF; border-radius: 4px; padding: 1px 6px; font-size: 10px; white-space: nowrap; }
        .foot { margin-top: 14px; display: flex; justify-content: space-between; font-size: 10px; color: #6B7280; border-top: 1px solid #E5E7EB; padding-top: 6px; }
        .empty { text-align: center; color: #6B7280; padding: 40px 0; }
        .actions { margin-bottom: 14px; display: flex; gap: 8px; }
        .btn { font: inherit; font-size: 12px; font-weight: 700; padding: 8px 16px; border-radius: 8px; border: 1px solid #C9A227; background: #C9A227; color: #fff; cursor: pointer; text-decoration: none; }
        .btn.ghost { background: #fff; color: #7A6318; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" class="btn" data-print>طباعة</button>
        <a href="{{ route('sessions.index') }}" class="btn ghost">رجوع</a>
    </div>

    <div class="head">
        {{-- data URI لا رابط: الشعار من قرصٍ خاص لا يقرؤه محرّك الطباعة برابط --}}
        @php $officeLogoData = \App\Support\OfficeBrand::logoDataUri(); @endphp
        @if ($officeLogoData)
            <img src="{{ $officeLogoData }}" alt="" class="logo">
        @endif
        <div>
            <p class="office">{{ \App\Support\OfficeBrand::name() }}</p>
            <p class="doc">جدول الجلسات</p>
        </div>
    </div>

    <div class="meta">
        <span>{{ $filtersSummary ?: 'كل الجلسات' }}</span>
        <span>عدد الجلسات: {{ $sessions->count() }}@if ($truncated ?? false) (بلغ الجدول حدّه — ضيّق الفلتر لطباعة الباقي)@endif • طُبع في {{ $generatedAt->format('Y/m/d — H:i') }}</span>
    </div>

    @if ($sessions->isEmpty())
        <p class="empty">لا جلسات ضمن هذه التصفية.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width:22mm">التاريخ</th>
                    <th style="width:14mm">الوقت</th>
                    <th>القضية</th>
                    <th>الموكّل</th>
                    <th>المحكمة / المكان</th>
                    <th>المحامي</th>
                    <th style="width:18mm">الحالة</th>
                    <th style="width:34mm">ملاحظات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sessions as $s)
                    <tr>
                        <td class="num">{{ $s->date?->format('Y/m/d') ?? '—' }}</td>
                        <td class="num">{{ $s->date?->format('H:i') ?? '—' }}</td>
                        <td>
                            <b>{{ $s->case?->title ?? '—' }}</b>
                            @if ($s->case?->office_case_number)
                                <br><span class="num" style="color:#6B7280">{{ $s->case->office_case_number }}</span>
                            @endif
                        </td>
                        <td>{{ $s->case?->client?->name ?? '—' }}</td>
                        <td>
                            {{-- المحكمة أولاً: هي ما يحدّد أين يذهب المحامي، والقاعة تفصيلها --}}
                            {{ $s->case?->court ?: '—' }}
                            @if ($s->location && $s->location !== $s->case?->court)
                                <br><span style="color:#6B7280">{{ $s->location }}</span>
                            @endif
                        </td>
                        <td>{{ $s->case?->lawyer?->name ?? '—' }}</td>
                        <td><span class="badge">{{ __('app.status_' . $s->status) }}</span></td>
                        <td>{{ \Illuminate\Support\Str::limit($s->notes, 90) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="foot">
        <span>{{ \App\Support\OfficeBrand::name() }}</span>
        <span>مُداوَلة</span>
    </div>

    <script nonce="{{ $cspNonce ?? '' }}">
        document.querySelector('[data-print]')?.addEventListener('click', function () { window.print(); });
    </script>
</body>
</html>
