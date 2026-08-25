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
                    'employee_code' => data_get($context, 'user.code'),
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
                    // الأرقامُ من محاسبة الحدود نفسها لا من عدٍّ موازٍ.
                    //
                    // كانت هذه السطور تعدّ «المستخدمين النشطين»، والحدُّ
                    // يُفرَض على «كلِّ من ليس موكّلاً». فرقمان لكلمةٍ
                    // واحدة: تعرض اللوحةُ أحدهما ويمنع المكتبُ بالآخر،
                    // فيقف صاحب اللوحة أمام «١ من ٥» في مكتبٍ فيه ستّة.
                    'users_count' => \App\Support\PlanLimits::used('users'),
                    'clients_count' => \App\Support\PlanLimits::used('clients'),
                    'cases_count' => \App\Support\PlanLimits::used('cases'),
                    'documents_count' => \App\Support\PlanLimits::used('documents'),
                    'storage_bytes' => \App\Support\PlanLimits::usedStorageBytes(),
                    // وهل يعرف هذا المكتب حدوده أصلاً؟ مكتبٌ لم تصله
                    // يعمل بلا حدّ — وذاك مقصود، لكنّه يجب أن يُرى.
                    'limits_known' => !\App\Support\PlanLimits::unlimited(),
                    'limits_synced_at' => \App\Support\PlanLimits::syncedAt()?->toIso8601String(),
                    'ai_enabled' => self::aiEnabled(),
                    'app_version' => (string) config('app.version', ''),
                    // نبض الأخطاء: عدد ونوع ومسار — بلا نصّ الخطأ، فبيانات
                    // المكتب لا تغادر خادمه (§56)
                    'errors' => \App\Support\ErrorPulse::summary(),
                    // النسخ الاحتياطي: أرقامٌ وتواريخُ وسببٌ منقّى.
                    //
                    // كلُّ مكتبٍ ينسخ نفسه كلَّ ليلة، ولم يكن يعلم بذلك
                    // أحد. فمركزُ النسخ في اللوحة كان يعرض «لا نسخ بعد»
                    // لمكتبٍ ينسخ بانتظام، ومثلَه لمكتبٍ عطبت نسخُه منذ
                    // أسبوع — فلا يُقرأ منه شيء.
                    'backup' => \App\Support\BackupStatus::summary(),
                ]);

            // الردّ يحمل الباقة والحدود نزولاً — هذه هي قناة المزامنة.
            // ترقيةٌ في اللوحة تسري هنا في النبضة التالية بلا لمس المكتب.
            if ($response->successful()) {
                $plan = $response->json('plan');

                if (is_array($plan) && is_array($plan['limits'] ?? null)) {
                    \App\Support\PlanLimits::sync(
                        $plan['key'] ?? null,
                        $plan['name'] ?? null,
                        $plan['limits'],
                    );
                }
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('PanelReporter: heartbeat unreachable — ' . $e->getMessage());

            return false;
        }
    }

    /**
     * طلب ترقية: يُرسل حين يبلغ المكتب حدّه ويضغط مديره «ترقية».
     *
     * إن لم يكن الجسر مربوطاً فلا شيء يُرسَل، ويُقال ذلك للمدير بدل
     * أن يظنّ أنّ طلبه وصل.
     */
    public static function requestUpgrade(?string $by = null, ?string $email = null, ?string $reason = null): bool
    {
        if (!self::configured()) {
            return false;
        }

        try {
            $response = Http::timeout((int) config('panel.ingest_timeout', 8))
                ->withHeaders(['X-Mudawala-Token' => config('panel.ingest_token')])
                ->acceptJson()
                ->post(rtrim((string) config('panel.ingest_url'), '/') . '/ingest/upgrade-request', [
                    'requested_by' => $by,
                    'requested_email' => $email,
                    'reason' => $reason,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('PanelReporter: upgrade request unreachable — ' . $e->getMessage());

            return false;
        }
    }

    /**
     * سحب مصير الاقتراحات من اللوحة: الحالة وردّ المطوّر.
     *
     * سحب لا استقبال: المكتب هو من يبدأ الاتصال، فلا يحتاج منفذاً
     * مفتوحاً ولا مساراً بلا جلسة يستقبل من الخارج. وإن كانت اللوحة
     * بعيدة أو الرمز خاطئاً فالنتيجة مصفوفة فارغة — لا شيء يتغيّر عند
     * الموظف.
     *
     * @param  array<int, int>  $remoteIds
     * @return array<int, array<string, mixed>>
     */
    public static function fetchReplies(array $remoteIds = []): array
    {
        if (!self::configured()) {
            return [];
        }

        try {
            $response = Http::timeout((int) config('panel.ingest_timeout', 8))
                ->withHeaders(['X-Mudawala-Token' => config('panel.ingest_token')])
                ->acceptJson()
                ->post(rtrim((string) config('panel.ingest_url'), '/') . '/ingest/replies', [
                    'remote_ids' => array_values(array_slice(array_map('intval', $remoteIds), 0, 500)),
                ]);

            if (!$response->successful()) {
                Log::warning('PanelReporter: replies rejected', ['status' => $response->status()]);

                return [];
            }

            $replies = $response->json('replies');

            return is_array($replies) ? $replies : [];
        } catch (\Throwable $e) {
            Log::warning('PanelReporter: replies unreachable — ' . $e->getMessage());

            return [];
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
