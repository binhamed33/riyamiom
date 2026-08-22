<?php

namespace App\Support;

use ZipArchive;

/**
 * فحص نسخة احتياطية.
 *
 * نسخة لم تُفحص ليست نسخة: أرشيف معطوب، أو أرشيف بلا قاعدة بيانات،
 * أو تفريغ بلا جداول — كلها «وهمُ نسخة» يُكتشف يوم الحاجة إليها،
 * وهو أسوأ يوم ممكن لاكتشافه.
 */
class BackupVerifier
{
    /** @return array{ok: bool, reason: string, tables: int, files: int} */
    public static function verify(string $filepath): array
    {
        $out = ['ok' => false, 'reason' => '', 'tables' => 0, 'files' => 0];

        if (!file_exists($filepath) || filesize($filepath) === 0) {
            $out['reason'] = 'الملف غير موجود أو حجمه صفر';

            return $out;
        }

        $zip = new ZipArchive();

        if ($zip->open($filepath, ZipArchive::CHECKCONS) !== true) {
            $out['reason'] = 'الأرشيف معطوب — لا يجتاز فحص السلامة';

            return $out;
        }

        $out['files'] = $zip->numFiles;

        $dump = $zip->getFromName('database/backup.sql')
            ?: $zip->getFromName('database/database.sqlite');

        if ($dump === false || $dump === '') {
            $zip->close();
            $out['reason'] = 'لا قاعدة بيانات داخل الأرشيف — نسخة بلا فائدة';

            return $out;
        }

        if (str_starts_with($dump, 'SQLite format 3')) {
            $out['tables'] = -1;   // ملف قاعدة صحيح، والعدّ من النص غير ممكن
        } else {
            $out['tables'] = substr_count($dump, 'CREATE TABLE');

            if ($out['tables'] === 0) {
                $zip->close();
                $out['reason'] = 'التفريغ لا يحتوي أي جدول';

                return $out;
            }
        }

        $zip->close();
        $out['ok'] = true;

        return $out;
    }

    /**
     * حذف الأقدم فقط، وضمن نمط الاسم المعطى وحده.
     *
     * القاعدتان اللتان كسرهما المنطق القديم: كان يحذف كل النسخ ويُبقي
     * واحدة، وكان نمطه `*.zip` فيلتهم النسخ اليومية من مسار النسخ
     * النصف-ساعية والعكس.
     *
     * @return array<int, string> أسماء ما حُذف
     */
    public static function prune(string $backupDir, string $pattern, int $keep): array
    {
        $files = glob($backupDir . '/' . $pattern) ?: [];

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $removed = [];

        foreach (array_slice($files, $keep) as $file) {
            if (@unlink($file)) {
                $removed[] = basename($file);
            }
        }

        return $removed;
    }
}
