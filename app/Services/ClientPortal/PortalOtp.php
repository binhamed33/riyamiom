<?php

namespace App\Services\ClientPortal;

use App\Models\Client;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\OfficeBrand;
use App\Support\WhatsAppSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * دخولُ البوابة برمزٍ يصل على واتساب — طريقٌ ثانٍ بجانب الهويّة.
 *
 * ═══ لماذا يتخطّى الرمزُ حدودَ الإيقاع ═══
 *
 * حدودُ الأمان (صمتٌ ليلي، مهلةٌ بين رسالتين، سقوف) وُضعت للرسائل
 * التي يبدؤها المكتب. والرمزُ عكسُها تماماً: صاحبُ الهاتف نفسُه
 * طلبه **الآن** ويجلس ينتظره أمام الشاشة — فحجزُه إلى الصباح أو
 * لرأس الساعة يقتل الميزةَ ولا يحمي أحداً من شيء. ولذلك يُرسَل
 * مباشرةً في الطلب نفسِه لا عبر الطابور: «لازم يوصل بسرعة».
 *
 * وموازينُه الخاصة أضيق من موازين الحدود: رمزٌ كلَّ دقيقتين للرقم
 * الواحد، وخمسُ محاولاتِ تحقّقٍ ثم قفل، وصلاحيةُ خمس دقائق،
 * واستعمالٌ واحد.
 *
 * ═══ وما لا يُكشف ═══
 *
 * الردُّ واحدٌ للرقم المسجَّل وغير المسجَّل: «إن كان الرقم مسجَّلاً
 * وصلك الرمز». من يجسّ الأرقام لا يعرف أيُّها موكّلٌ عندنا.
 */
class PortalOtp
{
    public const TTL_SECONDS = 300;
    public const RESEND_SECONDS = 120;
    public const MAX_ATTEMPTS = 5;

    /**
     * أمقترنٌ الرقمُ الآن فعلاً؟ — يُسأل الجسرُ لا الذاكرة.
     *
     * ═══ العطل الذي وقع ═══
     *
     * الذاكرةُ (wa_evo_state) قالت «close» قديمةً والرقمُ مقترنٌ
     * يرسل، فرُدّ الموكّلُ بـ«غير متاح» وكلُّ شيءٍ سليم. البوابةُ
     * واجهةُ بيعٍ: تُحكَم بالحقيقة الحيّة، والذاكرةُ تُشفى بما قاله
     * الجسرُ في الطريق — فيستفيد بقيّةُ النظام من الجواب نفسِه.
     */
    private static function connectedNow(): bool
    {
        if (WhatsAppSettings::isDisconnected()) {
            return false; // فصلٌ صريحٌ بيد المكتب — لا يُلتفّ عليه
        }

        if (!WhatsAppSettings::usingEvolution()) {
            return WhatsAppManager::isConnected();
        }

        try {
            $state = (string) WhatsAppManager::provider()?->connectionState();
            WhatsAppSettings::setEvolutionState($state);

            return $state === 'open';
        } catch (\Throwable) {
            // تعثّرت الشبكةُ نفسُها: الذاكرةُ أصدقُ ما بقي
            return WhatsAppManager::isConnected();
        }
    }

    /** @return array{ok: bool, message: string, retry_after?: int} */
    public static function request(string $rawPhone): array
    {
        if (!self::connectedNow()) {
            return [
                'ok' => false,
                'message' => 'الدخول برقم الهاتف غير متاحٍ حالياً — استخدم رقم الهويّة.',
            ];
        }

        $normalized = Client::normalizePhone($rawPhone);

        if (mb_strlen($normalized) < 8) {
            return ['ok' => false, 'message' => 'أدخل رقم هاتفٍ صحيحاً.'];
        }

        // مهلةُ الدقيقتين قبل أيّ عملٍ آخر — وقبل معرفة أهو مسجَّلٌ
        // أصلاً: لو حُدّ المسجَّلُ وحده لكشف الفرقُ في السلوك حالَه
        $cooldownKey = 'portal_otp_cool:' . hash_hmac('sha256', $normalized, (string) config('app.key'));

        if (!Cache::add($cooldownKey, 1, self::RESEND_SECONDS)) {
            return [
                'ok' => false,
                'message' => 'أُرسل رمزٌ قبل قليل — انتظر دقيقتين ثم اطلب غيرَه.',
                'retry_after' => self::RESEND_SECONDS,
            ];
        }

        // ‏findByPhone تعيد صاحبَ الرقم يقيناً أو لا شيء: رقمٌ يشترك
        // فيه موكّلان لا يُراسَل — دخولُ أحدهما بحساب الآخر أخطر من
        // تعطيل الطريق، ويبقى لهما بابُ الهويّة
        $client = Client::findByPhone($rawPhone);

        if ($client) {
            $code = (string) random_int(100000, 999999);

            Cache::put(self::stateKey($normalized), [
                'hash' => hash_hmac('sha256', $code, (string) config('app.key')),
                'client_id' => $client->id,
                'attempts' => 0,
            ], self::TTL_SECONDS);

            self::deliver($client, $code);
        }

        return [
            'ok' => true,
            'message' => 'إن كان الرقم مسجَّلاً لدى المكتب فقد وصلك الرمز على واتساب.',
        ];
    }

