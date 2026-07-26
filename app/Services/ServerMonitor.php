<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ServerMonitor
{
    public function gather(): array
    {
        $isWin = PHP_OS_FAMILY === 'Windows';

        return [
            'system'     => $this->systemInfo($isWin),
            'cpu'        => $this->cpuInfo($isWin),
            'memory'     => $this->memoryInfo($isWin),
            'disk'       => $this->diskInfo($isWin),
            'database'   => $this->databaseInfo(),
            'application' => $this->applicationInfo(),
            'laravel'    => $this->laravelInfo(),
            'timestamp'  => now()->toIso8601String(),
        ];
    }

    protected function systemInfo(bool $isWin): array
    {
        $bootTime = null;

        if ($isWin) {
            $host = gethostname() ?: 'unknown';
            $os = $this->runPowershell('(Get-CimInstance Win32_OperatingSystem).Caption');
            $bootRaw = $this->runPowershell('(Get-CimInstance Win32_OperatingSystem).LastBootUpTime');
            if ($bootRaw) {
                $bootTime = \Carbon\Carbon::parse($bootRaw);
            }
        } else {
            $host = trim(shell_exec('hostname') ?: 'unknown');
            $os = trim(shell_exec('cat /etc/os-release 2>/dev/null | grep PRETTY_NAME | head -1 | cut -d= -f2') ?: 'unknown');
            $uptimeSec = (int) (shell_exec('cat /proc/uptime 2>/dev/null | awk \'{print $1}\'') ?: 0);
            if ($uptimeSec) {
                $bootTime = now()->subSeconds($uptimeSec);
            }
        }

        $boot = $bootTime?->format('Y-m-d H:i:s') ?? 'N/A';
        $uptime = $bootTime?->diffForHumans(now(), ['syntax' => CarbonInterface::DIFF_ABSOLUTE]) ?? 'N/A';

        return [
            'hostname'  => $host,
            'os'        => trim($os, '"') ?: 'Windows',
            'uptime'    => $uptime,
            'boot_time' => $boot,
            'php'       => PHP_VERSION,
            'php_exts'  => implode(', ', array_slice(get_loaded_extensions(), 0, 15)),
        ];
    }

    protected function cpuInfo(bool $isWin): array
    {
        if ($isWin) {
            $cores = (int) ($this->runPowershell('(Get-CimInstance Win32_Processor).NumberOfCores') ?: 1);
            $logical = (int) ($this->runPowershell('(Get-CimInstance Win32_Processor).NumberOfLogicalProcessors') ?: 1);
            $load = (int) ($this->runPowershell('(Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average') ?: 0);
            return [
                'cores'   => $cores,
                'logical' => $logical,
                'usage'   => $load . '%',
            ];
        }

        $cores = (int) shell_exec('nproc') ?: 1;
        $load = sys_getloadavg();
        return [
            'cores'   => $cores,
            'load_1'  => round($load[0], 2),
            'load_5'  => round($load[1], 2),
            'load_15' => round($load[2], 2),
            'usage'   => round($load[0] / $cores * 100, 1) . '%',
        ];
    }

    protected function memoryInfo(bool $isWin): array
    {
        if ($isWin) {
            $total = round((float) ($this->runPowershell('(Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory') ?: 0) / 1048576, 1);
            $free  = round((float) ($this->runPowershell('(Get-CimInstance Win32_OperatingSystem).FreePhysicalMemory') ?: 0) / 1024, 1);
            $used  = round($total - $free, 1);
            $pct   = $total > 0 ? round($used / $total * 100, 1) : 0;
            return compact('total', 'used', 'free', 'pct');
        }

        $total = (int) shell_exec('grep MemTotal /proc/meminfo | awk \'{print $2}\'') ?: 1;
        $free  = (int) shell_exec('grep MemAvailable /proc/meminfo | awk \'{print $2}\'') ?: 0;
        $total = round($total / 1024, 1);
        $free  = round($free / 1024, 1);
        $used  = round($total - $free, 1);
        $pct   = round($used / $total * 100, 1);
        return compact('total', 'used', 'free', 'pct');
    }

    protected function diskInfo(bool $isWin): array
    {
        $drives = [];

        if ($isWin) {
            $raw = $this->runPowershell('Get-CimInstance Win32_LogicalDisk -Filter "DriveType=3" | Select-Object DeviceID,Size,FreeSpace | ConvertTo-Json');
            $parsed = json_decode($raw, true);
            if ($parsed) {
                $items = isset($parsed[0]) ? $parsed : [$parsed];
                foreach ($items as $disk) {
                    $total = round((int) ($disk['Size'] ?? 0) / 1073741824, 1);
                    $free  = round((int) ($disk['FreeSpace'] ?? 0) / 1073741824, 1);
                    $used  = round($total - $free, 1);
                    $pct   = $total > 0 ? round($used / $total * 100, 1) : 0;
                    $drives[] = compact('total', 'used', 'free', 'pct') + ['mount' => $disk['DeviceID'] ?? 'N/A'];
                }
            }
        } else {
            $raw = shell_exec('df -B1 --output=target,size,used,avail 2>/dev/null | tail -n +2') ?: '';
            foreach (explode("\n", trim($raw)) as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) === 4 && is_numeric($parts[1])) {
                    $total = round((int) $parts[1] / 1073741824, 1);
                    $used  = round((int) $parts[2] / 1073741824, 1);
                    $free  = round((int) $parts[3] / 1073741824, 1);
                    $pct   = $total > 0 ? round($used / $total * 100, 1) : 0;
                    $drives[] = compact('total', 'used', 'free', 'pct') + ['mount' => $parts[0]];
                }
            }
        }

        return $drives;
    }

    protected function databaseInfo(): array
    {
        try {
            $pdo = DB::connection()->getPdo();
            $connected = true;
            $driver = DB::connection()->getDriverName();
            $size = $this->getDbSize();
            $version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $host = config('database.connections.' . DB::connection()->getName() . '.host', 'N/A');
            $dbName = DB::connection()->getDatabaseName();
            $connections = $this->getDbConnections($driver);
        } catch (\Throwable) {
            $connected = false;
            $driver = 'N/A';
            $size = 'N/A';
            $version = 'N/A';
            $host = 'N/A';
            $dbName = 'N/A';
            $connections = 'N/A';
        }

        return compact('connected', 'driver', 'size', 'version', 'host', 'dbName', 'connections');
    }

    protected function getDbConnections(string $driver): string
    {
        return match ($driver) {
            'mysql' => DB::scalar("SELECT COUNT(*) FROM information_schema.processlist") . ' active',
            'sqlite' => 'N/A',
            default => 'N/A',
        };
    }

    protected function applicationInfo(): array
    {
        $storageOk = is_dir(public_path('storage'));
        $cached = app()->configurationIsCached();
        $routeCached = app()->routesAreCached();
        $totalUsers = \App\Models\User::count();
        $activeUsers = \App\Models\User::where('is_active', true)->count();
        $totalCases = \App\Models\LegalCase::count();
        $activeCases = \App\Models\LegalCase::where('status', 'active')->count();
        $backupCount = count(glob(storage_path('app/backups/*.zip')));
        $lastBackup = $this->getLastBackup();

        return [
            'env'           => app()->environment(),
            'debug'         => config('app.debug'),
            'url'           => config('app.url'),
            'storage_link'  => $storageOk,
            'config_cached' => $cached,
            'route_cached'  => $routeCached,
            'queue_worker'  => $this->checkQueueWorker(),
            'total_users'   => $totalUsers,
            'active_users'  => $activeUsers,
            'total_cases'   => $totalCases,
            'active_cases'  => $activeCases,
            'backup_count'  => $backupCount,
            'last_backup'   => $lastBackup,
        ];
    }

    protected function laravelInfo(): array
    {
        $composer = json_decode(@file_get_contents(base_path('composer.json')), true);
        $deps = collect($composer['require'] ?? [])->map(fn($v, $k) => "$k:$v")->take(8)->implode(', ');

        return [
            'version'   => app()->version(),
            'timezone'  => config('app.timezone'),
            'locale'    => app()->getLocale(),
            'last_migration' => $this->getLastMigration(),
            'cache_driver'   => config('cache.default'),
            'session_driver' => config('session.driver'),
            'dependencies'   => $deps ?: 'N/A',
        ];
    }

    protected function getLastBackup(): string
    {
        $files = glob(storage_path('app/backups/*.zip'));
        if (!$files) return 'N/A';
        $latest = max(array_map('filemtime', $files));
        return date('Y-m-d H:i', $latest);
    }

    protected function getDbSize(): string
    {
        try {
            $driver = DB::connection()->getDriverName();
            return match ($driver) {
                'mysql' => DB::scalar("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) FROM information_schema.tables WHERE table_schema = ?", [DB::connection()->getDatabaseName()]) . ' MB',
                'sqlite' => round(filesize(database_path('database.sqlite')) / 1048576, 1) . ' MB',
                default => 'N/A',
            };
        } catch (\Throwable) {
            return 'N/A';
        }
    }

    protected function getLastMigration(): string
    {
        try {
            return DB::table('migrations')->latest('id')->value('migration') ?? 'none';
        } catch (\Throwable) {
            return 'N/A';
        }
    }

    protected function checkQueueWorker(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $result = shell_exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV /NH 2>NUL') ?: '';
            return str_contains($result, 'artisan') ? 'running' : 'unknown';
        }
        $result = shell_exec('ps aux | grep "queue:work" | grep -v grep 2>/dev/null') ?: '';
        return $result ? 'running' : 'stopped';
    }

    protected function runPowershell(string $cmd): string
    {
        $encoded = base64_encode($cmd);
        return trim(shell_exec("powershell -NoProfile -EncodedCommand $encoded 2>NUL") ?: '') ?: '';
    }
}
