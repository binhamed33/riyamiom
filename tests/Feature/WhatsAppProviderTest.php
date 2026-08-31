<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\WhatsApp\MetaCloudProvider;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * طبقةُ مزوّد واتساب — ما يخرج إلى Meta، وما يُقال للمكتب حين لا يخرج.
 *
 * ═══ ما تحرسه هذه الاختبارات ═══
 *
 * ١) شكلُ السلك: العنوان والرمز وجسمُ الطلب كما توثّقها Meta حرفاً
 *    بحرف. خطأٌ في مفتاحٍ واحد يعني رفضاً عند أوّل رسالةٍ حقيقية —
 *    ولا يظهر في أيّ شاشةٍ عندنا قبل ذلك.
 *
 * ٢) الصدق عند الإخفاق: كلُّ فشلٍ يترك سببَه المكتوب بالعربية. فالردُّ
 *    الفارغ من دون سببٍ محفوظ يُقرأ «لا قوالب لديك» و«لا مستند» —
 *    وهما جوابان قد يكونان كذباً على مكتبٍ الشبكةُ عنده مقطوعة.
 *
 * ٣) حكمُ الإعادة: التمييز بين فشلٍ يزول وفشلٍ لا تُصلحه ألف إعادة.
 *    الخطأ في الاتجاه الأوّل يُضيع رسالةَ موكّل، وفي الثاني يُغرق
 *    الطابور بمحاولاتٍ مصيرُها الرفض ذاته.
 *
 * ٤) لا يُثبَّت هنا إصدارُ Graph نصّاً: العنوان يُبنى من
 *    ‏config('whatsapp.graph_version') كما يبنيه المزوّد، فترقيةُ
 *    الإصدار في الإعداد لا تكسر اختباراً واحداً.
 */
class WhatsAppProviderTest extends TestCase
{
    use RefreshDatabase;

    /** رمزٌ وهميّ يشبه رموز Meta — يُبحث عنه في كل رسالة خطأ فلا يوجد. */
    private const TOKEN = 'EAAG-office-secret-token-9f2c';
    private const PHONE_ID = '111222333';
    private const WABA_ID = '999888777';

    /** ما تردّ به Meta الآن — يُبدَّل داخل الاختبار الواحد. */
    private \Closure $responder;

    protected function setUp(): void
    {
        parent::setUp();

        // الاعتماد يُخزَّن مشفَّراً في إعدادات هذا المكتب — كما في الإنتاج
        WhatsAppSettings::store(self::TOKEN, self::PHONE_ID, self::WABA_ID);

        $this->responder = fn () => Http::response([], 200);

        /*
         * مُجيبٌ واحد يُسجَّل مرّةً ويُبدَّل محتواه.
         *
         * ‏Http::fake تُراكم مُجيبيها ويفوز أوّلُ من طابق: فاستدعاؤها
         * مرّتين في اختبارٍ واحد يجعل الترتيب الثاني بلا أثر، ويمرّ
         * الاختبار وهو يفحص الحالة الأولى مرّتين. وهذا الفخّ يُفقد
         * الثقة بالاختبار نفسه — فيُغلق هنا مرّةً واحدة.
         */
        Http::fake(fn (Request $request) => ($this->responder)($request));
    }

    // ── شكلُ ما يخرج إلى Meta ────────────────────────────────────

