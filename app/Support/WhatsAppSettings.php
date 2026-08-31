<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * بيانات اعتماد واتساب الخاصّة بهذا المكتب وحده.
 *
 * ═══ لماذا هنا لا في ملف البيئة ═══
 *
 * ‏WHATSAPP_META_TOKEN في .env كان يعني رقماً واحداً لكل الخادم: مكتبٌ
 * يربط رقمه فيرسل باسمه كلُّ من على الخادم. والتخزين هنا يجعل الرمز
 * مشفَّراً بمفتاح تطبيق هذا المكتب (APP_KEY) — فلو نُسخ الصفُّ حرفياً
 * إلى قاعدة مكتبٍ آخر لم يُفكّ، لأنّ المفاتيح مختلفة بحكم التنصيب.
 *
 * ولا يخرج الرمز الخام إلى قالبٍ ولا استجابةٍ ولا سجلٍّ أبداً: تُعرض
 * منه بصمةٌ مقنَّعة وحدها (آخر أربعة محارف).
 */
class WhatsAppSettings
{
    public const KEY_TOKEN = 'wa_access_token';
    public const KEY_APP_SECRET = 'wa_app_secret';
    public const KEY_APP_ID = 'wa_app_id';
    public const KEY_VERIFY_TOKEN = 'wa_verify_token';
    public const KEY_PHONE_ID = 'wa_phone_number_id';
    public const KEY_WABA_ID = 'wa_business_account_id';
    public const KEY_DISPLAY_PHONE = 'wa_display_phone';
    public const KEY_BUSINESS_NAME = 'wa_business_name';
    public const KEY_TOKEN_HINT = 'wa_token_hint';
    public const KEY_CONNECTED_AT = 'wa_connected_at';
    public const KEY_LAST_SYNC_AT = 'wa_last_sync_at';
    public const KEY_LAST_WEBHOOK_AT = 'wa_last_webhook_at';
    public const KEY_LAST_ERROR = 'wa_last_error';
    public const KEY_DISCONNECTED = 'wa_disconnected';

    // ── Evolution: جسر واتساب ويب ────────────────────────────
    // اسمُ نسخة المكتب على الخادم المشترك، وسرُّ ويبهوكها. العنوانُ
    // والمفتاحُ العام في ملفّ البيئة لا هنا: الخادم واحدٌ للجميع.
    public const KEY_EVO_INSTANCE = 'wa_evo_instance';
    public const KEY_EVO_SECRET = 'wa_evo_secret';
    public const KEY_EVO_STATE = 'wa_evo_state';

    // إعدادات السلوك — لا أسرار
    public const KEY_NOTIFY_SESSIONS = 'wa_notify_sessions';
    public const KEY_NOTIFY_INVOICES = 'wa_notify_invoices';
    public const KEY_NOTIFY_CASE_UPDATES = 'wa_notify_case_updates';
    public const KEY_SESSION_TEMPLATE = 'wa_session_template';
    public const KEY_INVOICE_TEMPLATE = 'wa_invoice_template';
    public const KEY_REMINDER_HOURS = 'wa_reminder_hours';
    public const KEY_AI_REPLY = 'wa_ai_reply';

    /**
     * صندوقُ الوارد — مخفيٌّ افتراضاً.
     *
     * ═══ لماذا مخفيّ ═══
     *
     * لأنّه الطريقُ الوحيد لإرسالٍ يدويٍّ حرّ، وهو أخطرُ ما في
     * المنظومة على سلامة الرقم: موظّفٌ يراسل عشرين رقماً في دقيقتين
     * يفعل بالرقم ما لا يفعله ألفُ إشعارٍ آلي مضبوطِ الإيقاع.
     *
     * والإشعاراتُ الآلية لا تحتاجه: تُكتب وتُرسَل بلا أن يفتحه أحد.
     *
     * ولا يكفي إخفاءُ الرابط: مسارٌ يعمل ورابطٌ مخفيٌّ حمايةٌ في
     * الشكل. فالمسارُ نفسُه يُرفض حين يكون مخفيّاً.
     */
    public const KEY_INBOX_VISIBLE = 'wa_inbox_visible';

