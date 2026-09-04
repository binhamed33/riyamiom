<?php

namespace App\Services\ClientPortal;

use App\Models\Client;
use App\Models\ClientPortalAttempt;
use App\Support\GulfPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * مصادقة بوابة العملاء — خطوتان.
 *
 *   ١) رقم الهوية      → يحدّد العميل مبدئياً
 *   ٢) آخر ٣ أرقام من هاتفه المسجَّل لدى المكتب → يثبت أنه هو
 *
 * مبادئ حاكمة:
 *
 * - كل تحقّق يجري في الخادم. لا يُرسَل رقم الهاتف كاملاً إلى المتصفّح
 *   أبداً، ولا حتى مخفياً في حقل أو في جافاسكربت.
 * - ولا تُرسَل الأرقامُ المطلوبةُ نفسها في أي صورة: تلميحٌ يكشف ما
 *   يُسأل عنه يُلغي السؤال.
 * - رسالة الفشل واحدة مهما كان السبب. «الهوية صحيحة والهاتف خطأ» تكشف
 *   للمُخمِّن أنه على الطريق الصحيح، فتُصبح المرحلة الثانية بلا قيمة.
 * - المرحلة الثانية مربوطة بجلسة أنشأتها المرحلة الأولى ولها عمر قصير،
 *   فلا يُقفَز إليها مباشرة ولا تبقى مفتوحة.
 * - مقارنة الأرقام بزمن ثابت.
 *
 * التوسعة لاحقاً (OTP عبر رسالة أو بريد): المرحلة الثانية مجرّد
 * «مُتحقِّق» يستهلك تحدّياً معلَّقاً. إضافة مزوّد رسائل تعني استبدال
 * verify() بمُتحقِّق آخر دون المساس ببقية البوابة.
 */
class ClientAuthenticator
{
    /** عمر التحدّي بين الخطوتين */
    public const CHALLENGE_TTL = 300;      // ٥ دقائق

    /** محاولات الخطوة الأولى لكل عنوان */
    private const LOOKUP_LIMIT = 8;
    private const LOOKUP_DECAY = 600;      // ١٠ دقائق

    /** محاولات التحقّق لكل تحدٍّ واحد */
    private const VERIFY_LIMIT = 5;
    private const VERIFY_DECAY = 900;      // ١٥ دقيقة

    /**
     * ═══ سقفُ التخمين لكلّ موكّلٍ لا لكلّ تحدٍّ ═══
     *
     * الرقمُ السرّيُّ ثلاثةُ أرقامٍ — ألفُ احتمالٍ لا غير. وحدُّ الخمسِ
     * كان مربوطاً برمز التحدّي، والتحدّي يُولَد جديداً مع كلّ طلبِ
     * هويّة: فمن خمّن خمساً عاد إلى الخطوة الأولى وأخذ خمساً أخرى،
     * بلا نهاية. أي أنّه لم يكن قفلاً على الحساب بل مطبّاً.
     *
     * والسقفُ هنا على الموكّل نفسِه ويعبر التحدّيات: عشرون محاولةً في
     * الساعة تكفي من نسي آخرَ ثلاثةٍ من رقمه، ولا تكفي من يمشي على
     * الألف — يلزمه خمسون ساعةً بدل ساعتين.
     */
    private const CLIENT_LIMIT = 20;

    /** كم نفادَ ميزانيّةٍ قبل الإغلاق الكامل لباب الهويّة */
    private const LOCK_ROUNDS = 3;

    /** مدّةُ الإغلاق — يومٌ كامل */
    private const LOCK_SECONDS = 86400;

    /** وعمرُ العدّ التراكميّ: أسبوعٌ، فلا يُنسى بين موجةٍ وأخرى */
    private const LOCK_MEMORY = 604800;
    private const CLIENT_DECAY = 3600;     // ساعة

    public const SESSION_CHALLENGE = 'client_portal_challenge';
    public const SESSION_CLIENT = 'client_access_id';
    public const SESSION_NAME = 'client_access_name';
    public const SESSION_AT = 'client_access_at';

