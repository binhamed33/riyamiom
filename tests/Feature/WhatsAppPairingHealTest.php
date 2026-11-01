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
        $creates = 0;

        // كما في 2.3.7 (instance.guard.ts:35-47، monitor.service.ts:241-262): الذاكرةُ لا تعرف
        // النسخة ⇐ الحالةُ والصفُّ والحذفُ ٤٠٤، والإنشاءُ الأوّل يصطدم بالصفّ الباقي (٤٠٠)
        // فيمحوه الجسر، والثاني ينشئ نظيفاً
        Http::fake(function ($request) use (&$creates) {
            $url = $request->url();

            if (str_contains($url, '/instance/create')) {
                $creates++;

                return $creates === 1
                    ? Http::response(['status' => 400, 'error' => 'Bad Request',
                        'response' => ['message' => ['[object Object]']]], 400)
                    : Http::response($this->qrCreated(), 201);
            }

            return Http::response(['status' => 404, 'error' => 'Not Found',
                'response' => ['message' => ['The "office" instance does not exist']]], 404);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertNotNull($result['qr'], 'بقي المكتبُ بلا رمزٍ أمام بابٍ مسدود');
        $this->assertSame('connecting', $result['state']);
        $this->assertSame(2, $creates, 'لم يُعَد الإنشاءُ بعد أن نظّف الجسرُ الصفَّ الميت');
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

    /**
     * ونسخةٌ لم تقترن تُطوى وتُنشأ من جديد — لا يُعاد رمزُها المخزَّن.
     *
     * الجسرُ يعيد لجلسةٍ تنتظر رمزاً قد مات (لا يُمحى عند سقوطها)، وعدّادُ رموزها
     * متراكمٌ من كلّ محاولةٍ سابقة فتُرفض في منتصف المسح. فالنظيفةُ هي الطريق.
     */
    public function test_a_waiting_unpaired_instance_is_replaced_by_a_fresh_one(): void
    {
        $deleteCalled = false;
        $connectCalled = false;

        Http::fake(function ($request) use (&$deleteCalled, &$connectCalled) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'connecting']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'office', 'connectionStatus' => 'connecting', 'ownerJid' => null]], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                $deleteCalled = true;

                return Http::response(['status' => 'SUCCESS', 'error' => false], 200);
            }

            if (str_contains($url, '/instance/connect/')) {
                $connectCalled = true;

                return Http::response(['count' => 29, 'code' => '2@STALE', 'base64' => 'iVBORw0KGgoSTALE='], 200);
            }

            return Http::response($this->qrCreated(), 201);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertTrue($deleteCalled, 'بقيت جلسةٌ عدّادُها على وشك النفاد');
        $this->assertFalse($connectCalled, 'عُرض الرمزُ المخزَّن بدل رمز الجلسة الجديدة');
        $this->assertStringContainsString('iVBORw0KGgoAAAA', (string) $result['qr']);
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

    /** الاقترانُ لا يعلّق على نداء ويبهوكٍ منفصل — العنوانُ في جسم الإنشاء نفسِه. */
    public function test_pairing_does_not_depend_on_a_separate_webhook_call(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/webhook/set/')) {
                return Http::response(['status' => 400, 'error' => 'Bad Request'], 400);
            }

            return Http::response($this->qrCreated(), 201);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertNotNull($result['qr'], 'أُلغي الاقترانُ كلُّه لأنّ خطوةً ثانويةً أخفقت');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/webhook/set/'));
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

        // أُنشئت الجلسة — ولم يسافر معها رقم
        Http::assertSent(fn ($request) => str_contains($request->url(), '/instance/create'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/instance/create')
            && array_key_exists('number', $request->data()));
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

            // الجسرُ يقرّ أنّه لا يعرف النسخة، ثمّ يعجز عن إنشائها (قاعدتُه غير موصولة)
            if (str_contains($request->url(), '/instance/fetchInstances') || str_contains($request->url(), '/instance/delete/')) {
                return Http::response(['status' => 404, 'error' => 'Not Found'], 404);
            }

            return Http::response(['status' => 500, 'error' => 'Internal Server Error'], 500);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertNull($result['qr']);
        $this->assertStringContainsString('قاعدة بياناته', (string) $result['message']);
    }

    // ══════════ ما رآه المكتب: الرقمُ يُهمَل والرمزُ يموت ══════════

    /**
     * طلبُ رمز ربطٍ وجلسةُ مسحٍ تنتظر: كان الجسرُ يعيد رمزَ المسح ويُهمل الرقم.
     *
     * ‏instance.controller.ts:322-323 — «connecting» ⇐ يعاد الرمزُ المخزَّن كما هو،
     * و‎?number=‎ لا يُقرأ. والكنسُ كان يُبقي النسخةَ في «connecting» أبداً، فلم
     * يُطلب من واتساب رمزُ ربطٍ قطّ. الآن تُطوى الجلسةُ وتُنشأ بالرقم.
     */
    public function test_a_phone_request_replaces_a_waiting_qr_session_and_gets_a_code(): void
    {
        $created = [];

        Http::fake(function ($request) use (&$created) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'connecting']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'office', 'connectionStatus' => 'connecting', 'ownerJid' => null]], 200);
            }

            if (str_contains($url, '/instance/connect/')) {
                // ما يفعله الجسرُ حقّاً: الرمزُ القديم بلا رمز ربط، والرقمُ مُهمَل
                return Http::response(['count' => 7, 'code' => '2@QR', 'base64' => 'iVBORw0KGgo='], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                return Http::response(['status' => 'SUCCESS', 'error' => false], 200);
            }

            if (str_contains($url, '/instance/create')) {
                $created[] = $request->data();

                return Http::response(['instance' => ['instanceName' => 'office', 'status' => 'connecting'],
                    'qrcode' => ['pairingCode' => 'K7M2QX9P', 'code' => '2@NEW', 'base64' => 'iVBORw0KGgoNEW=', 'count' => 1]], 201);
            }

            return Http::response([], 404);
        });

        $result = (new EvolutionProvider())->pair('96894249400');

        $this->assertSame('K7M2QX9P', $result['code'], 'عاد رمزُ المسح القديم والرقمُ مُهمَل');
        $this->assertNull($result['qr'], 'بالرقم يُعرض رمزُ الربط وحده');
        $this->assertCount(1, $created);
        $this->assertSame('96894249400', $created[0]['number'] ?? null);
    }

    /** ورمزُ الربط لم يصل في مهلة الجسر: يُقال «يُطلب» وتلتقطه الشاشةُ في سؤالها التالي. */
    public function test_a_code_not_ready_yet_is_announced_not_reported_as_failure(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/create')) {
                return Http::response(['instance' => ['instanceName' => 'office'], 'qrcode' => ['count' => 0]], 201);
            }

            return Http::response(['status' => 404, 'error' => 'Not Found'], 404);
        });

        $result = (new EvolutionProvider())->pair('96894249400');

        $this->assertSame('connecting', $result['state']);
        $this->assertNull($result['code']);
        $this->assertStringContainsString('يُطلب', (string) $result['message']);
    }

    /** ونسخةٌ مقترنةٌ سقط اتصالُها تُوصَل — لا تُحذف ولا تُنشأ. */
    public function test_a_paired_instance_that_dropped_is_reconnected_not_replaced(): void
    {
        $nudged = false;

        Http::fake(function ($request) use (&$nudged) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => $nudged ? 'open' : 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'office', 'connectionStatus' => 'open', 'ownerJid' => '96894249400@s.whatsapp.net']], 200);
            }

            if (str_contains($url, '/instance/connect/')) {
                $nudged = true;

                return Http::response(['instance' => ['state' => 'connecting']], 200);
            }

            return Http::response([], 200);
        });

        $result = (new EvolutionProvider())->pair('96894249400');

        $this->assertSame('open', $result['state']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/delete/') || str_contains($r->url(), '/instance/create'));
    }

    /** ومقترنةٌ تعيد وصلَ نفسها الآن: لا رمزَ ولا حذف — رمزُها المخزَّن قديمٌ لا يُعرض. */
    public function test_a_paired_instance_reconnecting_is_left_alone(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'connecting']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'office', 'connectionStatus' => 'open', 'ownerJid' => '96894249400@s.whatsapp.net']], 200);
            }

            return Http::response(['count' => 3, 'base64' => 'iVBORw0KGgoOLD='], 200);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertNull($result['qr']);
        $this->assertSame('connecting', $result['state']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/delete/')
            || str_contains($r->url(), '/instance/create') || str_contains($r->url(), '/instance/connect/'));
    }

    /**
     * الجسرُ يقلع الآن: نسخةُ مكتبٍ اقترن من قبل لم تُحمَّل بعد فتبدو «غير موجودة».
     * الإنشاءُ حينها يمحو صفَّها واعتمادَها — فيُمهَل حتى تظهر.
     */
    public function test_a_previously_paired_office_waits_for_a_booting_bridge_instead_of_recreating(): void
    {
        \App\Models\Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->subDay()->toIso8601String(), 'whatsapp');
        $rowAsks = 0;

        Http::fake(function ($request) use (&$rowAsks) {
            $url = $request->url();

            if (str_contains($url, '/instance/fetchInstances')) {
                $rowAsks++;

                return $rowAsks < 3
                    ? Http::response(['status' => 404, 'error' => 'Not Found'], 404)
                    : Http::response([['name' => 'office', 'connectionStatus' => 'open', 'ownerJid' => '96894249400@s.whatsapp.net']], 200);
            }

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => $rowAsks >= 3 ? 'open' : 'close']], 200);
            }

            return Http::response([], 500);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertSame('open', $result['state']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/delete/') || str_contains($r->url(), '/instance/create'));
    }

    /** الرمزُ الحاليّ لجلسةٍ تنتظر — الأحدثُ دائماً، مع عدّاده. */
    public function test_current_pairing_returns_the_rotated_code(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'connecting']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'office', 'connectionStatus' => 'connecting', 'ownerJid' => null]], 200);
            }

            return Http::response(['pairingCode' => 'R4T8WZ2N', 'code' => '2@R3', 'base64' => 'data:image/png;base64,iVBORw0KGgoR3=', 'count' => 3], 200);
        });

        $now = (new EvolutionProvider())->currentPairing();

        $this->assertSame('R4T8WZ2N', $now['code']);
        $this->assertSame(3, $now['count']);
        $this->assertSame('data:image/png;base64,iVBORw0KGgoR3=', $now['qr']);
    }

    /** ولا يُسأل «connect» وجلسةٌ مغلقة — هناك يفتح جلسةً جديدةً لا يطلبها أحد. */
    public function test_current_pairing_never_opens_a_session(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            return Http::response([], 200);
        });

        $now = (new EvolutionProvider())->currentPairing();

        $this->assertSame('close', $now['state']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/connect/'));
    }

    /** وطلبان معاً لا يتسابقان على حذف النسخة وإنشائها — الثاني ينتظر. */
    public function test_two_pairing_requests_never_run_at_once(): void
    {
        Http::fake();
        \Illuminate\Support\Facades\Cache::lock('wa:pair:' . WhatsAppSettings::evolutionInstance(), 60)->get();

        $result = (new EvolutionProvider())->pair();

        $this->assertStringContainsString('ما زال يُجهَّز', (string) $result['message']);
        Http::assertNothingSent();
    }

    // ══════════ لا يُحذف شيءٌ على جهل ══════════

    /** جسرٌ صامتٌ كلُّه (لا حالةَ ولا صفّ): لا حذفَ ولا إنشاء — ويُقال لماذا. */
    public function test_a_silent_bridge_is_never_touched_and_the_reason_is_named(): void
    {
        \App\Models\Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->subDay()->toIso8601String(), 'whatsapp');

        Http::fake(fn () => Http::response(['status' => 500, 'error' => 'Internal Server Error'], 500));

        $result = (new EvolutionProvider())->pair('96894249400');

        $this->assertStringContainsString('لا يجيب الآن (HTTP 500)', (string) $result['message']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/delete/') || str_contains($r->url(), '/instance/create'));
    }

    /**
     * جسرٌ يردّ ٥٠٠ على صفّ النسخة والشاشةُ تسأل كلَّ ثانيتين ونصف: سطرٌ واحدٌ في
     * السجلّ كلَّ دقيقة — لا نحو ١٤٠ سطراً متطابقاً في جلسة اقترانٍ واحدة تدفن غيرها.
     */
    public function test_a_failing_record_lookup_is_logged_once_a_minute(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/instance/fetchInstances')) {
                return Http::response(['status' => 500, 'error' => 'Internal Server Error'], 500);
            }

            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'connecting']], 200);
            }

            return Http::response(['pairingCode' => 'Z9Y8X7W6', 'count' => 1], 200);
        });
        \Illuminate\Support\Facades\Log::spy();
        $failed = fn ($m) => str_contains((string) $m, 'fetchInstances failed (500)');

        $provider = new EvolutionProvider();
        foreach (range(1, 5) as $_) {
            $provider->currentPairing();
        }

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs($failed)->once();

        $this->travel(61)->seconds();
        $provider->currentPairing();

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs($failed)->twice();
    }

    /**
     * صفٌّ لا يُقرأ (fetchInstances يردّ ٥٠٠) والحالةُ تُقرأ: لا يُوقَف الربطُ أبداً —
     * جلسةٌ بقيت تنتظر بعد ثوانٍ تُطوى وتُنشأ بالرقم. (ما رآه المكتب: «الجسرُ لا يجيب»
     * عند كلّ ضغطة، والجسرُ يجيب عن كلّ شيءٍ إلا هذا الاستعلام.)
     */
    public function test_an_unreadable_record_with_a_readable_state_still_pairs(): void
    {
        \App\Models\Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->subDay()->toIso8601String(), 'whatsapp');
        $created = [];

        Http::fake(function ($request) use (&$created) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'connecting']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response(['status' => 500, 'error' => 'Internal Server Error',
                    'response' => ['message' => ['PrismaClientKnownRequestError']]], 500);
            }

            if (str_contains($url, '/instance/delete/')) {
                return Http::response(['status' => 'SUCCESS', 'error' => false], 200);
            }

            $created[] = $request->data();

            return Http::response(['instance' => ['instanceName' => 'office'],
                'qrcode' => ['pairingCode' => 'Z9Y8X7W6', 'base64' => 'iVBORw0KGgo=', 'count' => 1]], 201);
        });

        $result = (new EvolutionProvider())->pair('96894249400');

        $this->assertSame('Z9Y8X7W6', $result['code']);
        $this->assertSame('96894249400', $created[0]['number'] ?? null);
    }

    /** وجلسةٌ مقترنةٌ تعيد وصلَها في ثواني الانتظار تلك: تُترك — لا تُطوى. */
    public function test_an_unreadable_record_never_costs_a_session_that_reopens(): void
    {
        $asks = 0;

        Http::fake(function ($request) use (&$asks) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => ++$asks === 1 ? 'connecting' : 'open']], 200);
            }

            return Http::response(['status' => 500, 'error' => 'Internal Server Error'], 500);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertSame('open', $result['state']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/delete/') || str_contains($r->url(), '/instance/create'));
    }

    /**
     * مكتبٌ اقترن من قبلُ ونسختُه غائبةٌ عن الجسر: لا يُنشأ بديلٌ من أوّل ضغطة —
     * الجسرُ قد يكون يقلع وإنشاءٌ حينها يمحو اعتمادَ النسخة. بعد دقيقةٍ ونصفٍ من
     * غيابٍ متّصلٍ تُنشأ.
     */
    public function test_a_missing_instance_of_a_paired_office_is_recreated_only_after_it_stays_missing(): void
    {
        \App\Models\Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->subDay()->toIso8601String(), 'whatsapp');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/instance/create')) {
                return Http::response($this->qrCreated(), 201);
            }

            return Http::response(['status' => 404, 'error' => 'Not Found'], 404);
        });

        $first = (new EvolutionProvider())->pair();

        $this->assertNull($first['qr']);
        $this->assertStringContainsString('انتظر دقيقتين', (string) $first['message']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/create'));

        $this->travel(2)->minutes();

        $later = (new EvolutionProvider())->pair();

        $this->assertNotNull($later['qr'], 'بقي المكتبُ بلا رمزٍ والنسخةُ غائبةٌ منذ دقيقتين');
    }

    /** اقترانٌ قديمٌ لا يعود وصلُه دقيقتين بين ضغطتين: تُنشأ نسخةٌ نظيفة — لا رسالةٌ تتكرّر أبداً. */
    public function test_a_paired_instance_that_never_reconnects_gives_way_after_two_minutes(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'connecting']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'office', 'connectionStatus' => 'open', 'ownerJid' => '96894249400@s.whatsapp.net']], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                return Http::response(['status' => 'SUCCESS', 'error' => false], 200);
            }

            return Http::response($this->qrCreated(), 201);
        });

        $first = (new EvolutionProvider())->pair();
        $this->assertNull($first['qr']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/delete/'));

        $this->travel(3)->minutes();

        $later = (new EvolutionProvider())->pair();
        $this->assertNotNull($later['qr']);
    }

    /** ضغطةٌ في الثواني التي تلي مسحاً ناجحاً: يُعاد السؤالُ قبل الطيّ، فلا يُلغى الاقترانُ الذي تمّ. */
    public function test_a_press_right_after_a_successful_scan_does_not_unlink_it(): void
    {
        $asks = 0;

        Http::fake(function ($request) use (&$asks) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                // إعادةُ التشغيل بعد المسح: close لحظةً، ثمّ open
                return Http::response(['instance' => ['state' => ++$asks === 1 ? 'close' : 'open']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'office', 'connectionStatus' => 'connecting', 'ownerJid' => null]], 200);
            }

            return Http::response([], 200);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertSame('open', $result['state']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/delete/') || str_contains($r->url(), '/instance/create'));
    }

    // ══════════ ما يفعله الجسرُ بعد الحذف ══════════

    /**
     * الحذفُ يردّ قبل أن تغادر النسخةُ ذاكرةَ الجسر: الإنشاءُ الفوريّ يجد الاسم «مستعمَلاً»
     * (403 بلا سبب) — فيُعاد بعد مهلةٍ قصيرة لا يُقال للمكتب «أخفق».
     */
    public function test_create_is_retried_while_the_bridge_finishes_the_delete(): void
    {
        $creates = 0;

        Http::fake(function ($request) use (&$creates) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'office', 'connectionStatus' => 'close', 'ownerJid' => null]], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                return Http::response(['status' => 'SUCCESS', 'error' => false], 200);
            }

            $creates++;

            return $creates === 1
                ? Http::response(['status' => 403, 'error' => 'Forbidden', 'response' => ['message' => ['This name "office" is already in use.']]], 403)
                : Http::response($this->qrCreated(), 201);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertNotNull($result['qr']);
        $this->assertSame(2, $creates);
    }

    /** وجسرٌ لا يحذف ولا ينشئ: يُقال ذلك بعد محاولاتٍ محدودة — لا حلقةً بلا نهاية. */
    public function test_a_bridge_that_neither_deletes_nor_creates_is_reported_after_bounded_tries(): void
    {
        $creates = 0;

        Http::fake(function ($request) use (&$creates) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'office', 'connectionStatus' => 'close', 'ownerJid' => null]], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                return Http::response(['status' => 500, 'error' => 'Internal Server Error'], 500);
            }

            $creates++;

            return Http::response(['status' => 403, 'error' => 'Forbidden', 'response' => ['message' => ['This name "office" is already in use.']]], 403);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertNull($result['qr']);
        $this->assertStringContainsString('أعد المحاولة', (string) $result['message']);
        $this->assertLessThanOrEqual(4, $creates);
    }

    /** نسخةٌ فصلها الهاتف (close ولها مالكٌ قديم): تُطوى وتُنشأ نظيفة — هي أكثرُ الحالات وقوعاً. */
    public function test_an_instance_the_phone_unlinked_is_replaced(): void
    {
        $deletes = 0;
        $creates = 0;

        Http::fake(function ($request) use (&$deletes, &$creates) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'office', 'connectionStatus' => 'close', 'ownerJid' => '96894249400@s.whatsapp.net',
                    'disconnectionReasonCode' => 401]], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                $deletes++;

                return Http::response(['status' => 'SUCCESS', 'error' => false], 200);
            }

            $creates++;

            return Http::response($this->qrCreated(), 201);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertNotNull($result['qr']);
        $this->assertSame(1, $deletes);
        $this->assertSame(1, $creates);
    }

    /**
     * ختمُ «التعثّر» يُمحى متى عاد الوصل — من أيّ طريق. وإلا عُدّ سقوطٌ قبل عشر دقائق
     * مع سقوطٍ عابرٍ الآن، فطوت ضغطةٌ واحدةٌ اقتراناً يعمل.
     */
    public function test_an_old_stumble_followed_by_recovery_never_unlinks_on_a_later_blip(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'connecting']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'office', 'connectionStatus' => 'open', 'ownerJid' => '96894249400@s.whatsapp.net']], 200);
            }

            return Http::response($this->qrCreated(), 201);
        });

        (new EvolutionProvider())->pair();          // تعثّرٌ أوّل يُختم

        // عاد الوصلُ بنفسه — والويبهوكُ يقول ذلك
        $this->postJson('/webhooks/evolution/' . WhatsAppSettings::evolutionSecret(), [
            'event' => 'connection.update', 'data' => ['state' => 'open'],
        ])->assertOk();

        $this->travel(10)->minutes();

        $result = (new EvolutionProvider())->pair(); // سقوطٌ عابرٌ وضغطةٌ واحدة

        $this->assertNull($result['qr']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/delete/') || str_contains($r->url(), '/instance/create'));
    }

    // ══════════ موعدُ الطلب ورحلتُه ══════════

    /**
     * حذفٌ بلا ردّ مرّتين: الإنشاءُ بعدهما يُمهَل ما بقي من الثلاثين لا خمساً وعشرين —
     * فيردّ الطلبُ قبل أن يقطعه الوسيط (nginx بعد دقيقة) بصفحة ٥٠٤ بلا رمزٍ ولا سبب.
     */
    public function test_a_slow_delete_never_pushes_the_request_past_its_budget(): void
    {
        $p = new ClockedProvider();

        Http::fake(function ($request) use ($p) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'x', 'connectionStatus' => 'close', 'ownerJid' => null]], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                // الجسرُ يُمسك الحذفَ حتى تنقضي مهلتُه (١٠ ث)
                $p->now += 10;

                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 10000 milliseconds');
            }

            return Http::response($this->qrCreated(), 201);
        });

        $result = $p->pair();

        $this->assertSame('connecting', $result['state']);
        $this->assertNotNull($result['qr']);
        // ‏١٠ + ١٫٥ + ١٠ = ٢١٫٥ ث مضت: الإنشاءُ أُمهل ما بقي (٨٫٥ ⇐ ٩) لا عشرين
        $this->assertSame(9, end($p->timeouts), 'مهلةُ الإنشاء لا تتجاوز ما بقي من الموعد');
        $this->assertEqualsWithDelta(21.5, $result['took'], 0.2);
        $this->assertSame(2, count(array_filter($result['trace'], fn ($t) => str_starts_with($t, 'الحذف: مهلةٌ بلا ردّ'))));
        $this->assertContains('الإنشاء: HTTP 201 (0.0 ث)', $result['trace']);
    }

    /**
     * الحذفُ يُنفَّذ عند الجسر وإن انقضت مهلتُنا، فحذفٌ لا يتبعه إنشاءٌ يترك المكتبَ بلا
     * نسخة — والضغطةُ التالية تجدها «غائبة» فتنتظر دقيقةً ونصفاً. فإن لم يبقَ للحذف ثمّ
     * الإنشاء وقتُهما لم يُبدأ شيء، ويُقال «انقضى الموعد» مع الخطوات التي أكلته.
     */
    public function test_no_delete_is_started_when_no_time_is_left_to_create_after_it(): void
    {
        \App\Models\Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->subDay()->toIso8601String(), 'whatsapp');
        $p = new ClockedProvider();

        // الجسرُ يقلع: الذاكرةُ لا تعرف النسخة (٤٠٤) وكلُّ سؤالٍ عن صفّها يأخذ ستَّ ثوانٍ
        Http::fake(function ($request) use ($p) {
            $url = $request->url();

            if (str_contains($url, '/instance/fetchInstances')) {
                $p->now += 6;
            }

            if (str_contains($url, '/instance/create')) {
                return Http::response($this->qrCreated(), 201);
            }

            return Http::response(['status' => 404, 'error' => 'Not Found'], 404);
        });

        // الضغطةُ الأولى: غيابٌ لم يدم بعدُ — يُنتظر
        $first = $p->pair();
        $this->assertStringContainsString('انتظر دقيقتين', (string) $first['message']);

        // وبعد دقيقةٍ ونصف: الغيابُ دام، لكنّ انتظارَ الصفّ ثلاثاً أكل الموعد (٦ + ٢٫٥ + ٦ + ٢٫٥ + ٦ = ٢٣ ث)
        $this->travel(100)->seconds();
        $result = $p->pair();

        $this->assertSame('close', $result['state']);
        $this->assertStringContainsString('انقضى موعدُ الطلب (30 ث) قبل طيّ الجلسة القديمة', (string) $result['message']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/delete/') || str_contains($r->url(), '/instance/create'));
        $this->assertLessThanOrEqual(30, $result['took']);
    }

    /**
     * جسرٌ متوقّف: curl ٨ يقول «Failed to connect … Couldn't connect to server» لا
     * «Connection refused» — فكان يُعرض نصُّ curl بعنوان الجسر ومنفذه بدل «متوقّف».
     */
    public function test_a_bridge_that_is_down_is_named_as_down_not_quoted(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException("cURL error 7: Failed to connect to 127.0.0.1 port 18999 after 0 ms: Couldn't connect to server");
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertSame('close', $result['state']);
        $this->assertStringContainsString('الاتصال مرفوض — الجسرُ متوقّف؟', (string) $result['message']);
        $this->assertStringNotContainsString('127.0.0.1', (string) $result['message'] . implode(' ', $result['trace']));
    }

    /** ردُّ الطلب يحمل رحلتَه: كلُّ نداءٍ وجوابُه ومدّتُه — ولا سرَّ فيها. */
    public function test_the_answer_carries_the_journey_step_by_step(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response(['status' => 500, 'error' => 'Internal Server Error'], 500);
            }

            if (str_contains($url, '/instance/delete/')) {
                return Http::response(['status' => 'SUCCESS', 'error' => false, 'token' => 'SECRET-TOKEN'], 200);
            }

            return Http::response($this->qrCreated() + ['hash' => 'SECRET-HASH'], 201);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertSame('connecting', $result['state']);
        $this->assertIsFloat($result['took']);
        $steps = implode(' | ', $result['trace']);
        $this->assertStringContainsString('الحالة: close', $steps);
        $this->assertStringContainsString('السجلّ: HTTP 500', $steps);
        $this->assertStringContainsString('الحذف: HTTP 200', $steps);
        $this->assertStringContainsString('الإنشاء: HTTP 201', $steps);
        $this->assertStringNotContainsString('SECRET', $steps);
        $this->assertStringNotContainsString('bridge-key', $steps);
    }

    /** ومن حُجب لأنّ طلباً آخر يعمل يُقال له منذ متى — لا «انتظر» مبهمة. */
    public function test_the_busy_message_says_how_long_the_other_request_has_run(): void
    {
        Http::fake();
        $instance = WhatsAppSettings::evolutionInstance();
        \Illuminate\Support\Facades\Cache::lock('wa:pair:' . $instance, 60)->get();
        \Illuminate\Support\Facades\Cache::put('wa:pair:running:' . $instance, microtime(true) - 7, 120);

        $result = (new EvolutionProvider())->pair();

        $this->assertStringContainsString('منذ 7 ث', (string) $result['message']);
        Http::assertNothingSent();
    }

    /** علامةُ «يعمل الآن» تُرفع مع الطلب وتُمحى بعده، ونتيجتُه تُحفظ للشاشة. */
    public function test_the_last_attempt_is_kept_and_the_running_mark_is_cleared(): void
    {
        $seen = [];

        // تُقرأ العلامةُ من داخل نداء الجسر: الموضعُ الوحيد الذي يكون الطلبُ فيه «يعمل الآن»
        Http::fake(function () use (&$seen) {
            $seen[] = EvolutionProvider::pairingRunningFor();

            return Http::response(['status' => 500], 500);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertSame('close', $result['state']);
        $this->assertNotEmpty($seen);
        $this->assertNotNull($seen[0], 'علامةُ «يعمل الآن» مرفوعةٌ أثناء الطلب');
        $this->assertLessThanOrEqual(1, $seen[0]);
        $this->assertNull(EvolutionProvider::pairingRunningFor());

        $last = EvolutionProvider::lastAttempt();
        $this->assertSame('close', $last['state']);
        $this->assertSame($result['message'], $last['message']);
        $this->assertNotEmpty($last['trace']);
        $this->assertEqualsWithDelta(time(), $last['at'], 2);
    }

    /**
     * سؤالُ الشاشة يقرأ ما بعث به الجسرُ في ويبهوكه قبل أن يسأله عن صفّ النسخة:
     * استعلامُ الصفّ يعدّ رسائلَ النسخة وجهاتِها كلَّها (instanceInfo: _count) —
     * والشاشةُ تسأل كلَّ ثانيتين ونصف، فكان العدُّ الثقيلُ يُطلب والرمزُ في اليد.
     */
    public function test_the_poll_serves_the_webhook_code_without_counting_the_bridge_records(): void
    {
        Http::fake(fn () => Http::response(['instance' => ['state' => 'connecting']], 200));
        EvolutionProvider::rememberPairing(['pairingCode' => 'ABCD1234', 'base64' => 'iVBORw0KGgoQ=', 'count' => 3]);

        $now = (new EvolutionProvider())->currentPairing();

        $this->assertSame('ABCD1234', $now['code']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/fetchInstances'));
    }

    // ══════════ لا يُحذف شيءٌ على جهل — ولو كان الجهلُ مهلةً ══════════

    /**
     * مكتبٌ اقترن من قبل، وصفُّ نسخته لم يُجب في مهلته (قاعدةُ الجسر تعدّ رسائلَه كلَّها
     * وتبطئ)، والحالةُ «close» (سقوطٌ عابر): كان يُطوى ويُنشأ بديلٌ — والطيُّ يُلغي اقترانَ
     * الهاتف. الآن لا يُمَسّ، ويُقال لماذا، والصفُّ يُمهَل اثنتي عشرةَ ثانية لا ستّاً.
     */
    public function test_a_slow_record_never_costs_a_paired_office_its_session(): void
    {
        \App\Models\Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->subDay()->toIso8601String(), 'whatsapp');
        $p = new ClockedProvider();

        Http::fake(function ($request) use ($p) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                $p->now += end($p->timeouts);

                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 12000 milliseconds');
            }

            return Http::response(['status' => 'SUCCESS'], 200);
        });

        $result = $p->pair();

        $this->assertSame('close', $result['state']);
        $this->assertStringContainsString('لم يُغيَّر شيء حتى لا يُلغى اقترانُه', (string) $result['message']);
        $this->assertSame(12, $p->timeouts[1], 'الصفُّ يُمهَل اثنتي عشرةَ ثانية');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/delete/') || str_contains($r->url(), '/instance/create'));
    }

    /** ومكتبٌ لم يقترن قطّ لا نسخةَ له تُحمى: مهلةُ الصفّ لا توقف ربطَه الأوّل. */
    public function test_a_never_paired_office_still_pairs_when_the_record_is_slow(): void
    {
        $p = new ClockedProvider();

        Http::fake(function ($request) use ($p) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                $p->now += end($p->timeouts);

                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 12000 milliseconds');
            }

            if (str_contains($url, '/instance/delete/')) {
                return Http::response(['status' => 404, 'error' => 'Not Found'], 404);
            }

            return Http::response($this->qrCreated(), 201);
        });

        $result = $p->pair();

        $this->assertSame('connecting', $result['state']);
        $this->assertNotNull($result['qr']);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/instance/create'));
    }

    /**
     * حذفٌ صدر يتبعه إنشاءٌ أبداً: مهلةُ الحذف لا تأكل نافذةَ الإنشاء، والإنشاءُ الأوّل
     * بعده لا يُتخطّى — وإلا بقي المكتبُ بلا نسخةٍ والضغطةُ التالية «غائبة».
     */
    public function test_a_delete_that_was_issued_is_always_followed_by_a_create(): void
    {
        $p = new ClockedProvider();
        $deletes = 0;

        Http::fake(function ($request) use ($p, &$deletes) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                // الصفُّ بطيء (٥٫٩ ث) لكنّه يُجيب: غيرُ مقترن
                $p->now += 5.9;

                return Http::response([['name' => 'x', 'connectionStatus' => 'close', 'ownerJid' => null]], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                // الجسرُ يُمسك الحذفَ حتى تنقضي المهلةُ الممنوحة له
                $deletes++;
                $p->now += end($p->timeouts);

                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
            }

            return Http::response($this->qrCreated(), 201);
        });

        $result = $p->pair();

        $this->assertSame('connecting', $result['state']);
        $this->assertSame(2, $deletes);
        // الحذفُ الثاني أُمهل ما يُبقي للإنشاء نافذتَه (٤ ث)، والإنشاءُ ما بقي (٩)
        $this->assertSame([6, 12, 10, 4, 9], $p->timeouts);
        $this->assertLessThanOrEqual(30, $result['took']);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/instance/create'));
    }

    /** ونسخةٌ طواها المكتبُ نفسُه للتوّ لا يُنتظر غيابُها دقيقةً ونصفاً: تُنشأ فوراً. */
    public function test_an_instance_we_deleted_ourselves_is_recreated_without_the_boot_wait(): void
    {
        \App\Models\Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->subDay()->toIso8601String(), 'whatsapp');
        \Illuminate\Support\Facades\Cache::put('wa:pair:deleted:' . WhatsAppSettings::evolutionInstance(), time() - 10, 300);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/instance/create')) {
                return Http::response($this->qrCreated(), 201);
            }

            return Http::response(['status' => 404, 'error' => 'Not Found'], 404);
        });

        $result = (new EvolutionProvider())->pair();

        $this->assertSame('connecting', $result['state']);
        $this->assertStringNotContainsString('انتظر دقيقتين', (string) $result['message']);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/instance/create'));
    }

    /**
     * إنشاءٌ قطعته مهلتُنا والجسرُ كان قد فتح الجلسةَ (يُمسك الردَّ خمسَ ثوانٍ): لا يُقال
     * «تعذّر» عن جلسةٍ حيّة — وإلا طوتها الضغطةُ التالية وأُنشئت أخرى تُقطع مثلَها.
     */
    public function test_a_create_cut_by_our_timeout_is_recognised_as_a_live_session(): void
    {
        $p = new ClockedProvider();
        $opened = false;
        $creates = 0;

        Http::fake(function ($request) use ($p, &$opened, &$creates) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => $opened ? 'connecting' : 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'x', 'connectionStatus' => 'close', 'ownerJid' => null]], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                return Http::response(['status' => 'SUCCESS', 'error' => false], 200);
            }

            $creates++;
            $opened = true;
            $p->now += end($p->timeouts);

            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 20000 milliseconds');
        });

        $result = $p->pair();

        $this->assertSame('connecting', $result['state']);
        $this->assertStringContainsString('فُتحت الجلسةُ', (string) $result['message']);
        $this->assertSame(1, $creates);
        $this->assertSame('connecting', WhatsAppSettings::evolutionState());
    }

    /**
     * نسخةٌ مقترنةٌ سقط اتصالُها وجسرٌ لا يجيب عن الحالة في مهلته: لا تُطوى ولا يُنشأ
     * بديلٌ، وكلُّ نداءٍ محدودٌ بما بقي من الموعد — فيردّ الطلبُ في ثلاثين ثانية.
     */
    public function test_a_paired_row_on_a_silent_bridge_is_neither_deleted_nor_waited_for_past_the_budget(): void
    {
        $p = new ClockedProvider();

        Http::fake(function ($request) use ($p) {
            $url = $request->url();

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'x', 'connectionStatus' => 'open', 'ownerJid' => '96891234567@s.whatsapp.net']], 200);
            }

            if (str_contains($url, '/instance/delete/') || str_contains($url, '/instance/create')) {
                return Http::response(['status' => 'SUCCESS'], 200);
            }

            // الحالةُ وطرقُ الوصل: بلا ردٍّ حتى تنقضي المهلةُ الممنوحة
            $p->now += end($p->timeouts);

            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
        });

        $result = $p->pair();

        $this->assertSame('connecting', $result['state']);
        $this->assertStringContainsString('الرقمُ مقترنٌ من قبل', (string) $result['message']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/delete/') || str_contains($r->url(), '/instance/create'));
        // ‏٦ + ٢ + ٦ + ١٠ + ٣ + ٣ = ٣٠: آخرُ سؤالٍ عن الحالة أُمهل ما بقي (٣) لا ستّاً
        $this->assertSame([6, 12, 6, 10, 3], $p->timeouts);
        $this->assertEqualsWithDelta(30, $result['took'], 0.1);
    }

    /**
     * نسخةٌ عالقةٌ في الجسر (رأيناها عند مكتب والدي): الحالةُ «connecting»، وسجلُّ النسخة
     * يردّ ٥٠٠، والحذفُ بلا ردّ، والإنشاءُ ٤٠٣ «مستعمَلة». إعادةُ المحاولة عبثٌ: يُقال
     * إنّ الجسرَ نفسَه عالقٌ ويحتاج إعادةَ تشغيل — لا «انقضى الموعد» المبهمة.
     */
    public function test_a_wedged_instance_names_the_bridge_restart_instead_of_a_blind_timeout(): void
    {
        $p = new ClockedProvider();
        $creates = 0;

        Http::fake(function ($request) use ($p, &$creates) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'connecting']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                $p->now += 5;

                return Http::response(['status' => 500, 'error' => 'Internal Server Error'], 500);
            }

            if (str_contains($url, '/instance/delete/')) {
                $p->now += end($p->timeouts);

                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 10000 milliseconds');
            }

            $creates++;

            return Http::response(['status' => 403, 'error' => 'Forbidden',
                'response' => ['message' => ['This name "x" is already in use.']]], 403);
        });

        $result = $p->pair();

        $this->assertSame('close', $result['state']);
        $this->assertStringContainsString('عالقةٌ في الجسر', (string) $result['message']);
        $this->assertStringContainsString('إعادةَ تشغيل', (string) $result['message']);
        // لم يُعَد الإنشاءُ عبثاً: ضربةٌ واحدةٌ كشفت العلوق، ولم يُقل «انقضى الموعد»
        $this->assertSame(1, $creates);
        $this->assertStringNotContainsString('انقضى موعد', (string) $result['message']);
    }

    /** علامةُ «يعمل الآن» أقدمُ من أيّ طلبٍ ممكن (عاملٌ قُتل) لا تُصدَّق. */
    public function test_a_stale_running_mark_from_a_killed_worker_is_ignored(): void
    {
        $key = 'wa:pair:running:' . WhatsAppSettings::evolutionInstance();

        \Illuminate\Support\Facades\Cache::put($key, microtime(true) - 20, 90);
        $this->assertSame(20, EvolutionProvider::pairingRunningFor());

        \Illuminate\Support\Facades\Cache::put($key, microtime(true) - 70, 90);
        $this->assertNull(EvolutionProvider::pairingRunningFor());
    }


    /**
     * علامةُ «طواها المكتبُ بنفسه» غرضُها حذفٌ لم يتبعه إنشاء — فتُمحى متى تمّ الإنشاءُ أو
     * فُتحت الجلسة. بقاؤها بعد اقترانٍ جديد كانت تُسقط مهلةَ إقلاع الجسر: ضغطةٌ أثناء
     * إعادة تشغيله (النسخةُ «غائبة» من ذاكرته) تنشئ فوق صفّها المقترن فيمحوه الجسر.
     */
    public function test_the_self_delete_mark_dies_with_the_create_or_the_open_that_follows(): void
    {
        $instance = WhatsAppSettings::evolutionInstance();
        $key = 'wa:pair:deleted:' . $instance;
        \App\Models\Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->subDay()->toIso8601String(), 'whatsapp');

        // حذفٌ ثمّ إنشاءٌ ناجح: لا علامةَ تبقى. (مزيّفٌ واحدٌ يُبدَّل طورُه: Http::fake الثانية
        // تُضاف بعد الأولى ولا تحلّ محلَّها، فأوّلُ من يجيب هو الأوّل)
        $restarting = false;
        $createsWhileRestarting = 0;

        Http::fake(function ($request) use (&$restarting, &$createsWhileRestarting) {
            $url = $request->url();

            if ($restarting) {
                $createsWhileRestarting += str_contains($url, '/instance/create') ? 1 : 0;

                return Http::response(['status' => 404, 'error' => 'Not Found'], 404);
            }

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'x', 'connectionStatus' => 'close', 'ownerJid' => null]], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                return Http::response(['status' => 'SUCCESS', 'error' => false], 200);
            }

            return Http::response($this->qrCreated(), 201);
        });

        $this->assertSame('connecting', (new EvolutionProvider())->pair()['state']);
        $this->assertNull(\Illuminate\Support\Facades\Cache::get($key), 'الإنشاءُ تمّ فلا علامةَ حذفٍ معلّق');

        // وحذفٌ لم يتبعه إنشاء ثمّ فُتحت الجلسةُ (ويبهوك): العلامةُ تُمحى مع سائر الآثار
        \Illuminate\Support\Facades\Cache::put($key, time(), 300);
        WhatsAppSettings::setEvolutionState('open');
        $this->assertNull(\Illuminate\Support\Facades\Cache::get($key));

        // فضغطةٌ أثناء إقلاع الجسر (٤٠٤ في كلّ شيء) تنتظر كما كانت — لا تنشئ فوق الصفّ المقترن
        $restarting = true;

        $result = (new EvolutionProvider())->pair();

        $this->assertStringContainsString('انتظر دقيقتين', (string) $result['message']);
        // (المزيّفُ يحفظ إنشاءَ الطور الأوّل أيضاً — فيُعدّ إنشاءُ الإقلاع وحده)
        $this->assertSame(0, $createsWhileRestarting);
    }

    /** الجسرُ البطيء يفتح المقبسَ بعد ثوانٍ من قطع الإنشاء: يُنظر ثلاثاً لا مرّة. */
    public function test_a_session_that_opens_seconds_after_the_cut_create_is_still_recognised(): void
    {
        $p = new ClockedProvider();
        $states = 0;
        $cutAt = null;

        Http::fake(function ($request) use ($p, &$states, &$cutAt) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                // بعد القطع: «close» في النظرتين الأوليين، ثمّ «connecting» بعد أربع ثوانٍ
                $late = $cutAt !== null && $p->now - $cutAt >= 4;
                $states++;

                return Http::response(['instance' => ['state' => $late ? 'connecting' : 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'x', 'connectionStatus' => 'close', 'ownerJid' => null]], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                return Http::response(['status' => 'SUCCESS', 'error' => false], 200);
            }

            $p->now += end($p->timeouts);
            $cutAt = $p->now;

            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 20000 milliseconds');
        });

        $result = $p->pair();

        $this->assertSame('connecting', $result['state']);
        $this->assertStringContainsString('فُتحت الجلسةُ', (string) $result['message']);
        $this->assertLessThanOrEqual(31, $result['took']);
        // سؤالٌ قبل الإنشاء وثلاثُ نظراتٍ بعده (الإنشاءُ المقطوع لا يُسجّله المزيّف)
        $this->assertSame(4, $states);
    }

    /**
     * والجسرُ الأبطأ: بعد قطع الإنشاء تبقى الحالةُ «close» (٢٠٠ — النسخةُ في ذاكرته والمقبسُ
     * لم يقم؛ يجلب إصدارَ واتساب بلا مهلة عنده). كان يُقال «تعذّر» فتطوي الضغطةُ التالية
     * جلسةً على وشك أن تُصدر رمزَها — فتُقطع الثانيةُ مثلَها ولا يرى المكتبُ رمزاً أبداً.
     */
    public function test_a_create_still_opening_after_the_cut_is_reported_as_pending_not_failed(): void
    {
        $p = new ClockedProvider();
        // (مزيّفٌ واحدٌ يُبدَّل طورُه: Http::fake الثانية تُضاف ولا تحلّ محلَّ الأولى)
        $gone = false;

        Http::fake(function ($request) use ($p, &$gone) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return $gone
                    ? Http::response(['status' => 404, 'error' => 'Not Found'], 404)
                    : Http::response(['instance' => ['state' => 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return $gone
                    ? Http::response(['status' => 404], 404)
                    : Http::response([['name' => 'x', 'connectionStatus' => 'close', 'ownerJid' => null]], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                return Http::response(['status' => 'SUCCESS', 'error' => false], $gone ? 404 : 200);
            }

            $p->now += end($p->timeouts);

            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 20000 milliseconds');
        });

        $result = $p->pair();

        $this->assertSame('connecting', $result['state']);
        $this->assertStringContainsString('ما زال يفتح الجلسةَ', (string) $result['message']);
        $this->assertSame(0, EvolutionProvider::createPendingFor());
        $this->assertSame('connecting', WhatsAppSettings::evolutionState());

        // وجسرٌ لا يعرف النسخةَ أصلاً (٤٠٤ بعد القطع): لا جلسةَ تُنتظر — يُقال «تعذّر»
        $gone = true;
        $again = $p->pair();

        $this->assertSame('close', $again['state']);
        $this->assertStringContainsString('تعذّر الاتصال بخادم الجسر لإنشاء', (string) $again['message']);
        $this->assertNull(EvolutionProvider::createPendingFor());
    }

}

/** مزوّدٌ بساعةٍ تُحرَّك باليد: الثواني تمضي بلا انتظار، والمهلُ المحسوبة تُسجَّل. */
class ClockedProvider extends EvolutionProvider
{
    public float $now = 1_000_000.0;

    /** @var list<int> */
    public array $timeouts = [];

    protected function clock(): float
    {
        return $this->now;
    }

    protected function sleep(int $ms): void
    {
        $this->now += $ms / 1000;
    }

    protected function within(int $nominalS): int
    {
        return $this->timeouts[] = parent::within($nominalS);
    }
}
