<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Services\ClientPortal\PortalOtp;
use Illuminate\Support\Facades\DB;

/**
 * مسحُ محادثات واتساب من الموقع — بطلب مدير المكتب.
 *
 * ═══ لماذا لا يُحذف الصفُّ كلُّه ═══
 *
 * المحادثةُ ليست نصّاً فقط. ثلاثةُ حرّاسٍ يقرؤون صفوفَها:
 *
 *  ١) سقفُ الإرسال وأسبوعُ الإحماء (SendingGuard) يعدّان ما خرج في
 *     الساعة واليوم، ومهلةَ ما بين رسالتين. حذفُ رسائل اليوم يُصفّر العدّاد،
 *     فيخرج بعده ما يفوق السقف — وهو بالضبط ما يحظر واتساب الرقمَ لأجله.
 *  ٢) تذكيرُ الجلسات يسأل «هل ذُكِّر الموكّلُ بهذه الجلسة؟» من الرسائل
 *     ‏(session_id + template_name). حذفُها يعني تذكيراً ثانياً بالجلسة نفسها.
 *  ٣) رفضُ الموكّل للمراسلة («إيقاف») محفوظٌ في جهة الاتصال لا في المحادثة —
 *     فجهةُ الاتصال لا تُمسّ أبداً، وإلا راسلنا من طلب ألّا نراسله.
 *
 * فالمسحُ: كلُّ نصٍّ ومرفقٍ يذهب نهائياً، وكلُّ رسالةٍ لا يحتاجها حارسٌ
 * تُحذف. وما يحتاجه حارسٌ يبقى أثراً بلا محتوى (TYPE_CLEARED) لا يظهر في
 * أيّ شاشة. والمحادثةُ تُخفى (STATUS_CLEARED) وتعود بأوّل رسالةٍ جديدة،
 * فارغةً ممّا سبق.
 *
 * والنسخةُ على هاتف المكتب لا تُمسّ: هذا مسحٌ من الموقع وحده.
 */
class ConversationEraser
{
    /** الكلمةُ التي تُكتب لمسح الكلّ — ضغطةٌ خاطئةٌ لا تمحو صندوقاً كاملاً. */
    public const CONFIRM_WORD = 'مسح';

    /** أسبوعُ الإحماء (٧ أيام) ويومٌ احتياط: أطولُ ما يعدّه SendingGuard. */
    private const GUARD_DAYS = 8;

    /** تذكيرُ الجلسة يُرسل قبلها بأيام: يكفي أثرُ شهرين ليُمنع التكرار. */
    private const REMINDER_DAYS = 60;

