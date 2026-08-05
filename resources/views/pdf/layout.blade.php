<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'ملف القضية' }}</title>
    <style>
        @page { margin: 15mm 12mm; }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'cairo', 'dejavusans', sans-serif; direction: rtl; color: #1a1a2e; font-size: 11px; line-height: 1.5; }

        /* Header */
        .pdf-header { border-bottom: 4px solid #C9A55A; padding-bottom: 12px; margin-bottom: 18px; }
        .office-name { font-size: 20px; font-weight: bold; color: #111B2E; }
        .office-sub { font-size: 10px; color: #666; margin-top: 2px; }
        .print-date { font-size: 10px; color: #888; float: left; }
        .doc-title-row { text-align: center; padding: 10px 0 0 0; }
        .doc-title { font-size: 20px; font-weight: bold; color: #C9A55A; border: 2px solid #C9A55A; display: inline-block; padding: 6px 30px; }

        /* Sections */
        .section { margin-bottom: 16px; }
        .section-header { background: #111B2E; color: #C9A55A; padding: 7px 14px; font-size: 13px; font-weight: bold; margin-bottom: 8px; border-right: 4px solid #C9A55A; }
        .section-body { padding: 0 4px; }

        /* Info grid */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .info-table td { padding: 5px 10px; border: 1px solid #ddd; font-size: 11px; vertical-align: top; }
        .info-label { background: #f5f3ed; color: #666; font-size: 9px; font-weight: bold; width: 28%; white-space: nowrap; }
        .info-value { color: #1a1a2e; font-weight: bold; }

        /* Data tables */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .data-table th { background: #C9A55A; color: #fff; padding: 7px 10px; font-size: 10px; font-weight: bold; text-align: right; border: 1px solid #B89349; }
        .data-table td { padding: 6px 10px; font-size: 10px; border: 1px solid #ddd; vertical-align: middle; }
        .data-table tr:nth-child(even) td { background: #faf8f4; }
        .data-table tr:nth-child(odd) td { background: #fff; }

        /* Badges */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-closed { background: #e2e3e5; color: #383d41; }
        .badge-won { background: #cce5ff; color: #004085; }
        .badge-lost { background: #f8d7da; color: #721c24; }
        .badge-overdue { background: #f8d7da; color: #721c24; }
        .badge-adjudicated { background: #d4edda; color: #155724; }
        .badge-fees-pending { background: #f8d7da; color: #721c24; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-upcoming { background: #cce5ff; color: #004085; }
        .badge-cancelled { background: #e2e3e5; color: #383d41; }
        .badge-postponed { background: #fff3cd; color: #856404; }
        .badge-low { background: #e2e3e5; color: #383d41; }
        .badge-medium { background: #fff3cd; color: #856404; }
        .badge-high { background: #ffe0b2; color: #e65100; }
        .badge-urgent { background: #f8d7da; color: #721c24; }
        .badge-access { background: #d4edda; color: #155724; }
        .badge-private { background: #f8d7da; color: #721c24; }
        .badge-team { background: #cce5ff; color: #004085; }

        /* Footer */
        .pdf-footer { margin-top: 20px; border-top: 3px solid #C9A55A; padding-top: 10px; }
        .pdf-footer-table { width: 100%; border-collapse: collapse; }
        .pdf-footer-table td { font-size: 9px; color: #888; padding: 3px 0; }

        /* Empty state */
        .empty-text { text-align: center; padding: 12px; color: #999; font-style: italic; background: #faf8f4; border: 1px dashed #ddd; }

        /* Note text */
        .note-text { font-size: 10px; color: #555; line-height: 1.6; padding: 8px 12px; background: #faf8f4; border-right: 3px solid #C9A55A; margin-top: 6px; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
