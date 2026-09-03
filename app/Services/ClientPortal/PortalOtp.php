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

    /** سقفُ رسائل الرمز لرقمٍ واحدٍ في اليوم */
    private const DAILY_LIMIT = 5;

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

        // ═══ سقفٌ يوميٌّ فوق الدقيقتين ═══
        //
        // الدقيقتان تحدّان السرعةَ لا العدد: من يعرف أرقامَ موكّلي
        // المكتب كان يدفع رسالةً إلى كلّ واحدٍ منهم كلَّ دقيقتين بلا
        // نهاية — مضايقةٌ للموكّلين، وطريقٌ إلى خفض تقييم رقم المكتب
        // عند واتساب حتى يُقيَّد أو يُوقَف.
        //
        // ويُعدّ لكلّ رقمٍ سُئل عنه، مسجَّلاً كان أو لا: عدٌّ للمسجَّل
        // وحدَه يصنع فرقاً في السلوك يُستدلّ به عليه.
        $dayKey = 'portal_otp_day:' . hash_hmac('sha256', $normalized, (string) config('app.key'));
        $today = (int) Cache::get($dayKey, 0);

        if ($today >= self::DAILY_LIMIT) {
            return [
                'ok' => false,
                'message' => 'طُلب هذا الرقمُ مرّاتٍ كثيرةً اليوم — جرّب غداً أو ادخل برقم الهويّة.',
            ];
        }

        Cache::put($dayKey, $today + 1, now()->addDay());

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

            // ═══ داخلَ الطلب عمداً — وما يبقى من أثرٍ زمنيّ ═══
            //
            // الرمزُ يُرسل هنا لا في طابور: صاحبُه ينتظره الآن، ودورةُ
            // العامل تؤخّره ثانيةً أو أكثر.
            //
            // وأثرُه أنّ الردَّ للرقم المسجَّل أبطأُ بقدر رحلةٍ إلى
            // واتساب — فرقٌ يُقاس، ويُستدلّ به على أنّ الرقمَ لموكّلٍ
            // هنا. جُرّب نقلُه إلى afterResponse فظهر أسوأُ منه:
            // لارافل لا يُفرغ نداءات terminating، فحاويةٌ تُعاد تُرسل
            // الرمزَ مرّتين وثلاثاً إلى موكّلٍ لم يطلب إلا مرّة.
            //
            // فبقي هنا، والسقفُ اليوميُّ أعلاه يحدّ الاستقصاء بخمس
            // محاولاتٍ للرقم الواحد في اليوم.
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

            // ═══ الرمزُ يُرسَل ولا يُكتب في السجلّ ═══
            //
            // كان الجسدُ يُحفظ كما هو في whatsapp_messages. وصندوقُ
            // الوارد لا يفحص الصلاحيةَ لكلّ محادثةٍ على حدة: من يملك
            // whatsapp.view — موظّفُ استقبالٍ مثلاً — يفتح محادثةَ أيّ
            // موكّل. فيطلب الرمزَ من بوابة الدخول العامّة برقم الموكّل،
            // ثمّ يقرؤه من الوارد خلال دقائقه الخمس، ويدخل بوابتَه.
            // تصعيدُ صلاحيةٍ كامل، بلا أثرٍ في سجلّ الموكّل.
            //
            // والرمزُ يبقى في النسخ الاحتياطية إلى الأبد أيضاً. فيُكتب
            // بديلٌ يقول ما جرى ولا يحمل الرمز، ويُرسَل الجسدُ الحقيقيُّ
            // إلى واتساب وحدَه.
            $message = WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'direction' => WhatsAppMessage::OUT,
                'type' => 'text',
                'body' => 'رمزُ دخولٍ إلى بوابة الموكّلين — لا يُعرض هنا.',
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
