<?php

namespace App\Support;

use App\Models\User;

/**
 * المظهر: الوضع (نهاري/ليلي) + السمة اللونية.
 *
 * السمة تُحقن في إعداد Tailwind قبل توليد الأصناف، فتتلوّن كل عناصر
 * الواجهة دفعة واحدة (أزرار، حدود، بطاقات، القائمة، الحقول) بلا طبقة
 * تجاوزات جديدة فوق الموجودة.
 *
 * التفضيل يُحفظ للمستخدم نفسه — لا يؤثر اختيار موظف على زميله.
 */
class Appearance
{
    public const MODES = ['light', 'dark'];
    public const DEFAULT_THEME = 'mudawala';

    /**
     * لكل سمة تدرّج من خمس درجات على نفس بنية الذهب الأصلية.
     * «dark» هي درجة النص على خلفية فاتحة، و«deep» درجة التمرير —
     * وكلتاهما مختارتان لتبقى النصوص مقروءة في الوضعين.
     */
    public const THEMES = [
        'mudawala' => [
            'label_ar' => 'مُداوَلة', 'label_en' => 'Mudawala',
            'swatch' => '#D4AF37',
            'light' => '#F0D98A', 'DEFAULT' => '#D4AF37', 'hover' => '#E5C158',
            'dark' => '#A98218', 'deep' => '#8C6A12',
        ],
        'emerald' => [
            'label_ar' => 'زمرّد', 'label_en' => 'Emerald',
            'swatch' => '#10B981',
            'light' => '#6EE7B7', 'DEFAULT' => '#10B981', 'hover' => '#34D399',
            'dark' => '#047857', 'deep' => '#065F46',
        ],
        'midnight' => [
            'label_ar' => 'أزرق ليلي', 'label_en' => 'Midnight',
            'swatch' => '#3B82F6',
            'light' => '#93C5FD', 'DEFAULT' => '#3B82F6', 'hover' => '#60A5FA',
            'dark' => '#1D4ED8', 'deep' => '#1E3A8A',
        ],
        'burgundy' => [
            'label_ar' => 'عنّابي', 'label_en' => 'Burgundy',
            'swatch' => '#BE123C',
            'light' => '#FDA4AF', 'DEFAULT' => '#BE123C', 'hover' => '#E11D48',
            'dark' => '#9F1239', 'deep' => '#881337',
        ],
        'slate' => [
            'label_ar' => 'رمادي', 'label_en' => 'Slate',
            'swatch' => '#475569',
            'light' => '#CBD5E1', 'DEFAULT' => '#64748B', 'hover' => '#94A3B8',
            'dark' => '#334155', 'deep' => '#1E293B',
        ],
    ];

    public static function themeKey(?User $user = null): string
    {
        $user ??= auth()->user();
        $key = $user?->theme;

        return is_string($key) && isset(self::THEMES[$key]) ? $key : self::DEFAULT_THEME;
    }

    public static function mode(?User $user = null): string
    {
        $user ??= auth()->user();
        $mode = $user?->appearance;

        return in_array($mode, self::MODES, true) ? $mode : 'light';
    }

    /** تدرّج السمة الحالية جاهزاً للحقن في إعداد Tailwind. */
    public static function palette(?User $user = null): array
    {
        return self::THEMES[self::themeKey($user)];
    }

    /**
     * الدرجة المستخدمة للأزرار المملوءة بنص أبيض.
     * نستخدم الدرجة الداكنة لا الأساسية، لأن الأبيض على الدرجة الأساسية
     * لا يبلغ حد التباين المقبول في أي من السمات.
     */
    public static function primary(?User $user = null): array
    {
        $p = self::palette($user);

        return [
            'light' => $p['light'],
            'DEFAULT' => $p['dark'],
            'hover' => $p['DEFAULT'],
            'dark' => $p['deep'],
        ];
    }

    public static function options(): array
    {
        $ar = app()->getLocale() === 'ar';

        return collect(self::THEMES)->map(fn ($t, $k) => [
            'key' => $k,
            'label' => $ar ? $t['label_ar'] : $t['label_en'],
            'swatch' => $t['swatch'],
            'light' => $t['light'],
            'dark' => $t['dark'],
        ])->values()->all();
    }

    public static function isValidTheme(mixed $key): bool
    {
        return is_string($key) && isset(self::THEMES[$key]);
    }

    public static function isValidMode(mixed $mode): bool
    {
        return in_array($mode, self::MODES, true);
    }
}