    /**
     * الرسالة النصّية تذهب إلى العنوان الذي توثّقه Meta، بالرمز، وبجسمٍ
     * مطابقٍ لما تنتظره.
     *
     * ولولا تثبيتُه هنا لما ظهر خطأُ مفتاحٍ واحد إلا في رفضِ Meta لرسالةِ
     * موكّلٍ حقيقيّ — بعد أن تكون الرسالة قد كُتبت في الخيط وظنّ المحامي
     * أنّه ردّ.
     */
    public function test_send_text_posts_to_the_endpoint_meta_documents(): void
    {
        $this->metaAccepts('wamid.TEXT1');

        $result = (new MetaCloudProvider())->sendText('96891234567', 'وصلتنا رسالتكم');

        $this->assertTrue($result->ok);

        $request = $this->lastRequest();

        $this->assertSame('POST', $request->method());
        $this->assertSame($this->endpoint(self::PHONE_ID . '/messages'), $request->url());
        $this->assertTrue(
            $request->hasHeader('Authorization', 'Bearer ' . self::TOKEN),
            'خرج الطلب بلا رمز المكتب — أو برمزٍ غير رمزه'
        );

        $data = $request->data();

        $this->assertSame('whatsapp', $data['messaging_product']);
        $this->assertSame('individual', $data['recipient_type']);
        $this->assertSame('96891234567', $data['to']);
        $this->assertSame('text', $data['type']);
        $this->assertSame('وصلتنا رسالتكم', $data['text']['body']);

        // معاينةُ الروابط مطفأة: صورةٌ تُجلب من موقعٍ خارجي وتُعرض تحت
        // رسالةٍ من مكتب محاماة محتوىً لا يملكه المكتب ولا يراجعه
        $this->assertFalse($data['text']['preview_url']);
        $this->assertArrayNotHasKey('template', $data);
    }

    /**
     * إصدارُ Graph يُقرأ من الإعداد لا من الشيفرة.
     *
     * ترقيةُ الإصدار سطرٌ في config/whatsapp.php، فلو كان مثبَّتاً في
     * المزوّد لبقيت المنصّة على إصدارٍ تُوقفه Meta بعد سنتين — وتوقُّفه
     * يعني صمتَ كل إشعارٍ للموكّلين في يومٍ واحد.
     */
    public function test_the_graph_version_comes_from_config_not_from_the_code(): void
    {
        config()->set('whatsapp.graph_version', 'v99.0');
        $this->metaAccepts('wamid.VERSION');

        (new MetaCloudProvider())->sendText('91234567', 'اختبار الإصدار');

        $this->assertSame($this->endpoint(self::PHONE_ID . '/messages'), $this->lastRequest()->url());
        $this->assertStringContainsString('/v99.0/', $this->lastRequest()->url());
    }

    /**
     * الرقم المحلّي يُكمَّل بمفتاح عُمان قبل أن يصل Meta.
     *
     * الموظّف يكتب «91234567» كما هو مكتوبٌ في الوكالة، وMeta لا تعرف
     * إلا الرقم الدوليّ كاملاً بلا «+» ولا فواصل — والرسالةُ بلا مفتاحٍ
     * تُرفض أو تذهب إلى بلدٍ آخر.
     */
    public function test_a_local_omani_number_is_normalised_before_it_reaches_meta(): void
    {
        $forms = [
            '91234567' => '96891234567',        // محلّي بثمانية أرقام
            '091234567' => '96891234567',       // بصفرٍ محلّي
            '+968 9123 4567' => '96891234567',  // دوليٌّ بفواصل
            '0096891234567' => '96891234567',   // بادئة 00
            '96891234567' => '96891234567',     // كاملٌ أصلاً — لا يُمسّ
        ];

        foreach ($forms as $written => $expected) {
            $this->metaAccepts('wamid.NUM');

            (new MetaCloudProvider())->sendText((string) $written, 'مرحبا');

            $this->assertSame(
                $expected,
                $this->lastRequest()->data()['to'],
                "الرقم «{$written}» لم يُطبَّع كما تريده Meta"
            );
        }
    }

