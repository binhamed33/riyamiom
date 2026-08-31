<?php

namespace App\Jobs;

use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\InboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * معالجةُ حدثِ واتساب المقيَّد في الدفتر.
 *
 * ═══ لماذا خارج الطلب ═══
 *
 * تُمهل Meta الردَّ ثوانيَ ثمّ تُعيد الإرسال. وقراءةُ الرسالة وربطُها
 * بموكّلها وسؤالُ الذكاء الاصطناعي تتجاوز تلك المهلة — فتصل النسخةُ
 * الثانية قبل أن تنتهي الأولى. الاستقبالُ يقيّد ويردّ 200، وهذه
 * المهمّة تعمل على مهلها.
 *
 * والحدثُ مقيَّدٌ قبل دفعِها: لو ماتت المهمّة أو تعطّل الطابور بقي
 * الحدث على القرص، ويلتقطه أمرُ الاستدراك المجدوَل.
 */
class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $eventId)
    {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(InboxService $inbox): void
    {
        $event = WhatsAppWebhookEvent::find($this->eventId);

        if (!$event) {
            return; // حُذف بالتقليم — لا شيء يُعالَج
        }

        // عولج من قبل: إعادةُ محاولةٍ بعد نجاحٍ جزئي لا تُنشئ رسالةً ثانية
        if ($event->processed_at !== null) {
            return;
        }

        $data = (array) $event->payload;

        try {
            if ($event->kind === 'status') {
                $inbox->applyStatus((array) ($data['status'] ?? []));
            } else {
                $message = $inbox->ingestIncoming(
                    (array) ($data['message'] ?? []),
                    (array) ($data['contacts'] ?? [])
                );

                if ($message) {
                    // الردُّ الآلي مهمّةٌ مستقلّة: فشلُه لا يُعيد استيعاب
                    // الرسالة من جديد فيُضاعفها في الخيط
                    AnswerWhatsAppMessage::dispatch($message->id);
                }
            }

            $event->markProcessed();
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook processing failed for event ' . $event->id . ': ' . $e->getMessage());
            $event->markFailed($e->getMessage());

            throw $e; // ليُعاد ضمن حدود tries وretryUntil
        }
    }
}
