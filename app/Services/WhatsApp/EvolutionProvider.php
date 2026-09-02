<?php

namespace App\Services\WhatsApp;

use App\Support\WhatsAppSettings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * جسرُ واتساب ويب — يُربط بمسح رمزٍ في ثوانٍ.
 *
 * ═══ ما هو، وما ثمنه ═══
 *
 * Evolution API خادمٌ يتحدّث بروتوكولَ «واتساب ويب» نيابةً عن هاتف.
 * فما يقدر عليه الهاتفُ يقدر عليه: يرسل لأيّ رقمٍ في أيّ وقت، بلا
 * نافذةِ أربعٍ وعشرين ساعة وبلا قوالبَ معتمَدة وبلا تطبيقٍ عند Meta.
 * والربطُ مسحُ رمزٍ من الهاتف، كما يُربط واتساب ويب تماماً.
 *
 * وثمنُه أنّه يخالف شروط Meta: الرقمُ المستعمَل عبره قد يُحظر بلا
 * إنذارٍ ولا استرجاع. وذلك قرارُ صاحب النظام لا قرارُ الكود — وقد
 * اتُّخذ بعد بيانِه.
 *
 * ═══ لماذا لم يتغيّر شيءٌ غيرُ هذا الملف ═══
 *
 * لأنّ الواجهة `WhatsAppProviderInterface` كُتبت من أوّل يوم. فصندوقُ
 * الوارد والطابور والجدولة والصلاحيات والردُّ الآلي لا تعرف Meta ولا
 * Evolution — تعرف العقدَ وحده.
 */
class EvolutionProvider implements WhatsAppProviderInterface
{
    protected ?string $lastError = null;

