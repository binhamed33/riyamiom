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
    public function pair(): array
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'خادم Evolution غير مضبوط — العنوان أو المفتاح ناقص.';

            return ['qr' => null, 'state' => 'close', 'message' => $this->lastError];
        }

        $instance = WhatsAppSettings::evolutionInstance();

        // موصولٌ أصلاً: لا يُعرض رمزٌ لمن لا يحتاجه
        $state = $this->connectionState();

        if ($state === 'open') {
            WhatsAppSettings::setEvolutionState('open');

            return ['qr' => null, 'state' => 'open', 'message' => 'الرقم موصولٌ بالفعل.'];
        }

        // ═══ العطلُ الحقيقي لا صداه ═══
        //
        // كان الإنشاءُ يُبتلع صامتاً، فيصل المكتبُ إلى `connect` ويجد
        // ٤٠٤ فيُقال له «النسخة غير موجودة — أعد الاقتران». وهو يعيد
        // ويعيد، والعلّةُ في خطوةٍ قبلها لم تُذكر قطّ.
        //
        // فيُقال سببُ الإنشاء نفسه، ويُتوقّف عنده.
        $created = $this->createInstance($instance);

        if ($created !== null) {
            $this->lastError = $created;

            return ['qr' => null, 'state' => 'close', 'message' => $created];
        }

        if (!$this->applyWebhook($instance)) {
            // ويبهوكٌ لم يُضبط يعني رمزاً يُمسح ثمّ صمتاً تامّاً: لا
            // رسالةَ تصل ولا سبب. يُقال قبل المسح لا بعده.
            $reason = $this->lastError ?: 'تعذّر ضبط عنوان الاستقبال على النسخة.';
            $this->lastError = $reason;

            return ['qr' => null, 'state' => 'close', 'message' => $reason];
        }

        try {
            $response = $this->http()->get($this->url('instance/connect/' . $instance));
        } catch (\Throwable) {
            $this->lastError = 'تعذّر الاتصال بخادم Evolution.';

            return ['qr' => null, 'state' => 'close', 'message' => $this->lastError];
        }

        if (!$response->successful()) {
            $this->failureFrom($response);

            return ['qr' => null, 'state' => 'close', 'message' => $this->lastError];
        }

        return [
            'qr' => $this->qrFrom($response),
            'state' => 'connecting',
            'message' => null,
        ];
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
    protected function createInstance(string $instance): ?string
    {
        try {
            $response = $this->http()->post($this->url('instance/create'), [
                'instanceName' => $instance,
                'qrcode' => true,
                'integration' => (string) config('whatsapp.evolution.integration', 'WHATSAPP-BAILEYS'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Evolution instance create failed: ' . $e->getMessage());

            return 'تعذّر الاتصال بخادم الجسر لإنشاء نسخة المكتب.';
        }

        if ($response->successful()) {
            return null;
        }

        $body = (string) $response->body();

        // «الاسم مستعمَل» = النسخةُ قائمة، وهو المطلوب
        if (str_contains(mb_strtolower($body), 'already in use') || $response->status() === 409) {
            return null;
        }

        Log::warning('Evolution instance create rejected (' . $response->status() . '): ' . mb_substr($body, 0, 500));

        // ٥٠٠ من Evolution عند الإنشاء علامتُه الغالبة أنّ قاعدة
        // بياناته غير موصولة: النسخةُ صفٌّ في جدول، فبلا قاعدةٍ لا
        // تُنشأ. وقولُ «أخفق» وحدها يترك المشغّل يبحث في الرقم
        // والمفتاح، والعلّةُ في تنصيب الخادم لا في المكتب.
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

        return match (true) {
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
        };
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
        $url = url('/webhooks/evolution/' . WhatsAppSettings::evolutionSecret());

        $payload = [
            'webhook' => [
                'enabled' => true,
                'url' => $url,
                'webhookByEvents' => false,
                'webhookBase64' => true,
                'events' => ['MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'],
            ],
        ];

        try {
            $response = $this->http()->post($this->url('webhook/set/' . $instance), $payload);
        } catch (\Throwable) {
            return false;
        }

        if (!$response->successful()) {
            // ‏Evolution غيّر شكلَ هذه الحمولة بين إصداراته: تُجرَّب
            // الصيغةُ المسطّحة قبل أن يُقال إنّها أخفقت
            try {
                $response = $this->http()->post($this->url('webhook/set/' . $instance), $payload['webhook']);
            } catch (\Throwable) {
                $this->lastError = 'تعذّر الاتصال بخادم الجسر لضبط عنوان الاستقبال.';

                return false;
            }
        }

        if (!$response->successful()) {
            Log::warning('Evolution webhook set failed (' . $response->status() . '): '
                . mb_substr((string) $response->body(), 0, 300));

            $this->lastError = 'خادم الجسر رفض ضبط عنوان الاستقبال (' . $response->status() . ').';

            return false;
        }

        return true;
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
            $permanent = in_array($response->status(), [400, 401, 403, 404], true);

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
