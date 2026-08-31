<?php

namespace App\Services\ClientPortal;

use App\Jobs\SendClientNotification;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\LegalCase;
use App\Support\ClientEvents;
use Illuminate\Support\Facades\Log;

/**
 * البابُ الواحد: حدثٌ يقع ⇐ إشعارٌ يُقيَّد ⇐ مهمّةٌ تُدفع.
 *
 * ═══ لماذا لا يُرسِل متحكّمٌ رسالةً بيده ═══
 *
 * لأنّ لكلّ إرسالٍ ستّةَ شروط: أشغّل المكتبُ هذا النوع؟ أللموكّل رقم؟
 * أطلب إيقافَ المراسلة؟ أمربوطٌ واتساب أصلاً؟ أسبق أن أُرسل هذا
 * الحدثُ نفسُه؟ وهل الطابور قادرٌ على استقباله؟
 *
 * وتكرارُها في عشرة متحكّمات يعني أنّ التاسع سينسى واحداً — فيصل
 * موكّلاً طلب الإيقاف رسالةٌ، أو يصل الحدثُ الواحد خمسَ مرّات.
 *
 * ═══ والصفُّ يُكتب قبل الإرسال دائماً ═══
 *
 * فواتساب قناةُ تنبيهٍ لا مخزنُ بيانات: الإشعارُ يعيش في البوابة
 * ويُقرأ هناك ولو أخفق الإرسال، ولو لم يكن للمكتب رقمٌ مربوطٌ أصلاً.
 */
class ClientNotifications
{
    /**
     * قيدُ حدثٍ وإحالتُه إلى الطابور.
     *
     * @param array{title: string, body?: ?string, target?: ?string, target_id?: ?int, key: string} $data
     */
    public static function record(string $type, ?Client $client, ?LegalCase $case, array $data): ?ClientNotification
    {
        if (!$client || !ClientEvents::enabled($type)) {
            return null;
        }

        $eventKey = $type . ':' . $data['key'];

        try {
            // ═══ الحارسُ عند القاعدة لا في الشيفرة ═══
            //
            // فحصٌ ثمّ إنشاءٌ يفلت منه سباقُ عمليّتين: كلتاهما تفحص
            // فلا تجد، ثمّ تكتب. والقيدُ الفريد يرفض الثانية مهما
            // تزامنتا — فلا يصل الموكّلَ إشعارٌ مكرّر.
            $notification = ClientNotification::create([
                'client_id' => $client->id,
                'case_id' => $case?->id,
                'type' => $type,
                'title' => mb_substr($data['title'], 0, 190),
                'body' => isset($data['body']) ? mb_substr((string) $data['body'], 0, 500) : null,
                'target' => $data['target'] ?? ClientEvents::target($type),
                'target_id' => $data['target_id'] ?? $case?->id,
                'event_key' => mb_substr($eventKey, 0, 120),
                'channel_state' => ClientNotification::PENDING,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (self::isDuplicate($e)) {
                return null; // رأيناه من قبل — المسارُ الطبيعي لا خطأ
            }

            Log::warning('Client notification not recorded: ' . $e->getMessage());

            return null;
        } catch (\Throwable $e) {
            // جدولٌ غير مهاجَر بعد: لا يُسقط حفظَ القضية نفسها. إنشاءُ
            // قضيّةٍ لا يفشل لأنّ إشعاراً تعذّر.
            Log::warning('Client notification not recorded: ' . $e->getMessage());

            return null;
        }

        try {
            SendClientNotification::dispatch($notification->id);
        } catch (\Throwable $e) {
            // طابورٌ لا يستقبل: الإشعار مقيَّدٌ ويظهر في البوابة،
            // ويلتقطه أمرُ الاستدراك المجدوَل
            Log::error('Client notification dispatch failed: ' . $e->getMessage());
        }

        return $notification;
    }

    private static function isDuplicate(\Illuminate\Database\QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
