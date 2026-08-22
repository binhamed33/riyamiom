<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * لقطة عن صاحب الاقتراح ومكتبه وقت الإرسال.
 *
 * تُحفظ مع الاقتراح لا تُقرأ لحظة العرض، حتى يبقى السياق صحيحاً لو تغيّر
 * دور الموظف أو اسم المكتب لاحقاً.
 *
 * ما لا يُخزَّن عمداً: كلمة المرور، الرمز، عنوان IP الكامل، سلسلة
 * المتصفح الخام — لا شيء منها يفيد في تشخيص اقتراح.
 */
class SuggestionContext
{
    public static function capture(Request $request, User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                // رمز مقروء مشتقّ من المعرّف نفسه — تنسيق لا بيانات
                // جديدة، فلا يوجد في النظام حقل «رقم موظف» مستقل.
                'code' => 'EMP-' . str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'role_label' => self::roleLabel($user->role),
            ],
            'office' => [
                'name' => OfficeBrand::name(),
                'domain' => $request->getHost(),
            ],
            'origin' => [
                // الصفحة التي أُرسل منها الاقتراح — تفيد في تحديد موضع المشكلة
                'page' => self::pagePath($request),
                'locale' => app()->getLocale(),
            ],
            'device' => self::device($request->userAgent()),
            'submitted_at' => now()->toDateTimeString(),
        ];
    }

    private static function pagePath(Request $request): ?string
    {
        $referer = $request->headers->get('referer');
        if (!$referer) {
            return null;
        }

        $path = parse_url($referer, PHP_URL_PATH);

        // المسار فقط بلا معاملات — قد تحمل المعاملات بيانات بحث حسّاسة
        return is_string($path) ? substr($path, 0, 120) : null;
    }

    /** ملخّص مقروء للجهاز بدل سلسلة المتصفح الطويلة. */
    private static function device(?string $agent): array
    {
        $agent = (string) $agent;

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            $agent === '' => null,
            default => 'أخرى',
        };

        $platform = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        $mobile = str_contains($agent, 'Mobile') || str_contains($agent, 'Android') || str_contains($agent, 'iPhone');

        return array_filter([
            'browser' => $browser,
            'platform' => $platform,
            'type' => $agent === '' ? null : ($mobile ? 'هاتف' : 'سطح مكتب'),
        ], static fn ($v) => $v !== null);
    }

    public static function roleLabel(?string $role): string
    {
        return match ($role) {
            'developer' => 'مطوّر',
            'admin' => 'مدير المكتب',
            'lawyer' => 'محامٍ',
            'staff' => 'موظف',
            'client' => 'عميل',
            default => (string) $role,
        };
    }
}
