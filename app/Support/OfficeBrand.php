<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * هوية المكتب البصرية — مصدر واحد لشعار المكتب واسمه.
 *
 * العزل: كل مكتب في مُداوَلة نسخة مستقلة بقاعدة بياناتها وتخزينها، فالشعار
 * يُحفظ داخل تخزين المكتب نفسه (storage/app/private/office) ويُقرأ مساره من
 * جدول إعدادات المكتب وحده — لا يمكن لمكتب الوصول إلى شعار مكتب آخر.
 *
 * ملاحظة توافق: النسخ التي وُضع فيها الشعار يدوياً تحت public/img/office-logo.*
 * تبقى تعمل كما هي (fallback) حتى لا تفقد أي نسخة قائمة شعارها.
 */
class OfficeBrand
{
    public const DIR = 'office';
    public const KEY_PATH = 'office_logo_path';
    public const KEY_VERSION = 'office_logo_updated_at';
    // svg مرفوض: ملفٌّ نصّيٌّ يحمل سكربتاً ويُقدَّم inline من نطاق
    // المكتب، فيُنفَّذ في أصلِه لو ضعُفت سياسةُ المحتوى يوماً.
    // logoDataUri() يرفضه أصلاً، وAttachments::INLINE_TYPES تستثنيه
    // للسبب نفسِه — فبقاؤه هنا كان الشذوذَ لا القاعدة.
    public const ALLOWED = ['png', 'jpg', 'jpeg', 'webp'];
    public const MAX_KB = 1024;

    /** اسم المكتب المعروض (هوية المكتب لا هوية المنتج) */
    public static function name(): string
    {
        return (string) Setting::get('office_name', 'مُداوَلة');
    }

    /** المسار النسبي المخزَّن داخل القرص الخاص، أو null */
    public static function storedPath(): ?string
    {
        $path = Setting::get(self::KEY_PATH);
        if (!$path || !is_string($path)) {
            return null;
        }

        // تحصين: لا نقبل إلا ملفاً باسم متوقَّع داخل مجلد المكتب — لا مسارات خارجة
        if (!preg_match('#^' . self::DIR . '/logo\.(' . implode('|', self::ALLOWED) . ')$#', $path)) {
            return null;
        }

        return Storage::disk('local')->exists($path) ? $path : null;
    }

    /** رابط عرض الشعار (أو null إن لم يوجد شعار للمكتب) */
    public static function logoUrl(): ?string
    {
        if (self::storedPath()) {
            $v = (string) Setting::get(self::KEY_VERSION, '1');

            return route('office.logo') . '?v=' . urlencode($v);
        }

        return self::legacyLogo()['url'] ?? null;
    }

    public static function logoMime(): ?string
    {
        if ($path = self::storedPath()) {
            return self::mimeFor(pathinfo($path, PATHINFO_EXTENSION));
        }

        return self::legacyLogo()['mime'] ?? null;
    }

    public static function hasLogo(): bool
    {
        return self::logoUrl() !== null;
    }

    /**
     * الشعار كـ data URI للطباعة و PDF — محرك الطباعة لا يستطيع طلب رابط محمي.
     * SVG يُستثنى لأن DomPDF لا يرسمه بثبات؛ عندها يُطبع اسم المكتب وحده.
     */
    public static function logoDataUri(): ?string
    {
        $file = null;
        $ext = null;

        if ($path = self::storedPath()) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $file = Storage::disk('local')->path($path);
        } else {
            foreach (['png', 'jpg', 'jpeg', 'webp'] as $candidate) {
                $legacy = public_path("img/office-logo.{$candidate}");
                if (is_file($legacy)) {
                    $file = $legacy;
                    $ext = $candidate;
                    break;
                }
            }
        }

        if (!$file || !$ext || $ext === 'svg' || !is_file($file)) {
            return null;
        }

        $bytes = @file_get_contents($file);
        if ($bytes === false) {
            return null;
        }

        return 'data:' . self::mimeFor($ext) . ';base64,' . base64_encode($bytes);
    }

    public static function mimeFor(string $ext): string
    {
        return match (strtolower($ext)) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    /** شعار وُضع يدوياً في public/img — يبقى مدعوماً للنسخ القديمة */
    private static function legacyLogo(): array
    {
        foreach (['svg', 'png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $file = public_path("img/office-logo.{$ext}");
            if (is_file($file)) {
                return [
                    'url' => asset("img/office-logo.{$ext}") . '?v=' . @filemtime($file),
                    'mime' => self::mimeFor($ext),
                ];
            }
        }

        return [];
    }
}
