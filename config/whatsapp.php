<?php

/*
|--------------------------------------------------------------------------
| واتساب الأعمال — إعدادات المزوّد
|--------------------------------------------------------------------------
| القيم هنا افتراضاتٌ عامّة للمنصّة. أمّا بيانات اعتماد كل مكتب — الرمز
| ومعرّف الرقم وسرّ التطبيق — فتُخزَّن مشفَّرةً في جدول settings الخاص
| بقاعدة بيانات ذلك المكتب، بمفتاح تطبيقه هو. راجع App\Support\WhatsAppSettings.
|
| المزوّد الرسمي هو Meta Cloud API. ولم يُكتب مزوّدٌ غير رسمي (يحاكي
| واتساب ويب) عمداً: أرقامُ مكاتب المحاماة معروفةٌ لموكّليها، وحظرُ رقمٍ
| بسبب مخالفة شروط Meta خسارةٌ لا تُستردّ.
*/

return [

    'default' => env('WHATSAPP_PROVIDER', 'meta'),

    // إصدار Graph API. مثبَّتٌ لا مفتوح: Meta تُغيّر السلوك بين
    // الإصدارات، و«الأحدث دائماً» يعني عطلاً يظهر بلا نشرٍ منّا.
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
    'graph_base' => env('WHATSAPP_GRAPH_BASE', 'https://graph.facebook.com'),

    'http_timeout_s' => (int) env('WHATSAPP_HTTP_TIMEOUT_S', 30),
    'connect_timeout_s' => (int) env('WHATSAPP_CONNECT_TIMEOUT_S', 10),

    'retry' => [
        'attempts' => (int) env('WHATSAPP_RETRY_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('WHATSAPP_RETRY_BASE_MS', 1000),
        'max_delay_ms' => (int) env('WHATSAPP_RETRY_MAX_MS', 8000),
    ],

    /*
    | نافذة خدمة العملاء: أربعٌ وعشرون ساعة من آخر رسالةٍ يرسلها العميل.
    | داخلها يجوز الردّ الحرّ، وخارجها لا يمرّ إلا قالبٌ معتمَد من Meta.
    | القيمة ليست إعداداً نُغيّره بل قاعدةٌ عندهم — وهي هنا كي يُقرأ
    | الرقم في مكانٍ واحد لا مبعثراً في الشيفرة.
    */
    'service_window_hours' => 24,

    'providers' => [
        'meta' => [
            'label' => 'Meta WhatsApp Cloud API',
            'implemented' => true,
            'driver' => \App\Services\WhatsApp\MetaCloudProvider::class,
        ],
    ],

    /*
    | أنواع الوسائط التي تقبلها Meta وأحجامها القصوى (بالبايت).
    | لا يُفترض دعمُ نوعٍ غير مذكور هنا: الرفع بنوعٍ غير مدعوم يفشل عند
    | المزوّد بعد أن يكون المستخدم انتظر، والخطأ يصله بلا معنى.
    */
    'media' => [
        'image' => ['mimes' => ['image/jpeg', 'image/png'], 'max' => 5 * 1024 * 1024],
        'audio' => ['mimes' => ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg'], 'max' => 16 * 1024 * 1024],
        'video' => ['mimes' => ['video/mp4', 'video/3gp'], 'max' => 16 * 1024 * 1024],
        'sticker' => ['mimes' => ['image/webp'], 'max' => 500 * 1024],
        'document' => ['mimes' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
        ], 'max' => 100 * 1024 * 1024],
    ],

    // سقفُ ما يُحفظ من وسائط واردة تلقائياً — ما فوقه يبقى عند Meta
    // حتى يطلبه موظّف صراحةً، فلا يمتلئ قرصُ المكتب برسائل دعائية.
    'auto_download_max' => (int) env('WHATSAPP_AUTO_DOWNLOAD_MAX', 20 * 1024 * 1024),

    // كم يوماً يُحتفظ بسجلّ أحداث الويبهوك الخام قبل تقليمه
    'event_retention_days' => (int) env('WHATSAPP_EVENT_RETENTION_DAYS', 14),
];
