<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;

/**
 * النسخة الاحتياطية اليومية — نسخة واحدة تتجدد، لا أكوام.
 *
 * ═══ سياسة صاحب المنصة ═══
 *
 * «نسخة وحدة تحدّث يومياً وليس أكثر — عشان المساحة والأمان». النظام
 * السابق راكم: سبع يوميات، وعشرين نصف-ساعية، وسلّم ترقيةٍ أسبوعي
 * وشهري وسنوي — لكل مكتب. كل ملفٍ منها قاعدةُ بيانات موكلين كاملة،
 * فكثرتُها عبءُ مساحةٍ وسطحُ تسريب معاً.
 *
 * ═══ لماذا لا يضيع شيء رغم أنها واحدة ═══
 *
 * تُبنى النسخة باسم مؤقت وتُفحص، ولا تحل محل السابقة إلا بعد نجاح
 * الفحص وبتبديلٍ ذرّي (rename) — فلا توجد لحظةٌ بلا نسخةٍ سليمة،
 * وفشلُ الليلة يُبقي نسخةَ الأمس كما هي. والتعدد يتحقق بالأماكن لا
 * بالأعداد: خزنة الجذر على الخادم تلتقط النسخة ذاتها بعد إنشائها،
 * والتذكير الشهري يدعو المدير لتنزيل نسخته الخاصة.
 */
class DailyBackup extends Command
{
    protected $signature = 'backup:daily';
    protected $description = 'نسخة احتياطية واحدة متجددة يومياً — تُفحص قبل أن تحل محل السابقة';