    /**
     * الخطوة الأولى. تُرجع دائماً الشكل نفسه سواء وُجد العميل أم لا،
     * فلا يُستدلّ من الرد على وجود رقم هوية في المكتب.
     *
     * @return array{ok: bool, locked: bool, hint: ?string, retry_after: ?int}
     */
    public function beginLookup(Request $request, string $nationalId): array
    {
        $nationalId = $this->normalizeId($nationalId);
        $key = 'client-portal:lookup:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, self::LOOKUP_LIMIT)) {
            return $this->locked(RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, self::LOOKUP_DECAY);

        // البحث بالبصمة لا بالنص: العمود مشفَّر تشفيراً غير حتمي فلا
        // تصلح المساواة عليه، وتحميل كل العملاء لفكّ تشفيرهم في كل
        // محاولة دخول باب استنزاف مفتوح.
        $hash = Client::hashNationalId($nationalId);

        $client = $hash === null ? null : Client::query()
            ->where('national_id_hash', $hash)
            ->first();

        // عميل بلا هاتف مسجَّل لا يمكن التحقّق منه — يُعامَل كغير موجود
        // بالضبط، فلا يكشف الرد أن الهوية صحيحة.
        $numbers = $client ? $this->phoneNumbers($client) : [];

        $this->record($request, $nationalId, 'lookup', $numbers !== [], $client?->id);

        // ═══ الردُّ واحدٌ عرفنا الهويّةَ أم لم نعرفها ═══
        //
        // الرسالةُ كانت واحدةً بالفعل — لكنّ الحالةَ لم تكن: التحدّي
        // يُكتب في الجلسة عند المعرفة وحدَها، فالصفحةُ التالية تُظهر
        // الخطوةَ الثانية بتلميحٍ مثل «9123••••» عند المعرفة، وتعود
        // إلى الأولى عند الجهل. فصار الرابطُ دليلَ هاتفٍ للمكتب: من
        // جرّب رقمَ هويّةٍ عرف أصاحبُه موكّلٌ هنا (وعلاقةُ المحامي
        // بموكّله سرٌّ في ذاتها)، وأخذ معه أوّلَ أربعةِ أرقامٍ من هاتفه.
        //
        // فالتحدّي يُكتب في الحالتين. والشكليُّ منه بلا معرّف موكّل —
        // فيسقط تحقّقُه دائماً لأنّ Client::find(null) لا شيء — وتلميحُه
        // مشتقٌّ من بصمة الهويّة نفسِها: ثابتٌ لمن أعاد المحاولةَ بالرقم
        // نفسِه، مختلفٌ بين رقمٍ وآخر، فلا يُفرَّق بتغيّره.
        if (!$client || $numbers === []) {
            $request->session()->put(self::SESSION_CHALLENGE, [
                'client_id' => null,
                'decoy_for' => $hash,
                'issued_at' => now()->timestamp,
                'token' => bin2hex(random_bytes(16)),
            ]);

            return ['ok' => true, 'locked' => false, 'hint' => null, 'retry_after' => null];
        }

        // التحدّي في الجلسة: لا يحمل الهاتف، بل معرّف العميل ووقت الإصدار
        $request->session()->put(self::SESSION_CHALLENGE, [
            'client_id' => $client->id,
            'issued_at' => now()->timestamp,
            'token' => bin2hex(random_bytes(16)),
        ]);

        return [
            'ok' => true,
            'locked' => false,
            'hint' => self::hint($numbers),
            'retry_after' => null,
        ];
    }