    /**
     * القالب يُبنى بشكل components/body/parameters الذي تنتظره Meta.
     *
     * القالبُ هو الطريق الوحيد لبدء محادثةٍ خارج نافذة الأربع والعشرين
     * ساعة — وهو تذكيرُ الجلسة الذي إن سقط لم يحضر الموكّل جلسته.
     * وشكلُ المتغيّرات عندهم دقيق: قيمةٌ نصّية في مصفوفةٍ لكلٍّ منها
     * ‏type=text، بترتيبها لا بأسمائها.
     */
    public function test_send_template_builds_metas_component_shape(): void
    {
        $this->metaAccepts('wamid.TPL1');

        $result = (new MetaCloudProvider())
            ->sendTemplate('91234567', 'session_reminder', 'ar', ['2026/1', 'الأحد ١٠ص']);

        $this->assertTrue($result->ok);

        $data = $this->lastRequest()->data();

        $this->assertSame('template', $data['type']);
        $this->assertSame('session_reminder', $data['template']['name']);
        $this->assertSame(['code' => 'ar'], $data['template']['language']);
        $this->assertSame([[
            'type' => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => '2026/1'],
                ['type' => 'text', 'text' => 'الأحد ١٠ص'],
            ],
        ]], $data['template']['components']);
    }

    /** وقالبٌ بلا متغيّرات لا يحمل components أصلاً — وجودُها فارغةً يُرفض. */
    public function test_a_template_without_values_carries_no_components_key(): void
    {
        $this->metaAccepts('wamid.TPL2');

        (new MetaCloudProvider())->sendTemplate('91234567', 'hello_world', 'en_US');

        $data = $this->lastRequest()->data();

        $this->assertSame('hello_world', $data['template']['name']);
        $this->assertArrayNotHasKey('components', $data['template']);
    }

    // ── النجاح، والنجاح الكاذب ───────────────────────────────────

    /** معرّف الرسالة يُلتقط من messages.0.id — وبه وحده تُتابَع حالتها. */
    public function test_a_successful_response_returns_the_wamid(): void
    {
        $this->metaAccepts('wamid.HBgLOTY4OTEyMzQ1NjcVAgAR');

        $result = (new MetaCloudProvider())->sendText('91234567', 'مرحبا');

        $this->assertTrue($result->ok);
        $this->assertSame('wamid.HBgLOTY4OTEyMzQ1NjcVAgAR', $result->wamid);
        $this->assertNull((new MetaCloudProvider())->getLastError());
    }

    /**
     * ردٌّ ٢٠٠ بلا معرّف رسالة فشلٌ لا نجاحٌ صامت.
     *
     * ولو عُدّ نجاحاً لظهرت الرسالة «مُرسَلة» في الخيط بلا معرّفٍ يُطابَق
     * به إشعارُ التسليم — فتبقى «مُرسَلة» إلى الأبد وقد لا تكون خرجت.
     * والمحامي يقرأ الشاشة لا سجلَّ الخادم.
     */
    public function test_a_two_hundred_without_a_message_id_is_a_failure_not_a_silent_success(): void
    {
        $this->metaReturns(['messaging_product' => 'whatsapp', 'messages' => []]);

        $provider = new MetaCloudProvider();
        $result = $provider->sendText('91234567', 'مرحبا');

        $this->assertFalse($result->ok, 'ردٌّ بلا معرّف عُدّ نجاحاً');
        $this->assertNull($result->wamid);
        $this->assertTrue($result->retryable, 'لم تُعَد محاولةُ رسالةٍ لا نعرف إن خرجت');
        $this->assertNotNull($provider->getLastError());
    }

    // ── حكمُ الإعادة ─────────────────────────────────────────────

    /**
     * خارج نافذة الأربع والعشرين ساعة: لا إعادة، وتفسيرٌ يفهمه المحامي.
     *
     * إعادةُ هذه الرسالة ثلاثاً تُنتج ثلاثةَ رفضٍ متطابق. والمطلوب أن
     * يُقال له: الطريقُ الآن قالبٌ معتمَد — لا «تعذّر الإرسال».
     */
    public function test_error_131047_is_not_retryable_and_explains_the_window(): void
    {
        $this->metaRejects(400, 131047, 'Message failed to send because more than 24 hours have passed');

        $provider = new MetaCloudProvider();
        $result = $provider->sendText('91234567', 'ردّ متأخر');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->retryable, 'أُعيدت رسالةٌ خارج النافذة — وهي مرفوضةٌ مهما أُعيدت');
        $this->assertSame('131047', $result->errorCode);
        $this->assertStringContainsString('قالب', (string) $result->errorTitle);
        $this->assertStringContainsString('ساعة', (string) $result->errorTitle);
        $this->assertSame($result->errorTitle, $provider->getLastError());
    }

    /** ورقمٌ ليس على واتساب لا يصير عليه بالإعادة. */
    public function test_error_131026_is_not_retryable(): void
    {
        $this->metaRejects(400, 131026, 'Message undeliverable');

        $result = (new MetaCloudProvider())->sendText('91234567', 'مرحبا');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->retryable);
        $this->assertStringContainsString('واتساب', (string) $result->errorTitle);
    }

    /** ازدحامٌ (429) يزول — تُعاد المحاولة. */
    public function test_a_rate_limit_is_retryable(): void
    {
        $this->metaRejects(429, null, 'Too many requests');

        $result = (new MetaCloudProvider())->sendText('91234567', 'مرحبا');

        $this->assertFalse($result->ok);
        $this->assertTrue($result->retryable, 'أُسقطت رسالةٌ سببُ رفضها ازدحامٌ مؤقّت');
    }

    /** وعطلٌ عندهم (5xx) ليس عيباً في الرسالة. */
    public function test_a_meta_outage_is_retryable(): void
    {
        $this->metaRejects(500, null, 'Internal server error');

        $result = (new MetaCloudProvider())->sendText('91234567', 'مرحبا');

        $this->assertFalse($result->ok);
        $this->assertTrue($result->retryable);
    }

    /**
     * الرمز الباطل لا تُصلحه إعادة — ويُقال للمكتب أين يُصلحه.
     *
     * رمزُ Meta ينتهي، والإعادةُ عليه تُغرق الطابور بفشلٍ متطابق بينما
     * الحلُّ نسخُ رمزٍ جديد في الإعدادات — لا انتظار.
     */
    public function test_an_invalid_token_is_not_retryable_and_blames_the_token(): void
    {
        $this->metaRejects(401, 190, 'Error validating access token: Session has expired');

        $provider = new MetaCloudProvider();
        $result = $provider->sendText('91234567', 'مرحبا');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->retryable);
        $this->assertStringContainsString('رمز', (string) $result->errorTitle);
        $this->assertStringContainsString('الإعدادات', (string) $result->errorTitle);
        $this->assertStringNotContainsString(self::TOKEN, (string) $provider->getLastError());
    }

    /**
     * انقطاعُ الشبكة يستحقّ إعادةً بلا شكّ.
     *
     * الطلبُ لم يصل Meta أصلاً، فلا حكم لها عليه. وعدمُ الإعادة هنا
     * يُضيع رسالةَ موكّلٍ لأنّ الخادم تعثّر ثانيةً واحدة.
     */
    public function test_a_transport_failure_is_retryable(): void
    {
        $this->metaIsUnreachable();

        $provider = new MetaCloudProvider();
        $result = $provider->sendText('91234567', 'مرحبا');

        $this->assertFalse($result->ok);
        $this->assertTrue($result->retryable);
        $this->assertNotNull($provider->getLastError());
        $this->assertStringNotContainsString(self::TOKEN, (string) $provider->getLastError());
    }

    /**
     * سقفُ الإنتاجية الذي ترسله Meta بحالة 400 ازدحامٌ لا خطأُ طلب.
     *
     * ‏130429 و131048 و131056 كلُّها حدودٌ مؤقّتة، وترسلها Meta بـ400 لا
     * بـ429. وقاعدةُ «كلُّ 4xx نهائية» كانت تحكم عليها بالإعدام: رسالةُ
     * موكّلٍ تُلغى إلى الأبد لأنّ الدقيقة كانت مزدحمة، ويقرأ المكتب
     * «تعذّر الإرسال» فيظنّ العطب في الرقم.
     */
    public function test_a_throughput_limit_meta_sends_as_400_is_still_retryable(): void
    {
        foreach ([130429, 131048, 131056] as $code) {
            $this->metaRejects(400, $code, 'Rate limit hit');

            $result = (new MetaCloudProvider())->sendText('91234567', 'مرحبا');

            $this->assertFalse($result->ok);
            $this->assertTrue($result->retryable, "الرمز {$code} حدٌّ مؤقّت وقد عُدّ نهائياً");
            $this->assertStringContainsString('مؤقت', (string) $result->errorTitle);
        }
    }

    /** ومكتبٌ لم يربط رقمه لا يُرسَل عنه شيء ولا يُعاد — يُقال له ما ينقص. */
    public function test_an_unconfigured_office_is_told_what_is_missing_and_is_not_retried(): void
    {
        $this->forgetCredentials();

        $result = (new MetaCloudProvider())->sendText('91234567', 'مرحبا');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->retryable);
        $this->assertStringContainsString('واتساب', (string) $result->errorTitle);
        Http::assertNothingSent();
    }

    // ── الصدق عند الإخفاق ────────────────────────────────────────

    /**
     * انقطاعُ الشبكة أثناء جلب القوالب ليس «حسابٌ بلا قوالب».
     *
     * ═══ العيب الذي يمنعه هذا الاختبار ═══
     *
     * كانت fetchTemplates تبتلع الاستثناء في السجلّ وتعود [] بلا سبب.
     * وأمرُ whatsapp:sync-templates وصفحةُ الإعدادات يقرآن [] فيقولان
     * للمكتب «لم تصل قوالب» — والمكتبُ قوالبُه معتمَدةٌ عند Meta، فيذهب
     * ينشئ قوالب موجودة بينما العطبُ في الشبكة وحدها.
     */
    public function test_a_network_failure_while_fetching_templates_is_not_reported_as_an_empty_account(): void
    {
        $this->metaIsUnreachable('cURL error 6: Could not resolve host: graph.facebook.com');

        $provider = new MetaCloudProvider();

        $this->assertSame([], $provider->fetchTemplates());
        $this->assertNotNull(
            $provider->getLastError(),
            'عاد الجلب فارغاً بلا سبب — فيُقرأ «لا قوالب لديك» وهو كذب'
        );
        $this->assertStringContainsString('واتساب', (string) $provider->getLastError());
    }

    /** ورفضُ Meta للطلب يُقال سببُه لا يُبتلع. */
    public function test_a_rejected_template_request_says_why(): void
    {
        $this->metaRejects(401, 190, 'Error validating access token');

        $provider = new MetaCloudProvider();

        $this->assertSame([], $provider->fetchTemplates());
        $this->assertStringContainsString('رمز', (string) $provider->getLastError());
    }

    /**
     * وحسابٌ فارغٌ حقّاً يعود فارغاً بلا خطأ.
     *
     * هذا النصفُ الآخر من الصدق: لو تُرك خطأٌ معلّقاً بعد ردٍّ ناجح
     * لقيل «تعذّر الاتصال» لمكتبٍ اتصالُه سليمٌ وحسابُه بلا قوالب بعد،
     * ولضاع تمييزُ الأمر بين الحالتين.
     */
    public function test_a_genuinely_empty_template_list_leaves_no_error_behind(): void
    {
        $this->metaReturns(['data' => []]);

        $provider = new MetaCloudProvider();

        $this->assertSame([], $provider->fetchTemplates());
        $this->assertNull($provider->getLastError(), 'فراغٌ صادقٌ حُمّل سببَ عطلٍ لم يقع');
    }

    /** ونقصُ معرّف حساب الأعمال يُسمّى باسمه — والقوالب تُقرأ منه. */
    public function test_a_missing_business_account_id_is_named(): void
    {
        Setting::set(WhatsAppSettings::KEY_WABA_ID, '', 'whatsapp');

        $provider = new MetaCloudProvider();

        $this->assertSame([], $provider->fetchTemplates());
        $this->assertStringContainsString('WABA', (string) $provider->getLastError());
        Http::assertNothingSent();
    }

    /** وخطأٌ سابق لا يبقى معلّقاً على عمليّةٍ نجحت بعده. */
    public function test_a_successful_call_clears_the_previous_error(): void
    {
        $provider = new MetaCloudProvider();

        $this->metaRejects(500, null, 'Internal error');
        $provider->sendText('91234567', 'أولى');
        $this->assertNotNull($provider->getLastError());

        $this->metaAccepts('wamid.SECOND');
        $result = $provider->sendText('91234567', 'ثانية');

        $this->assertTrue($result->ok);
        $this->assertNull($provider->getLastError(), 'خطأُ محاولةٍ سابقة نُسب إلى إرسالٍ نجح');
    }

    // ── الوسائط: نفس شكل العيب ───────────────────────────────────

    /**
     * إخفاقُ رفع مستند يترك سببَه.
     *
     * الرفعُ يعود null، وnull بلا سببٍ محفوظ يُعرض للموظّف «تعذّر رفع
     * المستند» بلا شيء يفعله — وهو قد يكون رمزاً منتهياً يُصلَح في دقيقة.
     */
    public function test_a_failed_media_upload_leaves_a_reason(): void
    {
        $path = $this->temporaryFile();

        try {
            // ١) رفضٌ من Meta
            $this->metaRejects(401, 190, 'Error validating access token');
            $provider = new MetaCloudProvider();
            $this->assertNull($provider->uploadMedia($path, 'application/pdf'));
            $this->assertStringContainsString('رمز', (string) $provider->getLastError());

            // ٢) انقطاعُ شبكة
            $this->metaIsUnreachable();
            $provider = new MetaCloudProvider();
            $this->assertNull($provider->uploadMedia($path, 'application/pdf'));
            $this->assertNotNull($provider->getLastError());

            // ٣) ردٌّ ناجح بلا معرّف وسيط — لا يصلح للإرسال
            $this->metaReturns(['messaging_product' => 'whatsapp']);
            $provider = new MetaCloudProvider();
            $this->assertNull($provider->uploadMedia($path, 'application/pdf'));
            $this->assertNotNull($provider->getLastError());
        } finally {
            @unlink($path);
        }
    }

    /** وملفٌّ اختفى من القرص يُقال إنّه اختفى — لا «تعذّر الاتصال». */
    public function test_a_missing_file_is_named_as_missing(): void
    {
        $provider = new MetaCloudProvider();

        $this->assertNull($provider->uploadMedia(sys_get_temp_dir() . '/غير-موجود-' . uniqid() . '.pdf', 'application/pdf'));
        $this->assertStringContainsString('الملف', (string) $provider->getLastError());
        Http::assertNothingSent();
    }

    /** وقراءةُ بيانات وسيطٍ واردٍ تُخفق بسببٍ مكتوب. */
    public function test_a_failed_media_meta_leaves_a_reason(): void
    {
        $this->metaRejects(404, 100, 'Unsupported get request');
        $provider = new MetaCloudProvider();
        $this->assertNull($provider->mediaMeta('media-123'));
        $this->assertNotNull($provider->getLastError());

        $this->metaIsUnreachable();
        $provider = new MetaCloudProvider();
        $this->assertNull($provider->mediaMeta('media-123'));
        $this->assertNotNull($provider->getLastError(), 'انقطاعُ شبكةٍ عاد null صامتاً');
    }

    /**
     * وتنزيلُ مستندٍ من موكّل يُخفق بسببٍ مكتوب.
     *
     * ‏401 هنا رمزٌ باطل، و404 عنوانٌ مضت دقائقُه الخمس. والفرقُ يهمّ من
     * يحاول الحفظ ثانيةً: أحدُهما يُعاد فوراً والآخر يُصلَح في الإعدادات.
     */
    public function test_a_failed_media_download_leaves_a_reason(): void
    {
        $this->metaRejects(401, 190, 'Error validating access token');
        $provider = new MetaCloudProvider();
        $this->assertNull($provider->downloadMedia('https://lookaside.fbsbx.com/whatsapp/media/1'));
        $this->assertStringContainsString('رمز', (string) $provider->getLastError());

        $this->metaIsUnreachable();
        $provider = new MetaCloudProvider();
        $this->assertNull($provider->downloadMedia('https://lookaside.fbsbx.com/whatsapp/media/1'));
        $this->assertNotNull($provider->getLastError(), 'انقطاعُ شبكةٍ عاد null صامتاً');
    }

    // ── الرمز لا يخرج ────────────────────────────────────────────

    /**
     * الرمز لا يظهر في شيءٍ يقرؤه إنسان — مهما كان طريقُ الفشل.
     *
     * رسائلُ الخطأ تُعرض في صفحة الإعدادات وتُحفظ في حالة الخدمة وتُنسخ
     * في لقطات شاشةٍ تُرسَل للدعم. ورمزُ واتساب يُرسِل باسم المكتب لمن
     * ملكه — وتسريبُه في نصّ خطأٍ تسريبٌ لا يُسترَدّ.
     */
    public function test_the_access_token_never_appears_in_what_a_human_reads(): void
    {
        $scenarios = [
            'رفضُ الرمز' => fn () => $this->metaRejects(401, 190, 'Error validating access token: ' . self::TOKEN),
            'ازدحام' => fn () => $this->metaRejects(429, null, 'Too many requests'),
            'عطلٌ عندهم' => fn () => $this->metaRejects(500, null, 'Internal error'),
            'خارج النافذة' => fn () => $this->metaRejects(400, 131047, 'Outside window'),
            'ردٌّ بلا معرّف' => fn () => $this->metaReturns(['messages' => []]),
            // نصُّ الاستثناء نفسه قد يحمل الرمز — وهو أخطر مصدر تسريب
            'انقطاع' => fn () => $this->metaIsUnreachable('cURL error 7 while sending token=' . self::TOKEN),
        ];

        foreach ($scenarios as $label => $arrange) {
            $arrange();

            $provider = new MetaCloudProvider();
            $result = $provider->sendText('91234567', 'مرحبا');
            $shown = (string) $provider->getLastError() . ' ' . (string) $result->errorTitle;

            $this->assertNotSame('', trim($shown), "الحالة «{$label}» فشلت بلا سبب مكتوب");
            $this->assertStringNotContainsString(self::TOKEN, $shown, "تسرّب الرمز في الحالة «{$label}»");
            $this->assertStringNotContainsString('Bearer', $shown, "تسرّبت ترويسة الاعتماد في «{$label}»");
        }
    }

    /** وحتّى فحصُ الاتصال المخفق لا يُخرج الرمز — وهو أكثر ما يُنسخ للدعم. */
    public function test_a_failed_connection_test_explains_without_leaking(): void
    {
        $this->metaRejects(401, 190, 'Error validating access token: ' . self::TOKEN);

        $provider = new MetaCloudProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('رمز', (string) $result['message']);
        $this->assertStringNotContainsString(self::TOKEN, (string) $result['message']);

        // وانقطاعُ الشبكة يترك سببَه أيضاً — whatsapp:doctor يعرضه
        $this->metaIsUnreachable();

        $provider = new MetaCloudProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result['ok']);
        $this->assertNotNull($provider->getLastError());
        $this->assertStringNotContainsString(self::TOKEN, (string) $provider->getLastError());
    }

    // ── أدوات ────────────────────────────────────────────────────

    /** العنوان كما يبنيه المزوّد: من الإعداد لا من نصٍّ مثبَّت هنا. */
    private function endpoint(string $path): string
    {
        return rtrim((string) config('whatsapp.graph_base'), '/')
            . '/' . trim((string) config('whatsapp.graph_version'), '/')
            . '/' . ltrim($path, '/');
    }

    private function metaAccepts(string $wamid): void
    {
        $this->metaReturns([
            'messaging_product' => 'whatsapp',
            'contacts' => [['input' => '96891234567', 'wa_id' => '96891234567']],
            'messages' => [['id' => $wamid]],
        ]);
    }

    private function metaReturns(array $body, int $status = 200): void
    {
        $this->responder = fn () => Http::response($body, $status);
    }

    private function metaRejects(int $status, ?int $code, string $message): void
    {
        $error = ['message' => $message, 'type' => 'OAuthException', 'fbtrace_id' => 'Axxxxxxx'];

        if ($code !== null) {
            $error['code'] = $code;
        }

        $this->metaReturns(['error' => $error], $status);
    }

    /** انقطاعٌ قبل أن يصل الطلبَ Meta أصلاً — لا حكم لها عليه. */
    private function metaIsUnreachable(string $message = 'cURL error 28: Operation timed out'): void
    {
        $this->responder = function () use ($message) {
            throw new ConnectionException($message);
        };
    }

    private function lastRequest(): Request
    {
        $recorded = Http::recorded();

        $this->assertNotEmpty($recorded, 'لم يخرج أيّ طلبٍ إلى Meta');

        return $recorded->last()[0];
    }

    /** ملفٌّ حقيقي على القرص — uploadMedia تقرأ القرص قبل أن تتّصل. */
    private function temporaryFile(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'wa_test_');
        file_put_contents($path, 'ملفُّ اختبار');

        return $path;
    }

    /** فصلُ الاعتماد — بما فيه الرجوع القديم إلى ملف البيئة. */
    private function forgetCredentials(): void
    {
        Setting::set(WhatsAppSettings::KEY_TOKEN, '', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '', 'whatsapp');
        config()->set('services.whatsapp.meta_token', '');
        config()->set('services.whatsapp.meta_phone_id', '');
    }
}
