<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Services\PanelReporter;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * المكتب يخبر اللوحة عن ربط واتساب — بالأرقام وحدها.
 *
 * ═══ العطل الذي وُضع له ═══
 *
 * قبلت اللوحةُ مجموعةَ نبضٍ اسمها whatsapp وعرضت لها بطاقةَ صحّة، ولم
 * يكن المكتب يرسلها. فبطاقةُ كلِّ مكتبٍ في اللوحة تقول «لم يبلّغ بعد»
 * سواءٌ أكان ربطُه يعمل أم انقطع منذ أسبوع — وهي أسوأ حالات الشاشة:
 * لا تكذب في اتجاهٍ واحد بل تُسوّي بين السليم والمعطوب فلا يُقرأ منها
 * شيء.
 *
 * ═══ وما تحرسه هذه الاختبارات ═══
 *
 * ١) الأرقام تصل صحيحةً: إخفاقاتُ اليوم وحدها لا كلُّ إخفاقٍ منذ
 *    التنصيب، والأحداثُ المعلّقة وحدها لا كلُّ حدثٍ دُوّن.
 *
 * ٢) ولا يغادر المكتبَ رقمٌ ولا نصٌّ ولا اسم. مراسلاتُ الموكّلين سرُّ
 *    مهنة، ورقمُ الموكّل وحده — بلا نصٍّ أصلاً — يكفي ليُعرف أنّ فلاناً
 *    يراسل هذا المكتب.
 *
 * ٣) ولا تموت النبضةُ في مكتبٍ لم تُنفَّذ هجراتُه: هي جسرُه الوحيد إلى
 *    اللوحة، وموتُها يُعمي اللوحةَ عن نسخه الاحتياطية وأخطائه معاً —
 *    بطاقةٌ جديدة تُطفئ بطاقاتٍ قائمة.
 */
class WhatsAppPulseTest extends TestCase
{
    use RefreshDatabase;