    public static function inboxVisible(): bool
    {
        try {
            return Setting::get(self::KEY_INBOX_VISIBLE, '0') === '1';
        } catch (\Throwable) {
            return false;
        }
    }

    public const GROUP = 'whatsapp';

    /**
     * هل يستطيع هذا المكتب الإرسال فعلاً؟
     *
     * والجواب يختلف بالمزوّد: Meta تحتاج رمزاً ومعرّفَ رقم، وEvolution
     * تحتاج نسخةً حالتُها «open» — أي أنّ رمزاً مُسح وهاتفاً مقترن.
     * وخلطُ الشرطين يجعل مكتباً على Evolution يبدو مفصولاً أبداً.
     */
    public static function isConnected(): bool
    {
        if (self::isDisconnected()) {
            return false;
        }

        if (self::usingEvolution()) {
            return self::evolutionState() === 'open';
        }

        return self::accessToken() !== null && self::phoneNumberId() !== null;
    }

    /** أيُّ مزوّدٍ يعمل عليه هذا المكتب. */
    public static function usingEvolution(): bool
    {
        return (string) config('whatsapp.default', 'meta') === 'evolution';
    }

    /**
     * فصلٌ صريح — يتقدّم على أيّ رجوعٍ لملف البيئة.
     *
     * ═══ العطل الذي وُضع له ═══
     *
     * الرمزُ ومعرّفُ الرقم يرجعان إلى services.whatsapp.* حين لا يكون
     * للمكتب اعتمادٌ خاص. فمكتبٌ على خادمٍ فيه تلك القيم يضغط «فصل
     * الرقم» فتُمحى إعداداتُه — ويبقى مُرسِلاً كما كان، والشاشةُ تقول
     * «مفصول». وواجهةٌ تكذب على المدير أسوأ من غياب الزرّ أصلاً.
     */
    public static function isDisconnected(): bool
    {
        return self::plain(self::KEY_DISCONNECTED) === '1';
    }

    /**
     * الرمز الخام — داخل الخادم فقط.
     *
     * الرجوع إلى .env مقصود: مكاتبُ تعمل اليوم بالرمز المركزي القديم
     * ‏(services.whatsapp.meta_token) ولم تربط رقمها بعد، ولا يجوز أن
     * يتوقّف إشعارُ موكّليها لحظةَ تحديثِ النظام.
     */
    public static function accessToken(): ?string
    {
        return self::secret(self::KEY_TOKEN)
            ?? self::envValue(config('services.whatsapp.meta_token'));
    }

    public static function appSecret(): ?string
    {
        return self::secret(self::KEY_APP_SECRET)
            ?? self::envValue(env('WHATSAPP_APP_SECRET'));
    }

    /**
     * رمز التحقّق من الويبهوك.
     *
     * يُولَّد عندنا لا يكتبه المستخدم: رمزٌ يختاره إنسان يصير «12345»،
     * وهو الحاجزُ الوحيد في مصافحة التحقّق مع Meta. ويُولَّد مرّةً
     * ويُحفظ — تغييرُه بعد ضبطه عند Meta يكسر الربط بلا رسالة.
     */
    public static function verifyToken(): string
    {
        $stored = self::secret(self::KEY_VERIFY_TOKEN);

        if ($stored !== null && $stored !== '') {
            return $stored;
        }

        $fresh = Str::random(40);
        Setting::set(self::KEY_VERIFY_TOKEN, Crypt::encryptString($fresh), self::GROUP);

        return $fresh;
    }

    public static function phoneNumberId(): ?string
    {
        return self::plain(self::KEY_PHONE_ID)
            ?? self::envValue(config('services.whatsapp.meta_phone_id'));
    }

