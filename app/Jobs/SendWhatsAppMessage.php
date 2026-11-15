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

        // نبضُ حياةٍ على الصفّ في كلّ محاولة: كنسُ الضائع يعدّ الصمتَ من آخر
        // لمسة — فمهمّةٌ في مهلة إعادة المحاولة (أطولُها عشر دقائق)، أو تُعاد بعد
        // استثناء، لا تُدفع لها نسخةٌ موازيةٌ بمهلةٍ جديدة كلَّ خمس دقائق
        $message->touch();

        $conversation = $message->conversation;
        $contact = $conversation?->contact;

        if (!$conversation || !$contact) {
            $this->fail($message, 'المحادثة غير موجودة.');

            return;
        }

        // الرفضُ يُفحص عند الإرسال لا عند القيد وحده: رسالةٌ محجوزةٌ لليل أو
        // أعاد الكنسُ دفعَها تخرج بعد ساعات — وقد كتب الموكّلُ «إيقاف» بينهما
        if (!$contact->acceptsNotifications()) {
            // قرارُ الموكّل لا عطلٌ في الخدمة: لا يُكتب خطأً في حالة واتساب
            $this->fail($message, 'الموكّل طلب إيقاف المراسلة.', 'opted_out', serviceError: false);

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
        //
        // ═══ ويدُ الإنسان مستثناةٌ كذلك ═══
        //
        // الحارسُ وُضع للإرسال الآليّ إلى من لا علاقةَ له بالمكتب.
        // أمّا موظّفٌ يكتب بيده — رداً في الوارد، أو تأكيدَ موعدٍ
        // لزائرٍ أعطاه رقمَه قبل قليل — فذاك اتصالٌ طلبه صاحبُه.
        // ومنعُه كان يعني أنّ موعدَ الاستشارة الأولى لا يصل صاحبَه
        // أبداً، لأنّه لم يصر موكّلاً بعد.
        //
        // والسقوفُ باقيةٌ عليه: خمسَ عشرةَ في الساعة ومئةٌ في اليوم
        // تُمسك اليدَ التي تتحوّل قناةَ بثّ.
        if (SendingGuard::clientsOnly()
            && $message->sent_by === null
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

        // سؤالٌ أخيرٌ للقاعدة قبل المزوّد: مسحَ المديرُ المحادثةَ بعد أن حُمّلت
        // الرسالة (ConversationEraser يجعلها «فاشلة» أثراً بلا نصّ) — فلا تخرج
        if (! WhatsAppMessage::whereKey($message->id)
            ->where('status', WhatsAppMessage::STATUS_QUEUED)
            ->where('type', '!=', WhatsAppMessage::TYPE_CLEARED)
            ->exists()) {
            return;
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

    /**
     * دفعُ الرسائل المحجوزة التي حان موعدُها — كلَّ دقيقة.
     *
     * المهلةُ بين رسالتين خمسَ عشرةَ ثانية، وكان لا يُفرج عن المحجوز إلا الكنسُ
     * كلَّ خمس دقائق: إشعاراتٌ صدرت معاً (رسمٌ ثمّ فاتورة) تصل بفارق خمس دقائق
     * بين كلّ واحدةٍ والتي تليها.
     */
    public static function releaseDue(int $cap = 200): int
    {
        $due = WhatsAppMessage::query()
            ->where('status', WhatsAppMessage::STATUS_QUEUED)
            ->whereNotNull('hold_until')
            ->where('hold_until', '<=', now())
            ->orderBy('id')
            ->limit($cap)
            ->pluck('id');

        foreach ($due as $id) {
            self::dispatch((int) $id);
        }

        return $due->count();
    }

    /** فشلٌ نهائي — يُكتب في الرسالة ويُسجَّل في حالة الخدمة. */
    protected function fail(WhatsAppMessage $message, string $reason, ?string $code = null, bool $serviceError = true): void
    {
        $message->forceFill([
            'status' => WhatsAppMessage::STATUS_FAILED,
            'error_code' => $code,
            'error_title' => mb_substr($reason, 0, 190),
        ])->save();

        if ($serviceError) {
            WhatsAppSettings::recordError($reason);
        }

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
