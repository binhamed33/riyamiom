<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'ملف القضية' }}</title>
    <style>
        @page { margin: 15mm 12mm; }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'cairo', 'dejavusans', sans-serif; direction: rtl; color: #111827; font-size: 11px; line-height: 1.5; }

        /* Header */
        .pdf-header { border-bottom: 4px solid #D4AF37; padding-bottom: 12px; margin-bottom: 18px; }
        .office-name { font-size: 20px; font-weight: bold; color: #121826; }
        .office-sub { font-size: 10px; color: #666; margin-top: 2px; }
        .print-date { font-size: 10px; color: #888; float: left; }
        .doc-title-row { text-align: center; padding: 10px 0 0 0; }
        .doc-title { font-size: 20px; font-weight: bold; color: #D4AF37; border: 2px solid #D4AF37; display: inline-block; padding: 6px 30px; }

        /* Sections */
        .section { margin-bottom: 16px; }
        .section-header { background: #121826; color: #D4AF37; padding: 7px 14px; font-size: 13px; font-weight: bold; margin-bottom: 8px; border-right: 4px solid #D4AF37; }
        .section-body { padding: 0 4px; }

        /* Info grid */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .info-table td { padding: 5px 10px; border: 1px solid #ddd; font-size: 11px; vertical-align: top; }
        .info-label { background: #f5f3ed; color: #666; font-size: 9px; font-weight: bold; width: 28%; white-space: nowrap; }
        .info-value { color: #111827; font-weight: bold; }

        /* Data tables */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .data-table th { background: #D4AF37; color: #fff; padding: 7px 10px; font-size: 10px; font-weight: bold; text-align: right; border: 1px solid #A88D57; }
        .data-table td { padding: 6px 10px; font-size: 10px; border: 1px solid #ddd; vertical-align: middle; }
        .data-table tr:nth-child(even) td { background: #faf8f4; }
        .data-table tr:nth-child(odd) td { background: #fff; }

        /* Badges */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .badge-active { background: #DCFCE7; color: #166534; }
        .badge-pending { background: #FEF3C7; color: #92400E; }
        .badge-closed { background: #E2E6EC; color: #4B5563; }
        .badge-won { background: #cce5ff; color: #1E40AF; }
        .badge-lost { background: #FEE2E2; color: #991B1B; }
        .badge-overdue { background: #FEE2E2; color: #991B1B; }
        .badge-adjudicated { background: #DCFCE7; color: #166534; }
        .badge-fees-pending { background: #FEE2E2; color: #991B1B; }
        .badge-completed { background: #DCFCE7; color: #166534; }
        .badge-upcoming { background: #cce5ff; color: #1E40AF; }
        .badge-cancelled { background: #E2E6EC; color: #4B5563; }
        .badge-postponed { background: #FEF3C7; color: #92400E; }
        .badge-low { background: #E2E6EC; color: #4B5563; }
        .badge-medium { background: #FEF3C7; color: #92400E; }
        .badge-high { background: #FEF3C7; color: #B45309; }
        .badge-urgent { background: #FEE2E2; color: #991B1B; }
        .badge-access { background: #DCFCE7; color: #166534; }
        .badge-private { background: #FEE2E2; color: #991B1B; }
        .badge-team { background: #cce5ff; color: #1E40AF; }

        /* Footer */
        .pdf-footer { margin-top: 20px; border-top: 3px solid #D4AF37; padding-top: 10px; }
        .pdf-footer-table { width: 100%; border-collapse: collapse; }
        .pdf-footer-table td { font-size: 9px; color: #888; padding: 3px 0; }

        /* Empty state */
        .empty-text { text-align: center; padding: 12px; color: #999; font-style: italic; background: #faf8f4; border: 1px dashed #ddd; }

        /* Note text */
        .note-text { font-size: 10px; color: #555; line-height: 1.6; padding: 8px 12px; background: #faf8f4; border-right: 3px solid #D4AF37; margin-top: 6px; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