    /** @return array{removed: int, kept: int} */
    public function erase(WhatsAppConversation $conversation): array
    {
        return DB::transaction(function () use ($conversation) {
            // قفلٌ أوّلاً: مهمّةُ إرسالٍ تكتب «أُرسلت» على صفٍّ تنتظر حتى يلتزم
            // المسح — فلا يُحذف صفٌّ خرج للتوّ ولا يُعدّ ما لم يُعدّ. والمحادثةُ
            // تُقرأ من القاعدة لا من نسخةٍ حمّلها الطلبُ قبل دقائق (مسحُ الكلّ)
            $fresh = WhatsAppConversation::whereKey($conversation->id)->lockForUpdate()->first();

            if (!$fresh) {
                return ['removed' => 0, 'kept' => 0];
            }

            WhatsAppMessage::where('conversation_id', $fresh->id)->lockForUpdate()->pluck('id');

            // ‏١) ما ينتظر الإرسال لا يُحذف بل يُلغى أثراً: مهمّتُه إن لم تبدأ تجده
            // «فاشلاً» فلا ترسل شيئاً، وإن كانت وسطَ الإرسال كتبت «أُرسلت» على
            // صفٍّ قائمٍ فيعدّه سقفُ الإرسال وحارسُ التذكير. كان الحذفُ هنا يترك
            // رسالةً تصل الموكّلَ بلا أثرٍ يُعدّ — تجاوزٌ للسقف وتذكيرٌ مكرّر
            $queued = WhatsAppMessage::query()
                ->where('conversation_id', $fresh->id)
                ->where('direction', WhatsAppMessage::OUT)
                ->where('status', WhatsAppMessage::STATUS_QUEUED)
                ->pluck('id');

            if ($queued->isNotEmpty()) {
                WhatsAppMessage::whereIn('id', $queued)->update([
                    'status' => WhatsAppMessage::STATUS_FAILED,
                    'error_code' => 'cleared',
                ]);
            }

            // ‏٢) وما خرج حديثاً أو حمل تذكيرَ جلسة: أثرٌ للحرّاس
            $recent = WhatsAppMessage::query()
                ->where('conversation_id', $fresh->id)
                ->where('direction', WhatsAppMessage::OUT)
                ->where(fn ($q) => $q
                    ->where('sent_at', '>=', now()->subDays(self::GUARD_DAYS))
                    ->orWhere(fn ($r) => $r->whereNotNull('session_id')
                        ->where('created_at', '>=', now()->subDays(self::REMINDER_DAYS))))
                ->pluck('id');

            $keep = $queued->merge($recent)->unique()->values();

            if ($keep->isNotEmpty()) {
                WhatsAppMessage::whereIn('id', $keep)->update([
                    'type' => WhatsAppMessage::TYPE_CLEARED,
                    'media_id' => null, 'media_mime' => null, 'media_name' => null, 'media_size' => null,
                    'document_id' => null, 'error_title' => null, 'hold_until' => null,
                ]);

                // النصُّ يذهب — إلا صفَّ رمز البوابة: نصُّه بديلٌ ثابتٌ لا سرَّ فيه،
                // وبه يستثنيه سقفُ الإرسال من عدّه (رموزُ الدخول لا تمرّ بالحارس)
                WhatsAppMessage::whereIn('id', $keep)
                    ->where(fn ($q) => $q->whereNull('body')->orWhere('body', '!=', PortalOtp::LEDGER_BODY))
                    ->update(['body' => null]);
            }

            // ‏٣) نسخةُ الوارد في سجلّ الاستقبال (whatsapp_webhook_events) تحمل النصَّ
            // كاملاً أربعةَ عشر يوماً — «حُذف نهائياً» لا يصدق وهي باقية. يُفرَّغ
            // محتواها ويبقى صفُّها: مفتاحُه يمنع إعادةَ Meta من إحياء ما مُسح
            $wamids = WhatsAppMessage::query()
                ->where('conversation_id', $fresh->id)
                ->whereNotIn('id', $keep)
                ->whereNotNull('wamid')
                ->pluck('wamid');

            foreach ($wamids->chunk(500) as $chunk) {
                WhatsAppWebhookEvent::whereIn('event_key', $chunk->map(fn ($w) => 'msg:' . $w)->all())
                    ->update(['payload' => json_encode(['cleared' => true]), 'processed_at' => DB::raw('COALESCE(processed_at, CURRENT_TIMESTAMP)')]);
            }

            $removed = WhatsAppMessage::query()
                ->where('conversation_id', $fresh->id)
                ->whereNotIn('id', $keep)
                ->delete();

            // بالاستعلام لا بتتبّع التغيير: نسخةٌ قديمةٌ في الذاكرة قيمُها كالجديدة
            // ظاهراً كانت تُسقط عمودَ «غير مقروء» من الحفظ فيبقى على ما وصل بعدها.
            //
            // ولا يُمسّ handoff_at ولا assigned_to ولا last_inbound_at: المسحُ يمحو
            // السجلّ لا القرار. محادثةٌ حُوّلت إلى محامٍ تبقى محوّلة — وإلا ردّ
            // الآليُّ على من طُلب له إنسانٌ بأوّل رسالةٍ بعد المسح.
            $reset = [
                'status' => WhatsAppConversation::STATUS_CLEARED,
                'unread_count' => 0,
                'last_message_at' => null,
            ];
            WhatsAppConversation::whereKey($fresh->id)->update($reset);
            $conversation->forceFill($reset)->syncOriginal();

            return ['removed' => $removed, 'kept' => $keep->count()];
        });
    }
}