    /**
     * اسمُ نسخة هذا المكتب على خادم Evolution.
     *
     * يُشتقّ من نطاق المكتب لا يُختار: خادمٌ واحدٌ يحمل نسخَ كلّ
     * المكاتب، واسمان متشابهان يعنيان مكتباً يقرأ رسائل مكتبٍ آخر.
     * والنطاقُ فريدٌ بحكم التعريف.
     */
    public static function evolutionInstance(): string
    {
        $stored = (string) self::plain(self::KEY_EVO_INSTANCE);

        if ($stored !== '') {
            return $stored;
        }

        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $name = preg_replace('/[^a-z0-9]+/i', '-', $host !== '' ? $host : 'office');
        // يدخل الاسمُ في عنوانٍ عند الخادم: يُصغَّر كي لا يختلف مكتبان
        // بحرفٍ كبيرٍ وحده فيقرأ أحدُهما رسائل الآخر
        $name = strtolower(trim((string) $name, '-')) ?: 'office';

        Setting::set(self::KEY_EVO_INSTANCE, $name, self::GROUP);

        return $name;
    }

    /**
     * سرُّ ويبهوك Evolution — يُولَّد مرّةً ويعيش في العنوان نفسه.
     *
     * ═══ لماذا في المسار لا في ترويسة ═══
     *
     * ‏Evolution لا يوقّع حمولاتِه توقيعاً معمّى كما تفعل Meta. فبابٌ
     * مفتوحٌ بلا سرّ يقبل من أيّ أحدٍ في الإنترنت رسالةً يزعم أنّها من
     * موكّل — فتُكتب في خيطه ويقرؤها المحامي على أنّها منه.
     *
     * فالسرُّ في المسار: من لا يعرفه لا يجد العنوان أصلاً. وهو أربعون
     * محرفاً عشوائياً، ويُقارَن بمقارنةٍ ثابتة الزمن.
     */
    public static function evolutionSecret(): string
    {
        $stored = (string) self::plain(self::KEY_EVO_SECRET);

        if ($stored !== '') {
            return $stored;
        }

        $secret = Str::random(40);
        Setting::set(self::KEY_EVO_SECRET, $secret, self::GROUP);

        return $secret;
    }

    /** آخرُ حالةِ اتصالٍ عرفناها من الخادم: open|connecting|close. */
    public static function evolutionState(): string
    {
        return self::plain(self::KEY_EVO_STATE) ?: 'close';
    }

    public static function setEvolutionState(string $state): void
    {
        Setting::set(self::KEY_EVO_STATE, $state, self::GROUP);

        if ($state === 'open') {
            // أوّلُ اقترانٍ يُختم مرّةً: منه يُحسب تدرّجُ السقوف في
            // الأيام الأولى — ورقمٌ جديد يندفع فجأةً أظهرُ من رقمٍ
            // يزيد قليلاً كلَّ يوم.
            \App\Services\WhatsApp\SendingGuard::markPaired();

            Setting::set(self::KEY_CONNECTED_AT, now()->toIso8601String(), self::GROUP);
            Setting::set(self::KEY_DISCONNECTED, '0', self::GROUP);
            Setting::set(self::KEY_LAST_ERROR, '', self::GROUP);
        }
    }

    public static function appId(): ?string
    {
        return self::plain(self::KEY_APP_ID) ?: null;
    }

    public static function wabaId(): ?string
    {
        return self::plain(self::KEY_WABA_ID);
    }

    public static function displayPhone(): ?string
    {
        return self::plain(self::KEY_DISPLAY_PHONE);
    }

    public static function businessName(): ?string
    {
        return self::plain(self::KEY_BUSINESS_NAME);
    }

    /** آخر أربعة محارف من الرمز — ما يُعرض للمدير ليتعرّف عليه لا ليستعمله. */
    public static function tokenHint(): ?string
    {
        return self::plain(self::KEY_TOKEN_HINT);
    }

