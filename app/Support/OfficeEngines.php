<?php

namespace App\Support;

use App\Models\Automation;
use App\Models\Setting;
use App\Services\Automation\AutomationEngine;

/**
 * مفتاحا «مركز الأتمتة» و«القوالب الذكية» — في مكانٍ واحد.
 *
 * ═══ لماذا انتقلا إلى الإعدادات ═══
 *
 * كان مفتاحُ المحرّك داخل مركز الأتمتة نفسِه: من يملك صلاحية
 * automations.manage — ولو كان موظّفاً — يُطفئ محرّكَ المكتب كلَّه
 * من الصفحة التي جاء يعمل فيها. والإطفاءُ صامت: القواعدُ تبقى
 * معروضةً «نشطة» ولا تعمل، فيبحث المديرُ عن عطلٍ لا وجود له.
 *
 * فصار البابُ واحداً: زرٌّ في الإعدادات، لمدير المكتب وحدَه. ومن
 * دخل مركزَ الأتمتة يرى حالتَه ولا يملك قلبَها.
 *
 * والقوالبُ الذكية تُشحن مفتوحةً في كلّ مكتب: هي أداةُ ترتيبٍ لا
 * خطرَ فيها، ومكتبٌ يفتحها بعد شهرٍ مكتبٌ عمل شهراً بلا ترتيب.
 * ولذلك الافتراضُ «١» لا «٠» — والغيابُ يعني مفتوحاً.
 */
class OfficeEngines
{
    public const KEY_AUTOMATION = 'automation_enabled';
    public const KEY_TEMPLATES = 'case_templates_enabled';

    /** محرّك الأتمتة — مغلقٌ حتى يفتحه المدير. */
    public static function automationOn(): bool
    {
        return Setting::get(self::KEY_AUTOMATION, '0') === '1';
    }

    /** القوالب الذكية — مفتوحةٌ ما لم تُغلق صراحةً. */
    public static function templatesOn(): bool
    {
        return Setting::get(self::KEY_TEMPLATES, '1') === '1';
    }

    /**
     * فتحُ الأتمتة: القواعدُ الجاهزة تنزل كاملةً، والموجودُ يُنشَّط.
     *
     * الفتحُ بلا قواعدَ يعطي مكتباً «مفعّلاً» لا يفعل شيئاً — فيُقال
     * إنّ الميزة لا تعمل. فالنزولُ جزءٌ من الفتح لا خطوةٌ تُنسى.
     *
     * @return int عددُ ما نزل جديداً
     */
    public static function openAutomation(?int $actorId = null): int
    {
        Setting::set(self::KEY_AUTOMATION, '1', 'automation');

        $created = AutomationEngine::seedDefaults($actorId);

        // ما كان مطفأً بالإغلاق السابق يعود — بلا لمس ما بقي نشطاً
        Automation::where('is_active', false)->update(['is_active' => true]);

        return $created;
    }

    /**
     * إغلاقُ الأتمتة: المحرّكُ يقف والقواعدُ تُطفأ ولا تُحذف.
     *
     * الحذفُ يعني إعادةَ بناءِ عملِ شهورٍ عند أوّل تراجع، والإطفاءُ
     * يعني عودةً بضغطة. فما مِن سببٍ يبرّر الأوّل.
     */
    public static function closeAutomation(): void
    {
        Setting::set(self::KEY_AUTOMATION, '0', 'automation');

        Automation::where('is_active', true)->update(['is_active' => false]);
    }

    public static function setTemplates(bool $on): void
    {
        Setting::set(self::KEY_TEMPLATES, $on ? '1' : '0', 'general');
    }
}