    /**
     * الخطوة الثانية.
     *
     * @return array{ok: bool, locked: bool, expired: bool, client: ?Client, retry_after: ?int}
     */
    public function verify(Request $request, string $digits): array
    {
        $challenge = $request->session()->get(self::SESSION_CHALLENGE);

        // array_key_exists لا isset: التحدّي الشكليُّ معرّفُه null،
        // وisset تعدّ الـnull غياباً — فكان يسقط في فرع «انتهت المهلة»
        // بينما يسقط الحقيقيُّ في «تعذّر التحقّق». رسالتان مختلفتان =
        // الأوراكلُ نفسُه الذي أُغلق في الخطوة الأولى.
        if (!is_array($challenge)
            || !array_key_exists('client_id', $challenge)
            || !isset($challenge['issued_at'], $challenge['token'])) {
            return $this->failed(expired: true);
        }

        if (now()->timestamp - (int) $challenge['issued_at'] > self::CHALLENGE_TTL) {
            $request->session()->forget(self::SESSION_CHALLENGE);

            return $this->failed(expired: true);
        }

        $key = 'client-portal:verify:' . $challenge['token'];

        // والسقفُ الثاني على الموكّل نفسِه: يعبر التحدّيات فلا يُلتفّ
        // عليه بالعودة إلى الخطوة الأولى وأخذ خمسٍ جديدة
        $clientKey = 'client-portal:verify-client:'
            . ($challenge['client_id'] ?? 'decoy-' . substr((string) ($challenge['decoy_for'] ?? 'x'), 0, 32));

        /*
         * ═══ عشرُ بتّاتٍ لا تُحمى بميزانيّةٍ تتجدّد ═══
         *
         * السرُّ آخرُ ثلاثةِ أرقامٍ من الهاتف — ألفُ احتمالٍ لا غير.
         * والسقفُ الوحيدُ المستقلُّ عن العنوان كان عشرين محاولةً في
         * الساعة، **تتجدّد** لا تُقفل. فمن دار على عناوينَ قليلة:
         * عشرون في الساعة × ألفُ احتمال ⇐ خمسٌ وعشرون ساعةً وسطياً،
         * وخمسون في أسوأ الحالات. ثمّ ملفُّ الموكّل كلُّه: قضاياه
         * وجلساتُه ومستنداتُه وفواتيرُه.
         *
         * وسقفُ التحدّي الواحد (خمس) يُجدَّد بالعودة إلى الخطوة الأولى.
         *
         * فالعدُّ صار تراكمياً على الموكّل بعمرٍ طويل: من بلغ الحدَّ
         * الأقصى أُغلق بابُ الهويّة عليه يوماً كاملاً — ويبقى بابُ
         * رمز واتساب مفتوحاً لصاحب الحقّ. وموكّلٌ حقيقيٌّ يعرف
         * أرقامَه لا يبلغ عشراً أبداً.
         */
        $lockKey = str_replace('verify-client:', 'verify-lock:', $clientKey);

        if (Cache::get($lockKey . ':until', 0) > now()->timestamp) {
            $request->session()->forget(self::SESSION_CHALLENGE);

            return $this->failed(
                locked: true,
                retryAfter: (int) Cache::get($lockKey . ':until', 0) - now()->timestamp,
            );
        }

        if (RateLimiter::tooManyAttempts($clientKey, self::CLIENT_LIMIT)) {
            $request->session()->forget(self::SESSION_CHALLENGE);

            // كلُّ نفادٍ للميزانية يزيد العدَّ التراكميّ
            $rounds = (int) Cache::increment($lockKey . ':rounds');
            Cache::put($lockKey . ':rounds', $rounds, self::LOCK_MEMORY);

            if ($rounds >= self::LOCK_ROUNDS) {
                Cache::put($lockKey . ':until', now()->timestamp + self::LOCK_SECONDS, self::LOCK_SECONDS);
            }

            return $this->failed(locked: true, retryAfter: RateLimiter::availableIn($clientKey));
        }

        if (RateLimiter::tooManyAttempts($key, self::VERIFY_LIMIT)) {
            // تحدٍّ استُهلك بالتخمين لا يُعاد استعماله
            $request->session()->forget(self::SESSION_CHALLENGE);

            return $this->failed(locked: true, retryAfter: RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, self::VERIFY_DECAY);
        RateLimiter::hit($clientKey, self::CLIENT_DECAY);

        $client = Client::find($challenge['client_id']);
        $expected = $client ? $this->phoneNumbers($client) : [];
        $given = preg_replace('/\D+/', '', $digits) ?? '';

        // كل رقم سجّله المكتب للموكّل هو رقمه: يُقبل آخرُ ثلاثةٍ من أيّها.
        // كان يُقارَن بالأول وحده، فمن سجّل المكتب له رقمين لا يدخل إلا
        // بأحدهما ويُقال له «تعذّر التحقق» وهو يُدخل رقمه الصحيح.
        //
        // ولا تُكسر الحلقة عند أول تطابق: زمنُ الردّ لا يدلّ على أيّ رقم
        // طابق ولا على كم رقماً لدى الموكّل.
        $matches = false;

        if ($client && strlen($given) === 3) {
            foreach ($expected as $candidate) {
                $matches = hash_equals(substr($candidate, -3), $given) || $matches;
            }
        }

        $this->record($request, null, 'verify', $matches, $client?->id);

        if (!$matches) {
            return $this->failed();
        }

        // نجاحٌ يمسح سقفَ الموكّل: من دخل حسابَه لا يُعاقَب بمحاولاتٍ
        // سبقت، والسقفُ إنّما وُضع للمخمِّن لا لصاحب الرقم
        RateLimiter::clear($clientKey);

        // تبديل معرّف الجلسة عند رفع الصلاحية — جلسة الزائر لا تصلح جلسةً لعميل
        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_CHALLENGE);
        $request->session()->put([
            self::SESSION_CLIENT => $client->id,
            self::SESSION_NAME => $client->name,
            self::SESSION_AT => now()->timestamp,
        ]);

        RateLimiter::clear($key);
        RateLimiter::clear('client-portal:lookup:' . $request->ip());

        return ['ok' => true, 'locked' => false, 'expired' => false, 'client' => $client, 'retry_after' => null];
    }

