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

        // ═══ أوّلاً: أهذا الرقمُ مسجَّلٌ عندنا؟ ═══
        //
        // ورقمٌ على أكثر من ملفّ يُراسَل كغيره — بقرار المكتب: كان يُردّ فوقف كلُّ
        // من سجّله المكتبُ على ملفَّين (أبٌ وشركتُه، أسرةٌ بهاتفٍ واحد) خارجَ البوابة.
        // الرمزُ يثبت ملكيّةَ الهاتف، ثمّ يختار صاحبُه أيَّ ملفّاته يفتح.
        //
        // والبصمةُ آخرُ ثمانية أرقام: رقمٌ عُمانيٌّ وآخرُ سعوديٌّ يشتركان فيها وهما
        // هاتفان لشخصين. فلا يُعدّ ملفّاً على الرقم إلا ما كان رقمُه كاملاً هو الرقمَ
        // الذي سيصله الرمز — وإلا ذهب رمزُ أحدهما إلى الآخر وفتح ملفَّه.
        ['wa_id' => $waId, 'clients' => $clients] = self::resolve($rawPhone);
        $client = $clients->first();

        if (!$client) {
            Log::info('Portal OTP: no code sent — the requested number matches no client record');

            return [
                'ok' => false,
                'reason' => 'unregistered',
                'message' => 'هذا الرقمُ غيرُ مسجَّلٍ لدى المكتب. تأكّد أنّه الرقمُ الذي أعطيتَه للمكتب، أو راجع المكتب ليُسجَّل في ملفّك.',
            ];
        }

        // ═══ رقمُ واتساب المكتب نفسُه ═══
        //
        // رسالةٌ من الرقم المقترن إليه تقع في محادثة «أنت» على الهاتف نفسِه، صادرةً
        // بلا تنبيه — فيظنّ من يجرّب البوابةَ برقم المكتب أنّ الرمزَ لم يُرسَل أصلاً.
        $destination = $waId;
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
        $sent = self::deliver($client, $code, $waId);

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
            // كلُّ الملفّات على الرقم ساعةَ الإرسال — يختار منها بعد التحقّق
            'client_ids' => $clients->pluck('id')->all(),
            'wa_id' => $waId,
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

    /** @return array{ok: bool, client?: Client, clients?: \Illuminate\Support\Collection<int, Client>, message: string} */
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

        // الملفّاتُ التي كانت على الرقم ساعةَ الإرسال، وما زالت عليه الآن: ملفٌّ نُقل
        // عنه الرقمُ أو حُذف في الدقائق الخمس لا يُفتح بهذا الرمز
        $ids = array_map('intval', (array) ($state['client_ids'] ?? [$state['client_id']]));
        $waId = (string) ($state['wa_id'] ?? WhatsAppContact::normalizeWaId($rawPhone));
        $clients = self::filesOn($ids, $waId);

        if ($clients->isEmpty()) {
            return ['ok' => false, 'message' => 'تعذّر الدخول — راجع المكتب.'];
        }

        return ['ok' => true, 'client' => $clients->first(), 'clients' => $clients, 'wa_id' => $waId, 'message' => ''];
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
    private static function deliver(Client $client, string $code, string $waId): array
    {
        $message = null;

        try {

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
     * الرقمُ الذي كتبه الزائر: إلى أيّ رقم واتساب كاملٍ يُرسَل، وأيُّ الملفّات عليه.
     *
     * يُطابَق بالرقم كاملاً. ورقمٌ كُتب محلّياً بصفرٍ في أوّله (٠٥٠٦٢٣٣١١٢ لرقمٍ
     * إماراتيّ) يُعرف بأنّه ذيلُ رقمٍ كاملٍ في ملفّ — إن كان ذيلَ رقمٍ واحدٍ لا أكثر.
     *
     * @return array{wa_id: string, clients: \Illuminate\Support\Collection<int, Client>}
     */
    public static function resolve(string $rawPhone): array
    {
        $typed = WhatsAppContact::normalizeWaId($rawPhone);
        $digits = preg_replace('/\D+/', '', strtr($rawPhone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ])) ?? '';
        $local = (str_starts_with($digits, '0') && !str_starts_with($digits, '00')) ? ltrim($digits, '0') : '';

        $byNumber = [];

        foreach (Client::allByPhone($rawPhone) as $client) {
            foreach (WhatsAppContact::waIdsOf((string) $client->phone) as $full) {
                if ($full !== '' && ($full === $typed || ($local !== '' && strlen($local) >= 8 && str_ends_with($full, $local)))) {
                    $byNumber[$full][$client->id] = $client;
                }
            }
        }

        // ذيلٌ محلّيٌّ يطابق رقمين كاملين مختلفين (بلدان مختلفان): لا يُخمَّن أيُّهما
        if (count($byNumber) !== 1) {
            return ['wa_id' => $typed, 'clients' => collect($byNumber[$typed] ?? [])->values()];
        }

        // ‏(string): PHP يحوّل مفتاحَ المصفوفة الرقميّ «96891234567» إلى عددٍ صحيح —
        // فكانت مقارنتُه الصارمةُ برقم المكتب تسقط دائماً
        $waId = (string) array_key_first($byNumber);

        return ['wa_id' => $waId, 'clients' => collect($byNumber[$waId])->sortKeys()->values()];
    }

    /**
     * أعلى ملفِّ هذا الموكّل رقمُ واتساب هذا بعينه؟ — الرقمُ كاملاً لا آخرُ ثمانية.
     */
    public static function holds(Client $client, string $waId): bool
    {
        if ($waId === '') {
            return false;
        }

        return in_array($waId, WhatsAppContact::waIdsOf((string) $client->phone), true);
    }

    /**
     * من هذه الملفّات: ما عليه هذا الرقمُ الآن.
     *
     * ملفٌّ نقل المكتبُ عنه الرقمَ أو حذفه بعد الإرسال لا يُفتح بالرمز.
     *
     * @param list<int> $ids
     * @return \Illuminate\Support\Collection<int, Client>
     */
    public static function filesOn(array $ids, string $waId): \Illuminate\Support\Collection
    {
        if ($ids === [] || $waId === '') {
            return collect();
        }

        return Client::whereIn('id', $ids)
            ->where('phone_hash', Client::hashPhone($waId))
            ->orderBy('id')->get()
            ->filter(fn (Client $c) => self::holds($c, $waId))->values();
    }

    /** نصُّ بديل الرمز في دفتر الرسائل — يُعرف به صفُّ الرمز عند التشخيص. */
    public const LEDGER_BODY = 'رمزُ دخولٍ إلى بوابة الموكّلين — لا يُعرض هنا.';

    private static function stateKey(string $normalized): string
    {
        return 'portal_otp:' . hash_hmac('sha256', $normalized, (string) config('app.key'));
    }
}
