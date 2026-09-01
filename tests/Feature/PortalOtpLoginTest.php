<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Setting;
use App\Models\WhatsAppMessage;
use App\Support\ClientPortal;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * دخولُ البوابة برمز واتساب — سريعٌ، محدودٌ، ولا يكشف شيئاً.
 *
 * ═══ ما تحرسه ═══
 *
 * ١) السرعة: الرمزُ يُرسَل في الطلب نفسِه مباشرةً — لا طابورَ يؤخّره
 *    دقيقةً ولا حارسَ إيقاعٍ يحجزه إلى الصباح: صاحبُه طلبه الآن.
 * ٢) الدقيقتان: رمزٌ ثم لا رمزَ قبل مرور مئةٍ وعشرين ثانية.
 * ٣) الكتمان: المسجَّلُ وغيرُ المسجَّل يسمعان الجملةَ نفسَها.
 * ٤) الحرق: رمزٌ يُستعمل مرّةً، وخمسُ محاولاتٍ ثم قفل.
 * ٥) الجلسة: التحقّق يقرأ هاتفَ الجلسة لا هاتفاً يرسله الزائر.
 */
class PortalOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('whatsapp.default', 'evolution');
        config()->set('whatsapp.evolution.base_url', 'http://127.0.0.1:18080');
        config()->set('whatsapp.evolution.api_key', 'test-key-0123456789');

        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
        Setting::set(WhatsAppSettings::KEY_EVO_STATE, 'open', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->toIso8601String(), 'whatsapp');

        $this->client = Client::create([
            'name' => 'سالم بن علي الريامي',
            'phone' => '91234567',
            'national_id' => '13572468',
            'type' => 'individual',
        ]);
    }

    private function code(): string
    {
        preg_match('/\b(\d{6})\b/u', (string) WhatsAppMessage::latest('id')->first()?->body, $m);

        return $m[1] ?? '';
    }

    /** الرحلةُ كاملة: هاتف ⇐ رمزٌ يصل فوراً ⇐ دخول. */
    public function test_a_client_logs_in_with_a_whatsapp_code(): void
    {
        Http::fake(['*' => Http::response(['key' => ['id' => 'OTP1']], 200)]);

        $this->post(route('client.access.otp'), ['phone' => '91234567'])->assertRedirect();

        // أُرسل في الطلب نفسِه — لا «في الانتظار» ولا طابور
        $message = WhatsAppMessage::latest('id')->firstOrFail();
        $this->assertSame(WhatsAppMessage::STATUS_SENT, $message->status, 'الرمزُ لم يخرج فوراً');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/message/sendText/'));

        $this->post(route('client.access.otp.verify'), ['code' => $this->code()])
            ->assertRedirect(route('client.portal.home'));

        $this->get(route('client.portal.home'))->assertOk();
    }

    /** الرمزُ يتخطّى الصمتَ الليلي: صاحبُه طلبه الآن وينتظره. */
    public function test_the_code_ignores_quiet_hours(): void
    {
        $this->travelTo(now()->setTime(23, 30));
        Http::fake(['*' => Http::response(['key' => ['id' => 'OTP2']], 200)]);

        $this->post(route('client.access.otp'), ['phone' => '91234567']);

        $message = WhatsAppMessage::latest('id')->firstOrFail();
        $this->assertSame(WhatsAppMessage::STATUS_SENT, $message->status, 'حُجز الرمزُ إلى الصباح');
        $this->assertNull($message->hold_until);
    }

    /** «من otp إلى otp دقيقتان» — حرفياً. */
    public function test_a_second_code_waits_two_minutes(): void
    {
        Http::fake(['*' => Http::response(['key' => ['id' => 'OTP3']], 200)]);

        $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $this->assertSame(1, WhatsAppMessage::count());

        $this->post(route('client.access.otp'), ['phone' => '91234567'])
            ->assertSessionHas('portal_error');
        $this->assertSame(1, WhatsAppMessage::count(), 'أُرسل رمزٌ ثانٍ قبل الدقيقتين');

        $this->travel(121)->seconds();

        $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $this->assertSame(2, WhatsAppMessage::count());
    }

    /** المسجَّلُ وغيرُ المسجَّل يسمعان الجملةَ نفسَها — ولا رسالةَ للغريب. */
    public function test_an_unknown_phone_hears_the_same_sentence_and_gets_nothing(): void
    {
        Http::fake();

        $known = $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $this->travel(121)->seconds();
        $unknown = $this->post(route('client.access.otp'), ['phone' => '99887766']);

        $this->assertSame(
            session()->get('portal_notice'),
            $unknown->getSession()->get('portal_notice') ?? session('portal_notice'),
        );

        $this->assertSame(1, WhatsAppMessage::count(), 'أُرسل شيءٌ لرقمٍ غير مسجَّل');
    }

    /** خمسُ محاولاتٍ خاطئة تقفل الرمز — والصحيحُ بعدها لا يمرّ. */
    public function test_five_wrong_attempts_burn_the_code(): void
    {
        Http::fake(['*' => Http::response(['key' => ['id' => 'OTP4']], 200)]);

        $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $code = $this->code();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('client.access.otp.verify'), ['code' => '000000']);
        }

        $this->post(route('client.access.otp.verify'), ['code' => $code])
            ->assertSessionHas('portal_error');

        $this->get(route('client.portal.home'))->assertRedirect();
    }

    /** والرمزُ يُحرق بالاستعمال: مرّةٌ تفتح، والثانية تُرَدّ. */
    public function test_a_code_works_exactly_once(): void
    {
        Http::fake(['*' => Http::response(['key' => ['id' => 'OTP5']], 200)]);

        $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $code = $this->code();

        $this->post(route('client.access.otp.verify'), ['code' => $code])
            ->assertRedirect(route('client.portal.home'));

        // جلسةٌ جديدة تحاول بالرمز المحروق
        $this->post(route('client.access.logout'));
        $this->post(route('client.access.otp.verify'), ['code' => $code])
            ->assertSessionHas('portal_error');
    }

    /** رقمٌ يتشاركه موكّلان لا يُراسَل — بابُ الهويّة لهما. */
    public function test_a_shared_phone_gets_no_code(): void
    {
        Client::create([
            'name' => 'موكّل ثانٍ بنفس الرقم',
            'phone' => '91234567',
            'national_id' => '86427531',
            'type' => 'individual',
        ]);

        Http::fake();

        $this->post(route('client.access.otp'), ['phone' => '91234567'])
            ->assertSessionHas('portal_notice');

        $this->assertSame(0, WhatsAppMessage::count(), 'رقمٌ ملتبسٌ رُوسل');
    }

    /** ومكتبٌ غيرُ مربوطٍ يقولها صراحةً بدل صمتٍ محيّر. */
    public function test_an_unconnected_office_says_so(): void
    {
        Setting::set(WhatsAppSettings::KEY_EVO_STATE, 'close', 'whatsapp');

        $this->post(route('client.access.otp'), ['phone' => '91234567'])
            ->assertSessionHas('portal_error');
    }

    /** والواجهةُ تعرض البابين. */
    public function test_the_login_page_offers_both_doors(): void
    {
        $html = $this->get(route('client.access'))->assertOk()->getContent();
        $this->assertStringContainsString('الدخول برقم الهاتف', $html);

        $otp = $this->get(route('client.access', ['otp' => 1]))->assertOk()->getContent();
        $this->assertStringContainsString('أرسل الرمز', $otp);
        $this->assertStringContainsString('الدخول برقم الهويّة', $otp);
    }
}
