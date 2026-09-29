<?php

namespace App\Support;

/**
 * حالُ القرص ومجلّدات storage — يُقرأ ولا يُكتب.
 *
 * ═══ ما وقع ═══
 *
 * امتلأ قرصُ الخادم، فسقطت أربعَ عشرةَ صفحةً في مكتب الوالد بيضاءَ
 * برمز 500: القالبُ لا يُجمَّع لأنّ storage/framework/views لا يقبل
 * كتابة، والخطأُ لا يُدوَّن لأنّ storage/logs لا يقبلها، فيخرج
 * الاستثناءُ من معالج الاستثناءات نفسِه. وفحصُ الصحّة كان يقول «لا
 * أخطاء» — لأنّه يقرأ السجلَّ، والسجلُّ هو ما لم يُكتب. ونبضةُ المكتب
 * إلى اللوحة تعدّ الأخطاءَ من السجلّ نفسِه، فبلّغت صفراً عن مكتبٍ ساقط.
 *
 * ═══ وما هنا ═══
 *
 * سؤالان يُجاب عنهما بلا كتابة: كم بقي على القرص؟ وهل تقبل مجلّداتُ
 * storage الكتابةَ من هذا المستخدم؟ يقرؤهما فحصُ الصحّة فيصرخ، وتحملهما
 * النبضةُ إلى اللوحة فتُنذر قبل أن يفتح محامٍ صفحةً بيضاء.
 *
 * ولا ملفَّ تجربةٍ يُكتب ثمّ يُحذف: فحصُ الصحّة يَعِد أنّه قراءةٌ فقط.
 * ‏is_writable تجيب عن الملكيّة والصلاحيّة، والمساحةُ الحرّة عن
 * الامتلاء — وكلاهما سببٌ رأيناه: مجلّدٌ بملك root أنشأه أمرٌ شُغّل
 * بـsudo، وقرصٌ أكلته النسخُ والسجلّات.
 */
class StorageHealth
{
    /** دون هذا القدر الحرّ تُعدّ الحالُ حرجة: نسخةٌ ليليّة واحدة قد تتجاوزه. */
    public const LOW_FREE_BYTES = 512 * 1024 * 1024;

    /** أو دون هذه النسبة من القرص مهما كبر. */
    public const LOW_FREE_RATIO = 0.05;

    /**
     * ما يكتب فيه النظامُ مع كلّ طلب — إن أُغلق أحدُها سقطت الصفحات.
     *
     * @return array<string, string> الاسمُ المختصر ⇐ المسار
     */
    public static function paths(): array
    {
        return [
            'storage/logs' => storage_path('logs'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/app' => storage_path('app'),
        ];
    }

    /** ملفُّ السجلّ نفسُه: مجلّدُه قد يقبل الكتابة وهو بملك غيرِنا. */
    public static function logFile(): string
    {
        return storage_path('logs/laravel.log');
    }

    public static function freeBytes(?string $path = null): ?int
    {
        $free = @disk_free_space($path ?? storage_path());

        return $free === false ? null : (int) $free;
    }

    public static function totalBytes(?string $path = null): ?int
    {
        $total = @disk_total_space($path ?? storage_path());

        return $total === false ? null : (int) $total;
    }

    /** أحرجةٌ مساحةُ قرص storage؟ ‎null‎ إن تعذّرت قراءتُها. */
    public static function low(): ?bool
    {
        return self::judge(self::freeBytes(), self::totalBytes());
    }

    /**
     * الحكمُ على رقمين — بلا قراءةِ قرص، ليُختبر ويُعاد استعمالُه في اللوحة.
     *
     * الحدُّ المطلق أوّلاً ثمّ النسبة؛ وبلا كلّيٍّ يُحكم بالمطلق وحده.
     */
    public static function judge(?int $free, ?int $total): ?bool
    {
        if ($free === null) {
            return null;
        }

        if ($free < self::LOW_FREE_BYTES) {
            return true;
        }

        return $total !== null && $total > 0 && ($free / $total) < self::LOW_FREE_RATIO;
    }

    /**
     * ما لا يقبل الكتابةَ من هذا المستخدم — بأسمائه المختصرة ومساراته.
     *
     * مجلّدٌ مفقودٌ يُعدّ مغلقاً كذلك: لارافل لا ينشئ storage/framework/views
     * بنفسه، فغيابُه بعد نقلٍ أو استعادةٍ يساوي امتلاءَه في الأثر.
     *
     * @param array<string, string>|null $paths
     * @return array<string, string>
     */
    public static function unwritable(?array $paths = null): array
    {
        $bad = [];

        foreach ($paths ?? self::paths() as $label => $path) {
            if (!is_dir($path) || !is_writable($path)) {
                $bad[$label] = $path;
            }
        }

        // والملفُّ إن وُجد: ما لم يُوجد بعدُ يُنشأ في المجلّد، وذاك فُحص أعلاه
        if ($paths === null && file_exists(self::logFile()) && !is_writable(self::logFile())) {
            $bad['storage/logs/laravel.log'] = self::logFile();
        }

        return $bad;
    }

    /** اسمُ من يشغّل هذا الكود — ليُقال «لا يقبل الكتابة من فلان». */
    public static function processUser(): ?string
    {
        if (!function_exists('posix_geteuid') || !function_exists('posix_getpwuid')) {
            return null;
        }

        $info = @posix_getpwuid(posix_geteuid());

        return is_array($info) ? ($info['name'] ?? null) : null;
    }

    /** مالكُ مسارٍ بالاسم — ليُقال «بملك root». */
    public static function ownerOf(string $path): ?string
    {
        if (!function_exists('posix_getpwuid') || !file_exists($path)) {
            return null;
        }

        $uid = @fileowner($path);
        $info = $uid === false ? null : @posix_getpwuid($uid);

        return is_array($info) ? ($info['name'] ?? null) : null;
    }

    /**
     * للنبضة إلى اللوحة: رقمان وحقيقةٌ واحدة، بلا مسارات.
     *
     * @return array{free_bytes: ?int, total_bytes: ?int, writable: bool}
     */
    public static function summary(): array
    {
        return [
            'free_bytes' => self::freeBytes(),
            'total_bytes' => self::totalBytes(),
            'writable' => self::unwritable() === [],
        ];
    }

    /** ‏1.2 غ.ب / 512 م.ب — للعين لا للحساب. */
    public static function human(int $bytes): string
    {
        if ($bytes >= 1024 ** 3) {
            return round($bytes / 1024 ** 3, 1) . ' غ.ب';
        }

        return round($bytes / 1024 ** 2) . ' م.ب';
    }
}
