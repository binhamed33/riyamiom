<?php

namespace App\Support;

use App\Models\User;

/**
 * أقسامُ الموقع نفسُها في نتائج البحث.
 *
 * ═══ العطل ═══
 *
 * البحثُ كان يقرأ الصفوفَ وحدَها: قضيةٌ وموكّلٌ وجلسةٌ ومهمّة. ومن
 * كتب «مداولة» أو «مواعيد» أو «نسخ احتياطي» لم يجد شيئاً، فيظنّ
 * الشيءَ غيرَ موجودٍ في النظام — والصفحةُ أمامه في القائمة.
 *
 * والبحثُ أوّلُ ما يلجأ إليه من لا يعرف أين يذهب. فصار يعرف الأبوابَ
 * كما يعرف ما خلفها.
 *
 * ═══ ولماذا مرادفاتٌ مكتوبة ═══
 *
 * الناسُ لا يكتبون أسماءَ القوائم: يكتبون «فاتورة» لا «الإدارة
 * المالية»، و«اجازة» لا «الموارد البشرية»، و«باركود» لا «واتساب».
 * والمرادفُ سطرٌ هنا، وغيابُه بحثٌ خائب.
 *
 * والصلاحيةُ تُفحص قبل العرض: بابٌ يظهر ثمّ يُغلق في الوجه أسوأُ من
 * بابٍ لا يظهر.
 */
class SearchPages
{
    /**
     * @return array<int,array{label:string,sub:string,url:string,icon:string}>
     */
    public static function match(string $query, ?User $user): array
    {
        if ($query === '' || !$user) {
            return [];
        }

        $needle = self::fold($query);
        $found = [];

        foreach (self::catalogue($user) as $page) {
            $haystack = self::fold($page['label'] . ' ' . implode(' ', $page['words']));

            if (!str_contains($haystack, $needle)) {
                continue;
            }

            $found[] = [
                'label' => $page['label'],
                'sub' => $page['sub'],
                'url' => $page['url'],
                'icon' => '⌘',
            ];

            if (count($found) >= 6) {
                break;
            }
        }

        return $found;
    }

    /**
     * تسويةُ النصّ قبل المطابقة.
     *
     * «مُداوَلة» و«مداولة» كلمةٌ واحدةٌ عند من يكتبها، وحرفان
     * مختلفان عند str_contains: التشكيلُ يُسقَط، والألفُ بأشكالها
     * تُوحَّد، والتاءُ المربوطة تُقرأ هاءً. وبدون هذا يبحث المستخدمُ
     * عن الاسم الذي يراه في الشاشة فلا يجده.
     */
    private static function fold(string $text): string
    {
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text) ?? $text;
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ', 'ى', 'ة', 'ﻻ'], ['ا', 'ا', 'ا', 'ا', 'ي', 'ه', 'لا'], $text);

