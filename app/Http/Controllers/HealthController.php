<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        $checks['database'] = $this->checkDatabase();
        if (!$checks['database']['ok']) $healthy = false;

        $checks['storage'] = $this->checkStorage();
        if (!$checks['storage']['ok']) $healthy = false;

        $checks['disk'] = $this->checkDiskSpace();
        if (!$checks['disk']['ok']) $healthy = false;

        $checks['app_key'] = $this->checkAppKey();

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'healthy' => $healthy,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    /**
     * ═══ الرسالةُ ثابتةٌ لا رسالةُ PDO ═══
     *
     * المسارُ عامٌّ بلا مصادقة. وكان يردّ نصَّ الخطأ كما هو، فأوّلُ
     * انقطاعٍ أو تدويرِ كلمةِ مرورٍ يسلّم لأيّ زائر اسمَ مستخدم
     * القاعدة وعنوانَ الخادم الداخليَّ ونسخةَ المحرّك:
     *   "Access denied for user 'mudawala_ofc7'@'10.0.0.12'"
     * التفصيلُ إلى السجلّ، والزائرُ يعرف «سليم» أو «غير متاح» فقط.
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['ok' => true, 'message' => 'Database connection OK'];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('health: database unreachable: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'Database unreachable'];
        }
    }

    private function checkStorage(): array
    {
        $dirs = ['app/backups', 'app/private', 'logs'];
        foreach ($dirs as $dir) {
            $path = storage_path($dir);
            if (!is_dir($path)) {
                @mkdir($path, 0700, true);
            }
            if (!is_writable($path)) {
                return ['ok' => false, 'message' => "Directory not writable: {$dir}"];
            }
        }
        return ['ok' => true, 'message' => 'Storage OK'];
    }

    private function checkDiskSpace(): array
    {
        $free = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());
        $percent = round((1 - $free / $total) * 100, 1);

        // النسبةُ إلى السجلّ لا إلى الجواب العامّ
        if ($percent > 95) {
            \Illuminate\Support\Facades\Log::warning("health: disk {$percent}% full");

            return ['ok' => false, 'message' => 'Disk almost full'];
        }

        return ['ok' => true, 'message' => 'Disk OK'];
    }

    private function checkAppKey(): array
    {
        $key = config('app.key');
        if (!$key || $key === 'base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=') {
            return ['ok' => false, 'message' => 'APP_KEY not set or is default'];
        }
        return ['ok' => true, 'message' => 'APP_KEY set'];
    }
}
