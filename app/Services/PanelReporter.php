<?php

namespace App\Services;

use App\Models\Suggestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * قناة من المكتب إلى لوحة مُداوَلة.
 *
 * خامدة تماماً ما لم تُضبط PANEL_INGEST_URL و PANEL_INGEST_TOKEN: مكتب
 * لم يُضبط له شيء يعمل كما كان بالضبط، بلا نداء ولا تأخير ولا تغيّر سلوك.
 *
 * وأياً كانت النتيجة فهي لا تُفشل حفظ الاقتراح: الموظف كتب اقتراحه
 * وحُفظ عنده، وتعذّر إبلاغ اللوحة شأن تشغيلي لا شأن له به.
 */
class PanelReporter
{
    public static function configured(): bool
    {
        return (bool) config('panel.ingest_url') && (bool) config('panel.ingest_token');
    }

    public static function sendSuggestion(Suggestion $suggestion): bool
    {
        if (!self::configured()) {
            return false;
        }

        $context = is_array($suggestion->context) ? $suggestion->context : [];

        try {
            $response = Http::timeout((int) config('panel.ingest_timeout', 8))
                ->withHeaders(['X-Mudawala-Token' => config('panel.ingest_token')])
                ->acceptJson()
                ->post(rtrim((string) config('panel.ingest_url'), '/') . '/ingest/suggestions', [
                    'remote_id' => $suggestion->id,
                    'title' => $suggestion->title ?: mb_substr($suggestion->content, 0, 60),
                    'content' => $suggestion->content,
                    'user_name' => data_get($context, 'user.name'),
                    'user_email' => data_get($context, 'user.email'),
                    'user_role' => data_get($context, 'user.role_label') ?: data_get($context, 'user.role'),
                    'remote_user_id' => data_get($context, 'user.id'),
                    'page' => data_get($context, 'origin.page'),
                    'device' => self::deviceLine($context),
                    'submitted_at' => data_get($context, 'submitted_at'),
                ]);

            if ($response->successful()) {
                return true;
            }

            // لا نسجّل جسم الرد — قد يحمل تفاصيل لا داعي لها في سجلّ المكتب
            Log::warning('PanelReporter: ingest rejected', ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::warning('PanelReporter: ingest unreachable — ' . $e->getMessage());
        }

        return false;
    }

    /**
     * نبضة دورية: تُعلم اللوحة أن المكتب حيّ، وتحمل ما لا تستطيع اللوحة
     * معرفته بنفسها — عدد المستخدمين وحالة الذكاء الاصطناعي والإصدار.
     * لا تحمل أي بيانات عمل: لا قضايا ولا عملاء ولا مستندات.
     */
    public static function heartbeat(): bool
    {
        if (!self::configured()) {
            return false;
        }

        try {
            $response = Http::timeout((int) config('panel.ingest_timeout', 8))
                ->withHeaders(['X-Mudawala-Token' => config('panel.ingest_token')])
                ->acceptJson()
                ->post(rtrim((string) config('panel.ingest_url'), '/') . '/ingest/heartbeat', [
                    'users_count' => \App\Models\User::query()->where('is_active', true)->count(),
                    'ai_enabled' => self::aiEnabled(),
                    'app_version' => (string) config('app.version', ''),
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('PanelReporter: heartbeat unreachable — ' . $e->getMessage());

            return false;
        }
    }

    private static function aiEnabled(): bool
    {
        try {
            return (bool) \App\Models\Setting::get(\App\Support\AiSettings::KEY_API_KEY);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function deviceLine(array $context): ?string
    {
        $device = data_get($context, 'device', []);

        if (!is_array($device) || $device === []) {
            return null;
        }

        return mb_substr(implode(' · ', array_filter($device)), 0, 180);
    }
}
