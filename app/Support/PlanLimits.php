<?php

namespace App\Support;

use App\Models\Setting;

/**
 * حدود باقة هذا المكتب — تُفرَض من الخادم لا من إخفاء زرّ.
 *
 * الحدود لا تُكتب هنا: تنزل من لوحة مُداوَلة في ردّ النبضة وتُحفَظ في
 * الإعدادات. فالمكتب لا يعرف أسعاراً ولا باقاتٍ غير ما بلّغته اللوحة،
 * وترقيةُ باقةٍ من اللوحة تسري هنا خلال ساعة بلا لمس المكتب.
 *
 * ═══ الفشل مفتوح، عن قصد ═══
 *
 * مكتبٌ لم تصله حدودٌ بعد (نسخة قديمة، أو الجسر غير مربوط، أو اللوحة
 * بعيدة) يعمل بلا حدّ. لأنّ البديل — أن يُقفل مكتبٌ يعمل لأنّ نبضةً
 * لم تصل — أسوأ من مكتبٍ تجاوز حدّه يوماً. المنعُ يبدأ حين نعرف
 * يقيناً، لا حين نجهل.
 */
class PlanLimits
{
    public const KEY = 'plan_limits';
    public const PLAN_KEY = 'plan_key';
    public const PLAN_NAME = 'plan_name';

    /** الموارد التي لها حدّ، واسم كل واحد كما يُعرض. */
    public const RESOURCES = [
        'users' => 'المستخدمون',
        'clients' => 'الموكّلون',
        'cases' => 'القضايا',
        'documents' => 'المستندات',
        'storage_gb' => 'مساحة التخزين',
    ];

    /** يحفظ ما نزل من اللوحة. */
    public static function sync(?string $planKey, ?string $planName, array $limits): void
    {
        $clean = [];

        foreach (self::RESOURCES as $key => $_) {
            if (isset($limits[$key]) && is_numeric($limits[$key]) && (int) $limits[$key] > 0) {
                $clean[$key] = (int) $limits[$key];
            }
        }

        if ($clean === []) {
            return;
        }

        Setting::set(self::KEY, json_encode($clean, JSON_UNESCAPED_UNICODE), 'subscription');
        Setting::set(self::PLAN_KEY, (string) $planKey, 'subscription');
        Setting::set(self::PLAN_NAME, (string) $planName, 'subscription');
    }

    /** @return array<string, int> فارغة = لم تصل حدود بعد */
    public static function all(): array
    {
        $raw = Setting::get(self::KEY);

        if (!$raw) {
            return [];
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function planName(): ?string
    {
        return Setting::get(self::PLAN_NAME) ?: null;
    }

    /** حدّ مورد بعينه — null يعني «بلا حدّ معروف». */
    public static function of(string $resource): ?int
    {
        $limits = self::all();

        return isset($limits[$resource]) ? (int) $limits[$resource] : null;
    }

    /** الاستهلاك الحالي، يُحسب من القاعدة لا من عدّاد قد يشرد. */
    public static function used(string $resource): int
    {
        return match ($resource) {
            'users' => \App\Models\User::query()->where('role', '!=', 'client')->count(),
            'clients' => \App\Models\Client::query()->count(),
            'cases' => \App\Models\LegalCase::query()->count(),
            'documents' => \App\Models\Document::query()->count(),
            'storage_gb' => (int) ceil(((int) \App\Models\Document::query()->sum('file_size')) / 1073741824),
            default => 0,
        };
    }

    /**
     * هل بلغ المكتب حدّ هذا المورد؟
     * بلا حدّ معروف: لا — الفشل مفتوح.
     */
    public static function reached(string $resource): bool
    {
        $limit = self::of($resource);

        return $limit !== null && self::used($resource) >= $limit;
    }

    /** رسالة يفهمها الموظّف — لا رقم ولا مصطلح. */
    public static function message(string $resource): string
    {
        $limit = self::of($resource);
        $label = self::RESOURCES[$resource] ?? $resource;
        $plan = self::planName();

        return 'لقد وصلت إلى الحد المسموح في باقتك'
            . ($plan ? ' (' . $plan . ')' : '')
            . ': ' . $label . ' ' . self::used($resource) . ' من ' . $limit . '.'
            . ' للمتابعة يمكنك ترقية الباقة أو تقليل الاستخدام.';
    }

    /** كل الموارد مع حدّها واستهلاكها — لشاشة الاشتراك. */
    public static function report(): array
    {
        $limits = self::all();

        if ($limits === []) {
            return [];
        }

        $rows = [];

        foreach (self::RESOURCES as $key => $label) {
            if (!isset($limits[$key])) {
                continue;
            }

            $used = self::used($key);
            $limit = (int) $limits[$key];

            $rows[$key] = [
                'label' => $label,
                'used' => $used,
                'limit' => $limit,
                'percent' => $limit > 0 ? min(100, (int) round($used * 100 / $limit)) : 0,
                'reached' => $used >= $limit,
            ];
        }

        return $rows;
    }
}
