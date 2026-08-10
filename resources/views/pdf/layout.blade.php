<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'ملف القضية' }}</title>
    <style>
        @page { margin: 15mm 12mm; }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'cairo', 'dejavusans', sans-serif; direction: rtl; color: #191A18; font-size: 11px; line-height: 1.5; }

        /* Header */
        .pdf-header { border-bottom: 4px solid #B89B5E; padding-bottom: 12px; margin-bottom: 18px; }
        .office-name { font-size: 20px; font-weight: bold; color: #191C1B; }
        .office-sub { font-size: 10px; color: #666; margin-top: 2px; }
        .print-date { font-size: 10px; color: #888; float: left; }
        .doc-title-row { text-align: center; padding: 10px 0 0 0; }
        .doc-title { font-size: 20px; font-weight: bold; color: #B89B5E; border: 2px solid #B89B5E; display: inline-block; padding: 6px 30px; }

        /* Sections */
        .section { margin-bottom: 16px; }
        .section-header { background: #191C1B; color: #B89B5E; padding: 7px 14px; font-size: 13px; font-weight: bold; margin-bottom: 8px; border-right: 4px solid #B89B5E; }
        .section-body { padding: 0 4px; }

        /* Info grid */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .info-table td { padding: 5px 10px; border: 1px solid #ddd; font-size: 11px; vertical-align: top; }
        .info-label { background: #f5f3ed; color: #666; font-size: 9px; font-weight: bold; width: 28%; white-space: nowrap; }
        .info-value { color: #191A18; font-weight: bold; }

        /* Data tables */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .data-table th { background: #B89B5E; color: #fff; padding: 7px 10px; font-size: 10px; font-weight: bold; text-align: right; border: 1px solid #A88D57; }
        .data-table td { padding: 6px 10px; font-size: 10px; border: 1px solid #ddd; vertical-align: middle; }
        .data-table tr:nth-child(even) td { background: #faf8f4; }
        .data-table tr:nth-child(odd) td { background: #fff; }

        /* Badges */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .badge-active { background: #DCEFE3; color: #327F55; }
        .badge-pending { background: #F4E8CC; color: #9F7935; }
        .badge-closed { background: #E4DFD4; color: #4A4A45; }
        .badge-won { background: #cce5ff; color: #456B97; }
        .badge-lost { background: #F5DEDE; color: #A94848; }
        .badge-overdue { background: #F5DEDE; color: #A94848; }
        .badge-adjudicated { background: #DCEFE3; color: #327F55; }
        .badge-fees-pending { background: #F5DEDE; color: #A94848; }
        .badge-completed { background: #DCEFE3; color: #327F55; }
        .badge-upcoming { background: #cce5ff; color: #456B97; }
        .badge-cancelled { background: #E4DFD4; color: #4A4A45; }
        .badge-postponed { background: #F4E8CC; color: #9F7935; }
        .badge-low { background: #E4DFD4; color: #4A4A45; }
        .badge-medium { background: #F4E8CC; color: #9F7935; }
        .badge-high { background: #F4E8CC; color: #C87F40; }
        .badge-urgent { background: #F5DEDE; color: #A94848; }
        .badge-access { background: #DCEFE3; color: #327F55; }
        .badge-private { background: #F5DEDE; color: #A94848; }
        .badge-team { background: #cce5ff; color: #456B97; }

        /* Footer */
        .pdf-footer { margin-top: 20px; border-top: 3px solid #B89B5E; padding-top: 10px; }
        .pdf-footer-table { width: 100%; border-collapse: collapse; }
        .pdf-footer-table td { font-size: 9px; color: #888; padding: 3px 0; }

        /* Empty state */
        .empty-text { text-align: center; padding: 12px; color: #999; font-style: italic; background: #faf8f4; border: 1px dashed #ddd; }

        /* Note text */
        .note-text { font-size: 10px; color: #555; line-height: 1.6; padding: 8px 12px; background: #faf8f4; border-right: 3px solid #B89B5E; margin-top: 6px; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
