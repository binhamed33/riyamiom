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
}
