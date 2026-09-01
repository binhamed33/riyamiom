<?php

namespace App\Jobs;

use App\Models\ClientNotification;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Services\ClientPortal\PortalLinks;
use App\Services\WhatsApp\InboxService;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\OfficeBrand;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * إيصالُ إشعارٍ إلى واتساب الموكّل — تنبيهٌ ورابط، لا تفاصيل.
 *
 * ═══ ما لا يُكتب في الرسالة ═══
 *
 * لا وقائعَ، ولا اسمَ خصم، ولا نصَّ مذكّرة، ولا مبلغَ فاتورة. رسالةُ
 * واتساب تصل إلى هاتفٍ قد يقرؤه غيرُ صاحبه، وتبقى في نسخِه الاحتياطية
 * وفي إشعار الشاشة المقفلة. فالتفاصيلُ خلف بابٍ يُفتح باسم صاحبه،
 * والرسالةُ تقول: «جدَّ شيءٌ، وهذا بابُه».
 *
 * ورقمُ القضية يُذكر لأنّه لا يفشي شيئاً وحده، وبه يعرف الموكّل أيَّ
 * ملفٍّ يعنيه إن كان له ملفّان.
 */
class SendClientNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $notificationId)
    {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(InboxService $inbox): void
    {
        $notification = ClientNotification::with('client', 'case')->find($this->notificationId);

        if (!$notification || $notification->notified_at !== null) {
            return; // عولج من قبل — إعادةُ مهمّةٍ لا تُرسل ثانيةً
        }

        $client = $notification->client;

        if (!$client) {
            $this->skip($notification, 'لا موكّل مرتبط');

            return;
        }

        if (!WhatsAppManager::isConnected()) {
            $this->skip($notification, 'واتساب غير مربوط — الإشعار في البوابة');

            return;
        }

        $waId = WhatsAppContact::normalizeWaId((string) $client->phone);

        // تسعُ خاناتٍ ليست رقماً دولياً: هي رقمٌ محلّيٌّ زادت فيه خانة،
        // وواتساب يقرأ أوّلَ ثلاثٍ منه مفتاحَ دولة — فتذهب الرسالةُ إلى
        // بلدٍ آخر ويُقال «تمّ». وردُّها هنا بسببٍ مكتوبٍ خيرٌ من
        // إرسالها إلى مجهول.
        if (! WhatsAppContact::isSendable($waId)) {
            $this->skip($notification, $waId === ''
                ? 'الموكّل بلا رقم'
                : 'رقمُ الموكّل ' . mb_strlen($waId) . ' خاناتٍ — لا يصلح رقماً دولياً');

            return;
        }

        $contact = WhatsAppContact::firstOrCreate(['wa_id' => $waId], ['client_id' => $client->id]);

        // الرفضُ الصريح يتقدّم على كلّ إعداد: شرطُ المزوّد وأدبُ
        // المهنة معاً، ومخالفتُه تُبلَّغ فيهبط تقييم رقم المكتب
        if (!$contact->acceptsNotifications()) {
            $this->skip($notification, 'الموكّل طلب إيقاف المراسلة');

            return;
        }

        if ($contact->client_id === null) {
            $contact->forceFill(['client_id' => $client->id])->save();
        }

        $conversation = WhatsAppConversation::firstOrCreate(
            ['contact_id' => $contact->id],
            ['status' => WhatsAppConversation::STATUS_OPEN, 'unread_count' => 0]
        );

        if ($conversation->case_id === null && $notification->case_id) {
            $conversation->forceFill(['case_id' => $notification->case_id])->save();
        }

        // الرابطُ يُنشأ الآن لا عند قيد الإشعار: مدّتُه تبدأ من وصول
        // الرسالة، ولو أُنشئ مبكّراً لضاع نصفُ عمره في الطابور
        $link = PortalLinks::for(
            $client,
            (string) $notification->target,
            $notification->target_id ? (int) $notification->target_id : null,
            $notification,
        );

        $message = $inbox->queueOutgoing($conversation, 'text', $this->compose($notification, $link));

        SendWhatsAppMessage::dispatch($message->id);

        $notification->forceFill([
            'notified_at' => now(),
            'channel_state' => ClientNotification::QUEUED,
            'channel_reason' => null,
        ])->save();
    }

    /**
     * نصُّ الرسالة — سطرُ ترحيبٍ، وسببٌ، ورابط.
     *
     * والاسمُ الأوّل وحده: الاسمُ الرباعي في إشعار شاشةٍ مقفلة يعرّف
     * صاحبَ القضية لمن يمرّ بالهاتف.
     */
    protected function compose(ClientNotification $notification, string $link): string
    {
        $client = $notification->client;
        $first = trim((string) strtok(trim((string) $client?->name), ' '));
        $office = OfficeBrand::name();

        $lines = array_filter([
            $first !== '' ? 'السلام عليكم ' . $first . '،' : 'السلام عليكم،',
            '',
            $notification->title,
            $notification->body ?: null,
            '',
            'للاطّلاع على التفاصيل من بوابة الموكّل:',
            $link,
            '',
            '— ' . $office,
        ], static fn ($line): bool => $line !== null);

        return implode("\n", $lines);
    }

    protected function skip(ClientNotification $notification, string $reason): void
    {
        // ‏notified_at يُختم في التخطّي أيضاً: بلا ذلك تُعاد المهمّة
        // كلَّ مرّةٍ يُستدرك فيها الطابور، فيمتلئ السجلّ بإخفاقٍ
        // لن يزول — وسببُه قرارٌ لا عطل.
        $notification->forceFill([
            'notified_at' => now(),
            'channel_state' => ClientNotification::SKIPPED,
            'channel_reason' => mb_substr($reason, 0, 190),
        ])->save();

        Log::info('Client notification not sent (' . $notification->id . '): ' . $reason);
    }
}
