<?php

namespace Tests\Feature;

use App\Services\WhatsApp\EvolutionProvider;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * الاقترانُ لا يقف عند بابٍ مسدود — يفتحه.
 *
 * ═══ الطريقُ المسدود كما هو في مصدر Evolution 2.3.7 ═══
 *
 * حارسُ «instance/create» (guards/instance.guard.ts) يرفض بـ٤٠٣ «الاسم
 * مستعمَل» إن وُجد صفُّ النسخة في قاعدة الجسر. ومتحكّم
 * «instance/connect» (controllers/instance.controller.ts) يقرأ النسخةَ
 * من ذاكرة العملية فيرمي «The … instance does not exist» إن لم تكن
 * محمَّلةً فيها.
 *
 * ويجتمعان في حالٍ واحدة: نسخةٌ أُنشئت ولم يُمسح رمزُها قطّ ثمّ أُعيد
 * تشغيلُ الجسر — لا اعتمادَ محفوظاً يُحمَّل، والصفُّ باقٍ. فيضغط
 * المكتبُ «ابدأ الاقتران»: إنشاءٌ ⇐ ٤٠٣، ووصلٌ ⇐ ٤٠٠ «غير موجودة».
 * أبداً. بلا رمزٍ ولا مخرجٍ ولا سببٍ يفهمه.
 *
 * فهذه الاختباراتُ تحرس الخروجَ من ذلك الباب: يُحذف الصفُّ الميت
 * وتُنشأ نسخةٌ نظيفة ويُعرض الرمز — ولا تُحذف نسخةٌ حيّةٌ أبداً.
 */
class WhatsAppPairingHealTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.default' => 'evolution',
            'whatsapp.evolution.base_url' => 'https://bridge.test',
            'whatsapp.evolution.api_key' => 'bridge-key-0123456789',
        ]);
    }

    /** ردُّ رمزٍ كما يصوغه الجسر عند الإنشاء */
    private function qrCreated(): array
    {
        return [
            'instance' => ['instanceName' => 'x', 'status' => 'connecting'],
            'qrcode' => ['code' => '2@FRESH', 'base64' => 'iVBORw0KGgoAAAA='],
        ];
    }

    /**
     * الحالةُ الميتة كاملةً: القاعدة تعرف النسخة والذاكرة لا تعرفها.
     */
    public function test_a_dead_instance_row_no_longer_blocks_pairing_forever(): void
    {
        $deleted = false;
        $creates = 0;

        Http::fake(function ($request) use (&$deleted, &$creates) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                $deleted = true;

                return Http::response(['status' => 'SUCCESS'], 200);
            }

            if (str_contains($url, '/instance/create')) {
                $creates++;

                // الصفُّ الميت باقٍ ⇐ الحارسُ يرفض. وبعد حذفه ⇐ نسخةٌ ورمز
                return $deleted
                    ? Http::response($this->qrCreated(), 201)
                    : Http::response(['status' => 403, 'error' => 'Forbidden',
                        'response' => ['message' => ['This name "office" is already in use.']]], 403);
            }

            if (str_contains($url, '/instance/connect/')) {
                return $deleted
                    ? Http::response(['code' => '2@FRESH', 'base64' => 'iVBORw0KGgoAAAA='], 200)
                    : Http::response(['status' => 400, 'error' => 'Bad Request',
                        'response' => ['message' => ['The "office" instance does not exist']]], 400);
            }

            return Http::response(['webhook' => ['enabled' => true]], 201);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertNotNull($result['qr'], 'بقي المكتبُ بلا رمزٍ أمام بابٍ مسدود');
        $this->assertSame('connecting', $result['state']);
        $this->assertTrue($deleted, 'لم يُحذف الصفُّ الميت — الرحلةُ نفسُها ستتكرّر إلى الأبد');
        $this->assertSame(2, $creates, 'لم يُعَد الإنشاءُ بعد الحذف');
    }

    /** نسخةٌ حيّةٌ لا تُحذف بحال — حذفُها قطعُ اقترانِ مكتبٍ يعمل. */
    public function test_a_live_instance_is_never_deleted(): void
    {
        $deleteCalled = false;

        Http::fake(function ($request) use (&$deleteCalled) {
            $url = $request->url();

            if (str_contains($url, '/instance/delete/')) {
                $deleteCalled = true;

                return Http::response([], 200);
            }

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'open']], 200);
            }

            return Http::response([], 200);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertSame('open', $result['state']);
        $this->assertFalse($deleteCalled, 'حُذفت نسخةٌ موصولة — قُطع اقترانُ مكتبٍ يعمل');
        $this->assertSame('open', WhatsAppSettings::evolutionState());
    }

    /** ونسخةٌ قيد الاتصال تُعطي رمزَها بلا حذفٍ ولا إنشاءٍ ثانٍ. */
    public function test_an_existing_instance_just_returns_its_code(): void
    {
        $deleteCalled = false;

        Http::fake(function ($request) use (&$deleteCalled) {
            $url = $request->url();

            if (str_contains($url, '/instance/delete/')) {
                $deleteCalled = true;

                return Http::response([], 200);
            }

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/create')) {
                return Http::response(['status' => 403,
                    'response' => ['message' => ['This name "office" is already in use.']]], 403);
            }

            if (str_contains($url, '/instance/connect/')) {
                return Http::response(['count' => 1, 'code' => '2@LIVE', 'base64' => 'iVBORw0KGgoAAAA='], 200);
            }

            return Http::response(['webhook' => ['enabled' => true]], 201);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertNotNull($result['qr']);
        $this->assertFalse($deleteCalled, 'حُذفت نسخةٌ كانت ستعطي رمزَها');
    }

    /** رمزُ الإنشاء يُقرأ من ردّه — لا رحلةَ ثانية قد تصادف نسخةً لم تُحمَّل. */
    public function test_a_fresh_instance_returns_the_code_from_create_itself(): void
    {
        $connectCalled = false;

        Http::fake(function ($request) use (&$connectCalled) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/create')) {
                return Http::response($this->qrCreated(), 201);
            }

            if (str_contains($url, '/instance/connect/')) {
                $connectCalled = true;

                return Http::response([], 400);
            }

            return Http::response([], 201);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertStringContainsString('base64', (string) $result['qr']);
        $this->assertFalse($connectCalled, 'رحلةٌ ثانيةٌ بلا داعٍ — ورمزُ الإنشاء كان بين يديه');
    }

    /**
     * الويبهوك يُضبط في جسم الإنشاء نفسِه بأسماء الإصدار الثاني.
     *
     * كنّا نرسل webhookByEvents وwebhookBase64 — أسماءُ الإصدار الأوّل —
     * ومخطَّطُ الثاني يتجاهل الزائد ولا يرفضه: الطلبُ ينجح ٢٠١
     * والإعدادان لا يُضبطان، فتصل صورةُ الموكّل بلا محتوىً يُحفظ.
     */
    public function test_the_webhook_travels_inside_create_with_version_two_names(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            return Http::response($this->qrCreated(), 201);
        });

        (new EvolutionProvider())->pair();

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/instance/create')) {
                return false;
            }

            $hook = $request->data()['webhook'] ?? [];

            return ($hook['enabled'] ?? false) === true
                && str_contains((string) ($hook['url'] ?? ''), '/webhooks/evolution/')
                && array_key_exists('byEvents', $hook)
                && ($hook['base64'] ?? false) === true
                && in_array('CONNECTION_UPDATE', $hook['events'] ?? [], true);
        });
    }

    /** ويبهوكٌ رفضه الجسرُ لا يُلغي الاقتران — الرمزُ أهمّ، ويُعاد ضبطُه لاحقاً. */
    public function test_a_refused_webhook_does_not_cancel_the_pairing(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/webhook/set/')) {
                return Http::response(['status' => 400, 'error' => 'Bad Request'], 400);
            }

            if (str_contains($url, '/instance/create')) {
                return Http::response(['status' => 403,
                    'response' => ['message' => ['This name "office" is already in use.']]], 403);
            }

            return Http::response(['code' => '2@X', 'base64' => 'iVBORw0KGgoAAAA='], 200);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertNotNull($result['qr'], 'أُلغي الاقترانُ كلُّه لأنّ خطوةً ثانويةً أخفقت');
    }

    /**
     * «انقطع الاتصال» لحظةَ الإرسال عطلٌ عابرٌ لا دائم.
     *
     * ‏Evolution يردّ بـ٤٠٠ حين تسقط الجلسةُ لحظةَ الإرسال، وكنّا نعدّ
     * كلَّ ٤٠٠ دائمةً: تموت رسالةُ الموكّل نهائياً ويُكتب «أخفق»،
     * والكنسُ يعيد وصلَ الرقم بعد دقيقةٍ ولا أحدَ يعيدها.
     */
    public function test_a_closed_connection_is_retryable_not_a_dead_message(): void
    {
        Http::fake(['*' => Http::response(['status' => 400, 'error' => 'Bad Request',
            'response' => ['message' => ['Connection Closed']]], 400)]);

        $result = (new EvolutionProvider())->sendText('96891234567', 'مرحباً');

        $this->assertFalse($result->ok);
        $this->assertTrue($result->retryable, 'ماتت رسالةُ الموكّل لانقطاعٍ يزول بعد دقيقة');
    }

    /** أمّا الرقمُ الخاطئ فعطلٌ دائم: تكرارُه ضجيجٌ بلا فائدة. */
    public function test_a_genuinely_bad_request_stays_permanent(): void
    {
        Http::fake(['*' => Http::response(['status' => 400, 'error' => 'Bad Request',
            'response' => ['message' => ['The number is not registered on WhatsApp']]], 400)]);

        $result = (new EvolutionProvider())->sendText('96891234567', 'مرحباً');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->retryable);
    }

    /**
     * البابُ الثاني: الربطُ برقم الهاتف حين يرفض واتساب مسحَ الرمز.
     *
     * «Can't link new devices right now» رسالةُ واتساب نفسِه بعد
     * محاولاتٍ متكرّرة — فيقف المكتبُ أمام رمزٍ صحيحٍ لا يُقبل. والربطُ
     * بالرقم مسارٌ آخر عنده: ثمانيةُ محارفَ تُكتب في الهاتف.
     */
    public function test_a_phone_number_asks_for_a_pairing_code(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            return Http::response([
                'instance' => ['instanceName' => 'x', 'status' => 'connecting'],
                'qrcode' => ['pairingCode' => 'WXYZ1234', 'code' => '2@A', 'base64' => 'iVBORw0KGgo='],
            ], 201);
        });

        $result = (new EvolutionProvider())->pair('968 9123 4567');

        $this->assertSame('WXYZ1234', $result['code'], 'لم يُطلب رمزُ الربط والبابُ الأوّل مغلق');

        // والرقمُ يسافر إلى الجسر مجرّداً من الفواصل والزائد
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/instance/create')
                && ($request->data()['number'] ?? null) === '96891234567';
        });
    }

    /** ومسحُ الرمز يبقى الافتراض: بلا رقمٍ لا يُطلب رمزُ ربط. */
    public function test_without_a_phone_the_journey_stays_a_qr_scan(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            return Http::response(['qrcode' => ['code' => '2@A', 'base64' => 'iVBORw0KGgo=']], 201);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertNull($result['code']);
        $this->assertNotNull($result['qr']);

        Http::assertSent(function ($request) {
            return !str_contains($request->url(), '/instance/create')
                || !array_key_exists('number', $request->data());
        });
    }

    /** ورقمٌ ناقصٌ يُردّ برسالةٍ تقول الصيغة — لا يُرسَل إلى الجسر. */
    public function test_a_short_number_is_refused_before_the_bridge(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin', 'is_active' => true]);
        \App\Models\Setting::set('whatsapp_provider', 'evolution', 'whatsapp');
        Http::fake();

        $this->actingAs($admin)
            ->postJson(route('settings.whatsapp.pair'), ['phone' => '9123'])
            ->assertOk()
            ->assertJsonPath('code', null)
            ->assertJsonFragment(['message' => 'اكتب الرقم بصيغته الدولية بلا صفرٍ ولا زائد — مثال: 96891234567']);

        Http::assertNothingSent();
    }

    /** وعطلُ الجسر الحقيقي يبقى مقروءاً — لا يُبتلع في رحلة الشفاء. */
    public function test_a_real_bridge_failure_is_still_named(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            return Http::response(['status' => 500, 'error' => 'Internal Server Error'], 500);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertNull($result['qr']);
        $this->assertStringContainsString('قاعدة بياناته', (string) $result['message']);
    }
}