    protected string $baseUrl;
    protected string $apiKey;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('whatsapp.evolution.base_url', ''), '/');
        $this->apiKey = $apiKey ?? (string) config('whatsapp.evolution.api_key', '');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    // ── الاقتران ─────────────────────────────────────────────────

    /**
     * إنشاءُ نسخة المكتب وإرجاعُ رمز المسح.
     *
     * ═══ لماذا يُنشأ ثمّ يُتصل ═══
     *
     * ‏Evolution يرفض إنشاءَ نسخةٍ موجودة بخطأ 403، وهو ليس عطلاً بل
     * حالةً عاديّة: المكتب يفتح الصفحة مرّتين. فيُحاول الإنشاءُ، ثمّ
     * يُطلب الرمزُ من `connect` في الحالتين — فتُقرأ نسخةٌ جديدة
     * وقديمةٌ بنفس الطريق.
     *
     * @return array{qr: ?string, state: string, message: ?string}
     */
    public function pair(?string $phone = null): array
    {
        $this->lastError = null;

        // رقمُ المكتب — أرقامٌ بلا زائد كما يقبله الجسر. ووجودُه يعني
        // البابَ الثاني: رمزٌ من ثمانية محارف يُكتب في الهاتف بدل المسح.
        $phone = $phone === null ? null : (preg_replace('/\D+/', '', $phone) ?: null);

        if (!$this->isConfigured()) {
            $this->lastError = 'خادم Evolution غير مضبوط — العنوان أو المفتاح ناقص.';

            return ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $this->lastError];
        }

        $instance = WhatsAppSettings::evolutionInstance();

        // موصولٌ أصلاً: لا يُعرض رمزٌ لمن لا يحتاجه
        $state = $this->connectionState();

        if ($state === 'open') {
            WhatsAppSettings::setEvolutionState('open');

            return ['qr' => null, 'code' => null, 'state' => 'open', 'message' => 'الرقم موصولٌ بالفعل.'];
        }

        // ═══ الإنشاء يعيد الرمزَ بنفسه ═══
        //
        // ‏instance/create عند Evolution 2 — مع qrcode:true — يفتح الجلسة
        // وينتظر خمسَ ثوانٍ ويعيد الرمزَ في جسم الردّ. فيُقرأ من هناك
        // بدل رحلةٍ ثانية قد تصادف نسخةً لم تُحمَّل بعد.
        $created = $this->createInstance($instance, $phone);

        if ($created['qr'] !== null || $created['code'] !== null) {
            return ['qr' => $created['qr'], 'code' => $created['code'], 'state' => 'connecting', 'message' => null];
        }

        // إخفاقٌ حقيقيٌّ في الإنشاء (لا «الاسم مستعمَل») يُقال ويُوقَف عنده
        if ($created['error'] !== null) {
            $this->lastError = $created['error'];

            return ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $created['error']];
        }

        // النسخةُ قائمةٌ من قبل: يُطلب الرمزُ منها
        $this->applyWebhook($instance);
        $connect = $this->connectQr($instance, $phone);

        if ($connect['qr'] !== null || $connect['code'] !== null) {
            return ['qr' => $connect['qr'], 'code' => $connect['code'], 'state' => 'connecting', 'message' => null];
        }

        if ($connect['state'] === 'open') {
            WhatsAppSettings::setEvolutionState('open');

            return ['qr' => null, 'code' => null, 'state' => 'open', 'message' => 'الرقم موصولٌ بالفعل.'];
        }

        // ═══ الطريقُ المسدود الذي كان يقتل الاقتران للأبد ═══
        //
        // حارسُ Evolution على «create» يقول «الاسم مستعمَل» إن وُجد صفُّ
        // النسخة في قاعدته، بينما «connect» يقرأ النسخةَ من ذاكرة العملية
        // فيقول «does not exist» إن لم تكن محمَّلة. وهما يجتمعان في حالٍ
        // واحدة: نسخةٌ أُنشئت ولم يُمسح رمزُها قطّ ثم أُعيد تشغيل الجسر —
        // فلا اعتمادَ محفوظاً يُحمَّل، والصفُّ باقٍ في القاعدة.
        //
        // فالمكتب يضغط «ابدأ الاقتران» فيُنشأ ⇐ ٤٠٣، ثمّ يُوصَل ⇐ ٤٠٠
        // «النسخة غير موجودة» — أبداً، بلا رمزٍ ولا مخرج. وهذا ما كان.
        //
        // العلاجُ حذفُ الصفِّ الميت وإنشاءٌ نظيف. ولا يُفعل إلا هنا: بعد
        // أن قال الجسرُ صراحةً إنّ النسخة غير موجودة عنده، وحالتُها ليست
        // مفتوحةً ولا قيد الاتصال — فلا جلسةَ حيّةٌ تُقطع بحال.
        if ($connect['missing']) {
            $this->deleteInstance($instance);
            $fresh = $this->createInstance($instance, $phone);

            if ($fresh['qr'] !== null || $fresh['code'] !== null) {
                return ['qr' => $fresh['qr'], 'code' => $fresh['code'], 'state' => 'connecting', 'message' => null];
            }

            if ($fresh['error'] !== null) {
                $this->lastError = $fresh['error'];

                return ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $fresh['error']];
            }

            $retry = $this->connectQr($instance, $phone);

            if ($retry['qr'] !== null || $retry['code'] !== null) {
                return ['qr' => $retry['qr'], 'code' => $retry['code'], 'state' => 'connecting', 'message' => null];
            }
        }

        $this->lastError = $connect['error']
            ?? 'لم يُصدر الجسرُ رمزَ اقترانٍ الآن — أعد المحاولة بعد لحظات.';

        return ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $this->lastError];
    }

    /**
     * طلبُ الرمز من نسخةٍ قائمة.
     *
     * @return array{qr: ?string, code: ?string, state: string, missing: bool, error: ?string}
     */
    protected function connectQr(string $instance, ?string $phone = null): array
    {
        try {
            // الرقمُ يسافر في الاستعلام: الجسرُ يدمج معاملاتِ الاستعلام
            // في بيانات النسخة، فيطلب رمزَ ربطٍ من واتساب بدل المسح
            $response = $this->http()->get($this->url('instance/connect/' . $instance)
                . ($phone !== null ? '?number=' . $phone : ''));
        } catch (\Throwable) {
            return ['qr' => null, 'code' => null, 'state' => 'close', 'missing' => false, 'error' => 'تعذّر الاتصال بخادم الجسر.'];
        }

        $body = (string) $response->body();

        // «غير موجودة» تأتي بـ٤٠٠ من المتحكّم وبـ٤٠٤ من الحارس، وقد
        // تأتي بـ٢٠٠ وجسمٍ فيه error:true — فالنصُّ هو الفيصل لا الرمز
        $missing = str_contains(mb_strtolower($body), 'does not exist')
            || str_contains(mb_strtolower($body), 'not exist')
            || $response->status() === 404;

        if (!$response->successful()) {
            if (!$missing) {
                $this->failureFrom($response);
            }

            return ['qr' => null, 'code' => null, 'state' => 'close', 'missing' => $missing, 'error' => $missing ? null : $this->lastError];
        }

        // حالةٌ مفتوحة تعود من connect نفسِه حين تكون الجلسةُ حيّة
        $state = (string) ($response->json('instance.state') ?? $response->json('state') ?? '');

        return [
            'qr' => $this->qrFrom($response),
            'code' => $this->pairingCodeFrom($response),
            'state' => $state === 'open' ? 'open' : 'connecting',
            'missing' => $missing,
            'error' => null,
        ];
    }

    /**
     * حذفُ نسخةٍ ميتة — تمهيداً لإنشاءٍ نظيف.
     *
     * لا يُنادى إلا بعد أن يُقرّ الجسرُ أنّ النسخة غير موجودة عنده:
     * حذفُ نسخةٍ حيّة يعني قطعَ اقترانِ مكتبٍ يعمل، وذلك ما لا نفعله
     * من طرفنا أبداً.
     */
    protected function deleteInstance(string $instance): bool
    {
        try {
            $response = $this->http()->delete($this->url('instance/delete/' . $instance));
        } catch (\Throwable) {
            return false;
        }

        if (!$response->successful()) {
            // ‏٤٠٤ هنا نجاحٌ: لا صفَّ يُحذف أصلاً
            return $response->status() === 404;
        }

        return true;
    }

    /** حالةُ الاقتران عند الخادم: open|connecting|close. */
    public function connectionState(): string
    {
        if (!$this->isConfigured()) {
            return 'close';
        }

        try {
            $response = $this->http()->get(
                $this->url('instance/connectionState/' . WhatsAppSettings::evolutionInstance())
            );
        } catch (\Throwable) {
            return 'close';
        }

        if (!$response->successful()) {
            return 'close';
        }

        $state = (string) ($response->json('instance.state') ?? $response->json('state') ?? 'close');

        return in_array($state, ['open', 'connecting', 'close'], true) ? $state : 'close';
    }

    /** فصلُ الجلسة عند الخادم — الهاتفُ يُلغي الاقتران. */
    public function logout(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->http()->delete(
                $this->url('instance/logout/' . WhatsAppSettings::evolutionInstance())
            );
        } catch (\Throwable) {
            return false;
        }

        WhatsAppSettings::setEvolutionState('close');

        return $response->successful();
    }

    /**
     * إحياءُ اقترانٍ سقط — بلا رمزٍ يُمسح وبلا يدِ أحد.
     *
     * ═══ «ما ينفصل أبداً إلا إذا المكتب فصله» ═══
     *
     * جلسةُ واتساب ويب تسقط أحياناً: الجسرُ يُعاد تشغيله، أو الاتصال
     * ينقطع لحظةً، فتصير الحالة close والاعتمادُ عند الخادم ما زال
     * صالحاً. كان المكتب يبقى مفصولاً حتى يفتح أحدُهم الإعدادات
     * صدفةً — والرسائلُ تُقيَّد «في البوابة» أياماً.
     *
     * فيُنادى `connect`: الاعتمادُ الصالح يُعيد الفتح فوراً بلا رمز،
     * والساقطُ فعلاً يبقى ساقطاً — إعادةُ المسح قرارُ المكتب وحده،
     * ولا يُمسح اقترانُه من طرفنا بحال.
     */
    public function reconnect(): string
    {
        if (!$this->isConfigured()) {
            return 'close';
        }

        try {
            $this->http()->get($this->url('instance/connect/' . WhatsAppSettings::evolutionInstance()));
        } catch (\Throwable) {
            // الجسرُ نفسُه لا يردّ: الحالةُ التالية ستقول ذلك
        }

        try {
            $state = $this->connectionState();
        } catch (\Throwable) {
            return WhatsAppSettings::evolutionState();
        }

        WhatsAppSettings::setEvolutionState($state);

        return $state;
    }

    // ── الإرسال ──────────────────────────────────────────────────

    public function sendText(string $to, string $body): SendResult
    {
        return $this->post('message/sendText', [
            'number' => $this->recipient($to),
            'text' => $body,
        ]);
    }

    /**
     * لا قوالبَ في واتساب ويب — تُرسَل نصّاً بعد ملء متغيّراتها.
     *
     * والنصُّ يُقرأ من جدول القوالب إن وُجد (فيبقى للمكتب مكانٌ واحد
     * يحرّر منه صيغةَ رسائله)، وإلا فالمتغيّراتُ وحدها مفصولةً بأسطر
     * — وذلك أنفعُ للموكّل من ألّا يصله شيء.
     */
    public function sendTemplate(string $to, string $name, string $language, array $bodyParams = []): SendResult
    {
        return $this->sendText($to, self::renderTemplate($name, $language, $bodyParams));
    }

    /** @param array<int, string> $params */
    public static function renderTemplate(string $name, string $language, array $params): string
    {
        $body = '';

        try {
            $template = \App\Models\WhatsAppTemplate::where('name', $name)
                ->where('language', $language)
                ->first() ?? \App\Models\WhatsAppTemplate::where('name', $name)->first();

            $body = (string) ($template?->body ?? '');
        } catch (\Throwable) {
            // جدولٌ غير مهاجَر — يُبنى النصُّ من المتغيّرات وحدها
        }

        if ($body === '') {
            return implode("\n", array_filter($params, static fn ($p): bool => trim((string) $p) !== ''));
        }

        foreach ($params as $i => $value) {
            $body = str_replace('{{' . ($i + 1) . '}}', (string) $value, $body);
        }

        // متغيّرٌ لم تصله قيمة يُحذف بدل أن يصل الموكّلَ «{{3}}»
        return trim((string) preg_replace('/\{\{\s*\d+\s*\}\}/', '', $body));
    }

    public function sendMedia(
        string $to,
        string $type,
        string $mediaId,
        ?string $caption = null,
        ?string $filename = null,
    ): SendResult {
        // ‏mediaId هنا عنوانٌ أو base64 لا معرّفٌ عند Meta: الجسر لا
        // يملك مخزناً، فيرفع مع الرسالة نفسها
        return $this->post('message/sendMedia', array_filter([
            'number' => $this->recipient($to),
            'mediatype' => $type === 'sticker' ? 'image' : $type,
            'media' => $mediaId,
            'caption' => $caption,
            'fileName' => $filename,
        ], static fn ($v): bool => $v !== null));
    }

    /** لا مخزنَ وسائط في الجسر — الملفّ يُرسَل بمساره كما هو. */
    public function uploadMedia(string $absolutePath, string $mime): ?string
    {
        if (!is_readable($absolutePath)) {
            $this->lastError = 'الملفّ غير مقروء.';

            return null;
        }

        $data = @file_get_contents($absolutePath);

        return $data === false ? null : base64_encode($data);
    }

    /**
     * الوسيطُ الوارد يصل كاملاً في الحمولة — لا عنوانَ يُطلب.
     *
     * فتُعاد بياناتُه من الرسالة المحفوظة بدل نداءٍ لا وجود له عند
     * الجسر. وغيابُ الطريق ليس عطلاً يُخفى: يُقال إنّه غيرُ متاح.
     */
    public function mediaMeta(string $mediaId): ?array
    {
        $this->lastError = 'الجسرُ لا يحفظ الوسائط عنده — تصل مع الرسالة.';

        return null;
    }

    public function downloadMedia(string $url): ?string
    {
        try {
            $response = Http::timeout((int) config('whatsapp.http_timeout_s', 30))->get($url);
        } catch (\Throwable) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    public function markRead(string $wamid): bool
    {
        // الجسرُ يحتاج معرّفَ المحادثة مع معرّف الرسالة، ولا نحفظه —
        // وتعليمُ القراءة تحسينٌ لا شرطٌ لعمل شيء
        return false;
    }

    // ── الفحص ────────────────────────────────────────────────────

    public function testConnection(): array
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'خادم Evolution غير مضبوط — العنوان أو المفتاح ناقص في ملفّ البيئة.';

            return ['ok' => false, 'message' => $this->lastError];
        }

        $state = $this->connectionState();
        WhatsAppSettings::setEvolutionState($state);

        if ($state !== 'open') {
            $this->lastError = $state === 'connecting'
                ? 'النسخة تنتظر مسحَ الرمز من الهاتف.'
                : 'الرقم غير مقترن — امسح الرمز من واتساب في هاتفك.';

            return ['ok' => false, 'message' => $this->lastError];
        }

        $me = $this->instanceOwner();

        if ($me !== null) {
            WhatsAppSettings::rememberIdentity($me['number'], $me['name']);
        }

        return [
            'ok' => true,
            'message' => 'الرقم مقترنٌ ويرسل.',
            'display_phone_number' => $me['number'] ?? '',
            'verified_name' => $me['name'] ?? '',
        ];
    }

    /** لا قوالبَ معتمَدة في الجسر — النصوصُ حرّة. */
    public function fetchTemplates(): array
    {
        $this->lastError = 'لا قوالبَ في واتساب ويب — النصوصُ تُرسَل حرّة.';

        return [];
    }

    /** الويبهوك يُضبط عند الاقتران، فلا يُطلب من المكتب شيء. */
    public function registerWebhook(string $callbackUrl, string $verifyToken, array $fields): bool
    {
        return $this->applyWebhook(WhatsAppSettings::evolutionInstance());
    }

    public function subscribeAccount(): bool
    {
        return true; // لا حسابَ يُشترَك فيه — النسخةُ هي الحساب
    }

    /**
     * لا اشتراكَ منفصلٌ في الجسر: الويبهوك يُضبط على النسخة عند
     * الاقتران. فالجوابُ مشتقٌّ من الاقتران نفسه.
     */
    public function subscribedFields(): ?array
    {
        return $this->connectionState() === 'open' ? ['messages'] : [];
    }

    public function discover(): array
    {
        $me = $this->instanceOwner();

        return [
            'waba_id' => null,
            'phone_number_id' => $me['number'] ?? null,
            'display_phone' => $me['number'] ?? null,
            'choices' => [],
        ];
    }

    // ── داخلي ────────────────────────────────────────────────────

    /**
     * إنشاءُ النسخة — يعيد null عند النجاح، أو سببَ الإخفاق بالعربية.
     *
     * ونسخةٌ موجودةٌ نجاحٌ لا إخفاق: المكتب يفتح الصفحة مرّتين، وأن
     * تكون النسخةُ قائمةً هو ما نريده أصلاً.
     */
    /**
     * إنشاءُ النسخة — يعيد الرمزَ إن أُنشئت، أو سببَ الإخفاق، أو لا شيء
     * إن كانت قائمةً من قبل.
     *
     * ═══ لماذا الويبهوك في جسم الإنشاء ═══
     *
     * ‏Evolution يقبل الويبهوك داخل «instance/create» ويضبطه في نفس
     * اللحظة. وضبطُه هنا يعني أنّ نسخةً جديدةً لا تعيش لحظةً واحدة بلا
     * عنوانِ استقبال — وكان ضبطُه بطلبٍ ثانٍ يفشل أحياناً فيُوقف
     * الاقترانَ كلَّه ويبقى المكتب بلا رمز.
     *
     * @return array{qr: ?string, code: ?string, error: ?string}
     */
    protected function createInstance(string $instance, ?string $phone = null): array
    {
        try {
            $response = $this->http()->post($this->url('instance/create'), array_filter([
                'instanceName' => $instance,
                'qrcode' => true,
                'integration' => (string) config('whatsapp.evolution.integration', 'WHATSAPP-BAILEYS'),
                'webhook' => $this->webhookPayload(),
                // رقمٌ مُمرَّر ⇐ رمزُ ربطٍ بدل المسح
                'number' => $phone,
            ], static fn ($v) => $v !== null));
        } catch (\Throwable $e) {
            Log::warning('Evolution instance create failed: ' . $e->getMessage());

            return ['qr' => null, 'code' => null, 'error' => 'تعذّر الاتصال بخادم الجسر لإنشاء نسخة المكتب.'];
        }

        if ($response->successful()) {
            // الرمزُ في جسم الإنشاء نفسِه (qrcode.base64) — يُقرأ الآن
            return ['qr' => $this->qrFrom($response), 'code' => $this->pairingCodeFrom($response), 'error' => null];
        }

        $body = (string) $response->body();

        // «الاسم مستعمَل» = النسخةُ قائمة: لا رمزَ هنا ولا خطأ — يُطلب الرمزُ منها
        if (str_contains(mb_strtolower($body), 'already in use') || $response->status() === 409) {
            return ['qr' => null, 'code' => null, 'error' => null];
        }

        Log::warning('Evolution instance create rejected (' . $response->status() . '): ' . mb_substr($body, 0, 500));

        // ٥٠٠ من Evolution عند الإنشاء علامتُه الغالبة أنّ قاعدة
        // بياناته غير موصولة: النسخةُ صفٌّ في جدول، فبلا قاعدةٍ لا
        // تُنشأ. وقولُ «أخفق» وحدها يترك المشغّل يبحث في الرقم
        // والمفتاح، والعلّةُ في تنصيب الخادم لا في المكتب.
        //
        // ═══ لماذا يُفرَّق بين ٤٠٤ و٥٠٠ هنا ═══
        //
        // ‏٤٠٤ على «instance/create» ليست «نسخةٌ غير موجودة» — المسارُ
        // نفسُه غير مسجَّل عند الخادم. ويقع ذلك حين يقوم الجسرُ قبل أن
        // تكتمل هجراتُ قاعدته: يردّ على الجذر بـ٢٠٠ ويبدو حيّاً، ولم
        // تُركَّب مساراتُ النسخ بعد.
        //
        // والعلاجان مختلفان تماماً — إعادةُ تشغيلٍ بانتظارٍ أطول في
        // الأولى، وفحصُ وصلِ القاعدة في الثانية — فلا يُقال فيهما نصٌّ
        // واحد يترك المشغّل يجرّب على غير هدى.
        $probe = 'sudo bash scripts/install-evolution.sh status';

        return ['qr' => null, 'code' => null, 'error' => match (true) {
            $response->status() === 401 || $response->status() === 403
                => 'خادم الجسر رفض المفتاح — راجع EVOLUTION_API_KEY في ملفّ بيئة المكتب.',
            $response->status() === 404
                => 'خادم الجسر يعمل لكنّ مسار إنشاء النسخ غير مُركَّب عنده'
                    . ' — الأرجح أنّه قام قبل أن تكتمل هجرات قاعدته. على الخادم: ' . $probe,
            $response->status() >= 500
                => 'خادم الجسر لم يستطع إنشاء النسخة — الأرجح أنّ قاعدة بياناته غير موصولة.'
                    . ' على الخادم: ' . $probe,
            default
                => 'خادم الجسر رفض إنشاء النسخة (' . $response->status() . ').',
        }];
    }

    /**
     * حمولةُ الويبهوك بأسماء حقول Evolution 2.
     *
     * ═══ حرفان كلّفا وسائطَ الرسائل ═══
     *
     * كنّا نرسل webhookByEvents و webhookBase64 — وهي أسماءُ الإصدار
     * الأوّل. ومخطَّطُ الثاني يسمّيهما byEvents و base64 ولا يرفض
     * الزائد، فكان الطلبُ ينجح ٢٠١ والإعدادان لا يُضبطان: تصل صورةُ
     * الموكّل بلا محتوى Base64 فلا تُحفظ.
     *
     * @return array<string, mixed>
     */
    protected function webhookPayload(): array
    {
        return [
            'enabled' => true,
            'url' => url('/webhooks/evolution/' . WhatsAppSettings::evolutionSecret()),
            'byEvents' => false,
            'base64' => true,
            'events' => ['MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'],
        ];
    }

    /**
     * ضبطُ عنوان الويبهوك على النسخة.
     *
     * والسرُّ في المسار لأنّ الجسر لا يوقّع حمولاتِه: بابٌ بلا سرٍّ
     * يقبل من أيّ أحدٍ رسالةً يزعم أنّها من موكّل، فتُكتب في خيطه
     * ويقرؤها المحامي على أنّها منه.
     */
    protected function applyWebhook(string $instance): bool
    {
        $v2 = $this->webhookPayload();

        // الصيغةُ القديمة احتياطاً: جسورٌ لم تُرقَّ بعد تقرأ هذه وحدها
        $legacy = [
            'enabled' => $v2['enabled'],
            'url' => $v2['url'],
            'webhookByEvents' => false,
            'webhookBase64' => true,
            'events' => $v2['events'],
        ];

        // ثلاثُ صيغٍ تُجرَّب بالترتيب: مغلَّفةٌ بـv2، مغلَّفةٌ بالقديمة،
        // ثمّ مسطّحة. ومخطَّطُ الإصدار الثاني يشترط الغلاف «webhook»،
        // فالمسطّحةُ آخرُ ما يُجرَّب لا أوّلُه.
        $last = null;

        foreach ([['webhook' => $v2], ['webhook' => $legacy], $v2] as $payload) {
            try {
                $response = $this->http()->post($this->url('webhook/set/' . $instance), $payload);
            } catch (\Throwable) {
                $this->lastError = 'تعذّر الاتصال بخادم الجسر لضبط عنوان الاستقبال.';

                return false;
            }

            if ($response->successful()) {
                return true;
            }

            $last = $response;
        }

        $status = $last?->status() ?? 0;

        Log::warning('Evolution webhook set failed (' . $status . '): '
            . mb_substr((string) $last?->body(), 0, 300));

        $this->lastError = 'خادم الجسر رفض ضبط عنوان الاستقبال (' . $status . ').';

        return false;
    }

    /** @return array{number: string, name: string}|null */
    protected function instanceOwner(): ?array
    {
        try {
            $response = $this->http()->get($this->url('instance/fetchInstances'), [
                'instanceName' => WhatsAppSettings::evolutionInstance(),
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $rows = (array) $response->json();
        $row = $rows[0] ?? $rows;
        $instance = (array) ($row['instance'] ?? $row);

        $owner = (string) ($instance['owner'] ?? $instance['ownerJid'] ?? '');
        $number = $owner !== '' ? (string) strtok($owner, '@') : '';

        return [
            'number' => $number,
            'name' => (string) ($instance['profileName'] ?? $instance['profilename'] ?? ''),
        ];
    }

    /**
     * رمزُ الربط بالرقم — بابٌ ثانٍ حين يتعذّر المسح.
     *
     * ═══ لماذا بابان ═══
     *
     * واتساب يرفض ربطَ أجهزةٍ جديدةٍ أحياناً («Can't link new devices
     * right now») بعد محاولاتٍ متكرّرة، فيقف المكتبُ أمام رمزٍ صحيحٍ
     * لا يُقبل. والربطُ بالرقم مسارٌ آخر عند واتساب نفسِه: ثمانيةُ
     * محارفَ تُكتب في الهاتف، وهو الخيارُ المعروض في شاشة المسح
     * («Link with phone number instead»).
     */
    protected function pairingCodeFrom(Response $response): ?string
    {
        $code = $response->json('pairingCode')
            ?? $response->json('qrcode.pairingCode')
            ?? $response->json('qr.pairingCode');

        return is_string($code) && $code !== '' ? $code : null;
    }

    protected function qrFrom(Response $response): ?string
    {
        $qr = $response->json('base64')
            ?? $response->json('qrcode.base64')
            ?? $response->json('qr.base64');

        if (!is_string($qr) || $qr === '') {
            $code = $response->json('code') ?? $response->json('qrcode.code');

            return is_string($code) && $code !== '' ? 'text:' . $code : null;
        }

        // بعضُ الإصدارات تُرجعه ببادئة data: وبعضُها بلا
        return str_starts_with($qr, 'data:') ? $qr : 'data:image/png;base64,' . $qr;
    }

    /** @param array<string, mixed> $payload */
    protected function post(string $path, array $payload): SendResult
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            return SendResult::failed(null, 'خادم Evolution غير مضبوط.', retryable: false);
        }

        try {
            $response = $this->http()->post(
                $this->url($path . '/' . WhatsAppSettings::evolutionInstance()),
                $payload,
            );
        } catch (\Throwable) {
            // انقطاعُ شبكةٍ عابر: تُعاد المحاولة ولا تُسقَط رسالةُ موكّل
            return SendResult::failed(null, 'تعذّر الاتصال بخادم الإرسال.', retryable: true);
        }

        if (!$response->successful()) {
            $this->failureFrom($response);

            // ‏401/403 اعتمادٌ خاطئ — إعادةُ المحاولة لا تُصلحه.
            // و404 نسخةٌ غير موجودة: تحتاج اقتراناً لا محاولةً أخرى.
            //
            // ═══ لكنّ ٤٠٠ «Connection Closed» عابرةٌ لا دائمة ═══
            //
            // ‏Evolution يردّ بـ٤٠٠ حين تكون الجلسةُ ساقطةً لحظةَ الإرسال.
            // وعدُّها دائمةً كان يقتل رسالةَ الموكّل نهائياً ويكتب
            // «أخفق» — بينما الكنسُ يعيد وصلَ الرقم بعد دقيقة والرسالةُ
            // ماتت بلا إعادة. فما كان سببُه سقوطَ الاتصال يُعاد.
            $transient = $response->status() === 400
                && preg_match('/connection closed|connection lost|not connected|closed connection|no session/i', (string) $this->lastError) === 1;

            $permanent = !$transient && in_array($response->status(), [400, 401, 403, 404], true);

            return SendResult::failed((string) $response->status(), (string) $this->lastError, retryable: !$permanent);
        }

        $id = $response->json('key.id') ?? $response->json('messageId');

        return SendResult::sent(is_string($id) ? $id : ('evo.' . uniqid()));
    }

    protected function failureFrom(Response $response): void
    {
        // ═══ النصُّ الحقيقي في response.message لا في message ═══
        //
        // جسمُ الخطأ عند Evolution v2 هكذا:
        //   {"status":400,"error":"Bad Request",
        //    "response":{"message":["Connection Closed"]}}
        //
        // فلا مفتاحَ «message» في الأعلى، وكان يقع الاختيارُ على
        // «error» فيُخزَّن السببُ «Bad Request» — وهي لا تفرّق بين
        // «الرقم ليس على واتساب» (عطلٌ دائم) و«انقطع الاتصال»
        // (عابرٌ تُعاد معه المحاولة). وهذا هو ما يُعرض للمكتب حين
        // يسأل: لماذا لم تصل؟
        $message = $response->json('response.message')
            ?? $response->json('message')
            ?? $response->json('error')
            ?? '';

        if (is_array($message)) {
            $message = implode(' — ', array_map(static fn ($m): string => (string) $m, $message));
        }

        $message = trim((string) $message);

        $this->lastError = match (true) {
            $response->status() === 401 || $response->status() === 403
                => 'خادم Evolution رفض المفتاح — راجع EVOLUTION_API_KEY.',
            $response->status() === 404
                => 'نسخةُ المكتب غير موجودة على الخادم — أعد الاقتران.',
            $message !== '' => mb_substr($message, 0, 190),
            default => 'أخفق الطلب عند خادم Evolution (' . $response->status() . ').',
        };

        WhatsAppSettings::recordError((string) $this->lastError);
    }

    /** رقمُ المستقبِل بالصيغة التي يقبلها الجسر: أرقامٌ بلا زائد. */
    protected function recipient(string $to): string
    {
        return preg_replace('/\D+/', '', $to) ?: $to;
    }

    protected function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    protected function http()
    {
        return Http::withHeaders(['apikey' => $this->apiKey])
            ->timeout((int) config('whatsapp.http_timeout_s', 30))
            ->connectTimeout((int) config('whatsapp.connect_timeout_s', 10))
            ->acceptJson();
    }
}
