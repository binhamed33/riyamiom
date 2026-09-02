<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Traits\AuditLoggable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class BackupController extends Controller
{
    use AuditLoggable;

    private const BACKUP_PATTERN = '/^(backup|auto)-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/';

    private function getDbConfig(): array
    {
        return [
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => config('database.connections.mysql.database'),
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
        ];
    }

    private function getMysqlPaths(): array
    {
        $isWin = PHP_OS_FAMILY === 'Windows';
        $mysqldump = 'mysqldump';
        $mysql = 'mysql';

        if ($isWin) {
            $paths = [
                'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin',
                'C:\\xampp\\mysql\\bin',
                'C:\\wamp64\\bin\\mysql\\mysql8.4\\bin',
            ];
            foreach ($paths as $path) {
                $testDump = $path . '\\mysqldump.exe';
                $testMysql = $path . '\\mysql.exe';
                if (file_exists($testDump) && $mysqldump === 'mysqldump') {
                    $mysqldump = $testDump;
                }
                if (file_exists($testMysql) && $mysql === 'mysql') {
                    $mysql = $testMysql;
                }
            }
            $mysqldump = '"' . $mysqldump . '"';
            $mysql = '"' . $mysql . '"';
        } else {
            // Linux — prefer mariadb-dump if available
            $dumpCheck = trim(shell_exec('which mariadb-dump 2>/dev/null') ?: '');
            $cliCheck = trim(shell_exec('which mariadb 2>/dev/null') ?: '');
            $mysqldump = $dumpCheck ?: trim(shell_exec('which mysqldump 2>/dev/null') ?: 'mysqldump');
            $mysql = $cliCheck ?: trim(shell_exec('which mysql 2>/dev/null') ?: 'mysql');
        }

        return [$mysqldump, $mysql];
    }

    private function createMyCnf(array $db): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'my_') . '.cnf';
        $content = "[client]\nhost=\"{$db['host']}\"\nport={$db['port']}\nuser=\"{$db['username']}\"\npassword=\"{$db['password']}\"\n";
        file_put_contents($tmp, $content);
        return $tmp;
    }

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
        
        // manual- لا backup-: النسخة اليدوية ملكُ من صنعها — التسمية
        // المميزة تعصمها من أي تنظيف آلي يطال بقايا النظام القديم
        $filename = 'manual-' . date('Y-m-d-His') . '.zip';
        $filepath = $backupDir . '/' . $filename;
        
        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return redirect()->route('backup.index')->with('error', 'فشل إنشاء النسخة الاحتياطية');
        }

        $db = $this->getDbConfig();
        [$mysqldump, $_] = $this->getMysqlPaths();
        $myCnf = $this->createMyCnf($db);
        $sqlFile = tempnam(sys_get_temp_dir(), 'db_') . '.sql';

        $cmd = $mysqldump
             . ' --defaults-extra-file=' . escapeshellarg($myCnf)
             . ' --routines --events --single-transaction --quick'
             . ' --result-file=' . escapeshellarg($sqlFile)
             . ' ' . escapeshellarg($db['database']);

        $output = [];
        $returnVar = 0;
        exec($cmd, $output, $returnVar);

        @unlink($myCnf);

        if ($returnVar !== 0 || !file_exists($sqlFile) || filesize($sqlFile) === 0) {
            @unlink($sqlFile);
            $zip->close();
            @unlink($filepath);
            Log::error('mysqldump failed', ['output' => $output, 'return' => $returnVar]);
            return redirect()->route('backup.index')->with('error', 'فشل تصدير قاعدة البيانات');
        }

        $zip->addFile($sqlFile, 'database/database.sql');

        $storagePath = storage_path('app/private');
        if (is_dir($storagePath)) {
            $this->addDirectoryToZip($zip, $storagePath, 'storage/private');
        }

        $storagePublic = storage_path('app/public');
        if (is_dir($storagePublic)) {
            $this->addDirectoryToZip($zip, $storagePublic, 'storage/public');
        }
        
        $zip->close();
        @unlink($sqlFile);

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

    public function uploadRestore(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:zip|max:512000',
        ]);

        $uploaded = $request->file('backup_file');
        $originalName = $uploaded->getClientOriginalName();

        $zip = new ZipArchive();
        if ($zip->open($uploaded->getRealPath()) !== true) {
            return redirect()->route('backup.index')->with('error', 'فشل فتح الملف — تأكد أنه ملف zip صحيح');
        }

        return $this->processRestore($zip, $originalName);
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

        return $this->processRestore($zip, $filename);
    }

    private function processRestore(ZipArchive $zip, string $displayName)
    {
        $restoreDir = storage_path('app/restore_temp');
        if (!is_dir($restoreDir)) {
            mkdir($restoreDir, 0755, true);
        }

        $foundSql = null;
        $fileCount = $zip->numFiles;
        for ($i = 0; $i < $fileCount; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with($name, '.sql')) {
                $foundSql = $name;
                break;
            }
        }

        if (!$foundSql) {
            $files = [];
            for ($i = 0; $i < min($fileCount, 50); $i++) {
                $files[] = $zip->getNameIndex($i);
            }
            $zip->close();

            $hasOldDb = count(array_filter($files, fn($f) => str_contains($f, '.sqlite'))) > 0;
            $msg = $hasOldDb
                ? 'هذا الملف من النسخة الاحتياطية القديمة (SQLite). النظام الآن يستخدم MySQL ولا يمكن استيراد هذا الملف مباشرة.'
                : 'ملف zip غير صالح — لا يوجد ملف قاعدة بيانات (database.sql) داخل الملف. الملفات الموجودة: ' . implode('، ', $files);

            $this->removeDirectory($restoreDir);
            return redirect()->route('backup.index')->with('error', $msg);
        }

        $zip->extractTo($restoreDir);
        $zip->close();

        $sqlFile = $restoreDir . '/' . $foundSql;
        if (!file_exists($sqlFile)) {
            $this->removeDirectory($restoreDir);
            return redirect()->route('backup.index')->with('error', 'فشل استخراج ملف قاعدة البيانات من النسخة الاحتياطية');
        }

        if (!str_starts_with($foundSql, 'database/')) {
            $properDir = $restoreDir . '/database';
            if (!is_dir($properDir)) {
                mkdir($properDir, 0755, true);
            }
            $newPath = $properDir . '/database.sql';
            rename($sqlFile, $newPath);
            $sqlFile = $newPath;
        }

        // ═══ الحارسُ الأوّل: الملفُّ يُفحص قبل أن يُصبّ ═══
        //
        // الاستعادةُ كانت تصبّ أيَّ SQL كما هو. ومن يملك الزرَّ يملك —
        // بملفٍّ يكتبه بيده — أن يرفع نفسَه مطوّراً أو يزرع أمراً لا
        // تحويه نسخةٌ احتياطيةٌ قطّ. (انظر RestoreGuard)
        $sqlText = (string) file_get_contents($sqlFile);
        if (($refusal = \App\Support\RestoreGuard::inspect($sqlText)) !== null) {
            $this->removeDirectory($restoreDir);
            Log::warning('backup restore refused', ['reason' => $refusal, 'by' => auth()->id(), 'file' => $displayName]);

            return redirect()->route('backup.index')->with('error', $refusal);
        }

        // والأدوارُ تُلتقط قبل الصبّ لتُعاد بعده — الحارسُ الثاني
        $rolesBefore = \App\Support\RestoreGuard::snapshotRoles();

        $db = $this->getDbConfig();
        [$_, $mysql] = $this->getMysqlPaths();
        $myCnf = $this->createMyCnf($db);

        // --local-infile=0: يمنع LOAD DATA LOCAL من قراءة ملفّات الخادم
        // عبر العميل مهما قال الملفّ. و--skip-... لا تكفي وحدَها.
        $cmd = $mysql
             . ' --defaults-extra-file=' . escapeshellarg($myCnf)
             . ' --local-infile=0'
             . ' ' . escapeshellarg($db['database']);

        $process = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        $returnVar = -1;
        if (is_resource($process)) {
            fwrite($pipes[0], $sqlText);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $returnVar = proc_close($process);
        }

        @unlink($myCnf);
        unset($sqlText);

        // ═══ الحارسُ الثاني: الأدوارُ تعود ═══
        //
        // مهما كُتب في الملفّ، مَن صار «مطوّراً» ولم يكن يعود إلى ما
        // كان. يجري في الحالتين — نجح الصبُّ أو فشل في منتصفه — لأنّ
        // فشلاً بعد نصف الملفّ قد يكون نفّذ السطرَ المقصود.
        $reasserted = \App\Support\RestoreGuard::reassertRoles($rolesBefore);
        if ($reasserted > 0) {
            Log::warning('backup restore tried to mint a developer', ['reverted' => $reasserted, 'by' => auth()->id(), 'file' => $displayName]);
        }

        if ($returnVar !== 0) {
            $this->removeDirectory($restoreDir);
            Log::error('mysql restore failed', ['return' => $returnVar, 'stderr' => $stderr ?? '']);
            return redirect()->route('backup.index')->with('error', 'فشل استيراد قاعدة البيانات — تأكد أن الملف يحتوي على تصدير MySQL صحيح');
        }

        $privateDir = $restoreDir . '/storage/private';
        if (is_dir($privateDir)) {
            $targetPrivate = storage_path('app/private');
            if (!is_dir($targetPrivate)) {
                mkdir($targetPrivate, 0755, true);
            }
            $this->mergeDirectory($privateDir, $targetPrivate);
        }

        $publicDir = $restoreDir . '/storage/public';
        if (is_dir($publicDir)) {
            $targetPublic = storage_path('app/public');
            if (!is_dir($targetPublic)) {
                mkdir($targetPublic, 0755, true);
            }
            $this->mergeDirectory($publicDir, $targetPublic);
        }

        $this->removeDirectory($restoreDir);

        $this->logAudit(
            AuditLog::ACTION_UPDATE,
            null,
            null,
            null,
            ['action' => 'backup_restored', 'filename' => $displayName]
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
        if (!is_dir($directory)) {
            return;
        }
        $files = glob($directory . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            } elseif (is_dir($file)) {
                $this->removeDirectory($file);
            }
        }
        @rmdir($directory);
    }

    private function mergeDirectory(string $source, string $dest): void
    {
        $files = glob($source . '/*');
        foreach ($files as $file) {
            $basename = basename($file);
            if (is_file($file)) {
                @copy($file, $dest . '/' . $basename);
            } elseif (is_dir($file)) {
                $subDest = $dest . '/' . $basename;
                if (!is_dir($subDest)) {
                    mkdir($subDest, 0755, true);
                }
                $this->mergeDirectory($file, $subDest);
            }
        }
    }
}
