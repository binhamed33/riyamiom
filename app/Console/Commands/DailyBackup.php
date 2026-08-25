<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DailyBackup extends Command
{
    protected $signature = 'backup:daily';
    protected $description = 'Create daily backup of database and private files';

    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0700, true);
        }

        $filename = 'backup-' . date('Y-m-d-His') . '.zip';
        $filepath = $backupDir . '/' . $filename;

        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Failed to create backup: {$filename}");
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
                @unlink($filepath);
                @unlink($sqlFile);
                @unlink($configFile);

                $this->error('تعذّر تفريغ قاعدة البيانات ولا يوجد بديل — لم تُنشأ نسخة.');
                $this->error('النسخ القديمة لم تُمسّ.');

                // تُدوَّن الحقيقة حيث تقرؤها اللوحة، لا في السجلّ وحده:
                // نسخةٌ تخفق كلَّ ليلة كانت تخفق بصمت.
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
        chmod($filepath, 0600);

        // نسخة لم تُفحص ليست نسخة: نتحقق أن الأرشيف يُفتح وأن قاعدة
        // البيانات داخله فعلاً وفيها جداول — قبل أن نتفاخر بأنها أُنشئت،
        // وقبل أن نحذف أي نسخة قديمة سليمة.
        $verify = \App\Support\BackupVerifier::verify($filepath);

        if (!$verify['ok']) {
            $this->error('النسخة الاحتياطية فشلت في الفحص: ' . $verify['reason']);
            $this->error('النسخ القديمة لم تُمسّ. لا تعتمد على هذا الملف.');
            @unlink($filepath);

            \App\Support\BackupStatus::record(false, 'فشل الفحص: ' . $verify['reason']);

            self::audit([
                'user_id' => null,
                'action' => 'backup_failed',
                'model_type' => 'System',
                'model_id' => null,
                'old_values' => null,
                'new_values' => json_encode(['filename' => $filename, 'reason' => $verify['reason']], JSON_UNESCAPED_UNICODE),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'System',
            ]);

            return 1;
        }

        \App\Support\BackupStatus::record(true, tables: (int) $verify['tables']);

        $size = round(filesize($filepath) / 1024 / 1024, 2);
        $this->info("Backup created: {$filename} ({$size} MB)");
        $this->info('الفحص: قاعدة البيانات داخل الأرشيف، ' . $verify['tables'] . ' جدولاً، ' . $verify['files'] . ' ملفاً.');

        self::audit([
            'user_id' => null,
            'action' => 'backup_created',
            'model_type' => 'System',
            'model_id' => null,
            'old_values' => null,
            'new_values' => json_encode([
                'filename' => $filename,
                'size_mb' => $size,
                'tables' => $verify['tables'],
                'files' => $verify['files'],
                'verified' => true,
            ]),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'System',
        ]);

        // الاحتفاظ بسبع نسخ لا واحدة: نسخة الليلة قد تكون معطوبة بعطل
        // لم يكشفه الفحص، وسبع ليالٍ تاريخ يُرجَع إليه.
        foreach (\App\Support\BackupVerifier::prune($backupDir, 'backup-*.zip', keep: 7) as $removed) {
            $this->info('Old backup removed: ' . $removed);
        }

        return 0;
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
