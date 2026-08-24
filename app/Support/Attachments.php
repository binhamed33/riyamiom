<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تقديم المرفقات: مرفقُ المحادثة ومرفقُ القيد الماليّ.
 *
 * ═══ لماذا لا تُقدَّم من القرص العام ═══
 *
 * كانت تُحفظ في storage/app/public وتُقدَّم بـ Storage::url() — أي عبر
 * الرابط الرمزيّ public/storage. وفي هذا المستودع public/storage مجلدٌ
 * حقيقيّ متتبَّع في git، فـ storage:link لا ينشئ الرابط أبداً لأنّ
 * المكان مشغول، ويشير ‎/storage/…‎ إلى مجلدٍ فارغ. فكانت كل صورة في
 * المحادثات تظهر مكسورة وكل تنزيل يقول «File wasn't available on site».
 *
 * والأخطر أنّ الرابط لو عمل لصار كلُّ مرفقٍ مقروءاً لمن يملك رابطه بلا
 * تسجيل دخول. ومرفقات مكتب محاماة صورُ هوياتٍ وعقودٌ ومستنداتُ قضايا،
 * لا تُترك على طريق عام.
 *
 * فالمرفقات الجديدة تُحفظ على القرص الخاص وتُقدَّم عبر مسارٍ يتحقّق من
 * الصلاحية أولاً. والقديمة الباقية على القرص العام تُقرأ من مكانها كما
 * هي — لا تُنقل ولا تُحذف، فما حُفظ لا يضيع.
 */
class Attachments
{
    /** حيث تُحفظ الجديدة، ثم القرص العام حيث بقيت القديمة. */
    public const DISKS = ['attachments', 'public'];

    /** حيث تُحفظ كل مرفقة جديدة. */
    public const DISK = 'attachments';

    /** يُعرَض داخل الصفحة؛ ما عداه يُنزَّل. */
    private const INLINE_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav',
        'video/mp4', 'video/quicktime', 'video/webm',
    ];

    /**
     * القرص الذي يحمل الملف فعلاً — الخاصّ أولاً ثم العام للقديم.
     */
    public static function diskFor(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        foreach (self::DISKS as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    public static function exists(?string $path): bool
    {
        return self::diskFor($path) !== null;
    }

    /** حذفٌ من حيث وُجد — القديم على العام والجديد على الخاص. */
    public static function delete(?string $path): void
    {
        $disk = self::diskFor($path);

        if ($disk !== null) {
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * الردّ بالملف.
     *
     * ولا يُعرَض داخل الصفحة إلا ما يُؤمَن عرضُه. الـ SVG صورةٌ في
     * ظاهرها ومستندٌ يحمل سكربتاً في حقيقتها: عرضُه من نطاق المكتب
     * يُشغّل سكربته في جلسة من يفتحه. فيُنزَّل ولا يُعرَض، ويُمنَع
     * المتصفّح من تخمين النوع بـ nosniff.
     */
    public static function respond(
        Request $request,
        string $path,
        ?string $name,
        ?string $type,
    ): StreamedResponse {
        $disk = self::diskFor($path);

        abort_if($disk === null, 404, 'المرفق غير موجود.');

        $type = is_string($type) && $type !== '' ? $type : self::typeFromPath($path);

        $inline = !$request->boolean('download') && in_array($type, self::INLINE_TYPES, true);

        return Storage::disk($disk)->response(
            $path,
            self::safeName($name, $path),
            [
                'Content-Type' => $inline ? $type : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
            $inline ? 'inline' : 'attachment',
        );
    }

    /**
     * نوعُ الملف من امتداده — للقيود المالية التي لا تحفظ النوع.
     *
     * ولا يُستنتج إلا من قائمةٍ مغلقة: امتدادٌ مجهول يبقى ملفاً يُنزَّل،
     * فلا يصير الاستنتاجُ باباً لعرض ما لا يُؤمَن عرضه.
     */
    private static function typeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    /**
     * اسمٌ يصلح لترويسة الردّ.
     *
     * اسمُ الملف يأتي من المتصفّح كما كتبه صاحبه، وقد يحمل سطراً جديداً
     * فيَشُقّ الترويسة إلى ترويستين (header injection). فتُنزع أحرف
     * التحكّم والمسارات ويبقى الاسم وحده.
     */
    private static function safeName(?string $name, string $path): string
    {
        $name = is_string($name) ? $name : '';
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = str_replace(['/', '\\', '"'], '', $name);
        $name = trim($name);

        return $name !== '' ? mb_substr($name, 0, 120) : basename($path);
    }

    /** حجمٌ يُقرأ: «2.4 م.ب» لا «2516582». */
    public static function humanSize(?int $bytes): string
    {
        if ($bytes === null || $bytes <= 0) {
            return '';
        }

        foreach ([['ج.ب', 1073741824], ['م.ب', 1048576], ['ك.ب', 1024]] as [$unit, $step]) {
            if ($bytes >= $step) {
                $value = $bytes / $step;

                return ($value >= 10 ? number_format($value) : number_format($value, 1)) . ' ' . $unit;
            }
        }

        return $bytes . ' بايت';
    }
}
