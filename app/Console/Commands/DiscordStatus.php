<?php

namespace App\Console\Commands;

use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use App\Services\DiscordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DiscordStatus extends Command
{
    protected $signature = 'discord:status';
    protected $description = 'Send server status to Discord webhook';

    public function handle(DiscordService $discord): int
    {
        $laravelRunning = true;
        $responseTime = 0;

        try {
            $start = microtime(true);
            Artisan::call('about', [], $output = new \Symfony\Component\Console\Output\BufferedOutput);
            $responseTime = round((microtime(true) - $start) * 1000);
        } catch (\Exception $e) {
            $laravelRunning = false;
        }

        $memoryUsed = round(memory_get_usage(true) / 1024 / 1024);
        $memoryTotal = round(memory_get_peak_usage(true) / 1024 / 1024);
        if ($memoryTotal < 512) $memoryTotal = 512;

        $diskPath = getenv('SystemDrive') ?: 'C:';
        $diskFree = @round(disk_free_space($diskPath) / 1073741824, 1) ?: 0;
        $diskTotal = @round(disk_total_space($diskPath) / 1073741824, 1) ?: 1;
        $diskUsed = round($diskTotal - $diskFree, 1);

        $dbPath = database_path('database.sqlite');
        $dbSize = file_exists($dbPath) ? round(filesize($dbPath) / 1024 / 1024, 2) : 0;

        $backupDir = storage_path('app/backups');
        $backupFiles = is_dir($backupDir) ? glob($backupDir . '/*.zip') : [];
        $backupCount = count($backupFiles);
        $backupSize = 0;
        foreach ($backupFiles as $f) {
            $backupSize += filesize($f);
        }
        $backupSize = round($backupSize / 1024 / 1024, 1);

        $loadAvg = function_exists('sys_getloadavg') ? (float) sys_getloadavg()[0] : 0.0;

        $stats = [
            'uptime' => $loadAvg,
            'response_time' => $responseTime,
            'memory_used' => $memoryUsed,
            'memory_total' => $memoryTotal,
            'disk_used' => $diskUsed,
            'disk_total' => $diskTotal,
            'db_size' => $dbSize,
            'backup_count' => $backupCount,
            'backup_size' => $backupSize,
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'total_cases' => LegalCase::count(),
            'active_cases' => LegalCase::where('status', 'active')->count(),
            'total_tasks' => Task::count(),
            'pending_tasks' => Task::where('status', '!=', 'completed')->count(),
            'laravel_running' => $laravelRunning,
        ];

        $discord->serverStatus($stats);
        $this->info('Status sent to Discord');
        return 0;
    }
}
