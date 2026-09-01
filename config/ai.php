<?php

/*
|--------------------------------------------------------------------------
| إعدادات الذكاء الاصطناعي
|--------------------------------------------------------------------------
| كل مكتب نسخة مستقلة بقاعدة بياناتها ومفتاح تشفيرها، ومفتاح المزوّد يُخزَّن
| مشفَّراً في جدول settings الخاص بالمكتب. القيم هنا افتراضات فقط، وما في
| ملف .env يبقى احتياطاً للمكاتب التي لم تُعِدّ مفتاحها بعد.
|
| «implemented» تعني أن المزوّد مكتوب فعلاً ومُختبَر. لا تضف مزوّداً إلى
| هذه القائمة بقيمة true قبل كتابة صنفه — الواجهة تعرض المطبَّق فقط.
*/

return [

    'default' => env('AI_PROVIDER', 'gemini'),

    /*
    | إعادة المحاولة
    |
    | الفاصل يتضاعف مع كل محاولة وفيه رجفةٌ عشوائيّة، والميزانيّة سقفٌ
    | لزمن الطلب كلّه: بلا سقفٍ تلتهم المحاولاتُ أكثر ممّا يصبر عليه
    | الخادم فيسقط الاتّصال بلا رسالة. و`base_delay_ms = 0` يُلغي
    | الانتظار — تستعمله الاختبارات وحدها.
    */
    'retry' => [
        // الطلب التفاعليّ لا يصبر صبر المهمّة الخلفيّة: محامٍ أمام
        // نافذة محادثةٍ ينتظر — فإن لم يُفلح النظام في ~٢٠ ثانية قال
        // «سؤالك محفوظ» وأكمل في الخلفيّة بميزانيّتها الطويلة. القيمة
        // تُفعَّل من المتحكّمات التفاعليّة وحدها.
        'interactive_budget_ms' => (int) env('AI_INTERACTIVE_BUDGET_MS', 20000),
        'attempts_per_model' => (int) env('AI_RETRY_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('AI_RETRY_BASE_MS', 1500),
        'max_delay_ms' => (int) env('AI_RETRY_MAX_MS', 8000),
        'budget_ms' => (int) env('AI_RETRY_BUDGET_MS', 100000),
    ],

    // مهلة قراءة الطلب الواحد إلى المزوّد بالثواني — تُخفَّض تفاعلياً
    'http_timeout_s' => (int) env('AI_HTTP_TIMEOUT_S', 90),

    'providers' => [

        'gemini' => [
            'label' => 'Google Gemini',
            'implemented' => true,
            'driver' => \App\Services\Ai\GeminiProvider::class,
            'key_url' => 'https://aistudio.google.com/apikey',
            'key_prefix_hint' => 'AIza… أو AQ.…',
            'default_model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
            // gemini-2.5-flash أُزيل من هذه القائمة عمداً: Google ردّت
            // حرفياً «no longer available to new users» — يظهر في قائمة
            // النماذج ولا يقبل طلباً من مفتاح جديد. وعرضُه في الواجهة
            // يعني أن يختاره مدير مكتب فيقع في نفس العطل.
            //
            // والاعتماد ليس على هذه القائمة وحدها على كل حال: المزوّد
            // يسمّي بديله في نصّ خطئه، والنظام يتبعه ويثبّته.
            'models' => [
                'gemini-flash-latest',
                'gemini-3.6-flash',
                'gemini-3.5-flash',
                'gemini-2.5-pro',
            ],
            // flash-lite ملاذٌ أخير مقصود: أضعف، لكن حصّته اليوميّة
            // المجانيّة مستقلّة وأكبر — فإذا نفدت حصص flash كلّها بقي
            // جوابٌ من الاحتياطي خيراً من «سؤالك محفوظ» إلى منتصف الليل.
            // (flash-latest و3.6-flash قد يكونان النموذجَ نفسه بحصّةٍ
            // واحدة — الاسم الأوّل مستعار.)
            'fallback_models' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('GEMINI_FALLBACK_MODELS', 'gemini-3.6-flash,gemini-flash-latest,gemini-flash-lite-latest'))
            ))),
        ],

        // مزوّدون مخطَّط لهم ولم تُكتب أصنافهم بعد — لا تُعرض في الواجهة
        'openai' => [
            'label' => 'OpenAI',
            'implemented' => false,
        ],
        'anthropic' => [
            'label' => 'Anthropic',
            'implemented' => false,
        ],
    ],
];
