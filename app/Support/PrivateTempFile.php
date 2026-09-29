<?php

namespace App\Support;

/**
 * ملفٌّ مؤقّتٌ يقرؤه صاحبُه وحدَه.
 *
 * ═══ الفخُّ الذي وقع فيه موضعان ═══
 *
 * ‏tempnam() ينشئ الملفَّ بصلاحية 0600 — صحيح. لكنّ إلحاق لاحقةٍ به:
 *
 *     $sqlFile = tempnam(sys_get_temp_dir(), 'db_') . '.sql';
 *
 * يعطي اسماً **لملفٍّ آخر لم يُنشأ بعد**. فمن ينشئه فعلاً هو
 * ‏mysqldump (أو إعادةُ التوجيه ‎>) بقناع النظام — أي 0644 في ‎/tmp
 * المشترك. والأصليُّ ذو الـ0600 يبقى فارغاً ولا يُمحى.
 *
 * والمكتوبُ هناك ليس أيَّ شيء: نسخةُ قاعدة المكتب كاملةً — أسماءُ
 * الموكّلين وأرقامُهم وأرقامُ هوياتهم وملفّاتُ القضايا والفواتيرُ
 * وتجزئاتُ كلمات المرور. وكلُّ مكتبٍ على الخادم مستخدمُ لينكس مستقلّ
 * يقرأ ‎/tmp. والنسخةُ اليوميّة تكتبها كلَّ ليلةٍ في الثانية عشرة.
 *
 * ‏MysqlCredentialsFile أصلحت هذا لملفّ الاعتماد ولم يُصلَح للنسخة.
 * فالمؤقّتُ هنا: داخل تخزين المكتب لا في ‎/tmp، باسمٍ لا يُتوقَّع،
 * ومُنشأٌ 0600 قبل أن يكتب فيه أحد.
 */
class PrivateTempFile
{
    public static function create(string $prefix, string $suffix = ''): string
    {
        $dir = storage_path('app/backups/tmp');

        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        @chmod($dir, 0700);

        $path = $dir . '/' . $prefix . bin2hex(random_bytes(12)) . $suffix;

        // يُنشأ فارغاً ثمّ يُقيَّد: من يكتب فيه بعد ذلك — بإعادة توجيه
        // أو ‎--result-file — يقتطعه ولا يغيّر صلاحيتَه
        touch($path);
        chmod($path, 0600);

        return $path;
    }

    /**
     * كنسُ ما تُرك من نسخٍ قُتلت في منتصفها.
     *
     * ═══ ما وقع ═══
     *
     * نسخةٌ تُقطع (قرصٌ امتلأ أثناء الضغط، عمليةٌ أُوقفت، خطأٌ قاتل) تترك
     * هنا ملفَّ mysqldump كاملاً — قاعدةُ المكتب نصّاً صريحاً — ولا أحدَ
     * يحذفه: الحذفُ في مسار النجاح وحدَه. فتراكمت نسخٌ خامٌ على القرص،
     * وكلُّ واحدةٍ بحجم القاعدة، وكلُّها سرٌّ مكشوف.
     *
     * ═══ الشرطُ الدقيق ═══
     *
     * داخل مجلّد tmp هذا وحدَه، وباسمٍ من صنع create() (بادئةٌ ثمّ 24 خانةً
     * ستّ عشريّة)، وأقدمُ من يوم: نسخةٌ جاريةٌ الآن لا تبلغ ذلك، فلا يُلمس
     * ما قد يكون قيد الكتابة.
     *
     * @return int عددُ ما كُنس
     */
    public static function sweep(int $olderThanSeconds = 86400): int
    {
        $dir = storage_path('app/backups/tmp');

        if (!is_dir($dir)) {
            return 0;
        }

        $swept = 0;
        $cutoff = time() - $olderThanSeconds;

        foreach (glob($dir . '/*') ?: [] as $file) {
            if (!is_file($file) || !preg_match('/^[a-z-]+[0-9a-f]{24}(\.[a-z0-9]+)?$/', basename($file))) {
                continue;
            }

            $mtime = @filemtime($file);

            if ($mtime !== false && $mtime < $cutoff && @unlink($file)) {
                $swept++;
            }
        }

        return $swept;
    }
}