    /**
     * حفظ الاعتماد. الحقلُ الفارغ يُبقي المخزَّن كما هو — فمن عدّل اسم
     * النشاط وحده لا يُطالَب بلصق الرمز كاملاً من جديد.
     */
    public static function store(
        ?string $token,
        ?string $phoneNumberId,
        ?string $wabaId = null,
        ?string $appSecret = null,
        ?string $appId = null,
    ): void {
        if (filled($appId)) {
            // معرّفُ التطبيق ليس سرّاً — يظهر في كلّ عنوانٍ في لوحة
            // Meta. لكنّه مع السرّ يصنع «رمزَ تطبيق» يسجّل الويبهوك
            // ويشترك في الحقول بلا أن يفتح المكتبُ لوحةَ Meta أصلاً.
            Setting::set(self::KEY_APP_ID, trim($appId), self::GROUP);
        }

        if (filled($token)) {
            $token = trim($token);
            Setting::set(self::KEY_TOKEN, Crypt::encryptString($token), self::GROUP);
            Setting::set(self::KEY_TOKEN_HINT, '••••' . mb_substr($token, -4), self::GROUP);
        }

        if (filled($appSecret)) {
            Setting::set(self::KEY_APP_SECRET, Crypt::encryptString(trim($appSecret)), self::GROUP);
        }

        if (filled($phoneNumberId)) {
            Setting::set(self::KEY_PHONE_ID, trim($phoneNumberId), self::GROUP);
        }

        if (filled($wabaId)) {
            Setting::set(self::KEY_WABA_ID, trim($wabaId), self::GROUP);
        }

        Setting::set(self::KEY_CONNECTED_AT, now()->toIso8601String(), self::GROUP);
        Setting::set(self::KEY_LAST_ERROR, '', self::GROUP);
        // ربطٌ جديد يرفع علامةَ الفصل — وإلا بقي المكتب صامتاً بعد أن
        // أعاد الربط، بلا سببٍ يظهر في أيّ شاشة
        Setting::set(self::KEY_DISCONNECTED, '0', self::GROUP);
    }

    /** ما يُعرفه المزوّد عن الرقم — يُحفظ بعد كل فحص اتصال ناجح. */
    public static function rememberIdentity(?string $displayPhone, ?string $businessName): void
    {
        if (filled($displayPhone)) {
            Setting::set(self::KEY_DISPLAY_PHONE, $displayPhone, self::GROUP);
        }

        if (filled($businessName)) {
            Setting::set(self::KEY_BUSINESS_NAME, $businessName, self::GROUP);
        }

        Setting::set(self::KEY_LAST_SYNC_AT, now()->toIso8601String(), self::GROUP);
    }

    public static function touchWebhook(): void
    {
        Setting::set(self::KEY_LAST_WEBHOOK_AT, now()->toIso8601String(), self::GROUP);
    }

    /**
     * تدوينُ سببِ آخر إخفاق — بعد تنقيته من كلّ سرّ.
     *
     * ═══ لماذا تنقيةٌ خاصّة هنا ═══
     *
     * ‏MailIdentity::scrub تعرف اعتماداتِ البريد وحدها، ولا تعرف رمزَ
     * واتساب ولا سرَّ التطبيق. وهذا النصّ يُعرض في صفحة الإعدادات
     * ويُرسَل في التشخيص — فلو حمل رمزاً يوماً لقُرئ من الشاشة.
     *
     * فتُحجب هنا ثلاثة: رمزُ هذا المكتب وسرُّه حرفياً إن ظهرا، وأيُّ
     * سلسلةٍ على هيئة رمز Meta (يبدأ بـEAA ويطول) ولو لم تكن رمزَنا —
     * فقد يعود في نصّ خطأٍ من عندهم. والحجبُ قبل القصّ لا بعده: القصُّ
     * أوّلاً قد يقطع الرمز نصفين فينجو نصفُه من الاستبدال.
     */
    public static function recordError(string $reason): void
    {
        $clean = MailIdentity::scrub($reason);

        foreach ([self::secret(self::KEY_TOKEN), self::secret(self::KEY_APP_SECRET)] as $secret) {
            if ($secret !== null && $secret !== '') {
                $clean = str_replace($secret, '[محجوب]', $clean);
            }
        }

        $clean = (string) preg_replace('/\bEAA[A-Za-z0-9_\-]{20,}/', '[محجوب]', $clean);

        Setting::set(self::KEY_LAST_ERROR, mb_substr($clean, 0, 300), self::GROUP);
    }