    /** رمزٌ يشبه رموز Meta الحقيقية — كي يُبحث عنه في الحمولة نصّاً. */
    private const TOKEN = 'EAAJ9ZBx1QsPeRealLookingMetaTokenValue0099';
    private const PHONE_ID = '109876543210987';
    private const WABA_ID = '203040506070809';
    private const DISPLAY_PHONE = '+968 24 123456';
    private const CONTACT_WA_ID = '96899887766';
    private const CONTACT_NAME = 'سالم بن ناصر البوسعيدي';
    private const MESSAGE_BODY = 'رقم الحساب البنكي 0123456789 لسداد الأتعاب';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'panel.ingest_url' => 'https://panel.example',
            'panel.ingest_token' => 'panel-token',
            // الرجوعُ إلى الرمز المركزي في .env يُطفأ صراحةً: مكتبٌ
            // ‏«غير مربوط» في هذا الاختبار يجب أن يبقى غير مربوط ولو
            // كان على جهاز المطوّر رمزٌ في بيئته.
            'services.whatsapp.meta_token' => '',
            'services.whatsapp.meta_phone_id' => '',
        ]);

        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    // ── أدوات ────────────────────────────────────────────────────

    /** مكتبٌ ربط رقمه فعلاً — باعتمادٍ يشبه الحقيقي. */
    private function connect(): void
    {
        WhatsAppSettings::store(self::TOKEN, self::PHONE_ID, self::WABA_ID);
        WhatsAppSettings::rememberIdentity(self::DISPLAY_PHONE, 'مكتب الريامي للمحاماة');
        WhatsAppSettings::touchWebhook();
    }

    private function conversation(): WhatsAppConversation
    {
        $contact = WhatsAppContact::create([
            'wa_id' => self::CONTACT_WA_ID,
            'profile_name' => self::CONTACT_NAME,
        ]);

        return WhatsAppConversation::create([
            'contact_id' => $contact->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'last_inbound_at' => now(),
        ]);
    }

    private function message(WhatsAppConversation $conversation, string $status, ?int $ageDays = null): WhatsAppMessage
    {
        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'wamid' => 'wamid.' . uniqid(),
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => self::MESSAGE_BODY,
            'status' => $status,
        ]);

        if ($ageDays !== null) {
            // عمرُ الصفّ يُكتب بالاستعلام لا بالنموذج: created_at يملؤه
            // Eloquent عند الإنشاء فيدهس ما نضعه في المصفوفة.
            WhatsAppMessage::query()->whereKey($message->id)
                ->update(['created_at' => now()->subDays($ageDays)]);
        }

        return $message;
    }

    /** يُطلق النبضة ويُعيد ما حملته فعلاً — من الطلب المرسَل لا من حسابٍ موازٍ. */
    private function pulse(): array
    {
        $this->assertTrue(
            PanelReporter::heartbeat(),
            'النبضة لم تصل — ومجموعةٌ جديدة لا يجوز أن تكسر جسر المكتب الوحيد',
        );

        $payload = [];

        Http::assertSent(function ($request) use (&$payload) {
            if (!str_contains($request->url(), '/ingest/heartbeat')) {
                return false;
            }

            $payload = $request->data();

            return true;
        });

        return $payload;
    }

    // ── الأرقام تصل ──────────────────────────────────────────────

    /** النبضة تحمل المجموعة، وأرقامُها هي أرقامُ المكتب. */
    public function test_the_heartbeat_payload_carries_the_whatsapp_block(): void
    {
        $this->connect();
        $conversation = $this->conversation();

        $this->message($conversation, WhatsAppMessage::STATUS_FAILED);
        $this->message($conversation, WhatsAppMessage::STATUS_SENT);

        WhatsAppWebhookEvent::create(['event_key' => 'evt-pending', 'kind' => 'message']);
        WhatsAppWebhookEvent::create([
            'event_key' => 'evt-done', 'kind' => 'message', 'processed_at' => now(),
        ]);

        $whatsapp = $this->pulse()['whatsapp'] ?? null;

        $this->assertIsArray($whatsapp, 'لم تحمل النبضةُ مجموعةَ واتساب');
        $this->assertTrue($whatsapp['connected']);
        $this->assertSame(1, $whatsapp['failed_24h'], 'عُدّت الرسائلُ الناجحة مع المخفقة');
        $this->assertSame(1, $whatsapp['pending_events'], 'عُدّ حدثٌ عولج ضمن المعلّق');
        $this->assertNotNull($whatsapp['last_webhook_at']);
        $this->assertNotNull($whatsapp['checked_at']);
    }

    /**
     * وإخفاقاتُ اليوم وحدها تُعدّ.
     *
     * عددٌ تراكميٌّ منذ التنصيب يبقى مرتفعاً بعد أن يُصلَح العطل، فتظلّ
     * بطاقةُ المكتب حمراء إلى الأبد ولا يُقرأ منها أنّه عوفي.
     */
    public function test_only_the_last_day_of_failures_is_counted(): void
    {
        $this->connect();
        $conversation = $this->conversation();

        $this->message($conversation, WhatsAppMessage::STATUS_FAILED);
        $this->message($conversation, WhatsAppMessage::STATUS_FAILED, ageDays: 3);

        $this->assertSame(1, $this->pulse()['whatsapp']['failed_24h']);
    }

    // ── ولا يغادر المكتبَ سرّ ────────────────────────────────────

    /**
     * الحمولةُ كلُّها لا تحمل رقماً ولا رمزاً ولا نصّاً ولا اسماً.
     *
     * يُبحث في الحمولة المرمّزة كاملةً لا في مفاتيح المجموعة وحدها:
     * تسريبٌ يوماً ما لن يأتي في مفتاحٍ اسمه display_phone بل في حقلٍ
     * أُضيف بحسن نيّة — «آخر رسالة» أو «اسم الرقم».
     */
    public function test_the_payload_carries_no_phone_no_token_no_body_and_no_name(): void
    {
        $this->connect();
        $conversation = $this->conversation();
        $this->message($conversation, WhatsAppMessage::STATUS_FAILED);

        $encoded = json_encode($this->pulse(), JSON_UNESCAPED_UNICODE);

        foreach ([
            self::TOKEN,                                    // الرمز الخام
            '••••' . mb_substr(self::TOKEN, -4),            // وبصمتُه المقنَّعة
            self::PHONE_ID,                                 // معرّف الرقم
            self::WABA_ID,                                  // معرّف حساب الأعمال
            self::DISPLAY_PHONE,                            // رقم العرض
            self::CONTACT_WA_ID,                            // رقم الموكّل
            self::CONTACT_NAME,                             // واسمه
            self::MESSAGE_BODY,                             // ونصُّ رسالته
            'مكتب الريامي للمحاماة',                        // واسمُ النشاط
        ] as $secret) {
            $this->assertStringNotContainsString(
                $secret,
                (string) $encoded,
                'غادر المكتبَ ما لا يجوز أن يغادره: ' . $secret,
            );
        }
    }

    /** والمجموعةُ مقفلةٌ على مفاتيحها: أعدادٌ وتواريخُ لا غير. */
    public function test_the_group_carries_exactly_these_keys_and_nothing_more(): void
    {
        $this->connect();

        $this->assertSame(
            ['connected', 'last_webhook_at', 'failed_24h', 'pending_events', 'checked_at'],
            array_keys($this->pulse()['whatsapp']),
            'أُضيف إلى المجموعة مفتاحٌ لم تُراجَع خصوصيّتُه',
        );
    }

    // ── ولا تموت النبضة ─────────────────────────────────────────

    /**
     * مكتبٌ لم تُنفَّذ هجراتُه: تُحذف المجموعة وتُكمل النبضةُ طريقها.
     *
     * جداولُ واتساب لا وجود لها عنده، والاستعلامُ فيها يرمي. ولو خرج
     * الاستثناءُ إلى النبضة لماتت — وهي جسرُه الوحيد — فعميت اللوحةُ
     * عن نسخه الاحتياطية وأخطائه أيضاً: عطلٌ في بطاقةٍ جديدة يُطفئ
     * بطاقاتٍ قائمة.
     */
    public function test_the_group_is_dropped_when_the_tables_are_missing(): void
    {
        Schema::drop('whatsapp_messages');
        Schema::drop('whatsapp_webhook_events');

        $payload = $this->pulse();

        $this->assertArrayNotHasKey('whatsapp', $payload, 'أُرسلت مجموعةٌ لا مصدر لها');
        // وبقيةُ النبضة تصل كما كانت — وهذا هو المقصود من الحذف
        $this->assertArrayHasKey('backup', $payload);
        $this->assertArrayHasKey('errors', $payload);
    }

    /**
     * ومكتبٌ لم يربط رقمه يقول «غير مربوط» ولا يصمت.
     *
     * الصمتُ يعني «لم يُفحص» فتُبقي اللوحةُ آخر ما تعرف — فمكتبٌ فُصل
     * رقمُه اليوم يظلّ في اللوحة مربوطاً بلا نهاية.
     */
    public function test_a_disconnected_office_reports_false_rather_than_silence(): void
    {
        WhatsAppSettings::disconnect();

        $whatsapp = $this->pulse()['whatsapp'] ?? null;

        $this->assertIsArray($whatsapp, 'صمت المكتبُ عن ربطٍ مفصول بدل أن يقول إنّه مفصول');
        $this->assertFalse($whatsapp['connected']);
        $this->assertSame(0, $whatsapp['failed_24h']);
        $this->assertSame(0, $whatsapp['pending_events']);
        $this->assertNotNull($whatsapp['checked_at']);
    }

    /** والمكتبُ غيرُ المربوط بالجسر أصلاً لا يُرسل شيئاً — كما كان. */
    public function test_an_office_with_no_panel_bridge_sends_nothing(): void
    {
        config(['panel.ingest_url' => '', 'panel.ingest_token' => '']);

        $this->assertFalse(PanelReporter::heartbeat());

        Http::assertNothingSent();
    }

    /** ولا يُخزَّن في الإعدادات أثرٌ لهذه القراءة — النبضةُ تقرأ ولا تكتب. */
    public function test_the_pulse_reads_and_does_not_write(): void
    {
        $this->connect();
        $before = Setting::query()->count();

        $this->pulse();

        $this->assertSame($before, Setting::query()->count());
    }
}