    /** @return array{ok: bool, client?: Client, message: string} */
    public static function verify(string $rawPhone, string $code): array
    {
        $normalized = Client::normalizePhone($rawPhone);
        $key = self::stateKey($normalized);
        $state = Cache::get($key);

        if (!is_array($state)) {
            return ['ok' => false, 'message' => 'انتهت صلاحية الرمز — اطلب رمزاً جديداً.'];
        }

        if ((int) $state['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return ['ok' => false, 'message' => 'تجاوزتَ عدد المحاولات — اطلب رمزاً جديداً بعد دقيقتين.'];
        }

        $state['attempts'] = (int) $state['attempts'] + 1;
        Cache::put($key, $state, self::TTL_SECONDS);

        if (!hash_equals((string) $state['hash'], hash_hmac('sha256', trim($code), (string) config('app.key')))) {
            return ['ok' => false, 'message' => 'الرمز غير صحيح.'];
        }

        // استعمالٌ واحد: يُحرق قبل فتح الجلسة كرابط البوابة سواءً بسواء
        Cache::forget($key);

        $client = Client::find((int) $state['client_id']);

        if (!$client) {
            return ['ok' => false, 'message' => 'تعذّر الدخول — راجع المكتب.'];
        }

        return ['ok' => true, 'client' => $client, 'message' => ''];
    }

    /**
     * الإرسالُ المباشر: صفٌّ في الدفتر ثم نداءُ المزوّد في الطلب نفسِه.
     *
     * لا طابورَ (دقيقةُ العامل تقتل «بسرعة») ولا حارسَ إيقاع (الرمزُ
     * مطلوبٌ من صاحبه الآن). والصفُّ يُكتب ليُحسب في أعداد اليوم
     * وليبقى أثرُ الإرسال في دفتر المكتب.
     */
    private static function deliver(Client $client, string $code): void
    {
        try {
            $waId = WhatsAppContact::normalizeWaId((string) $client->phone);

            $contact = WhatsAppContact::firstOrCreate(['wa_id' => $waId], ['client_id' => $client->id]);
            $conversation = WhatsAppConversation::firstOrCreate(
                ['contact_id' => $contact->id],
                ['status' => WhatsAppConversation::STATUS_OPEN, 'unread_count' => 0],
            );

            // لا اسمَ في الرسالة: تصل هاتفاً قد يقرؤه غيرُ صاحبه،
            // والرمزُ وحده لا يدلّ على شيءٍ عند من لا يملك الجلسة
            $body = 'رمز دخولك إلى بوابة ' . OfficeBrand::name() . ': ' . $code
                . "\nصالحٌ خمسَ دقائق ولا يُطلب منك مشاركتُه مع أحد.";

            $message = WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'direction' => WhatsAppMessage::OUT,
                'type' => 'text',
                'body' => $body,
                'status' => WhatsAppMessage::STATUS_QUEUED,
            ]);

            $result = WhatsAppManager::provider()?->sendText($waId, $body);

            if ($result && $result->ok) {
                $message->forceFill([
                    'wamid' => $result->wamid,
                    'status' => WhatsAppMessage::STATUS_SENT,
                    'sent_at' => now(),
                ])->save();
            } else {
                $message->forceFill([
                    'status' => WhatsAppMessage::STATUS_FAILED,
                    'error_title' => mb_substr((string) ($result->errorTitle ?? 'تعذّر الإرسال'), 0, 190),
                ])->save();
            }
        } catch (\Throwable $e) {
            // فشلُ التسليم لا يكشف شيئاً للزائر — الردُّ المحايد نفسُه،
            // والأثرُ في السجلّ لمن يشخّص
            Log::warning('Portal OTP delivery failed: ' . $e->getMessage());
        }
    }

    private static function stateKey(string $normalized): string
    {
        return 'portal_otp:' . hash_hmac('sha256', $normalized, (string) config('app.key'));
    }
}
