<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;

class AutoBackup extends Command
{
    protected $signature = 'backup:auto';
    protected $description = 'Auto backup every 30 minutes if changes detected';

    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0700, true);
        }

        // Check if any changes since last auto backup
        $autoFiles = glob($backupDir . '/auto-*.zip');
        $lastBackupTime = 0;
        foreach ($autoFiles as $f) {
            $mtime = filemtime($f);
            if ($mtime > $lastBackupTime) {
                $lastBackupTime = $mtime;
            }
        }

        $tables = ['audit_logs', 'cases', 'clients', 'tasks', 'sessions', 'documents', 'users', 'finance_transactions', 'finance_invoices', 'finance_fees', 'hr_performances', 'hr_bonuses', 'hr_penalties', 'hr_leaves', 'conversations', 'messages'];
        $latestChange = 0;

        foreach ($tables as $table) {
            try {
                $ts = \Illuminate\Support\Facades\DB::table($table)->whereNull('deleted_at')->max('updated_at');
                if ($ts) {
                    $unix = strtotime($ts);
                    if ($unix > $latestChange) $latestChange = $unix;
                }
            } catch (\Exception $e) {}
        }

        // Also check audit_logs created_at
        try {
            $auditTs = \Illuminate\Support\Facades\DB::table('audit_logs')->max('created_at');
            if ($auditTs) {
                $unix = strtotime($auditTs);
                if ($unix > $latestChange) $latestChange = $unix;
            }
        } catch (\Exception $e) {}

        if ($latestChange <= $lastBackupTime) {
            $this->info('No changes detected since last auto backup. Skipping.');
            return 0;
        }

        $this->info('Changes detected. Creating auto backup...');

        // Create backup
        $filename = 'auto-' . date('Y-m-d-His') . '.zip';
        $filepath = $backupDir . '/' . $filename;

        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Failed to create backup: {$filename}");
            return 1;
        }

        $sqlFile = tempnam(sys_get_temp_dir(), 'backup') . '.sql';
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        $mysqldump = PHP_OS_FAMILY === 'Windows'
            ? '"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqldump.exe"'
            : 'mysqldump';

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
            // فشل التفريغ = لا نسخة. أرشيف من ملفات التخزين وحدها يوهم
            // أن هناك نسخة وليس فيها القاعدة — أخطر من لا شيء.
            $zip->close();
            @unlink($filepath);
            @unlink($sqlFile);
            @unlink($configFile);

            $this->error('تعذّر تفريغ قاعدة البيانات — لم تُنشأ نسخة تلقائية. النسخ القديمة لم تُمسّ.');

            return 1;
        }

        $storagePath = storage_path('app/private');
        if (is_dir($storagePath)) {
            $this->addDirectoryToZip($zip, $storagePath, 'storage/private');
        }

        $zip->close();

        @unlink($sqlFile);
        @unlink($configFile);
        chmod($filepath, 0600);

        // نسخة لم تُفحص ليست نسخة
        $verify = \App\Support\BackupVerifier::verify($filepath);

        if (!$verify['ok']) {
            $this->error('النسخة التلقائية فشلت في الفحص: ' . $verify['reason'] . ' — حُذفت، والقديمة لم تُمسّ.');
            @unlink($filepath);

            return 1;
        }

        $size = round(filesize($filepath) / 1024 / 1024, 2);
        $this->info("Auto backup created: {$filename} ({$size} MB)");
        $this->info('الفحص: ' . $verify['tables'] . ' جدولاً، ' . $verify['files'] . ' ملفاً.');

        \App\Models\AuditLog::create([
            'user_id' => null,
            'action' => 'backup_auto',
            'model_type' => 'System',
            'model_id' => null,
            'old_values' => null,
            'new_values' => json_encode(['filename' => $filename, 'size_mb' => $size]),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'System',
        ]);

        // النصف-ساعية تحذف النصف-ساعية وحدها — كانت تلتهم اليومية أيضاً،
        // فتُعدم نسخة ما قبل التحديث خلال نصف ساعة من أخذها.
        foreach (\App\Support\BackupVerifier::prune($backupDir, 'auto-*.zip', keep: 6) as $removed) {
            $this->info('Old backup removed: ' . $removed);
        }

        return 0;
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
