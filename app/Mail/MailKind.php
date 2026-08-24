<?php

namespace App\Mail;

use App\Models\Setting;

/**
 * أنواع البريد التي يرسلها النظام.
 *
 * ═══ لماذا نوعٌ مُعدَّد لا صنفٌ لكل رسالة ═══
 *
 * إضافةُ نوعٍ جديد يجب أن تكون: حالةٌ هنا، وقالبُ Blade باسمها، وسطرٌ
 * في شاشة الإعدادات. لا محرّكَ يُمسّ ولا مسارَ إرسالٍ يُكرَّر. وكلُّ
 * نوعٍ يملك مفتاحَ تفعيلٍ مستقلاً، فيقرّر المكتب ما يصل موكّليه.
 *
 * والافتراض: مُفعَّلٌ ما طلبه المستخدم صراحةً الآن (إنشاء القضية
 * ورسائل النظام)، ومُطفأٌ ما هو «عند تفعيلها» (الجلسات والمستندات) —
 * فلا ينهال على الموكّلين بريدٌ لم يطلبه أحد يوم التحديث.
 */
enum MailKind: string
{
    /** إشعار الموكّل بقيد قضيته ودعوته إلى البوابة. */
    case CaseCreated = 'case_created';

    /** إشعار الموكّل بتحديثٍ على قضيته. */
    case CaseUpdated = 'case_updated';

    /** إشعار الموكّل بجلسة قادمة. */
    case SessionNotice = 'session_notice';

    /** إشعار الموكّل بمستندٍ أُتيح له. */
    case DocumentNotice = 'document_notice';

    /** رسالة نظامٍ رسمية — نصُّها يُمرَّر عند الإرسال. */
    case SystemNotice = 'system_notice';

    public function settingKey(): string
    {
        return 'mail_notify_' . $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::CaseCreated => 'إشعار الموكّل عند قيد قضيته',
            self::CaseUpdated => 'إشعار الموكّل عند تحديث قضيته',
            self::SessionNotice => 'إشعار الموكّل بالجلسات',
            self::DocumentNotice => 'إشعار الموكّل بالمستندات',
            self::SystemNotice => 'رسائل النظام الرسمية',
        };
    }

    /** قالب المحتوى — واحدٌ لكل نوع تحت resources/views/emails. */
    public function view(): string
    {
        return 'emails.' . str_replace('_', '-', $this->value);
    }

    /** الأنواع التي تصل الموكّل موقَّعةً باسم المكتب لا باسم النظام. */
    public function isFromOffice(): bool
    {
        return $this !== self::SystemNotice;
    }

    /** مُفعَّلٌ ما لم يُطفئه المكتب — والجلساتُ والمستنداتُ عكسه. */
    public function enabledByDefault(): bool
    {
        return match ($this) {
            self::SessionNotice, self::DocumentNotice => false,
            default => true,
        };
    }

    public function isEnabled(): bool
    {
        $stored = Setting::get($this->settingKey());

        if ($stored === null || $stored === '') {
            return $this->enabledByDefault();
        }

        return (string) $stored === '1';
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