    /** اسم النسخة الثابت — ثباتُه ما يسمح للخزنة والاسترجاع بإيجاده دائماً */
    public const LATEST = 'backup-latest.zip';

    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0700, true);
        }

        // البناء باسم مؤقت: النسخة الحالية لا تُمسّ حتى تنجح الجديدة
        $building = $backupDir . '/backup-new.zip';
        $latest = $backupDir . '/' . self::LATEST;
        @unlink($building);

        $zip = new ZipArchive();
        if ($zip->open($building, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('تعذّر إنشاء ملف النسخة.');
            \App\Support\BackupStatus::record(false, 'تعذّر إنشاء ملف النسخة');

            return 1;
        }

        // MySQL dump
        $sqlFile = tempnam(sys_get_temp_dir(), 'backup') . '.sql';
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        $mysqldump = PHP_OS_FAMILY === 'Windows'
            ? '"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqldump.exe"'
            : (trim((string) shell_exec('command -v mariadb-dump')) ?: 'mysqldump');

        // Use temp config file for mysqldump to avoid shell escaping issues
        $configFile = tempnam(sys_get_temp_dir(), 'my') . '.cnf';
        file_put_contents($configFile, "[client]\nhost={$host}\nport={$port}\nuser={$user}\npassword={$pass}\n");

        $command = sprintf(
            '%s --defaults-extra-file=%s --no-tablespaces --single-transaction --routines --triggers %s > %s',
            $mysqldump,
            escapeshellarg($configFile),
            escapeshellarg($db),
            escapeshellarg($sqlFile)
        );
        exec($command, $output, $exitCode);

        if ($exitCode === 0 && file_exists($sqlFile) && filesize($sqlFile) > 0) {
            $zip->addFile($sqlFile, 'database/backup.sql');
        } else {
            $this->warn('MySQL dump failed, trying fallback...');
            $dbPath = database_path('database.sqlite');

            if (file_exists($dbPath)) {
                $zip->addFile($dbPath, 'database/database.sqlite');
            } else {
                // لا تفريغ ولا بديل: أرشيف بلا قاعدة بيانات ليس نسخة
                // احتياطية بل وهمُ نسخة — نفشل بصوت عالٍ ولا ننشئه أصلاً.
                $zip->close();
                @unlink($building);
                @unlink($sqlFile);
                @unlink($configFile);

                $this->error('تعذّر تفريغ قاعدة البيانات ولا يوجد بديل — لم تُنشأ نسخة.');
                $this->error('النسخة الحالية لم تُمسّ.');

                \App\Support\BackupStatus::record(false, 'تعذّر تفريغ قاعدة البيانات ولا بديل لها');

                self::audit([
                    'user_id' => null,
                    'action' => 'backup_failed',
                    'model_type' => 'System',
                    'model_id' => null,
                    'old_values' => null,
                    'new_values' => json_encode(['reason' => 'mysqldump failed, no fallback db'], JSON_UNESCAPED_UNICODE),
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'System',
                ]);

                return 1;
            }
        }

        $storagePath = storage_path('app/private');
        if (is_dir($storagePath)) {
            $this->addDirectoryToZip($zip, $storagePath, 'storage/private');
        }

        $zip->close();

        @unlink($sqlFile);
        @unlink($configFile);
        chmod($building, 0600);

        // الفحص قبل التبديل: نسخة لم تُفحص ليست نسخة، ونسخةٌ فاشلة
        // لا يُسمح لها أن تحل محل نسخةٍ سليمة.
        $verify = \App\Support\BackupVerifier::verify($building);

        if (!$verify['ok']) {
            $this->error('النسخة الاحتياطية فشلت في الفحص: ' . $verify['reason']);
            $this->error('نسخة الأمس لم تُمسّ — لا تعتمد على ملف الليلة.');
            @unlink($building);

            \App\Support\BackupStatus::record(false, 'فشل الفحص: ' . $verify['reason']);

            self::audit([
                'user_id' => null,
                'action' => 'backup_failed',
                'model_type' => 'System',
                'model_id' => null,
                'old_values' => null,
                'new_values' => json_encode(['reason' => $verify['reason']], JSON_UNESCAPED_UNICODE),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'System',
            ]);

            return 1;
        }

        // التبديل الذرّي: rename على نفس القرص لا يترك لحظةً بلا ملف
        rename($building, $latest);

        \App\Support\BackupStatus::record(true, tables: (int) $verify['tables']);

        $size = round(filesize($latest) / 1024 / 1024, 2);
        $this->info('النسخة محدَّثة: ' . self::LATEST . " ({$size} MB)");
        $this->info('الفحص: قاعدة البيانات داخل الأرشيف، ' . $verify['tables'] . ' جدولاً، ' . $verify['files'] . ' ملفاً.');

        self::audit([
            'user_id' => null,
            'action' => 'backup_created',
            'model_type' => 'System',
            'model_id' => null,
            'old_values' => null,
            'new_values' => json_encode([
                'filename' => self::LATEST,
                'size_mb' => $size,
                'tables' => $verify['tables'],
                'files' => $verify['files'],
                'verified' => true,
            ]),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'System',
        ]);

        // تنظيف تركة النظام القديم — بعد نجاح نسخة الليلة وفحصها فقط،
        // فلا نكون أبداً أفقر نسخاً مما كنا قبل الحذف.
        foreach ($this->cleanupLegacy($backupDir) as $line) {
            $this->info($line);
        }

        return 0;
    }

    /**
     * إزالة أكوام النظام القديم — الآليُّ قطعاً فوراً، والملتبسُ بمهلة.
     *
     * auto-*.zip وweekly/monthly/yearly-*.zip لا يصنعها إلا الجهاز —
     * تُحذف مباشرة. أما backup-بتاريخ.zip فكانت تسمية اليوميات القديمة
     * «واليدويةِ من الواجهة أيضاً» — قد تكون نسخةً صنعها مديرٌ بيده قبل
     * عمليةٍ خطرة، فلا تُحذف إلا بعد ٤٥ يوماً من عمرها. واليدوية
     * الجديدة تُسمّى manual-* ولا يمسّها هذا التنظيف أبداً.
     *
     * @return array<int, string>
     */
    private function cleanupLegacy(string $backupDir): array
    {
        $report = [];
        $freed = 0;

        foreach (['auto-*.zip', 'weekly-*.zip', 'monthly-*.zip', 'yearly-*.zip'] as $pattern) {
            foreach (glob($backupDir . '/' . $pattern) ?: [] as $file) {
                $freed += (int) filesize($file);
                @unlink($file);
                $report[] = 'أُزيلت نسخة النظام القديم: ' . basename($file);
            }
        }

        $cutoff = time() - 45 * 86400;
        foreach (glob($backupDir . '/backup-[0-9]*.zip') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                $freed += (int) filesize($file);
                @unlink($file);
                $report[] = 'أُزيلت نسخة مؤرّخة تجاوزت ٤٥ يوماً: ' . basename($file);
            }
        }

        if ($freed > 0) {
            $report[] = 'المساحة المحرَّرة: ' . round($freed / 1024 / 1024, 1) . ' MB';
        }

        return $report;
    }

    /**
     * تدوينُ الحدث في السجلّ — ولا يُفشل النسخَ إن تعذّر.
     *
     * وقع فعلاً: مكتبٌ لم تُنفَّذ هجراتُه فلا جدول audit_logs عنده،
     * فرمت الكتابةُ استثناءً بعد أن تمّت النسخة، فمات الأمرُ ورمزُ
     * خروجه غير صفر — فامتنع تحديثُ ذلك المكتب لأنّ «النسخة لم تنجح»،
     * وهي قد نجحت.
     *
     * @param array<string, mixed> $row
     */
    private static function audit(array $row): void
    {
        try {
            \App\Models\AuditLog::create($row);
        } catch (\Throwable) {
            // السجلّ خبرٌ عن الحدث لا الحدثُ نفسه
        }
    }

    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $zipDir): void
    {
        $files = glob($directory . '/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                $zip->addFile($file, $zipDir . '/' . basename($file));
            } elseif (is_dir($file)) {
                $this->addDirectoryToZip($zip, $file, $zipDir . '/' . basename($file));
            }
        }
    }
}
