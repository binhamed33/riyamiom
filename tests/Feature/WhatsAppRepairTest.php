<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * أمرُ إصلاحٍ واحد يُشغَّل على كلّ مكتب.
 *
 * ما يحرسه: أنّه يُصلح فعلاً (نسخةٌ ميتةٌ تُستبدل، ورسالةٌ ماتت بانقطاعٍ
 * عابرٍ تعود)، وأنّه لا يتجاوز قرارَ المكتب: رقمٌ فصله صاحبُه لا يُعاد
 * وصلُه، ونسخةٌ موصولةٌ لا تُلمس.
 */
class WhatsAppRepairTest extends TestCase
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

    /** الحالةُ الميتة: يُحذف الصفُّ ويُنشأ نظيفٌ ويُعرض الرمز. */
    public function test_it_heals_a_dead_instance_so_the_office_gets_a_code(): void
    {
        $deleted = false;

        Http::fake(function ($request) use (&$deleted) {
            $url = $request->url();

            if (str_contains($url, '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'close']], 200);
            }
            if (str_contains($url, '/instance/delete/')) {
                $deleted = true;

                return Http::response([], 200);
            }
            if (str_contains($url, '/instance/create')) {
                return $deleted
                    ? Http::response(['qrcode' => ['base64' => 'iVBORw0KGgo=']], 201)
                    : Http::response(['response' => ['message' => ['This name is already in use.']]], 403);
            }
            if (str_contains($url, '/instance/connect/')) {
                return $deleted
                    ? Http::response(['base64' => 'iVBORw0KGgo='], 200)
                    : Http::response(['response' => ['message' => ['The "x" instance does not exist']]], 400);
            }

            return Http::response([], 201);
        });

        $this->artisan('whatsapp:repair')
            ->expectsOutputToContain('رمزُ الاقتران')
            ->assertSuccessful();

        $this->assertTrue($deleted);
    }

    /** رقمٌ فصله المكتبُ بنفسه لا يُعاد وصلُه — فصلُه قرارُه. */
    public function test_it_respects_an_office_that_disconnected_itself(): void
    {
        Setting::set(WhatsAppSettings::KEY_DISCONNECTED, '1', 'whatsapp');
        Http::fake();

        $this->artisan('whatsapp:repair')
            ->expectsOutputToContain('فصل رقمه بنفسه')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    /** ورقمٌ موصولٌ لا تُلمس نسختُه — يُضبط عنوانُ الاستقبال وحده. */
    public function test_a_connected_office_is_left_alone(): void
    {
        $deleteCalled = false;

        Http::fake(function ($request) use (&$deleteCalled) {
            if (str_contains($request->url(), '/instance/delete/')) {
                $deleteCalled = true;
            }
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'open']], 200);
            }

            return Http::response([], 201);
        });

        $this->artisan('whatsapp:repair')
            ->expectsOutputToContain('الرقم موصول')
            ->assertSuccessful();

        $this->assertFalse($deleteCalled);
    }

    /** ورسالةٌ ماتت بانقطاعٍ عابرٍ تعود إلى الطابور — لا تُدفن. */
    public function test_messages_killed_by_a_brief_outage_are_requeued(): void
    {
        Queue::fake();

        $contact = WhatsAppContact::create(['wa_id' => '96891234567']);
        $conversation = WhatsAppConversation::create([
            'contact_id' => $contact->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
        ]);

        $dead = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => 'تحديث قضيتك',
            'status' => WhatsAppMessage::STATUS_FAILED,
            'error_title' => 'Connection Closed',
        ]);

        $genuine = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => 'رسالة لرقم خاطئ',
            'status' => WhatsAppMessage::STATUS_FAILED,
            'error_title' => 'The number is not registered on WhatsApp',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'open']], 200);
            }

            return Http::response([], 201);
        });

        $this->artisan('whatsapp:repair')->assertSuccessful();

        $this->assertSame(WhatsAppMessage::STATUS_QUEUED, $dead->fresh()->status);
        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $genuine->fresh()->status,
            'أُعيدت رسالةٌ عطلُها دائم — تكرارُها ضجيجٌ بلا فائدة');
        Queue::assertPushed(\App\Jobs\SendWhatsAppMessage::class, 1);
    }

    /** ومكتبٌ على Meta لا يُصلَح بهذا الأمر ولا يُكسر. */
    public function test_a_meta_office_is_untouched(): void
    {
        config(['whatsapp.default' => 'meta']);
        Http::fake();

        $this->artisan('whatsapp:repair')
            ->expectsOutputToContain('مزوّد Meta')
            ->assertSuccessful();

        Http::assertNothingSent();
    }
}
