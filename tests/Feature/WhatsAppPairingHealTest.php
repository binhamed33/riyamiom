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

    /** جسرٌ لا يجيب عن صفّ النسخة (٥٠٠ أو مهلة): لا حذفَ ولا إنشاء — صمتُه ليس «لم تقترن». */
    public function test_an_unreadable_bridge_record_never_leads_to_a_delete(): void
    {
        \App\Models\Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->subDay()->toIso8601String(), 'whatsapp');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'connecting']], 200);
            }

            return Http::response(['status' => 500, 'error' => 'Internal Server Error'], 500);
        });

        $result = (new EvolutionProvider())->pair('96894249400');

        $this->assertStringContainsString('لا يجيب', (string) $result['message']);
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
}
