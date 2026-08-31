<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\SendingGuard;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\WhatsAppSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * إرسالُ رسالةٍ صادرة عبر مزوّد واتساب.
 *
 * ═══ لماذا لا يُرسَل من المتحكّم ═══
 *
 * نداءُ Meta قد يستغرق ثوانيَ أو يسقط بمهلة. والإرسالُ داخل الطلب
 * يجمّد الصفحة أمام المحامي، ويجعل انقطاعَ شبكةٍ لحظياً ضياعاً
 * للرسالة. هنا: تُحفظ الرسالة أوّلاً في الخيط بحالة «في الانتظار»،
 * ثمّ تُرسَل، ثمّ تُحدَّث حالتُها — فما كُتب لا يضيع أبداً.
 *
 * والإعادةُ تفرّق بين ما يزول وما لا يزول: رقمٌ ليس على واتساب لا
 * تُصلحه ثلاثُ محاولات، وازدحامٌ لحظيّ تكفيه واحدة.
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /**
     * ‏$deferrals باقٍ للتوافق مع مهامَّ قديمةٍ في الطابور وقت النشر —
     * لم يعد يُقرأ: الانتظارُ صار يُكتب في الرسالة لا في المهمّة.
     */
    public function __construct(public int $messageId, public int $deferrals = 0)
    {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(): void
    {
        $message = WhatsAppMessage::with('conversation.contact')->find($this->messageId);

        if (!$message) {
            return;
        }

        // ملاحظةٌ داخلية لا تُرسَل — الرفضُ صريحٌ هنا لا اتّكالاً على
        // أنّ أحداً لن يدفعها إلى الطابور
        if ($message->is_internal) {
            return;
        }

        // أُرسلت من قبل: إعادةُ محاولةٍ بعد نجاحٍ لا تُرسل مرّتين
        if ($message->status !== WhatsAppMessage::STATUS_QUEUED) {
            return;
        }

        $conversation = $message->conversation;
        $contact = $conversation?->contact;

        if (!$conversation || !$contact) {
            $this->fail($message, 'المحادثة غير موجودة.');

            return;
        }

        $provider = WhatsAppManager::provider();

        if (!$provider) {
            $this->fail($message, 'لم يُربط رقم واتساب لهذا المكتب.');

            return;
        }

        // ═══ لا يُراسَل إلا موكّلٌ في سجلّ المكتب ═══
        //
        // الإرسالُ إلى من لم يراسلك قطّ ولا علاقةَ له بك أظهرُ ما
        // يُرصد، وأسرعُ ما يُبلَّغ عنه. ومكتبُ المحاماة لا يحتاجه
        // أصلاً: مخاطبوه موكّلوه.
        //
        // ومن راسل المكتبَ بنفسه مستثنى: الردُّ على من بدأ المحادثة
        // ليس اقتحاماً، وهو ما يفعله أيُّ هاتفٍ عادي.
        if (SendingGuard::clientsOnly()
            && $contact->client_id === null
            && $conversation->last_inbound_at === null) {
            $this->fail($message, 'الإرسال مقصورٌ على الموكّلين المسجَّلين ومن راسل المكتب.');

            return;
        }

        // ═══ الإيقاع ═══
        //
        // مهلةٌ متفاوتةٌ بين رسالةٍ وأخرى، وسقفٌ في الساعة واليوم،
        // وصمتٌ في ساعات النوم. والتأجيلُ لا الإسقاط: رسالةُ الموكّل
        // لا تُلغى لأنّ الساعة متأخّرة — تنتظر الصباح.
        // ═══ الانتظارُ يُكتب في الصفّ لا يُدفع في الطابور ═══
        //
        // كان التأجيلُ يعيد دفعَ مهمّةٍ بمهلة. والمهلةُ لم تُحترم في
        // التشغيل الفعلي، فدارت المهمّةُ أربعاً وعشرين مرّةً في دقائق
        // ثمّ أُعلنت «فشلاً» — ورسالةُ موكّلٍ ضاعت لأنّ الساعة كانت
        // الثالثة فجراً، وكان يكفي أن تنتظر الصباح.
        //
        // فصار الموعدُ يُكتب في الرسالة نفسها، وتبقى «في الانتظار»،
        // ويلتقطها أمرُ الاستدراك المجدوَل حين يحين. لا دورةَ تدور،
        // ولا اعتمادَ على سلوك مُشغّل الطابور.
        $delay = SendingGuard::delayFor($message);

        if ($delay !== null) {
            $message->forceFill(['hold_until' => now()->addSeconds($delay)])->save();

            return;
        }

        // بلغ موعدُها: يُمحى الحجز كي لا يبقى أثرٌ يربك القراءة
        if ($message->hold_until !== null) {
            $message->forceFill(['hold_until' => null])->save();
        }

        $to = $contact->wa_id;

        $result = match ($message->type) {
            'template' => $provider->sendTemplate(
                $to,
                (string) $message->template_name,
                $this->templateLanguage($message),
                $this->templateParams($message),
            ),
            'image', 'document', 'audio', 'video' => $provider->sendMedia(
                $to,
                $message->type,
                (string) $message->media_id,
                $message->body,
                $message->media_name,
            ),
            default => $provider->sendText($to, (string) $message->body),
        };

        if ($result->ok) {
            $message->forceFill([
                'wamid' => $result->wamid,
                'status' => WhatsAppMessage::STATUS_SENT,
                'sent_at' => now(),
                'error_code' => null,
                'error_title' => null,
            ])->save();

            return;
        }

        if ($result->retryable && $this->attempts() < $this->tries) {
            // لا تُكتب الحالةُ «فشل» بعد: المحاولةُ التالية قد تنجح،
            // ورؤيةُ المحامي «فشل» ثم «أُرسلت» تُفقده الثقة بالشاشة
            Log::warning('WhatsApp send retryable failure: ' . $result->errorTitle);

            $this->release($this->backoff()[$this->attempts() - 1] ?? 600);

            return;
        }

        $this->fail($message, (string) $result->errorTitle, $result->errorCode);
    }

    /** فشلٌ نهائي — يُكتب في الرسالة ويُسجَّل في حالة الخدمة. */
    protected function fail(WhatsAppMessage $message, string $reason, ?string $code = null): void
    {
        $message->forceFill([
            'status' => WhatsAppMessage::STATUS_FAILED,
            'error_code' => $code,
            'error_title' => mb_substr($reason, 0, 190),
        ])->save();

        WhatsAppSettings::recordError($reason);

        if ($message->sent_by !== null) {
            \App\Support\Notify::send(
                userId: $message->sent_by,
                titleKey: 'app.notif_wa_failed_title',
                messageKey: 'app.notif_wa_failed_body',
                params: ['reason' => $reason],
                type: 'error',
            );
        }
    }

    protected function templateParams(WhatsAppMessage $message): array
    {
        $decoded = json_decode((string) $message->body, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    protected function templateLanguage(WhatsAppMessage $message): string
    {
        $template = \App\Models\WhatsAppTemplate::where('name', $message->template_name)->first();

        return (string) ($template?->language ?: 'ar');
    }
}
