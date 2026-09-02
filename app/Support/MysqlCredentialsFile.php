<?php

namespace App\Support;

/**
 * ملفُّ بيانات الاتصال المؤقّت لـmysqldump/mysql — بإذنِ صاحبه وحدَه.
 *
 * ═══ الثغرة ═══
 *
 * كان الملفُّ يُنشأ هكذا: tempnam() . '.cnf' ثمّ file_put_contents.
 * tempnam تُنشئ ملفّاً بإذن 0600 — لكنّ الاسمَ الذي يُكتب فيه غيرُه
 * (اللاحقةُ أُلصقت بعدها)، فيُنشأ ملفٌّ جديدٌ بإذن umask العاديّ:
 * 0644. أي أنّ كلمةَ مرور قاعدة المكتب كانت تجلس في /tmp المشترك
 * مقروءةً لكلّ مستخدمٍ على الخادم طوالَ مدّة النسخ — وعلى خادمٍ فيه
 * مكاتبُ عدّةٍ لكلٍّ منها مستخدمُ لينكس خاصّ، هذا بابٌ من مكتبٍ إلى
 * قاعدة مكتبٍ آخر. وكانت النسخةُ اليوميّةُ تفتحه كلَّ يوم.
 *
 * والملفُّ الفارغُ الذي أنشأته tempnam كان يُترك خلفنا بلا حذف.
 *
 * ═══ الآن ═══
 *
 * يُكتب في الملفّ الذي أنشأته tempnam نفسِه (0600 منذ لحظة وجوده)،
 * ويُثبَّت الإذنُ صراحةً قبل الكتابة احتياطاً من umask غريب. ولا
 * لاحقة: mysql لا تشترط .cnf لـ--defaults-extra-file.
 */
class MysqlCredentialsFile
{
    /** @return string مسارُ الملفّ — على المستدعي حذفُه بعد الاستعمال */
    public static function write(string $host, string|int $port, string $user, string $password): string
    {
        $path = tempnam(sys_get_temp_dir(), 'my');

        if ($path === false) {
            throw new \RuntimeException('تعذّر إنشاء ملفّ بيانات الاتصال المؤقّت.');
        }

        // الإذنُ قبل المحتوى: لحظةٌ واحدةٌ بمحتوًى مكشوفٍ لحظةٌ زائدة
        chmod($path, 0600);

        $written = file_put_contents(
            $path,
            "[client]\nhost=\"{$host}\"\nport={$port}\nuser=\"{$user}\"\npassword=\"{$password}\"\n",
            LOCK_EX
        );

        if ($written === false) {
            @unlink($path);
            throw new \RuntimeException('تعذّر كتابة ملفّ بيانات الاتصال المؤقّت.');
        }

        chmod($path, 0600);

        return $path;
    }
}
