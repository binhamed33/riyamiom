<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * «ما ينفصل أبداً إلا إذا المكتب فصله».
 *
 * جلسةُ الجسر تسقط أحياناً والاعتمادُ صالح: كان المكتب يبقى مفصولاً
 * حتى يفتح أحدُهم الإعدادات صدفةً، والرسائلُ تُقيَّد «في البوابة»
 * أياماً. فصار الكنسُ — كلَّ دورةٍ — يجرّب `connect` ويقرأ الحالة.
 *
 * والحدّان محفوظان: الفصلُ الصريحُ بيد المكتب مقدَّسٌ لا يُنقض،
 * ومن لم يقترن قطُّ لا يُطرق بابُه.
 */
class WhatsAppRevivalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('whatsapp.default', 'evolution');
        config()->set('whatsapp.evolution.base_url', 'http://127.0.0.1:18080');
        config()->set('whatsapp.evolution.api_key', 'test-key-0123456789');
    }

    private function pairedThenDropped(): void
    {
        Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->subDay()->toIso8601String(), 'whatsapp');
        Setting::set('wa_evo_state', 'close', 'whatsapp');
    }

    public function test_a_dropped_session_is_reconnected_by_the_sweep(): void
    {
        $this->pairedThenDropped();

        $connectCalled = false;
        Http::fake(function ($request) use (&$connectCalled) {
            if (str_contains($request->url(), '/instance/connect/')) {
                $connectCalled = true;

                return Http::response(['instance' => []], 200);
            }

            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'open']], 200);
            }

            return Http::response([], 200);
        });

        $this->artisan('whatsapp:sweep')->assertSuccessful();

        $this->assertTrue($connectCalled, 'الكنسُ لم يطرق بابَ الوصل');
        $this->assertSame('open', WhatsAppSettings::evolutionState(), 'الحالةُ لم تُحدَّث بعد الوصل');
    }

    /** الفصلُ الصريح بيد المكتب لا يُنقَض — ولو كانت الجلسةُ صالحة. */
    public function test_an_explicit_disconnect_is_never_overridden(): void
    {
        $this->pairedThenDropped();
        Setting::set('wa_disconnected', '1', 'whatsapp');

        Http::fake();

        $this->artisan('whatsapp:sweep')->assertSuccessful();

        Http::assertNothingSent();
    }

    /** ومن لم يقترن قطُّ لا يُطرق بابُه — لا معنى لإحياء ما لم يولد. */
    public function test_a_never_paired_office_is_left_alone(): void
    {
        Setting::set('wa_evo_state', 'close', 'whatsapp');

        Http::fake();

        $this->artisan('whatsapp:sweep')->assertSuccessful();

        Http::assertNothingSent();
    }

    /** واقترانٌ حيٌّ لا يُلمس: لا نداءَ وصلٍ على حالة open. */
    public function test_an_open_session_is_not_touched(): void
    {
        Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->subDay()->toIso8601String(), 'whatsapp');
        Setting::set('wa_evo_state', 'open', 'whatsapp');

        Http::fake();

        $this->artisan('whatsapp:sweep')->assertSuccessful();

        Http::assertNothingSent();
    }
}
