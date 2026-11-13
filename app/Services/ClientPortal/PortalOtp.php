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
 * ═══ الرقمُ يُتحقَّق منه أوّلاً — ويُقال ═══
 *
 * كان الردُّ واحداً للمسجَّل وغيره («إن كان الرقم مسجَّلاً وصلك الرمز») كي
 * لا يعرف من يجسّ الأرقامَ أيُّها لموكّلٍ هنا. فدفع المكتبُ ثمنَه: موكّلٌ
 * كتب رقماً غيرَ الذي في ملفّه وقف ينتظر رمزاً لن يصل، والمكتبُ نفسُه لا
 * يعرف لماذا. فقرّر المكتبُ أن يُقال: غيرُ مسجَّل، أو مسجَّلٌ لملفَّين، أو
 * تعذّر الإرسال ولماذا، أو أُرسل. والجسُّ يحدّه خانقُ المسار (ستّةُ طلباتٍ
 * في عشر دقائق للعنوان الواحد).
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

    /**
     * @return array{ok: bool, message: string, reason?: string, retry_after?: int}
     */
    public static function request(string $rawPhone): array
    {
        if (!self::connectedNow()) {
            return [
                'ok' => false,
                'reason' => 'disconnected',
                'message' => 'الدخولُ غير متاحٍ الآن — واتساب المكتب غيرُ موصول. حاول بعد قليل أو راجع المكتب.',
            ];
        }

        $normalized = Client::normalizePhone($rawPhone);

        if (mb_strlen($normalized) < 8) {
            return ['ok' => false, 'reason' => 'invalid', 'message' => 'أدخل رقم هاتفٍ صحيحاً.'];
        }

        // ═══ أوّلاً: أهذا الرقمُ لموكّلٍ واحدٍ عندنا؟ ═══
        //
        // رقمٌ يشترك فيه ملفّان لا يُراسَل: دخولُ أحدهما بملفّ الآخر أخطرُ من
        // ردّه إلى المكتب. ويُكتب العددُ وحدَه في السجلّ — بلا رقمٍ ولا اسم.
        $matches = Client::countByPhone($rawPhone);
        $client = $matches === 1 ? Client::findByPhone($rawPhone) : null;

        if (!$client) {
            Log::info('Portal OTP: no code sent — the requested number matches '
                . $matches . ' client record(s); exactly 1 is required');

            return $matches > 1
                ? [
                    'ok' => false,
                    'reason' => 'shared',
                    'message' => 'هذا الرقمُ مسجَّلٌ لأكثر من ملفٍّ لدى المكتب، فلا يُرسَل إليه رمز. راجع المكتب ليُصحَّح رقمُك.',
                ]
                : [
                    'ok' => false,
                    'reason' => 'unregistered',
                    'message' => 'هذا الرقمُ غيرُ مسجَّلٍ لدى المكتب. تأكّد أنّه الرقمُ الذي أعطيتَه للمكتب، أو راجع المكتب ليُسجَّل في ملفّك.',
                ];
        }

        // ═══ رقمُ واتساب المكتب نفسُه ═══
        //
        // رسالةٌ من الرقم المقترن إليه تقع في محادثة «أنت» على الهاتف نفسِه، صادرةً
        // بلا تنبيه — فيظنّ من يجرّب البوابةَ برقم المكتب أنّ الرمزَ لم يُرسَل أصلاً.
        $destination = self::destination($client, $rawPhone);
        $own = WhatsAppContact::normalizeWaId((string) WhatsAppSettings::displayPhone());

        if ($own !== '' && $destination === $own) {
            return [
                'ok' => false,
                'reason' => 'own_number',
                'message' => 'هذا رقمُ واتساب المكتب نفسُه — الرسالةُ منه إليه لا تصل كتنبيه. جرّب برقم موكّلٍ آخر.',
            ];
        }

        if (!WhatsAppContact::isSendable($destination)) {
            return [
                'ok' => false,
                'reason' => 'invalid_destination',
                'message' => 'الرقمُ المسجَّل في ملفّك لدى المكتب ناقص — راجع المكتب ليُصحَّح.',
            ];
        }

        // مهلةُ الدقيقتين بين رمزٍ ورمز للرقم الواحد
        $cooldownKey = 'portal_otp_cool:' . hash_hmac('sha256', $normalized, (string) config('app.key'));

        if (!Cache::add($cooldownKey, 1, self::RESEND_SECONDS)) {
            return [
                'ok' => false,
                'reason' => 'cooldown',
                'message' => 'أُرسل رمزٌ قبل قليل — انتظر دقيقتين ثم اطلب غيرَه.',
                'retry_after' => self::RESEND_SECONDS,
            ];
        }

        // ═══ سقفٌ يوميٌّ فوق الدقيقتين — يُعدّ قبل الإرسال ═══
        //
        // الدقيقتان تحدّان السرعةَ لا العدد: من يعرف أرقامَ موكّلي المكتب كان
        // يدفع رسالةً إلى كلٍّ منهم كلَّ دقيقتين — مضايقةٌ للموكّلين، وطريقٌ إلى
        // تقييد رقم المكتب عند واتساب. ويُعدّ الطلبُ قبل أن يُرسَل، ولا يُردّ إلا
        // حين يرفض واتساب يقيناً: إرسالٌ لا يُعرف مصيرُه (مهلة) قد يكون وصل.
        $dayKey = 'portal_otp_day:' . hash_hmac('sha256', $normalized, (string) config('app.key'));
        $today = (int) Cache::get($dayKey, 0);

        if ($today >= self::DAILY_LIMIT) {
            Cache::forget($cooldownKey);

            return [
                'ok' => false,
                'reason' => 'daily_cap',
                'message' => 'طُلب هذا الرقمُ مرّاتٍ كثيرةً اليوم — جرّب غداً أو راجع المكتب.',
            ];
        }

        Cache::put($dayKey, $today + 1, now()->addDay());

        $code = (string) random_int(100000, 999999);

        // ═══ داخلَ الطلب عمداً ═══
        //
        // الرمزُ يُرسل هنا لا في طابور: صاحبُه ينتظره الآن. ولأنّه هنا فنتيجتُه
        // بين أيدينا: يُقال للموكّل أخرج الرمزُ إلى واتساب أم لا.
        $sent = self::deliver($client, $code, $rawPhone);

        if ($sent['outcome'] === 'refused') {
            // رفضٌ يقينيّ: لا رسالةَ خرجت — فلا تُحسب ولا تحجز الدقيقتين. ورمزٌ سابقٌ
            // وصل الموكّلَ يبقى صالحاً: لم يُكتب فوقه شيءٌ بعد
            Cache::put($dayKey, $today, now()->addDay());
            Cache::forget($cooldownKey);

            return [
                'ok' => false,
                'reason' => 'send_failed',
                // سببُ الجسر الخامُ في السجلّ وصفّ الدفتر وحدهما — لا يُعرض لزائرٍ مجهول
                'message' => $sent['not_on_whatsapp']
                    ? 'رقمُك المسجَّل لدى المكتب ليس على واتساب، فلا يصله رمز. راجع المكتب ليُصحَّح رقمُك.'
                    : 'تعذّر إرسالُ الرمز إلى واتساب الآن. حاول بعد دقيقة، وإن تكرّر فراجع المكتب.',
            ];
        }

        // خرج، أو لا يُعرف مصيرُه (قد يصل): يُكتب الرمزُ الآن — بعد الإرسال لا قبله،
        // فإرسالٌ مرفوضٌ لا يكتب فوق رمزٍ سابقٍ صالحٍ في يد الموكّل
        Cache::put(self::stateKey($normalized), [
            'hash' => hash_hmac('sha256', $code, (string) config('app.key')),
            'client_id' => $client->id,
            'attempts' => 0,
        ], self::TTL_SECONDS);

        return [
            'ok' => true,
            'reason' => $sent['outcome'],
            'message' => $sent['outcome'] === 'sent'
                ? 'أرسلنا رمزَ الدخول إلى واتساب رقمك.'
                : 'أرسلنا الرمز ولم يؤكّد واتساب وصولَه بعد — إن لم يصلك خلال دقيقتين فاطلب رمزاً جديداً.',
        ];
    }

    /** أفي يد هذا الرقم رمزٌ صالحٌ الآن؟ — تُبقي به الصفحةُ خطوةَ الرمز بعد طلبٍ لم يُرسَل. */
    public static function hasLiveCode(string $rawPhone): bool
    {
        $normalized = Client::normalizePhone($rawPhone);

        return $normalized !== '' && Cache::has(self::stateKey($normalized));
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
    /**
     * @return array{outcome: 'sent'|'unconfirmed'|'refused', not_on_whatsapp: bool}
     *
     * ‏sent: خرج بمعرّف. unconfirmed: قبله الجسرُ بلا معرّف، أو انقطع الطريقُ قبل
     * الردّ (مهلة) — قد يكون وصل. refused: ردّ الجسرُ برفضٍ صريح — لم يخرج شيء.
     */
    private static function deliver(Client $client, string $code, string $rawPhone = ''): array
    {
        $message = null;

        try {
            $waId = self::destination($client, $rawPhone);

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
            // صندوقُ الوارد لا يفحص الصلاحيةَ لكلّ محادثةٍ على حدة: من يملك
            // whatsapp.view كان يقرأ رمزَ أيّ موكّلٍ ويدخل بوابتَه. والرمزُ يبقى في
            // النسخ الاحتياطية أيضاً. فيُكتب بديلٌ لا يحمله، والجسدُ إلى واتساب وحدَه.
            $message = WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'direction' => WhatsAppMessage::OUT,
                'type' => 'text',
                'body' => self::LEDGER_BODY,
                'status' => WhatsAppMessage::STATUS_QUEUED,
            ]);
        } catch (\Throwable $e) {
            // لم يبلغ الجسرَ شيء: رفضٌ يقينيّ من جهتنا
            Log::error('Portal OTP: could not prepare the code for client #' . $client->id . ': ' . $e->getMessage());

            return ['outcome' => 'refused', 'not_on_whatsapp' => false];
        }

        $provider = WhatsAppManager::provider();

        if (!$provider) {
            self::markFailed($message, 'مزوّدُ واتساب غيرُ مضبوط');
            Log::error('Portal OTP: no WhatsApp provider configured');

            return ['outcome' => 'refused', 'not_on_whatsapp' => false];
        }

        $result = $provider->sendText($waId, $body);

        // ‏«Connection Closed» (٤٠٠ عابرة): ردٌّ صريحٌ بأنّ الرسالة لم تخرج — فمحاولةٌ
        // ثانيةٌ بعد ثانيةٍ ونصف لا تُرسل شيئاً مرّتين. ولا تُعاد مهلةٌ أو انقطاعٌ بلا
        // ردّ: قد تكون الأولى خرجت، والإعادةُ ترسل الرمزَ مرّتين
        if (!$result->ok && $result->retryable && $result->errorCode === '400') {
            if (!app()->runningUnitTests()) {
                usleep(1_500_000);
            }

            $result = $provider->sendText($waId, $body);
        }

        if ($result->ok) {
            // الرمزُ خرج: تعثّرُ قيده في الدفتر بعدها لا يجعله «لم يُرسَل»
            try {
                $message->forceFill([
                    'wamid' => $result->wamid,
                    'status' => WhatsAppMessage::STATUS_SENT,
                    'sent_at' => now(),
                ])->save();
            } catch (\Throwable $e) {
                Log::error('Portal OTP: sent, but the ledger row could not be updated: ' . $e->getMessage());
            }

            if ($result->unconfirmed) {
                Log::error('Portal OTP: the bridge accepted the code for client #' . $client->id . ' without a message id');

                return ['outcome' => 'unconfirmed', 'not_on_whatsapp' => false];
            }

            return ['outcome' => 'sent', 'not_on_whatsapp' => false];
        }

        $reason = (string) ($result->errorTitle ?: 'تعذّر الإرسال');
        self::markFailed($message, $reason);

        // ‏error لا warning: الإنتاجُ يكتب السجلَّ من مستوى error فما فوق
        // (LOG_LEVEL=error)، فكان سببُ الرفض يُسقط ولا يراه من يشخّص
        Log::error('Portal OTP: WhatsApp did not take the code for client #' . $client->id . ' — ' . $reason);

        // بلا رمزِ حالة: انقطع الطريقُ قبل الردّ (مهلةٌ أو انقطاع) — لا يُعرف أخرجت
        return [
            'outcome' => $result->errorCode === null ? 'unconfirmed' : 'refused',
            'not_on_whatsapp' => str_contains($reason, 'ليس على واتساب'),
        ];
    }

    private static function markFailed(?WhatsAppMessage $message, string $reason): void
    {
        try {
            $message?->forceFill([
                'status' => WhatsAppMessage::STATUS_FAILED,
                'error_title' => mb_substr($reason, 0, 190),
            ])->save();
        } catch (\Throwable) {
            // القاعدةُ نفسُها عليلة: السجلُّ يكفي
        }
    }

    /**
     * إلى أيّ رقمٍ يُرسَل الرمز: الرقمُ الذي طلبه الموكّل من أرقام ملفّه.
     *
     * حقلُ الهاتف قد يحمل أكثرَ من رقمٍ بفاصلة. وكان يُحوَّل كلُّه إلى معرّف
     * واتساب واحد — أرقامُ الرقمين ملتصقةً — فيُرسَل الرمزُ إلى رقمٍ لا وجودَ
     * له ويُكتب «أخفق» بلا أن يعرف أحدٌ لماذا.
     */
    public static function destination(Client $client, string $rawPhone): string
    {
        $typed = Client::normalizePhone($rawPhone);
        $numbers = array_values(array_filter(array_map('trim', preg_split('/[,،;\/|]+/u', (string) $client->phone) ?: [])));

        foreach ($numbers as $candidate) {
            if ($typed !== '' && Client::normalizePhone($candidate) === $typed) {
                return WhatsAppContact::normalizeWaId($candidate);
            }
        }

        return WhatsAppContact::normalizeWaId($numbers !== [] ? end($numbers) : (string) $client->phone);
    }

    /** نصُّ بديل الرمز في دفتر الرسائل — يُعرف به صفُّ الرمز عند التشخيص. */
    public const LEDGER_BODY = 'رمزُ دخولٍ إلى بوابة الموكّلين — لا يُعرض هنا.';

    private static function stateKey(string $normalized): string
    {
        return 'portal_otp:' . hash_hmac('sha256', $normalized, (string) config('app.key'));
    }
}