    /**
     * فتحُ جلسةٍ لموكّلٍ تحقّقنا منه بطريقٍ آخر (رابطٌ موقّع).
     *
     * ═══ لماذا regenerate هنا أيضاً ═══
     *
     * لأنّ معرّفَ الجلسة قبل الدخول معرّفُ زائر، وقد يكون أحدٌ زرعه
     * في متصفّح الموكّل (Session Fixation). فيُبدَّل عند رفع
     * الصلاحية — هنا كما في الدخول بالهوية سواءً بسواء.
     */
    public function establish(Request $request, Client $client): void
    {
        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_CHALLENGE);
        $request->session()->put([
            self::SESSION_CLIENT => $client->id,
            self::SESSION_NAME => $client->name,
            self::SESSION_AT => now()->timestamp,
        ]);
    }

    /** العميل الحالي — أو null. لا يُقرأ من غير الجلسة. */
    public function current(Request $request): ?Client
    {
        $id = $request->session()->get(self::SESSION_CLIENT);

        return $id ? Client::find($id) : null;
    }

    /**
     * نسيانُ مفتاح البوابة وحدَه — بلا مساسٍ ببقيّة الجلسة.
     *
     * للحارس: زائرٌ يطرق بابَ البوابة بلا جلسةِ موكّلٍ قد يكون
     * موظّفاً مسجَّلَ الدخول، وإفراغُ جلسته إخراجٌ له من حسابه.
     */
    public function forget(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_CLIENT,
            self::SESSION_NAME,
            self::SESSION_AT,
            self::SESSION_CHALLENGE,
        ]);
    }

    public function logout(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_CLIENT,
            self::SESSION_NAME,
            self::SESSION_AT,
            self::SESSION_CHALLENGE,
        ]);

        // إبطال فعلي: الجلسة القديمة لا تعود صالحة حتى لو نُسخ معرّفها
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /** هل يوجد تحدٍّ سارٍ الآن؟ (لعرض الخطوة الثانية) */
    public function pendingChallenge(Request $request): ?array
    {
        $challenge = $request->session()->get(self::SESSION_CHALLENGE);

        if (!is_array($challenge) || !isset($challenge['issued_at'])) {
            return null;
        }

        if (now()->timestamp - (int) $challenge['issued_at'] > self::CHALLENGE_TTL) {
            $request->session()->forget(self::SESSION_CHALLENGE);

            return null;
        }

        $client = ($challenge['client_id'] ?? null) ? Client::find($challenge['client_id']) : null;
        $numbers = $client ? $this->phoneNumbers($client) : [];

        if ($numbers !== []) {
            return ['hint' => self::hint($numbers), 'count' => count($numbers)];
        }

        // تحدٍّ شكليّ: خطوةٌ ثانيةٌ تبدو كغيرها تماماً، بتلميحٍ مشتقٍّ
        // من بصمة الهويّة — ثابتٍ لصاحبه ولا يدلّ على شيء
        if (isset($challenge['decoy_for'])) {
            return ['hint' => self::decoyHint((string) $challenge['decoy_for']), 'count' => 1];
        }

        return null;
    }

    // ------------------------------------------------------------ داخلي

    /**
     * تلميحٌ يدلّ على الرقم ولا يحمل جوابه.
     *
     * كانت الشاشة تعرض آخر ثلاثة أرقام — وهي بعينها ما يُطلَب إدخاله،
     * فكان الجواب مطبوعاً فوق سؤاله: من عرف رقم الهوية وحده يدخل. ورقم
     * الهوية في عُمان يُكتب في العقود ويُعطى لجهات كثيرة، فليس سرّاً
     * يُبنى عليه دخول.
     *
     * فالآن تُحجب الأربعة الأخيرة كلها — الثلاثة المطلوبة ورابعٌ معها
     * كي لا يضيق التخمين — ولا يظهر من المقدّمة أكثر من أربعة أرقام.
     * ويبقى نافعاً لمن سجّل لدى المكتب أكثر من رقم: يعرف أيَّها يقصد.
     *
     * ويُحذف مفتاح الدولة قبل الحجب، لأنّه لا يميّز أحداً: «‎+968 91234567‎»
     * بدونه تُعرض ‎9689•••‎ — مقدّمةٌ يشترك فيها كل أهل عُمان. وبه يستوي
     * الرقمُ المحفوظ بمفتاحه والمحفوظُ بدونه فلا يتبدّل على الموكّل نفسه.
     */
    private static function maskDigits(string $digits): string
    {
        $local = self::localPart($digits);
        $visible = max(0, min(4, strlen($local) - 4));

        return substr($local, 0, $visible) . str_repeat('•', strlen($local) - $visible);
    }

    /**
     * الرقم المحلّي بلا مفتاح دولة.
     *
     * كان يُؤخذ آخرُ ثمانيةٍ مهما كان الرقم، فيُقصّ الرقم الإماراتي
     * ‎00971506233112‎ من وسطه فيُعرض ‎0623••••‎ — شريحةٌ لا تقابل شيئاً
     * في رقم صاحبها، فينظر إليها ولا يعرفها. وبطول الدولة يُقتطع
     * المفتاح وحده فيبقى ‎5062•••••‎ — وهو أوّل رقمه كما يكتبه.
     */
    private static function localPart(string $digits): string
    {
        $digits = str_starts_with($digits, '00') ? substr($digits, 2) : $digits;

        foreach (GulfPhone::COUNTRIES as [$code, $length]) {
            if (strlen($digits) === strlen($code) + $length && str_starts_with($digits, $code)) {
                return substr($digits, strlen($code));
            }
        }

        return $digits;
    }

    /**
     * أرقام كل هاتف مسجَّل للموكّل — الحقل قد يحمل أكثر من رقم مفصولة
     * بفاصلة، وكلّها أرقامه.
     *
     * ويُكتفى بأربعة: سجلٌّ فيه عشرون رقماً يجعل لكل تخمينٍ عشرين باباً
     * بدل باب، فيَضعُف ما بُني ليقوى.
     */
    private const MAX_NUMBERS = 4;

    /** @return list<string> */
    private function phoneNumbers(Client $client): array
    {
        $numbers = [];

        foreach (array_map('trim', explode(',', (string) $client->phone)) as $candidate) {
            $digits = preg_replace('/\D+/', '', $candidate) ?? '';

            if (strlen($digits) >= 3 && !in_array($digits, $numbers, true)) {
                $numbers[] = $digits;
            }

            if (count($numbers) === self::MAX_NUMBERS) {
                break;
            }
        }

        return $numbers;
    }

    /**
     * تلميحٌ لكل رقم مسجَّل: يعرف الموكّل أنّ لدى المكتب رقمين وأنّ
     * أيّهما يفي، فلا يظنّ رقمه الصحيح خطأً.
     *
     * @param list<string> $numbers
     */
    private static function hint(array $numbers): string
    {
        return implode(' · ', array_map(self::maskDigits(...), $numbers));
    }

    /**
     * تلميحٌ شكليٌّ لهويّةٍ لا يعرفها المكتب.
     *
     * مشتقٌّ من بصمة الهويّة لا من عشوائيّة: من أعاد المحاولةَ بالرقم
     * نفسِه رأى التلميحَ نفسَه، فلا يُميَّز الشكليُّ بتقلّبه. وشكلُه
     * شكلُ الحقيقيّ: أربعةُ أرقامٍ ثمّ أربعُ نقاط.
     */
    private static function decoyHint(string $seed): string
    {
        $digits = preg_replace('/\D+/', '', hash('sha256', 'portal-decoy:' . $seed)) ?: '92000000';
        $digits = str_pad($digits, 8, '0');

        /*
         * ═══ أوّلُ رقمٍ كان يفضح الشكليَّ ═══
         *
         * أرقامُ الهواتف العُمانيّة المحمولة تبدأ بـ٩ أو ٧ لا غير.
         * وكان أوّلُ رقمٍ في التلميح الشكليّ يُؤخذ من بصمةٍ موزّعةٍ
         * على العشرة بالتساوي — فسبعُ مرّاتٍ من عشرٍ يخرج رقمٌ لا
         * يبدأ به رقمٌ عُمانيّ قطّ.
         *
         * فمن أراد أن يعرف: أهذا الشخصُ موكّلٌ لهذا المكتب؟ — وهي
         * علاقةٌ سرّيّةٌ بذاتها — أدخل هويّتَه ونظر إلى أوّل رقم.
         * لا يبدأ بـ٧ أو ٩ ⇐ ليس موكّلاً، قطعاً.
         *
         * فصار الشكليُّ يبدأ بما يبدأ به الحقيقيّ، بالتوزيع نفسِه
         * تقريباً (٩ أغلبُ من ٧)، ومشتقّاً من البصمة فيثبت لصاحبه.
         */
        $first = ((int) $digits[0] % 5 === 0) ? '7' : '9';
        $prefix = $first . substr($digits, 1, 3);

        return $prefix . str_repeat('•', 4);
    }

    private function normalizeId(string $value): string
    {
        // مصدر واحد للتطبيع: النموذج — فلا تختلف قاعدة البصمة عن قاعدة البحث
        return Client::normalizeNationalId($value);
    }

    private function record(Request $request, ?string $identifier, string $step, bool $succeeded, ?int $clientId): void
    {
        try {
            ClientPortalAttempt::create([
                // بصمة لا تُعكَس: تربط المحاولات ولا تكشف رقم هوية أحد
                'identifier_hash' => $identifier ? hash('sha256', $identifier) : null,
                'ip' => $request->ip(),
                'step' => $step,
                'succeeded' => $succeeded,
                'client_id' => $clientId,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // تسجيل المحاولة خدمة أمنية لا شرط دخول — لا يُفشِل الطلب
        }
    }

    private function locked(int $retryAfter): array
    {
        return ['ok' => false, 'locked' => true, 'hint' => null, 'retry_after' => $retryAfter];
    }

    private function failed(bool $locked = false, bool $expired = false, ?int $retryAfter = null): array
    {
        return ['ok' => false, 'locked' => $locked, 'expired' => $expired, 'client' => null, 'retry_after' => $retryAfter];
    }
}
