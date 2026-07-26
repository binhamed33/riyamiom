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
            : 'mysqldump';

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

        $size = round(filesize($filepath) / 1024 / 1024, 2);
        $this->info("Backup created: {$filename} ({$size} MB)");

        \App\Models\AuditLog::create([
            'user_id' => null,
            'action' => 'backup_created',
            'model_type' => 'System',
            'model_id' => null,
            'old_values' => null,
            'new_values' => json_encode(['filename' => $filename, 'size_mb' => $size]),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'System',
        ]);

        $this->cleanupOldBackups($backupDir, 60);

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

    private function cleanupOldBackups(string $backupDir, int $keepCount): void
    {
        $files = glob($backupDir . '/*.zip');
        if (count($files) <= $keepCount) {
            return;
        }

        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));

        $toDelete = array_slice($files, 0, count($files) - $keepCount);
        foreach ($toDelete as $file) {
            unlink($file);
            $this->info("Old backup removed: " . basename($file));
        }
    }
}
