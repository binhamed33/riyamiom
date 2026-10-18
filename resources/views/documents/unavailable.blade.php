{{--
    ملفٌّ لا يُعاين — صفحةٌ صغيرةٌ لا التطبيقُ كلُّه.

    المعاينةُ تُفتح داخل إطار العارض (doc-viewer) أو مصغّرةً في بطاقة.
    كان الملفُّ المفقود يُحوَّل إلى صفحة المستندات، فيُرسم الشريطُ
    الجانبيّ والترويسةُ والإشعاراتُ داخل النافذة الصغيرة وفوقها «الملف
    غير موجود على الخادم» — تطبيقٌ داخل تطبيق.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('app.file_not_found') }}</title>
    <style>
        html, body { height: 100%; margin: 0; }
        body {
            display: flex; align-items: center; justify-content: center;
            font-family: 'Tajawal', 'Cairo', system-ui, sans-serif;
            background: #F9FAFB; color: #374151; text-align: center; padding: 1rem; box-sizing: border-box;
        }
        .box { max-width: 26rem; }
        .mark { width: 2.75rem; height: 2.75rem; margin: 0 auto .75rem; color: #9CA3AF; }
        h1 { font-size: .95rem; font-weight: 700; margin: 0 0 .35rem; }
        p { font-size: .8rem; line-height: 1.7; color: #6B7280; margin: 0; }
        @media (prefers-color-scheme: dark) {
            body { background: #111827; color: #E5E7EB; }
            p { color: #9CA3AF; }
        }
    </style>
</head>
<body>
    <div class="box" data-document-unavailable>
        <svg class="mark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <h1>{{ $document->title }}</h1>
        <p>{{ __('app.document_file_unavailable') }}</p>
    </div>
</body>
</html>
