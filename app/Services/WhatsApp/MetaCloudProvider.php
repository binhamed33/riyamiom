<?php

namespace App\Services\WhatsApp;

use App\Support\WhatsAppSettings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * مزوّد Meta WhatsApp Cloud API — الرسمي.
 *
 * ═══ لماذا الرسمي وحده ═══
 *
 * البدائل غير الرسمية تحاكي بروتوكول «واتساب ويب»، وهي مخالفةٌ لشروط
 * Meta تُعرّض الرقم للحظر بلا إنذارٍ ولا استرجاع. ورقمُ مكتب المحاماة
 * معروفٌ لموكّليه ومكتوبٌ في وكالاتهم — حظرُه ليس عطلاً تقنياً بل
 * قطعُ صلةٍ بالموكّلين.
 *
 * ═══ ما يخصّ هذا الصنف وحده ═══
 *
 * الرمز ومعرّف الرقم يُقرآن من إعدادات هذا المكتب لا من ملف بيئةٍ
 * مشترك، ولا يُكتب أيٌّ منهما في سجلٍّ ولا في رسالة خطأ.
 *
 * ═══ قاعدةُ الصدق في هذا الصنف ═══
 *
 * كلُّ طريقٍ يعود بفشل — [] أو null أو false — يترك سببَه في
 * ‏$lastError قبل أن يعود. وإلّا صار «انقطاعُ الشبكة» و«لا شيء عندك»
 * شيئاً واحداً في عين المستخدم: يقول أمرُ المزامنة «لم تصل قوالب»
 * لمكتبٍ قوالبُه عند Meta موجودة، ويقول ملفُّ الوسائط «لا مستند»
 * لمستندٍ لم يُقرأ أصلاً. والقاعدةُ تُكسر في اتجاهٍ واحد فقط: نجاحٌ
 * صادقٌ بلا نتيجة (حسابٌ بلا قوالب فعلاً) يترك $lastError فارغاً —
 * فيُميّز من يقرأ بين «لا شيء» و«لم أستطع أن أعرف».
 *
 * ولذلك يُصفَّر $lastError في مطلع كل عملية: خطأُ عمليةٍ سابقة
 * يُنسب إلى عمليةٍ نجحت كذبةٌ من الجهة الأخرى.
 */
class MetaCloudProvider implements WhatsAppProviderInterface
{
    protected ?string $token;
    protected ?string $phoneNumberId;
    protected ?string $lastError = null;
    protected ?int $lastStatus = null;

    public function __construct(?string $token = null, ?string $phoneNumberId = null)
    {
        $this->token = $token ?: WhatsAppSettings::accessToken();
        $this->phoneNumberId = $phoneNumberId ?: WhatsAppSettings::phoneNumberId();
    }