        return mb_strtolower(trim($text));
    }

    /** @return array<int,array{label:string,sub:string,url:string,words:array<int,string>}> */
    private static function catalogue(User $user): array
    {
        $admin = in_array($user->role, ['admin', 'developer'], true);
        $team = in_array($user->role, ['developer', 'admin', 'lawyer', 'staff'], true);

        $can = fn (string $permission) => $admin || $user->hasPermission($permission);

        $pages = [
            ['dashboard', 'لوحة التحكم', 'نظرةٌ على عمل مكتبك اليوم', ['الرئيسية', 'الصفحة الرئيسية', 'dashboard', 'home'], true],
            ['attention.index', 'مركز الانتباه', 'ما يحتاج تدخّلك الآن', ['تنبيهات', 'المتأخرات', 'attention'], $team],
            ['cases.index', 'القضايا', 'ملفّات المكتب', ['قضية', 'دعوى', 'ملف', 'cases'], $team],
            ['sessions.index', 'الجلسات', 'جلسات المحاكم', ['جلسة', 'محكمة', 'موعد جلسة', 'sessions'], $team],
            ['appointments.index', 'المواعيد', 'حجزُ المواعيد وجدولتها', ['موعد', 'حجز', 'تقويم', 'appointments'], $team],
            ['tasks.index', 'المهام', 'ما على الفريق أن ينجزه', ['مهمة', 'مهام', 'tasks', 'todo'], $team],
            ['clients.index', 'العملاء', 'موكّلو المكتب', ['موكل', 'موكلين', 'عميل', 'clients'], $team],
            ['documents.index', 'المستندات', 'أوراقُ المكتب وملفّاتها', ['مستند', 'ملف', 'وثيقة', 'مرفق', 'documents'], $team],
            ['chat.index', 'المحادثات', 'محادثاتُ الفريق', ['رسائل', 'شات', 'chat'], $team],
            ['finance.index', 'الإدارة المالية', 'الفواتير والمدفوعات', ['فاتورة', 'فواتير', 'دفعة', 'مالية', 'محاسبة', 'finance'], $can('finance.view')],
            ['hr.index', 'الموارد البشرية', 'الحضور والإجازات والرواتب', ['اجازة', 'حضور', 'راتب', 'موظف', 'hr'], $can('hr.view')],
            ['reports.index', 'التقارير', 'أرقامُ المكتب مجموعة', ['تقرير', 'احصائيات', 'reports'], $can('reports.view')],
            ['feasibility.index', 'دراسة الجدوى', 'قياسُ جدوى القضية', ['جدوى', 'feasibility'], $can('feasibility.view')],
            ['audit-log.index', 'سجل التدقيق', 'من فعل ماذا ومتى', ['سجل', 'تدقيق', 'audit'], $can('audit_log.view')],
            ['notifications.index', 'الإشعارات', 'ما وصلك من تنبيهات', ['اشعار', 'تنبيه', 'notifications'], true],
            ['users.index', 'المستخدمون', 'فريقُ المكتب وصلاحياته', ['مستخدم', 'موظف', 'صلاحيات', 'users'], $admin],
            ['settings.index', 'الإعدادات', 'ضبطُ المكتب كلِّه', ['اعدادات', 'ضبط', 'settings'], $can('settings.manage')],
            ['backup.index', 'النسخ الاحتياطي', 'نسخةٌ من قاعدة مكتبك', ['نسخة', 'احتياطي', 'backup', 'استعادة'], $can('backup.manage')],
            ['automations.index', 'مركز الأتمتة', 'قواعدُ تعمل عن مكتبك', ['اتمتة', 'قاعدة', 'تلقائي', 'automation'], $admin && OfficeEngines::automationOn()],
            ['case-templates.index', 'القوالب الذكية', 'قالبُ قضيةٍ ينزل بمهامّه', ['قالب', 'قوالب', 'template'], $admin && OfficeEngines::templatesOn()],
            ['suggestions.index', 'صندوق الاقتراحات', 'اقترح على المطوّر', ['اقتراح', 'ملاحظة', 'شكوى'], $team],
            ['profile.edit', 'الملف الشخصي', 'حسابُك وكلمةُ مرورك', ['حسابي', 'كلمة المرور', 'profile'], true],

            // ═══ اسمُ النظام نفسِه ═══
            // من كتب «مداولة» يريد أن يعرف ما هذا النظام — والدليلُ
            // أوّلُ ما يُقدَّم له، لا نتيجةٌ خالية.
            ['guide', 'دليل الاستخدام', 'شرحُ مُداوَلة قسماً قسماً', ['مداولة', 'مُداوَلة', 'mudawala', 'دليل', 'شرح', 'مساعدة', 'كيف'], true],
            ['about', 'عن مُداوَلة', 'ما هذا النظام ومن يقف خلفه', ['مداولة', 'مُداوَلة', 'mudawala', 'عن البرنامج', 'about', 'النظام'], true],
        ];

        $out = [];

        foreach ($pages as [$route, $label, $sub, $words, $allowed]) {
            // مسارٌ غيرُ مسجَّلٍ في هذا البناء لا يُعرض بدل أن يرمي
            if (!$allowed || !\Illuminate\Support\Facades\Route::has($route)) {
                continue;
            }

            $out[] = ['label' => $label, 'sub' => $sub, 'url' => route($route), 'words' => $words];
        }

        return $out;
    }
}
