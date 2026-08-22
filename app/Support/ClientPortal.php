<?php

namespace App\Support;

use App\Models\Setting;

/**
 * إعدادات بوابة العملاء — مصدر واحد لما يراه العميل.
 *
 * العزل معماري لا اختياري: كل مكتب في مُداوَلة نسخة مستقلة بقاعدة
 * بياناتها وتخزينها ومفتاح تشفيرها. فهذه الإعدادات تخصّ هذا المكتب
 * وحده، ولا يوجد جدول عملاء مشترك بين المكاتب أصلاً.
 *
 * قاعدة الافتراضات: ما يخصّ الخصوصية يبدأ مغلقاً (المستندات، بيانات
 * الخصم)، وما هو عرضٌ محايد يبدأ مفتوحاً (الجلسات، المسار الزمني).
 */
class ClientPortal
{
    public const KEY_ENABLED = 'client_portal_enabled';
    public const KEY_WELCOME = 'client_portal_welcome';
    public const KEY_SHOW_SESSIONS = 'client_portal_show_sessions';
    public const KEY_SHOW_TIMELINE = 'client_portal_show_timeline';
    public const KEY_SHOW_DOCUMENTS = 'client_portal_show_documents';
    public const KEY_SHOW_OPPONENT = 'client_portal_show_opponent';
    public const KEY_SHOW_LAWYER = 'client_portal_show_lawyer';

    /** الافتراضات: الخصوصية أولاً */
    private const DEFAULTS = [
        self::KEY_ENABLED => '1',
        self::KEY_SHOW_SESSIONS => '1',
        self::KEY_SHOW_TIMELINE => '1',
        self::KEY_SHOW_DOCUMENTS => '0',   // لا مستند إلا بقرار صريح
        self::KEY_SHOW_OPPONENT => '0',    // بيانات الخصم ليست للعرض افتراضاً
        self::KEY_SHOW_LAWYER => '1',
    ];

    public static function enabled(): bool
    {
        return self::flag(self::KEY_ENABLED);
    }

    public static function flag(string $key): bool
    {
        $value = Setting::get($key, self::DEFAULTS[$key] ?? '0');

        return $value === '1' || $value === 1 || $value === true;
    }

    public static function showsSessions(): bool
    {
        return self::flag(self::KEY_SHOW_SESSIONS);
    }

    public static function showsTimeline(): bool
    {
        return self::flag(self::KEY_SHOW_TIMELINE);
    }

    public static function showsDocuments(): bool
    {
        return self::flag(self::KEY_SHOW_DOCUMENTS);
    }

    public static function showsOpponent(): bool
    {
        return self::flag(self::KEY_SHOW_OPPONENT);
    }

    public static function showsLawyer(): bool
    {
        return self::flag(self::KEY_SHOW_LAWYER);
    }

    public static function welcome(): ?string
    {
        $text = Setting::get(self::KEY_WELCOME);

        return is_string($text) && trim($text) !== '' ? trim($text) : null;
    }

    /** بيانات تواصل المكتب — العميل يتواصل مع مكتبه لا مع مطوّر المنصة */
    public static function contact(): array
    {
        return array_filter([
            'name' => OfficeBrand::name(),
            'email' => Setting::get('office_email'),
            'phone' => Setting::get('office_phone'),
            'address' => Setting::get('office_address'),
        ], static fn ($v) => is_string($v) && trim($v) !== '');
    }

    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    /**
     * أنواع أحداث المسار الزمني المسموح للعميل برؤيتها.
     *
     * قائمة سماح لا قائمة منع: أي نوع حدث جديد يُضاف إلى النظام لاحقاً
     * يبقى محجوباً عن العميل حتى يُدرَج هنا عمداً. الخطأ الآمن هو الإخفاء.
     */
    public static function clientVisibleActivityTypes(): array
    {
        // الأنواع الفعلية في CaseActivity هي:
        // note, call, document, task, session, payment, appointment, other
        //
        // المسموح منها للعميل:
        //   session     — جلسة تخصّه ويعلمها أصلاً
        //   appointment — موعد معه
        //   document    — إضافة مستند (الخبر لا محتواه)
        //
        // والمحجوب عمداً: note و call (ملاحظات ومكالمات داخلية)،
        // task (مهامّ الفريق)، payment (شأن مالي لا يُعرض بلا قرار)،
        // other (مجهول المحتوى — والخطأ الآمن هو الإخفاء).
        return [
            \App\Models\CaseActivity::TYPE_SESSION,
            \App\Models\CaseActivity::TYPE_APPOINTMENT,
            \App\Models\CaseActivity::TYPE_DOCUMENT,
        ];
    }
}