    public function isConfigured(): bool
    {
        return filled($this->token) && filled($this->phoneNumberId);
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    // ── الإرسال ──────────────────────────────────────────────────

    public function sendText(string $to, string $body): SendResult
    {
        return $this->send([
            'type' => 'text',
            // preview_url مطفأة عمداً: معاينةُ رابطٍ في رسالةٍ قانونية
            // تجلب من الخارج صورةً لا يملك المكتب محتواها
            'text' => ['preview_url' => false, 'body' => $body],
        ], $to);
    }

    public function sendTemplate(string $to, string $name, string $language, array $bodyParams = []): SendResult
    {
        $template = [
            'name' => $name,
            'language' => ['code' => $language],
        ];

        if ($bodyParams !== []) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    static fn ($value) => ['type' => 'text', 'text' => (string) $value],
                    array_values($bodyParams)
                ),
            ]];
        }

        return $this->send(['type' => 'template', 'template' => $template], $to);
    }

    public function sendMedia(
        string $to,
        string $type,
        string $mediaId,
        ?string $caption = null,
        ?string $filename = null,
    ): SendResult {
        $media = ['id' => $mediaId];

        if (filled($caption) && in_array($type, ['image', 'video', 'document'], true)) {
            $media['caption'] = $caption;
        }

        // اسمُ الملف للمستندات وحده — تُرسله Meta كما هو للعميل، ومن
        // دونه يصل المستند باسمٍ عشوائي لا يدلّ على شيء
        if (filled($filename) && $type === 'document') {
            $media['filename'] = $filename;
        }

        return $this->send(['type' => $type, $type => $media], $to);
    }

    /**
     * جسمُ الطلب واحدٌ لكل الأنواع — يختلف مفتاحُ المحتوى وحده.
     */
    protected function send(array $payload, string $to): SendResult
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'لم يُربط رقم واتساب لهذا المكتب بعد.';

            return SendResult::failed(null, $this->lastError, retryable: false);
        }

        $body = array_merge([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalize($to),
        ], $payload);

        try {
            $response = $this->http()->post($this->url($this->phoneNumberId . '/messages'), $body);
        } catch (\Throwable $e) {
            // انقطاعُ شبكةٍ أو مهلة — يستحقّ إعادةً بلا شكّ
            Log::warning('WhatsApp send transport error: ' . $e->getMessage());
            $this->lastError = 'تعذّر الاتصال بخدمة واتساب.';

            return SendResult::failed(null, $this->lastError, retryable: true);
        }

        $this->lastStatus = $response->status();

        if ($response->successful()) {
            $wamid = $response->json('messages.0.id');

            if (is_string($wamid) && $wamid !== '') {
                return SendResult::sent($wamid);
            }

            // ٢٠٠ بلا معرّف: لا نستطيع تتبّع هذه الرسالة ولا مطابقة
            // حالتها لاحقاً — تُعامَل فشلاً صريحاً لا نجاحاً صامتاً
            $this->lastError = 'ردٌّ ناجح بلا معرّف رسالة.';

            return SendResult::failed(null, $this->lastError, retryable: true);
        }

        return $this->failureFrom($response);
    }

    // ── الوسائط ──────────────────────────────────────────────────

    public function uploadMedia(string $absolutePath, string $mime): ?string
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'لم يُربط رقم واتساب لهذا المكتب بعد.';

            return null;
        }

        // ملفٌّ اختفى من القرص بين اختيارِه وإرسالِه: يُقال صراحةً
        // بدل «تعذّر الإرسال» الذي يُرسل الموظّف يفتّش عن عطلٍ في الشبكة
        if (!is_file($absolutePath)) {
            $this->lastError = 'الملف غير موجود على الخادم — لم يُرفع شيء إلى واتساب.';

            return null;
        }

        try {
            $response = Http::withToken($this->token)
                ->connectTimeout((int) config('whatsapp.connect_timeout_s', 10))
                ->timeout((int) config('whatsapp.http_timeout_s', 30))
                ->attach('file', file_get_contents($absolutePath), basename($absolutePath), ['Content-Type' => $mime])
                ->post($this->url($this->phoneNumberId . '/media'), [
                    'messaging_product' => 'whatsapp',
                    'type' => $mime,
                ]);

            if ($response->successful()) {
                $id = $response->json('id');

                if (is_string($id) && $id !== '') {
                    return $id;
                }

                // ردٌّ ناجحٌ بلا معرّف: لا يصلح للإرسال، ولا يجوز أن
                // يعود كـnull صامتٍ يُقرأ «فشلٌ مجهول»
                $this->lastError = 'رُفع الملف بلا معرّف من واتساب — لا يمكن إرساله.';

                return null;
            }

            $this->failureFrom($response);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp media upload failed: ' . $e->getMessage());
            $this->lastError = 'تعذّر رفع الملف إلى واتساب.';
        }

        return null;
    }

    public function mediaMeta(string $mediaId): ?array
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'لم يُربط رقم واتساب لهذا المكتب بعد.';

            return null;
        }

        try {
            $response = $this->http()->get($this->url($mediaId));

            if (!$response->successful()) {
                $this->failureFrom($response);

                return null;
            }

            $url = (string) $response->json('url');

            // بلا عنوانٍ لا تنزيل. يُعاد null بسببٍ مكتوب بدل مصفوفةٍ
            // «ناجحة» عنوانُها فارغ — تُقرأ عند المتصل فشلاً بلا تفسير
            if ($url === '') {
                $this->lastError = 'لم تُعطِ واتساب عنوان تنزيلٍ لهذا الملف.';

                return null;
            }

            return [
                'url' => $url,
                'mime' => (string) $response->json('mime_type'),
                'sha256' => (string) $response->json('sha256'),
                'size' => (int) $response->json('file_size'),
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp media meta failed: ' . $e->getMessage());
            $this->lastError = 'تعذّر الاتصال بخدمة واتساب لقراءة بيانات الملف.';

            return null;
        }
    }

    public function downloadMedia(string $url): ?string
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'لم يُربط رقم واتساب لهذا المكتب بعد.';

            return null;
        }

        // العنوان الذي تُرجعه Meta يعيش خمس دقائق ويطلب الرمز في
        // الترويسة رغم ذلك — تنزيلٌ بلا رمزٍ يعود 401 بلا تفسير
        try {
            $response = Http::withToken($this->token)
                ->connectTimeout((int) config('whatsapp.connect_timeout_s', 10))
                ->timeout((int) config('whatsapp.http_timeout_s', 30))
                ->get($url);

            if (!$response->successful()) {
                // ‏401 هنا تعني رمزاً باطلاً، و404 عنواناً مضت دقائقُه
                // الخمس. الفرقُ يهمّ من يحاول الحفظ ثانيةً — فيُترجَم
                $this->failureFrom($response);

                return null;
            }

            $body = $response->body();

            if ($body === '') {
                $this->lastError = 'وصل الملف فارغاً من واتساب.';

                return null;
            }

            return $body;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp media download failed: ' . $e->getMessage());
            $this->lastError = 'تعذّر تنزيل الملف من واتساب.';

            return null;
        }
    }

    public function markRead(string $wamid): bool
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'لم يُربط رقم واتساب لهذا المكتب بعد.';

            return false;
        }

        try {
            $response = $this->http()->post($this->url($this->phoneNumberId . '/messages'), [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $wamid,
            ]);

            if (!$response->successful()) {
                $this->lastError = 'تعذّر تعليم الرسالة مقروءةً عند واتساب.';

                return false;
            }

            return true;
        } catch (\Throwable) {
            // إخفاقُ «علامة القراءة» لا يستحقّ إفشال أي عملية — لكنّه
            // يترك سببَه كغيره، فلا يُقرأ false على أنّه «لا شيء حدث»
            $this->lastError = 'تعذّر الاتصال بخدمة واتساب لتعليم الرسالة مقروءة.';

            return false;
        }
    }

    // ── الفحص والقوالب ───────────────────────────────────────────

    public function testConnection(): array
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'أكمل الرمز ومعرّف الرقم أولاً.';

            return ['ok' => false, 'message' => $this->lastError];
        }

        try {
            $response = $this->http()->get($this->url($this->phoneNumberId), [
                'fields' => 'display_phone_number,verified_name,quality_rating',
            ]);
        } catch (\Throwable) {
            // ‏whatsapp:doctor يعرض getLastError() عند الإخفاق — ولو تُرك
            // فارغاً هنا لقال «بلا تفسير» عن انقطاعِ شبكةٍ نعرف سببه
            $this->lastError = 'تعذّر الاتصال بخدمة واتساب. تحقّق من الشبكة وحاول مجدداً.';

            return ['ok' => false, 'message' => $this->lastError];
        }

        if (!$response->successful()) {
            $this->failureFrom($response);
            WhatsAppSettings::recordError((string) $this->lastError);

            return ['ok' => false, 'message' => (string) $this->lastError];
        }

        $display = (string) $response->json('display_phone_number');
        $name = (string) $response->json('verified_name');
        WhatsAppSettings::rememberIdentity($display, $name);

        return [
            'ok' => true,
            'message' => 'الاتصال سليم — الرقم متاح للإرسال.',
            'display_phone_number' => $display,
            'verified_name' => $name,
            'quality_rating' => (string) $response->json('quality_rating'),
        ];
    }

    /**
     * قوالبُ الحساب عند Meta.
     *
     * ═══ لماذا لا تكفي القائمة الفارغة ═══
     *
     * القائمةُ الفارغة جوابان لا جواب: «حسابُك بلا قوالب» و«لم أصل إلى
     * Meta أصلاً». ومن يقرأها — أمرُ whatsapp:sync-templates وصفحةُ
     * الإعدادات — يقول للمكتب أحدَهما. فيُترك السببُ في $lastError عند
     * كل إخفاق، ويُترك فارغاً وحده حين يكون الفراغ حقيقةً جاءت من Meta.
     */
    public function fetchTemplates(): array
    {
        $this->lastError = null;
        $waba = WhatsAppSettings::wabaId();

        if (!$this->isConfigured()) {
            $this->lastError = 'لم يُربط رقم واتساب لهذا المكتب بعد.';

            return [];
        }

        if (!filled($waba)) {
            $this->lastError = 'معرّف حساب الأعمال (WABA ID) غير مضبوط — والقوالب تُقرأ منه.';

            return [];
        }

        try {
            $response = $this->http()->get($this->url($waba . '/message_templates'), ['limit' => 100]);

            if (!$response->successful()) {
                $this->failureFrom($response);

                return [];
            }

            return (array) $response->json('data', []);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp template sync failed: ' . $e->getMessage());
            // انقطاعُ شبكةٍ يُبتلع في السجلّ وحده كان يخرج «لا قوالب» —
            // فيُطمأنّ مكتبٌ قوالبُه معتمَدةٌ إلى أنّ عليه إنشاءها
            $this->lastError = 'تعذّر الاتصال بخدمة واتساب لجلب القوالب.';

            return [];
        }
    }

    /**
     * هل اشترك تطبيقُنا في حقول هذا الحساب — وفي أيّها؟
     *
     * ═══ لماذا يُسأل Meta ولا يُفترض ═══
     *
     * أكثرُ ما يتعثّر فيه المكتب أن يسجّل عنوانَ الويبهوك وينسى
     * الاشتراكَ في الحقول (زرّ Manage تحته). فيرى «Verified» عند Meta
     * ويظنّ أنّه أتمّ، ولا تصله رسالةٌ واحدة أبداً — ولا شيءَ في
     * نظامنا يعرف السبب، لأنّ عدم الوصول لا يترك أثراً.
     *
     * فيُسأل الحسابُ عن اشتراكاته، ويُقال للمكتب: «العنوان مسجَّل،
     * والحقول لا» — وهي الجملةُ التي كان يبحث عنها ساعة.
     *
     * @return array<int, string>|null
     */
    public function subscribedFields(): ?array
    {
        $this->lastError = null;
        $waba = WhatsAppSettings::wabaId();

        if (!$this->isConfigured()) {
            $this->lastError = 'لم يُربط رقم واتساب لهذا المكتب بعد.';

            return null;
        }

        if (!filled($waba)) {
            $this->lastError = 'معرّف حساب الأعمال (WABA ID) غير مضبوط — والاشتراكات تُقرأ منه.';

            return null;
        }

        try {
            $response = $this->http()->get($this->url($waba . '/subscribed_apps'));
        } catch (\Throwable) {
            $this->lastError = 'تعذّر الاتصال بخدمة واتساب للسؤال عن الاشتراكات.';

            return null;
        }

        if (!$response->successful()) {
            $this->failureFrom($response);

            return null;
        }

        $fields = [];

        foreach ((array) $response->json('data', []) as $app) {
            foreach ((array) ($app['subscribed_fields'] ?? []) as $field) {
                $fields[] = (string) $field;
            }
        }

        return array_values(array_unique($fields));
    }

    // ── داخلي ────────────────────────────────────────────────────

    protected function http()
    {
        return Http::withToken($this->token)
            ->connectTimeout((int) config('whatsapp.connect_timeout_s', 10))
            ->timeout((int) config('whatsapp.http_timeout_s', 30))
            ->acceptJson();
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('whatsapp.graph_base', 'https://graph.facebook.com'), '/')
            . '/' . trim((string) config('whatsapp.graph_version', 'v23.0'), '/')
            . '/' . ltrim($path, '/');
    }

    /** الرقم كما تريده Meta: أرقامٌ فقط بمفتاح الدولة بلا «+» ولا فواصل. */
    protected function normalize(string $to): string
    {
        $digits = preg_replace('/\D+/', '', $to) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // رقمٌ محلّي بلا مفتاح دولة: يُفترض عُمان — وهو بلدُ المنصّة
        // وموكّليها. الافتراضُ يُكتب هنا مرّةً بدل أن يُخمّن في كل موضع.
        if (strlen($digits) === 8) {
            $digits = '968' . $digits;
        } elseif (strlen($digits) === 9 && str_starts_with($digits, '0')) {
            $digits = '968' . substr($digits, 1);
        }

        return $digits;
    }

    /**
     * ترجمةُ خطأ Meta إلى رسالةٍ مفهومة + حكمٍ على قابليّة الإعادة.
     *
     * ═══ لماذا لا يُعرض نصُّ Meta كما هو ═══
     *
     * ردُّها إنجليزيٌّ تقنيّ وقد يحمل معرّفاتٍ داخلية، ورؤيتُه لا تفيد
     * محامياً. فيُسجَّل التفصيل في السجلّ ويُعرض للمستخدم ما يستطيع أن
     * يفعل به شيئاً.
     */
    protected function failureFrom(Response $response): SendResult
    {
        $status = $response->status();
        $code = $response->json('error.code');
        $title = (string) ($response->json('error.error_user_title') ?? $response->json('error.message') ?? '');

        Log::error('WhatsApp API error ' . $status . ' code=' . json_encode($code) . ' — ' . $title);

        // ١٣١٠٢٦: الرقم ليس على واتساب. ١٣١٠٤٧: مضت نافذة الأربع
        // والعشرين ساعة فلا يجوز إلا قالب. كلاهما لا تُصلحه إعادة.
        $permanent = [131026, 131047, 131051, 131052, 132000, 132001, 132005, 132007, 132012, 132015, 133010, 100];

        // وفي المقابل: رموزٌ ترسلها Meta بحالة 400 وهي ازدحامٌ يزول لا
        // خطأٌ في الطلب — سقفُ الإنتاجية (130429)، حدُّ الإزعاج (131048)،
        // حدُّ الزوج مرسِلٍ ومستقبِل (131056)، حدُّ التسجيل (133016)،
        // وحدُّ نداءات التطبيق (4 و80007).
        //
        // وقاعدةُ «كلُّ 4xx نهائية» في آخر السلّم أدناه كانت تحكم عليها
        // بالإعدام: رسالةُ موكّلٍ تُلغى إلى الأبد لأنّ الدقيقة كانت
        // مزدحمة، والمكتب يقرأ «تعذّر الإرسال» فيظنّ الرقم معطوباً.
        // فتُستثنى قبل النظر إلى الحالة.
        $transient = [4, 80007, 130429, 131048, 131056, 133016];

        $retryable = match (true) {
            in_array((int) $code, $permanent, true) => false,
            in_array((int) $code, $transient, true) => true,
            $status === 429 => true,              // ازدحامٌ يزول
            $status >= 500 => true,               // عطلٌ عندهم
            $status === 401 || $status === 403 => false, // رمزٌ باطل
            default => $status >= 400 && $status < 500 ? false : true,
        };

        $this->lastError = match (true) {
            (int) $code === 131026 => 'هذا الرقم غير مسجَّل في واتساب.',
            (int) $code === 131047 => 'مضت أربعٌ وعشرون ساعة على آخر رسالة من العميل — لا يمكن إلا إرسال قالب معتمَد.',
            (int) $code === 131051 => 'نوع الرسالة غير مدعوم.',
            (int) $code === 132000 || (int) $code === 132001 => 'القالب غير معتمَد أو غير موجود بهذا الاسم واللغة.',
            (int) $code === 132005 || (int) $code === 132007 => 'محتوى القالب لا يطابق ما اعتُمد عند Meta.',
            (int) $code === 133010 => 'الرقم غير مُسجَّل في حساب الأعمال — أكمل تسجيله في Meta.',
            in_array((int) $code, $transient, true) => 'تجاوز الحدّ المسموح من الرسائل مؤقتاً — تُعاد المحاولة تلقائياً.',
            $status === 401 || $status === 403 => 'رمز واتساب غير صالح أو انتهت صلاحيته — حدّثه من الإعدادات.',
            $status === 429 => 'تجاوز الحدّ المسموح من الرسائل مؤقتاً — تُعاد المحاولة تلقائياً.',
            $status >= 500 => 'خدمة واتساب غير متاحة مؤقتاً — تُعاد المحاولة تلقائياً.',
            default => 'تعذّر إرسال الرسالة عبر واتساب.',
        };

        return SendResult::failed($code !== null ? (string) $code : (string) $status, $this->lastError, $retryable);
    }
}