    /**
     * فصلُ الرقم: تُمحى الاعتمادات وحدها.
     *
     * والمحادثات تبقى: هي مراسلاتُ موكّلين ومستنداتُهم، ومحوُها مع
     * ضغطةِ «فصل» فقدانٌ لا رجعة فيه لسجلٍّ قد يُحتاج في نزاع.
     */
    public static function disconnect(): void
    {
        foreach ([self::KEY_TOKEN, self::KEY_APP_SECRET, self::KEY_VERIFY_TOKEN, self::KEY_PHONE_ID,
                  self::KEY_WABA_ID, self::KEY_TOKEN_HINT, self::KEY_CONNECTED_AT] as $key) {
            Setting::set($key, '', self::GROUP);
        }

        Setting::set(self::KEY_DISCONNECTED, '1', self::GROUP);
    }

    /** لمحةُ الحالة للواجهة — بلا أيّ سرّ. */
    public static function snapshot(): array
    {
        $connectedAt = self::plain(self::KEY_CONNECTED_AT);
        $lastWebhook = self::plain(self::KEY_LAST_WEBHOOK_AT);
        $error = self::plain(self::KEY_LAST_ERROR);

        return [
            'connected' => self::isConnected(),
            'phone_number_id' => self::maskId(self::phoneNumberId()),
            'waba_id' => self::maskId(self::wabaId()),
            'display_phone' => self::displayPhone(),
            'business_name' => self::businessName(),
            'token_hint' => self::tokenHint(),
            'connected_at' => $connectedAt ?: null,
            'last_sync_at' => self::plain(self::KEY_LAST_SYNC_AT) ?: null,
            'last_webhook_at' => $lastWebhook ?: null,
            'webhook_url' => url('/webhooks/whatsapp'),
            // ‏«أكمل الربط تلقائياً» لا يُعرض إلا حين يمكن فعلاً:
            // زرٌّ يظهر ثم يقول «ينقصني كذا» أسوأ من زرٍّ لا يظهر
            'can_autowire' => filled(self::appId()) && filled(self::appSecret()) && filled(self::accessToken()),
            'error' => $error ?: null,
            // «يحتاج انتباهاً»: مربوطٌ ولم يصل منه إشعارٌ قطّ، أو مضى
            // على آخر إشعارٍ أكثر من أسبوع — ربطٌ يبدو حياً وهو ميت.
            'needs_attention' => self::isConnected()
                && ($error !== '' && $error !== null || $lastWebhook === null || $lastWebhook === ''),
        ];
    }

    /** إعدادٌ سلوكي منطقي — مطفأٌ افتراضاً. */
    public static function flag(string $key): bool
    {
        return self::plain($key) === '1';
    }

    public static function setFlag(string $key, bool $on): void
    {
        Setting::set($key, $on ? '1' : '0', self::GROUP);
    }

    /** كم ساعةً قبل الجلسة يُرسَل التذكير. */
    public static function reminderHours(): int
    {
        $hours = (int) (self::plain(self::KEY_REMINDER_HOURS) ?: 24);

        return max(1, min(72, $hours));
    }

    public static function templateName(string $key, string $default = ''): string
    {
        return (string) (self::plain($key) ?: $default);
    }

    // ── داخلي ────────────────────────────────────────────────────

    private static function plain(string $key): ?string
    {
        $value = Setting::get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function secret(string $key): ?string
    {
        $stored = Setting::get($key);

        if (!is_string($stored) || $stored === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($stored);

            return $plain !== '' ? $plain : null;
        } catch (DecryptException) {
            // مفتاح تطبيقٍ مختلف أو صفٌّ تالف — لا نُسقط الخدمة ولا
            // نُرجع نصّاً مشفَّراً يُرسَل إلى Meta فيُرفض بلا تفسير
            return null;
        }
    }

    private static function envValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** المعرّفات تُعرض مقنَّعةً: ليست سرّاً، لكنّها لا تُنثر في لقطة شاشة. */
    private static function maskId(?string $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        return strlen($id) <= 6 ? $id : '…' . substr($id, -6);
    }
}
