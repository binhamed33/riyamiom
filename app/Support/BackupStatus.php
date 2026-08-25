<?php

namespace App\Support;

use App\Models\Setting;

/**
 * حقائقُ النسخ الاحتياطي في هذا المكتب — مصدرٌ واحد يقرأ منه الجميع.
 *
 * ═══ لماذا وُجد ═══
 *
 * كلُّ مكتب ينسخ نفسه يومياً، ولا يعلم بذلك أحد. ومركزُ النسخ في اللوحة
 * كان يقرأ `last_backup_at` — وهو حقلٌ لا تكتبه إلا وظيفةُ اللوحة نفسها
 * حين تنسخ من طرفها. فمكتبٌ ينسخ نفسه كلَّ ليلة يظهر في المركز «لا نسخ
 * بعد»، ومكتبٌ عطبت نسخُه منذ أسبوع يظهر مثله تماماً.
 *
 * وهذه أسوأ حالات الشاشة: لا تكذب في اتجاهٍ واحد بل تُسوّي بين السليم
 * والمعطوب، فلا يُقرأ منها شيء.
 *
 * ═══ الملفّاتُ هي الحقيقة ═══
 *
 * ما يُقرأ من القرص — العدد والأحدث وحجمه — يُقرأ من القرص لا من إعداد
 * مسجَّل: إعدادٌ قد يبقى بعد أن يُحذف الملف، والملفُّ لا يكذب عن نفسه.
 * ولا يُسجَّل في الإعدادات إلا ما لا يُقرأ من الملفات: أنجحت آخرُ محاولة
 * أم أخفقت، ولماذا.
 */
class BackupStatus
{
    public const KEY_LAST_OK_AT = 'backup_last_ok_at';
    public const KEY_LAST_RUN_AT = 'backup_last_run_at';
    public const KEY_LAST_ERROR = 'backup_last_error';
    public const KEY_LAST_TABLES = 'backup_last_tables';

    private const DIR = 'app/backups';
    private const GLOB = 'backup-*.zip';

    /**
     * تسجيلُ نتيجة محاولة.
     *
     * ولا يُرمى من هنا شيءٌ أبداً: هذا تدوينُ خبرٍ عن النسخة، وليس من
     * حقّه أن يُسقط النسخة نفسها. وقد وقع ذلك فعلاً — كتابةُ سجلّ في
     * جدولٍ مفقود رمت استثناءً فمات أمرُ النسخ كلُّه.
     */
    public static function record(bool $ok, string $reason = '', ?int $tables = null): void
    {
        try {
            Setting::set(self::KEY_LAST_RUN_AT, now()->toIso8601String(), 'backup');

            if ($ok) {
                Setting::set(self::KEY_LAST_OK_AT, now()->toIso8601String(), 'backup');
                Setting::set(self::KEY_LAST_ERROR, '', 'backup');

                if ($tables !== null) {
                    Setting::set(self::KEY_LAST_TABLES, (string) $tables, 'backup');
                }

                return;
            }

            // السببُ يُقصّ ولا يُنقّى من سرّ: رسائل mysqldump تحمل اسم
            // المستخدم والمضيف، وهذا السطر يُرسَل إلى اللوحة ويُعرض.
            Setting::set(self::KEY_LAST_ERROR, mb_substr(MailIdentity::scrub($reason), 0, 300), 'backup');
        } catch (\Throwable) {
            // لا شيء: تدوينُ الخبر لا يُفشل الخبر
        }
    }

    /** مجلّدُ النسخ — يُنشأ إن غاب. */
    public static function directory(): string
    {
        return storage_path(self::DIR);
    }

    /** @return list<string> مساراتُ النسخ، الأحدثُ أولاً. */
    public static function files(): array
    {
        $files = glob(self::directory() . '/' . self::GLOB) ?: [];

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $files;
    }

    /**
     * ما يُرسَل إلى اللوحة.
     *
     * أرقامٌ وتواريخ وسببٌ منقّى — ولا اسم موكّل ولا محتوى ملف. بياناتُ
     * المكتب لا تغادر خادمه.
     *
     * @return array{
     *     last_at: ?string, last_ok_at: ?string, last_run_at: ?string,
     *     count: int, size_bytes: int, total_bytes: int,
     *     oldest_at: ?string, error: ?string, tables: ?int
     * }
     */
    public static function summary(): array
    {
        $files = self::files();
        $newest = $files[0] ?? null;
        $oldest = $files === [] ? null : $files[count($files) - 1];

        $error = trim((string) self::setting(self::KEY_LAST_ERROR));

        return [
            // زمنُ أحدث ملفٍ على القرص — لا زمنُ آخر محاولة. محاولةٌ
            // أخفقت لا تُنتج ملفاً، فلا تُقدّم عمرَ آخر نسخةٍ صالحة.
            'last_at' => $newest ? self::iso(filemtime($newest)) : null,
            'last_ok_at' => self::setting(self::KEY_LAST_OK_AT) ?: null,
            'last_run_at' => self::setting(self::KEY_LAST_RUN_AT) ?: null,
            'count' => count($files),
            'size_bytes' => $newest ? (int) filesize($newest) : 0,
            'total_bytes' => array_sum(array_map(fn ($f) => (int) filesize($f), $files)),
            'oldest_at' => $oldest ? self::iso(filemtime($oldest)) : null,
            'error' => $error !== '' ? $error : null,
            'tables' => ($t = self::setting(self::KEY_LAST_TABLES)) !== '' ? (int) $t : null,
        ];
    }

    private static function setting(string $key): string
    {
        try {
            return (string) Setting::get($key, '');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function iso(int|false $timestamp): ?string
    {
        return $timestamp === false ? null : \Illuminate\Support\Carbon::createFromTimestamp($timestamp)->toIso8601String();
    }
}
