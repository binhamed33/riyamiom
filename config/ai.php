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

    'providers' => [

        'gemini' => [
            'label' => 'Google Gemini',
            'implemented' => true,
            'driver' => \App\Services\Ai\GeminiProvider::class,
            'key_url' => 'https://aistudio.google.com/apikey',
            'key_prefix_hint' => 'AIza…',
            'default_model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
            'models' => [
                'gemini-flash-latest',
                'gemini-3.6-flash',
                'gemini-2.5-flash',
                'gemini-2.5-pro',
            ],
            'fallback_models' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('GEMINI_FALLBACK_MODELS', 'gemini-3.6-flash,gemini-2.5-flash'))
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
