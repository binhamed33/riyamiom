<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * صحة المساعد كما تُعرض للإدارة: آخر نجاح، آخر خطأ، وعدّادات الاستخدام.
 *
 * التسجيل لا يُفشل طلباً أبداً — مساعدٌ أجاب ثم تعثّر سطرُ سجلّه
 * يجب أن يبقى مساعداً أجاب.
 */
class AiHealth
{
    public const LAST_SUCCESS = 'ai_last_success_at';
    public const LAST_ERROR = 'ai_last_error';

    public static function record(string $status, string $provider, ?string $model, ?int $durationMs, ?string $errorType): void
    {
        try {
            DB::table('ai_requests')->insert([
                'user_id' => auth()->id(),
                'provider' => $provider,
                'model' => $model !== null ? mb_substr($model, 0, 80) : null,
                'status' => $status,
                'error_type' => $errorType !== null ? mb_substr($errorType, 0, 60) : null,
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]);

            if ($status === 'ok') {
                Setting::set(self::LAST_SUCCESS, now()->toIso8601String(), 'ai');
            } else {
                Setting::set(self::LAST_ERROR, json_encode([
                    'type' => $errorType,
                    'at' => now()->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE), 'ai');
            }
        } catch (\Throwable $e) {
            Log::warning('AiHealth: تعذّر تسجيل الطلب — ' . $e->getMessage());
        }
    }

    /** ملخص للإدارة: الحالة والعدّادات — لا يظهر لغير المخوَّلين. */
    public static function snapshot(): array
    {
        $lastSuccess = Setting::get(self::LAST_SUCCESS);
        $lastErrorRaw = Setting::get(self::LAST_ERROR);
        $lastError = $lastErrorRaw ? json_decode((string) $lastErrorRaw, true) : null;

        $today = now()->startOfDay();
        $counts = [
            'today' => DB::table('ai_requests')->where('created_at', '>=', $today)->count(),
            'today_errors' => DB::table('ai_requests')->where('created_at', '>=', $today)->where('status', 'error')->count(),
            'month' => DB::table('ai_requests')->where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        $status = 'offline';
        if (AiSettings::isConfigured()) {
            $successAt = $lastSuccess ? \Illuminate\Support\Carbon::parse($lastSuccess) : null;
            $errorAt = ($lastError['at'] ?? null) ? \Illuminate\Support\Carbon::parse($lastError['at']) : null;

            $status = match (true) {
                $successAt && (!$errorAt || $successAt->gt($errorAt)) => 'healthy',
                $errorAt && $successAt && $errorAt->gt($successAt) => 'warning',
                $errorAt && !$successAt => 'warning',
                default => 'healthy', // مضبوط ولم يُستعمل بعد
            };
        }

        // زمن الرد رقماً لا إحساساً: متوسط آخر أسبوع وآخر طلب ناجح —
        // «بطيء» بلا رقم شكوى، وبرقمٍ تشخيص
        $avgMs = DB::table('ai_requests')
            ->where('status', 'ok')
            ->where('created_at', '>=', now()->subDays(7))
            ->avg('duration_ms');
        $lastMs = DB::table('ai_requests')
            ->where('status', 'ok')
            ->orderByDesc('id')
            ->value('duration_ms');

        return [
            'status' => $status,
            'provider' => AiSettings::provider(),
            'model' => AiSettings::model(),
            'last_success_at' => $lastSuccess,
            'last_error' => $lastError,
            'counts' => $counts,
            'avg_ms' => $avgMs !== null ? (int) round((float) $avgMs) : null,
            'last_ms' => $lastMs !== null ? (int) $lastMs : null,
        ];
    }
}
