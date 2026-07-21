<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Traits\AuditLoggable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupController extends Controller
{
    use AuditLoggable;

    private const BACKUP_PATTERN = '/^backup-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/';

    public function index()
    {
        $backups = [];
        $backupDir = storage_path('app/backups');
        if (is_dir($backupDir)) {
            $files = glob($backupDir . '/*.zip');
            foreach ($files as $file) {
                $backups[] = [
                    'name' => basename($file),
                    'size' => round(filesize($file) / 1024 / 1024, 2),
                    'date' => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
            usort($backups, fn($a, $b) => strcmp($b['date'], $a['date']));
        }
        
        return view('settings.backup', compact('backups'));
    }
    
    public function create()
    {
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $filename = 'backup-' . date('Y-m-d-His') . '.zip';
        $filepath = $backupDir . '/' . $filename;
        
        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return redirect()->route('backup.index')->with('error', 'فشل إنشاء النسخة الاحتياطية');
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

        $sizeMb = round(filesize($filepath) / 1024 / 1024, 2);

        $this->logAudit(
            AuditLog::ACTION_CREATE,
            null,
            null,
            null,
            ['action' => 'backup_created', 'filename' => $filename, 'size_mb' => $sizeMb]
        );

        return redirect()->route('backup.index')->with('success', "تم إنشاء النسخة الاحتياطية: {$filename}");
    }
    
    public function download($filename)
    {
        if (!preg_match(self::BACKUP_PATTERN, $filename)) {
            abort(400);
        }
        $filepath = storage_path('app/backups/' . basename($filename));
        if (!file_exists($filepath)) {
            abort(404);
        }
        
        return response()->download($filepath);
    }
    
    public function restore($filename)
    {
        if (!preg_match(self::BACKUP_PATTERN, $filename)) {
            abort(400);
        }
        $filepath = storage_path('app/backups/' . basename($filename));
        if (!file_exists($filepath)) {
            abort(404);
        }
        
        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            return redirect()->route('backup.index')->with('error', 'فشل فتح ملف النسخة الاحتياطية');
        }

        $restoreDir = storage_path('app/restore_temp');
        if (!is_dir($restoreDir)) {
            mkdir($restoreDir, 0755, true);
        }
        $zip->extractTo($restoreDir);
        $zip->close();
        
        $restoredDb = $restoreDir . '/database/database.sqlite';
        $validDb = false;
        if (file_exists($restoredDb)) {
            $handle = fopen($restoredDb, 'rb');
            $header = fread($handle, 16);
            fclose($handle);
            $validDb = (substr($header, 0, 15) === 'SQLite format 3');
        }

        if (!$validDb) {
            $this->removeDirectory($restoreDir);
            return redirect()->route('backup.index')->with('error', 'ملف النسخة الاحتياطية غير صالح');
        }
        
        copy($restoredDb, database_path('database.sqlite'));
        $this->removeDirectory($restoreDir);

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            null,
            null,
            null,
            ['action' => 'backup_restored', 'filename' => $filename]
        );

        return redirect()->route('backup.index')->with('success', 'تم استرجاع النسخة الاحتياطية بنجاح');
    }
    
    public function destroy($filename)
    {
        if (!preg_match(self::BACKUP_PATTERN, $filename)) {
            abort(400);
        }
        $filepath = storage_path('app/backups/' . basename($filename));
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $this->logAudit(
            AuditLog::ACTION_DELETE,
            null,
            null,
            ['filename' => $filename],
            null
        );

        return redirect()->route('backup.index')->with('success', 'تم حذف النسخة الاحتياطية');
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
    
    private function removeDirectory(string $directory): void
    {
        $files = glob($directory . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->removeDirectory($file);
            }
        }
        @rmdir($directory);
    }
}
