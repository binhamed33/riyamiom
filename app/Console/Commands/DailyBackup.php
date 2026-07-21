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
            mkdir($backupDir, 0755, true);
        }

        $filename = 'backup-' . date('Y-m-d-His') . '.zip';
        $filepath = $backupDir . '/' . $filename;

        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Failed to create backup: {$filename}");
            return 1;
        }

        $dbPath = database_path('database.sqlite');
        if (file_exists($dbPath)) {
            $zip->addFile($dbPath, 'database/database.sqlite');
        }

        $storagePath = storage_path('app/private');
        if (is_dir($storagePath)) {
            $this->addDirectoryToZip($zip, $storagePath, 'storage/private');
        }

        $zip->close();

        $size = round(filesize($filepath) / 1024 / 1024, 2);
        $this->info("Backup created: {$filename} ({$size} MB)");

        // Keep only last 60 backups (~20 days at 8-hour intervals)
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
