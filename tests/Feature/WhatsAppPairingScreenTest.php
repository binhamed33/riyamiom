<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * شاشةُ الاقتران كما رآها المكتب: رمزٌ ظاهرٌ لا يُقبل، و«اطلب رمزاً» يقول
 * «تعذّر إحضار الرمز» بلا سبب.
 *
 * ═══ ما كان ═══
 *
 * ‏throttle بلا بادئةٍ مفتاحُه المستخدمُ وحده: مجموعةُ الصفحات (١٢٠) وحدُّ
 * سؤال الحالة (٦٠) يعدّان في المفتاح نفسِه الذي يقرؤه حدُّ «اطلب رمزاً» (٢٠).
 * والشاشةُ تسأل الحالةَ كلَّ ثانيتين — مرّتين في كلّ سؤال — فبعد ستّ عشرة ثانية
 * من ظهور الرمز صار كلُّ طلبٍ للرمز ٤٢٩. والردُّ ‎{ok:false,error}‎ بلا message،
 * والشاشةُ تقرأ message، فكتبت «تعذّر إحضار الرمز» عامّةً.
 */
class WhatsAppPairingScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.default' => 'evolution',
            'whatsapp.evolution.base_url' => 'https://bridge.test',
            'whatsapp.evolution.api_key' => 'bridge-key-0123456789',
        ]);
        Setting::set('whatsapp_provider', 'evolution', 'whatsapp');

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    /** جلسةٌ تنتظر ورمزُ ربطها الحاليّ — كما يردّ الجسرُ في «connecting». */
    private function waitingBridge(string $code = 'K7M2QX9P', int $count = 2): void
    {
        Http::fake(function ($request) use ($code, $count) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'connecting']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'office', 'connectionStatus' => 'connecting', 'ownerJid' => null]], 200);
            }

            if (str_contains($url, '/instance/connect/')) {
                return Http::response(['pairingCode' => $code, 'code' => '2@Q', 'base64' => 'iVBORw0KGgoQ=', 'count' => $count], 200);
            }

            if (str_contains($url, '/instance/delete/')) {
                return Http::response(['status' => 'SUCCESS', 'error' => false], 200);
            }

            return Http::response(['instance' => ['instanceName' => 'office'],
                'qrcode' => ['pairingCode' => $code, 'code' => '2@Q', 'base64' => 'iVBORw0KGgoQ=', 'count' => 1]], 201);
        });
    }

    /** سؤالُ الحالة مهما تكرّر لا يُغلق «اطلب رمزاً». */
    public function test_polling_the_state_never_blocks_asking_for_a_code(): void
    {
        $this->waitingBridge();

        // دقيقةٌ من شاشةٍ مفتوحة: سؤالٌ كلَّ ثانيتين ونصف
        for ($i = 0; $i < 24; $i++) {
            $this->actingAs($this->admin)->getJson(route('settings.whatsapp.pair-state'))->assertOk();
        }

        $this->actingAs($this->admin)
            ->postJson(route('settings.whatsapp.pair'), ['phone' => '96894249400'])
            ->assertOk()
            ->assertJsonPath('code', 'K7M2QX9P');
    }

    /** والسؤالُ يعيد الرمزَ الأحدث — الجسرُ يبدّله كلَّ ٤٥ ثانية. */
    public function test_the_state_poll_carries_the_latest_code(): void
    {
        $this->waitingBridge('R4T8WZ2N', 4);

        $this->actingAs($this->admin)
            ->getJson(route('settings.whatsapp.pair-state'))
            ->assertOk()
            ->assertJsonPath('state', 'connecting')
            ->assertJsonPath('code', 'R4T8WZ2N')
            ->assertJsonPath('count', 4);

        // ولا سرَّ في الردّ: رمزُ النسخة (token) ومفتاحُ الجسر لا يخرجان
        $body = $this->actingAs($this->admin)->getJson(route('settings.whatsapp.pair-state'))->getContent();
        $this->assertStringNotContainsString('bridge-key-0123456789', $body);
        $this->assertStringNotContainsString('token', $body);
    }

    /** وحين يُرفض الطلبُ يُقال سببُه — لا «تعذّر» بلا سبب. */
    public function test_a_throttled_request_names_its_reason(): void
    {
        $this->waitingBridge();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($this->admin)->postJson(route('settings.whatsapp.pair'), []);
        }

        $response = $this->actingAs($this->admin)->postJson(route('settings.whatsapp.pair'), []);

        $response->assertStatus(429);
        $this->assertStringContainsString('انتظر دقيقة', (string) $response->json('message'));
    }

    /**
     * الرمزُ الذي يرسله الجسرُ في ويبهوكه يُعرض كما هو — بلا سؤال «connect».
     *
     * بعد المسح الناجح تمرّ الجلسةُ بـ«close» لحظةً (إعادةُ التشغيل 515)، و«connect»
     * في «close» يفتح جلسةً ثانيةً تُضيّع الاقترانَ الذي تمّ للتوّ.
     */
    public function test_the_code_pushed_by_the_bridge_is_shown_without_asking_connect(): void
    {
        $this->waitingBridge('OLD00000');

        $this->postJson('/webhooks/evolution/' . WhatsAppSettings::evolutionSecret(), [
            'event' => 'qrcode.updated',
            'instance' => 'office',
            'data' => ['qrcode' => ['instance' => 'office', 'pairingCode' => 'P8W3KX7M', 'code' => '2@R', 'base64' => 'data:image/png;base64,iVBORw0KGgoNEW=']],
        ])->assertOk();

        $this->actingAs($this->admin)
            ->getJson(route('settings.whatsapp.pair-state'))
            ->assertOk()
            ->assertJsonPath('code', 'P8W3KX7M')
            ->assertJsonPath('qr', 'data:image/png;base64,iVBORw0KGgoNEW=');

        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/connect/'));
    }

    /** وبلا ويبهوك: لا يُسأل «connect» إلا والحالةُ «connecting» في اللحظة نفسها. */
    public function test_connect_is_never_asked_once_the_session_has_dropped(): void
    {
        $asked = 0;

        Http::fake(function ($request) use (&$asked) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                // أوّلُ سؤالٍ «connecting»، والتالي «close»: سقطت الجلسةُ بين النداءين
                return Http::response(['instance' => ['state' => ++$asked === 1 ? 'connecting' : 'close']], 200);
            }

            if (str_contains($url, '/instance/fetchInstances')) {
                return Http::response([['name' => 'office', 'connectionStatus' => 'connecting', 'ownerJid' => null]], 200);
            }

            return Http::response(['pairingCode' => 'STALE000', 'base64' => 'iVBORw0KGgo=', 'count' => 9], 200);
        });

        $this->actingAs($this->admin)
            ->getJson(route('settings.whatsapp.pair-state'))
            ->assertOk()
            ->assertJsonPath('code', null);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/connect/'));
    }

    /** ورفضُ الجسر بعد نفاد الرموز («refused») يُقرأ إغلاقاً — لا «connecting» أبدية. */
    public function test_a_refused_session_reads_as_closed(): void
    {
        WhatsAppSettings::setEvolutionState('connecting');

        $this->postJson('/webhooks/evolution/' . WhatsAppSettings::evolutionSecret(), [
            'event' => 'connection.update',
            'data' => ['instance' => 'office', 'state' => 'refused', 'statusReason' => 428],
        ])->assertOk();

        $this->assertSame('close', WhatsAppSettings::evolutionState());
    }

    // ══════════ الطلبُ الطويل: ما تراه الشاشةُ أثناءه وبعده ══════════

    /**
     * طلبُ ربطٍ يعمل الآن عند الخادم (أو قطعه الوسيطُ قبل ردّه وما زال يحذف وينشئ):
     * سؤالُ الحالة يقول منذ متى، ولا يسأل الجسرَ — سؤالٌ ثانٍ يتنازع معه ويشغل عاملاً آخر.
     */
    public function test_the_state_poll_reports_a_running_request_without_asking_the_bridge(): void
    {
        Http::fake();
        \Illuminate\Support\Facades\Cache::put('wa:pair:running:' . WhatsAppSettings::evolutionInstance(), microtime(true) - 12, 120);

        $this->actingAs($this->admin)->getJson(route('settings.whatsapp.pair-state'))
            ->assertOk()
            ->assertJsonPath('state', 'connecting')
            ->assertJsonPath('preparing', true)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'منذ 12 ث'));

        Http::assertNothingSent();
    }

    /**
     * طلبٌ قطعه الوسيطُ (٥٠٤) ثمّ أخفق في الخلفية: الشاشةُ التي تتابع تقرأ سببَه ورحلتَه
     * من آخر محاولة — وإلا بقيت تسأل عن جلسةٍ لم تُفتح حتى تيأس بلا سبب.
     */
    public function test_a_failed_background_attempt_reaches_the_poller_for_two_minutes(): void
    {
        Http::fake(fn () => Http::response(['instance' => ['state' => 'close']], 200));
        $key = 'wa:pair:last:' . WhatsAppSettings::evolutionInstance();
        $attempt = ['at' => time() - 30, 'state' => 'close', 'message' => 'انقضى موعدُ الطلب (30 ث) قبل إنشاء الجلسة',
            'trace' => ['الحذف: مهلةٌ بلا ردّ (10.0 ث)'], 'took' => 31.2];
        \Illuminate\Support\Facades\Cache::put($key, $attempt, 600);

        $this->actingAs($this->admin)->getJson(route('settings.whatsapp.pair-state'))
            ->assertOk()
            ->assertJsonPath('state', 'close')
            ->assertJsonPath('failed', true)
            ->assertJsonPath('message', $attempt['message'])
            ->assertJsonPath('trace.0', 'الحذف: مهلةٌ بلا ردّ (10.0 ث)')
            ->assertJsonPath('took', 31.2);

        // محاولةٌ قديمة (أكثرُ من دقيقتين) ليست خبراً عمّا يجري الآن
        \Illuminate\Support\Facades\Cache::put($key, ['at' => time() - 300] + $attempt, 600);

        $this->actingAs($this->admin)->getJson(route('settings.whatsapp.pair-state'))
            ->assertOk()
            ->assertJsonMissingPath('failed');
    }

    /** ومحاولةٌ أخفقت لا تحجب جلسةً حيّةً فُتحت بعدها. */
    public function test_a_failed_record_never_overrides_a_live_session(): void
    {
        $this->waitingBridge('Q1W2E3R4', 5);
        \Illuminate\Support\Facades\Cache::put('wa:pair:last:' . WhatsAppSettings::evolutionInstance(),
            ['at' => time() - 10, 'state' => 'close', 'message' => 'أخفق', 'trace' => [], 'took' => 9.0], 600);

        $this->actingAs($this->admin)->getJson(route('settings.whatsapp.pair-state'))
            ->assertOk()
            ->assertJsonPath('state', 'connecting')
            ->assertJsonPath('code', 'Q1W2E3R4')
            ->assertJsonMissingPath('failed');
    }

    /** ردُّ «اطلب رمزاً» يحمل رحلتَه ومدّتَه — صورةٌ من الشاشة تكفي لتشخيص الجسر. */
    public function test_the_pair_answer_carries_its_journey_and_duration(): void
    {
        $this->waitingBridge();

        $this->actingAs($this->admin)->postJson(route('settings.whatsapp.pair'), ['phone' => '96894249400'])
            ->assertOk()
            ->assertJsonPath('state', 'connecting')
            ->assertJsonPath('code', 'K7M2QX9P')
            ->assertJsonPath('trace', fn ($t) => is_array($t) && count($t) >= 3 && str_starts_with($t[0], 'الحالة: connecting'))
            ->assertJsonPath('took', fn ($t) => is_numeric($t) && $t < 5);
    }

    /** محاولةٌ أخفقت قبل أن تبدأ الشاشةُ متابعتَها (ضغطةٌ سابقة) ليست خبرَ هذه الضغطة. */
    public function test_a_failure_older_than_the_pollers_own_wait_is_not_its_news(): void
    {
        Http::fake(fn () => Http::response(['instance' => ['state' => 'close']], 200));
        \Illuminate\Support\Facades\Cache::put('wa:pair:last:' . WhatsAppSettings::evolutionInstance(),
            ['at' => time() - 30, 'state' => 'close', 'message' => 'أخفق', 'trace' => [], 'took' => 9.0], 600);

        $this->actingAs($this->admin)->getJson(route('settings.whatsapp.pair-state', ['elapsed' => 10]))
            ->assertOk()->assertJsonMissingPath('failed');

        $this->actingAs($this->admin)->getJson(route('settings.whatsapp.pair-state', ['elapsed' => 40]))
            ->assertOk()->assertJsonPath('failed', true);
    }

    /** إنشاءٌ قُبل والجسرُ بعدُ يفتح جلستَه: «close» في السؤال ليس انتهاءً بل انتظارٌ يُقال بمدّته. */
    public function test_the_state_poll_waits_while_an_accepted_create_is_still_opening(): void
    {
        Http::fake(fn () => Http::response(['instance' => ['state' => 'close']], 200));
        \Illuminate\Support\Facades\Cache::put('wa:pair:pending:' . WhatsAppSettings::evolutionInstance(), time() - 9, 90);

        $this->actingAs($this->admin)->getJson(route('settings.whatsapp.pair-state'))
            ->assertOk()
            ->assertJsonPath('state', 'connecting')
            ->assertJsonPath('preparing', true)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'منذ 9 ث'));
    }
}
